<?php
namespace phs\libraries;

use phs\PHS_Scope;

abstract class PHS_Controller_Api extends PHS_Controller
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_API];
    }
}
