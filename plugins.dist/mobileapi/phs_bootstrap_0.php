<?php

use \phs\PHS;
use \phs\PHS_api;
use \phs\libraries\PHS_Logger;

/** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
if( ($mobile_plugin = PHS::load_plugin( 'mobileapi' ))
and $mobile_plugin->plugin_active() )
{
    PHS_Logger::define_channel( $mobile_plugin::LOG_CHANNEL );

    PHS_api::register_api_route( array(
            array(
                'exact_match' => 'users',
            ),
            array(
                'exact_match' => 'login',
            ),
        ),
        array(
            'p' => 'mobileapi',
            'a' => 'login',
        ),
        array(
            'method' => 'post',
            'name' => '3rd party login',
            'description' => 'Login functionality for 3rd party applications',
        )
    );

    PHS_api::register_api_route( array(
            array(
                'exact_match' => 'users',
            ),
            array(
                'exact_match' => 'register',
            ),
        ),
        array(
            'p' => 'mobileapi',
            'a' => 'register',
        ),
        array(
            'method' => 'post',
            'name' => '3rd party registration',
            'description' => 'Registration functionality for 3rd party applications',
        )
    );

    PHS_api::register_api_route( array(
            array(
                'exact_match' => 'users',
            ),
            array(
                'exact_match' => 'forgot_password',
            ),
        ),
        array(
            'p' => 'mobileapi',
            'a' => 'forgot',
        ),
        array(
            'method' => 'post',
            'name' => '3rd party forgot password',
            'description' => 'Forgot password functionality for 3rd party applications',
        )
    );

    PHS_api::register_api_route( array(
            array(
                'exact_match' => 'users',
            ),
            array(
                'exact_match' => 'logout',
            ),
        ),
        array(
            'p' => 'mobileapi',
            'a' => 'logout',
        ),
        array(
            'authentication_callback' => array( $mobile_plugin, 'do_api_authentication' ),
            'method' => 'get',
            'name' => '3rd party logout',
            'description' => 'Logout functionality for 3rd party applications',
        )
    );

    PHS_api::register_api_route( array(
            array(
                'exact_match' => 'users',
            ),
            array(
                'exact_match' => 'change_password',
            ),
        ),
        array(
            'p' => 'mobileapi',
            'a' => 'change_password',
        ),
        array(
            'authentication_callback' => array( $mobile_plugin, 'do_api_authentication' ),
            'method' => 'post',
            'name' => '3rd party change password',
            'description' => 'Change password functionality for 3rd party applications',
        )
    );
}
