<?php
namespace PoP\GraphQLAPIRequest\Hooks;

use PoP\API\Schema\QueryInputs;
use PoP\Engine\Hooks\AbstractHookSet;
use PoP\API\Schema\FieldQueryConvertorUtils;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\GraphQLAPIQuery\Facades\GraphQLQueryConvertorFacade;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use PoP\GraphQLAPIRequest\Environment;

class VarsHooks extends AbstractHookSet
{
    protected function init()
    {
        // Priority 20: execute after the same code in API, as to remove $vars['query]
        $this->hooksAPI->addAction(
            '\PoP\ComponentModel\Engine_Vars:addVars',
            array($this, 'addURLParamVars'),
            20,
            1
        );
    }

    public function addURLParamVars($vars_in_array)
    {
        $vars = &$vars_in_array[0];
        if ($vars['scheme'] == POP_SCHEME_API) {
            $this->addFieldsToVars($vars);
        }
    }

    private function addFieldsToVars(&$vars)
    {
        if (isset($_REQUEST[QueryInputs::QUERY]) && Environment::disableGraphQLAPIForPoP()) {
            // Remove the query set by package API
            unset($vars['query']);
        }
        // If the "query" param is set, this case is already handled in API package
        if (!isset($_REQUEST[QueryInputs::QUERY]) || Environment::disableGraphQLAPIForPoP()) {
            // Attempt to get the query from the body, following the GraphQL syntax
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
                $rawBody     = file_get_contents('php://input');
                $requestData = json_decode($rawBody ?: '', true);
            } else {
                $requestData = $_POST;
            }
            // Get the query, transform it, and set it on $vars
            $graphQLQuery = isset($requestData['query']) ? $requestData['query'] : null;
            if ($graphQLQuery) {
                // Maybe override the variables, getting them from the GraphQL dictionary
                $variables = isset($requestData['variables']) ? $requestData['variables'] : null;
                if ($variables) {
                    $vars['variables'] = $variables;
                }

                // Convert from GraphQL syntax to Field Query syntax
                $graphQLQueryConvertor = GraphQLQueryConvertorFacade::getInstance();
                $fieldQuery = $graphQLQueryConvertor->convertFromGraphQLToFieldQuery($graphQLQuery, $variables);
                // Convert the query to an array
                $vars['query'] = FieldQueryConvertorUtils::getQueryAsArray($fieldQuery);
                // Do not include the fieldArgs when outputting the field
                $vars['skip-fieldargs-from-outputkey'] = true;
            } else {
                $translationAPI = TranslationAPIFacade::getInstance();
                $errorMessage = $translationAPI->__('The query is empty', 'api-graphql-request');
                $feedbackMessageStore = FeedbackMessageStoreFacade::getInstance();
                $feedbackMessageStore->addQueryError($errorMessage);
            }
        }
    }
}
