<?php

namespace phs\plugins\mobileapi\controllers;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;

class PHS_Controller_Index extends PHS_Controller
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX, PHS_Scope::SCOPE_API );
    }
}
