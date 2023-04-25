<?php
namespace phs\system\core\events\layout;

use phs\libraries\PHS_Event;

class PHS_Event_Template_details extends PHS_Event
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
            // Previous action result (if any)
            'action_result' => null,
            // Added only for hook backwards compatibility
            'page_template' => '',
            'page_template_args'        => [],
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            // Resulting action result (if any)
            'action_result' => null,
            'page_template' => '',
            'page_template_args'        => [],
        ];
    }
}
