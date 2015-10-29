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
}

