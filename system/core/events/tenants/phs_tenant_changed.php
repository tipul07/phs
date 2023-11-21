<?php
namespace phs\system\core\events\tenants;

use phs\libraries\PHS_Event;

class PHS_Event_Tenant_changed extends PHS_Event
{
    protected function _input_parameters() : array
    {
        return [
            'old_tenant' => null,
            'new_tenant' => null,
        ];
    }

    /**
     * @inheritdoc
     * @see \phs\PHS::validate_route_from_parts()
     * @return array[]
     */
    protected function _output_parameters() : array
    {
        return [];
    }
}
