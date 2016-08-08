<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS_db;

if( !defined( 'PHS_DB_SILENT_ERRORS' ) )
    define( 'PHS_DB_SILENT_ERRORS', false );
if( !defined( 'PHS_DB_DIE_ON_ERROR' ) )
    define( 'PHS_DB_DIE_ON_ERROR', true );
if( !defined( 'PHS_DB_CLOSE_AFTER_QUERY' ) )
    define( 'PHS_DB_CLOSE_AFTER_QUERY', true );
if( !defined( 'PHS_DB_USE_PCONNECT' ) )
    define( 'PHS_DB_USE_PCONNECT', true );

// Define any common database connections (if required)
// if( !PHS_db::db_drivers_init() )
// {
//     \phs\libraries\PHS_db::st_throw_error();
// }
