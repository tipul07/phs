<?php

final class PHS_session extends PHS_Registry
{
    const ERR_DOMAIN = 1;

    const SESS_DATA = 'sess_data',
          SESS_DIR = 'sess_dir', SESS_NAME = 'sess_name', SESS_COOKIE_LIFETIME = 'sess_cookie_lifetime', SESS_COOKIE_PATH = 'sess_cookie_path', SESS_AUTOSTART = 'sess_autostart',
          SESS_STARTED = 'sess_started';

    function __construct()
    {
        parent::__construct();
        self::init();
    }

    public static function init()
    {
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

    public static function _g( $key = null )
    {
        if( !self::start() )
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
        if( !self::start() )
            return false;

        if( !($sess_arr = self::get_data( self::SESS_DATA ))
         or !is_array( $sess_arr ) )
            $sess_arr = array();

        $sess_arr[$key] = $val;

        self::set_data( self::SESS_DATA, $sess_arr );

        return true;
    }


    public static function start()
    {
        if( self::is_started() )
            return true;

        if( !defined( 'PHS_DOMAIN' ) or !constant( 'PHS_DOMAIN' ) )
        {
            self::st_set_error( self::ERR_DOMAIN, self::_t( 'Domain not set.' ) );
            return false;
        }

        @session_set_save_handler(
            array( 'PHS_session', 'sf_open' ),
            array( 'PHS_session', 'sf_close' ),
            array( 'PHS_session', 'sf_read' ),
            array( 'PHS_session', 'sf_write' ),
            array( 'PHS_session', 'sf_destroy' ),
            array( 'PHS_session', 'sf_gc' )
        );

        session_save_path( self::get_data( self::SESS_DIR ) );
        session_cache_limiter( 'nocache' );
        session_set_cookie_params( self::get_data( self::SESS_COOKIE_LIFETIME ), self::get_data( self::SESS_COOKIE_PATH ), PHS_DOMAIN );
        session_name( self::get_data( self::SESS_NAME ) );

        @register_shutdown_function( array( 'PHS_session', 'session_close' ) );

        @session_start();

        self::set_data( self::SESS_STARTED, true );

        self::set_data( self::SESS_DATA, $_SESSION );

        return true;
    }

    public static function session_close( $params = false )
    {
        if( !self::is_started() )
            return true;

        if( !($sess_arr = self::get_data( self::SESS_DATA ))
         or !is_array( $sess_arr ) )
            $sess_arr = array();

        $_SESSION = $sess_arr;

        @session_write_close();

        self::set_data( self::SESS_STARTED, false );

        return true;
    }

    public static function sf_open( $path, $session_name )
    {
        if( !@is_dir( $path ) )
            @mkdir( $path, 0775 );

        return true;
    }

    public static function sf_close()
    {
        return true;
    }

    public static function sf_read( $id )
    {
        $sess_file = self::get_data( self::SESS_DIR ).'/sess_'.$id;
        if( !@file_exists( $sess_file )
         or !($ret_val = @file_get_contents( $sess_file )) )
            $ret_val = '';

        return $ret_val;
    }

    public static function sf_write( $id, $data )
    {
        if( !($fil = @fopen( self::get_data( self::SESS_DIR ).'/sess_'.$id, 'w' )) )
            return false;

        @fwrite( $fil, $data );
        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

    public static function sf_destroy( $id )
    {
        $file = self::get_data( self::SESS_DIR ).'/sess_'.$id;
        if( @file_exists( $file ) )
            @unlink( $file );

        return true;
    }

    public static function sf_gc( $maxlifetime )
    {
        if( ($file_list = @glob( self::get_data( self::SESS_DIR ).'/sess_*' )) )
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
