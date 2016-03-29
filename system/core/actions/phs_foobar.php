<?php

namespace phs\system\core\actions;

use \phs\libraries\PHS_Action;

class PHS_Action_Foobar extends PHS_Action
{
    public function execute()
    {
        return self::default_action_result();
    }
}
