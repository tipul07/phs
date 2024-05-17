<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\plugins\notifications\PHS_Plugin_Notifications;

/** @var PHS_Plugin_Notifications $notifications_plugin */
if (($notifications_plugin = PHS_Plugin_Notifications::get_instance())) {
    PHS::register_hook(
        PHS_Hooks::H_NOTIFICATIONS_DISPLAY,
        [$notifications_plugin, 'get_notifications_hook_args'],
        PHS_Hooks::default_notifications_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );
}
