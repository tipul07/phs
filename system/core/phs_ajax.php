<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Registry;
use \phs\PHS_Crypt;

//! @version 1.00

class PHS_Ajax extends PHS_Registry
{
    const ERR_DB_INSERT = 30000, ERR_COMMAND = 30001, ERR_RUN_JOB = 30002, ERR_JOB_DB = 30003, ERR_JOB_STALLING = 30004;

    const PARAM_PUB_KEY = '_apk', PARAM_FB_KEY = '_phs_ajax_fb', PARAM_CHECK_SUM = '_achks';

    const TIME_OFFSET = 1460000000;

    private static $_ajax_checksum_timeout = 86400; // checksum will fail after one day...

    public static function checksum_timeout( $timeout = false )
    {
        if( $timeout === false )
            return self::$_ajax_checksum_timeout;

        self::$_ajax_checksum_timeout = intval( $timeout );

        return self::$_ajax_checksum_timeout;
    }

    /**
     * @param bool|array $route_arr
     * @param bool|array $args
     * @param bool|array $extra
     *
     * @return mixed|string
     */
    public static function url( $route_arr = false, $args = false, $extra = false )
    {
        self::st_reset_error();

        if( empty( $route_arr ) or !is_array( $route_arr ) )
            $route_arr = array();

        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        $args = self::get_ajax_validation_params( $args );

        $extra['for_scope'] = PHS_Scope::SCOPE_AJAX;

        return PHS::url( $route_arr, $args, $extra );
    }

    public static function get_ajax_validation_params( $args = false )
    {
        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        $pub_key = time() - self::TIME_OFFSET;
        $check_sum = md5( $pub_key.':'.PHS_Crypt::crypting_key() );

        $args[self::PARAM_PUB_KEY] = $pub_key;
        $args[self::PARAM_CHECK_SUM] = $check_sum;

        return $args;
    }

    public static function validate_input()
    {
        if( !($pub_key = PHS_Params::_g( self::PARAM_PUB_KEY, PHS_Params::T_INT ))
         or !($check_sum = PHS_Params::_g( self::PARAM_CHECK_SUM, PHS_Params::T_ASIS )) )
        {
            PHS_Logger::logf( 'Required parameters not found.', PHS_Logger::TYPE_AJAX );
            return false;
        }

        $computed_checksum = md5( $pub_key.':'.PHS_Crypt::crypting_key() );

        $pub_key += self::TIME_OFFSET;

        if( $computed_checksum != $check_sum
         or $pub_key + self::checksum_timeout() < time() )
        {
            PHS_Logger::logf( 'Checksum failed. ['.$computed_checksum.' != '.$check_sum.']', PHS_Logger::TYPE_AJAX );
            return false;
        }

        return true;
    }

    public static function run_route( $extra = false )
    {
        self::st_reset_error();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( !PHS_Scope::current_scope( PHS_Scope::SCOPE_AJAX ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Error preparing environment.' ) );

            return false;
        }

        $execution_params = array();
        $execution_params['die_on_error'] = false;

        if( !($action_result = PHS::execute_route( $execution_params )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Error executing route [%s].', PHS::get_route_as_string() ) );

            return false;
        }

        return $action_result;
    }
}

