<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
if( ($backup_plugin = PHS::load_plugin( 'backup' ))
and $backup_plugin->plugin_active() )
{
    PHS::register_hook(
    // $hook_name
        PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU,
        // $hook_callback = null
        array( $backup_plugin, 'trigger_after_left_menu_admin' ),
        // $hook_extra_args = null
        PHS_Hooks::default_buffer_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_USER_REGISTRATION_ROLES,
        // $hook_callback = null
        array( $backup_plugin, 'trigger_assign_registration_roles' ),
        // $hook_extra_args = null
        PHS_Hooks::default_user_registration_roles_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );

}
