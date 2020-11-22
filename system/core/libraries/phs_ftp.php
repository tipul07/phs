<?php

namespace phs\system\core\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Library;

/*! \file phs_ftp.php
 *  \brief Contains PHS_Ftp class (connect to ftp servers)
 *  \author Andy
 *  \version 1.55
 */

class PHS_Ftp extends PHS_Library
{
    // Hooks...
    const H_BEFORE_CONNECT = 'phs_ftp_before_connect', H_AFTER_CONNECT = 'phs_ftp_after_connect',
          H_BEFORE_CLOSE = 'phs_ftp_before_close', H_AFTER_CLOSE = 'phs_ftp_after_close',
          H_BEFORE_GET = 'phs_ftp_before_get', H_AFTER_GET = 'phs_ftp_after_get',
          H_BEFORE_PUT = 'phs_ftp_before_put', H_AFTER_PUT = 'phs_ftp_after_put',
          H_BEFORE_DELETE = 'phs_ftp_before_delete', H_AFTER_DELETE = 'phs_ftp_after_delete',
          H_BEFORE_RENAME = 'phs_ftp_before_rename', H_AFTER_RENAME = 'phs_ftp_after_rename',
          H_BEFORE_MKDIR = 'phs_ftp_before_mkdir', H_AFTER_MKDIR = 'phs_ftp_after_mkdir',
          H_CHDIR = 'phs_ftp_chdir', H_LS = 'phs_ftp_ls', H_LOCALDIR = 'phs_ftp_local_dir';

    const
        //! Error related to connection to server
        ERR_CONNECTION = 2,
        //! Error related to authentication
        ERR_AUTHENTICATION = 3,
        //! Error related to remote location (file or dir)
        ERR_REMOTE_LOCATION = 4,
        //! Error related to 'local' location (file or dir)
        ERR_LOCAL_LOCATION = 5;

    const //! Used for ASCII file transfer
        TRANSFER_MODE_ASCII = 1,
        //! Used for binary file transfer
        TRANSFER_MODE_BINARY = 2;

    const //! Normal file
        TYPE_FILE = 1,
        //! Directory
        TYPE_DIRECTORY = 2,
        //! Symlink
        TYPE_SYM_LINK = 3;

    const //! Unknown listing type
        LIST_TYPE_UNKNOWN = 0,
        //! Cloud raw listing type
        LIST_TYPE_CLOUD = 1,
        //! Linux server listing type
        LIST_TYPE_LINUX = 2,
        //! Linux server listing type
        LIST_TYPE_CUSTOM = 3;

    const //! Connect 'normally'
        CON_TYPE_NORMAL = 1,
        //! SSL connection using ftp_ssl_connect
        CON_TYPE_NORMAL_SSL = 2,
        //! Normal connection using CURL
        CON_TYPE_CURL = 3,
        //! Secure connection using CURL
        CON_TYPE_CURL_SSL = 4,
        //! Secure connection using CURL
        CON_TYPE_SSH = 5;

    protected static $CONNECTION_TYPES_ARR = array(
        self::CON_TYPE_NORMAL => array( 'title' => 'Built-in ftp functions' ),
        self::CON_TYPE_NORMAL_SSL => array( 'title' => 'Built-in ftp functions (SSL)' ),
        self::CON_TYPE_CURL => array( 'title' => 'cURL library' ),
        self::CON_TYPE_CURL_SSL => array( 'title' => 'cURL library (SSL)' ),
        self::CON_TYPE_SSH => array( 'title' => 'SSH/SFTP connection' ),
    );

    //! ftp connection details
    private $ftp_settings, $settings_passed;

    //! CURL ftp connection details (if needed)
    private $ftp_curl_settings;

    //! Internal variables
    private $internal_settings;

    /**
     * PHS_Ftp constructor.
     *
     * @param bool|array $params
     */
    public function __construct( $params = false )
    {
        parent::__construct();

        $this->_reset_settings();

        if( !empty( $params ) )
        {
            if( !is_array( $params ) or empty( $params['server'] ) or !is_array( $params['server'] ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Bad parameters.' ) );
                return;
            }

            if( !$this->settings( $params['server'] ) )
                return;
        }

        $this->reset_error();
    }

    public function get_connection_types( $lang = false )
    {
        static $con_types_arr = array();

        if( $lang === false
        and !empty( $con_types_arr ) )
            return $con_types_arr;

        $result_arr = $this->translate_array_keys( self::$CONNECTION_TYPES_ARR, array( 'title' ), $lang );

        if( $lang === false )
            $con_types_arr = $result_arr;

        return $result_arr;
    }

    final public function get_connection_types_as_key_val( $lang = false )
    {
        static $ctypes_key_val_arr = false;

        if( $lang === false
        and $ctypes_key_val_arr !== false )
            return $ctypes_key_val_arr;

        $key_val_arr = array();
        if( ($types = $this->get_connection_types( $lang )) )
        {
            foreach( $types as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $ctypes_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function valid_connection_type( $con_type )
    {
        $con_type = (int)$con_type;
        if( empty( $con_type )
         or !($con_types_arr = $this->get_connection_types()) or !isset( $con_types_arr[$con_type] ) )
            return false;

        return $con_types_arr[$con_type];
    }

    public function transfer_mode( $mode = null )
    {
        $ftp_settings = $this->settings();

        if( empty( $ftp_settings ) or !is_array( $ftp_settings ) )
            return null;

        if( $mode === null )
        {
            // We should return transfer mode (if available)
            $return_val = (!isset( $ftp_settings['transfer_mode'] )?null:$ftp_settings['transfer_mode']);

            // Check if we have a valid curl connection and set options as required
            if( $return_val !== null and !empty( $this->internal_settings['con'] )
            and ($ftp_settings['connection_mode'] === self::CON_TYPE_CURL or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL) )
            {
                if( $mode === self::TRANSFER_MODE_ASCII )
                {
                    if( defined( 'CURLOPT_TRANSFERTEXT' ) )
                        @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_TRANSFERTEXT' ), true );
                    if( defined( 'CURLOPT_BINARYTRANSFER' ) )
                        @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_BINARYTRANSFER' ), false );
                } elseif( $mode === self::TRANSFER_MODE_BINARY )
                {
                    if( defined( 'CURLOPT_TRANSFERTEXT' ) )
                        @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_TRANSFERTEXT' ), false );
                    if( defined( 'CURLOPT_BINARYTRANSFER' ) )
                        @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_BINARYTRANSFER' ), true );
                }
            }

            return $return_val;
        }

        $new_mode = (int)$mode;
        $this->settings( 'transfer_mode', $new_mode );

        return true;
    }

    public function passive_mode( $mode = null )
    {
        $ftp_settings = $this->settings();

        if( empty( $ftp_settings ) or !is_array( $ftp_settings ) )
            return null;

        if( $mode === null )
            return (!isset( $ftp_settings['passive_mode'] )?null:$ftp_settings['passive_mode']);

        $new_mode = (!empty( $mode )?true:false);
        $this->settings( 'passive_mode', $new_mode );

        if( $this->is_connected() )
        {
            if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
                return @ftp_pasv( $this->internal_settings['con'], $new_mode );

            elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            {
                if( defined( 'CURLOPT_FTP_USE_EPSV' ) )
                    return @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_FTP_USE_EPSV' ), $new_mode );
            }
        }

        return true;
    }

    public function curl_settings( $settings = false, $value = null )
    {
        if( $settings === false )
            return $this->ftp_curl_settings;

        if( $settings !== false and $value !== null )
        {
            if( !isset( $this->ftp_curl_settings[$settings] ) )
                return false;

            $this->ftp_curl_settings[$settings] = $value;

            return true;
        }

        if( !($valid_settings = self::validate_curl_settings( $settings )) )
            return false;

        $this->ftp_curl_settings = $valid_settings;

        return $this->ftp_curl_settings;
    }

    public function settings( $settings = false, $value = null )
    {
        if( $settings === false )
            return $this->ftp_settings;

        if( $settings !== false and $value !== null )
        {
            if( !isset( $this->ftp_settings[$settings] ) )
                return false;

            $this->ftp_settings[$settings] = $value;

            return true;
        }

        $this->reset_error();

        if( !self::settings_valid( $settings ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Unkown FTP details.' );
            return false;
        }

        if( !($valid_settings = self::validate_settings( $settings )) )
            return false;

        if( isset( $valid_settings['local_dir'] )
        and $valid_settings['local_dir'] !== ''
        and !$this->local_dir( $valid_settings['local_dir'] ) )
            return false;

        if( $this->is_connected() )
            $this->close();

        $this->ftp_settings = $valid_settings;

        $this->settings_passed = true;

        return $this->ftp_settings;
    }


    public static function settings_valid( $settings )
    {
        if( empty( $settings ) or !is_array( $settings )
         or empty( $settings['host'] ) or empty( $settings['port'] ) or empty( $settings['user'] ) or !isset( $settings['pass'] ) )
            return false;

        return true;
    }

    public static function _default_internal_settings()
    {
        $default_internal_config = array();
        // Connection handler
        $default_internal_config['con'] = 0;
        // SSH2 Connection handler
        $default_internal_config['con_ssh2'] = 0;
        // Tell whether CURL 'environment' should be preserved for futher calls (same as ftp_* functions would be called between ftp_connect and ftp_close)
        $default_internal_config['curl_session_started'] = false;
        // If changing directories is not a command sent to server we will store current remote directory here
        $default_internal_config['remote_dir'] = '';

        return $default_internal_config;
    }

    public static function default_settings()
    {
        $default_config = array();
        $default_config['connection_mode'] = self::CON_TYPE_NORMAL;
        $default_config['host'] = 'localhost';
        $default_config['port'] = 21;
        $default_config['user'] = 'anonymous';
        $default_config['pass'] = 'email@email.com';
        $default_config['transfer_mode'] = self::TRANSFER_MODE_BINARY;
        $default_config['remote_dir'] = '';
        $default_config['local_dir'] = '';
        $default_config['timeout'] = 30;
        $default_config['passive_mode'] = false;
        // If you provide this using self::CON_TYPE_SSH connection type, script will check server fingerprint
        $default_config['ssh2_fingerprint'] = '';

        return $default_config;
    }

    public static function default_curl_settings()
    {
        $default_curl_config = array();

        // Default be ready to get files...
        $default_curl_config['CURLOPT_FTPLISTONLY'] = false; // TRUE to only list the names of an FTP directory

        $default_curl_config['CURLOPT_FTP_USE_EPRT'] = false; // Use EPSV = Extended Data port.
        $default_curl_config['CURLOPT_FTP_USE_EPSV'] = false; // Use EPSV = Extended Passive.
        $default_curl_config['CURLOPT_FTP_CREATE_MISSING_DIRS'] = false;
        $default_curl_config['CURLOPT_FTPAPPEND'] = false;

        // The FTP authentication method (when is activated): CURLFTPAUTH_SSL (try SSL first), CURLFTPAUTH_TLS (try TLS first), or CURLFTPAUTH_DEFAULT (let cURL decide).
        if( defined( 'CURLFTPAUTH_DEFAULT' ) )
            $default_curl_config['CURLOPT_FTPSSLAUTH'] = constant( 'CURLFTPAUTH_DEFAULT' );

        // 1 to check the existence of a common name in the SSL peer certificate. 2 to check the existence of a common name and also verify that it matches the hostname provided. In production environments the value of this option should be kept at 2 (default value).
        $default_curl_config['CURLOPT_SSL_VERIFYHOST'] = 0;
        $default_curl_config['CURLOPT_SSL_VERIFYPEER'] = false;

        // $default_curl_config['CURLOPT_SSLVERSION'] = 3; // 2 or 3 - comment line to let php decide
        // A bitmask consisting of one or more of CURLSSH_AUTH_PUBLICKEY, CURLSSH_AUTH_PASSWORD, CURLSSH_AUTH_HOST, CURLSSH_AUTH_KEYBOARD. Set to CURLSSH_AUTH_ANY to let libcurl pick one.
        if( defined( 'CURLSSH_AUTH_ANY' ) )
            $default_curl_config['CURLOPT_SSH_AUTH_TYPES'] = constant( 'CURLSSH_AUTH_ANY' );

        $default_curl_config['CURLOPT_FAILONERROR'] = true;
        $default_curl_config['CURLOPT_FOLLOWLOCATION'] = true; // follow redirects
        $default_curl_config['CURLOPT_HEADER'] = false; // don't return headers

        $default_curl_config['CURLOPT_RETURNTRANSFER'] = true; // return web page
        $default_curl_config['CURLOPT_ENCODING'] = ''; // handle all encodings
        $default_curl_config['CURLOPT_USERAGENT'] = 'PHS_ftp_CURL';
        $default_curl_config['CURLOPT_AUTOREFERER'] = true; // set referer on redirect
        $default_curl_config['CURLOPT_CONNECTTIMEOUT'] = 30; // timeout on connect
        $default_curl_config['CURLOPT_TIMEOUT'] = 30; // timeout on response
        $default_curl_config['CURLOPT_MAXREDIRS'] = 10; // stop after 10 redirects

        $default_curl_config['CURLOPT_POST'] = false; // by default we don't send info in post
        $default_curl_config['CURLOPT_POSTFIELDS'] = false;

        // TRUE to force the connection to explicitly close when it has finished processing, and not be pooled for reuse.
        $default_curl_config['CURLOPT_FORBID_REUSE'] = true;
        // TRUE to force the use of a new connection instead of a cached one.
        $default_curl_config['CURLOPT_FRESH_CONNECT'] = true;

        $default_curl_config['CURLOPT_VERBOSE'] = 0;

        return $default_curl_config;
    }

    public static function validate_curl_settings( $settings )
    {
        $def_settings = self::default_curl_settings();
        if( empty( $settings ) or !is_array( $settings ) )
            return $def_settings;

        foreach( $def_settings as $key => $val )
        {
            if( !isset( $settings[$key] ) )
                $settings[$key] = $val;
        }

        foreach( $settings as $key => $val )
        {
            if( !defined( $key ) )
                unset( $settings[$key] );
        }

        return $settings;
    }

    public static function validate_settings( $settings )
    {
        $def_settings = self::default_settings();
        if( empty( $settings ) or !is_array( $settings ) )
            return $def_settings;

        if( empty( $settings['connection_mode'] ) )
            $settings['connection_mode'] = $def_settings['connection_mode'];
        else
            $settings['connection_mode'] = (int)$settings['connection_mode'];

        if( empty( $settings['host'] ) )
            $settings['host'] = $def_settings['host'];
        else
            $settings['host'] = trim( $settings['host'] );

        if( empty( $settings['port'] ) )
        {
            if( $settings['connection_mode'] === self::CON_TYPE_SSH )
                $settings['port'] = 22;
            else
                $settings['port'] = $def_settings['port'];
        } else
            $settings['port'] = (int)$settings['port'];

        if( !isset( $settings['user'] ) )
            $settings['user'] = $def_settings['user'];

        if( !isset( $settings['pass'] ) )
            $settings['pass'] = $def_settings['pass'];

        if( !isset( $settings['transfer_mode'] ) )
            $settings['transfer_mode'] = $def_settings['transfer_mode'];
        else
            $settings['transfer_mode'] = (int)$settings['transfer_mode'];

        if( !isset( $settings['remote_dir'] ) )
            $settings['remote_dir'] = $def_settings['remote_dir'];

        if( !isset( $settings['local_dir'] ) )
            $settings['local_dir'] = $def_settings['local_dir'];
        else
            $settings['local_dir'] = trim( $settings['local_dir'] );

        if( empty( $settings['timeout'] ) )
            $settings['timeout'] = $def_settings['timeout'];
        else
            $settings['timeout'] = (int)$settings['timeout'];

        if( empty( $settings['passive_mode'] ) )
            $settings['passive_mode'] = $def_settings['passive_mode'];
        else
            $settings['passive_mode'] = (!empty( $settings['passive_mode'] )?true:false);

        if( empty( $settings['ssh2_fingerprint'] ) )
            $settings['ssh2_fingerprint'] = $def_settings['ssh2_fingerprint'];

        return $settings;
    }

    private function _reset_settings()
    {
        if( $this->is_connected() )
            $this->close();

        $this->ftp_settings = self::default_settings();
        $this->ftp_curl_settings = self::default_curl_settings();
        $this->internal_settings = self::_default_internal_settings();

        $this->settings_passed = false;
    }

    private function _reset_curl_opts()
    {
        $ftp_settings = $this->settings();

        if( ($ftp_settings['connection_mode'] === self::CON_TYPE_CURL
                or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL)
        and !empty( $this->internal_settings['con'] ) )
        {
            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_PUT' ), false );

            if( ($in_std = fopen( 'php://stdin', 'r' )) )
                @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_INFILE' ), $in_std );
            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_INFILESIZE' ), 0 );

            if( ($out_std = fopen( 'php://stdout', 'w' )) )
                @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_FILE' ), $out_std );

            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_QUOTE' ), array() );
        }
    }

    private function _close_curl_connection()
    {
        $ftp_settings = $this->settings();

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            if( !empty( $this->internal_settings['con'] ) )
            {
                $this->_reset_curl_opts();

                @curl_close( $this->internal_settings['con'] );
            }

            $this->internal_settings['con'] = false;
        }
    }

    public function is_connected()
    {
        // we use !empty to be sure method is not called b4 settings are initialized (somehow...)
        return (!empty( $this->internal_settings['con'] )?true:false);
    }

    public function can_connect()
    {
        // we use !empty to be sure method is not called b4 settings are initialized (somehow...)
        return (!empty( $this->settings_passed )?true:false);
    }

    /**
     * @param bool|array $params
     *
     * @return array
     */
    public function curl_url( $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        $ftp_settings = $this->settings();
        $curl_settings = $this->curl_settings();

        if( !isset( $params['dir'] ) or !is_string( $params['dir'] ) )
            $params['dir'] = '';
        if( !isset( $params['file'] ) or !is_string( $params['file'] ) )
            $params['file'] = '';

        if( $params['dir'] !== '' or substr( $params['dir'], 0, 1 ) !== '/' )
            $params['dir'] = '/'.$params['dir'];
        if( $params['dir'] !== '' and substr( $params['dir'], -1 ) !== '/' )
            $params['dir'] .= '/';

        $protocol = 'ftp';
        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $protocol = 'ftps';

        $user_pass = '';
        if( !empty( $ftp_settings['user'] ) )
            $user_pass .= $ftp_settings['user'];
        if( !empty( $ftp_settings['pass'] ) )
            $user_pass .= ':'.$ftp_settings['pass'];

        $full_url = $protocol.'://';
        if( !empty( $user_pass ) )
            $full_url .= $user_pass.'@';
        $full_url .= $ftp_settings['host'].':'.$ftp_settings['port'];

        $current_dir = $this->internal_settings['remote_dir'];

        if( substr( $params['file'], 0, 1 ) !== '/' )
        {
            $dir = $current_dir.$params['dir'];
        } else
        {
            $dir = dirname( $params['file'] );
            $params['file'] = basename( $params['file'] );
        }

        if( $dir === './' )
            $dir = '/';
        if( substr( $dir, 0, 1 ) !== '/' )
            $dir = '/'.$dir;
        if( substr( $dir, -1 ) !== '/' )
            $dir .= '/';

        $dir = str_replace( '//', '/', $dir );

        $dir_url = $full_url.$dir;
        $full_url .= $dir.$params['file'];

        $return_arr = array();
        $return_arr['protocol'] = $protocol;
        $return_arr['user_pass'] = $user_pass;
        $return_arr['current_dir'] = $current_dir;
        $return_arr['dir'] = $dir;
        $return_arr['file'] = $params['file'];
        $return_arr['dir_url'] = $dir_url;
        $return_arr['full_url'] = $full_url;

        return $return_arr;
    }

    // /**
    //  * Method which will be callback for disconnect signal from libssh2 library
    //  *
    //  * @param int $reason
    //  * @param string $message
    //  * @param $language
    //  */
    // public function signal_ssh_disconnect( $reason, $message, $language )
    // {
    //     $this->close();
    // }
    //
    // /**
    //  * Method which will be called when a packet is received but the message authentication code failed.
    //  * If the callback returns TRUE, the mismatch will be ignored, otherwise the connection will be terminated.
    //  * from libssh2 library
    //  *
    //  * @param string $packet
    //  * @return bool
    //  */
    // public function signal_ssh_macerror( $packet )
    // {
    //     return true;
    // }

    /**
     * @param bool|array $params
     *
     * @return bool
     */
    public function connect( $params = false )
    {
        if( $this->is_connected() )
            return true;

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_CONNECTION, 'Cannot connect to server. FTP settings not provided.' );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        $this->reset_error();

        $ftp_settings = $this->settings();

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_CONNECT, array( 'server' => $ftp_settings, 'success' => true ) );

        $curl_change_dir = true;
        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            // Connect
            if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL )
                $this->internal_settings['con'] = @ftp_connect( $ftp_settings['host'], $ftp_settings['port'], $ftp_settings['timeout'] );
            else
                $this->internal_settings['con'] = @ftp_ssl_connect( $ftp_settings['host'], $ftp_settings['port'], $ftp_settings['timeout'] );

            if( empty( $this->internal_settings['con'] ) )
            {
                $this->set_error( self::ERR_CONNECTION, 'FTP connection to server failed.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }

            // Login
            if( !@ftp_login( $this->internal_settings['con'], $ftp_settings['user'], $ftp_settings['pass'] ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, 'FTP login failed.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }
        } elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
               or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            $curl_change_dir = false;

            $curl_url = $this->curl_url( array( 'dir' => '' ) ); // (empty( $this->internal_settings['curl_session_started'] )?$ftp_settings['remote_dir']:'') ) );

            $this->internal_settings['con'] = @curl_init( $curl_url['full_url'] );

            if( empty( $this->internal_settings['con'] ) )
            {
                $this->set_error( self::ERR_CONNECTION, 'FTP/CURL failed to initialize.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }

            if( !($curl_ftp_settings = $this->curl_settings())
             or !is_array( $curl_ftp_settings ) )
            {
                $this->set_error( self::ERR_CONNECTION, 'FTP/CURL couldn\'t setup options.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }

            foreach( $curl_ftp_settings as $opt_name => $opt_value )
            {
                @curl_setopt( $this->internal_settings['con'], constant( $opt_name ), $opt_value );
            }

            if( empty( $this->internal_settings['curl_session_started'] ) )
            {
                $this->internal_settings['curl_session_started'] = true;
                $curl_change_dir = true;
            }
        } elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
        {
            if( !@function_exists( 'ssh2_connect' )
             or !@function_exists( 'ssh2_sftp' ) )
            {
                $this->set_error( self::ERR_CONNECTION, 'Functions ssh2_connect or ssh2_sftp not defined. Cannot connect to server.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }

            // $ssh2_callbacks = array(
            //     'disconnect' => array( $this, 'signal_ssh_disconnect' ),
            //     'macerror' => array( $this, 'signal_ssh_macerror' ),
            // );

            if( !($this->internal_settings['con_ssh2'] = @ssh2_connect( $ftp_settings['host'], $ftp_settings['port'] )) )//, null, $ssh2_callbacks )) )
            {
                $error_msg = '';
                if( ($conn_error = @error_get_last())
                and !empty( $conn_error['message'] ) )
                    $error_msg = $conn_error['message'];

                $this->set_error( self::ERR_CONNECTION, 'FTP connection to server failed.'.(!empty( $error_msg )?' ('.$error_msg.')':'') );

                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );

                return false;
            }

            if( !empty( $ftp_settings['ssh2_fingerprint'] )
            and @function_exists( 'ssh2_fingerprint' )
            and ($server_fingerprint = @ssh2_fingerprint( $this->internal_settings['con_ssh2'], SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX ))
            and strtoupper( $server_fingerprint ) !== strtoupper( $ftp_settings['ssh2_fingerprint'] ) )
            {
                unset( $this->internal_settings['con_ssh2'] );
                $this->internal_settings['con_ssh2'] = 0;

                $this->set_error( self::ERR_CONNECTION, 'Server fingerprint mismatch.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }

            // Login
            if( !@ssh2_auth_password( $this->internal_settings['con_ssh2'], $ftp_settings['user'], $ftp_settings['pass'] ) )
            {
                //unset( $this->internal_settings['con_ssh2'] );
                $this->internal_settings['con_ssh2'] = 0;

                $this->set_error( self::ERR_AUTHENTICATION, 'FTP login failed.' );

                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );

                return false;
            }

            if( !($this->internal_settings['con'] = @ssh2_sftp( $this->internal_settings['con_ssh2'] )) )
            {
                unset( $this->internal_settings['con_ssh2'] );
                $this->internal_settings['con_ssh2'] = 0;

                $this->set_error( self::ERR_AUTHENTICATION, 'Error creating FTP connection.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
                return false;
            }
        }

        // set passive mode (if required)
        if( isset( $ftp_settings['passive_mode'] )
        and !$this->passive_mode( $ftp_settings['passive_mode'] ) )
        {
            $this->set_error( self::ERR_CONNECTION, 'FTP setting passive mode to '.(!empty( $ftp_settings['passive_mode'] )?'true':'false').' failed.' );
            if( empty( $params['skip_callbacks'] ) )
                $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
            return false;
        }

        // chdir to whatever remote dir says (if required)
        if( !empty( $curl_change_dir )
        and !empty( $ftp_settings['remote_dir'] )
        and !$this->chdir( $ftp_settings['remote_dir'], array( 'skip_callbacks' => $params['skip_callbacks'] ) ) )
        {
            $this->set_error( self::ERR_CONNECTION, 'FTP changing remote directory failed.' );
            if( empty( $params['skip_callbacks'] ) )
                $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => false ) );
            return false;
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_CONNECT, array( 'server' => $ftp_settings, 'success' => true ) );

        return true;
    }

    /**
     * @param string $dir
     * @param bool|array $params
     *
     * @return bool
     */
    public function chdir( $dir, $params = false )
    {
        if( !is_string( $dir ) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        $this->reset_error();

        $ftp_settings = $this->settings();

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            if( !$this->is_connected()
            and !$this->connect() )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
                return false;
            }
        }

        if( substr( $dir, -1 ) === '/' )
            $dir = substr( $dir, 0, -1 );

        if( substr( $dir, 0, 1 ) === '/' )
            $new_remote_dir = $dir;
        else
            $new_remote_dir = $this->internal_settings['remote_dir'].$dir;

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            if( !@ftp_chdir( $this->internal_settings['con'], $new_remote_dir ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP cannot change remote directory.' );
                return false;
            }
        }

        $this->internal_settings['remote_dir'] = $new_remote_dir;

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_CHDIR, array( 'dir' => $dir ) );

        return true;
    }

    /**
     * @param bool|string $dir
     * @param bool|array $params
     *
     * @return bool|mixed
     */
    public function local_dir( $dir = false, $params = false )
    {
        if( $dir === false )
        {
            if( $this->can_connect()
            and is_array( $this->ftp_settings )
            and isset( $this->ftp_settings['local_dir'] ) )
                return $this->ftp_settings['local_dir'];

            return false;
        }

        $this->reset_error();

        if( !is_string( $dir ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Local directory parameter not a string.' );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;
        if( !isset( $params['create_if_doesnt_exist'] ) )
            $params['create_if_doesnt_exist'] = true;

        // Check directory structure...
        if( $dir !== ''
        and !empty( $params['create_if_doesnt_exist'] )
        and !@file_exists( $dir ) )
        {
            $dir = str_replace( '\\', '/', $dir );
            $dir_segments = explode( '/', $dir );
            $path = '';
            foreach( $dir_segments as $dir_seg )
            {
                // In case we have an absolute path...
                if( $path === ''
                and $dir_seg === '' )
                {
                    $path = '/';
                    continue;
                }

                $path .= ($path!==''?'/':'').$dir_seg;
                if( !@file_exists( $path ) )
                {
                    if( !@mkdir( $path ) )
                    {
                        $this->set_error( self::ERR_LOCAL_LOCATION, 'FTP cannot create local directory.' );
                        return false;
                    }
                }
            }
        }

        if( $dir !== ''
        and (!($dir = @realpath( $dir )) or !@is_dir( $dir )) )
        {
            $this->set_error( self::ERR_LOCAL_LOCATION, 'FTP invalid local directory.' );
            return false;
        }

        $this->settings( 'local_dir', $dir );

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_LOCALDIR, array( 'dir' => $dir ) );

        return true;
    }

    /**
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function ls( $params = false )
    {
        $this->reset_error();

        $ftp_settings = $this->settings();

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $this->_close_curl_connection();

        if( !$this->is_connected()
        and !$this->connect() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( empty( $params['dir'] ) )
            $params['dir'] = '.';
        if( empty( $params['ls_type'] ) )
            $params['ls_type'] = 'rawlist';
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        $files_arr = false;
        switch( $params['ls_type'] )
        {
            case 'rawlist':
                if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
                 or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
                    $files_arr = @ftp_rawlist( $this->internal_settings['con'], $params['dir'] );

                elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
                     or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
                {
                    $curl_settings = $this->curl_settings();

                    @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_FTPLISTONLY' ), false );

                    $dir = '';
                    if( !empty( $params['dir'] )
                    and $params['dir'] !== '.' )
                        $dir = $params['dir'];

                    $curl_url = $this->curl_url( array( 'dir' => $dir ) );

                    @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $curl_url['full_url'] );

                    $content = @curl_exec( $this->internal_settings['con'] );
                    $err     = @curl_errno( $this->internal_settings['con'] );
                    $errmsg  = @curl_error( $this->internal_settings['con'] ) ;
                    //$header  = curl_getinfo( $this->internal_settings['con'] );

                    $this->_close_curl_connection();

                    if( $content === false
                     or $err )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL cannot get directory list. ['.$err.']' );
                        return false;
                    }

                    if( $content === '' )
                        $files_arr = array();

                    else
                    {
                        $content = str_replace( array( "\r\n", "\r", "\n\r" ), "\n", $content );
                        $files_arr = explode( "\n", $content );
                    }
                } elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
                {
                    if( ($ls_dir = trim( $params['dir'], '/\\' )) === '.' )
                        $ls_dir = '';

                    $remote_dir = trim( $this->internal_settings['remote_dir'], '/\\' ).
                                  ($ls_dir!==''?'/'.$ls_dir:'');

                    if( $remote_dir === '' )
                        $remote_dir = '.';

                    $con = $this->internal_settings['con'];
                    if( !($handle = @opendir('ssh2.sftp://'.(int)$con.'/'.$remote_dir )) )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot get directory list.' );
                        return false;
                    }

                    $files_arr = array();
                    while( ($entry = @readdir( $handle )) !== false )
                    {
                        if( $entry === '.' or $entry === '..' )
                            continue;

                        $file_arr = self::default_entry_array();
                        $file_arr['name'] = $entry;
                        $file_arr['format'] = self::LIST_TYPE_CUSTOM;

                        if( @is_file( 'ssh2.sftp://'.(int)$con.'/'.$remote_dir.'/'.$entry ) )
                            $file_arr['type'] = self::TYPE_FILE;
                        elseif( @is_dir( 'ssh2.sftp://'.(int)$con.'/'.$remote_dir.'/'.$entry ) )
                            $file_arr['type'] = self::TYPE_DIRECTORY;
                        elseif( @is_link( 'ssh2.sftp://'.(int)$con.'/'.$remote_dir.'/'.$entry ) )
                            $file_arr['type'] = self::TYPE_SYM_LINK;

                        if( $file_arr['type'] === self::TYPE_SYM_LINK
                        and ($target = @ssh2_sftp_readlink( $this->internal_settings['con'], $remote_dir.'/'.$entry )) )
                            $file_arr['link'] = $target;

                        if( ($stats_arr = @ssh2_sftp_stat( $this->internal_settings['con'], $remote_dir.'/'.$entry )) )
                        {
                            $file_arr['size'] = (isset( $stats_arr['size'] )?$stats_arr['size']:(isset( $stats_arr[7] )?$stats_arr[7]:0));

                            $date_time = 0;
                            if( isset( $stats_arr['ctime'] ) )
                                $date_time = $stats_arr['ctime'];
                            elseif( isset( $stats_arr[10] ) )
                                $date_time = $stats_arr[10];
                            elseif( isset( $stats_arr['mtime'] ) )
                                $date_time = $stats_arr['mtime'];
                            elseif( isset( $stats_arr[9] ) )
                                $date_time = $stats_arr[9];

                            $file_arr['datetime'] = $date_time;
                            $file_arr['user'] = (isset( $stats_arr['uid'] )?$stats_arr['uid']:(isset( $stats_arr[4] )?$stats_arr[4]:0));
                            $file_arr['group'] = (isset( $stats_arr['gid'] )?$stats_arr['gid']:(isset( $stats_arr[5] )?$stats_arr[5]:0));
                        }

                        $files_arr[] = $file_arr;
                    }

                    @closedir( $handle );
                }
            break;

            case 'nlist':
                if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
                 or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
                    $files_arr = @ftp_nlist( $this->internal_settings['con'], $params['dir'] );

                elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
                     or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
                {
                    $curl_settings = $this->curl_settings();

                    @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_FTPLISTONLY' ), true );

                    $dir = '';
                    if( !empty( $params['dir'] )
                    and $params['dir'] !== '.' )
                        $dir = $params['dir'];

                    $new_url = $this->curl_url( array( 'dir' => $dir ) );
                    @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $new_url['full_url'] );

                    $content = @curl_exec( $this->internal_settings['con'] );
                    $err     = @curl_errno( $this->internal_settings['con'] );
                    $errmsg  = @curl_error( $this->internal_settings['con'] ) ;
                    //$header  = @curl_getinfo( $this->internal_settings['con'] );

                    $this->_close_curl_connection();

                    if( $content === false
                     or $err )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL cannot get directory list. ['.$err.']' );
                        return false;
                    }

                    if( $content === '' )
                        $files_arr = array();

                    else
                    {
                        $content = str_replace( array( "\r\n", "\r", "\n\r" ), "\n", $content );
                        $files_arr = explode( "\n", $content );
                    }
                } elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
                {
                    $con = $this->internal_settings['con'];
                    if( !($handle = @opendir('ssh2.sftp://'.(int)$con.'/'.$params['dir'] )) )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot get directory list.' );
                        return false;
                    }

                    while( ($entry = @readdir( $handle )) !== false )
                    {
                        if( $entry === '.' or $entry === '..' )
                            continue;

                        $files_arr[] = $entry;
                    }

                    @closedir( $handle );
                }
            break;
        }

        if( $files_arr === false
         or !is_array( $files_arr ) )
        {
            $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP cannot get directory list.' );
            return false;
        }

        $file_details_arr = array();
        switch( $params['ls_type'] )
        {
            case 'rawlist':
                if( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
                    $file_details_arr = $files_arr;

                else
                {
                    foreach( $files_arr as $line_no => $file_line )
                    {
                        if( ($line_arr = self::parse_raw_line( $file_line )) === false )
                            continue;

                        $file_details_arr[] = $line_arr;
                    }
                }
            break;

            case 'nlist':
                $file_details_arr = $files_arr;
            break;
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_LS, array( 'files' => $file_details_arr ) );

        return $file_details_arr;
    }

    public static function parse_raw_line( $str )
    {
        $detection_arr = preg_split( '/[\s]+/', $str, 2 );
        $detection_str = (empty( $detection_arr[0] )?'':$detection_arr[0]);

        if( empty( $detection_str ) )
            return false;

        $line_arr = self::default_entry_array();
        $line_arr['raw'] = $str;

        // Check if linux...
        if( @preg_match( '@[dlrwx-]{10}@i', $detection_str ) )
        {
            $line_arr = self::parse_raw_linux_line( $str, $line_arr );
        }
        // Check cloud
        elseif( @preg_match( '@[0-9]{2}-[0-9]{2}-[0-9]{2}@i', $detection_str ) )
        {
            $line_arr = self::parse_raw_cloud_line( $str, $line_arr );
        }

        return $line_arr;
    }

    /**
     * @param string $str
     * @param bool|array $line_arr
     *
     * @return array|bool
     */
    public static function parse_raw_linux_line( $str, $line_arr = false )
    {
        if( $line_arr === false
         or !is_array( $line_arr ) )
        {
            $line_arr = self::default_entry_array();
            $line_arr['raw'] = $str;
        }

        $line_details_arr = preg_split( '/[\s]+/', $str, 9 );

        if( empty( $line_details_arr[0] ) )
            return $line_arr;

        $rights_arr = self::lin_parse_rights_string( $line_details_arr[0] );

        $datetime_str = (!empty( $line_details_arr[5] )?$line_details_arr[5].' ':'').
                        (!empty( $line_details_arr[6] )?$line_details_arr[6].' ':'').
                        (!empty( $line_details_arr[7] )?$line_details_arr[7]:'');
        $datetime_str = trim( $datetime_str );
        $datetime = self::lin_parse_datetime_string( $datetime_str );

        $line_arr['format'] = self::LIST_TYPE_LINUX;
        $line_arr['type'] = $rights_arr['type'];
        $line_arr['rights'] = $rights_arr['rights'];
        $line_arr['user'] = (isset( $line_details_arr[2] )?$line_details_arr[2]:'');
        $line_arr['group'] = (isset( $line_details_arr[3] )?$line_details_arr[3]:'');
        $line_arr['size'] = (!empty( $line_details_arr[4] )?(int)$line_details_arr[4]:0);
        $line_arr['datetime_str'] = $datetime_str;
        $line_arr['datetime'] = $datetime;
        $line_arr['name'] = (!empty( $line_details_arr[8] )?$line_details_arr[8]:'(??)');
        $line_arr['link'] = $line_arr['name'];

        if( $line_arr['type'] === self::TYPE_SYM_LINK )
        {
            $link_arr = explode( '->', $line_arr['name'] );
            $line_arr['name'] = trim( $link_arr[0] );

            if( isset( $link_arr[1] ) )
                $line_arr['link'] = trim( $link_arr[1] );
        }

        return $line_arr;
    }

    /**
     * @param string $str
     * @param bool|array $line_arr
     *
     * @return array|bool
     */
    public static function parse_raw_cloud_line( $str, $line_arr = false )
    {
        if( $line_arr === false
         or !is_array( $line_arr ) )
        {
            $line_arr = self::default_entry_array();
            $line_arr['raw'] = $str;
        }

        $line_details_arr = preg_split( '/[\s]+/', $str, 4 );

        if( empty( $line_details_arr[0] ) )
            return $line_arr;

        $datetime_str = (!empty( $line_details_arr[0] )?$line_details_arr[0].' ':'').
                        (!empty( $line_details_arr[1] )?$line_details_arr[1].' ':'');
        $datetime_str = trim( $datetime_str );
        $datetime = self::cloud_parse_datetime_string( $datetime_str );

        $line_arr['format'] = self::LIST_TYPE_CLOUD;
        $line_arr['type'] = ((!empty( $line_details_arr[2] ) and strtolower( $line_details_arr[2] )==='<dir>')?self::TYPE_DIRECTORY:self::TYPE_FILE);
        $line_arr['size'] = (!empty( $line_details_arr[2] )?(int)$line_details_arr[2]:0);
        $line_arr['datetime_str'] = $datetime_str;
        $line_arr['datetime'] = $datetime;
        $line_arr['name'] = (!empty( $line_details_arr[3] )?$line_details_arr[3]:'(??)');
        $line_arr['link'] = $line_arr['name'];

        return $line_arr;
    }

    public static function default_entry_array()
    {
        $default_rights_arr = self::default_rights_array();

        $line_arr = array();
        $line_arr['format'] = self::LIST_TYPE_UNKNOWN;
        $line_arr['type'] = $default_rights_arr['type'];
        $line_arr['rights'] = $default_rights_arr['rights'];
        $line_arr['user'] = '';
        $line_arr['group'] = '';
        $line_arr['size'] = 0;
        $line_arr['datetime_str'] = '';
        $line_arr['datetime'] = 0;
        $line_arr['name'] = '';
        $line_arr['link'] = '';
        $line_arr['raw'] = '';

        return $line_arr;
    }

    public static function default_rights_array()
    {
        $return_arr = array();
        $return_arr['type'] = self::TYPE_FILE;
        $return_arr['rights']['octal'] = 0;
        $return_arr['rights']['numeric'] = 0;
        $return_arr['rights']['string'] = array( 'owner' => '', 'group' => '', 'others' => '' );

        return $return_arr;
    }

    public static function cloud_parse_datetime_string( $str )
    {
        if( empty( $str ) )
            return 0;

        $date_time_arr = explode( ' ', $str, 2 );

        if( empty( $date_time_arr[1] ) )
            $date_time_arr[1] = '';

        $date_arr = explode( '-', $date_time_arr[0], 3 );
        $time_arr = explode( ':', $date_time_arr[1], 2 );

        if( empty( $date_arr[1] ) )
            $date_arr[1] = '';
        if( empty( $date_arr[2] ) )
            $date_arr[2] = '';
        if( empty( $time_arr[1] ) )
            $time_arr[1] = '';

        $month = (int)$date_arr[0];
        $day = (int)$date_arr[1];
        $year = (int)$date_arr[2];

        if( $year > 80 )
            $year += 1900;
        else
            $year += 2000;

        $hour = (int)$time_arr[0];
        if( !empty( $time_arr[1] ) )
        {
            if( ($ampm = substr( $time_arr[1], -2 )) )
                $ampm = strtolower( $ampm );
            else
                $ampm = 'am';
            if( ($minutes = substr( $time_arr[1], 0, 2 )) )
                $minutes = (int)$minutes;
            else
                $minutes = 0;
        } else
        {
            $ampm = 'am';
            $minutes = 0;
        }

        if( $ampm === 'pm' and $hour < 12 )
            $hour += 12;

        return @mktime( $hour, $minutes, 0, $month, $day, $year );
    }

    public static function lin_parse_datetime_string( $str )
    {
        $str = str_replace( '/', '-', $str );
        $datetime = strtotime( $str );

        if( $datetime > time() )
            $datetime = strtotime( '-1 year', $datetime );

        return $datetime;
    }

    public static function lin_parse_rights_string( $str )
    {
        $return_arr = self::default_rights_array();

        if( empty( $str ) )
            return $return_arr;

        $type = substr( $str, 0, 1 );
        switch( $type )
        {
            case 'd':
                $return_arr['type'] = self::TYPE_DIRECTORY;
            break;
            case 'l':
                $return_arr['type'] = self::TYPE_SYM_LINK;
            break;
            default:
                $return_arr['type'] = self::TYPE_FILE;
            break;
        }

        $rights_str = substr( $str, 1 );
        $rights_arr = str_split( $rights_str );

        $octal_str = '0';
        $nr = 0;
        $str = '';
        for( $key = 0; $key < 9; $key++ )
        {
            $ch = (isset( $rights_arr[$key] )?$rights_arr[$key]:'');

            if( $ch === 'r' )
                $nr += 4;
            elseif( $ch === 'w' )
                $nr += 2;
            elseif( $ch === 'x' )
                $nr += 1;

            $str .= $ch;

            if( $key === 2 or $key === 5 or $key === 8 )
            {
                if( $key === 2 )
                    $return_arr['rights']['string']['owner'] = $str;
                elseif( $key === 5 )
                    $return_arr['rights']['string']['group'] = $str;
                elseif( $key === 8 )
                    $return_arr['rights']['string']['others'] = $str;

                $octal_str .= $nr;
                $nr = 0;
                $str = '';
            }
        }

        $return_arr['rights']['octal'] = $octal_str;
        $return_arr['rights']['numeric'] = octdec( $octal_str );

        return $return_arr;
    }

    /**
     * @param string $file
     * @param bool|array $params
     *
     * @return bool
     */
    public function get( $file, $params = false )
    {
        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, 'FTP object not setup.' );
            return false;
        }

        if( !is_string( $file )
         or $file === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Please provide a remote file to get.' );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;
        if( !isset( $params['local_file'] ) or $params['local_file'] === '' )
            $params['local_file'] = @basename( $file );
        if( empty( $params['resume_pos'] ) )
            $params['resume_pos'] = 0;
        if( empty( $params['file_rights'] ) )
            $params['file_rights'] = 0664; // try to set file flags after transfer

        $ftp_settings = $this->settings();

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $this->_close_curl_connection();

        if( !$this->is_connected()
        and !$this->connect() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
            return false;
        }

        $local_file = $ftp_settings['local_dir'];
        if( $local_file !== ''
        and substr( $local_file, -1 ) !== '/' )
            $local_file .= '/';

        $params['local_file'] = $local_file . $params['local_file'];

        if( ($mode = $this->transfer_mode()) === false )
            $mode = self::TRANSFER_MODE_BINARY;

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => true ) );

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            // 4.3.0    resumepos was added.
            if( !@ftp_get( $this->internal_settings['con'], $params['local_file'], $file, ($mode===self::TRANSFER_MODE_BINARY?FTP_BINARY:FTP_ASCII), $params['resume_pos'] ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP couldn\'t GET file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
             or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            if( !($fp = @fopen( $params['local_file'], 'w' )) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t create local file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            $curl_url = $this->curl_url( array( 'file' => $file ) );

            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $curl_url['full_url'] );

            if( !@curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_FILE' ), $fp )
             or @curl_exec( $this->internal_settings['con'] ) === false )
            {
                @fclose( $fp );
                $err = @curl_errno( $this->internal_settings['con'] );
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t GET file. ['.$err.']' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            @fflush( $fp );
            @fclose( $fp );

            $this->_close_curl_connection();
        }

        elseif( $ftp_settings['connection_mode'] == self::CON_TYPE_SSH )
        {
            if( substr( $file, 0, 1 ) !== '/'
            and ($win_drive = substr( $file, 1, 2 )) !== ':/' and $win_drive !== ':\\' )
            {
                $file = trim( $file, '/\\' );
                $remote_file = rtrim( $this->internal_settings['remote_dir'], '/\\' ).
                               ($file!==''?'/'.$file:'');
            } else
                $remote_file = $file;

            $con = $this->internal_settings['con'];
            if( !($in_fp = @fopen( 'ssh2.sftp://'.(int)$con.'/'.$remote_file, 'rb' )) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot open remote file.' );
                return false;
            }

            if( !($out_fp = @fopen( $params['local_file'], 'wb' )) )
            {
                @fclose( $in_fp );

                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP couldn\'t create local file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            // read chunks of 8Kb
            $we_have_error = false;
            while( ($buf = @fread( $in_fp, 8192 )) )
            {
                if( @fwrite( $out_fp, $buf ) === false )
                {
                    $we_have_error = true;
                    break;
                }
            }

            @fclose( $in_fp );
            @fflush( $out_fp );
            @fclose( $out_fp );

            if( $we_have_error )
            {
                @unlink( $params['local_file'] );

                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP error copying file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }
        }

        if( !empty( $params['file_rights'] ) )
            @chmod( $params['local_file'], $params['file_rights'] );

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => true ) );

        return true;
    }

    /**
     * @param string $file
     * @param bool|array $params
     *
     * @return bool
     */
    public function put( $file, $params = false )
    {
        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, 'FTP object not setup.' );
            return false;
        }

        if( !is_string( $file )
         or $file === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Unknown file.' );
            return false;
        }

        $ftp_settings = $this->settings();

        if( !@file_exists( $file ) )
        {
            $local_dir = $ftp_settings['local_dir'];
            if( $local_dir !== '' and substr( $local_dir, -1 ) !== '/' )
                $local_dir .= '/';

            $file = $local_dir.$file;
        }

        if( !@file_exists( $file )
         or !@is_file( $file ) )
        {
            $this->set_error( self::ERR_LOCAL_LOCATION, 'Unknown local file to PUT.' );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;
        if( !isset( $params['remote_file'] ) or $params['remote_file'] === '' )
            $params['remote_file'] = @basename( $file );
        if( empty( $params['start_pos'] ) )
            $params['start_pos'] = 0;
        if( empty( $params['file_rights'] ) )
            $params['file_rights'] = 0664; // try to set file flags after transfer

        $params['remote_file'] = rtrim( $params['remote_file'], '/\\' );

        if( $params['remote_file'] === '' )
        {
            $this->set_error( self::ERR_REMOTE_LOCATION, 'Please provide a remote file name.' );
            return false;
        }

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $this->_close_curl_connection();

        if( !$this->is_connected()
        and !$this->connect() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
            return false;
        }

        if( ($mode = $this->transfer_mode()) === false )
            $mode = self::TRANSFER_MODE_BINARY;

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_PUT, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => true ) );

        // CURLOPT_FTPAPPEND // TRUE to append to the remote file instead of overwriting it.

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            // 4.3.0    startpos was added.
            if( !@ftp_put( $this->internal_settings['con'], $params['remote_file'], $file, ($mode===self::TRANSFER_MODE_BINARY?FTP_BINARY:FTP_ASCII), $params['start_pos'] ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP couldn\'t PUT file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_PUT, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            if( !empty( $params['file_rights'] ) )
                @ftp_chmod( $this->internal_settings['con'], $params['file_rights'], $params['remote_file'] );
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
             or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            if( !($file_size = @filesize( $file )) )
                $file_size = 0;

            if( !($fp = @fopen( $file, 'r' )) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t read local file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_PUT, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            $curl_url = $this->curl_url( array( 'file' => $params['remote_file'] ) );

            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $curl_url['full_url'] );
            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_PUT' ), true );
            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_INFILESIZE' ), $file_size );

            if( !@curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_INFILE' ), $fp )
             or @curl_exec( $this->internal_settings['con'] ) === false )
            {
                @fclose( $fp );
                $err = curl_errno( $this->internal_settings['con'] );
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t PUT file. ['.$err.']' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_PUT, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            @fclose( $fp );

            $this->_close_curl_connection();
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
        {
            if( substr( $params['remote_file'], 0, 1 ) !== '/'
            and ($win_drive = substr( $params['remote_file'], 1, 2 )) !== ':/' and $win_drive !== ':\\' )
            {
                $remote_file = trim( $params['remote_file'], '/\\' );
                $remote_file = rtrim( $this->internal_settings['remote_dir'], '/\\' ).
                               ($remote_file!==''?'/'.$remote_file:'');
            } else
                $remote_file = $params['remote_file'];

            if( !($in_fp = @fopen( $file, 'rb' )) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP couldn\'t open local file for read.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            $con = $this->internal_settings['con'];
            if( !($out_fp = @fopen( 'ssh2.sftp://'.(int)$con.'/'.$remote_file, 'wb' )) )
            {
                @fclose( $in_fp );

                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot create remote file for write.' );
                return false;
            }

            // read chunks of 8Kb
            $we_have_error = false;
            while( ($buf = @fread( $in_fp, 8192 )) )
            {
                if( @fwrite( $out_fp, $buf ) === false )
                {
                    $we_have_error = true;
                    break;
                }
            }

            @fclose( $in_fp );
            @fflush( $out_fp );
            @fclose( $out_fp );

            if( $we_have_error )
            {
                @ssh2_sftp_unlink( $this->internal_settings['con'], $remote_file );

                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP error copying file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_GET, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => false ) );
                return false;
            }

            if( !empty( $params['file_rights'] )
            and @function_exists( 'ssh2_sftp_chmod' ) )
                @ssh2_sftp_chmod( $this->internal_settings['con'], $remote_file, $params['file_rights'] );
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_PUT, array( 'server' => $ftp_settings, 'file' => $file, 'mode' => $mode, 'params' => $params, 'success' => true ) );

        return true;
    }

    /**
     * @param string $file
     * @param bool|array $params
     *
     * @return bool
     */
    public function delete( $file, $params = false )
    {
        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, 'FTP object not setup.' );
            return false;
        }

        if( !is_string( $file )
         or $file === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Please provide remote file to delete.' );
            return false;
        }

        $ftp_settings = $this->settings();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $this->_close_curl_connection();

        if( !$this->is_connected()
        and !$this->connect() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
            return false;
        }

        $remote_file = $file;
        $curl_url = array();
        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            $curl_url = $this->curl_url( array( 'file' => $file ) );
            $remote_file = $curl_url['dir'].$curl_url['file'];
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_DELETE, array( 'server' => $ftp_settings, 'file' => $file, 'remote_file' => $remote_file, 'params' => $params, 'success' => true ) );

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            // 4.3.0    resumepos was added.
            if( !@ftp_delete( $this->internal_settings['con'], $file ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP couldn\'t DELETE file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'file' => $file, 'remote_file' => $remote_file, 'params' => $params, 'success' => false ) );
                return false;
            }
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
             or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            if( empty( $curl_url ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL Unknown file to DELETE.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'file' => $file, 'remote_file' => $remote_file, 'params' => $params, 'success' => false ) );
                return false;
            }

            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $curl_url['dir_url'] );

            if( $remote_file !== '' )
                @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_POSTQUOTE' ), array( 'DELE '.$remote_file ) );

            if( @curl_exec( $this->internal_settings['con'] ) === false )
            {
                $err = curl_errno( $this->internal_settings['con'] );
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t DELETE file. ['.$err.']' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'file' => $file, 'remote_file' => $remote_file, 'params' => $params, 'success' => false ) );
                return false;
            }

            $this->_close_curl_connection();
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
        {
            if( substr( $file, 0, 1 ) !== '/'
            and ($win_drive = substr( $file, 1, 2 )) !== ':/' and $win_drive !== ':\\' )
            {
                $file = trim( $file, '/\\' );
                $remote_file = rtrim( $this->internal_settings['remote_dir'], '/\\' ).
                    ($file!==''?'/'.$file:'');
            } else
                $remote_file = $file;

            if( !@ssh2_sftp_unlink( $this->internal_settings['con'], $remote_file ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot delete remote file.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'file' => $file, 'remote_file' => $remote_file, 'params' => $params, 'success' => false ) );
                return false;
            }
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'file' => $file, 'remote_file' => $remote_file, 'params' => $params, 'success' => true ) );

        return true;
    }

    /**
     * @param string $dir
     * @param bool|array $params
     *
     * @return bool
     */
    public function delete_dir( $dir, $params = false )
    {
        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, 'FTP object not setup.' );
            return false;
        }

        if( !is_string( $dir )
         or $dir === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Please provide remote file to delete.' );
            return false;
        }

        $ftp_settings = $this->settings();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $this->_close_curl_connection();

        if( !$this->is_connected()
        and !$this->connect() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
            return false;
        }

        $remote_dir = $dir;
        $curl_url = array();
        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            $curl_url = $this->curl_url( array( 'dir' => $dir ) );
            $remote_dir = $curl_url['dir'];
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_DELETE, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => true ) );

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            // 4.3.0    resumepos was added.
            if( !@ftp_rmdir( $this->internal_settings['con'], $remote_dir ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP couldn\'t DELETE directory.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                return false;
            }
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
             or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            if( empty( $curl_url ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL Unknown directory to DELETE.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                return false;
            }

            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $curl_url['dir_url'] );

            if( $remote_dir !== '' )
                @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_POSTQUOTE' ), array( 'rmdir '.$remote_dir ) );

            if( @curl_exec( $this->internal_settings['con'] ) === false )
            {
                $err = curl_errno( $this->internal_settings['con'] );
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t DELETE directory. ['.$err.']' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                return false;
            }

            $this->_close_curl_connection();
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
        {
            if( substr( $dir, 0, 1 ) !== '/'
                and ($win_drive = substr( $dir, 1, 2 )) !== ':/' and $win_drive !== ':\\' )
            {
                $dir = trim( $dir, '/\\' );
                $remote_dir = rtrim( $this->internal_settings['remote_dir'], '/\\' ).
                              ($dir!==''?'/'.$dir:'');
            } else
                $remote_dir = $dir;

            if( !@ssh2_sftp_rmdir( $this->internal_settings['con'], $remote_dir ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot delete remote directory.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                return false;
            }
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_DELETE, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => true ) );

        return true;
    }

    /**
     * @param string $dir
     * @param bool|array $params
     *
     * @return bool
     */
    public function mkdir( $dir, $params = false )
    {
        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, 'FTP object not setup.' );
            return false;
        }

        if( !is_string( $dir )
         or $dir === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Please provide remote directory to create.' );
            return false;
        }

        $ftp_settings = $this->settings();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;
        if( empty( $params['dir_rights'] ) )
            $params['dir_rights'] = 0755; // try to set dir flags after creation (if supported)
        if( !isset( $params['recursive'] ) )
            $params['recursive'] = true;
        else
            $params['recursive'] = (!empty( $params['recursive'] )?true:false);

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
            $this->_close_curl_connection();

        if( !$this->is_connected()
        and !$this->connect() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.' );
            return false;
        }

        $remote_dir = $dir;
        $curl_url = array();
        if( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            $curl_url = $this->curl_url( array( 'dir' => $dir ) );
            $remote_dir = $curl_url['dir'];
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_MKDIR, [ 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => true ] );

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            if( !empty( $params['recursive'] ) )
                $path_parts = explode( '/', $remote_dir );
            else
                $path_parts = array( $remote_dir );

            $old_dir = @ftp_pwd( $this->internal_settings['con'] );

            foreach( $path_parts as $part )
            {
                if( !@ftp_chdir( $this->internal_settings['con'], $part ) )
                {
                    if( !@ftp_mkdir( $this->internal_settings['con'], $part ) )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP couldn\'t create directory.' );
                        if( empty( $params['skip_callbacks'] ) )
                            $this->trigger_phs_hooks( self::H_AFTER_MKDIR, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                        return false;
                    }

                    if( !empty( $params['dir_rights'] ) )
                        @ftp_chmod( $this->internal_settings['con'], $params['dir_rights'], $part );

                    if( @ftp_chdir( $this->internal_settings['con'], $part ) === false )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP couldn\'t create directory.' );
                        if( empty( $params['skip_callbacks'] ) )
                            $this->trigger_phs_hooks( self::H_AFTER_MKDIR, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                        return false;
                    }
                }
            }

            if( $old_dir !== false )
                @ftp_chdir( $this->internal_settings['con'], $old_dir );
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
             or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            if( empty( $curl_url ) )
            {
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL Unknown directory to create.' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_MKDIR, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                return false;
            }

            if( !empty( $params['recursive'] ) )
                @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_FTP_CREATE_MISSING_DIRS' ), true );
            @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_URL' ), $curl_url['dir_url'] );

            if( $remote_dir !== '' )
                @curl_setopt( $this->internal_settings['con'], constant( 'CURLOPT_POSTQUOTE' ), array( 'mkdir '.$remote_dir ) );

            if( @curl_exec( $this->internal_settings['con'] ) === false )
            {
                $err = curl_errno( $this->internal_settings['con'] );
                $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP/CURL couldn\'t create directory. ['.$err.']' );
                if( empty( $params['skip_callbacks'] ) )
                    $this->trigger_phs_hooks( self::H_AFTER_MKDIR, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
                return false;
            }

            $this->_close_curl_connection();
        }

        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
        {
            if( substr( $dir, 0, 1 ) !== '/'
            and ($win_drive = substr( $dir, 1, 2 )) !== ':/' and $win_drive !== ':\\' )
            {
                $dir = trim( $dir, '/\\' );
                $remote_dir = rtrim( $this->internal_settings['remote_dir'], '/\\' ).
                    ($dir!==''?'/'.$dir:'');
            } else
                $remote_dir = $dir;

            // On some systems is_dir() or file_exists() returns false even if directory exists...
            // In this case if ssh2_sftp_mkdir() returns false we don't know if there was an error while creating the directory or directory already exists
            // Just assume we created the directory...
            @ssh2_sftp_mkdir( $this->internal_settings['con'], $remote_dir, $params['dir_rights'], $params['recursive'] );

            // if( !@is_dir( 'ssh2.sftp://'.(int)$this->internal_settings['con'].'/'.$remote_dir )
            // and !@ssh2_sftp_mkdir( $this->internal_settings['con'], $remote_dir, $params['dir_rights'], $params['recursive'] ) )
            // {
            //     $this->set_error( self::ERR_REMOTE_LOCATION, 'SFTP cannot create remote directory.' );
            //     if( empty( $params['skip_callbacks'] ) )
            //         $this->trigger_phs_hooks( self::H_AFTER_MKDIR, array( 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => false ) );
            //     return false;
            // }
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_MKDIR, [ 'server' => $ftp_settings, 'dir' => $dir, 'remote_dir' => $remote_dir, 'params' => $params, 'success' => true ] );

        return true;
    }

    /**
     * @param string $old_dir
     * @param string $new_dir
     * @param false|array $params
     *
     * @return bool
     */
    public function rename( $old_dir, $new_dir, $params = false )
    {
        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, 'FTP object not setup.' );
            return false;
        }

        if( !is_string( $old_dir )
         || $old_dir === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Please provide remote directory to be renamed.' );
            return false;
        }

        if( !is_string( $new_dir )
         || $new_dir === '' )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Please provide new remote directory name to be used for rename.' );
            return false;
        }

        $ftp_settings = $this->settings();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_RENAME, [ 'server' => $ftp_settings, 'old_dir' => $old_dir, 'new_dir' => $new_dir, 'params' => $params, 'success' => true ] );

        $return_val = true;
        switch( $ftp_settings['connection_mode'] )
        {
            case self::CON_TYPE_SSH:
                if( !$this->is_connected() && !$this->connect() )
                {
                    if( !$this->has_error() )
                        $this->set_error(self::ERR_CONNECTION, 'FTP not connected and cannot (re)connect to server.');

                    $return_val = false;
                } else
                {
                    if( substr( $old_dir, 0, 1 ) !== '/'
                     && ($win_drive = substr($old_dir, 1, 2)) !== ':/'
                     && $win_drive !== ':\\' )
                    {
                        $old_dir = trim( $old_dir, '/\\' );
                        $old_remote_dir = rtrim( $this->internal_settings['remote_dir'], '/\\' ).
                                          ($old_dir !== '' ? '/' . $old_dir : '');
                    } else
                        $old_remote_dir = $old_dir;

                    if( substr( $new_dir, 0, 1 ) !== '/'
                     && ($win_drive = substr($new_dir, 1, 2)) !== ':/'
                     && $win_drive !== ':\\' )
                    {
                        $new_dir = trim( $new_dir, '/\\' );
                        $new_remote_dir = rtrim( $this->internal_settings['remote_dir'], '/\\' ) .
                                          ($new_dir !== '' ? '/' . $new_dir : '');
                    } else
                        $new_remote_dir = $new_dir;

                    if( !@ssh2_sftp_rename( $this->internal_settings['con'], $old_remote_dir, $new_remote_dir ) )
                    {
                        $this->set_error( self::ERR_REMOTE_LOCATION, 'FTP cannot rename remote directory.' );
                        $return_val = false;
                    }
                }
            break;

            case self::CON_TYPE_NORMAL:
            case self::CON_TYPE_NORMAL_SSL:
            case self::CON_TYPE_CURL:
            case self::CON_TYPE_CURL_SSL:
            default:
                $this->set_error( self::ERR_FUNCTIONALITY, 'FTP rename not implemented for you connection type.' );
                $return_val = false;
            break;
        }

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_RENAME, [ 'server' => $ftp_settings, 'old_dir' => $old_dir, 'new_dir' => $new_dir, 'params' => $params, 'success' => true ] );

        return $return_val;
    }

    /**
     * @param bool|array $params
     *
     * @return bool
     */
    public function close( $params = false )
    {
        if( !$this->is_connected() )
            return true;

        if( !$this->can_connect()
         or empty( $this->internal_settings['con'] ) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['skip_callbacks'] ) )
            $params['skip_callbacks'] = false;

        $ftp_settings = $this->settings();

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_BEFORE_CLOSE, array( 'server' => $ftp_settings, 'success' => true ) );

        if( $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL
         or $ftp_settings['connection_mode'] === self::CON_TYPE_NORMAL_SSL )
        {
            @ftp_close( $this->internal_settings['con'] );
        }
        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_CURL
             or $ftp_settings['connection_mode'] === self::CON_TYPE_CURL_SSL )
        {
            $this->_close_curl_connection();
        }
        elseif( $ftp_settings['connection_mode'] === self::CON_TYPE_SSH )
        {
            @ssh2_exec( $this->internal_settings['con_ssh2'], 'exit' );

            unset( $this->internal_settings['con_ssh2'] );
            unset( $this->internal_settings['con'] );
        }

        $this->internal_settings = self::_default_internal_settings();

        if( empty( $params['skip_callbacks'] ) )
            $this->trigger_phs_hooks( self::H_AFTER_CLOSE, [ 'server' => $ftp_settings, 'success' => true ] );

        return true;
    }

    /**
     * @param string $hook_name
     * @param bool|array $hook_args
     *
     * @return array|null
     */
    private function trigger_phs_hooks( $hook_name, $hook_args = false )
    {
        if( empty( $hook_args ) || !is_array( $hook_args ) )
            $hook_args = [];

        $hook_args['ftp_obj'] = $this;

        return PHS::trigger_hooks( $hook_name, $hook_args );
    }
}
