<?php

declare(strict_types=1);

namespace PoP\GraphQLAPIRequest\Execution;

class QueryExecutionHelpers
{
    public static function getRequestedGraphQLQueryAndVariables()
    {
        // Attempt to get the query from the body, following the GraphQL syntax
        if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
            $rawBody     = file_get_contents('php://input');
            $requestData = json_decode($rawBody ?: '', true);
        } else {
            $requestData = $_POST;
        }
        // Get the query, transform it, and set it on $vars
        return [
            $requestData['query'],
            $requestData['variables']
        ];
    }
}
