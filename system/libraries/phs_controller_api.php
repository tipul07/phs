<?php
namespace phs\libraries;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;

abstract class PHS_Controller_Api extends PHS_Controller
{
    /**
     * Returns an array of scopes in which controller is allowed to run
     *
     * @return array If empty array, controller is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_API ];
    }
}
