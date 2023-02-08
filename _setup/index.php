<?php

if (@file_exists('../main.php')) {
    echo 'You should use CLI application to manage the framework...';
    exit;
}

include 'main.php';

use phs\setup\libraries\PHS_Setup;

$phs_setup_obj = PHS_Setup::get_instance();

$phs_setup_obj->check_prerequisites();

$phs_setup_obj->load_steps();

if (!($step_instance = $phs_setup_obj->get_current_step_instance())) {
    echo 'Couldn\'t obtain current setup step instance...';
    exit;
}

echo $step_instance->render();
