<?php

const PHS_CLI_BIN_DIR = __DIR__.'/../';

define('PHS_PATH_FROM_CLI_DIR', @realpath(PHS_CLI_BIN_DIR.'/../'));

const PHS_CLI_APPS_DIR = PHS_CLI_BIN_DIR.'apps/';

const PHS_CLI_APPS_LIBRARIES_DIR = PHS_CLI_APPS_DIR.'libraries/';

const PHS_PREVENT_SESSION = true;

const PHS_SCRIPT_SCOPE = 'cli';

if (!@file_exists(PHS_CLI_BIN_DIR.'../main.php')) {
    echo 'It seems framework is not yet initialized!'."\n"
         .'You should complete framework setup.'."\n";
    exit(1);
}

include_once PHS_CLI_BIN_DIR.'../main.php';

include_once PHS_CORE_DIR.'phs_cli.php';

use phs\PHS_Scope;

PHS_Scope::current_scope(PHS_Scope::SCOPE_CLI);
