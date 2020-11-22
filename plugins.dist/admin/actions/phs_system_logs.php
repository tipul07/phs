<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Params;

class PHS_Action_System_logs extends PHS_Action
{
    const HOOK_LOG_ACTIONS = 'phs_system_logs_actions';

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'System logs' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_VIEW_LOGS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to view system logs.' ) );
            return self::default_action_result();
        }

        if( !($logging_files_arr = PHS_Logger::get_logging_files()) )
            $logging_files_arr = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $log_file = PHS_Params::_pg( 'log_file', PHS_Params::T_NOHTML );
        $log_lines = PHS_Params::_pg( 'log_lines', PHS_Params::T_INT );
        $search_term = PHS_Params::_pg( 'search_term', PHS_Params::T_NOHTML );
        $command = PHS_Params::_pg( 'command', PHS_Params::T_NOHTML );

        if( empty( $log_lines ) or $log_lines < 0 )
            $log_lines = 20;

        if( !empty( $command )
        and !in_array( $command, array( 'display_file', 'download_file' ) ) )
            $command = 'display_file';

        if( !empty( $command ) ) // PHS_Scope::current_scope() == PHS_Scope::SCOPE_AJAX )
        {
            $action_result = self::default_action_result();

            switch( $command )
            {
                case 'download_file':
                    if( empty( $log_file )
                     or empty( $logging_files_arr[$log_file] )
                     or !@file_exists( $logging_files_arr[$log_file] ) )
                    {
                        PHS_Notifications::add_error_notice( $this->_pt( 'Invalid log file for download.' ) );
                        return $action_result;
                    }

                    if( !($file_size = @filesize( $logging_files_arr[$log_file] )) )
                        $file_size = 0;

                    @header( 'Content-Description: File Transfer' );
                    @header( 'Content-Type: application/octet-stream' );
                    @header( 'Content-Disposition: attachment; filename=' . $log_file );
                    @header( 'Content-Transfer-Encoding: binary' );
                    @header( 'Connection: Keep-Alive' );
                    @header( 'Expires: 0' );
                    @header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
                    @header( 'Pragma: public' );
                    @header( 'Content-Length: ' . $file_size );

                    @readfile( $logging_files_arr[$log_file] );
                    exit;
                break;

                default:
                case 'display_file':
                    if( empty( $log_file )
                     or empty( $logging_files_arr[$log_file] ) )
                    {
                        PHS_Notifications::add_error_notice( $this->_pt( 'Cannot read provided log file data.' ) );
                        return $action_result;
                    }

                    $header_buffer = PHS_Logger::get_file_header_str();
                    if( !($tail_buffer = PHS_Logger::tail_log( $log_file, $log_lines )) )
                        $tail_buffer = '';

                    if( strstr( $tail_buffer, $header_buffer ) === false )
                        $log_file_buffer = $header_buffer.$tail_buffer;
                    else
                        $log_file_buffer = $tail_buffer;

                    $data = array(
                        'HOOK_LOG_ACTIONS' => self::HOOK_LOG_ACTIONS,
                        'log_lines' => $log_lines,
                        'log_file' => $log_file,
                        'log_full_file' => $logging_files_arr[$log_file],
                        'log_file_buffer' => $log_file_buffer,
                    );

                    if( ($render_result = $this->quick_render_template( 'system_logs_display', $data ))
                    and !empty( $render_result['buffer'] ) )
                        $action_result['buffer'] = $render_result['buffer'];
                break;
            }

            return $action_result;
        }

        $data = array(
            'log_file' => $log_file,
            'log_lines' => $log_lines,
            'logging_files_arr' => $logging_files_arr,
        );

        return $this->quick_render_template( 'system_logs', $data );
    }
}
