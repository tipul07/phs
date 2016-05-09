<?php

namespace phs\plugins\admin;

use \phs\PHS;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Hooks;

class PHS_Plugin_Admin extends PHS_Plugin
{
    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.0';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'name' => 'Administration Plugin',
            'description' => 'Handles all administration actions.',
        );
    }

    public function get_models()
    {
        return array();
    }

}
