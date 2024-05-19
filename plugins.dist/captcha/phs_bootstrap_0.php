<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\plugins\captcha\PHS_Plugin_Captcha;

/** @var PHS_Plugin_Captcha $captcha_plugin */
if (($captcha_plugin = PHS_Plugin_Captcha::get_instance())) {
    PHS::register_hook(
        PHS_Hooks::H_CAPTCHA_DISPLAY,
        [$captcha_plugin, 'get_captcha_display_hook_args'],
        PHS_Hooks::default_captcha_display_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_CAPTCHA_CHECK,
        [$captcha_plugin, 'get_captcha_check_hook_args'],
        PHS_Hooks::default_captcha_check_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_CAPTCHA_REGENERATE,
        [$captcha_plugin, 'captcha_regenerate_hook_args'],
        PHS_Hooks::default_captcha_regeneration_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );
}
