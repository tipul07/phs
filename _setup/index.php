<?php

    include( 'main.php' );

    use \phs\setup\libraries\PHS_Setup;

    $phs_setup_obj = PHS_Setup::get_instance();

    $phs_setup_obj->check_prerequisite();

    $phs_setup_obj->load_steps();

    if( !($step_instance = $phs_setup_obj->get_current_step_instance()) )
    {
        echo 'Couldn\'t obtain current setup step instance...';
        exit;
    }

    echo $step_instance->render();
