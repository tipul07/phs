<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS_db;
use \phs\libraries\PHS_Model;

function validate_ip( $ip )
{
    if( function_exists( 'filter_var' ) and defined( 'FILTER_VALIDATE_IP' ) )
        return filter_var( $ip, FILTER_VALIDATE_IP );

    if( !($ip_numbers = explode( '.', $ip ))
     or !is_array( $ip_numbers ) or count( $ip_numbers ) != 4 )
        return false;

    $parsed_ip = '';
    foreach( $ip_numbers as $ip_part )
    {
        $ip_part = intval( $ip_part );
        if( $ip_part < 0 or $ip_part > 255 )
            return false;

        $parsed_ip = ($parsed_ip!=''?'.':'').$ip_part;
    }

    return $parsed_ip;
}

function request_ip()
{
    $guessed_ip = '';
    if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
        $guessed_ip = validate_ip( $_SERVER['HTTP_CLIENT_IP'] );

    if( empty( $guessed_ip )
        and !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
        $guessed_ip = validate_ip( $_SERVER['HTTP_X_FORWARDED_FOR'] );

    if( empty( $guessed_ip ) )
        $guessed_ip = (!empty( $_SERVER['REMOTE_ADDR'] )?$_SERVER['REMOTE_ADDR']:'');

    return $guessed_ip;
}

//
// Database related functions
//

function db_supress_errors( $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
    {
        if( PHS_db::st_debugging_mode() )
            PHS_db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    $db_instance->suppress_errors();

    return true;
}

function db_restore_errors_state( $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
    {
        if( PHS_db::st_debugging_mode() )
            PHS_db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    $db_instance->restore_errors_state();

    return true;
}

function db_query_insert( $query, $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
    {
        if( PHS_db::st_debugging_mode() )
            PHS_db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    if( !($qid = $db_instance->query( $query, $connection )) )
    {
        if( $db_instance->display_errors() )
        {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        return 0;
    }

    $last_id = $db_instance->last_inserted_id();

    return $last_id;
}

function db_query( $query, $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
    {
        if( PHS_db::st_debugging_mode() )
            PHS_db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    if( !($qid = $db_instance->query( $query, $connection )) )
    {
        if( $db_instance->display_errors() )
        {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        return 0;
    }

    return $qid;
}

function db_fetch_assoc( $qid, $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
    {
        if( PHS_db::st_debugging_mode() )
            PHS_db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    return $db_instance->fetch_assoc( $qid );
}

function db_num_rows( $qid, $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
    {
        if( PHS_db::st_debugging_mode() )
            PHS_db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    return $db_instance->num_rows( $qid );
}

function db_query_count( $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return 0;

    return $db_instance->queries_number();
}

function db_quick_insert( $table_name, $insert_arr, $connection = false, $params = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return '';

    return $db_instance->quick_insert( $table_name, $insert_arr, $connection, $params );
}

function db_quick_edit( $table_name, $edit_arr, $connection = false, $params = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return '';

    return $db_instance->quick_edit( $table_name, $edit_arr, $connection, $params );
}

function db_escape( $fields, $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return false;

    return $db_instance->escape( $fields, $connection );
}

function db_last_id( $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return -1;

    return $db_instance->last_inserted_id();
}

function db_settings( $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return -1;

    return $db_instance->connection_settings( $connection );
}

function db_prefix( $connection = false )
{
    if( !($db_settings = db_settings( $connection ))
     or !is_array( $db_settings )
     or empty( $db_settings['prefix'] ) )
        return '';

    return $db_settings['prefix'];
}
//
// END Database related functions
//

function form_str( $str )
{
    return str_replace( '"', '&quot;', $str );
}

function make_sure_is_filename( $str )
{
    if( !is_string( $str ) )
        return false;

    return str_replace(
                array( '..', '/', '\\', '~', '<', '>', '|' ),
                array( '.',  '',  '',   '',  '',  '',  '' ),
            $str );
}

function seconds_passed( $str, $params = false )
{
    return time() - parse_db_date( $str, $params );
}

function validate_db_date_array( $date_arr )
{
    if( !is_array( $date_arr ) )
        return false;

    for( $i = 0; $i < 6; $i++ )
    {
        if( !isset( $date_arr[$i] ) )
            return false;
    }

    if(
        $date_arr[1] < 1 or $date_arr[1] > 12
     or $date_arr[2] < 1 or $date_arr[2] > 31
     or $date_arr[3] < 0 or $date_arr[3] > 23
     or $date_arr[4] < 0 or $date_arr[4] > 59
     or $date_arr[5] < 0 or $date_arr[5] > 59
        )
        return false;

    return true;
}

function is_db_date( $date, $params = false )
{
    if( is_string( $date ) )
        $date = trim( $date );

    if( empty( $date )
     or !is_string( $date )
     or strstr( $date, '-' ) === false )
        return false;

    if( empty( $params ) or !is_array( $params ) )
        $params = array();

    if( !isset( $params['validate_intervals'] ) )
        $params['validate_intervals'] = true;
    else
        $params['validate_intervals'] = (!empty( $params['validate_intervals'] )?true:false);

    if( strstr( $date, ' ' ) )
    {
        $d = explode( ' ', $date );
        $date_ = explode( '-', $d[0] );
        $time_ = explode( ':', $d[1] );
    } else
    {
        $date_ = explode( '-', $date );
        $time_ = array( 0, 0, 0 );
    }

    for( $i = 0; $i < 3; $i++ )
    {
        if( !isset( $date_[$i] )
         or !isset( $time_[$i] ) )
            return false;

        $date_[$i] = intval( $date_[$i] );
        $time_[$i] = intval( $time_[$i] );
    }

    $result_arr = array_merge( $date_, $time_ );
    if( !empty( $params['validate_intervals'] )
    and !validate_db_date_array( $result_arr ) )
        return false;

    return $result_arr;
}

function parse_db_date( $date, $params = false )
{
    if( empty( $params ) or !is_array( $params ) )
        $params = array();

    if( !isset( $params['validate_intervals'] ) )
        $params['validate_intervals'] = true;
    else
        $params['validate_intervals'] = (!empty( $params['validate_intervals'] )?true:false);

    if( is_array( $date ) )
    {
        for( $i = 0; $i < 6; $i++ )
        {
            if( !isset( $date[$i] ) )
                return 0;

            $date[$i] = intval( $date[$i] );
        }

        $date_arr = $date;

        if( !empty( $params['validate_intervals'] )
        and !validate_db_date_array( $date_arr ) )
            return 0;
    } elseif( is_string( $date ) )
    {
        if( !($date_arr = is_db_date( $date, $params )) )
            return 0;
    } else
        return 0;

    return @mktime( $date_arr[3], $date_arr[4], $date_arr[5], $date_arr[1], $date_arr[2], $date_arr[0] );
}

function empty_db_date( $date )
{
    return (empty( $date ) or $date == PHS_Model::DATETIME_EMPTY or $date == PHS_Model::DATE_EMPTY);
}

function validate_db_date( $date, $format = false )
{
    if( empty_db_date( $date ) )
        return PHS_Model::DATETIME_EMPTY;

    if( $format === false )
        $format = PHS_Model::DATETIME_DB;

    return @date( $format, parse_db_date( $date ) );
}

function prepare_data( $str )
{
    return str_replace( '\'', '\\\'', str_replace( '\\\'', '\'', $str ) );
}

function safe_url( $url )
{
    return str_replace( array( '?', '&', '#' ), array( '%3F', '%26', '%23' ), $url );
}

function from_safe_url( $url )
{
    return str_replace( array( '%3F', '%26', '%23' ), array( '?', '&', '#' ), $url );
}
