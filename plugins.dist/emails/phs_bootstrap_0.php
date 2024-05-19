<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\emails\PHS_Plugin_Emails;

/** @var PHS_Plugin_Emails $emails_plugin */
if (($emails_plugin = PHS_Plugin_Emails::get_instance())) {
    PHS_Logger::define_channel($emails_plugin::LOG_CHANNEL);

    PHS::register_hook(
        PHS_Hooks::H_EMAIL_INIT,
        [$emails_plugin, 'init_email_hook_args'],
        PHS_Hooks::default_init_email_hook_args(),
        ['chained_hook' => true, 'stop_chain' => true, 'priority' => 10, ]
    );
}
