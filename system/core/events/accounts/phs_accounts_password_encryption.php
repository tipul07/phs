<?php

namespace phs\system\core\events\accounts;

use Closure;
use phs\libraries\PHS_Event;

class PHS_Event_Accounts_password_encryption extends PHS_Event
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
            'password' => '',
            'salt'     => '',
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'encrypted_password' => '',
        ];
    }
}
