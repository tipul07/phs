<?php

use phs\libraries\PHS_Logger;
use phs\plugins\phs_inmail\PHS_Plugin_Phs_inmail;

if (($inmail_plugin = PHS_Plugin_Phs_inmail::get_instance())) {
    PHS_Logger::defined_channel($inmail_plugin::LOG_CHANNEL);
}
