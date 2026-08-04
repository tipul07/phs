<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\system\core\events\emails\PHS_Event_Emails_send;
use phs\plugins\phs_mock_emails\PHS_Plugin_Phs_mock_emails;
use phs\system\core\events\emails\PHS_Event_Emails_settings;

if (($plugin_obj = PHS_Plugin_Phs_mock_emails::get_instance())) {
    PHS_Logger::define_channel($plugin_obj::LOG_CHANNEL);

    PHS::register_hook(
        PHS_Hooks::H_EMAIL_INIT,
        [$plugin_obj, 'init_email_hook_args'],
        PHS_Hooks::default_init_email_hook_args(),
        ['chained_hook' => true, 'stop_chain' => true, 'priority' => 0, ]
    );

    PHS_Event_Emails_settings::listen([$plugin_obj, 'listen_email_settings']);
    PHS_Event_Emails_send::listen([$plugin_obj, 'listen_email_send']);
}
