<?php

namespace phs\system\core\actions;

use phs\libraries\PHS_Action;
use phs\system\core\views\PHS_View;

class PHS_Action_Tandc extends PHS_Action
{
    public function execute()
    {
        return $this->quick_render_template('terms_and_conditions');
    }
}
