<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Model;

/** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
if( ($messages_plugin = PHS::load_plugin( 'messages' ))
and $messages_plugin->plugin_active() )
{
    /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
    if( ($accounts_plugin = PHS::load_plugin( 'accounts' ))
    and $accounts_plugin->plugin_active() )
    {
        PHS::register_hook(
            // $hook_name
            PHS_Model::HOOK_TABLE_FIELDS.'_'.$accounts_plugin->instance_id(),
            // $hook_callback = null
            array( $messages_plugin, 'trigger_model_table_fields' ),
            // $hook_extra_args = null
            PHS_Model::default_table_fields_hook_args(),
            array(
                'chained_hook' => true,
                'stop_chain' => false,
                'priority' => 10,
            )
        );

        PHS::register_hook(
            // $hook_name
            PHS_Hooks::H_USER_ACCOUNT_ACTION,
            // $hook_callback = null
            array( $messages_plugin, 'trigger_account_action' ),
            // $hook_extra_args = null
            PHS_Hooks::default_account_action_hook_args(),
            array(
                'chained_hook' => true,
                'stop_chain' => false,
                'priority' => 10,
            )
        );
    }

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_USERS_DETAILS_FIELDS,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_user_details_fields' ),
        // $hook_extra_args = null
        PHS_Hooks::default_user_account_fields_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_USERS_REGISTRATION,
        // $hook_callback = null
        array( $messages_plugin, 'trigger_user_registration' ),
        // $hook_extra_args = null
        PHS_Hooks::default_user_account_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 0,
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
}
