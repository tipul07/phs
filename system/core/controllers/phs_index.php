<?php

namespace phs\system\core\controllers;

use \phs\libraries;

class PHS_Controller_Index extends \phs\libraries\PHS_Controller
{
    public function get_models()
    {
        return array();
    }

    public function action_index()
    {
        echo 'Bubulica';
    }
}
