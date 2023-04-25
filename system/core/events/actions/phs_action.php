<?php
namespace phs\system\core\events\actions;

use phs\libraries\PHS_Event;

class PHS_Event_Action extends PHS_Event
{
    protected function _input_parameters() : array
    {
        return [
            'action_obj'         => null,
            'page_template'      => null,
            'page_template_args' => [],
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'stop_execution' => false,
            // If listeners want to change action result, they should set it in action_result index
            'action_result' => null,
        ];
    }
}
