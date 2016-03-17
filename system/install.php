<?php

    if( !defined( 'PHS_VERSION' ) )
        exit;

    $core_models = array( 'bg_jobs' );

    foreach( $core_models as $core_model )
    {
        if( ($model_obj = phs\PHS::load_model( $core_model )) )
            $model_obj->check_installation();
    }
