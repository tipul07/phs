<?php

namespace phs\plugins\cookie_notice\controllers;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;

class PHS_Controller_Index extends PHS_Controller
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AJAX );
    }
}
