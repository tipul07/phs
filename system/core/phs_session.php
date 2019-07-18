<?php

namespace phs;

use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_utils;

final class PHS_session extends PHS_Registry
{
    const ERR_DOMAIN = 1, ERR_COOKIE = 2;

    const SESS_DIR_LENGTH = 2, SESS_DIR_MAX_SEGMENTS = 4;

    const SESS_DATA = 'sess_data',
          SESS_DIR = 'sess_dir', SESS_NAME = 'sess_name', SESS_COOKIE_LIFETIME = 'sess_cookie_lifetime', SESS_COOKIE_PATH = 'sess_cookie_path', SESS_AUTOSTART = 'sess_autostart',
          SESS_STARTED = 'sess_started';

    // Make sure session is not considered garbage by adding a parameter in session with a "random" number
    const SESS_TIME_PARAM_NAME = '__phs_t';

    function __construct()
    {
        parent::__construct();
        self::init();
    }

    public static function init()
    {
        if( PHS::prevent_session() )
            return true;

        self::reset_registry();

        if( defined( 'PHS_SESSION_DIR' ) )
            self::set_data( self::SESS_DIR, PHS_SESSION_DIR );
        if( defined( 'PHS_SESSION_NAME' ) )
            self::set_data( self::SESS_NAME, PHS_SESSION_NAME );
        if( defined( 'PHS_SESSION_COOKIE_LIFETIME' ) )
            self::set_data( self::SESS_COOKIE_LIFETIME, PHS_SESSION_COOKIE_LIFETIME );
        if( defined( 'PHS_SESSION_COOKIE_PATH' ) )
            self::set_data( self::SESS_COOKIE_PATH, PHS_SESSION_COOKIE_PATH );
        if( defined( 'PHS_SESSION_AUTOSTART' ) )
            self::set_data( self::SESS_AUTOSTART, PHS_SESSION_AUTOSTART );

        return true;
    }

    public static function _d( $key = null )
    {
        if( PHS::prevent_session()
         or !self::start() )
            return null;

        if( !($sess_arr = self::get_data( self::SESS_DATA ))
         or !is_array( $sess_arr ) )
            $sess_arr = array();

        if( $key === null )
            $sess_arr = array();
        elseif( isset( $sess_arr[$key] ) )
            unset( $sess_arr[$key] );

        self::set_data( self::SESS_DATA, $sess_arr );

        return true;
    }

    public static function _g( $key = null )
    {
        if( PHS::prevent_session()
         or !self::start() )
            return null;

        if( !($sess_arr = self::get_data( self::SESS_DATA ))
         or !is_array( $sess_arr ) )
            $sess_arr = array();

        if( $key === null )
            return $sess_arr;

        if( !isset( $sess_arr[$key] ) )
            return null;

        return $sess_arr[$key];
    }

    public static function _s( $key, $val )
    {
        if( PHS::prevent_session()
         or !self::start() )
            return false;

        if( !($sess_arr = self::get_data( self::SESS_DATA ))
         or !is_array( $sess_arr ) )
            $sess_arr = array();

        $sess_arr[$key] = $val;

        self::set_data( self::SESS_DATA, $sess_arr );

        return true;
    }

    public static function set_cookie( $name, $val, $params = false )
    {
        self::st_reset_error();

        if( empty( $name ) )
        {
            self::st_set_error( self::ERR_COOKIE, self::_t( 'Please provide valid cookie name.' ) );
            return false;
        }

        if( !defined( 'PHS_DOMAIN' ) )
        {
            self::st_set_error( self::ERR_COOKIE, self::_t( 'Framework domain is not defined.' ) );
            return false;
        }

        if( @headers_sent() )
        {
            self::st_set_error( self::ERR_COOKIE, self::_t( 'Headers already sent to request.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['alter_globals'] ) )
            $params['alter_globals'] = true;
        else
            $params['alter_globals'] = (!empty( $params['alter_globals'] )?true:false);

        if( !isset( $params['expire_secs'] ) )
            $params['expire_secs'] = 0;
        else
            $params['expire_secs'] = intval( $params['expire_secs'] );

        if( !isset( $params['path'] ) )
            $params['path'] = '/';

        if( !isset( $params['httponly'] ) )
            $params['httponly'] = false;
        else
            $params['httponly'] = (!empty( $params['httponly'] )?true:false);

        if( $params['expire_secs'] < 0 )
            return self::delete_cookie( $name, $params );

        if( !@setcookie( $name, $val, time() + $params['expire_secs'], $params['path'], PHS_DOMAIN, $params['httponly'] ) )
            return false;

        if( !empty( $params['alter_globals'] ) )
        {
            $_COOKIE[$name] = $val;
            $_REQUEST[$name] = $val;
        }

        return true;
    }

    public static function delete_cookie( $name, $params = false )
    {
        self::st_reset_error();

        if( empty( $name ) or !is_string( $name ) )
        {
            self::st_set_error( self::ERR_COOKIE, self::_t( 'Please provide valid cookie name and value.' ) );
            return false;
        }

        if( !defined( 'PHS_DOMAIN' ) )
        {
            self::st_set_error( self::ERR_COOKIE, self::_t( 'Framework domain is not defined.' ) );
            return false;
        }

        if( @headers_sent() )
        {
            self::st_set_error( self::ERR_COOKIE, self::_t( 'Headers already sent to request.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['alter_globals'] ) )
            $params['alter_globals'] = true;
        else
            $params['alter_globals'] = (!empty( $params['alter_globals'] )?true:false);

        if( !isset( $params['path'] ) )
            $params['path'] = '/';

        if( !isset( $params['httponly'] ) )
            $params['httponly'] = false;
        else
            $params['httponly'] = (!empty( $params['httponly'] )?true:false);

        if( !@setcookie( $name, '', time() - 90000, $params['path'], PHS_DOMAIN, $params['httponly'] ) )
            return false;

        if( !empty( $params['alter_globals'] ) )
        {
            if( isset( $_COOKIE[$name] ) )
                unset( $_COOKIE[$name] );
            if( isset( $_REQUEST[$name] ) )
                unset( $_REQUEST[$name] );
        }

        return true;
    }

    public static function get_cookie( $name )
    {
        if( empty( $_COOKIE ) or !is_array( $_COOKIE )
         or !isset( $_COOKIE[$name] ) )
            return null;

        return $_COOKIE[$name];
    }

    public static function start()
    {
        if( PHS::prevent_session() )
            return false;

        if( self::is_started() )
            return true;

        if( !defined( 'PHS_DOMAIN' ) or !constant( 'PHS_DOMAIN' ) )
        {
            self::st_set_error( self::ERR_DOMAIN, self::_t( 'Domain not set.' ) );
            return false;
        }

        session_set_save_handler(
            array( '\\phs\\PHS_session', 'sf_open' ),
            array( '\\phs\\PHS_session', 'sf_close' ),
            array( '\\phs\\PHS_session', 'sf_read' ),
            array( '\\phs\\PHS_session', 'sf_write' ),
            array( '\\phs\\PHS_session', 'sf_destroy' ),
            array( '\\phs\\PHS_session', 'sf_gc' )
        );

        @session_save_path( self::get_data( self::SESS_DIR ) );
        @session_cache_limiter( 'nocache' );
        @session_set_cookie_params( self::get_data( self::SESS_COOKIE_LIFETIME ), self::get_data( self::SESS_COOKIE_PATH ), PHS_DOMAIN );
        @session_name( self::get_data( self::SESS_NAME ) );

        @register_shutdown_function( array( '\\phs\\PHS_session', 'session_close' ) );

        @session_start();

        self::set_data( self::SESS_STARTED, true );

        // safe...
        if( empty( $_SESSION ) or !is_array( $_SESSION ) )
            $_SESSION = array();

        self::set_data( self::SESS_DATA, $_SESSION );

        return true;
    }

    public static function get_session_id_dir_as_array( $id )
    {
        if( empty( $id ) or !is_string( $id )
         or !($return_arr = @str_split( $id, self::SESS_DIR_LENGTH ))
         or !is_array( $return_arr ) )
            return array();

        if( count( $return_arr ) > self::SESS_DIR_MAX_SEGMENTS )
            $return_arr = @array_slice( $return_arr, 0, self::SESS_DIR_MAX_SEGMENTS );

        return $return_arr;
    }

    public static function get_session_id_dir( $id )
    {
        if( empty( $id ) or !is_string( $id ) )
            return '';

        $sess_dir = '';
        if( ($sess_dir = self::get_data( self::SESS_DIR )) )
            $sess_dir = rtrim( $sess_dir, '/\\' );

        if( ($id_arr = self::get_session_id_dir_as_array( $id )) )
            $sess_dir .= '/'.implode( '/', $id_arr );

        return $sess_dir;
    }

    public static function get_session_id_file_name( $id )
    {
        if( !($sess_dir = self::get_session_id_dir( $id )) )
            return 'sess_'.$id;

        return $sess_dir.'/sess_'.$id;
    }

    public static function session_close( $params = false )
    {
        if( PHS::prevent_session()
         or !self::is_started() )
            return true;

        if( !($sess_arr = self::get_data( self::SESS_DATA ))
         or !is_array( $sess_arr ) )
            $sess_arr = array();

        $_SESSION = $sess_arr;

        $_SESSION[self::SESS_TIME_PARAM_NAME] = microtime( true );

        @session_write_close();

        self::set_data( self::SESS_STARTED, false );

        return true;
    }

    public static function sf_open( $path, $session_name )
    {
        if( PHS::prevent_session() )
            return true;

        if( !@is_dir( $path ) )
            PHS_utils::mkdir_tree( $path, array( 'dir_mode' => 0775 ) );

        return true;
    }

    public static function sf_close()
    {
        return true;
    }

    public static function sf_read( $id )
    {
        if( PHS::prevent_session() )
            return true;

        if( !($sess_file = self::get_session_id_file_name( $id ))
         or !@file_exists( $sess_file )
         or !($ret_val = @file_get_contents( $sess_file )) )
            $ret_val = '';

        return $ret_val;
    }

    public static function sf_write( $id, $data )
    {
        if( PHS::prevent_session() )
            return true;

        if( !($sess_file = self::get_session_id_file_name( $id )) )
            return false;

        if( !@file_exists( $sess_file )
        and ($sess_dir = self::get_session_id_dir( $id ))
        and !@is_dir( $sess_dir ) )
        {
            $sess_root = '';
            if( ($sess_root = self::get_data( self::SESS_DATA )) )
                $sess_root = rtrim( $sess_root, '/\\' );

            // maybe we should create directory...
            if( !(PHS_utils::mkdir_tree( $sess_dir, array( 'root' => $sess_root, 'dir_mode' => 0775 ) )) )
                return false;
        }

        if( !($fil = @fopen( $sess_file, 'w' )) )
            return false;

        @fwrite( $fil, $data );
        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

    public static function sf_destroy( $id )
    {
        if( PHS::prevent_session() )
            return true;

        if( ($sess_file = self::get_session_id_file_name( $id ))
        and @file_exists( $sess_file ) )
            @unlink( $sess_file );

        return true;
    }

    public static function sf_gc( $maxlifetime )
    {
        if( PHS::prevent_session() )
            return true;

        $sess_dir = '';
        if( ($sess_dir = self::get_data( self::SESS_DIR )) )
            $sess_dir = rtrim( $sess_dir, '/\\' );

        for( $i = 0; $i < self::SESS_DIR_MAX_SEGMENTS; $i++ )
            $sess_dir .= '/*';

        if( ($file_list = @glob( $sess_dir.'/sess_*' )) )
        {
            foreach( $file_list as $file )
            {
                if( @file_exists( $file )
                and @filemtime( $file ) + $maxlifetime < time() )
                    @unlink( $file );
            }
        }

        return true;
    }

    public static function is_started()
    {
        return (self::get_data( self::SESS_STARTED )?true:false);
    }

    private static function reset_registry()
    {
        self::set_data( self::SESS_DIR, '' );
        self::set_data( self::SESS_NAME, 'PHS_SESS' );
        self::set_data( self::SESS_COOKIE_LIFETIME, 0 );
        self::set_data( self::SESS_COOKIE_PATH, '/' );
        self::set_data( self::SESS_AUTOSTART, false );

        self::set_data( self::SESS_STARTED, false );
        self::set_data( self::SESS_DATA, array() );
    }

}
