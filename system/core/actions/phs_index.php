<?php

namespace phs\system\core\actions;

use \phs\libraries\PHS_Action;

class PHS_Action_Index extends PHS_Action
{
    public function execute()
    {
        if( !($view_obj = $this->init_view( 'index' )) )
            return false;

        $action_result = self::default_action_result();

        $action_result['buffer'] = $view_obj->render();

        return $action_result;
    }
}
