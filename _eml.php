<?php

@set_time_limit(0);

const PHS_PREVENT_SESSION = true;
const PHS_SCRIPT_SCOPE = 'inmail';

include_once 'main.php';

use phs\libraries\PHS_Logger;
use phs\plugins\phs_inmail\PHS_Plugin_Phs_inmail;

if (!($inmail_plugin = PHS_Plugin_Phs_inmail::get_instance())
   || !$inmail_plugin->plugin_active()
   || !$inmail_plugin->is_inmail_enabled()) {
    exit;
}

PHS_Logger::notice(' --- Started InMail check', $inmail_plugin::LOG_CHANNEL);

PHS_Logger::notice(' --- InMail script finish', $inmail_plugin::LOG_CHANNEL);
