<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;

//! Class which handles all logging in platform
class PHS_Logger extends PHS_Registry
{
    public const TYPE_MAINTENANCE = 'maintenance.log', TYPE_ERROR = 'errors.log', TYPE_DEBUG = 'debug.log', TYPE_INFO = 'info.log',
          TYPE_BACKGROUND = 'background.log', TYPE_AJAX = 'ajax.log', TYPE_AGENT = 'agent.log', TYPE_API = 'api.log',
          TYPE_TESTS = 'phs_tests.log', TYPE_CLI = 'phs_cli.log', TYPE_REMOTE = 'phs_remote.log',
          // these constants are used only to tell log_channels() method it should log redefined sets of channels
          TYPE_DEF_ALL = 'log_all', TYPE_DEF_DEBUG = 'log_debug', TYPE_DEF_PRODUCTION = 'log_production';

    /** @var bool $_logging */
    private static $_logging = true;
    /** @var array $_custom_channels */
    private static array $_custom_channels = [];
    /** @var array $_channels */
    private static array $_channels = [];
    /** @var bool|string */
    private static $_logs_dir = false;
    /** @var bool|string */
    private static $_request_identifier = false;

    private static function _regenerate_request_identifier(): void
    {
        self::$_request_identifier = (string)microtime( true );
    }

    public static function get_types()
    {
        return [
            self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG, self::TYPE_INFO,
            self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT, self::TYPE_API,
            self::TYPE_TESTS, self::TYPE_CLI, self::TYPE_REMOTE,
        ];
    }

    public static function valid_type( $type ): bool
    {
        if( empty( $type )
         || !($types_arr = self::get_types()) || !in_array( (string)$type, $types_arr, true ) )
            return false;

        return true;
    }

    public static function defined_channel( $channel ): bool
    {
        if( empty( self::$_channels ) || !is_array( self::$_channels )
         || empty( self::$_channels[$channel] ) )
            return false;

        return true;
    }

    public static function safe_escape_log_channel( $channel )
    {
        if( empty( $channel ) || !is_string( $channel )
         || preg_match( '@[^a-zA-Z0-9_\-]@', $channel ) )
            return false;

        return $channel;
    }

    /**
     * @param string $channel Channel name (basically this is the log file name). It should end in .log or have no extension.
     *
     * @return bool true on success, false on error
     */
    public static function define_channel( $channel )
    {
        if( empty( $channel ) || !is_string( $channel ) )
            return false;

        if( strtolower( substr( $channel, -4 ) ) === '.log' )
            $check_channel = substr( $channel, 0, -4 );
        else
            $check_channel = $channel;

        if( !self::safe_escape_log_channel( $check_channel ) )
            return false;

        self::$_custom_channels[$channel] = 1;
        self::$_channels[$channel] = 1;

        return true;
    }

    public static function logging_enabled( $log = null )
    {
        if( $log === null )
            return self::$_logging;

        self::$_logging = (!empty( $log ));
        return self::$_logging;
    }

    public static function logging_dir( $dir = null )
    {
        if( $dir === null )
            return self::$_logs_dir;

        $dir = rtrim( trim( $dir ), '/\\' );
        if( empty( $dir ) || !@is_dir( $dir ) || !@is_writable( $dir ) )
            return false;

        $dir .= '/';

        self::$_logs_dir = $dir;
        return self::$_logs_dir;
    }

    public static function log_channels( $types_arr )
    {
        if( !is_array( $types_arr ) )
        {
            if( !is_string( $types_arr ) )
                return false;

            switch( $types_arr )
            {
                default:
                    return false;
                break;

                case self::TYPE_DEF_ALL:
                    $types_arr = [
                        self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG, self::TYPE_INFO,
                        self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT, self::TYPE_API,
                        self::TYPE_TESTS, self::TYPE_CLI, self::TYPE_REMOTE,
                    ];
                break;

                case self::TYPE_DEF_DEBUG:
                    $types_arr = [
                        self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG,
                        self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT,
                        self::TYPE_API, self::TYPE_TESTS, self::TYPE_CLI, self::TYPE_REMOTE,
                    ];
                break;

                case self::TYPE_DEF_PRODUCTION:
                    $types_arr = [
                        self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_BACKGROUND,
                        self::TYPE_AGENT, self::TYPE_API, self::TYPE_CLI, self::TYPE_REMOTE,
                    ];
                break;
            }
        }

        self::$_channels = self::$_custom_channels;
        foreach( $types_arr as $type )
        {
            if( !self::valid_type( $type ) )
                continue;

            self::$_channels[$type] = 1;
        }

        return self::$_channels;
    }

    public static function get_file_header_arr()
    {
        return [
            '          Date          |    Identifier   |      IP         |  Log',
            '------------------------+-----------------+-----------------+---------------------------------------------------',
        ];
    }

    public static function get_file_header_str()
    {
        return implode( "\n", self::get_file_header_arr() )."\n";
    }

    public static function get_logging_files()
    {
        self::st_reset_error();

        if( !($logs_dir = self::logging_dir()) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t obtain logging directory.' ) );
            return false;
        }

        if( !($log_files_arr = @glob( $logs_dir.'*.log' )) )
            return [];

        $return_arr = [];
        foreach( $log_files_arr as $file_name )
        {
            if( !($base_name = @basename( $file_name )) )
                continue;

            $return_arr[$base_name] = $file_name;
        }

        return $return_arr;
    }

    public static function tail_log( $log_file, $lines, $buffer = 4096 )
    {
        self::st_reset_error();

        if( !($logs_dir = self::logging_dir()) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t obtain logging directory.' ) );
            return false;
        }

        if( strtolower( substr( $log_file, -4 ) ) === '.log' )
            $check_channel = substr( $log_file, 0, -4 );
        else
            $check_channel = $log_file;

        $filename = $logs_dir.$log_file;

        if( substr( $log_file, -4 ) !== '.log' )
            $filename .= '.log';

        if( !PHS::safe_escape_root_script( $check_channel )
         || !@file_exists( $filename ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Invalid logging file.' ) );
            return false;
        }

        // Open the file
        if( !($f = @fopen( $filename, 'rb' )) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error opening log file for read.' ) );
            return false;
        }

        // Jump to last character
        @fseek( $f, -1, SEEK_END );

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if( @fread( $f, 1 ) !== "\n" )
            $lines -= 1;

        // Start reading
        $output = '';
        $chunk = '';

        $using_mb = false;
        if( @function_exists( 'mb_strlen' ) )
            $using_mb = true;

        // While we would like more
        while( ($ftell_val = @ftell( $f )) > 0 && $lines >= 0 )
        {
            // Figure out how far back we should jump
            $seek = min( $ftell_val, $buffer );

            // Do the jump (backwards, relative to where we are)
            @fseek( $f, -$seek, SEEK_CUR );

            if( ($chunk = @fread( $f, $seek )) === false )
                break;

            // Read a chunk and prepend it to our output
            $output = $chunk.$output;

            // Jump back to where we started reading
            if( $using_mb )
                @fseek( $f, -mb_strlen( $chunk, '8bit' ), SEEK_CUR );
            else
                @fseek( $f, -strlen( $chunk ), SEEK_CUR );

            // Decrease our line counter
            $lines -= substr_count( $chunk, "\n" );
        }
        @fclose( $f );

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while( $lines++ < 0 )
        {
            // Find first newline and remove all text before that
            $output = substr( $output, strpos( $output, "\n" ) + 1 );
        }

        return $output;
    }

    public static function logf()
    {
        if( !self::logging_enabled() )
            return true;

        if( !($logs_dir = self::logging_dir())
         || !($args_num = func_num_args())
         || !($args_arr = func_get_args()) )
            return false;

        $str = array_shift( $args_arr );

        $channel = self::TYPE_INFO;
        if( !empty( $args_arr ) && is_array( $args_arr )
         && ($len = count( $args_arr ))
         && self::defined_channel( $args_arr[$len-1] ) )
        {
            $channel = (string)$args_arr[$len - 1];
            array_pop( $args_arr );

            if( empty( $args_arr ) )
                $args_arr = [];
        }

        if( $channel === self::TYPE_INFO )
        {
            $current_scope = PHS_Scope::current_scope();
            switch( $current_scope )
            {
                case PHS_Scope::SCOPE_BACKGROUND:
                    $channel = self::TYPE_BACKGROUND;
                break;
                case PHS_Scope::SCOPE_AJAX:
                    $channel = self::TYPE_AJAX;
                break;
                case PHS_Scope::SCOPE_AGENT:
                    $channel = self::TYPE_AGENT;
                break;
                case PHS_Scope::SCOPE_API:
                    $channel = self::TYPE_API;
                break;
                case PHS_Scope::SCOPE_TESTS:
                    $channel = self::TYPE_TESTS;
                break;
                case PHS_Scope::SCOPE_CLI:
                    $channel = self::TYPE_CLI;
                break;
                case PHS_Scope::SCOPE_REMOTE:
                    $channel = self::TYPE_REMOTE;
                break;
            }
        }

        if( !empty( $args_arr ) )
            $str = vsprintf( $str, $args_arr );

        if( $str === '' )
            return false;

        $log_file = $logs_dir.$channel;

        if( substr( $channel, -4 ) !== '.log' )
            $log_file .= '.log';

        if( !($request_ip = request_ip()) )
            $request_ip = '(unknown)';

        $log_time = date( 'd-m-Y H:i:s T' );

        $hook_args = self::validate_array( [
            'stop_logging' => false,
            'log_file' => $log_file,
            'log_time' => $log_time,
            'request_identifier' => self::$_request_identifier,
            'request_ip' => $request_ip,
            'str' => $str,
        ], PHS_Hooks::default_common_hook_args() );

        $stop_logging = false;
        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_LOG, $hook_args ))
         && is_array( $hook_args ) )
        {
            $stop_logging = (!empty($hook_args['stop_logging'] ));
            if( !empty( $hook_args['request_ip'] ) )
                $request_ip = $hook_args['request_ip'];
            if( !empty( $hook_args['str'] ) )
                $str = $hook_args['str'];
        }

        if( $stop_logging )
            return true;

        @clearstatcache();
        if( !($log_size = @filesize( $log_file )) )
            $log_size = 0;

        if( !($fil = @fopen( $log_file, 'ab' )) )
            return false;

        if( empty( self::$_request_identifier ) )
            self::_regenerate_request_identifier();

        if( empty( $log_size ) )
        {
            @fwrite( $fil, self::get_file_header_str() );
        }

        @fwrite( $fil, str_pad( $log_time, 23, ' ', STR_PAD_LEFT ) . ' | ' .
                      (!empty(self::$_request_identifier) ? str_pad( self::$_request_identifier, 15, ' ', STR_PAD_LEFT ) . ' | ' : '') .
                      str_pad( $request_ip, 15, ' ', STR_PAD_LEFT ) . ' | ' .
                      $str . "\n" );

        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

}
