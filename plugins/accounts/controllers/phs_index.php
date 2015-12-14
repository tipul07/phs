<?php

namespace phs\plugins\accounts\controllers;

use \phs\libraries;

class PHS_Controller_Index extends \phs\libraries\PHS_Controller
{
    public function get_models()
    {
        return array( 'accounts', 'accounts_details' );
    }

    public function action_index()
    {
        echo 'Accounts index';
    }
}
