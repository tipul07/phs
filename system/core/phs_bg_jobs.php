<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use phs\libraries\PHS_Registry;

//! @version 1.00

class PHS_bg_jobs extends PHS_Registry
{
    const ERR_DB_INSERT = 30000, ERR_COMMAND = 30001, ERR_RUN_JOB = 30002, ERR_JOB_DB = 30003, ERR_JOB_STALLING = 30004;

    const DATA_JOB_KEY = 'bg_jobs_job_data';

    public static function current_job_data( $job_data = null )
    {
        if( $job_data === null )
            return self::get_data( self::DATA_JOB_KEY );

        return self::set_data( self::DATA_JOB_KEY, $job_data );
    }

    public static function get_current_job_parameters()
    {
        if( !($job_arr = self::current_job_data())
         or !is_array( $job_arr )
         or empty( $job_arr['params'] )
         or !($job_params_arr = @json_decode( $job_arr['params'], true )) )
            return array();

        return $job_params_arr;
    }

    public static function run( $route, $params = false, $extra = false )
    {
        self::st_reset_error();

        $route_parts = false;
        if( is_string( $route )
        and !($route_parts = PHS::parse_route( $route )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Route is invalid.' ) );

            return false;
        }

        if( is_array( $route ) )
            $route_parts = $route;

        if( empty( $route_parts ) or !is_array( $route_parts ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Route is invalid.' ) );

            return false;
        }

        if( !isset( $route_parts['plugin'] ) )
            $route_parts['plugin'] = false;
        if( !isset( $route_parts['controller'] ) )
            $route_parts['controller'] = false;
        if( !isset( $route_parts['action'] ) )
            $route_parts['action'] = false;

        if( !($cleaned_route = PHS::route_from_parts(
                                        array(
                                            'p' => $route_parts['plugin'],
                                            'c' => $route_parts['controller'],
                                            'a' => $route_parts['action'],
                                        )
             )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Route is invalid.' ) );

            return false;
        }

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $extra['return_command'] ) )
            $extra['return_command'] = false;
        if( !isset( $extra['async_task'] ) )
            $extra['async_task'] = true;
        else
            $extra['async_task'] = (!empty( $extra['async_task'] )?true:false);

        if( empty( $extra['timed_action'] ) )
            $extra['timed_action'] = false;
        else
            $extra['timed_action'] = validate_db_date( $extra['timed_action'] );

        if( !is_array( $params ) )
            $params = array();

        /** @var \phs\system\core\models\PHS_Model_Bg_jobs $bg_jobs_model */
        if( !($bg_jobs_model = PHS::load_model( 'bg_jobs' )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t load background jobs model.' ) );

            return false;
        }

        $insert_arr = array();
        $insert_arr['uid'] = 0;
        $insert_arr['pid'] = 0;
        $insert_arr['route'] = $cleaned_route;
        $insert_arr['params'] = @json_encode( $params );
        $insert_arr['timed_action'] = $extra['timed_action'];

        if( !($job_arr = $bg_jobs_model->insert( array( 'fields' => $insert_arr ) ))
         or empty( $job_arr['id'] ) )
        {
            if( $bg_jobs_model->has_error() )
                self::st_copy_error( $bg_jobs_model );
            else
                self::st_set_error( self::ERR_DB_INSERT, self::_t( 'Couldn\'t save database details. Please try again.' ) );

            return false;
        }

        $cmd_extra = array();
        $cmd_extra['async_task'] = $extra['async_task'];
        $cmd_extra['bg_jobs_model'] = $bg_jobs_model;

        if( !($cmd_parts = self::get_job_command( $job_arr, $cmd_extra ))
         or empty( $cmd_parts['cmd'] ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_COMMAND, self::_t( 'Couldn\'t get background job command.' ) );

            $bg_jobs_model->hard_delete( $job_arr );

            return false;
        }

        self::current_job_data( $job_arr );

        if( !empty( $extra['return_command'] ) )
            return $cmd_parts['cmd'];

        if( !empty_db_date( $extra['timed_action'] )
        and parse_db_date( $extra['timed_action'] ) > time() )
            return true;

        PHS_Logger::logf( 'Launching job: [#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_DEBUG );

        return (@system( $cmd_parts['cmd'] ) !== false );
    }

    public static function get_job_command( $job_data, $extra = false )
    {
        self::st_reset_error();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        /** @var \phs\system\core\models\PHS_Model_Bg_jobs $bg_jobs_model */
        if( !empty( $extra['bg_jobs_model'] ) )
            $bg_jobs_model = $extra['bg_jobs_model'];
        else
            $bg_jobs_model = PHS::load_model( 'bg_jobs' );

        if( !isset( $extra['async_task'] ) )
            $extra['async_task'] = true;
        else
            $extra['async_task'] = (!empty( $extra['async_task'] )?true:false);

        if( empty( $job_data )
         or empty( $bg_jobs_model )
         or !($job_arr = $bg_jobs_model->data_to_array( $job_data )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_COMMAND, self::_t( 'Couldn\'t get background jobs details.' ) );

            return false;
        }

        $pub_key = microtime( true );

        $clean_cmd = PHP_EXEC.' '.PHS::get_background_path().' '.PHS_crypt::quick_encode( $job_arr['id'].'::'.md5( $job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'] ) ).'::'.$pub_key;

        if( strtolower( substr( PHP_OS, 0, 3 ) ) == 'win' )
        {
            // launching background task under windows
            $cmd = 'start '.(!empty($extra['async_task'])?' /B ':'').$clean_cmd;
        } else
        {
            $cmd = $clean_cmd . ' 2>/dev/null >&- <&- >/dev/null';
            if( !empty($extra['async_task']) )
                $cmd .= ' &';
        }

        return array(
            'cmd' => $cmd,
            'pub_key' => $pub_key,
        );
    }

    public static function bg_validate_input( $input_str )
    {
        if( empty( $input_str )
         or @strstr( $input_str, '::' ) === false
         or !($parts_arr = explode( '::', $input_str, 2 ))
         or empty( $parts_arr[0] ) or empty( $parts_arr[1] ) )
        {
            PHS_Logger::logf( 'Invalid input', PHS_Logger::TYPE_DEBUG );
            return false;
        }

        $crypted_data = $parts_arr[0];
        $pub_key = $parts_arr[1];

        /** @var \phs\system\core\models\PHS_Model_Bg_jobs $bg_jobs_model */
        if( !($decrypted_data = PHS_crypt::quick_decode( $crypted_data ))
         or !($decrypted_parts = explode( '::', $decrypted_data, 2 ))
         or empty( $decrypted_parts[0] ) or empty( $decrypted_parts[1] )
         or !($job_id = intval( $decrypted_parts[0] ))
         or !($bg_jobs_model = PHS::load_model( 'bg_jobs' ))
         or !($job_arr = $bg_jobs_model->get_details( $job_id ))
         or $decrypted_parts[1] != md5( $job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'] ) )
        {
            PHS_Logger::logf( 'Input validation failed', PHS_Logger::TYPE_DEBUG );
            return false;
        }

        return array(
            'job_data' => $job_arr,
            'pub_key' => $pub_key,
            'bg_jobs_model' => $bg_jobs_model,
        );
    }

    public static function bg_run_job( $job_data, $extra = false )
    {
        self::st_reset_error();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $extra['force_run'] ) )
            $extra['force_run'] = false;
        else
            $extra['force_run'] = true;

        /** @var \phs\system\core\models\PHS_Model_Bg_jobs $bg_jobs_model */
        if( !empty( $extra['bg_jobs_model'] ) )
            $bg_jobs_model = $extra['bg_jobs_model'];
        else
            $bg_jobs_model = PHS::load_model( 'bg_jobs' );

        /** @var \phs\system\core\models\PHS_Model_Bg_jobs $bg_jobs_model */
        if( empty( $job_data )
         or empty( $bg_jobs_model )
         or !($job_arr = $bg_jobs_model->data_to_array( $job_data )) )
        {
            if( $bg_jobs_model->has_error() )
                self::st_copy_error( $bg_jobs_model );

            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Couldn\'t get background jobs details.' ) );

            return false;
        }

        self::current_job_data( $job_arr );

        if( $bg_jobs_model->job_is_running( $job_arr ) )
        {
            if( ($job_stalling = $bg_jobs_model->job_is_stalling( $job_arr )) === null )
            {
                if( $bg_jobs_model->has_error() )
                    self::st_copy_error( $bg_jobs_model );

                return false;
            }

            if( empty( $job_stalling ) )
            {
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Job already running.' ) );
                return false;
            } elseif( !empty( $job_stalling ) and empty( $extra['force_run'] ) )
            {
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Job seems to stall. Run not told to force execution.' ) );
                return false;
            }
        }

        if( !($pid = @getmypid()) )
            $pid = -1;

        $edit_arr = array();
        $edit_arr['pid'] = $pid;

        if( !($new_job_arr = $bg_jobs_model->edit( $job_arr, array( 'fields' => $edit_arr ) )) )
        {
            if( $bg_jobs_model->has_error() )
                self::st_copy_error( $bg_jobs_model );

            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Couldn\'t save background jobs details in database.' ) );

            return false;
        }

        $job_arr = $new_job_arr;

        self::current_job_data( $job_arr );

        if( !PHS_Scope::current_scope( PHS_Scope::SCOPE_BACKGROUND )
         or !PHS::set_route( $job_arr['route'] ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Error preparing environment.' ) );

            $error_arr = self::st_get_error();

            $error_params = array();
            $error_params['last_error'] = self::st_get_error_message();;

            $bg_jobs_model->job_error_stop( $job_arr, $error_params );

            self::st_copy_error_from_array( $error_arr );

            return false;
        }

        $execution_params = array();
        $execution_params['die_on_error'] = false;

        if( !PHS::execute_route( $execution_params ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Error executing route.' ) );

            $error_arr = self::st_get_error();

            $error_params = array();
            $error_params['last_error'] = self::st_get_error_message();

            $bg_jobs_model->job_error_stop( $job_arr, $error_params );

            self::st_copy_error_from_array( $error_arr );

            return false;
        }

        $bg_jobs_model->hard_delete( $job_arr );

        return true;
    }
}

