<?php

declare(strict_types=1);

namespace PoP\GraphQLAPIRequest;

use PoP\ComponentModel\ComponentConfiguration\EnvironmentValueHelpers;
use PoP\ComponentModel\ComponentConfiguration\ComponentConfigurationTrait;

class ComponentConfiguration
{
    use ComponentConfigurationTrait;

    private static $disableGraphQLAPIForPoP;

    public static function disableGraphQLAPIForPoP(): bool
    {
        // Define properties
        $envVariable = Environment::DISABLE_GRAPHQL_API_FOR_POP;
        $selfProperty = &self::$disableGraphQLAPIForPoP;
        $defaultValue = false;
        $callback = [EnvironmentValueHelpers::class, 'toBool'];

        // Initialize property from the environment/hook
        self::maybeInitializeConfigurationValue(
            $envVariable,
            $selfProperty,
            $defaultValue,
            $callback
        );
        return $selfProperty;
    }
}
