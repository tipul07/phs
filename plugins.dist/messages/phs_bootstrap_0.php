<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
if( ($messages_plugin = PHS::load_plugin( 'messages' ))
and $messages_plugin->plugin_active() )
{
    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_MSG_GET_SUMMARY,
        // $hook_callback = null
        array( $messages_plugin, 'get_messages_summary_hook_args' ),
        // $hook_extra_args = null
        PHS_Hooks::default_messages_summary_hook_args(),
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
        array( $messages_plugin, 'trigger_assign_registration_roles' ),
        // $hook_extra_args = null
        PHS_Hooks::default_user_registration_roles_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );

    PHS::register_hook(
    // $hook_name
        PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_after_main_menu_logged_in' ),
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
        PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_LEFT_MENU,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_before_left_menu_admin' ),
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
        PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_MAIN_MENU,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_after_main_menu_admin' ),
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
        PHS_Hooks::H_MODEL_EMPTY_DATA,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_model_empty_data' ),
        // $hook_extra_args = null
        PHS_Hooks::default_model_empty_data_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );

    PHS::register_hook(
    // $hook_name
        PHS_Hooks::H_MODEL_VALIDATE_DATA_FIELDS,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_model_validate_data_fields' ),
        // $hook_extra_args = null
        PHS_Hooks::default_model_validate_data_fields_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );
}
