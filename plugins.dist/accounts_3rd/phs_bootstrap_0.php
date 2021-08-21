<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;

/** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $trd_party_plugin */
if( ($trd_party_plugin = PHS::load_plugin( 'accounts_3rd' ))
 && $trd_party_plugin->plugin_active() )
{
    PHS_Logger::define_channel( $trd_party_plugin::LOG_CHANNEL );
    PHS_Logger::define_channel( $trd_party_plugin::LOG_ERR_CHANNEL );

    PHS::register_hook(
        $trd_party_plugin::H_ACCOUNTS_3RD_LOGIN_BUFFER,
        [ $trd_party_plugin, 'trigger_trd_party_login_buffer' ],
        PHS_Hooks::default_buffer_hook_args(),
        [ 'chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );

    PHS::register_hook(
        $trd_party_plugin::H_ACCOUNTS_3RD_REGISTER_BUFFER,
        [ $trd_party_plugin, 'trigger_trd_party_register_buffer' ],
        PHS_Hooks::default_buffer_hook_args(),
        [ 'chained_hook' => true, 'stop_chain' => false, 'priority' => 0, ]
    );
}
