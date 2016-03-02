<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
if( ($captcha_plugin = PHS::load_plugin( 'captcha' ))
and $captcha_plugin->plugin_active() )
{
    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_CAPTCHA_DISPLAY,
        // $hook_callback = null
        array( $captcha_plugin, 'get_captcha_hook_args' ),
        // $hook_extra_args = null
        PHS_Hooks::default_captcha_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );
}
