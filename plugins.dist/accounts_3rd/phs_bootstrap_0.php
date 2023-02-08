<?php

use phs\PHS;
use phs\PHS_Api;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;

/** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $trd_party_plugin */
if (($trd_party_plugin = PHS::load_plugin('accounts_3rd'))
 && $trd_party_plugin->plugin_active()) {
    PHS_Logger::define_channel($trd_party_plugin::LOG_CHANNEL);
    PHS_Logger::define_channel($trd_party_plugin::LOG_ERR_CHANNEL);

    /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
    if (($mobile_plugin = PHS::load_plugin('mobileapi'))
     && $mobile_plugin->plugin_active()) {
        // POST /users/google/login Login an account from 3rd party mobile app using a Google account
        PHS_Api::register_api_route([
            ['exact_match' => 'users', ],
            ['exact_match' => 'google', ],
            ['exact_match' => 'login', ],
        ],
            [
                'p'  => 'accounts_3rd',
                'c'  => 'index_api',
                'a'  => 'google_login',
                'ad' => 'api',
            ],
            [
                'authentication_callback' => [$mobile_plugin, 'do_api_authentication'],
                'method'                  => 'post',
                'name'                    => '3rd party Google mobile login',
                'description'             => 'Login functionality for 3rd party mobile applications with Google accounts',
            ]
        );

        // POST /users/google/register Register an account from 3rd party mobile app using a Google account
        PHS_Api::register_api_route([
            ['exact_match' => 'users', ],
            ['exact_match' => 'google', ],
            ['exact_match' => 'register', ],
        ],
            [
                'p'  => 'accounts_3rd',
                'c'  => 'index_api',
                'a'  => 'google_register',
                'ad' => 'api',
            ],
            [
                'authentication_callback' => [$mobile_plugin, 'do_api_authentication'],
                'method'                  => 'post',
                'name'                    => '3rd party Google mobile register',
                'description'             => 'Register functionality for 3rd party mobile applications with Google accounts',
            ]
        );

        // POST /users/apple/login Login an account from 3rd party mobile app using an Apple account
        PHS_Api::register_api_route([
            ['exact_match' => 'users', ],
            ['exact_match' => 'apple', ],
            ['exact_match' => 'login', ],
        ],
            [
                'p'  => 'accounts_3rd',
                'c'  => 'index_api',
                'a'  => 'apple_login',
                'ad' => 'api',
            ],
            [
                'authentication_callback' => [$mobile_plugin, 'do_api_authentication'],
                'method'                  => 'post',
                'name'                    => '3rd party Apple mobile login',
                'description'             => 'Login functionality for 3rd party mobile applications with Apple accounts',
            ]
        );

        // POST /users/apple/register Register an account from 3rd party mobile app using an Apple account
        PHS_Api::register_api_route([
            ['exact_match' => 'users', ],
            ['exact_match' => 'apple', ],
            ['exact_match' => 'register', ],
        ],
            [
                'p'  => 'accounts_3rd',
                'c'  => 'index_api',
                'a'  => 'apple_register',
                'ad' => 'api',
            ],
            [
                'authentication_callback' => [$mobile_plugin, 'do_api_authentication'],
                'method'                  => 'post',
                'name'                    => '3rd party Apple mobile register',
                'description'             => 'Register functionality for 3rd party mobile applications with Apple accounts',
            ]
        );
    }

    PHS::register_hook(
        $trd_party_plugin::H_ACCOUNTS_3RD_LOGIN_BUFFER,
        [$trd_party_plugin, 'trigger_trd_party_login_buffer'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );

    PHS::register_hook(
        $trd_party_plugin::H_ACCOUNTS_3RD_REGISTER_BUFFER,
        [$trd_party_plugin, 'trigger_trd_party_register_buffer'],
        PHS_Hooks::default_buffer_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );
}
