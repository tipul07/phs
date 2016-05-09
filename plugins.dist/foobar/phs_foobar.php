<?php

namespace phs\plugins\foobar;

use \phs\libraries\PHS_Plugin;

class PHS_Plugin_Foobar extends PHS_Plugin
{
    const ERR_TEMPLATE = 40000, ERR_RENDER = 40001;

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.1';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'name' => 'Foobar Plugin',
            'description' => 'This is a foobar plugin...',
        );
    }

    public function get_models()
    {
        return array( 'foobar' );
    }
}
