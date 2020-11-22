<?php

namespace phs;

use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Utils;

final class PHS_Session extends PHS_Registry
{
    const ERR_DOMAIN = 1, ERR_COOKIE = 2;

    const SESS_DIR_LENGTH = 2, SESS_DIR_MAX_SEGMENTS = 4;

    const SESS_DATA = 'sess_data',
          SESS_DIR = 'sess_dir', SESS_NAME = 'sess_name', SESS_COOKIE_LIFETIME = 'sess_cookie_lifetime', SESS_COOKIE_PATH = 'sess_cookie_path', SESS_SAMESITE = 'sess_samesite',
          SESS_AUTOSTART = 'sess_autostart', SESS_STARTED = 'sess_started';

    // Make sure session is not considered garbage by adding a parameter in session with a "random" number
    const SESS_TIME_PARAM_NAME = '__phs_t';

    public function __construct()
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
        if( defined( 'PHS_SESSION_SAMESITE' ) )
            self::set_data( self::SESS_SAMESITE, PHS_SESSION_SAMESITE );
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

    /**
     * @param array|bool $options_arr
     *
     * @return array
     */
    public static function validate_cookie_params( $options_arr = false )
    {
        if( empty( $options_arr ) or !is_array( $options_arr ) )
            $options_arr = array();

        if( empty( $options_arr['expires'] ) )
            $options_arr['expires'] = 0;
        if( empty( $options_arr['path'] ) or !is_string( $options_arr['path'] ) )
            $options_arr['path'] = '/';
        if( empty( $options_arr['domain'] ) or !is_string( $options_arr['domain'] ) )
            $options_arr['domain'] = PHS_DOMAIN;
        if( empty( $options_arr['secure'] ) )
            $options_arr['secure'] = false;
        else
            $options_arr['secure'] = true;
        if( empty( $options_arr['httponly'] ) )
            $options_arr['httponly'] = false;
        else
            $options_arr['httponly'] = true;

        if( empty( $options_arr['samesite'] ) or !is_array( $options_arr['samesite'] )
         or !in_array( strtolower( $options_arr['samesite'] ), array( 'none', 'lax', 'strict' ) ) )
            $options_arr['samesite'] = 'Lax';
        else
            $options_arr['samesite'] = ucfirst( strtolower( $options_arr['samesite'] ) );

        return $options_arr;
    }

    /**
     * @param string $name
     * @param string|int $value
     * @param array|bool $options_arr
     */
    public static function raw_setcookie( $name, $value, $options_arr = false )
    {
        $options_arr = self::validate_cookie_params( $options_arr );

        $header = 'Set-Cookie: ';
        $header .= rawurlencode( $name ) . '=' . rawurlencode( $value ) . ';';
        $header .= 'expires=' . \gmdate( 'D, d-M-Y H:i:s T', $options_arr['expires'] ) . ';';
        $header .= 'Max-Age=' . max( 0, (int)($options_arr['expires'] - time())) . ';';
        $header .= 'path=' . rawurlencode( $options_arr['path'] ). ';';
        $header .= 'domain=' . rawurlencode( $options_arr['domain'] ) . ';';

        if( !empty( $options_arr['secure'] ) )
            $header .= 'secure;';
        if( !empty( $options_arr['httponly'] ) )
            $header .= 'httponly;';

        $header .= 'SameSite=' . rawurlencode( $options_arr['samesite'] );

        @header( $header, false );
        $_COOKIE[$name] = $value;
    }

    /**
     * @param string $name
     * @param string $val
     * @param bool|array $params
     *
     * @return bool
     */
    public static function set_cookie( $name, $val, $params = false )
    {
        self::st_reset_error();

        if( empty( $name ) or !is_string( $name ) )
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
            $params['expire_secs'] = (int)$params['expire_secs'];

        if( !isset( $params['path'] ) )
            $params['path'] = '/';

        if( !isset( $params['httponly'] ) )
            $params['httponly'] = false;
        else
            $params['httponly'] = (!empty( $params['httponly'] )?true:false);

        if( !isset( $params['secure'] ) )
            $params['secure'] = false;
        else
            $params['secure'] = (!empty( $params['secure'] )?true:false);

        if( !isset( $params['samesite'] )
         or !in_array( strtolower( $params['samesite'] ), array( 'none', 'lax', 'strict' ), true ) )
            $params['samesite'] = 'Lax';
        else
            $params['samesite'] = ucfirst( strtolower( $params['samesite'] ) );

        if( $params['expire_secs'] < 0 )
            return self::delete_cookie( $name, $params );

        $time_expire = time() + $params['expire_secs'];

        $cookie_params = array(
            'expires' => $time_expire,
            'path' => $params['path'],
            'domain' => PHS_DOMAIN,
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        );

        if( defined( 'PHP_VERSION' ) and version_compare( constant( 'PHP_VERSION' ), '7.3.0', '>=' ) )
        {
            if( !@setcookie( $name, $val, $cookie_params ) )
                return false;
        } elseif( !self::raw_setcookie( $name, $val, $cookie_params ) )
        {
            return false;
        }

        if( !empty( $params['alter_globals'] ) )
        {
            $_COOKIE[$name] = $val;
            $_REQUEST[$name] = $val;
        }

        return true;
    }

    /**
     * @param string $name
     * @param bool|array $params
     *
     * @return bool
     */
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

        if( !isset( $params['secure'] ) )
            $params['secure'] = false;
        else
            $params['secure'] = (!empty( $params['secure'] )?true:false);

        if( !isset( $params['samesite'] )
         or !in_array( strtolower( $params['samesite'] ), array( 'none', 'lax', 'strict' ), true ) )
            $params['samesite'] = 'Lax';
        else
            $params['samesite'] = ucfirst( strtolower( $params['samesite'] ) );

        $time_expire = time() - 90000;

        $cookie_params = array(
            'expires' => $time_expire,
            'path' => $params['path'],
            'domain' => PHS_DOMAIN,
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        );

        if( defined( 'PHP_VERSION' ) and version_compare( constant( 'PHP_VERSION' ), '7.3.0', '>=' ) )
        {
            if( !@setcookie( $name, '', $cookie_params ) )
                return false;
        } else
        {
            if( !self::raw_setcookie( $name, '', $cookie_params ) )
                return false;
        }

        if( !empty( $params['alter_globals'] ) )
        {
            if( isset( $_COOKIE[$name] ) )
                unset( $_COOKIE[$name] );
            if( isset( $_REQUEST[$name] ) )
                unset( $_REQUEST[$name] );
        }

        return true;
    }

    /**
     * @param string $name
     *
     * @return string|null
     */
    public static function get_cookie( $name )
    {
        if( empty( $_COOKIE ) or !is_array( $_COOKIE )
         or !isset( $_COOKIE[$name] ) )
            return null;

        return $_COOKIE[$name];
    }

    /**
     * @return bool
     */
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

        @session_set_save_handler(
            array( '\\phs\\PHS_Session', 'sf_open' ),
            array( '\\phs\\PHS_Session', 'sf_close' ),
            array( '\\phs\\PHS_Session', 'sf_read' ),
            array( '\\phs\\PHS_Session', 'sf_write' ),
            array( '\\phs\\PHS_Session', 'sf_destroy' ),
            array( '\\phs\\PHS_Session', 'sf_gc' )
        );

        @session_save_path( self::get_data( self::SESS_DIR ) );
        @session_cache_limiter( 'nocache' );
        @session_name( self::get_data( self::SESS_NAME ) );

        // SameSite session cookie...
        if( defined( 'PHP_VERSION' ) and version_compare( constant( 'PHP_VERSION' ), '7.3.0', '>=' ) )
        {
            @session_set_cookie_params( array(
                                        'lifetime' => self::get_data( self::SESS_COOKIE_LIFETIME ),
                                        'path' => self::get_data( self::SESS_COOKIE_PATH ),
                                        'domain' => PHS_DOMAIN,
                                        'secure' => (PHS::is_secured_request()?true:false),
                                        'httponly' => true,
                                        'samesite' => self::get_data( self::SESS_SAMESITE ) ) );
        } else
        {
            @session_set_cookie_params( self::get_data( self::SESS_COOKIE_LIFETIME ),
                                        self::get_data( self::SESS_COOKIE_PATH ),
                                        PHS_DOMAIN,
                                        (PHS::is_secured_request()?true:false),
                                        true );
        }

        @register_shutdown_function( array( '\\phs\\PHS_Session', 'session_close' ) );

        @session_start();

        // If provided session ID is not safe, generate a new one
        if( !self::safe_session_id( @session_id() ) )
        {
            @session_regenerate_id( true );
        }

        self::set_data( self::SESS_STARTED, true );

        // safe...
        if( empty( $_SESSION ) or !is_array( $_SESSION ) )
            $_SESSION = array();

        self::set_data( self::SESS_DATA, $_SESSION );

        return true;
    }

    public static function safe_session_id( $id )
    {
        if( empty( $id ) or !is_string( $id )
         or preg_match( '@[^a-zA-Z0-9_]@', $id ) )
            return false;

        return $id;
    }

    /**
     * @param string $id
     *
     * @return array
     */
    public static function get_session_id_dir_as_array( $id )
    {
        if( empty( $id ) or !is_string( $id )
         or !self::safe_session_id( $id )
         or !($return_arr = @str_split( $id, self::SESS_DIR_LENGTH ))
         or !is_array( $return_arr ) )
            return array();

        if( count( $return_arr ) > self::SESS_DIR_MAX_SEGMENTS )
            $return_arr = @array_slice( $return_arr, 0, self::SESS_DIR_MAX_SEGMENTS );

        return $return_arr;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function get_session_id_dir( $id )
    {
        if( empty( $id ) or !is_string( $id )
         or !self::safe_session_id( $id ) )
            return '';

        $sess_dir = '';
        if( ($sess_dir = self::get_data( self::SESS_DIR )) )
            $sess_dir = rtrim( $sess_dir, '/\\' );

        if( empty( $sess_dir ) )
            $sess_dir = '';

        if( ($id_arr = self::get_session_id_dir_as_array( $id )) )
            $sess_dir .= '/'.implode( '/', $id_arr );

        return $sess_dir;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function get_session_file_name_for_id( $id )
    {
        if( !self::safe_session_id( $id ) )
            return false;

        return 'sess_'.$id;
    }

    /**
     * @param string $id
     *
     * @return string|bool
     */
    public static function get_session_id_file_name( $id )
    {
        if( !self::safe_session_id( $id ) )
            return false;

        if( !($sess_dir = self::get_session_id_dir( $id )) )
            return self::get_session_file_name_for_id( $id );

        return $sess_dir.'/'.self::get_session_file_name_for_id( $id );
    }

    /**
     * @param bool $params
     *
     * @return bool
     */
    public static function session_close( $params = false )
    {
        if( PHS::prevent_session()
         or !self::is_started()
         or !self::safe_session_id( @session_id() ) )
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
            PHS_Utils::mkdir_tree( $path, array( 'dir_mode' => 0775 ) );

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

        if( !self::safe_session_id( $id )
         or !($sess_file = self::get_session_id_file_name( $id ))
         or !@file_exists( $sess_file )
         or !($ret_val = @file_get_contents( $sess_file )) )
            $ret_val = '';

        return $ret_val;
    }

    public static function sf_write( $id, $data )
    {
        if( !self::safe_session_id( $id )
         or PHS::prevent_session() )
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
            if( !(PHS_Utils::mkdir_tree( $sess_dir, array( 'root' => $sess_root, 'dir_mode' => 0775 ) )) )
                return false;
        }

        if( !($fil = @fopen( $sess_file, 'wb' )) )
            return false;

        @fwrite( $fil, $data );
        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

    public static function sf_destroy( $id )
    {
        if( !self::safe_session_id( $id )
         or PHS::prevent_session() )
            return true;

        if( ($sess_file = self::get_session_id_file_name( $id ))
        and @file_exists( $sess_file ) )
            @unlink( $sess_file );

        return true;
    }

    public static function sf_gc( $maxlifetime )
    {
        if( PHS::prevent_session()
         or self::sessions_gc( $maxlifetime ) )
            return true;

        return false;
    }

    /**
     * @param bool|int $maxlifetime
     *
     * @return array|bool
     */
    public static function sessions_gc( $maxlifetime = false )
    {
        if( ($sess_dir = self::get_data( self::SESS_DIR )) )
            $sess_dir = rtrim( $sess_dir, '/\\' );

        if( empty( $sess_dir )
        and defined( 'PHS_SESSION_DIR' ) )
            $sess_dir = constant( 'PHS_SESSION_DIR' );

        if( empty( $sess_dir ) )
            return false;

        $sess_dir = rtrim( $sess_dir, '/\\' );

        if( $maxlifetime === false
        and defined( 'PHS_SESSION_COOKIE_LIFETIME' ) )
            $maxlifetime = constant( 'PHS_SESSION_COOKIE_LIFETIME' );

        $maxlifetime = (int)$maxlifetime;

        // If max lifetime is 0 (meaning till browser is closed) we will put a default value of 30 days
        if( empty( $maxlifetime ) )
            $maxlifetime = 2592000; // delete all sessions older than 30 days if session max life time is 0...

        $dir_pattern = $sess_dir;
        for( $i = 0; $i < self::SESS_DIR_MAX_SEGMENTS; $i++ )
            $dir_pattern .= '/*';

        $return_arr = array();
        $return_arr['sess_dir'] = $sess_dir;
        $return_arr['dir_pattern'] = $dir_pattern;
        $return_arr['maxlifetime'] = $maxlifetime;
        $return_arr['total'] = 0;
        $return_arr['deleted'] = 0;

        if( ($file_list = @glob( $dir_pattern.'/'.self::get_session_file_name_for_id( '*' ).'*' )) )
        {
            $empty_dir_maybe = array();

            foreach( $file_list as $file )
            {
                $return_arr['total']++;

                if( @file_exists( $file )
                and @filemtime( $file ) + $maxlifetime < time() )
                {
                    @unlink( $file );

                    $check_dir = $file;
                    for( $i = 0; $i < self::SESS_DIR_MAX_SEGMENTS; $i++ )
                        $check_dir = @dirname( $check_dir );

                    $empty_dir_maybe[$check_dir] = true;

                    $return_arr['deleted']++;
                }
            }

            if( !empty( $empty_dir_maybe ) )
            {
                foreach( $empty_dir_maybe as $check_dir => $true )
                {
                    PHS_Utils::rmdir_tree( $check_dir, array( 'recursive' => true, 'only_if_no_files' => true ) );
                }
            }
        }

        return $return_arr;
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
