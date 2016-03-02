<?php

namespace phs\plugins\captcha\actions;

use \phs\libraries\PHS_Action;
use \phs\system\core\views\PHS_View;

class PHS_Action_Index extends PHS_Action
{
    public function execute()
    {
        /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $plugin_instance */
        if( !($plugin_instance = $this->get_plugin_instance())
         or !$plugin_instance->plugin_active()
         or !($plugin_settings = $plugin_instance->get_plugin_db_settings()) )
        {
            echo self::_t( 'Couldn\'t obtain plugin settings.' );
            exit;
        }

        return $this->quick_render_template( 'test' );
    }
}
