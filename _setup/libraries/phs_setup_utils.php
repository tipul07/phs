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
