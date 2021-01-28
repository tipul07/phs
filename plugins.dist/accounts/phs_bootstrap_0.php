<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
if( ($accounts_plugin = PHS::load_plugin( 'accounts' ))
 && $accounts_plugin->plugin_active() )
{
    if( !PHS::prevent_session() )
        $accounts_plugin->resolve_idler_sessions();

    PHS::register_hook(
        PHS_Hooks::H_USER_DB_DETAILS,
        [ $accounts_plugin, 'get_current_user_db_details' ],
        PHS_Hooks::default_user_db_details_hook_args(),
        [ 'chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );

    PHS::register_hook(
        PHS_Hooks::H_USER_ACCOUNT_STRUCTURE,
        [ $accounts_plugin, 'get_account_structure' ],
        PHS_Hooks::default_account_structure_hook_args(),
        [ 'chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );
}
