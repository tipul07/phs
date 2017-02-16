<?php

namespace phs\libraries;

if( (!defined( 'PHS_SETUP_FLOW' ) or !constant( 'PHS_SETUP_FLOW' ))
and !defined( 'PHS_VERSION' ) )
    exit;

class PHS_Error
{
    const ERR_OK = 0, ERR_PARAMETERS = 10000, ERR_FUNCTIONALITY = 10001;

    //! Error code as integer
    /** @var int $error_no */
    private $error_no = self::ERR_OK;
    //! Contains error message including debugging information
    /** @var string $error_msg */
    private $error_msg = '';
    //! Contains only error message
    /** @var string $error_simple_msg */
    private $error_simple_msg = '';
    //! Contains a debugging error message
    /** @var string $error_debug_msg */
    private $error_debug_msg = '';

    //! Warnings count
    /** @var int $warnings_no */
    private $warnings_no = 0;
    //! Warning messages as array. Warnings are categorized by tags saved as array keys
    /** @var array $warnings_arr */
    private $warnings_arr = array();

    //! If true platform will automatically throw errors in set_error() method
    /** @var bool $throw_errors */
    private $throw_errors = false;

    //! Tells if platform in is debugging mode
    /** @var bool $debugging_mode */
    private $debugging_mode = false;

    function __construct( $error_no = self::ERR_OK, $error_msg = '', $error_debug_msg = '', $static_instance = false )
    {
        $error_no = intval( $error_no );
        $error_msg = trim( $error_msg );

        $this->error_no = $error_no;
        $this->error_msg = $error_msg;
        $this->error_debug_msg = $error_debug_msg;

        // Make sure we inherit debugging mode from static call...
        if( empty( $static_instance ) )
        {
            $this->throw_errors( self::st_throw_errors() );
            $this->debugging_mode( self::st_debugging_mode() );
        }
    }

    /**
     * Throw exception with error code and error message only if there is an error code diffrent than self::ERR_OK
     *
     * @return bool
     * @throws \Exception
     */
    public function throw_error()
    {
        if( $this->error_no == self::ERR_OK )
            return false;

        echo 'Full backtrace:'."\n".
             $this->debug_call_backtrace();

        if( $this->debugging_mode() )
            throw new \Exception( $this->error_debug_msg.":\n".$this->error_msg, $this->error_no );
        else
            throw new \Exception( $this->error_simple_msg, $this->error_no );
    }

    public static function st_throw_error()
    {
        //$error_instance = self::get_error_static_instance();
        //if( ($error_arr = $error_instance->get_error())
        //and $error_arr['error_no'] == self::ERR_OK )
        //    return false;
        //
        //if( self::st_debugging_mode() )
        //    throw new \Exception( $error_arr['error_msg'], $error_arr['error_no'] );
        //else
        //    throw new \Exception( $error_arr['error_simple_msg'], $error_arr['error_no'] );
        return self::get_error_static_instance()->throw_error();
    }

    //! Tells if we have an error
    /**
     *   Tells if current error is different than default error code provided in constructor meaning there is an error.
     *
     *   @return bool True if there is an error, false if no error
     **/
    public function has_error()
    {
        return ($this->error_no != self::ERR_OK);
    }

    public static function st_has_error()
    {
        return self::get_error_static_instance()->has_error();
    }

    public static function validate_error_arr( $err_arr )
    {
        if( empty( $err_arr ) or !is_array( $err_arr ) )
            $err_arr = array();

        return array_merge( self::default_error_array(), $err_arr );
    }

    public static function arr_has_error( $err_arr )
    {
        $err_arr = self::validate_error_arr( $err_arr );

        return ($err_arr['error_no'] != self::ERR_OK);
    }

    //! Get number of warnings
    /**
     *   Method returns number of warnings warnings (for specified tag or as total)
     *
     *   @param string|bool $tag Check if we have warnings for provided tag (false by default)
     *   @return int Return warnings number (for specified tag or as total)
     **/
    public function has_warnings( $tag = false )
    {
        if( $tag === false )
            return $this->warnings_no;
        elseif( isset( $this->warnings_arr[$tag] ) and is_array( $this->warnings_arr[$tag] ) )
            return count( $this->warnings_arr[$tag] );

        return 0;
    }

    public static function st_has_warnings( $tag = false )
    {
        return self::get_error_static_instance()->has_warnings( $tag );
    }

    public static function mixed_to_string( $value )
    {
        if( is_bool( $value ) )
            return '('.gettype( $value ).') ['.($value?'true':'false').']';

        if( is_resource( $value ) )
            return '('.@get_resource_type( $value ).')';

        if( is_array( $value ) )
            return '(array) ['.count( $value ).']';

        if( !is_object( $value ) )
        {
            $return_str = '(' . gettype( $value ) . ') [';
            if( is_string( $value ) and strlen( $value ) > 100 )
                $return_str .= substr( $value, 0, 100 ) . '[...]';
            else
                $return_str .= $value;

            $return_str .= ']';

            return  $return_str;
        }

        return '('.@get_class( $value ).')';
    }

    //! Set error code and error message in an array
    /**
     *   Set an error code and error message in an array. Also method will make a backtrace of this call and present all functions/methods called (with their parameters) and files/line of call.
     *
     *   @param int $error_no Error code
     *   @param string $error_msg Error message
     *   @param string $error_debug_msg Error message
     * @return array
     **/
    public static function arr_set_error( $error_no, $error_msg, $error_debug_msg = '' )
    {
        $backtrace = '';
        if( is_array( ($err_info = debug_backtrace()) ) )
        {
            $lvl = count( $err_info );
            for( $i = $lvl-1; $i >= 0; $i-- )
            {
                $args_str = '';
                if( is_array( $err_info[$i]['args'] ) )
                {
                    foreach( $err_info[$i]['args'] as $key => $val )
                        $args_str .= self::mixed_to_string( $val ).', ';

                    $args_str = substr( $args_str, 0, -2 );
                } else
                    $args_str = $err_info[$i]['args'];

                if( !isset( $err_info[$i]['class'] ) )
                    $err_info[$i]['class'] = '';
                if( !isset( $err_info[$i]['type'] ) )
                    $err_info[$i]['type'] = '';
                if( !isset( $err_info[$i]['function'] ) )
                    $err_info[$i]['function'] = '';
                if( !isset( $err_info[$i]['file'] ) )
                    $err_info[$i]['file'] = '(unknown)';
                if( !isset( $err_info[$i]['line'] ) )
                    $err_info[$i]['line'] = '-1';

                $backtrace .= '#'.($lvl-$i).'. '.$err_info[$i]['class'].$err_info[$i]['type'].$err_info[$i]['function'].'( '.$args_str.' ) -> '.
                              $err_info[$i]['file'].':'.$err_info[$i]['line']."\n";
            }
        }

        $error_arr = self::default_error_array();
        $error_arr['error_no'] = $error_no;
        $error_arr['error_simple_msg'] = $error_msg;
        if( $error_debug_msg != '' )
            $error_arr['error_debug_msg'] = $error_debug_msg;
        else
            $error_arr['error_debug_msg'] = $error_msg;
        $error_arr['error_msg'] = 'Error: ('.$error_msg.')'."\n".
                                  'Code: ('.$error_no.')'."\n".
                                  'Backtrace:'."\n".
                                  $backtrace;

        if( self::st_debugging_mode() )
            $error_arr['display_error'] = $error_arr['error_debug_msg'];
        else
            $error_arr['display_error'] = $error_arr['error_simple_msg'];

        return $error_arr;
    }

    //! Set error code and error message
    /**
     *   Set an error code and error message. Also method will make a backtrace of this call and present all functions/methods called (with their parameters) and files/line of call.
     *
     *   @param int $error_no Error code
     *   @param string $error_msg Error message
     *   @param string $error_debug_msg Debugging error message
     *   @param bool|array $params Extra parameters
     **/
    public function set_error( $error_no, $error_msg, $error_debug_msg = '', $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['prevent_throwing_errors'] ) )
            $params['prevent_throwing_errors'] = false;

        if( !($arr = self::arr_set_error( $error_no, $error_msg, $error_debug_msg )) )
            $arr = self::default_error_array();

        $this->error_no = $arr['error_no'];
        $this->error_simple_msg = $arr['error_simple_msg'];
        $this->error_debug_msg = $arr['error_debug_msg'];
        $this->error_msg = $arr['error_msg'];

        if( empty( $params['prevent_throwing_errors'] )
        and $this->throw_errors() )
            $this->throw_error();
    }

    public static function st_set_error( $error_no, $error_msg, $error_debug_msg = '' )
    {
        self::get_error_static_instance()->set_error( $error_no, $error_msg, $error_debug_msg );
    }

    //! Add a warning message
    /**
     *   Add a warning message for a speficied tag or as general warning. Also method will make a backtrace of this call and present all functions/methods called (with their parameters) and files/line of call.
     *
     *   @param string $warning string Warning message
     *   @param bool|string $tag string Add warning for a specific tag (default false). If this is not provided warning will be added as general warning.
     **/
    public function add_warning( $warning, $tag = false )
    {
        $backtrace = '';
        if( is_array( ($err_info = debug_backtrace()) ) )
        {
            $lvl = count( $err_info );
            for( $i = $lvl-1; $i >= 0; $i-- )
            {
                $args_str = '';
                if( is_array( $err_info[$i]['args'] ) )
                {
                    foreach( $err_info[$i]['args'] as $key => $val )
                        $args_str .= self::mixed_to_string( $val ).', ';

                    $args_str = substr( $args_str, 0, -2 );
                } else
                    $args_str = $err_info[$i]['args'];

                $backtrace .= '#'.($lvl-$i).'. '.$err_info[$i]['class'].$err_info[$i]['type'].$err_info[$i]['function'].'( '.$args_str.' ) -> '.
                              $err_info[$i]['file'].':'.$err_info[$i]['line']."\n";
            }
        }

        if( $this->debugging_mode() )
            $warning_msg = $warning."\n".
                           'Backtrace:'."\n".
                           $backtrace;
        else
            $warning_msg = $warning;

        if( !empty( $tag ) )
        {
            if( !isset( $this->warnings_arr[$tag] ) )
                $this->warnings_arr[$tag] = array();

            $this->warnings_arr[$tag][] = $warning_msg;
        } else
            $this->warnings_arr[] = $warning_msg;

        $this->warnings_no++;
    }

    public static function st_add_warning( $warning, $tag = false )
    {
        self::get_error_static_instance()->add_warning( $warning, $tag );
    }

    //! Remove warnings
    /**
     * Remove warning messages for a speficied tag or all warnings.
     *
     * @param bool|string $tag string Remove warnings of specific tag or all warnings. (default false)
     * @return int
     **/
    public function reset_warnings( $tag = false )
    {
        if( $tag !== false )
        {
            if( isset( $this->warnings_arr[$tag] ) and is_array( $this->warnings_arr[$tag] ) )
            {
                $this->warnings_no -= count( $this->warnings_arr[$tag] );
                unset( $this->warnings_arr[$tag] );

                if( !$this->warnings_no )
                    $this->warnings_arr = array();
            }
        } else
        {
            $this->warnings_arr = array();
            $this->warnings_no = 0;
        }

        return $this->warnings_no;
    }

    public static function st_reset_warnings( $tag = false )
    {
        return self::get_error_static_instance()->reset_warnings( $tag );
    }

    public function reset_error()
    {
        $this->error_no = self::ERR_OK;
        $this->error_msg = '';
        $this->error_simple_msg = '';
        $this->error_debug_msg = '';
    }

    public static function st_reset_error()
    {
        self::get_error_static_instance()->reset_error();
    }

    public static function default_error_array()
    {
        return array(
            'error_no' => self::ERR_OK,
            'error_msg' => '',
            'error_simple_msg' => '',
            'error_debug_msg' => '',
            'display_error' => '',
        );
    }

    //! Get error details
    /**
     *   Method returns an array with current error code and message.
     *
     *   @return array Array with indexes 'error_no' for error code and 'error_msg' for error message
     **/
    public function get_error()
    {
        $return_arr = self::default_error_array();

        $return_arr['error_no'] = $this->error_no;
        $return_arr['error_msg'] = $this->error_msg;
        $return_arr['error_simple_msg'] = $this->error_simple_msg;
        $return_arr['error_debug_msg'] = $this->error_debug_msg;

        if( $this->debugging_mode() )
            $return_arr['display_error'] = $this->error_debug_msg;
        else
            $return_arr['display_error'] = $this->error_simple_msg;

        return $return_arr;
    }

    /**
     * @return string Returns error message
     */
    public function get_error_message()
    {
        if( $this->debugging_mode() )
            return $this->error_debug_msg;

        return $this->error_simple_msg;
    }

    /**
     * @return int Returns error code
     */
    public function get_error_code()
    {
        return $this->error_no;
    }

    /**
     * Copies error set in $obj to current object
     *
     * @param PHS_Error $obj
     *
     * @return bool
     */
    public function copy_error( $obj, $force_error_code = false )
    {
        if( empty( $obj ) or !($obj instanceof PHS_Error)
         or !($error_arr = $obj->get_error())
         or !is_array( $error_arr ) )
            return false;

        $this->error_no = $error_arr['error_no'];
        $this->error_msg = $error_arr['error_msg'];
        $this->error_simple_msg = $error_arr['error_simple_msg'];
        $this->error_debug_msg = $error_arr['error_debug_msg'];

        if( $force_error_code !== false )
            $this->error_no = $force_error_code;

        return true;
    }

    /**
     * Copies error set in $error_arr array to current object
     *
     * @param array $error_arr
     *
     * @return bool
     */
    public function copy_error_from_array( $error_arr, $force_error_code = false )
    {
        if( empty( $error_arr ) or !is_array( $error_arr )
         or !isset( $error_arr['error_no'] ) or !isset( $error_arr['error_msg'] )
         or !isset( $error_arr['error_simple_msg'] ) or !isset( $error_arr['error_debug_msg'] ) )
            return false;

        $this->error_no = $error_arr['error_no'];
        $this->error_msg = $error_arr['error_msg'];
        $this->error_simple_msg = $error_arr['error_simple_msg'];
        $this->error_debug_msg = $error_arr['error_debug_msg'];

        if( $force_error_code !== false )
            $this->error_no = $force_error_code;

        return true;
    }

    public static function st_copy_error_from_array( $error_arr, $force_error_code = false )
    {
        return self::get_error_static_instance()->copy_error_from_array( $error_arr, $force_error_code );
    }

    public static function st_copy_error( $obj, $force_error_code = false )
    {
        return self::get_error_static_instance()->copy_error( $obj, $force_error_code );
    }

    public function copy_static_error( $force_error_code = false )
    {
        return $this->copy_error( self::get_error_static_instance(), $force_error_code );
    }

    public static function st_get_error_code()
    {
        return self::get_error_static_instance()->get_error_code();
    }

    public static function st_get_error_message()
    {
        return self::get_error_static_instance()->get_error_message();
    }

    public static function arr_get_error_code( $err_arr )
    {
        $err_arr = self::validate_error_arr( $err_arr );

        return $err_arr['error_no'];
    }

    public static function arr_get_error_message( $err_arr )
    {
        $err_arr = self::validate_error_arr( $err_arr );

        if( self::st_debugging_mode() )
            return $err_arr['error_debug_msg'];

        return $err_arr['error_simple_msg'];
    }

    public static function arr_merge_error_to_array( $source_error_arr, $error_arr )
    {
        $source_error_arr = self::validate_error_arr( $source_error_arr );
        $error_arr = self::validate_error_arr( $error_arr );

        $source_error_arr['error_msg'] .= ($source_error_arr['error_msg']!=''?"\n\n":'').$error_arr['error_msg'];
        $source_error_arr['error_simple_msg'] .= ($source_error_arr['error_simple_msg']!=''?"\n\n":'').$error_arr['error_simple_msg'];
        $source_error_arr['error_debug_msg'] .= ($source_error_arr['error_debug_msg']!=''?"\n\n":'').$error_arr['error_debug_msg'];
        $source_error_arr['display_error'] .= ($source_error_arr['display_error']!=''?"\n\n":'').$error_arr['display_error'];

        return $source_error_arr;
    }

    public function stack_all_errors()
    {
        return array_merge(
            $this->stack_error(),
            self::st_stack_error()
        );
    }

    public function stack_error()
    {
        return array(
            'instance_error' => $this->get_error(),
        );
    }

    public static function st_stack_error()
    {
        return array(
            'static_error' => self::st_get_error(),
        );
    }

    public function restore_errors( $errors_arr )
    {
        if( !empty( $errors_arr['instance_error'] )
        and ($instance_errors = self::validate_error_arr( $errors_arr['instance_error'] )) )
            $this->copy_error_from_array( $instance_errors );

        if( !empty( $errors_arr['static_error'] )
        and ($static_errors = self::validate_error_arr( $errors_arr['static_error'] )) )
            self::st_copy_error_from_array( $static_errors );
    }

    public static function st_restore_errors( $errors_arr )
    {
        if( !empty( $errors_arr['static_error'] )
        and ($static_errors = self::validate_error_arr( $errors_arr['static_error'] )) )
            self::st_copy_error_from_array( $static_errors );
    }

    public static function st_get_error()
    {
        return self::get_error_static_instance()->get_error();
    }

    //! Return warnings for specified tag or all warnings
    /**
     *   Return warnings array for specified tag (if any) or
     *
     *   \param $tag Check if we have warnings for provided tag (false by default)
     *   \return (mixed) Return array of warnings (all or for specified tag) or false if no warnings
     **/
    public function get_warnings( $tag = false )
    {
        if( empty( $this->warnings_arr )
         or ($tag !== false and !isset( $this->warnings_arr[$tag] )) )
            return false;

        $ret_warnings = array();

        if( $tag === false )
            $warning_pool = $this->warnings_arr;
        else
            $warning_pool = $this->warnings_arr[$tag];

        if( empty( $warning_pool ) or !is_array( $warning_pool ) )
            $warning_pool = array();

        foreach( $warning_pool as $wtag => $warning )
        {
            if( is_array( $warning ) )
            {
                foreach( $warning as $junk => $value )
                    $ret_warnings[] = '['.$wtag.'] '.$value;
            } else
                $ret_warnings[] = $warning;
        }

        return $ret_warnings;
    }

    public static function st_get_warnings( $tag = false )
    {
        return self::get_error_static_instance()->get_warnings( $tag );
    }

    //! \brief Returns function/method call backtrace
    /**
     *  Used for debugging calls to functions or methods.
     *  @return string Method will return a string representing function/method calls.
     */
    public function debug_call_backtrace()
    {
        $backtrace = '';
        if( is_array( ($err_info = debug_backtrace()) ) )
        {
            $err_info = array_reverse( $err_info );
            foreach( $err_info as $i => $trace_data )
            {
                if( !isset( $trace_data['args'] ) )
                    $trace_data['args'] = '';
                if( !isset( $trace_data['class'] ) )
                    $trace_data['class'] = '';
                if( !isset( $trace_data['type'] ) )
                    $trace_data['type'] = '';
                if( !isset( $trace_data['function'] ) )
                    $trace_data['function'] = '';
                if( !isset( $trace_data['file'] ) )
                    $trace_data['file'] = '(unknown)';
                if( !isset( $trace_data['line'] ) )
                    $trace_data['line'] = 0;

                $args_str = '';
                if( is_array( $trace_data['args'] ) )
                {
                    foreach( $trace_data['args'] as $key => $val )
                        $args_str .= self::mixed_to_string( $val ).', ';

                    $args_str = substr( $args_str, 0, -2 );
                } else
                    $args_str = $trace_data['args'];

                $backtrace .= '#'.($i+1).'. '.$trace_data['class'].$trace_data['type'].$trace_data['function'].'( '.$args_str.' ) - '.
                              $trace_data['file'].':'.$trace_data['line']."\n";
            }
        }

        return $backtrace;
    }

    //! @brief Returns function/method call backtrace. Used for static calls
    /**
     *  Used for debugging calls to functions or methods.
     *  @return string Method will return a string representing function/method calls.
     */
    public static function st_debug_call_backtrace()
    {
        $backtrace = '';
        if( is_array( ($err_info = debug_backtrace()) ) )
        {
            $err_info = array_reverse( $err_info );
            foreach( $err_info as $i => $trace_data )
            {
                if( !isset( $trace_data['args'] ) )
                    $trace_data['args'] = '';
                if( !isset( $trace_data['class'] ) )
                    $trace_data['class'] = '';
                if( !isset( $trace_data['type'] ) )
                    $trace_data['type'] = '';
                if( !isset( $trace_data['function'] ) )
                    $trace_data['function'] = '';
                if( !isset( $trace_data['file'] ) )
                    $trace_data['file'] = '(unknown)';
                if( !isset( $trace_data['line'] ) )
                    $trace_data['line'] = 0;

                $args_str = '';
                if( is_array( $trace_data['args'] ) )
                {
                    foreach( $trace_data['args'] as $key => $val )
                        $args_str .= self::mixed_to_string( $val ).', ';

                    $args_str = substr( $args_str, 0, -2 );
                } else
                    $args_str = $trace_data['args'];

                $backtrace .= '#'.($i+1).'. '.$trace_data['class'].$trace_data['type'].$trace_data['function'].'( '.$args_str.' ) - '.
                              $trace_data['file'].':'.$trace_data['line']."\n";
            }
        }

        return $backtrace;
    }

    public function throw_errors( $mode = null )
    {
        if( is_null( $mode ) )
            return $this->throw_errors;

        $this->throw_errors = (!empty( $mode )?true:false);

        return $this->throw_errors;
    }

    public static function st_throw_errors( $mode = null )
    {
        return self::get_error_static_instance()->throw_errors( $mode );
    }

    public function debugging_mode( $mode = null )
    {
        if( is_null( $mode ) )
            return $this->debugging_mode;

        $this->debugging_mode = (!empty( $mode )?true:false);

        return $this->debugging_mode;
    }

    public static function st_debugging_mode( $mode = null )
    {
        return self::get_error_static_instance()->debugging_mode( $mode );
    }

    static function get_error_static_instance()
    {
        static $error_instance = false;

        if( empty( $error_instance ) )
            $error_instance = new PHS_Error( self::ERR_OK, '', '', true );

        return $error_instance;
    }
}
