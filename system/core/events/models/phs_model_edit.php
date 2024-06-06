<?php

namespace phs\system\core\events\models;

use phs\libraries\PHS_Event;

class PHS_Event_Model_edit extends PHS_Event
{
    protected function _input_parameters() : array
    {
        return [
            'flow_params'     => [],
            'fields_arr'      => [],
            'record_data'     => null,
            'new_record_data' => null,
            'model_obj'       => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'record_data' => null,
        ];
    }
}
