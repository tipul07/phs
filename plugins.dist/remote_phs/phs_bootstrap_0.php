<?php

use phs\PHS;
use phs\PHS_Api;
use phs\libraries\PHS_Hooks;
use phs\plugins\remote_phs\PHS_Plugin_Remote_phs;
use phs\system\core\events\layout\PHS_Event_Layout;

if (($remote_phs_plugin = PHS_Plugin_Remote_phs::get_instance())) {
    PHS_Event_Layout::listen([$remote_phs_plugin, 'listen_after_left_menu_admin'],
        PHS_Event_Layout::ADMIN_TEMPLATE_AFTER_LEFT_MENU);

    if ($remote_phs_plugin->is_remote_enabled()) {
        PHS_Api::register_api_route([
            ['exact_match' => 'phs_remote', ],
            ['exact_match' => 'ping', ],
        ], [
            'p'  => 'remote_phs',
            'c'  => 'index_api',
            'a'  => 'ping',
            'ad' => 'connection',
        ], [
            'authentication_required' => true,
            'method'                  => 'post',
            'name'                    => 'Perform a ping',
            'description'             => 'Send a ping request to a 3rd party PHS platform to see if connection is alive',
        ]
        );

        PHS_Api::register_api_route([
            ['exact_match' => 'phs_remote', ],
            ['exact_match' => 'connect', ],
        ], [
            'p'  => 'remote_phs',
            'c'  => 'index_api',
            'a'  => 'connect',
            'ad' => 'connection',
        ], [
            'authentication_required' => true,
            'method'                  => 'post',
            'name'                    => 'Connect with 3rd PHS platform',
            'description'             => 'Send a request to a 3rd party PHS platform to connect',
        ]
        );

        PHS_Api::register_api_route([
            ['exact_match' => 'phs_remote', ],
            ['exact_match' => 'connect_confirm', ],
        ], [
            'p'  => 'remote_phs',
            'c'  => 'index_api',
            'a'  => 'connect_confirm',
            'ad' => 'connection',
        ], [
            'authentication_required' => true,
            'method'                  => 'post',
            'name'                    => 'Confirm connection with 3rd PHS platform',
            'description'             => 'Send a request to a 3rd party PHS platform to confirm a connection',
        ]
        );
    }

    PHS::register_hook(
        PHS_Hooks::H_USER_REGISTRATION_ROLES,
        [$remote_phs_plugin, 'trigger_assign_registration_roles'],
        PHS_Hooks::default_user_registration_roles_hook_args(),
        ['chained_hook' => true, 'stop_chain' => false, 'priority' => 10, ]
    );
}
