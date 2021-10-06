<?php

namespace phs;

use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Encdec;

//! @version 1.10

class PHS_Crypt extends PHS_Language
{
    static private $internal_keys = [];
    static private $crypt_key = '';

    /**
     * @param bool|string $key
     *
     * @return bool|string
     */
    public static function crypting_key( $key = false )
    {
        if( $key === false )
            return self::$crypt_key;

        self::$crypt_key = $key;
        return self::$crypt_key;
    }

    /**
     * @return array
     */
    public static function get_internal_keys()
    {
        return self::$internal_keys;
    }

    /**
     * @param array $keys_arr
     *
     * @return bool
     */
    public static function set_internal_keys( $keys_arr = [] )
    {
        if( empty( $keys_arr ) || !is_array( $keys_arr ) )
            return false;

        self::$internal_keys = $keys_arr;
        return true;
    }

    /**
     * @param string $str
     * @param false|array $params
     *
     * @return false|string
     */
    public static function quick_encode( $str, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !isset( $params['use_base64'] ) )
            $params['use_base64'] = true;
        if( empty( $params['crypting_key'] ) || !is_string( $params['crypting_key'] ) )
            $params['crypting_key'] = self::crypting_key();
        if( empty( $params['internal_keys'] ) || !is_array( $params['internal_keys'] ) )
            $params['internal_keys'] = self::get_internal_keys();

        $enc_dec = new PHS_Encdec( $params['crypting_key'], !empty( $params['use_base64'] ), $params['internal_keys'] );

        if( $enc_dec->has_error() )
            return false;

        return $enc_dec->encrypt( $str );
    }

    /**
     * @param string $str
     * @param false|array $params
     *
     * @return false|string
     */
    public static function quick_decode( $str, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !isset( $params['use_base64'] ) )
            $params['use_base64'] = true;
        if( empty( $params['crypting_key'] ) || !is_string( $params['crypting_key'] ) )
            $params['crypting_key'] = self::crypting_key();
        if( empty( $params['internal_keys'] ) || !is_array( $params['internal_keys'] ) )
            $params['internal_keys'] = self::get_internal_keys();

        $enc_dec = new PHS_Encdec( $params['crypting_key'], !empty( $params['use_base64'] ), $params['internal_keys'] );

        if( $enc_dec->has_error() )
            return false;

        return $enc_dec->decrypt( $str );
    }
}

