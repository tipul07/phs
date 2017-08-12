<?php

namespace phs\plugins\bbeditor\controllers;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Roles;

class PHS_Controller_Index extends PHS_Controller
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }
}
