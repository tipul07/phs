<?php

use \phs\PHS;
use \phs\libraries\PHS_Hooks;

/** @var \phs\plugins\emails\PHS_Plugin_Emails $emails_plugin */
if( ($emails_plugin = PHS::load_plugin( 'emails' ))
and $emails_plugin->plugin_active() )
{
    if( $emails_plugin->has_error() )
        $emails_plugin->throw_error();

    PHS::register_hook(
        // $hook_name
        PHS_Hooks::H_EMAIL_INIT,
        // $hook_callback = null
        array( $emails_plugin, 'init_email_hook_args' ),
        // $hook_extra_args = null
        PHS_Hooks::default_init_email_hook_args(),
        array(
            'chained_hook' => true,
            'stop_chain' => false,
            'priority' => 10,
        )
    );
}
