<?php

namespace phs\libraries;

if( !defined( 'PHS_VERSION' )
 && (!defined( 'PHS_SETUP_FLOW' ) || !constant( 'PHS_SETUP_FLOW' )) )
    exit;

class PHS_Error
{
    const ERR_OK = 0, ERR_PARAMETERS = 10000, ERR_FUNCTIONALITY = 10001,
          ERR_SERVER = 10002, ERR_WEB_SERVER = 10003, ERR_FRAMEWORK = 10004, ERR_RESOURCES = 10005,
          ERR_DEPENDENCIES = 10006, ERR_PLUGIN_SETUP = 10007;

    const WARNING_NOTAG = -1;

    //! Error code as integer
    /** @var int $error_no */
    private $error_no;
    //! Contains error message including debugging information
    /** @var string $error_msg */
    private $error_msg;
    //! Contains only error message
    /** @var string $error_simple_msg */
    private $error_simple_msg = '';
    //! Contains a debugging error message
    /** @var string $error_debug_msg */
    private $error_debug_msg;

    //! Warnings count
    /** @var int $warnings_no */
    private $warnings_no = 0;
    //! Warning messages as array. Warnings are categorized by tags saved as array keys
    /** @var array $warnings_arr */
    private $warnings_arr = [];

    //! If true platform will automatically throw errors in set_error() method
    /** @var bool $throw_errors */
    private $throw_errors = false;

    //! Tells if platform in is debugging mode
    /** @var bool $debugging_mode */
    private $debugging_mode = false;

    //! Tells if we should get full backtrace when we have an error.
    //! In some cases debug_backtrace() would result in Segmentation fault because of some resources (eg. SSH2/SFTP connections)
    /** @var bool $supress_backtrace */
    private $suppress_backtrace = false;

    public function __construct( $error_no = self::ERR_OK, $error_msg = '', $error_debug_msg = '', $static_instance = false )
    {
        $error_no = (int)$error_no;
        $error_msg = trim( $error_msg );

        $this->error_no = $error_no;
        $this->error_msg = $error_msg;
        $this->error_debug_msg = $error_debug_msg;

        // Make sure we inherit debugging mode from static call...
        if( empty( $static_instance ) )
        {
            $this->throw_errors( self::st_throw_errors() );
            $this->debugging_mode( self::st_debugging_mode() );
            $this->suppress_backtrace( self::st_suppress_backtrace() );
        }
    }

    /**
     * Throw exception with error code and error message only if there is an error code different than self::ERR_OK
     *
     * @return bool
     * @throws \Exception
     */
    public function throw_error()
    {
        if( $this->error_no === self::ERR_OK )
            return false;

        echo 'Full backtrace:'."\n".
             $this->debug_call_backtrace( 1 );

        if( $this->debugging_mode() )
            throw new \Exception( $this->error_debug_msg.":\n".$this->error_msg, $this->error_no );
        else
            throw new \Exception( $this->error_simple_msg, $this->error_no );
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public static function st_throw_error()
    {
        return self::get_error_static_instance()->throw_error();
    }

    //! Tells if we have an error
    /**
     *   Tells if current error is different from default error code provided in constructor meaning there is an error.
     *
     *   @return bool True if there is an error, false if no error
     **/
    public function has_error()
    {
        return ($this->error_no !== self::ERR_OK);
    }

    public static function st_has_error()
    {
        return self::get_error_static_instance()->has_error();
    }

    public static function validate_error_arr( $err_arr )
    {
        if( empty( $err_arr ) || !is_array( $err_arr ) )
            $err_arr = [];

        $return_arr = array_merge( self::default_error_array(), $err_arr );
        $return_arr['error_no'] = (int)$return_arr['error_no'];

        return $return_arr;
    }

    public static function arr_has_error( $err_arr )
    {
        $err_arr = self::validate_error_arr( $err_arr );

        return ($err_arr['error_no'] !== self::ERR_OK);
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
        elseif( isset( $this->warnings_arr[$tag] ) && is_array( $this->warnings_arr[$tag] ) )
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
            if( is_string( $value ) && strlen( $value ) > 100 )
                $return_str .= substr( $value, 0, 100 ) . '[...]';
            else
                $return_str .= $value;

            $return_str .= ']';

            return $return_str;
        }

        return '('.@get_class( $value ).')';
    }

    /**
     * Do a var_dump up to a specified level (helps using var_dump on big objects)
     * @param mixed $var
     * @param bool|array $params
     *
     * @return array|false|\stdClass|string
     */
    public static function var_dump( $var, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['level'] ) )
            $params['level'] = 0;
        if( !isset( $params['max_level'] ) )
            $params['max_level'] = 3;

        if( $params['level'] >= $params['max_level'] )
        {
            if( is_scalar( $var ) )
            {
                if( !empty( $params['level'] ) )
                    return $var;

                ob_start();
                var_dump( $var );
                return ob_get_clean();
            }

            return '[Max recursion lvl reached: ' . $params['max_level'] . '] (' . gettype( $var ).' '.self::mixed_to_string( $var ) . ')';
        }

        $new_params = $params;
        $new_params['level']++;

        if( is_array( $var ) )
        {
            $new_var = [];
            foreach( $var as $key => $arr_val )
                $new_var[$key] = self::var_dump( $arr_val, $new_params );
        } elseif( is_object( $var ) )
        {
            if( !($var_type = @get_class( $var )) )
                $var_type = 'object';

            $new_var = new \stdClass();
            $new_var->__var_dump_object_type = $var_type;
            if( ($var_vars = get_object_vars( $var )) )
            {
                foreach( $var_vars as $key => $arr_val )
                    $new_var->$key = self::var_dump( $arr_val, $new_params );
            }
        } elseif( is_resource( $var ) )
            $new_var = 'Resource ('.@get_resource_type( $var ).')';
        else
            $new_var = $var;

        if( empty( $params['level'] ) )
        {
            ob_start();
            var_dump( $new_var );

            return ob_get_clean();
        }

        return $new_var;
    }

    //! Set error code and error message in an array
    /**
     * Set an error code and error message in an array.
     * Also, method will make a backtrace of this call and present all functions/methods
     * called (with their parameters) and files/line of call.
     *
     *   @param int $error_no Error code
     *   @param string $error_msg Error message
     *   @param string $error_debug_msg Error message
     * @return array
     **/
    public static function arr_set_error( $error_no, $error_msg, $error_debug_msg = '' )
    {
        $backtrace = self::st_debug_call_backtrace();

        $error_arr = self::default_error_array();
        $error_arr['error_no'] = (int)$error_no;
        $error_arr['error_simple_msg'] = $error_msg;
        if( $error_debug_msg !== '' )
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
     *   Set an error code and error message. Also, method will make a backtrace of this call and present all
     * functions/methods called (with their parameters) and files/line of call.
     *
     * @param  int  $error_no  Error code
     * @param  string  $error_msg  Error message
     * @param  string  $error_debug_msg  Debugging error message
     * @param  bool|array  $params  Extra parameters
     */
    public function set_error( $error_no, $error_msg, $error_debug_msg = '', $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['prevent_throwing_errors'] ) )
            $params['prevent_throwing_errors'] = false;

        if( !($arr = self::arr_set_error( $error_no, $error_msg, $error_debug_msg )) )
            $arr = self::default_error_array();

        $this->error_no = $arr['error_no'];
        $this->error_simple_msg = $arr['error_simple_msg'];
        $this->error_debug_msg = $arr['error_debug_msg'];
        $this->error_msg = $arr['error_msg'];

        // if( empty( $params['prevent_throwing_errors'] )
        //  && $this->throw_errors() )
        //     $this->throw_error();
    }

    /**
     * @param int $error_no
     * @param string $error_msg
     * @param string $error_debug_msg
     *
     * @return void
     */
    public static function st_set_error( $error_no, $error_msg, $error_debug_msg = '' )
    {
        self::get_error_static_instance()->set_error( $error_no, $error_msg, $error_debug_msg );
    }

    /**
     * @param string $error_msg
     * @param string $error_debug_msg
     *
     * @return array
     */
    public function change_error_message( $error_msg, $error_debug_msg = '' )
    {
        if( empty( $error_debug_msg ) )
            $error_debug_msg = $error_msg;

        $this->error_simple_msg = $error_msg;
        $this->error_debug_msg = $error_debug_msg;

        return $this->get_error();
    }

    /**
     * @param string $error_msg
     * @param string $error_debug_msg
     *
     * @return array
     */
    public function st_change_error_message( $error_msg, $error_debug_msg = '' )
    {
        return self::get_error_static_instance()->change_error_message( $error_msg, $error_debug_msg );
    }

    /**
     * @param int $error_no
     *
     * @return array
     */
    public function change_error_code( $error_no )
    {
        $this->error_no = (int)$error_no;

        return $this->get_error();
    }

    /**
     * @param int $error_no
     *
     * @return array
     */
    public function st_change_error_code( $error_no )
    {
        return self::get_error_static_instance()->change_error_code( $error_no );
    }

    //! Add a warning message
    /**
     * Add a warning message for a specified tag or as general warning.
     * Also, method will make a backtrace of this call and present all
     * functions/methods called (with their parameters) and files/line of call.
     *
     *   @param string $warning string Warning message
     *   @param false|string|int $tag string Add warning for a specific tag (default false).
     *                           If this is not provided, warning will be added as general warning.
     *
     * @return void
     **/
    public function add_warning( $warning, $tag = false )
    {
        if( empty( $this->warnings_arr[self::WARNING_NOTAG] ) )
            $this->warnings_arr[self::WARNING_NOTAG] = [];

        $backtrace = $this->debug_call_backtrace( 1 );

        $warning_unit = [
            'warning_msg' => $warning,
            'debug_msg' => $warning."\n".
                           'Backtrace:'."\n".
                           $backtrace,
        ];

        if( is_string( $tag ) || is_int( $tag ) )
        {
            if( !isset( $this->warnings_arr[$tag] ) )
                $this->warnings_arr[$tag] = [];

            $this->warnings_arr[$tag][] = $warning_unit;
        } else
            $this->warnings_arr[self::WARNING_NOTAG][] = $warning_unit;

        $this->warnings_no++;
    }

    /**
     * @param string $warning
     * @param false|string|int $tag
     *
     * @return void
     */
    public static function st_add_warning( $warning, $tag = false )
    {
        self::get_error_static_instance()->add_warning( $warning, $tag );
    }

    //! Remove warnings
    /**
     * Remove warning messages for a speficied tag or all warnings.
     *
     * @param false|string|int $tag string Remove warnings of specific tag or all warnings. (default false)
     * @return int Returns number of warnings left after removing required warnings
     **/
    public function reset_warnings( $tag = false )
    {
        if( $tag !== false )
        {
            if( isset( $this->warnings_arr[$tag] ) && is_array( $this->warnings_arr[$tag] ) )
            {
                $this->warnings_no -= count( $this->warnings_arr[$tag] );
                unset( $this->warnings_arr[$tag] );

                if( !$this->warnings_no )
                    $this->warnings_arr = [];
            }
        } else
        {
            $this->warnings_arr = [];
            $this->warnings_no = 0;
        }

        return $this->warnings_no;
    }

    /**
     * @param false|string|int $tag
     *
     * @return int
     */
    public static function st_reset_warnings( $tag = false )
    {
        return self::get_error_static_instance()->reset_warnings( $tag );
    }

    /**
     * Reset instance error
     * @return void
     */
    public function reset_error()
    {
        $this->error_no = self::ERR_OK;
        $this->error_msg = '';
        $this->error_simple_msg = '';
        $this->error_debug_msg = '';
    }

    /**
     * Reset error of static instance
     * @return void
     */
    public static function st_reset_error()
    {
        self::get_error_static_instance()->reset_error();
    }

    /**
     * @param array $err_arr
     * @return array
     */
    public static function arr_reset_error( $err_arr )
    {
        if( empty( $err_arr ) || !is_array( $err_arr ) )
            $err_arr = [];

        return array_merge( $err_arr, self::default_error_array() );
    }

    /**
     * @return array Returns default error array structure with default values (no error)
     */
    public static function default_error_array()
    {
        return [
            'error_no' => self::ERR_OK,
            'error_msg' => '',
            'error_simple_msg' => '',
            'error_debug_msg' => '',
            'display_error' => '',
        ];
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
     * @return string Always returns short version error message
     */
    public function get_simple_error_message()
    {
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
     * @param int|bool $force_error_code
     *
     * @return bool
     */
    public function copy_error( $obj, $force_error_code = false )
    {
        if( empty( $obj ) || !($obj instanceof self)
         || !($error_arr = $obj->get_error())
         || !is_array( $error_arr ) )
            return false;

        $this->error_no = $error_arr['error_no'];
        $this->error_msg = $error_arr['error_msg'];
        $this->error_simple_msg = $error_arr['error_simple_msg'];
        $this->error_debug_msg = $error_arr['error_debug_msg'];

        if( $force_error_code !== false )
            $this->error_no = (int)$force_error_code;

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
        if( empty( $error_arr ) || !is_array( $error_arr )
         || !isset( $error_arr['error_no'] ) || !isset( $error_arr['error_msg'] )
         || !isset( $error_arr['error_simple_msg'] ) || !isset( $error_arr['error_debug_msg'] ) )
            return false;

        $this->error_no = (int)$error_arr['error_no'];
        $this->error_msg = $error_arr['error_msg'];
        $this->error_simple_msg = $error_arr['error_simple_msg'];
        $this->error_debug_msg = $error_arr['error_debug_msg'];

        if( $force_error_code !== false )
            $this->error_no = (int)$force_error_code;

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

    public static function st_get_simple_error_message()
    {
        return self::get_error_static_instance()->get_simple_error_message();
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

    public static function arr_get_simple_error_message( $err_arr )
    {
        $err_arr = self::validate_error_arr( $err_arr );

        return $err_arr['error_simple_msg'];
    }

    public static function arr_change_error_message( $err_arr, $error_msg, $error_debug_msg = '' )
    {
        $err_arr = self::validate_error_arr( $err_arr );

        if( empty( $error_debug_msg ) )
            $error_debug_msg = $error_msg;

        $err_arr['error_debug_msg'] = $error_debug_msg;
        $err_arr['error_simple_msg'] = $error_msg;

        return $err_arr;
    }

    public static function arr_merge_error_to_array( $source_error_arr, $error_arr )
    {
        $source_error_arr = self::validate_error_arr( $source_error_arr );
        $error_arr = self::validate_error_arr( $error_arr );

        if( $error_arr['error_msg'] !== '' )
            $source_error_arr['error_msg'] .= ($source_error_arr['error_msg']!==''?"\n\n":'').$error_arr['error_msg'];
        if( $error_arr['error_simple_msg'] !== '' )
            $source_error_arr['error_simple_msg'] .= ($source_error_arr['error_simple_msg']!==''?"\n\n":'').$error_arr['error_simple_msg'];
        if( $error_arr['error_debug_msg'] !== '' )
            $source_error_arr['error_debug_msg'] .= ($source_error_arr['error_debug_msg']!==''?"\n\n":'').$error_arr['error_debug_msg'];
        if( $error_arr['display_error'] !== '' )
            $source_error_arr['display_error'] .= ($source_error_arr['display_error']!==''?"\n\n":'').$error_arr['display_error'];

        if( !self::arr_has_error( $source_error_arr )
        && self::arr_has_error( $error_arr ) )
            $source_error_arr['error_no'] = (int)$error_arr['error_no'];

        return $source_error_arr;
    }

    public static function arr_append_error_to_array( $error_arr, $error_msg, $error_code = false )
    {
        if( empty( $error_msg ) )
            return false;

        $error_arr = self::validate_error_arr( $error_arr );

        if( $error_code === false )
            $error_code = self::arr_get_error_code( $error_arr );

        $append_error_arr = self::arr_set_error( $error_code, $error_msg );

        return self::arr_merge_error_to_array( $error_arr, $append_error_arr );
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
        return [
            'instance_error' => $this->get_error(),
        ];
    }

    public static function st_stack_error()
    {
        return [
            'static_error' => self::st_get_error(),
        ];
    }

    public function restore_errors( $errors_arr )
    {
        if( !empty( $errors_arr['instance_error'] )
        && ($instance_errors = self::validate_error_arr( $errors_arr['instance_error'] )) )
            $this->copy_error_from_array( $instance_errors );

        if( !empty( $errors_arr['static_error'] )
        && ($static_errors = self::validate_error_arr( $errors_arr['static_error'] )) )
            self::st_copy_error_from_array( $static_errors );
    }

    public static function st_restore_errors( $errors_arr )
    {
        if( !empty( $errors_arr['static_error'] )
        && ($static_errors = self::validate_error_arr( $errors_arr['static_error'] )) )
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
     *   @param bool $simple_messages Tells which set of messages to get (simple or debugging)
     *   @param string|bool $tag Check if we have warnings for provided tag (false by default)
     *   @return array|false Return array of warnings (all or for specified tag) or false if no warnings
     **/
    public function get_warnings( $simple_messages = true, $tag = false )
    {
        if( empty( $this->warnings_arr )
         || ($tag !== false && !isset( $this->warnings_arr[$tag] )) )
            return false;

        if( $tag === false )
            $warning_pool = $this->warnings_arr[self::WARNING_NOTAG];
        else
            $warning_pool = $this->warnings_arr[$tag];

        if( empty( $warning_pool ) || !is_array( $warning_pool ) )
            return [];

        $ret_warnings = [];
        foreach( $warning_pool as $warning_unit )
        {
            if( !is_array( $warning_unit )
             || empty( $warning_unit['warning_msg'] )
             || empty( $warning_unit['debug_msg'] ) )
                continue;

            $ret_warnings[] = ($simple_messages?$warning_unit['warning_msg']:$warning_unit['debug_msg']);
        }

        return $ret_warnings;
    }

    //! Return warnings for specified tag or all warnings
    /**
     *   Return all warnings array
     *
     *   @param bool $simple_messages Tells which set of messages to get (simple or debugging)
     *   @return array Return array of all warnings
     **/
    public function get_all_warnings( $simple_messages = true )
    {
        if( empty( $this->warnings_arr ) )
            return [];

        $ret_warnings = [];
        foreach( $this->warnings_arr as $tag => $warnings_arr )
        {
            if( !is_array( $warnings_arr ) )
                continue;

            foreach( $warnings_arr as $warning_unit )
            {
                if( !is_array( $warning_unit )
                 || empty( $warning_unit['warning_msg'] )
                 || empty( $warning_unit['debug_msg'] ) )
                    continue;

                $ret_warnings[] = ($simple_messages?$warning_unit['warning_msg']:$warning_unit['debug_msg']);
            }
        }

        return $ret_warnings;
    }

    public static function st_get_warnings( $simple_messages = true, $tag = false )
    {
        return self::get_error_static_instance()->get_warnings( $simple_messages, $tag );
    }

    public static function st_get_all_warnings( $simple_messages = true )
    {
        return self::get_error_static_instance()->get_all_warnings( $simple_messages );
    }

    //! \brief Returns function/method call backtrace
    /**
     *  Used for debugging calls to functions or methods.
     *
     *  @param int $lvl Tells from which level of backtrace should we cut trace (helps not showing calls to internal PHS_Error methods)
     *  @param null|int $limit Tells how many calls in backtrace to return
     *
     *  @return string Method will return a string representing function/method calls.
     */
    public function debug_call_backtrace( $lvl = 0, $limit = null )
    {
        if( $this->suppress_backtrace() )
            return '';

        $lvl++;
        if( !($err_info = @debug_backtrace())
         || !is_array( $err_info )
         || !($err_info = @array_slice( $err_info, $lvl ))
         || !is_array( $err_info ) )
            return '';

        if( $limit !== null )
        {
            $limit = (int)$limit;
            if( !($err_info = @array_slice( $err_info, 0, $limit ))
             || !is_array( $err_info ) )
                return '';
        }

        $backtrace = '';
        $err_info_len = count( $err_info );
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

            $backtrace = '#'.($err_info_len-$i).'. '.$trace_data['class'].$trace_data['type'].$trace_data['function'].'( '.$args_str.' ) - '.
                          $trace_data['file'].':'.$trace_data['line']."\n".$backtrace;
        }

        unset( $err_info );

        return $backtrace;
    }

    //! @brief Returns function/method call backtrace. Used for static calls
    /**
     * Used for debugging calls to functions or methods.
     * @param int $lvl Recurring level when backtracing
     * @return string Method will return a string representing function/method calls.
     */
    public static function st_debug_call_backtrace( $lvl = 0 )
    {
        return self::get_error_static_instance()->debug_call_backtrace( $lvl );
    }

    /**
     * @param null|bool $mode
     *
     * @return bool
     */
    public function throw_errors( $mode = null )
    {
        if( $mode === null )
            return $this->throw_errors;

        $this->throw_errors = !empty( $mode );

        return $this->throw_errors;
    }

    public static function st_throw_errors( $mode = null )
    {
        return self::get_error_static_instance()->throw_errors( $mode );
    }

    public function debugging_mode( $mode = null )
    {
        if( $mode === null )
            return $this->debugging_mode;

        $this->debugging_mode = !empty( $mode );

        return $this->debugging_mode;
    }

    public static function st_debugging_mode( $mode = null )
    {
        return self::get_error_static_instance()->debugging_mode( $mode );
    }

    public function suppress_backtrace( $mode = null )
    {
        if( $mode === null )
            return $this->suppress_backtrace;

        $this->suppress_backtrace = !empty( $mode );

        return $this->suppress_backtrace;
    }

    public static function st_suppress_backtrace( $mode = null )
    {
        return self::get_error_static_instance()->suppress_backtrace( $mode );
    }

    public static function get_error_static_instance()
    {
        static $error_instance = false;

        if( empty( $error_instance ) )
            $error_instance = new PHS_Error( self::ERR_OK, '', '', true );

        return $error_instance;
    }
}
