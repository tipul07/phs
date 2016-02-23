<?php

namespace phs\system\core\actions;

use \phs\libraries\PHS_Action;

class PHS_Action_Index extends PHS_Action
{
    public function execute()
    {
        if( !($view_obj = $this->init_view( 'test' )) )
        {
            var_dump( $this->get_error() );
            return false;
        }

        return self::validate_array( array( 'buffer' => $view_obj->render() ), self::default_action_result() );
    }
}
