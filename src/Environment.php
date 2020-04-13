<?php

declare(strict_types=1);

namespace PoP\GraphQLAPIRequest;

class Environment
{
    public static function disableGraphQLAPIForPoP(): bool
    {
        return isset($_ENV['DISABLE_GRAPHQL_API_FOR_POP']) ? strtolower($_ENV['DISABLE_GRAPHQL_API_FOR_POP']) == "true" : false;
    }
}
