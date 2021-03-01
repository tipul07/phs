<?php

namespace phs\libraries;

//! \version 1.90

class PHS_Params
{
    const ERR_OK = 0, ERR_PARAMS = 1;

    const T_ASIS = 1, T_INT = 2, T_FLOAT = 3, T_ALPHANUM = 4, T_SAFEHTML = 5, T_NOHTML = 6, T_EMAIL = 7,
          T_REMSQL_CHARS = 8, T_ARRAY = 9, T_DATE = 10, T_URL = 11, T_BOOL = 12, T_NUMERIC_BOOL = 13, T_TIMESTAMP = 14;

    const FLOAT_PRECISION = 10;
    const REGEX_INT = '/^[+-]?\d+$/', REGEX_FLOAT = '/^[+-]?\d+\.?\d*$/',
          REGEX_EMAIL = '/^[a-zA-Z0-9]+[a-zA-Z0-9\._\-\+]*@[a-zA-Z0-9_-]+\.[a-zA-Z0-9\._-]+$/',
          REGEX_URL = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS';


    /**
     * @return array
     */
    public static function get_valid_types()
    {
        return [
            self::T_ASIS, self::T_INT, self::T_FLOAT, self::T_ALPHANUM, self::T_SAFEHTML, self::T_NOHTML, self::T_EMAIL,
            self::T_REMSQL_CHARS, self::T_ARRAY, self::T_DATE, self::T_URL, self::T_BOOL, self::T_NUMERIC_BOOL, self::T_TIMESTAMP,
        ];
    }

    /**
     * @param int $type
     *
     * @return bool
     */
    public static function valid_type( $type )
    {
        return in_array( (int)$type, self::get_valid_types(), true );
    }

    /**
     * @param int|float|string $val
     * @param int $type
     * @param false|array $extra
     *
     * @return bool
     */
    public static function check_type( $val, $type, $extra = false )
    {
        $type = (int)$type;
        switch( $type )
        {
            default:
                return true;
            break;

            case self::T_INT:
                if( preg_match( self::REGEX_INT, $val ) )
                    return true;
            break;

            case self::T_FLOAT:
                if( preg_match( self::REGEX_FLOAT, $val ) )
                    return true;
            break;

            case self::T_ALPHANUM:
                if( ctype_alnum( $val ) )
                    return true;
            break;

            case self::T_EMAIL:
                if( preg_match( self::REGEX_EMAIL, $val ) )
                    return true;
            break;

            case self::T_DATE:
                if( !empty( $val ) && @strtotime( $val ) !== false )
                    return true;
            break;

            case self::T_TIMESTAMP:
                if( !empty( $val )
                 && (is_numeric( $val ) || @strtotime( $val ) !== false) )
                    return true;
            break;

            case self::T_URL:
                if( preg_match( self::REGEX_URL, $val ) )
                    return true;
            break;
        }

        return false;
    }

    /**
     * @param mixed $val
     * @param int $type
     * @param bool|array $extra
     *
     * @return mixed
     */
    public static function set_type( $val, $type, $extra = false )
    {
        if( $val === null )
            return null;

        if( empty( $extra ) || !is_array( $extra ) )
            $extra = [];

        if( empty( $extra['trim_before'] ) )
            $extra['trim_before'] = false;

        if( !empty( $extra['trim_before'] )
         && is_scalar( $val ) )
            $val = trim( $val );

        $type = (int)$type;
        switch( $type )
        {
            default:
            case self::T_ASIS:
                return $val;
            break;

            case self::T_INT:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( $val !== '' )
                    $val = (int)$val;

                return $val;
            break;

            case self::T_FLOAT:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( empty( $extra ) || !is_array( $extra ) )
                    $extra = [];

                if( empty( $extra['digits'] ) )
                    $extra['digits'] = self::FLOAT_PRECISION;

                if( $val !== '' )
                {
                    if( @function_exists( 'bcmul' ) )
                        $val = @bcmul( $val, 1, $extra['digits'] );
                    else
                        $val = @number_format( $val, $extra['digits'], '.', '' );

                    if( strpos( $val, '.' ) !== false )
                    {
                        $val = trim( $val, '0' );
                        if( substr( $val, -1 ) === '.' )
                            $val = substr( $val, 0, -1 );
                        if( substr( $val, 0, 1 ) === '.' )
                            $val = '0' . $val;
                    }

                    $val = (float)$val;
                }

                return $val;
            break;

            case self::T_ALPHANUM:
                return preg_replace( '/^([a-zA-Z0-9]+)$/', '$1', strip_tags( $val ) );
            break;

            case self::T_SAFEHTML:
                return htmlspecialchars( $val );
            break;

            case self::T_EMAIL:
            case self::T_NOHTML:
            case self::T_URL:
                return strip_tags( $val );
            break;

            case self::T_REMSQL_CHARS:
                return str_replace( [ '--', '\b', '\Z', '%' ], '', $val );
            break;

            case self::T_ARRAY:
                if( empty( $val ) || !is_array( $val ) )
                    return [];

                if( empty( $extra ) || !is_array( $extra ) )
                    $extra = [];

                if( empty( $extra['type'] ) )
                    $extra['type'] = self::T_ASIS;

                foreach( $val as $key => $vval )
                    $val[$key] = self::set_type( $vval, $extra['type'], $extra );

                return $val;
            break;

            case self::T_DATE:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( empty( $val ) || ($val = @strtotime( $val )) === false || $val === -1 )
                    $val = false;
                elseif( !empty( $extra['format'] ) )
                        $val = @date( $extra['format'], $val );

                return $val;
            break;

            case self::T_TIMESTAMP:
                if( empty( $extra['trim_before'] ) )
                    $val = trim( $val );

                if( empty( $val ) )
                    $val = 0;
                elseif( is_numeric( $val ) )
                    $val = (int)$val;
                elseif( ($val = @strtotime( $val )) === false || $val === -1 )
                    $val = 0;

                if( $val < 0 )
                    $val = 0;

                return $val;
            break;

            case self::T_BOOL:
            case self::T_NUMERIC_BOOL:
                if( is_string( $val ) )
                {
                    if( empty( $extra['trim_before'] ) )
                        $val = trim( $val );

                    $low_val = strtolower( $val );

                    if( $low_val === 'true' )
                        $val = true;
                    elseif( $low_val === 'false' )
                        $val = false;
                }

                if( $type === self::T_BOOL )
                    return (!empty( $val ));

                if( $type === self::T_NUMERIC_BOOL )
                    return (!empty( $val )?1:0);
            break;
        }

        return null;
    }

    /**
     * Obtain a variable from _GET, then check _POST if not found in _GET
     * @param string $v
     * @param int $type
     * @param bool|array $extra
     *
     * @return mixed|null
     */
    public static function _gp( $v, $type = self::T_ASIS, $extra = false )
    {
        if( !empty( $_POST ) && isset( $_POST[$v] ) )
            $var = $_POST[$v];
        elseif( !empty( $_GET ) && isset( $_GET[$v] ) )
            $var = $_GET[$v];
        else
            return null;

        return self::set_type( $var, $type, $extra );
    }

    /**
     * Obtain a variable from _POST, then check _GET if not found in _POST
     * @param string $v
     * @param int $type
     * @param bool|array $extra
     *
     * @return mixed|null
     */
    public static function _pg( $v, $type = self::T_ASIS, $extra = false )
    {
        if( !empty( $_POST ) && isset( $_POST[$v] ) )
            $var = $_POST[$v];
        elseif( !empty( $_GET ) && isset( $_GET[$v] ) )
            $var = $_GET[$v];
        else
            return null;

        return self::set_type( $var, $type, $extra );
    }

    /**
     * Checks _GET (g), _POST (p), _SESSION (s), _FILES (f), _COOKIE (c), _REQUEST (r), _ENV (e), _SERVER (v) arrays to find $v key in provided order in $from
     *
     * @param string $from Order in which to check arrays as string _GET (g), _POST (p), _SESSION (s), _FILES (f), _COOKIE (c), _REQUEST (r), _ENV (e), _SERVER (v)
     * @param string $v Key to be search in provided order in arrays
     * @param int $type
     * @param bool|array $extra
     *
     * @return mixed|null
     */
    public static function _var( $from, $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $from ) )
            return null;

        $from = strtolower( $from );
        while( !empty( $from[0] ) )
        {
            switch( $from[0] )
            {
                case 'g':
                    if( !empty( $_GET ) && isset( $_GET[$v] ) )
                        return self::_g( $v, $type, $extra );
                break;
                case 'p':
                    if( !empty( $_POST ) && isset( $_POST[$v] ) )
                        return self::_p( $v, $type, $extra );
                break;
                case 's':
                    if( !empty( $_SESSION ) && isset( $_SESSION[$v] ) )
                        return self::_s( $v, $type, $extra );
                break;
                case 'f':
                    if( !empty( $_FILES ) && isset( $_FILES[$v] ) )
                        return self::_f( $v );
                break;
                case 'c':
                    if( !empty( $_COOKIE ) && isset( $_COOKIE[$v] ) )
                        return self::_c( $v, $type, $extra );
                break;
                case 'r':
                    if( !empty( $_REQUEST ) && isset( $_REQUEST[$v] ) )
                        return self::_r( $v, $type, $extra );
                break;
                case 'e':
                    if( !empty( $_ENV ) && isset( $_ENV[$v] ) )
                        return self::_e( $v, $type, $extra );
                break;
                case 'v':
                    if( !empty( $_SERVER ) && isset( $_SERVER[$v] ) )
                        return self::_v( $v, $type, $extra );
                break;
            }

            $from = substr( $from, 1 );
        }

        return null;
    }

    public static function _g( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_GET ) || !isset( $_GET[$v] ) )
            return null;

        return self::set_type( $_GET[$v], $type, $extra );
    }

    public static function _p( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_POST ) || !isset( $_POST[$v] ) )
            return null;

        return self::set_type( $_POST[$v], $type, $extra );
    }

    /**
     * @param string $v
     *
     * @return array|null
     */
    public static function _f( $v )
    {
        if( empty( $_FILES )
         || !isset( $_FILES[$v] )
         || !isset( $_FILES[$v]['name'] )
         || $_FILES[$v]['name'] === '' )
            return null;

        return $_FILES[$v];
    }

    public static function _s( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_SESSION ) || !isset( $_SESSION[$v] ) )
            return null;

        return self::set_type( $_SESSION[$v], $type, $extra );
    }

    public static function _c( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_COOKIE ) || !isset( $_COOKIE[$v] ) )
            return null;

        return self::set_type( $_COOKIE[$v], $type, $extra );
    }

    public static function _r( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_REQUEST ) || !isset( $_REQUEST[$v] ) )
            return null;

        return self::set_type( $_REQUEST[$v], $type, $extra );
    }

    public static function _v( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_SERVER ) || !isset( $_SERVER[$v] ) )
            return null;

        return self::set_type( $_SERVER[$v], $type, $extra );
    }

    public static function _e( $v, $type = self::T_ASIS, $extra = false )
    {
        if( empty( $_ENV ) || !isset( $_ENV[$v] ) )
            return null;

        return self::set_type( $_ENV[$v], $type, $extra );
    }
}
