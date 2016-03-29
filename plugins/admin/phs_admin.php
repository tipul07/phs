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

    public function get_models()
    {
        return array();
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     * @return array
     */
    public function get_default_settings()
    {
        return array(
        );
    }

}
