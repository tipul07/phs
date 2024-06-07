<?php

namespace phs\system\core\events\models;

use phs\libraries\PHS_Event;

class PHS_Event_Model_hard_delete extends PHS_Event
{
    protected function _input_parameters() : array
    {
        return [
            'flow_params' => [],
            'record_data' => null,
            'model_obj'   => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'stop_hard_delete' => false,
            'flow_params'      => [],
        ];
    }
}
