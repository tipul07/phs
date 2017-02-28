<?php

namespace phs\setup\libraries;

class PHS_Setup_utils
{
    public static function _detect_setup_path()
    {
        if( !($phs_setup_path = @dirname( __DIR__ )) )
            $phs_setup_path = '..';

        return $phs_setup_path.'/';
    }

    public static function _detect_setup_domain()
    {
        static $domain_settings_arr = false;

        if( !empty( $domain_settings_arr ) )
            return $domain_settings_arr;

        if( empty( $_SERVER ) )
            $_SERVER = array();

        // Domain
        $phs_setup_domain = '127.0.0.1';
        if( !empty( $_SERVER['SERVER_NAME'] )
        and !in_array( $_SERVER['SERVER_NAME'], array( 'localhost', '127.0.0.1' ) ) )
            $phs_setup_domain = $_SERVER['SERVER_NAME'];
        elseif( !empty( $_SERVER['SERVER_ADDR'] )
            and !in_array( $_SERVER['SERVER_ADDR'], array( '127.0.0.1', '::1' ) ) )
            $phs_setup_domain = $_SERVER['SERVER_ADDR'];

        $phs_setup_secured_request = false;
        if( isset( $_SERVER['HTTPS'] )
        and ($_SERVER['HTTPS'] == 'on' or $_SERVER['HTTPS'] == '1') )
            $phs_setup_secured_request = true;

        // Port...
        $phs_setup_port = '80';
        $phs_setup_ssl_port = '443';
        if( !empty( $_SERVER['SERVER_PORT'] )
        and !in_array( $_SERVER['SERVER_PORT'], array( '443', '80' ) ) )
        {
            if( $phs_setup_secured_request )
                $phs_setup_ssl_port = $_SERVER['SERVER_PORT'];
            else
                $phs_setup_port = $_SERVER['SERVER_PORT'];
        }

        $domain_path = '';
        if( !empty( $_SERVER['REQUEST_URI'] ) )
            $domain_path = @dirname( $_SERVER['REQUEST_URI'] );
        elseif( !empty( $_SERVER['SCRIPT_NAME'] ) )
            $domain_path = @dirname( @dirname( $_SERVER['SCRIPT_NAME'] ) );

        if( $phs_setup_port == '80' )
            $phs_setup_port = '';
        if( $phs_setup_ssl_port == '443' )
            $phs_setup_ssl_port = '';
        if( !empty( $domain_path ) )
            $domain_path = '/'.trim( $domain_path, '/' );

        $domain_settings_arr = array();
        $domain_settings_arr['domain'] = $phs_setup_domain;
        $domain_settings_arr['ssl_domain'] = $phs_setup_domain;
        $domain_settings_arr['cookie_domain'] = $phs_setup_domain;
        $domain_settings_arr['port'] = $phs_setup_port;
        $domain_settings_arr['ssl_port'] = $phs_setup_ssl_port;
        $domain_settings_arr['domain_path'] = $domain_path;

        return $domain_settings_arr;
    }

    public static function safe_escape_script( $script )
    {
        if( empty( $script ) or !is_string( $script )
         or preg_match( '@[^a-zA-Z0-9_\-]@', $script ) )
            return false;

        return $script;
    }

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
}
