<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
if( ($backup_plugin = PHS::load_plugin( 'backup' ))
and $admin_plugin->plugin_active() )
{
    PHS::register_hook(
    // $hook_name
        PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU,
        // $hook_callback = null
        array( $admin_plugin, 'trigger_after_left_menu_admin' ),
        // $hook_extra_args = null
        PHS_Hooks::default_buffer_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );

}
