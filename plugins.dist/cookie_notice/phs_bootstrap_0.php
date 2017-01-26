<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\cookie_notice\PHS_Plugin_Cookie_notice $cookie_notice_plugin */
if( ($cookie_notice_plugin = PHS::load_plugin( 'cookie_notice' ))
and $cookie_notice_plugin->plugin_active() )
{
    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_COOKIE_NOTICE_DISPLAY,
        // $hook_callback = null
        array( $cookie_notice_plugin, 'get_cookie_notice_hook_args' ),
        // $hook_extra_args = null
        PHS_Hooks::default_buffer_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );
}
