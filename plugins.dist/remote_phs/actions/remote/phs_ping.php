<?php

namespace phs\plugins\remote_phs\actions\remote;

use phs\libraries\PHS_Remote_action;

class PHS_Action_Ping extends PHS_Remote_action
{
    /**
     * @return array|bool
     */
    public function execute()
    {
        return $this->send_api_success(['pong' => true]);
    }
}
