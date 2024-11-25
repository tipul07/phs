<?php
namespace phs\system\core\events\layout;

use phs\libraries\PHS_Event;

class PHS_Event_Layout_buffer extends PHS_Event
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
            'view_obj' => null,
            // Added only for hook backwards compatibility
            'concatenate_buffer' => 'buffer',
            'buffer_data'        => [],
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'buffer_data' => [],
            'buffer'      => '',
        ];
    }
}
