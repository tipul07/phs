<?php

namespace phs\system\core\events\plugins;

use phs\libraries\PHS_Event;

class PHS_Event_Plugin_settings extends PHS_Event
{
    /** @inheritdoc */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters(): array
    {
        return [
            'tenant_id' => 0,
            'instance_id' => '',
            'settings_arr' => [],
        ];
    }

    protected function _output_parameters(): array
    {
        return [
            'settings_arr' => [],
        ];
    }
}
