<?php

    if( !defined( 'PHS_VERSION' ) )
        exit;

    use \phs\PHS;

    if( ($core_models = PHS::get_core_modules())
    and is_array( $core_models ) )
    {
        foreach( $core_models as $core_model )
        {
            if( ($model_obj = PHS::load_model( $core_model )) )
                $model_obj->check_installation();
        }
    }
