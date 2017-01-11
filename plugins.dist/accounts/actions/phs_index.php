<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;

class PHS_Action_Index extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB );
    }

    public function execute()
    {
        return $this->quick_render_template( 'login' );
    }
}
