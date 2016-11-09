<?php

    @set_time_limit( 0 );

    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );

    // HTTP/1.0
    header( 'Pragma: no-cache' );

    define( 'PHS_PREVENT_SESSION', true );

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_Agent;
    use \phs\PHS_Scope;
    use \phs\libraries\PHS_Logger;

    PHS_Scope::current_scope( PHS_Scope::SCOPE_AGENT );

    PHS_Logger::logf( ' --- Started agent...', PHS_Logger::TYPE_AGENT );

    if( !($agent_obj = new PHS_Agent())
     or !($run_result = $agent_obj->check_agent_jobs()) )
    {
        PHS_Logger::logf( 'Error checking agent jobs!', PHS_Logger::TYPE_AGENT );

        if( $agent_obj
        and $agent_obj->has_error() )
            PHS_Logger::logf( 'Agent error: ['.$agent_obj->get_error_message().']', PHS_Logger::TYPE_AGENT );
    } elseif( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::logf( 'Agent run with success: '.$run_result['jobs_count'].' total jobs, '.
                          $run_result['jobs_success'].' success jobs, '.$run_result['jobs_errors'].' error jobs, '.
                          $debug_data['db_queries_count'].' queries,'.
                          ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s,'.
                          ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s', PHS_Logger::TYPE_AGENT );
    }

    PHS_Logger::logf( ' --- Agent finish', PHS_Logger::TYPE_AGENT );
