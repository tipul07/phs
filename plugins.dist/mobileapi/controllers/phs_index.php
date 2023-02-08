<?php
namespace phs\plugins\mobileapi\controllers;

use phs\PHS_Scope;

class PHS_Controller_Index extends \phs\libraries\PHS_Controller_Index
{
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX, PHS_Scope::SCOPE_API];
    }
}
