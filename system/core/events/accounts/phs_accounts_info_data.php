<?php
namespace phs\system\core\events\accounts;

use phs\libraries\PHS_Event;

class PHS_Event_Accounts_info_data extends PHS_Event
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
            'account_data'         => null,
            'account_details_data' => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'template_data' => [],
        ];
    }
}
