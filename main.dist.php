<?php

// Version main,php was installed with. In case there are variables / definitions that change in future releases
// bootstrap.php will announce that main.php has to be updated
define( 'PHS_KNOWN_VERSION', '1.0.4.1' );

// Site build version
define( 'PHS_SITEBUILD_VERSION', '1.0.0' );

@date_default_timezone_set( 'Europe/London' );

if( @function_exists( 'mb_internal_encoding' ) )
    @mb_internal_encoding( 'UTF-8' );

// Platform full absolute path
define( 'PHS_PATH', '/absolute/path/to/root/' );

// Prety name of the site (will be displayed to visitors as site name) (eg. MyNiceSite.com)
define( 'PHS_DEFAULT_SITE_NAME', 'PoweredByPHS.com' );

// Where should Contact Us send emails (this email(s) might be shown to users) this can contain comma separated emails
define( 'PHS_CONTACT_EMAIL', 'contact@email.com' );

// If no domain is defined for current request in config directory system will use PHS_DEFAULT_* values
define( 'PHS_DEFAULT_COOKIE_DOMAIN', '.domain.com' ); // domain to set session cookie (could be .example.com serving all subdomains)
define( 'PHS_DEFAULT_DOMAIN', 'www.domain.com' ); // only domain name (used to set cookies)
define( 'PHS_DEFAULT_SSL_DOMAIN', PHS_DEFAULT_DOMAIN ); // If diffrent than "normal" domain (eg. secure.example.com)
define( 'PHS_DEFAULT_PORT', '' ); // port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_SSL_PORT', '' ); // https port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_DOMAIN_PATH', 'url/path/to/root/' ); // tells the path from domain to get to root URL of the platform

// Default database settings (these settings will be used when creating default database connection)
define( 'PHS_DB_DRIVER', 'mysqli' );
define( 'PHS_DB_HOSTNAME', 'localhost' );
define( 'PHS_DB_USERNAME', 'dbuser' );
define( 'PHS_DB_PASSWORD', 'dbpass' );
define( 'PHS_DB_DATABASE', 'dbdatabase' );
define( 'PHS_DB_PREFIX', '' );
define( 'PHS_DB_PORT', '3306' );
define( 'PHS_DB_TIMEZONE', date( 'P' ) );
define( 'PHS_DB_CHARSET', 'UTF8' );
define( 'PHS_DB_USE_PCONNECT', true );
define( 'PHS_DB_DRIVER_SETTINGS', @json_encode( array( 'sql_mode' => '-ONLY_FULL_GROUP_BY' ) ) );

// Controlling database library behaviour (if different than default one)
// if( !defined( 'PHS_DB_SILENT_ERRORS' ) )
//     define( 'PHS_DB_SILENT_ERRORS', false );
// if( !defined( 'PHS_DB_DIE_ON_ERROR' ) )
//     define( 'PHS_DB_DIE_ON_ERROR', true );
// if( !defined( 'PHS_DB_CLOSE_AFTER_QUERY' ) )
//     define( 'PHS_DB_CLOSE_AFTER_QUERY', true );

// Define other specific database settings (if required)
// $mysql_settings = array();
// $mysql_settings['driver'] = 'mysqli';
// $mysql_settings['host'] = '';
// $mysql_settings['user'] = '';
// $mysql_settings['password'] = '';
// $mysql_settings['database'] = '';
// $mysql_settings['prefix'] = '';
// $mysql_settings['port'] = '';
// $mysql_settings['timezone'] = date( 'P' );
// $mysql_settings['charset'] = 'UTF8';
// $mysql_settings['use_pconnect'] = true;
// $mysql_settings['driver_settings'] = array( 'sql_mode' => '-ONLY_FULL_GROUP_BY' );
//
// define( 'PHS_NEW_CONNECTION', 'db_new_connection' );
//
// if( !PHS_db::add_db_connection( PHS_NEW_CONNECTION, $mysql_settings ) )
// {
//    PHS_db::st_throw_error();
//    exit;
// }

// Default session settings
define( 'PHS_DEFAULT_SESSION_DIR', PHS_PATH.'sess/' );
define( 'PHS_DEFAULT_SESSION_NAME', 'PHS_SESS' );
// 0 to close session when browser closes... This is session lifetime, not how long user will be logged in
// We can save in session language or other details that should be available for a longer period
define( 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME', 2678400 ); // 31 days by default
define( 'PHS_DEFAULT_SESSION_COOKIE_PATH', '/' );
// Session starts automatically if it is required a variable.
// If system gets to the point to start displaying something and this constant is set to true, session will be started before displaying
// It is important to start the session before sending headers, as it cannot be started once headers were sent to browser
define( 'PHS_DEFAULT_SESSION_AUTOSTART', false );
// END Default session settings

// Default framework logging dir... (you can setup logs dir per domain in config/* files by defining PHS_LOGS_DIR)
define( 'PHS_FRAMEWORK_LOGS_DIR', PHS_PATH.'system/logs/' );

// Default framework uploads dir... (you can setup upload dir per domain in config/* files by defining PHS_UPLOADS_DIR)
define( 'PHS_FRAMEWORK_UPLOADS_DIR', PHS_PATH.'_uploads/' );

// Default framework assets dir... (you can setup assets dir per domain in config/* files by defining PHS_ASSETS_DIR)
define( 'PHS_FRAMEWORK_ASSETS_DIR', PHS_PATH.'assets/' );

// Default theme... (this is the fallback theme where template files are. Change this only if you know what you are doing!!!)
if( !defined( 'PHS_DEFAULT_THEME' ) )
    define( 'PHS_DEFAULT_THEME', 'default' );
if( !defined( 'PHS_THEME' ) )
    define( 'PHS_THEME', 'default' );

// Default crypting keys...
define( 'PHS_DEFAULT_CRYPT_KEY', '' );
global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;
// You should use _new_crypt_keys.php script to generate new crypting internal keys before using platform.
// Copy&paste result of the script in this array
// After you complete this step you can delete _new_crypt_keys.php script
$PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR = array(
);

// php binary executable full path (point this to your CLI executable)
define( 'PHP_EXEC', '/usr/bin/php' );

// Debugging mode?
define( 'PHS_DEBUG_MODE', true );
define( 'PHS_DEBUG_THROW_ERRORS', false );

include_once( PHS_PATH.'bootstrap.php' );

use \phs\PHS;
use \phs\PHS_ajax;
use \phs\libraries\PHS_Logger;

// Tell the system if it should use multi language feature
PHS::set_multi_language( true );

// After how many seconds will an Ajax URL expire (if user stays on page and javascript will request same URL)
PHS_ajax::checksum_timeout( 86400 );

// Loggin settings (overwritten from bootstrap.php)
PHS_Logger::logging_enabled( true );
PHS_Logger::log_channels( PHS_Logger::TYPE_DEF_ALL );
PHS_Logger::logging_dir( PHS_LOGS_DIR );
