<?php

namespace phs\system\core\events\plugins;

use phs\libraries\PHS_Event;

class PHS_Event_Plugin_settings_saved extends PHS_Event
{
    /** @inheritdoc */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters(): array
    {
        return [
            'tenant_id' => 0, // for which tenant we save the details
            'instance_id' => '', // eg. plugin:accounts:accounts
            'instance_type' => '', // eg. plugin, model
            'plugin_name' => '', // eg. accounts
            'old_settings_arr' => [], // previous settings array
            'new_settings_arr' => [], // new settings array
            'obfucate_keys_arr' => [], // keys which were obfuscated
        ];
    }

    protected function _output_parameters(): array
    {
        return [];
    }
}
