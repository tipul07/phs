<?php

define( 'PHS_VERSION', '1.0.0' );

@date_default_timezone_set( 'Europe/London' );

if( @function_exists( 'mb_internal_encoding' ) )
    @mb_internal_encoding( 'UTF-8' );

// Platform full absolute path
define( 'PHS_PATH', '/data/phpprojects/test/' );

// If no domain is defined for current request in config directory system will use PHS_DEFAULT_* values
define( 'PHS_DEFAULT_DOMAIN', 'iasi.smart2pay.com' ); // only domain name (used to set cookies)
define( 'PHS_DEFAULT_PORT', '7020' ); // port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_DOMAIN_PATH', 'test/' ); // tells the path from domain to get to root URL of the platform

define( 'PHS_DEFAULT_FULL_PATH_WWW', PHS_DEFAULT_DOMAIN.(PHS_DEFAULT_PORT!=''?':':'').PHS_DEFAULT_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH );

define( 'PHS_DEFAULT_HTTP', 'http://'.PHS_DEFAULT_FULL_PATH_WWW );
define( 'PHS_DEFAULT_HTTPS', 'http://'.PHS_DEFAULT_FULL_PATH_WWW );

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


// Root folders
define( 'PHS_CONFIG_DIR', PHS_PATH.'config/' );
define( 'PHS_SYSTEM_DIR', PHS_PATH.'system/' );
define( 'PHS_PLUGINS_DIR', PHS_PATH.'plugins/' );

// Second level folders
define( 'PHS_CORE_DIR', PHS_SYSTEM_DIR.'core/' );
define( 'PHS_LIBRARIES_DIR', PHS_SYSTEM_DIR.'libraries/' );

define( 'PHS_CORE_MODEL_DIR', PHS_CORE_DIR.'model/' );
define( 'PHS_CORE_CONTROLLER_DIR', PHS_CORE_DIR.'controller/' );

// These paths will need a www pair, but after bootstrap
define( 'PHS_THEMES_DIR', PHS_PATH.'themes/' );
define( 'PHS_LANGUAGES_DIR', PHS_PATH.'languages/' );
define( 'PHS_DOWNLOADS_DIR', PHS_PATH.'downloads/' );

include_once( PHS_LIBRARIES_DIR.'phs_error.inc.php' );
include_once( PHS_LIBRARIES_DIR.'phs_language.inc.php' );
include_once( PHS_LIBRARIES_DIR.'phs_registry.inc.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model.inc.php' );
include_once( PHS_CORE_DIR. 'phs.inc.php' );
include_once( PHS_CORE_DIR. 'phs_db.inc.php' );
include_once( PHS_CORE_DIR. 'phs_session.inc.php' );

PHS::init();

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

//
// Default database settings
//
$mysql_settings = array();
$mysql_settings['driver'] = PHS_DB_DRIVER;
$mysql_settings['host'] = PHS_DB_HOSTNAME;
$mysql_settings['user'] = PHS_DB_USERNAME;
$mysql_settings['password'] = PHS_DB_PASSWORD;
$mysql_settings['database'] = PHS_DB_DATABASE;
$mysql_settings['prefix'] = PHS_DB_PREFIX;
$mysql_settings['port'] = PHS_DB_PORT;
$mysql_settings['timezone'] = PHS_DB_TIMEZONE;
$mysql_settings['charset'] = PHS_DB_CHARSET;

define( 'PHS_DB_DEFAULT_CONNECTION', 'db_default' );

if( !PHS_db::add_db_connection( PHS_DB_DEFAULT_CONNECTION, $mysql_settings ) )
{
    PHS_db::st_throw_error();
    exit;
}
//
// END Default database settings
//

//
// Request domain settings (if available)
//
if( ($request_full_host = PHS::get_data( PHS::REQUEST_FULL_HOST )) )
{
    if( @is_file( PHS_CONFIG_DIR.$request_full_host.'.inc.php' ) )
    {
        include_once( PHS_CONFIG_DIR.$request_full_host.'.inc.php' );
    }
}
//
// END Request domain settings (if available)
//

PHS::define_constants();

//
// Init database settings
// We don't create a connection with database server yet, we just instantiate database objects and
// validate database settings
//
include_once( PHS_SYSTEM_DIR.'database_init.inc.php' );
//
// END Init database settings
//

define( 'PHS_FULL_PATH_WWW', PHS_DOMAIN.(PHS_PORT!=''?':':'').PHS_PORT.'/'.PHS_DOMAIN_PATH );

define( 'PHS_HTTP', 'http://'.PHS_FULL_PATH_WWW );
define( 'PHS_HTTPS', 'http://'.PHS_FULL_PATH_WWW );

if( !($base_url = PHS::get_base_url()) )
    $base_url = '/';

define( 'PHS_PLUGINS_WWW', $base_url.'plugins/' );

define( 'PHS_THEMES_WWW', $base_url.'themes/' );
define( 'PHS_LANGUAGES_WWW', $base_url.'languages/' );
define( 'PHS_DOWNLOADS_WWW', $base_url.'downloads/' );

// Init session
include_once( PHS_SYSTEM_DIR.'session_init.inc.php' );

// Init language system
include_once( PHS_SYSTEM_DIR.'languages_init.inc.php' );

// most used functionalities defined as functions for quick access (doh...)
include_once( PHS_SYSTEM_DIR.'functions.inc.php' );
