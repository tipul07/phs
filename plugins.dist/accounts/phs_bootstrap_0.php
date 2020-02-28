<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
if( ($accounts_plugin = PHS::load_plugin( 'accounts' ))
and $accounts_plugin->plugin_active() )
{
    if( !PHS::prevent_session() )
        $accounts_plugin->resolve_idler_sessions();

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_USER_DB_DETAILS,
        // $hook_callback = null
        array( $accounts_plugin, 'get_current_user_db_details' ),
        // $hook_extra_args = null
        PHS_Hooks::default_user_db_details_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 0,
        )
    );

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_USER_ACCOUNT_STRUCTURE,
        // $hook_callback = null
        array( $accounts_plugin, 'get_account_structure' ),
        // $hook_extra_args = null
        PHS_Hooks::default_account_structure_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 0,
        )
    );

}
