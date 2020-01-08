<?php
namespace PoP\APIGraphQLRequest\Hooks;

use PoP\API\Schema\QueryInputs;
use PoP\Engine\Hooks\AbstractHookSet;
use PoP\API\Schema\FieldQueryConvertorUtils;

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
            // TODO: Get $payload
            $payload = false;
            if ($payload) {
                // The fields param can either be an array or a string. Convert them to array
                $vars['query'] = FieldQueryConvertorUtils::getQueryAsArray($payload);
            }
        }
    }
}
