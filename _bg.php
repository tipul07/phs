<?php

    @set_time_limit( 0 );

    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );

    // HTTP/1.0
    header( 'Pragma: no-cache' );

    define( 'PHS_PREVENT_SESSION', true );
    define( 'PHS_SCRIPT_SCOPE', 'background' );

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_Bg_jobs;
    use \phs\libraries\PHS_Logger;
    use \phs\libraries\PHS_Action;

    PHS_Logger::logf( ' --- Started bg job...', PHS_Logger::TYPE_BACKGROUND );

    $input = '';
    if( !empty( $_SERVER['argv'] ) && is_array( $_SERVER['argv'] ) && !empty( $_SERVER['argv'][1] ) )
        $input = $_SERVER['argv'][1];

    if( !($parsed_input = PHS_Bg_jobs::bg_validate_input( $input ))
     || empty( $parsed_input['job_data'] ) )
    {
        PHS_Logger::logf( 'INVALID job input.', PHS_Logger::TYPE_BACKGROUND );
        exit;
    }

    $job_arr = $parsed_input['job_data'];

    $run_job_extra = [];
    $run_job_extra['bg_jobs_model'] = (!empty( $parsed_input['bg_jobs_model'] )?$parsed_input['bg_jobs_model']:false);

    if( !($action_result = PHS_Bg_jobs::bg_run_job( $job_arr, $run_job_extra )) )
    {
        PHS_Logger::logf( 'Error running job [#'.$job_arr['id'].'] ('.$job_arr['route'].')', PHS_Logger::TYPE_BACKGROUND );

        if( PHS_Bg_jobs::st_has_error() )
            PHS_Logger::logf( 'Job error: ['.PHS_Bg_jobs::st_get_error_message().']', PHS_Logger::TYPE_BACKGROUND );
    } elseif( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::logf( 'Job #'.$job_arr['id'].' ('.$job_arr['route'].') run with success: '.$debug_data['db_queries_count'].' queries, '.
                          ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s, '.
                          ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s', PHS_Logger::TYPE_BACKGROUND );
    }

    PHS_Logger::logf( ' --- Background script finish', PHS_Logger::TYPE_BACKGROUND );

    if( !empty( $action_result ) )
    {
        $action_result = PHS::validate_array( $action_result, PHS_Action::default_action_result() );
        if( !empty( $job_arr['return_buffer'] ) )
            echo @json_encode( $action_result );
    }
