<?php
namespace phs\system\core\events\models;

use Closure;
use phs\libraries\PHS_Event;

class PHS_Event_Model_validate_data_fields extends PHS_Event
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
            'table_fields'       => [],
            'model_obj'          => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'flow_params'  => [],
            'table_fields' => [],
        ];
    }
}
