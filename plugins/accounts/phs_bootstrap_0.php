<?php

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
    if( ($accounts_plugin = phs\PHS::load_plugin( 'accounts' )) )
    {
        PHS::register_hook(
            // $hook_name
            PHS_Hooks::H_USER_DB_DETAILS,
            // $hook_callback = null
            array( $accounts_plugin, 'get_current_user_db_details' ),
            // $hook_extra_args = null
            PHS::default_user_db_details_hook_args(),
            // $chained_hook = false
            true,
            // $priority = 10
            0
        );
    }
