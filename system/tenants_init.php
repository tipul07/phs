<?php

if ((!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))
&& !defined('PHS_VERSION')) {
    exit;
}

use phs\PHS_Tenants;

if( !PHS_Tenants::init() ) {
    PHS_Tenants::st_throw_error();
}
