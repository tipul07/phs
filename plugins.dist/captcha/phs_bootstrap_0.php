<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;

/** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
if (($captcha_plugin = PHS::load_plugin('captcha'))
&& $captcha_plugin->plugin_active()) {
    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_CAPTCHA_DISPLAY,
        // $hook_callback = null
        [$captcha_plugin, 'get_captcha_display_hook_args'],
        // $hook_extra_args = null
        PHS_Hooks::default_captcha_display_hook_args(),
        [
            'chained_hook' => true,
            'stop_chain'   => false,
            'priority'     => 10,
        ]
    );

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_CAPTCHA_CHECK,
        // $hook_callback = null
        [$captcha_plugin, 'get_captcha_check_hook_args'],
        // $hook_extra_args = null
        PHS_Hooks::default_captcha_check_hook_args(),
        [
            'chained_hook' => true,
            'stop_chain'   => false,
            'priority'     => 10,
        ]
    );

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_CAPTCHA_REGENERATE,
        // $hook_callback = null
        [$captcha_plugin, 'captcha_regenerate_hook_args'],
        // $hook_extra_args = null
        PHS_Hooks::default_captcha_regeneration_hook_args(),
        [
            'chained_hook' => true,
            'stop_chain'   => false,
            'priority'     => 10,
        ]
    );
}
