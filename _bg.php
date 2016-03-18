<?php

    @set_time_limit( 0 );

    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );

    // HTTP/1.0
    header( 'Pragma: no-cache' );

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\libraries\PHS_Logger;
    use \phs\PHS_bg_jobs;

    // PHS::execute_route();

    PHS_Logger::logf( 'Started bg job...', PHS_Logger::TYPE_DEBUG );

    $input = '';
    if( !empty( $_SERVER['argv'] ) and is_array( $_SERVER['argv'] ) and !empty( $_SERVER['argv'][1] ) )
        $input = $_SERVER['argv'][1];

    if( !($parsed_input = PHS_bg_jobs::validate_input( $input )) )
        exit;

    PHS_Logger::logf( 'Input ['.$input.']', PHS_Logger::TYPE_DEBUG );
