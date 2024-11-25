<?php
namespace phs\libraries;

use phs\PHS_Scope;

abstract class PHS_Controller_Background extends PHS_Controller
{
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_BACKGROUND, PHS_Scope::SCOPE_AGENT];
    }
}
