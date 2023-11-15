<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\system\core\events\layout\PHS_Event_Template;

/** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
if (($admin_plugin = PHS_Plugin_Admin::get_instance())) {
    PHS_Logger::define_channel($admin_plugin::LOG_API_MONITOR);

    PHS::register_hook(
        PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU,
        [$admin_plugin, 'trigger_after_left_menu_admin'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 1000, ]
    );

    // PHS_Event_Layout::listen([$admin_plugin, 'listen_after_left_menu_admin'],
    //     PHS_Event_Layout::ADMIN_TEMPLATE_AFTER_LEFT_MENU, ['priority' => 1000]);

    if (($settings_arr = $admin_plugin->get_plugin_settings())
     && !empty($settings_arr['default_theme_in_admin'])) {
        // Set "default" as current theme for admin section
        PHS_Event_Template::listen([$admin_plugin, 'listen_web_template_rendering'], PHS_Event_Template::GENERIC);
    }
}
