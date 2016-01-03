<?php

namespace phs\plugins\accounts\controllers;

use \phs\libraries\PHS_Controller;

class PHS_Controller_Index extends PHS_Controller
{
    public function get_models()
    {
        return array( 'accounts', 'accounts_details' );
    }

    public function action_index()
    {
        if( !($view_obj = $this->init_view( 'test' )) )
            return false;

        echo $view_obj->render();
    }
}
