<?php

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\actions\api\PHS_Action_Login;
use phs\plugins\accounts\actions\api\PHS_Action_Logout;
use phs\plugins\accounts\actions\api\PHS_Action_Session;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_saved;

if (($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
    PHS_Logger::define_channel($accounts_plugin::LOG_SECURITY);
    PHS_Logger::define_channel($accounts_plugin::LOG_IMPORT);
    PHS_Logger::define_channel($accounts_plugin::LOG_TFA);

    if (!PHS::prevent_session()) {
        $accounts_plugin->resolve_idler_sessions();
    }

    // Login an account using bearer token
    PHS_Action_Login::post('users/login/token');

    // Logout an account using bearer token
    PHS_Action_Logout::get('users/logout/token');

    // Get account details
    PHS_Action_Session::get('users/session');

    PHS::register_hook(
        PHS_Hooks::H_USER_DB_DETAILS,
        [$accounts_plugin, 'get_current_user_db_details'],
        PHS_Hooks::default_user_db_details_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ],
    );

    PHS::register_hook(
        PHS_Hooks::H_USER_ACCOUNT_STRUCTURE,
        [$accounts_plugin, 'get_account_structure'],
        PHS_Hooks::default_account_structure_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ],
    );

    // Check if new plugin settings say that we should turn password encryption/decryption off
    PHS_Event_Plugin_settings_saved::listen([
        $accounts_plugin, 'listen_plugin_settings_saved',
    ]);
}
