<?php

use \phs\PHS_db;

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


function make_sure_is_filename( $str )
{
    if( !is_string( $str ) )
        return false;

    return str_replace(
                array( '..', '/', '~', '<', '>', '|' ),
                array( '.',  '',  '',  '',  '',  '' ),
            $str );
}

function parse_db_date( $str )
{
    $str = trim( $str );
    if( strstr( $str, ' ' ) )
    {
        $d = explode( ' ', $str );
        $date_ = explode( '-', $d[0] );
        $time_ = explode( ':', $d[1] );
    } else
        $date_ = explode( '-', $str );

    for( $i = 0; $i < 3; $i++ )
    {
        if( !isset( $date_[$i] ) )
            $date_[$i] = 0;
        if( isset( $time_ ) and !isset( $time_[$i] ) )
            $time_[$i] = 0;
    }

    if( !empty( $date_ ) and is_array( $date_ ) )
        foreach( $date_ as $key => $val )
            $date_[$key] = intval( $val );
    if( !empty( $time_ ) and is_array( $time_ ) )
        foreach( $time_ as $key => $val )
            $time_[$key] = intval( $val );

    if( isset( $time_ ) )
        return mktime( $time_[0], $time_[1], $time_[2], $date_[1], $date_[2], $date_[0] );
    else
        return mktime( 0, 0, 0, $date_[1], $date_[2], $date_[0] );
}

function empty_db_date( $date )
{
    return (empty( $date ) or $date == \phs\libraries\PHS_Model::DATETIME_EMPTY or $date == \phs\libraries\PHS_Model::DATE_EMPTY);
}
