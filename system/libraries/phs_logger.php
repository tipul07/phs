<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;

//! Class which handles all logging in platform
class PHS_Logger extends PHS_Registry
{
    public const L_DEBUG = 1, L_INFO = 2, L_NOTICE = 3, L_WARNING = 4, L_ERROR = 5, L_CRITICAL = 6, L_ALERT = 7, L_EMERGENCY = 8;
    /**
     * @var array|array[]
     */
    protected static array $LEVELS_ARR = [
        self::L_DEBUG => [ 'title' => 'Debug', 'log_title' => 'DBG', ],
        self::L_INFO => [ 'title' => 'Info', 'log_title' => 'INF', ],
        self::L_NOTICE => [ 'title' => 'Notice', 'log_title' => 'NOT', ],
        self::L_WARNING => [ 'title' => 'Warning', 'log_title' => 'WAR', ],
        self::L_ERROR => [ 'title' => 'Error', 'log_title' => 'ERR', ],
        self::L_CRITICAL => [ 'title' => 'Critical', 'log_title' => 'CRT', ],
        self::L_ALERT => [ 'title' => 'Alert', 'log_title' => 'ALT', ],
        self::L_EMERGENCY => [ 'title' => 'Emergency', 'log_title' => 'EMG', ],
    ];

    public const TYPE_MAINTENANCE = 'maintenance.log', TYPE_ERROR = 'errors.log', TYPE_DEBUG = 'debug.log', TYPE_INFO = 'info.log',
          TYPE_BACKGROUND = 'background.log', TYPE_AJAX = 'ajax.log', TYPE_AGENT = 'agent.log', TYPE_API = 'api.log',
          TYPE_TESTS = 'phs_tests.log', TYPE_CLI = 'phs_cli.log', TYPE_REMOTE = 'phs_remote.log',
          // these constants are used only to tell log_channels() method it should log redefined sets of channels
          TYPE_DEF_ALL = 'log_all', TYPE_DEF_DEBUG = 'log_debug', TYPE_DEF_PRODUCTION = 'log_production';

    /**
     * @var int Current log level...
     */
    private static int $_log_level = self::L_DEBUG;

    /**
     * @var int Default log level if not provided...
     */
    private static int $_default_log_level = self::L_NOTICE;
    /** @var bool $_logging */
    private static bool $_logging = true;
    /** @var array $_custom_channels */
    private static array $_custom_channels = [];
    /** @var array $_channels */
    private static array $_channels = [];
    /** @var bool|string */
    private static $_logs_dir = false;
    /** @var bool|string */
    private static $_request_identifier = false;

    /**
     * @param false|string $lang
     *
     * @return array
     */
    public static function get_log_levels( $lang = false ): array
    {
        static $levels_arr = [];

        if( empty( self::$LEVELS_ARR ) ) {
            return [];
        }

        if( $lang === false
         && !empty( $levels_arr ) ) {
            return $levels_arr;
        }

        $result_arr = [];
        foreach( self::$LEVELS_ARR as $lvl_id => $lvl_arr ) {
            if( empty( $lvl_arr['title'] ) ) {
                continue;
            }

            $lvl_arr['title'] = self::_t( $lvl_arr['title'], $lang );

            $result_arr[$lvl_id] = $lvl_arr;
        }

        if( $lang === false ) {
            $levels_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param ?string  $lang
     *
     * @return array
     */
    public static function get_log_levels_as_key_val( string $lang = null ): array
    {
        static $levels_key_val_arr = null;

        if( $lang === null
         && $levels_key_val_arr !== null ) {
            return $levels_key_val_arr;
        }

        $key_val_arr = [];
        if( ($statuses = self::get_log_levels( $lang )) )
        {
            foreach( $statuses as $key => $val )
            {
                if( !is_array( $val ) ) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false ) {
            $levels_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param int $level
     * @param  null|string  $lang
     *
     * @return null|array
     */
    public static function valid_log_level( int $level, string $lang = null ): ?array
    {
        $all_levels = self::get_log_levels( $lang );
        if( empty( $level )
         || !isset( $all_levels[$level] ) ) {
            return null;
        }

        return $all_levels[$level];
    }

    public static function get_types(): array
    {
        return [
            self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG, self::TYPE_INFO,
            self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT, self::TYPE_API,
            self::TYPE_TESTS, self::TYPE_CLI, self::TYPE_REMOTE,
        ];
    }

    public static function valid_type( string $type ): bool
    {
        return (!empty( $type ) && ($types_arr = self::get_types()) && in_array( $type, $types_arr, true ));
    }

    public static function defined_channel( $channel ): bool
    {
        return !empty(self::$_channels[$channel]);
    }

    public static function safe_escape_log_channel( string $channel ): ?string
    {
        if( empty( $channel )
         || preg_match( '@[^a-zA-Z0-9_\-]@', $channel ) ) {
            return null;
        }

        return $channel;
    }

    /**
     * @param  string  $channel Channel name (basically this is the log file name). It should end in .log or have no extension.
     *
     * @return bool true on success, false on error
     */
    public static function define_channel( string $channel ): bool
    {
        if( empty( $channel ) ) {
            return false;
        }

        if( strtolower( substr( $channel, -4 ) ) === '.log' ) {
            $check_channel = substr($channel, 0, -4);
        } else {
            $check_channel = $channel;
        }

        if( !self::safe_escape_log_channel( $check_channel ) ) {
            return false;
        }

        self::$_custom_channels[$channel] = true;
        self::$_channels[$channel] = true;

        return true;
    }

    public static function logging_enabled( $log = null ): bool
    {
        if( $log === null ) {
            return self::$_logging;
        }

        self::$_logging = (!empty( $log ));
        return self::$_logging;
    }

    public static function default_log_level( int $lvl = null ): ?int
    {
        if( $lvl === null ) {
            return self::$_default_log_level;
        }

        if( !self::valid_log_level( $lvl ) ) {
            return null;
        }

        self::$_default_log_level = $lvl;
        return self::$_default_log_level;
    }

    public static function log_level( int $lvl = null ): ?int
    {
        if( $lvl === null ) {
            return self::$_log_level;
        }

        if( !self::valid_log_level( $lvl ) ) {
            return null;
        }

        self::$_log_level = $lvl;
        return self::$_log_level;
    }

    public static function logging_dir( string $dir = null )
    {
        if( $dir === null ) {
            return self::$_logs_dir;
        }

        $dir = rtrim( trim( $dir ), '/\\' );
        if( empty( $dir ) || !@is_dir( $dir ) || !@is_writable( $dir ) ) {
            return false;
        }

        $dir .= '/';

        self::$_logs_dir = $dir;
        return self::$_logs_dir;
    }

    public static function log_channels( $types_arr ): ?array
    {
        if( !is_array( $types_arr ) )
        {
            if( !is_string( $types_arr ) ) {
                return null;
            }

            switch( $types_arr )
            {
                default:
                    return null;
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
            if( !self::valid_type( $type ) ) {
                continue;
            }

            self::$_channels[$type] = 1;
        }

        return self::$_channels;
    }

    public static function get_file_header_arr(): array
    {
        return [
            '          Date          | Lvl |    Identifier   |      IP         |  Log',
            '------------------------+-----+-----------------+-----------------+---------------------------------------------------',
        ];
    }

    public static function get_file_header_str(): string
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

        if( !($log_files_arr = @glob( $logs_dir.'*.log' )) ) {
            return [];
        }

        $return_arr = [];
        foreach( $log_files_arr as $file_name )
        {
            if( !($base_name = @basename( $file_name )) ) {
                continue;
            }

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

        if( strtolower( substr( $log_file, -4 ) ) === '.log' ) {
            $check_channel = substr($log_file, 0, -4);
        } else {
            $check_channel = $log_file;
        }

        $filename = $logs_dir.$log_file;

        if( substr( $log_file, -4 ) !== '.log' ) {
            $filename .= '.log';
        }

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
        if( @fread( $f, 1 ) !== "\n" ) {
            --$lines;
        }

        // Start reading
        $output = '';

        $using_mb = false;
        if( @function_exists( 'mb_strlen' ) ) {
            $using_mb = true;
        }

        // While we would like more
        while( ($ftell_val = @ftell( $f )) > 0 && $lines >= 0 )
        {
            // Figure out how far back we should jump
            $seek = min( $ftell_val, $buffer );

            // Do the jump (backwards, relative to where we are)
            @fseek( $f, -$seek, SEEK_CUR );

            if( ($chunk = @fread( $f, $seek )) === false ) {
                break;
            }

            // Read a chunk and prepend it to our output
            $output = $chunk.$output;

            // Jump back to where we started reading
            if( $using_mb ) {
                @fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            } else {
                @fseek($f, -strlen($chunk), SEEK_CUR);
            }

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

    /**
     * System is unusable.
     *
     * @param  string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function emergency(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_EMERGENCY, $message, $log_file, $context );
    }

    /**
     * Action must be taken immediately.
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function alert(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_ALERT, $message, $log_file, $context );
    }

    /**
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function critical(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_CRITICAL, $message, $log_file, $context );
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function error(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_ERROR, $message, $log_file, $context );
    }

    /**
     * Exceptional occurrences that are not errors.
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function warning(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_WARNING, $message, $log_file, $context );
    }

    /**
     * Normal but significant events.
     *
     * @param  string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function notice(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_NOTICE, $message, $log_file, $context );
    }

    /**
     * Interesting events.
     * Example: User logs in, SQL logs.
     *
     * @param string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function info(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_INFO, $message, $log_file, $context );
    }

    /**
     * Detailed debug information.
     *
     * @param  string  $message
     * @param string $log_file
     * @param array $context
     *
     * @return void
     */
    public static function debug(string $message, string $log_file, array $context = [] ): void
    {
        self::log( self::L_DEBUG, $message, $log_file, $context );
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param int $level
     * @param string $log_file
     * @param  string  $message
     * @param array $context
     *
     * @return void
     */
    public static function log( int $level, string $message, string $log_file, array $context = [] ): void
    {
        self::logf( self::interpolate( $message, $context ), $log_file, $level );
    }

    public static function interpolate( $message, array $context = [] ): string
    {
        if( false === strpos( $message, '{' ) ) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    public static function logf(): bool
    {
        if( !self::logging_enabled() ) {
            return true;
        }

        if( !($logs_dir = self::logging_dir())
         || !func_num_args()
         || !($args_arr = func_get_args()) ) {
            return false;
        }

        $str = array_shift( $args_arr );

        $log_level = self::default_log_level();
        if( !empty( $args_arr ) && is_array( $args_arr )
         && ($len = count( $args_arr ))
         && self::valid_log_level( (int)$args_arr[$len-1] ) )
        {
            $log_level = (int)$args_arr[$len - 1];
            array_pop( $args_arr );

            if( empty( $args_arr ) ) {
                $args_arr = [];
            }
        }

        if( !($log_details = self::valid_log_level( $log_level )) ) {
            $log_level = self::L_NOTICE;
            $log_details = self::$LEVELS_ARR[$log_level];
        }

        if( self::log_level() >= $log_level ) {
            return true;
        }

        $channel = self::TYPE_INFO;
        if( !empty( $args_arr ) && is_array( $args_arr )
         && ($len = count( $args_arr ))
         && self::defined_channel( $args_arr[$len-1] ) )
        {
            $channel = (string)$args_arr[$len - 1];
            array_pop( $args_arr );

            if( empty( $args_arr ) ) {
                $args_arr = [];
            }
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

        if( !empty( $args_arr ) ) {
            $str = vsprintf($str, $args_arr);
        }

        if( $str === '' ) {
            return false;
        }

        $log_file = $logs_dir.$channel;

        if( substr( $channel, -4 ) !== '.log' ) {
            $log_file .= '.log';
        }

        if( !($request_ip = request_ip()) ) {
            $request_ip = '(unknown)';
        }

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
            if( !empty( $hook_args['request_ip'] ) ) {
                $request_ip = $hook_args['request_ip'];
            }
            if( !empty( $hook_args['str'] ) ) {
                $str = $hook_args['str'];
            }
        }

        if( $stop_logging ) {
            return true;
        }

        @clearstatcache();
        if( !($log_size = @filesize( $log_file )) ) {
            $log_size = 0;
        }

        if( !($fil = @fopen( $log_file, 'ab' )) ) {
            return false;
        }

        if( empty( self::$_request_identifier ) ) {
            self::_regenerate_request_identifier();
        }

        if( empty( $log_size ) )
        {
            @fwrite( $fil, self::get_file_header_str() );
        }

        @fwrite( $fil,
            str_pad( $log_time, 23, ' ', STR_PAD_LEFT ) . ' | ' .
            ($log_details['log_title'] ?? '   ' ). ' | ' .
            (!empty(self::$_request_identifier) ? str_pad( self::$_request_identifier, 15, ' ', STR_PAD_LEFT ) . ' | ' : '') .
            str_pad( $request_ip, 15, ' ', STR_PAD_LEFT ) . ' | ' .
            $str . "\n" );

        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

    private static function _regenerate_request_identifier(): void
    {
        self::$_request_identifier = (string)microtime( true );
    }
}
