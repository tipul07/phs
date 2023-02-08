<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;

/** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
if (($admin_plugin = PHS::load_plugin('admin'))
 && $admin_plugin->plugin_active()) {
    PHS::register_hook(
        PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU,
        [$admin_plugin, 'trigger_after_left_menu_admin'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 1000, ]
    );

    if (($settings_arr = $admin_plugin->get_plugin_settings())
     && !empty($settings_arr['default_theme_in_admin'])) {
        // Set "default" as current theme for admin section
        PHS::register_hook(
            PHS_Hooks::H_WEB_TEMPLATE_RENDERING,
            [$admin_plugin, 'trigger_web_template_rendering'],
            PHS_Hooks::default_page_location_hook_args(),
            ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
        );
    }
}
