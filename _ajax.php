<?php

    @set_time_limit( 0 );

    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );

    // HTTP/1.0
    header( 'Pragma: no-cache' );

    define( 'PHS_SCRIPT_SCOPE', 'ajax' );

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_Ajax;
    use \phs\libraries\PHS_Logger;

    PHS_Logger::logf( ' --- Started ajax request...', PHS_Logger::TYPE_AJAX );

    if( !PHS_Ajax::validate_input() )
        exit;

    if( !($run_result = PHS_Ajax::run_route()) )
    {
        PHS_Logger::logf( 'Error running ajax request.', PHS_Logger::TYPE_AJAX );

        if( PHS_Ajax::st_has_error() )
            PHS_Logger::logf( 'Error: ['.PHS_Ajax::st_get_error_message().']', PHS_Logger::TYPE_AJAX );
    } elseif( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::logf( 'Ajax route ['.PHS::get_route_as_string().'] run with success: '.$debug_data['db_queries_count'].' queries, '.
                          ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s, '.
                          ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s', PHS_Logger::TYPE_AJAX );
    }

    PHS_Logger::logf( ' --- Ajax script finish', PHS_Logger::TYPE_AJAX );
