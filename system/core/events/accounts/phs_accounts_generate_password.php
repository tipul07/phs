<?php
namespace phs\system\core\events\accounts;

use phs\libraries\PHS_Event;

class PHS_Event_Accounts_generate_password extends PHS_Event
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
            'length' => 0,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'generated_password' => '',
        ];
    }
}
