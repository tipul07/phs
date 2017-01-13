<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_params;

class PHS_Action_Plugins_integrity extends PHS_Action
{
    const HOOK_LOG_ACTIONS = 'phs_system_logs_actions';

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Plugins\' Integrity' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ) );

            return $action_result;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage plugins.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_instance */
        if( !($plugins_instance = PHS::load_model( 'plugins' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load plugins model.' ) );
            return self::default_action_result();
        }

        if( !($plugin_names_arr = $plugins_instance->get_all_plugin_names_from_dir()) )
            $plugin_names_arr = array();

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $check_plugin = PHS_params::_pg( 'check_plugin', PHS_params::T_NOHTML );
        $command = PHS_params::_pg( 'command', PHS_params::T_NOHTML );

        if( !empty( $command )
        and !in_array( $command, array( 'integrity_check', 'download_file' ) ) )
            $command = 'integrity_check';

        if( !empty( $command ) )
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
            'check_plugin' => $check_plugin,
            'plugin_names_arr' => $plugin_names_arr,
        );

        return $this->quick_render_template( 'plugins_integrity', $data );
    }
}
