<?php

declare(strict_types=1);

namespace PoP\GraphQLAPIRequest\Hooks;

use PoP\API\Schema\QueryInputs;
use PoP\Engine\Hooks\AbstractHookSet;
use PoP\API\State\ApplicationStateUtils;
use PoP\GraphQLAPIRequest\ComponentConfiguration;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\GraphQLAPIRequest\Execution\QueryExecutionHelpers;
use PoP\GraphQLAPIQuery\Facades\GraphQLQueryConvertorFacade;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use PoP\GraphQLAPI\DataStructureFormatters\GraphQLDataStructureFormatter;

class VarsHooks extends AbstractHookSet
{
    protected function init()
    {
        // Priority 20: execute after the same code in API, as to remove $vars['query]
        $this->hooksAPI->addAction(
            'ApplicationState:addVars',
            array($this, 'addURLParamVars'),
            20,
            1
        );
    }

    public function addURLParamVars($vars_in_array)
    {
        $vars = &$vars_in_array[0];
        if ($vars['scheme'] == \POP_SCHEME_API && $vars['datastructure'] == GraphQLDataStructureFormatter::getName()) {
            $this->processURLParamVars($vars);
        }
    }

    protected function processURLParamVars(&$vars)
    {
        if (isset($_REQUEST[QueryInputs::QUERY]) && ComponentConfiguration::disableGraphQLAPIForPoP()) {
            // Remove the query set by package API
            unset($vars['query']);
        }
        // If the "query" param is set, this case is already handled in API package
        if (!isset($_REQUEST[QueryInputs::QUERY]) || ComponentConfiguration::disableGraphQLAPIForPoP()) {
            // Add a flag indicating that we are doing standard GraphQL
            // Do it already, so that even if there is no query, the error doesn't have "extensions"
            $vars['standard-graphql'] = true;

            // Process the query, or show an error if empty
            list(
                $graphQLQuery,
                $variables,
                $operationName
            ) = QueryExecutionHelpers::extractRequestedGraphQLQueryPayload();
            if ($graphQLQuery) {
                // Maybe override the variables, getting them from the GraphQL dictionary
                if ($variables) {
                    $vars['variables'] = $variables;
                }
                $this->addGraphQLQueryToVars($vars, $graphQLQuery, $operationName);
            } else {
                $translationAPI = TranslationAPIFacade::getInstance();
                $feedbackMessageStore = FeedbackMessageStoreFacade::getInstance();
                $errorMessage = (isset($_REQUEST[QueryInputs::QUERY]) && ComponentConfiguration::disableGraphQLAPIForPoP()) ?
                    $translationAPI->__('No query was provided. (The body has no query, and the query provided as a URL param is ignored because of configuration)', 'graphql-request') :
                    $translationAPI->__('The query in the body is empty', 'graphql-request');
                $feedbackMessageStore->addQueryError($errorMessage);
            }
        }
    }

    /**
     * Function is public so it can be invoked from the WordPress plugin
     *
     * @param array $vars
     * @param string $graphQLQuery
     * @return void
     */
    public function addGraphQLQueryToVars(array &$vars, string $graphQLQuery, ?string $operationName = null)
    {
        // Take the existing variables from $vars, so they must be set in advance
        $variables = $vars['variables'] ?? [];
        // Convert from GraphQL syntax to Field Query syntax
        $graphQLQueryConvertor = GraphQLQueryConvertorFacade::getInstance();
        $fieldQuery = $graphQLQueryConvertor->convertFromGraphQLToFieldQuery(
            $graphQLQuery,
            $variables,
            $operationName
        );

        // Set the query in $vars
        ApplicationStateUtils::maybeConvertQueryAndAddToVars($vars, $fieldQuery);

        // Do not include the fieldArgs and directives when outputting the field
        $vars['only-fieldname-as-outputkey'] = true;
    }
}
