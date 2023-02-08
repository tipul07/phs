<?php

use phs\PHS;
use phs\libraries\PHS_Logger;

/** @var \phs\plugins\hubspot\PHS_Plugin_Hubspot $hubspot_plugin */
if (($hubspot_plugin = PHS::load_plugin('hubspot'))
&& $hubspot_plugin->plugin_active()) {
    PHS_Logger::define_channel($hubspot_plugin::LOG_CHANNEL);
}
