<?php

namespace phs\setup\libraries;

class PHS_Setup
{
    function __construct()
    {
    }

    public function load_setup_config()
    {

    }

    public function detect_paths_and_domain()
    {
        if( !($phs_path = @dirname( __DIR__ )) )
            $phs_path = '../';

        var_dump( $phs_path );
        var_dump( $_SERVER );
    }
}
