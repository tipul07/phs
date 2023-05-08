<?php

namespace phs\system\core\events\plugins;

use phs\libraries\PHS_Event;

class PHS_Event_Plugin_registry extends PHS_Event
{
    /** @inheritdoc */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters(): array
    {
        return [
            'instance_id' => '',
            'registry_arr' => [],
        ];
    }

    protected function _output_parameters(): array
    {
        return [
            'registry_arr' => [],
        ];
    }
}
