<?php

if ((!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))
&& !defined('PHS_VERSION')) {
    exit;
}

use phs\PHS_Tenants;

if (!PHS_Tenants::init()) {
    PHS_Tenants::trigger_critical_error(
        PHS_Tenants::st_has_error()
            ? PHS_Tenants::st_get_simple_error_message()
            : 'Error initializing multi-tenants.'
    );
}
