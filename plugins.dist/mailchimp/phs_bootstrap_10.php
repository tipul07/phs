<?php

use phs\libraries\PHS_Logger;
use phs\plugins\mailchimp\PHS_Plugin_Mailchimp;

if (($mailchimp_plugin = PHS_Plugin_Mailchimp::get_instance())) {
    PHS_Logger::define_channel($mailchimp_plugin::LOG_CHANNEL);
}
