<?php

namespace phs\system\core\events\models;

use phs\libraries\PHS_Event;

class PHS_Event_Model_Fields extends PHS_Event
{
    /**
     * @inheritdoc
     */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters() : array
    {
        return [
            'model_instance_id'  => '',
            'plugin_instance_id' => '',
            'flow_params'        => [],
            'fields_arr'         => [],
            'model_obj'          => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'fields_arr' => [],
        ];
    }
}
