<?php

if( (!defined( 'PHS_SETUP_FLOW' ) or !constant( 'PHS_SETUP_FLOW' ))
and !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS_Db;

if( !defined( 'PHS_DB_SILENT_ERRORS' ) )
    define( 'PHS_DB_SILENT_ERRORS', false );
if( !defined( 'PHS_DB_DIE_ON_ERROR' ) )
    define( 'PHS_DB_DIE_ON_ERROR', true );
if( !defined( 'PHS_DB_CLOSE_AFTER_QUERY' ) )
    define( 'PHS_DB_CLOSE_AFTER_QUERY', false );

// Define any common database connections (if required)
// if( !PHS_Db::db_drivers_init() )
// {
//     \phs\libraries\PHS_Db::st_throw_error();
// }
