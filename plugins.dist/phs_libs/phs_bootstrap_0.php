<?php

use phs\libraries\PHS_Logger;
use phs\plugins\phs_libs\PHS_Plugin_Phs_libs;

/** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $phs_libs_plugin */
if (($phs_libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
    PHS_Logger::define_channel($phs_libs_plugin::LOG_QR_CODE);
}
