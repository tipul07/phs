<?php

@date_default_timezone_set( 'Europe/London' );

if( @function_exists( 'mb_internal_encoding' ) )
    @mb_internal_encoding( 'UTF-8' );

// Platform full absolute path
define( 'PHS_PATH', '/absolute/path/to/root/' );

// If no domain is defined for current request in config directory system will use PHS_DEFAULT_* values
define( 'PHS_DEFAULT_DOMAIN', 'domain.com' ); // only domain name (used to set cookies)
define( 'PHS_DEFAULT_PORT', '' ); // port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_DOMAIN_PATH', 'url/path/to/root/' ); // tells the path from domain to get to root URL of the platform

// Default database settings (these settings will be used when creating default database connection)
define( 'PHS_DB_DRIVER', 'mysqli' );
define( 'PHS_DB_HOSTNAME', 'localhost' );
define( 'PHS_DB_USERNAME', 'testdb' );
define( 'PHS_DB_PASSWORD', 'Fj4EAdeGuSyXJ737' );
define( 'PHS_DB_DATABASE', 'testdb' );
define( 'PHS_DB_PREFIX', '' );
define( 'PHS_DB_PORT', '3306' );
define( 'PHS_DB_TIMEZONE', date( 'P' ) );
define( 'PHS_DB_CHARSET', 'UTF8' );

// Default session settings
define( 'PHS_DEFAULT_SESSION_DIR', PHS_PATH.'sess/' );
define( 'PHS_DEFAULT_SESSION_NAME', 'PHS_SESS' );
// 0 to close session when browser closes...
define( 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME', 0 );
define( 'PHS_DEFAULT_SESSION_COOKIE_PATH', '/' );
// Session starts automatically if it is required a variable.
// If system gets to the point to start displaying something and this constant is set to true, session will be started before displaying
// It is important to start the session before sending headers, as it cannot be started once headers were sent to browser
define( 'PHS_DEFAULT_SESSION_AUTOSTART', false );
// END Default session settings

define( 'PHS_DEFAULT_THEME', 'default' );

// php binary executable full path (point this to your CLI executable)
define( 'PHP_EXEC', '/usr/bin/php' );

include_once( PHS_PATH.'bootstrap.inc.php' );

// Running in debugging mode?
PHS::st_debugging_mode( true );
// Should errors be thrown?
PHS::st_throw_errors( true );

if( PHS::st_debugging_mode() )
{
    // Make sure we get all errors if we are in debugging mode and set custom error handler
    error_reporting( -1 );
    ini_set( 'display_errors', true );
    ini_set( 'display_startup_errors', true );

    $old_error_handler = set_error_handler( array( 'PHS', 'error_handler' ) );
} else
{
    // Make sure we don't display errors if we'r not in debugging mode
    error_reporting( 0 );
    ini_set( 'display_errors', false );
    ini_set( 'display_startup_errors', false );
}
