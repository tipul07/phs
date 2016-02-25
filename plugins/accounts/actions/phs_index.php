<?php

namespace phs\plugins\accounts\actions;

use \phs\libraries\PHS_Action;
use \phs\system\core\views\PHS_View;

class PHS_Action_Index extends PHS_Action
{
    public function execute()
    {
        $view_params = array();
        $view_params['action_obj'] = $this;
        $view_params['controller_obj'] = $this->get_controller();
        $view_params['plugin'] = $this->instance_plugin_name();

        if( !($view_obj = PHS_View::init_view( 'test' )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        $action_result = self::default_action_result();

        $action_result['buffer'] = $view_obj->render();

        return $action_result;
    }
}
