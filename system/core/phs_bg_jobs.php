<?php

namespace phs;

use \phs\PHS_crypt;
use \phs\libraries\PHS_Language;
use phs\libraries\PHS_Logger;

//! @version 1.00

class PHS_bg_jobs extends PHS_Language
{
    function __construct()
    {
        parent::__construct();
    }

    public static function run( $route, $params = false, $extra = false )
    {
        self::st_reset_error();

        if( !($route_parts = PHS::parse_route( $route ))
         or !($cleaned_route = PHS::route_from_parts(
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

        $params_str = '';

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

        foreach( $params as $key => $val )
            $params_str .= '&'.$key.'='.$val;

        $clean_cmd = PHP_EXEC.' '.PHS::get_background_path().' '.PHS_crypt::quick_encode( '&act='.$act.'&mysec='.md5( $APP_CFG['private_key'].'::'.$act ).$params_str );

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

        PHS_Logger::logf( 'BG job: ['.$cmd.']' );

        return @system( $cmd );
    }

}

