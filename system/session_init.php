<?php

if ((!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))
&& !defined('PHS_VERSION')) {
    exit;
}

use phs\PHS_Session;

// Set required variables to session, but don't start it yet.
// Session will start when it will be asked first time for a variable or right after displaying the template
if (!PHS_Session::init()) {
    PHS_Session::st_throw_error();
}
