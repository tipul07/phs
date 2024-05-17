<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\plugins\cookie_notice\PHS_Plugin_Cookie_notice;

/** @var PHS_Plugin_Cookie_notice $cookie_notice_plugin */
if (($cookie_notice_plugin = PHS_Plugin_Cookie_notice::get_instance())) {
    PHS::register_hook(
        PHS_Hooks::H_COOKIE_NOTICE_DISPLAY,
        [$cookie_notice_plugin, 'get_cookie_notice_hook_args'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );
}
