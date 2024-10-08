<?php

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_saved;

/** @var PHS_Plugin_Accounts $accounts_plugin */
if (($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
    PHS_Logger::define_channel($accounts_plugin::LOG_SECURITY);
    PHS_Logger::define_channel($accounts_plugin::LOG_IMPORT);
    PHS_Logger::define_channel($accounts_plugin::LOG_TFA);

    if (!PHS::prevent_session()) {
        $accounts_plugin->resolve_idler_sessions();
    }

    // POST /users/login/token Login an account using bearer token
    PHS_Api::register_api_route([
        ['exact_match' => 'users', ],
        ['exact_match' => 'login', ],
        ['exact_match' => 'token', ],
    ],
        [
            'p'  => 'accounts',
            'c'  => 'index_api',
            'a'  => 'login',
            'ad' => 'api',
        ],
        [
            'authentication_required' => false,
            'method'                  => 'post',
            'name'                    => 'Login using bearer authentication',
            'description'             => 'Login using API calls with bearer authentication in headers',
        ]
    );

    // POST /users/logout/token Logout an account using bearer token
    PHS_Api::register_api_route([
        ['exact_match' => 'users', ],
        ['exact_match' => 'logout', ],
        ['exact_match' => 'token', ],
    ],
        [
            'p'  => 'accounts',
            'c'  => 'index_api',
            'a'  => 'logout',
            'ad' => 'api',
        ],
        [
            'authentication_required' => true,
            'authentication_methods'  => [PHS_Api_base::AUTH_METHOD_BEARER, ],
            'method'                  => 'get',
            'name'                    => 'Logout using bearer authentication',
            'description'             => 'Logout using API calls with bearer authentication in headers',
        ]
    );

    // GET /users/session Get account details
    PHS_Api::register_api_route([
        ['exact_match' => 'users', ],
        ['exact_match' => 'session', ],
    ],
        [
            'p'  => 'accounts',
            'c'  => 'index_api',
            'a'  => 'session',
            'ad' => 'api',
        ],
        [
            'authentication_required' => true,
            'authentication_methods'  => [PHS_Api_base::AUTH_METHOD_BEARER, ],
            'method'                  => 'get',
            'name'                    => 'Session details using bearer authentication',
            'description'             => 'Get logged in user session details using API calls with bearer authentication in headers',
        ]
    );

    PHS::register_hook(
        PHS_Hooks::H_USER_DB_DETAILS,
        [$accounts_plugin, 'get_current_user_db_details'],
        PHS_Hooks::default_user_db_details_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_USER_ACCOUNT_STRUCTURE,
        [$accounts_plugin, 'get_account_structure'],
        PHS_Hooks::default_account_structure_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );

    // Check if new plugin settings say that we should turn password encryption/decryption off
    PHS_Event_Plugin_settings_saved::listen([
        $accounts_plugin, 'listen_plugin_settings_saved',
    ]);
}
