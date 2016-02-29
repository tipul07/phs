<?php

namespace phs\plugins\accounts\actions;

use \phs\libraries\PHS_Action;
use \phs\system\core\views\PHS_View;

class PHS_Action_Index extends PHS_Action
{
    public function execute()
    {
        return $this->quick_render_template( 'test' );
    }
}
