<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\notifications\PHS_Plugin_Notifications $notifications_plugin */
if( ($notifications_plugin = PHS::load_plugin( 'notifications' ))
and $notifications_plugin->plugin_active() )
{
    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_NOTIFICATIONS_DISPLAY,
        // $hook_callback = null
        array( $notifications_plugin, 'get_notifications_hook_args' ),
        // $hook_extra_args = null
        PHS_Hooks::default_notifications_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );
}
