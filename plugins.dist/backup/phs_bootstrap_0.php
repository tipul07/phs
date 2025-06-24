<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\backup\PHS_Plugin_Backup;
use phs\system\core\events\layout\PHS_Event_Layout;

if (($backup_plugin = PHS_Plugin_Backup::get_instance())) {
    PHS_Logger::define_channel($backup_plugin::LOG_CHANNEL);

    PHS_Event_Layout::listen([$backup_plugin, 'listen_after_left_menu_admin'],
        PHS_Event_Layout::ADMIN_TEMPLATE_AFTER_LEFT_MENU);

    PHS::register_hook(
        PHS_Hooks::H_USER_REGISTRATION_ROLES,
        [$backup_plugin, 'trigger_assign_registration_roles'],
        PHS_Hooks::default_user_registration_roles_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );
}
