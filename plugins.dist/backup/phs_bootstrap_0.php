<?php

use phs\libraries\PHS_Logger;
use phs\plugins\backup\PHS_Plugin_Backup;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\system\core\events\accounts\PHS_Event_Accounts_registration_roles;

if (($backup_plugin = PHS_Plugin_Backup::get_instance())) {
    PHS_Logger::define_channel($backup_plugin::LOG_CHANNEL);

    PHS_Event_Layout::listen([$backup_plugin, 'listen_after_left_menu_admin'],
        PHS_Event_Layout::ADMIN_TEMPLATE_AFTER_LEFT_MENU);

    PHS_Event_Accounts_registration_roles::listen(
        [$backup_plugin, 'listen_accounts_registration_roles']
    );
}
