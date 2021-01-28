<?php

namespace phs\libraries;

if( (!defined( 'PHS_SETUP_FLOW' ) or !constant( 'PHS_SETUP_FLOW' ))
and !defined( 'PHS_VERSION' ) )
    exit;

class PHS_Registry extends PHS_Language
{
    private static $data = array();

    // Array with variables set for current view only. General information will be set using self::set_data()
    protected $_context = array();

    public static function get_full_data()
    {
        return self::$data;
    }

    public function get_full_context()
    {
        return $this->_context;
    }

    /**
     * @param $key
     *
     * @return mixed|null
     */
    public function get_context( $key )
    {
        if( array_key_exists( $key, $this->_context ) )
            return $this->_context[$key];

        return null;
    }

    /**
     * @param array|string $key
     * @param null|mixed $val
     *
     * @return bool
     */
    public function set_context( $key, $val = null )
    {
        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                $this->_context[$kkey] = $kval;
            }
        }

        if( !is_scalar( $key ) )
            return false;

        $this->_context[$key] = $val;

        return true;
    }

    /**
     * This is usually used when we have a numeric array holding records from database (eg. values returned by get_list() method on models.
     * If we want to get from $a[0]['name'], $a[1]['name'] => $a['name'][0], $a['name'][1] which is easier to work with (eg. implode all names)
     *
     * @param array $provided_arr Array to be walked
     * @param array $keys_arr Keys to be translated
     *
     * @return array Array with first keys keys from $keys_arr and second set of keys first set of keys from $strings_arr
     */
    public function extract_array_keys( $provided_arr, $keys_arr )
    {
        if( empty( $provided_arr ) or !is_array( $provided_arr ) )
            return array();

        if( empty( $keys_arr ) or !is_array( $keys_arr ) )
            return $provided_arr;

        $return_arr = array();
        foreach( $provided_arr as $key => $val_arr )
        {
            if( !is_array( $val_arr ) )
                continue;

            foreach( $keys_arr as $ret_key )
            {
                if( !isset( $provided_arr[$key][$ret_key] ) )
                    continue;

                $return_arr[$ret_key][$key] = $provided_arr[$key][$ret_key];
            }
        }

        return $return_arr;
    }

    /**
     * Translate an array to provided language. It is expected that $strings_arr is an array of arrays and $keys_arr are keys inside "leafs" arrays.
     * This is useful when defining statuses, types, etc arrays inside models which contains texts which normally should be translated.
     * Check $STATUSES_ARR found in built-in models to understand.
     *
     * @param array $strings_arr Array to be walked
     * @param array $keys_arr Keys to be translated
     * @param bool|string $lang Language in which we want array translated
     *
     * @return array Translated array
     */
    public function translate_array_keys( $strings_arr, $keys_arr, $lang = false )
    {
        if( empty( $strings_arr ) or !is_array( $strings_arr ) )
            return array();

        if( empty( $keys_arr ) or !is_array( $keys_arr ) )
            return $strings_arr;

        if( $lang === false )
            $lang = self::get_current_language();

        foreach( $strings_arr as $key => $val_arr )
        {
            if( !is_array( $val_arr ) )
                continue;

            foreach( $keys_arr as $trans_key )
            {
                if( !isset( $strings_arr[$key][$trans_key] )
                 or !is_string( $strings_arr[$key][$trans_key] ) )
                    continue;

                $strings_arr[$key][$trans_key] = $this->_pt( $strings_arr[$key][$trans_key], $lang );
            }
        }

        return $strings_arr;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public static function get_data( $key )
    {
        if( array_key_exists( $key, self::$data ) )
            return self::$data[$key];

        return null;
    }

    /**
     * @param array $arr
     * @param bool $merge
     *
     * @return bool
     */
    public static function set_full_data( $arr, $merge = false )
    {
        if( !is_array( $arr ) )
            return false;

        if( empty( $merge ) )
            self::$data = $arr;
        else
            self::$data = self::merge_array_assoc( self::$data, $arr );

        return true;
    }

    /**
     * @param string|array $key Key for which we want to change value or full array with key/value pairs
     * @param null|mixed $val Value of provided key or null in case we receive a full array of key/value pairs
     *
     * @return bool
     */
    public static function set_data( $key, $val = null )
    {
        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                self::$data[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key ) )
            return false;

        self::$data[$key] = $val;

        return true;
    }

    /**
     * @param string $str
     * @param string|array $args
     *
     * @return string
     */
    public static function sprintf_all( $str, $args )
    {
        if( !is_string( $str ) )
            return '';

        if( !is_string( $args ) and !is_array( $args ) )
            return $str;

        if( !is_array( $args ) )
            $args = array( $args );

        if( !($args_count = count( $args )) )
            return $str;

        $new_args = array();
        // in case we don't have numeric indexes for the args array
        $keys = array_keys( $args );
        // we will cycle through $args array
        if( !($perc_s = substr_count( $str, '%s' )) )
            $perc_s = 0;

        $keyi = 0;
        $new_args = array();
        while( $perc_s > 0 )
        {
            // safe...
            if( !isset( $args[$keys[$keyi]] ) )
                $keyi = 0;

            $new_args[] = $args[$keys[$keyi]];

            $keyi++;
            if( $keyi >= $args_count )
                $keyi = 0;

            $perc_s--;
        }

        if( empty( $new_args ) )
            return $str;

        return @vsprintf( $str, $new_args );
    }

    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    public static function merge_array_assoc( $arr1, $arr2 )
    {
        if( empty( $arr1 ) or !is_array( $arr1 ) )
            return $arr2;
        if( empty( $arr2 ) or !is_array( $arr2 ) )
            return $arr1;

        foreach( $arr2 as $key => $val )
        {
            $arr1[$key] = $val;
        }

        return $arr1;
    }

    /**
     * @param array $arr1
     * @param bool|array $params
     *
     * @return array
     */
    public static function unify_array_insensitive( $arr1, $params = false )
    {
        if( empty( $arr1 ) or !is_array( $arr1 ) )
            return array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['use_newer_values'] ) )
            $params['use_newer_values'] = true;
        else
            $params['use_newer_values'] = (!empty( $params['use_newer_values'] ));
        if( !isset( $params['trim_keys'] ) )
            $params['trim_keys'] = false;
        else
            $params['trim_keys'] = (!empty( $params['trim_keys'] ));

        $lower_to_raw_arr = array();
        foreach( $arr1 as $key => $val )
        {
            if( !empty( $params['trim_keys'] ) )
                $key = trim( $key );

            $lower_key = strtolower( $key );

            if( isset( $lower_to_raw_arr[$lower_key] ) )
            {
                if( empty( $params['use_newer_key_case'] ) )
                    $key = $lower_to_raw_arr[$lower_key];
                elseif( isset( $arr1[$lower_to_raw_arr[$lower_key]] ) )
                    unset( $arr1[$lower_to_raw_arr[$lower_key]] );
            }

            $arr1[$key] = $val;

            $lower_to_raw_arr[$lower_key] = $key;
        }

        return $arr1;
    }

    /**
     * @param array $arr1
     * @param bool|array $params
     *
     * @return array
     */
    public static function array_lowercase_keys( $arr1, $params = false )
    {
        if( empty( $arr1 ) or !is_array( $arr1 ) )
            return array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['trim_keys'] ) )
            $params['trim_keys'] = false;
        else
            $params['trim_keys'] = (!empty( $params['trim_keys'] ));

        $new_array = array();
        foreach( $arr1 as $key => $val )
        {
            if( !empty( $params['trim_keys'] ) )
                $key = trim( $key );

            $lower_key = strtolower( $key );

            $new_array[$lower_key] = $val;
        }

        return $new_array;
    }

    public static function merge_array_assoc_insensitive( $arr1, $arr2, $params = false )
    {
        if( empty( $arr1 ) or !is_array( $arr1 ) )
            return $arr2;
        if( empty( $arr2 ) or !is_array( $arr2 ) )
            return $arr1;

        return self::unify_array_insensitive( self::merge_array_assoc( $arr1, $arr2 ), $params );
    }

    public static function merge_array_assoc_recursive( $arr1, $arr2 )
    {
        if( empty( $arr1 ) or !is_array( $arr1 ) )
            return $arr2;
        if( empty( $arr2 ) or !is_array( $arr2 ) )
            return $arr1;

        foreach( $arr2 as $key => $val )
        {
            if( !array_key_exists( $key, $arr1 )
             or !is_array( $val ) )
                $arr1[$key] = $val;

            else
                $arr1[$key] = self::merge_array_assoc_recursive( $arr1[$key], $val );
        }

        return $arr1;
    }

    /**
     * @param array $arr Array to be validated for keys
     * @param array $definition_arr Array of key definitions. Keys not found in arr will not be returned in resulting array
     *
     * @return array If default array is not an array returns false else validated array is returned
     */
    public static function validate_array_keys_from_definition( $arr, $definition_arr )
    {
        if( empty( $definition_arr ) || !is_array( $definition_arr )
         || empty( $arr ) or !is_array( $arr ))
            return [];

        $new_arr = [];
        foreach( $arr as $key => $val )
        {
            if( !array_key_exists( $key, $definition_arr ) )
                continue;

            $new_arr[$key] = $val;
        }

        return $new_arr;
    }

    /**
     * @param array $arr Array to be validated
     * @param array $default_arr Array keys and default values which should be present in array to be validated
     *
     * @return array|bool If default array is not an array returns false else validated array is returned
     */
    public static function validate_array( $arr, $default_arr )
    {
        if( empty( $default_arr ) or !is_array( $default_arr ) )
            return false;

        if( empty( $arr ) or !is_array( $arr ) )
            $arr = array();

        foreach( $default_arr as $key => $val )
        {
            if( !array_key_exists( $key, $arr ) )
                $arr[$key] = $val;
        }

        return $arr;
    }

    /**
     * @param array $arr
     * @param array $default_arr
     *
     * @return array|bool
     */
    public static function validate_array_recursive( $arr, $default_arr )
    {
        if( empty( $default_arr ) or !is_array( $default_arr ) )
            return false;

        if( empty( $arr ) or !is_array( $arr ) )
            return $default_arr;

        foreach( $default_arr as $key => $val )
        {
            if( !array_key_exists( $key, $arr ) )
                $arr[$key] = $val;

            elseif( is_array( $val ) )
            {
                if( !is_array( $arr[$key] ) )
                    $arr[$key] = array();

                if( !empty( $val ) )
                    $arr[$key] = self::validate_array_recursive( $arr[$key], $val );
            }
        }

        return $arr;
    }

    /**
     * @param array $arr
     * @param array $default_arr
     *
     * @return array|bool
     */
    public static function validate_array_to_new_array( $arr, $default_arr )
    {
        if( empty( $default_arr ) or !is_array( $default_arr ) )
            return false;

        if( empty( $arr ) or !is_array( $arr ) )
            $arr = array();

        $new_array = array();
        foreach( $default_arr as $key => $val )
        {
            if( !array_key_exists( $key, $arr ) )
                $new_array[$key] = $val;
            else
                $new_array[$key] = $arr[$key];
        }

        return $new_array;
    }

    /**
     * @param array $arr
     * @param array $default_arr
     *
     * @return array|bool
     */
    public static function validate_array_to_new_array_recursive( $arr, $default_arr )
    {
        if( empty( $default_arr ) or !is_array( $default_arr ) )
            return false;

        if( empty( $arr ) or !is_array( $arr ) )
            $arr = array();

        $new_array = array();
        foreach( $default_arr as $key => $val )
        {
            if( !array_key_exists( $key, $arr ) )
                $new_array[$key] = $val;

            elseif( is_array( $val ) )
            {
                if( !is_array( $arr[$key] ) )
                {
                    $arr[$key] = array();
                    $new_array[$key] = array();
                }

                if( !empty( $val ) )
                    $new_array[$key] = self::validate_array_to_new_array_recursive( $arr[$key], $val );
            } else
                $new_array[$key] = $arr[$key];
        }

        return $new_array;
    }

    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    public static function array_merge_unique_values( $arr1, $arr2 )
    {
        if( empty( $arr1 ) or !is_array( $arr1 ) )
            $arr1 = array();
        if( empty( $arr2 ) or !is_array( $arr2 ) )
            $arr2 = array();

        $return_arr = array();
        foreach( $arr2 as $val )
        {
            if( !is_scalar( $val ) )
                continue;

            $return_arr[$val] = 1;
        }
        foreach( $arr1 as $val )
        {
            if( !is_scalar( $val ) )
                continue;

            $return_arr[$val] = 1;
        }

        return @array_keys( $return_arr );
    }

    /**
     * Checks if provided arrays values are same (order is not checked)
     * Values MUST BE SCALARS!!!
     *
     * @param array $arr1
     * @param array $arr2
     *
     * @return bool True is arrays hold same values (ignoring position in array)
     */
    public static function arrays_have_same_values( $arr1, $arr2 )
    {
        if( !is_array( $arr1 ) or !is_array( $arr2 ) )
            return false;

        if( empty( $arr1 ) and empty( $arr2 ) )
            return true;

        if( empty( $arr1 ) or empty( $arr2 )
         or count( $arr1 ) !== count( $arr2 ) )
            return false;

        $new_arr1 = array();
        foreach( $arr1 as $val )
        {
            if( !is_scalar( $val ) )
                return false;

            $new_arr1[$val] = true;
        }

        foreach( $arr2 as $val )
        {
            if( !is_scalar( $val )
             or empty( $new_arr1[$val] ) )
                return false;
        }

        return true;
    }

    /**
     * Tells if provided array has only numeric indexes
     *
     * @param array $arr Array to be checked
     * @param array|bool $params Parameters
     *
     * @return bool True is array has only integers as indexes, false if indexes are something else than integers
     */
    public static function array_has_numeric_indexes( $arr, $params = false )
    {
        if( empty( $arr ) or !is_array( $arr ) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        // How many checks to be done. 0 means no limit, traverse whole array
        if( empty( $params['max_iterations'] ) )
            $params['max_iterations'] = 0;
        else
            $params['max_iterations'] = (int)$params['max_iterations'];

        $knti = 0;
        foreach( $arr as $key => $junk )
        {
            if( !empty( $params['max_iterations'] )
            and $params['max_iterations'] <= $knti )
                break;

            if( (string)intval( $key ) !== (string)$key )
                return false;

            $knti++;
        }

        return true;
    }

    /**
     * @param string $str
     * @param bool|array $params
     *
     * @return string[]
     */
    public static function extract_strings_from_comma_separated( $str, $params = false )
    {
        if( !is_string( $str ) )
            return array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['trim_parts'] ) )
            $params['trim_parts'] = true;
        if( !isset( $params['dump_empty_parts'] ) )
            $params['dump_empty_parts'] = true;
        if( !isset( $params['to_lowercase'] ) )
            $params['to_lowercase'] = false;
        if( !isset( $params['to_uppercase'] ) )
            $params['to_uppercase'] = false;

        $str_arr = explode( ',', $str );
        $return_arr = array();
        foreach( $str_arr as $str_part )
        {
            if( !empty( $params['trim_parts'] ) )
                $str_part = trim( $str_part );

            if( !empty( $params['dump_empty_parts'] )
            and $str_part === '' )
                continue;

            if( !empty( $params['to_lowercase'] ) )
                $str_part = strtolower( $str_part );
            if( !empty( $params['to_uppercase'] ) )
                $str_part = strtoupper( $str_part );

            $return_arr[] = $str_part;
        }

        return $return_arr;
    }

    /**
     * Returns array of integers casted from comma separated values from provided string
     *
     * @param string $str String to be checked
     * @param bool|array $params Parameters
     *
     * @return array Array of casted integers
     */
    public static function extract_integers_from_comma_separated( $str, $params = false )
    {
        if( !is_string( $str ) )
            return array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['dump_empty_parts'] ) )
            $params['dump_empty_parts'] = true;

        $str_arr = explode( ',', $str );
        $return_arr = array();
        foreach( $str_arr as $int_part )
        {
            $int_part = (int)trim( $int_part );

            if( !empty( $params['dump_empty_parts'] )
            and empty( $int_part ) )
                continue;

            $return_arr[] = $int_part;
        }

        return $return_arr;
    }

    /**
     * Get all values in string that can be cast to non-empty integers.
     *
     * @param array $arr Array to be checked
     *
     * @return array
     */
    public static function extract_integers_from_array( $arr )
    {
        if( empty( $arr ) or !is_array( $arr ) )
            return array();

        $return_arr = array();
        foreach( $arr as $int_part )
        {
            $int_part = (int)trim( $int_part );

            if( empty( $int_part ) )
                continue;

            $return_arr[] = $int_part;
        }

        return $return_arr;
    }

    /**
     * Get all values in string that can be cast to non-empty integers.
     *
     * @param array $arr Array to be checked
     * @param array|bool $params Parameters
     *
     * @return array
     */
    public static function extract_strings_from_array( $arr, $params = false )
    {
        if( empty( $arr ) or !is_array( $arr ) )
            return array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['trim_parts'] ) )
            $params['trim_parts'] = true;
        if( !isset( $params['dump_empty_parts'] ) )
            $params['dump_empty_parts'] = true;
        if( !isset( $params['to_lowercase'] ) )
            $params['to_lowercase'] = false;
        if( !isset( $params['to_uppercase'] ) )
            $params['to_uppercase'] = false;

        $return_arr = array();
        foreach( $arr as $key => $str_part )
        {
            if( !empty( $params['trim_parts'] ) )
                $str_part = trim( $str_part );

            if( !empty( $params['dump_empty_parts'] )
            and $str_part === '' )
                continue;

            if( !empty( $params['to_lowercase'] ) )
                $str_part = strtolower( $str_part );
            if( !empty( $params['to_uppercase'] ) )
                $str_part = strtoupper( $str_part );

            if( is_string( $key ) )
                $return_arr[$key] = $str_part;
            else
                $return_arr[] = $str_part;
        }

        return $return_arr;
    }

    /**
     * Extract all key-values pairs from an array for which key is prefixed with a provided string
     *
     * @param array $arr Array with keys-values pairs
     * @param string $prefix String which is to be checked as prefix in keys
     * @param bool|array $params Optional parameters to the function
     *
     * @return array Resulting key-values pairs which are prefixed with provided string
     */
    public static function extract_keys_with_prefix( $arr, $prefix, $params = false )
    {
        if( empty( $arr ) or !is_array( $arr ) )
            return array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['remove_prefix_from_keys'] ) )
            $params['remove_prefix_from_keys'] = true;
        else
            $params['remove_prefix_from_keys'] = (!empty( $params['remove_prefix_from_keys'] )?true:false);

        if( !is_string( $prefix ) or $prefix === '' )
            return $arr;

        $return_arr = array();
        $prefix_len = strlen( $prefix );
        foreach( $arr as $key => $val )
        {
            if( substr( $key, 0, $prefix_len ) !== $prefix )
                continue;

            if( !empty( $params['remove_prefix_from_keys'] ) )
                $key = substr( $key, $prefix_len );

            $return_arr[$key] = $val;
        }

        return $return_arr;
    }
}

