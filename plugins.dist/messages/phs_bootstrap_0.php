<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Model;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\messages\PHS_Plugin_Messages;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\system\core\events\accounts\PHS_Event_Accounts_info_template;

if (($messages_plugin = PHS_Plugin_Messages::get_instance())) {
    if (($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
        PHS::register_hook(
            PHS_Model::HOOK_TABLE_FIELDS.'_'.$accounts_plugin->instance_id(),
            [$messages_plugin, 'trigger_model_table_fields'],
            PHS_Model::default_table_fields_hook_args(),
            ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
        );

        PHS::register_hook(
            PHS_Hooks::H_USER_ACCOUNT_ACTION,
            [$messages_plugin, 'trigger_account_action'],
            PHS_Hooks::default_account_action_hook_args(),
            ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
        );
    }

    PHS::register_hook(
        PHS_Hooks::H_USERS_DETAILS_FIELDS,
        [$messages_plugin, 'trigger_user_details_fields'],
        PHS_Hooks::default_user_account_fields_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_USERS_REGISTRATION,
        [$messages_plugin, 'trigger_user_registration'],
        PHS_Hooks::default_user_account_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_USER_REGISTRATION_ROLES,
        [$messages_plugin, 'trigger_assign_registration_roles'],
        PHS_Hooks::default_user_registration_roles_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_MSG_GET_SUMMARY,
        [$messages_plugin, 'get_messages_summary_hook_args'],
        PHS_Hooks::default_messages_summary_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN,
        [$messages_plugin, 'trigger_after_main_menu_logged_in'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );

    PHS_Event_Layout::listen([$messages_plugin, 'listen_after_main_menu_admin'],
        PHS_Event_Layout::ADMIN_TEMPLATE_AFTER_MAIN_MENU);

    PHS_Event_Accounts_info_template::listen_for_buffer(
        [$messages_plugin, 'listen_account_info_template'], ['priority' => -1]
    );
}
