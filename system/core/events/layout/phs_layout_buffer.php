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

    public function append_to_buffer(?string $buffer) : void
    {
        if ($buffer === null || $buffer === '') {
            return;
        }

        $this->set_output('buffer', $this->get_output('buffer').$buffer);
    }

    public function prepend_to_buffer(?string $buffer) : void
    {
        if ($buffer === null || $buffer === '') {
            return;
        }

        $this->set_output('buffer', $buffer.$this->get_output('buffer'));
    }

    public function get_buffer_data_input() : array
    {
        return $this->get_input('buffer_data') ?: [];
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
