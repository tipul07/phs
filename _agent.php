<?php

    @set_time_limit( 0 );

    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );

    // HTTP/1.0
    header( 'Pragma: no-cache' );

    define( 'PHS_PREVENT_SESSION', true );
    define( 'PHS_SCRIPT_SCOPE', 'agent' );

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_Agent;
    use \phs\libraries\PHS_Logger;

    if( !($agent_obj = new PHS_Agent()) )
    {
        PHS_Logger::error( 'Error instantiating PHS_Agent class!', PHS_Logger::TYPE_AGENT );
        exit;
    }

    PHS_Logger::notice( "\n".' --- Started agent...', PHS_Logger::TYPE_AGENT );

    PHS_Logger::notice( ' - Checking stalling jobs...', PHS_Logger::TYPE_AGENT );

    if( !($run_result = $agent_obj->check_stalling_agent_jobs()) )
    {
        PHS_Logger::error( 'Error checking stalling agent jobs!', PHS_Logger::TYPE_AGENT );

        if( $agent_obj->has_error() ) {
            PHS_Logger::error('Agent error: ['.$agent_obj->get_error_message().']', PHS_Logger::TYPE_AGENT);
        }
    } elseif( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::notice( 'Stalling agent jobs checked with success: '.$run_result['jobs_running'].' jobs running, '.
                          $run_result['jobs_not_dead'].' not dead jobs, '.$run_result['jobs_stopped'].' jobs stopped, '.
                          $run_result['jobs_stopped_error'].' jobs FAILED to stop.', PHS_Logger::TYPE_AGENT );
    }

    PHS_Logger::notice( ' - Launching agent jobs...', PHS_Logger::TYPE_AGENT );

    if( !($run_result = $agent_obj->check_agent_jobs()) )
    {
        PHS_Logger::error( 'Error checking agent jobs!', PHS_Logger::TYPE_AGENT );

        if( $agent_obj->has_error() ) {
            PHS_Logger::error('Agent error: ['.$agent_obj->get_error_message().']', PHS_Logger::TYPE_AGENT);
        }
    } elseif( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::notice( 'Agent run with success: '.$run_result['jobs_count'].' total jobs, '.
                          $run_result['jobs_success'].' success jobs, '.$run_result['jobs_errors'].' error jobs.', PHS_Logger::TYPE_AGENT );
    }

    if( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::debug( 'DEBUG data: '.$debug_data['db_queries_count'].' queries,'.
                          ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s,'.
                          ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s', PHS_Logger::TYPE_AGENT );
    }

    PHS_Logger::notice( "\n".' --- Agent finish', PHS_Logger::TYPE_AGENT );
