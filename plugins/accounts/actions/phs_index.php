<?php

namespace phs\plugins\accounts\actions;

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

        return $view_obj->render();
    }
}
