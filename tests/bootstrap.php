<?php

define('PHS_TESTS_DIR', __DIR__.'/');

define('PHS_TESTS_PHS_DIR', PHS_TESTS_DIR.'phs/');

define('PHS_PREVENT_SESSION', true);

define('PHS_SCRIPT_SCOPE', 'test');

if (!@file_exists(PHS_TESTS_DIR.'../main.php')) {
    echo 'It seems framework is not yet initialized!'."\n"
         .'You should access the framework using a browser and complete the guided setup.'."\n";
    exit(1);
}

include_once PHS_TESTS_DIR.'../main.php';

include_once PHS_CORE_DIR.'phs_cli.php';

include_once PHS_TESTS_PHS_DIR.'PHSTests.php';

use phs\PHS_Scope;

PHS_Scope::current_scope(PHS_Scope::SCOPE_TESTS);
