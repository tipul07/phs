<?php
namespace phs\plugins\admin\controllers;

use phs\PHS_Scope;

class PHS_Controller_Api extends \phs\libraries\PHS_Controller_Api
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_API, PHS_Scope::SCOPE_AJAX];
    }
}
