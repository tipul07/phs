<?php

if (!defined('PHS_VERSION')
 && (!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))) {
    exit;
}

if (!defined('PHS_DB_SILENT_ERRORS')) {
    define('PHS_DB_SILENT_ERRORS', false);
}
if (!defined('PHS_DB_DIE_ON_ERROR')) {
    define('PHS_DB_DIE_ON_ERROR', true);
}
if (!defined('PHS_DB_CLOSE_AFTER_QUERY')) {
    define('PHS_DB_CLOSE_AFTER_QUERY', false);
}
