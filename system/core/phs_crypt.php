<?php

namespace phs;

use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Encdec;

//! @version 1.10

class PHS_Crypt extends PHS_Language
{
    static private $internal_keys = array();
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
    public static function set_internal_keys( $keys_arr = array() )
    {
        if( empty( $keys_arr ) or !is_array( $keys_arr ) )
            return false;

        self::$internal_keys = $keys_arr;
        return true;
    }

    /**
     * @param string $str
     * @param bool|array $params
     *
     * @return string
     */
    public static function quick_encode( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['use_base64'] ) )
            $params['use_base64'] = true;
        if( empty( $params['crypting_key'] ) or !is_string( $params['crypting_key'] ) )
            $params['crypting_key'] = self::crypting_key();
        if( empty( $params['internal_keys'] ) or !is_array( $params['internal_keys'] ) )
            $params['internal_keys'] = self::get_internal_keys();

        $enc_dec = new PHS_Encdec( $params['crypting_key'], (!empty( $params['use_base64'] )?true:false) );

        $enc_dec->set_internal_keys( $params['internal_keys'] );

        return $enc_dec->encrypt( $str );
    }

    /**
     * @param string $str
     * @param bool|array $params
     *
     * @return string
     */
    public static function quick_decode( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['use_base64'] ) )
            $params['use_base64'] = true;
        if( empty( $params['crypting_key'] ) or !is_string( $params['crypting_key'] ) )
            $params['crypting_key'] = self::crypting_key();
        if( empty( $params['internal_keys'] ) or !is_array( $params['internal_keys'] ) )
            $params['internal_keys'] = self::get_internal_keys();

        $enc_dec = new PHS_Encdec( $params['crypting_key'], (!empty( $params['use_base64'] )?true:false) );

        $enc_dec->set_internal_keys( $params['internal_keys'] );

        return $enc_dec->decrypt( $str );
    }
}

