<?php

use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\system\core\events\layout\PHS_Event_Template;

if (($admin_plugin = PHS_Plugin_Admin::get_instance())) {
    PHS_Logger::define_channel($admin_plugin::LOG_API_MONITOR);
    PHS_Logger::define_channel($admin_plugin::LOG_DATA_RETENTION);
    PHS_Logger::define_channel($admin_plugin::LOG_PAGINATOR);
    PHS_Logger::define_channel($admin_plugin::LOG_AI_TRANSLATIONS);
    PHS_Logger::define_channel($admin_plugin::LOG_UI_TRANSLATIONS);

    PHS_Event_Layout::listen([$admin_plugin, 'listen_after_left_menu_admin'],
        PHS_Event_Layout::ADMIN_TEMPLATE_AFTER_LEFT_MENU, ['priority' => 1000]);

    if ($admin_plugin->use_default_theme_in_admin()) {
        // Set "default" as current theme for admin section
        PHS_Event_Template::listen([$admin_plugin, 'listen_web_template_rendering'], PHS_Event_Template::GENERIC);
    }
}
