<?php

if( !defined( 'PHS_VERSION' ) )
    exit;

define( 'PHS_DB_SILENT_ERRORS', false );
define( 'PHS_DB_DIE_ON_ERROR', true );
define( 'PHS_DB_CLOSE_AFTER_QUERY', true );
define( 'PHS_DB_USE_PCONNECT', true );

// Define any common database connections (if required)
//if( !PHS_db::db_drivers_init() )
//{
//    PHS_db::st_throw_error();
//}
