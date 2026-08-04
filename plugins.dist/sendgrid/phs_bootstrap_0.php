<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\sendgrid\PHS_Plugin_Sendgrid;
use phs\system\core\events\emails\PHS_Event_Emails_send;
use phs\system\core\events\emails\PHS_Event_Emails_settings;

if (($sendgrid_plugin = PHS_Plugin_Sendgrid::get_instance())) {
    PHS_Logger::define_channel($sendgrid_plugin::LOG_CHANNEL);

    PHS::register_hook(
        PHS_Hooks::H_EMAIL_INIT,
        [$sendgrid_plugin, 'init_email_hook_args'],
        PHS_Hooks::default_init_email_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS_Event_Emails_settings::listen([$sendgrid_plugin, 'listen_email_settings']);
    PHS_Event_Emails_send::listen([$sendgrid_plugin, 'listen_email_send']);
}
