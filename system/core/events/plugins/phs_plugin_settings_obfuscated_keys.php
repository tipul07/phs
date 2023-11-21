<?php

namespace phs\system\core\events\plugins;

use phs\libraries\PHS_Event;

class PHS_Event_Plugin_settings_obfuscated_keys extends PHS_Event
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
            'obfucate_keys_arr' => [],
        ];
    }

    protected function _output_parameters(): array
    {
        return [
            'obfucate_keys_arr' => [],
        ];
    }
}
