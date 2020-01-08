<?php
namespace PoP\GraphQLAPIRequest\Hooks;

use PoP\API\Schema\QueryInputs;
use PoP\Engine\Hooks\AbstractHookSet;
use PoP\API\Schema\FieldQueryConvertorUtils;
use PoP\GraphQLAPIQuery\Facades\GraphQLQueryConvertorFacade;

class VarsHooks extends AbstractHookSet
{
    protected function init()
    {
        $this->hooksAPI->addAction(
            '\PoP\ComponentModel\Engine_Vars:addVars',
            array($this, 'addURLParamVars'),
            10,
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
        // If the "query" param is set, this case is already handled in API package
        if (!isset($_REQUEST[QueryInputs::QUERY])) {
            // Attempt to get the query from the body, following the GraphQL syntax
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
                $rawBody     = file_get_contents('php://input');
                $requestData = json_decode($rawBody ?: '', true);
            } else {
                $requestData = $_POST;
            }
            // Maybe override the variables, getting them from the GraphQL dictionary
            $variables = isset($requestData['variables']) ? $requestData['variables'] : null;
            if ($variables) {
                $vars['variables'] = $variables;
            }
            // Get the query, transform it, and set it on $vars
            $graphQLQuery = isset($requestData['query']) ? $requestData['query'] : null;
            if ($graphQLQuery) {
                // Convert from GraphQL syntax to Field Query syntax
                $graphQLQueryConvertor = GraphQLQueryConvertorFacade::getInstance();
                $fieldQuery = $graphQLQueryConvertor->convertFromGraphQLToFieldQuery($graphQLQuery, $variables);
                // Convert the query to an array
                $vars['query'] = FieldQueryConvertorUtils::getQueryAsArray($fieldQuery);
            }
        }
    }
}
