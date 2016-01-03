<?php

namespace phs\system\core\controllers;

use \phs\libraries\PHS_Controller;

class PHS_Controller_Index extends PHS_Controller
{
    public function get_models()
    {
        return array();
    }

    public function action_index()
    {
        if( !($view_obj = $this->init_view( 'test' )) )
        {
            var_dump( $this->get_error() );
            return false;
        }

        echo $view_obj->render();
    }
}
