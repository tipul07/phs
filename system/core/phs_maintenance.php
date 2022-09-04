<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Registry;

// We don't translate messages in this class as they are pure maintenance texts...
final class PHS_Maintenance extends PHS_Registry
{
    static private $last_output = null;

    public static function output( $msg )
    {
        // In logs, we have timestamps
        PHS_Logger::logf( $msg, PHS_Logger::TYPE_MAINTENANCE );

        if( empty( self::$last_output ) )
        {
            self::$last_output = time();
            $msg = date( '(Y-m-d H:i:s)', self::$last_output ).' '.$msg;
        } else
        {
            $msg = '('.str_pad( '+'.(time()-self::$last_output).'s', 19, ' ', STR_PAD_LEFT ).') '.$msg;
        }

        if( ($callback = self::output_callback()) )
            $callback( $msg );
    }

    /**
     * If we need to capture maintenance output we will pass a callble which will handle output
     * If $callback is false, maintenance class will not call anything for the output
     *
     * @param null|bool|callable $callback
     *
     * @return bool|callable
     */
    public static function output_callback( $callback = null )
    {
        static $output_callback = false;

        if( $callback === null )
            return $output_callback;

        self::st_reset_error();

        if( $callback === false )
        {
            $output_callback = false;
            return true;
        }

        if( empty( $callback )
         || !is_callable( $callback ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, 'Maintenance output callback is not a callable.' );
            return false;
        }

        $output_callback = $callback;
        return true;
    }
}
