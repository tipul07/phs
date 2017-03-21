<?php

namespace phs\plugins\backup\controllers;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;

class PHS_Controller_Index_bg extends PHS_Controller
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND, PHS_Scope::SCOPE_AGENT );
    }
}
