<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;

/** @var \phs\plugins\sendgrid\PHS_Plugin_Sendgrid $sendgrid_plugin */
if( ($sendgrid_plugin = PHS::load_plugin( 'sendgrid' ))
 && $sendgrid_plugin->plugin_active() )
{
    PHS_Logger::define_channel( $sendgrid_plugin::LOG_CHANNEL );

    PHS::register_hook(
        PHS_Hooks::H_EMAIL_INIT,
        [ $sendgrid_plugin, 'init_email_hook_args' ],
        PHS_Hooks::default_init_email_hook_args(),
        [ 'chained_hook' => true, 'stop_chain' => true, 'priority' => 10, ]
    );
}
