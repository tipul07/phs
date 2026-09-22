<?php

use phs\libraries\PHS_Logger;
use phs\plugins\hubspot\PHS_Plugin_Hubspot;

if (($hubspot_plugin = PHS_Plugin_Hubspot::get_instance())) {
    PHS_Logger::define_channel($hubspot_plugin::LOG_CHANNEL);
}
