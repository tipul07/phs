<?php

//! @version 1.00

class PHS_crypt extends PHS_Language
{
    static private $internal_keys = array();
    static private $crypt_key = '';

    function __construct()
    {
        parent::__construct();
    }

    static function crypting_key( $key = false )
    {
        if( $key === false )
            return self::$crypt_key;

        self::$crypt_key = $key;
        return self::$crypt_key;
    }

    static function get_internal_keys()
    {
        return self::$internal_keys;
    }

    static function set_internal_keys( array $keys_arr = array() )
    {
        if( empty( $keys_arr ) or !is_array( $keys_arr ) )
            return false;

        self::$internal_keys = $keys_arr;
        return true;
    }

    static function quick_encode( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['use_base64'] ) )
            $params['use_base64'] = true;
        if( empty( $params['crypting_key'] ) or !is_string( $params['crypting_key'] ) )
            $params['crypting_key'] = self::crypting_key();
        if( empty( $params['internal_keys'] ) or !is_array( $params['internal_keys'] ) )
            $params['internal_keys'] = self::get_internal_keys();

        $enc_dec = new PHS_encdec( $params['crypting_key'], (!empty( $params['use_base64'] )?true:false) );

        $enc_dec->set_internal_keys( $params['internal_keys'] );

        return $enc_dec->encrypt( $str );
    }

    static function quick_decode( $str, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['use_base64'] ) )
            $params['use_base64'] = true;
        if( empty( $params['crypting_key'] ) or !is_string( $params['crypting_key'] ) )
            $params['crypting_key'] = self::crypting_key();
        if( empty( $params['internal_keys'] ) or !is_array( $params['internal_keys'] ) )
            $params['internal_keys'] = self::get_internal_keys();

        $enc_dec = new PHS_encdec( $params['crypting_key'], (!empty( $params['use_base64'] )?true:false) );

        $enc_dec->set_internal_keys( $params['internal_keys'] );

        return $enc_dec->decrypt( $str );
    }
}

