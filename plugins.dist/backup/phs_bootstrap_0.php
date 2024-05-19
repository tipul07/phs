<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\backup\PHS_Plugin_Backup;

/** @var PHS_Plugin_Backup $backup_plugin */
if (($backup_plugin = PHS_Plugin_Backup::get_instance())) {
    PHS_Logger::define_channel($backup_plugin::LOG_CHANNEL);

    PHS::register_hook(
        PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU,
        [$backup_plugin, 'trigger_after_left_menu_admin'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 2000, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_USER_REGISTRATION_ROLES,
        [$backup_plugin, 'trigger_assign_registration_roles'],
        PHS_Hooks::default_user_registration_roles_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );
}
