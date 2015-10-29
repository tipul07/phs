<?php

//
// Database related functions
//
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

    return $db_instance->fetch_assoc( $qid );
}

function db_query_count( $connection = false )
{
    if( !($db_instance = PHS_db::db( $connection )) )
        return 0;

    return $db_instance->queries_number();
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
