<?php

namespace phs;

use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Logger;

//! @version 1.00

class PHS_bg_jobs extends PHS_Language
{
    const ERR_DB_INSERT = 30000;

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

        if( !is_array( $params ) )
            $params = array();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
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

        if( !($job_arr = $bg_jobs_model->insert( array( 'fields' => $insert_arr ) ))
         or empty( $job_arr['id'] ) )
        {
            if( $bg_jobs_model->has_error() )
                self::st_copy_error( $bg_jobs_model );
            else
                self::st_set_error( self::ERR_DB_INSERT, self::_t( 'Couldn\'t save database details. Please try again.' ) );

            return false;
        }

        $pub_key = microtime( true );

        $clean_cmd = PHP_EXEC.' '.PHS::get_background_path().' '.PHS_crypt::quick_encode( $job_arr['id'].'::'.md5( $cleaned_route.':'.$pub_key.':'.$job_arr['cdate'] ) ).'::'.$pub_key;

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

        if( !empty( $extra['return_command'] ) )
            return $cmd;

        PHS_Logger::logf( 'Running job: ['.$cleaned_route.':'.$cmd.']', PHS_Logger::TYPE_DEBUG );

        return @system( $cmd );
    }

    public static function validate_input( $input_str )
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
        $pub_key = $parts_arr[0];
    }

}

