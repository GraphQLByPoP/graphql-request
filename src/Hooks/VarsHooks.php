<?php

declare(strict_types=1);

namespace GraphQLByPoP\GraphQLRequest\Hooks;

use PoP\Hooks\AbstractHookSet;
use PoP\API\Schema\QueryInputs;
use PoP\API\State\ApplicationStateUtils;
use PoP\API\Response\Schemes as APISchemes;
use PoP\Translation\Facades\TranslationAPIFacade;
use GraphQLByPoP\GraphQLQuery\Schema\OperationTypes;
use GraphQLByPoP\GraphQLRequest\ComponentConfiguration;
use GraphQLByPoP\GraphQLRequest\Execution\QueryExecutionHelpers;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use GraphQLByPoP\GraphQLQuery\Facades\GraphQLQueryConvertorFacade;
use PoP\ComponentModel\CheckpointProcessors\MutationCheckpointProcessor;
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

        // Change the error message when mutations are not supported
        $this->hooksAPI->addFilter(
            MutationCheckpointProcessor::HOOK_MUTATIONS_NOT_SUPPORTED_ERROR_MSG,
            array($this, 'getMutationsNotSupportedErrorMessage')
        );
    }

    /**
     * @param array<array> $vars_in_array
     */
    public function getMutationsNotSupportedErrorMessage(): string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return sprintf(
            $translationAPI->__('Use the operation type \'%s\' to execute mutations', 'graphql-request'),
            OperationTypes::MUTATION
        );
    }

    /**
     * @param array<array> $vars_in_array
     */
    public function addURLParamVars(array $vars_in_array): void
    {
        [&$vars] = $vars_in_array;
        if ($vars['scheme'] == APISchemes::API && $vars['datastructure'] == GraphQLDataStructureFormatter::getName()) {
            $this->processURLParamVars($vars);
        }
    }

    /**
     * @param array<string, mixed> $vars
     */
    protected function processURLParamVars(array &$vars): void
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
        list(
            $operationType,
            $fieldQuery
        ) = $graphQLQueryConvertor->convertFromGraphQLToFieldQuery(
            $graphQLQuery,
            $variables,
            ComponentConfiguration::enableMultipleQueryExecution(),
            $operationName
        );

        // Set the operation type and, based on it, if mutations are supported
        $vars['graphql-operation-type'] = $operationType;
        $vars['are-mutations-executable'] = $operationType == OperationTypes::MUTATION;

        // Set the query in $vars
        ApplicationStateUtils::maybeConvertQueryAndAddToVars($vars, $fieldQuery);

        // Do not include the fieldArgs and directives when outputting the field
        $vars['only-fieldname-as-outputkey'] = true;
    }
}
