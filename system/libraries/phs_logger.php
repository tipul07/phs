<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;

//! Class which handles all logging in platform
class PHS_Logger extends PHS_Registry
{
    const TYPE_MAINTENANCE = 'maintenance.log', TYPE_ERROR = 'errors.log', TYPE_DEBUG = 'debug.log', TYPE_INFO = 'info.log',
          TYPE_BACKGROUND = 'background.log', TYPE_AJAX = 'ajax.log', TYPE_AGENT = 'agent.log',
          // this constants are used only to tell log_channels() method it should log redefined sets of channels
          TYPE_DEF_ALL = 'log_all', TYPE_DEF_DEBUG = 'log_debug', TYPE_DEF_PRODUCTION = 'log_production';

    private static $_logging = true;
    private static $_custom_channels = array();
    private static $_channels = array();
    private static $_logs_dir = false;
    private static $_request_identifier = false;

    private static function _regenerate_request_identifier()
    {
        self::$_request_identifier = microtime( true );
    }

    public static function get_types()
    {
        return array( self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG, self::TYPE_INFO,
                      self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT );
    }

    public static function valid_type( $type )
    {
        if( empty( $type )
         or !($types_arr = self::get_types()) or !in_array( $type, $types_arr ) )
            return false;

        return true;
    }

    public static function defined_channel( $channel )
    {
        if( empty( self::$_channels ) or !is_array( self::$_channels )
         or empty( self::$_channels[$channel] ) )
            return false;

        return true;
    }

    /**
     * @param string $channel Channel name (basically this is the log file name). It should end in .log or have no extension.
     *
     * @return bool true on success, false on error
     */
    public static function define_channel( $channel )
    {
        if( empty( $channel ) or !is_string( $channel ) )
            return false;

        if( strtolower( substr( $channel, -4 ) ) == '.log' )
            $check_channel = substr( $channel, 0, -4 );
        else
            $check_channel = $channel;

        if( !PHS::safe_escape_root_script( $check_channel ) )
            return false;

        self::$_custom_channels[$channel] = 1;
        self::$_channels[$channel] = 1;

        return true;
    }

    public static function logging_enabled( $log = null )
    {
        if( $log === null )
            return self::$_logging;

        self::$_logging = (!empty( $log )?true:false);
        return self::$_logging;
    }

    public static function logging_dir( $dir = null )
    {
        if( $dir === null )
            return self::$_logs_dir;

        $dir = rtrim( trim( $dir ), '/\\' );
        if( empty( $dir ) or !@is_dir( $dir ) or !@is_writable( $dir ) )
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
                    $types_arr = array( self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG, self::TYPE_INFO,
                                        self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT );
                break;

                case self::TYPE_DEF_DEBUG:
                    $types_arr = array( self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_DEBUG,
                                        self::TYPE_BACKGROUND, self::TYPE_AJAX, self::TYPE_AGENT );
                break;

                case self::TYPE_DEF_PRODUCTION:
                    $types_arr = array( self::TYPE_MAINTENANCE, self::TYPE_ERROR, self::TYPE_BACKGROUND, self::TYPE_AGENT );
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

    public static function logf()
    {
        if( !self::logging_enabled() )
            return true;

        if( !($logs_dir = self::logging_dir())
         or !($args_num = func_num_args())
         or !($args_arr = func_get_args()) )
            return false;

        $str = array_shift( $args_arr );

        $channel = self::TYPE_INFO;
        if( !empty( $args_arr ) and is_array( $args_arr )
        and ($len = count( $args_arr ))
        and self::defined_channel( $args_arr[$len-1] ) )
        {
            $channel = $args_arr[$len - 1];
            array_pop( $args_arr );

            if( empty( $args_arr ) )
                $args_arr = array();
        }

        if( $channel == self::TYPE_INFO )
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
            }
        }

        if( !empty( $args_arr ) )
            $str = vsprintf( $str, $args_arr );

        if( $str === '' )
            return false;

        $log_file = $logs_dir.$channel;

        if( !($request_ip = request_ip()) )
            $request_ip = '(unknown)';

        $log_time = date( 'd-m-Y H:i:s T' );

        $hook_args = self::validate_array( array(
            'stop_logging' => false,
            'log_file' => $log_file,
            'log_time' => $log_time,
            'request_identifier' => self::$_request_identifier,
            'request_ip' => $request_ip,
            'str' => $str,
        ), PHS_Hooks::default_common_hook_args() );

        $stop_logging = false;
        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_LOG, $hook_args ))
        and is_array( $hook_args ) )
        {
            $stop_logging = (!empty( $hook_args['stop_logging'] )?true:false);
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

        if( !($fil = @fopen( $log_file, 'a' )) )
            return false;

        if( empty( self::$_request_identifier ) )
            self::_regenerate_request_identifier();

        if( empty( $log_size ) )
        {
            fputs( $fil, "          Date          |    Identifier   |      IP         |  Log\n" );
            fputs( $fil, "------------------------+-----------------+-----------------+---------------------------------------------------\n" );
        }

        @fputs( $fil, str_pad( $log_time, 23, ' ', STR_PAD_LEFT ) . ' | ' .
                      (!empty(self::$_request_identifier) ? str_pad( self::$_request_identifier, 15, ' ', STR_PAD_LEFT ) . ' | ' : '') .
                      str_pad( $request_ip, 15, ' ', STR_PAD_LEFT ) . ' | ' .
                      $str . "\n" );

        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

}
