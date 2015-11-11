<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

class PHS_Registry extends PHS_Language
{
    private static $data = array();

    function __construct()
    {
        parent::__construct();
    }

    public static function get_data( $key )
    {
        if( array_key_exists( $key, self::$data ) )
            return self::$data[$key];

        return null;
    }

    public static function set_full_data( $arr, $merge = false )
    {
        if( !is_array( $arr ) )
            return false;

        if( empty( $merge ) )
            self::$data = $arr;
        else
            self::$data = array_merge( self::$data, $arr );

        return true;
    }

    public static function set_data( $key, $val )
    {
        self::$data[$key] = $val;
    }

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
}

