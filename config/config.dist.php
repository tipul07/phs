<?php

define( 'PHS_DOMAIN', 'domain.only.com' );
// port if site is accessible with a port other than 80. If default port used (80) leave empty
define( 'PHS_PORT', 'site port' );
// if root URL is accessible with a path appended to domain name. Leave empty if domain points to root of platform
define( 'PHS_DOMAIN_PATH', '/site/path/' ); // ending in /

// What theme should domain use (don't define anything if default theme should be used
define( 'PHS_THEME', PHS_DEFAULT_THEME );

// Put a string here which will be the name of database connection used
// Define as many dabatase connections here
define( 'PHS_DB_CONNECTION', PHS_DB_DEFAULT_CONNECTION );

// Session settings (if not default ones)
define( 'PHS_SESSION_DIR', PHS_DEFAULT_SESSION_DIR );
define( 'PHS_SESSION_NAME', PHS_DEFAULT_SESSION_NAME );
// 0 to close session when browser closes...
define( 'PHS_SESSION_COOKIE_LIFETIME', PHS_DEFAULT_SESSION_COOKIE_LIFETIME );
define( 'PHS_SESSION_COOKIE_PATH', PHS_DEFAULT_SESSION_COOKIE_PATH );
// Session starts automatically if it is required a variable.
// If system gets to the point to start displaying something and this constant is set to true, session will be started before displaying
// It is important to start the session before sending headers, as it cannot be started once headers were sent to browser
define( 'PHS_SESSION_AUTOSTART', false );

// Define domain specific languages (if required)

// Define domain specific database settings (if required)
//$mysql_settings = array();
//$mysql_settings['driver'] = 'mysqli';
//$mysql_settings['host'] = '';
//$mysql_settings['user'] = '';
//$mysql_settings['password'] = '';
//$mysql_settings['database'] = '';
//$mysql_settings['prefix'] = '';
//$mysql_settings['port'] = '';
//$mysql_settings['timezone'] = date( 'P' );
//$mysql_settings['charset'] = 'UTF8';
//
//define( 'PHS_DB_CONNECTION', 'db_domain_default' );
//
//if( !PHS_db::add_db_connection( PHS_DB_DOMAIN_CONNECTION, $mysql_settings ) )
//{
//    PHS_db::st_throw_error();
//    exit;
//}
