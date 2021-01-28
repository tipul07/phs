<?php

use \phs\PHS;
use \phs\PHS_Api;
use \phs\libraries\PHS_Logger;

/** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
if( ($mobile_plugin = PHS::load_plugin( 'mobileapi' ))
 && $mobile_plugin->plugin_active() )
{
    PHS_Logger::define_channel( $mobile_plugin::LOG_CHANNEL );
    PHS_Logger::define_channel( $mobile_plugin::LOG_FIREBASE );

    // POST /devices/session Create session
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'devices', ],
            [ 'exact_match' => 'session', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'device_session',
        ],
        [
            'method' => 'post',
            'name' => 'Create session for device',
            'description' => '3rd party app can anonymously create a session for a device in the system in order to send push notifications.',
        ]
    );

    // GET /devices/session Get session details
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'devices', ],
            [ 'exact_match' => 'session', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'device_session_details',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'get',
            'name' => 'Get session details',
            'description' => '3rd party app can get session details.',
        ]
    );

    // POST /devices/update Update session
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'devices', ],
            [ 'exact_match' => 'update', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'device_update',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'post',
            'name' => 'Update session for device',
            'description' => '3rd party app can send device updates as required (update location or other variables)',
        ]
    );

    // POST /users/login Login an account from 3rd party mobile app
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'users', ],
            [ 'exact_match' => 'login', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'login',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'post',
            'name' => '3rd party login',
            'description' => 'Login functionality for 3rd party applications',
        ]
    );

    // POST /users/register Register an account from a 3rd party mobile app
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'users', ],
            [ 'exact_match' => 'register', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'register',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'post',
            'name' => '3rd party registration',
            'description' => 'Registration functionality for 3rd party applications',
        ]
    );

    // POST /users/forgot_password User forgot password request from a 3rd party mobile app
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'users', ],
            [ 'exact_match' => 'forgot_password', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'forgot',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'post',
            'name' => '3rd party forgot password',
            'description' => 'Forgot password functionality for 3rd party applications',
        ]
    );

    // GET /users/logout Logout from a 3rd party mobile app
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'users', ],
            [ 'exact_match' => 'logout', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'logout',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'get',
            'name' => '3rd party logout',
            'description' => 'Logout functionality for 3rd party applications',
        ]
    );

    // GET /users/change_password Request new password from a 3rd party mobile app
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'users', ],
            [ 'exact_match' => 'change_password', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'change_password',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'post',
            'name' => '3rd party change password',
            'description' => 'Change password functionality for 3rd party applications',
        ]
    );

    // POST /users/edit Edit account request from a 3rd party mobile app
    PHS_Api::register_api_route( [
            [ 'exact_match' => 'users', ],
            [ 'exact_match' => 'edit', ],
        ],
        [
            'p' => 'mobileapi',
            'a' => 'account_edit',
        ],
        [
            'authentication_callback' => [ $mobile_plugin, 'do_api_authentication' ],
            'method' => 'post',
            'name' => '3rd party edit account',
            'description' => 'Change account functionality for 3rd party applications',
        ]
    );
}
