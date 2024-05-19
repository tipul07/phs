<?php

use phs\PHS;
use phs\libraries\PHS_Logger;
use phs\plugins\mailchimp\PHS_Plugin_Mailchimp;

/** @var PHS_Plugin_Mailchimp $mailchimp_plugin */
if (($mailchimp_plugin = PHS_Plugin_Mailchimp::get_instance())) {
    PHS_Logger::define_channel($mailchimp_plugin::LOG_CHANNEL);
}
