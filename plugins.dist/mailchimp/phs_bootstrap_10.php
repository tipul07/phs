<?php

use \phs\PHS;
use \phs\libraries\PHS_Logger;

/** @var \phs\plugins\mailchimp\PHS_Plugin_Mailchimp $mailchimp_plugin */
if( ($mailchimp_plugin = PHS::load_plugin( 'mailchimp' ))
and $mailchimp_plugin->plugin_active() )
{
    PHS_Logger::define_channel( $mailchimp_plugin::LOG_CHANNEL );
}
