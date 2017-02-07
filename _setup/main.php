<?php

    define( 'PHS_SETUP_FLOW', true );

    @date_default_timezone_set( 'Europe/London' );

    if( @function_exists( 'mb_internal_encoding' ) )
        @mb_internal_encoding( 'UTF-8' );

    include( 'libraries/phs_setup.php' );

    use \phs\setup\libraries\PHS_Setup;

    $phs_setup_obj = new PHS_Setup();

    $phs_setup_obj->detect_paths_and_domain();

    if( !($phs_path = @dirname( __DIR__ )) )
        $phs_path = '../';

    // Platform full absolute path
    define( 'PHS_PATH', $phs_path );

    // Prety name of the site (will be displayed to visitors as site name)
    define( 'PHS_DEFAULT_SITE_NAME', 'PHS Setup' );
