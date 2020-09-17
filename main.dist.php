<?php

// Version main,php was installed with. In case there are variables / definitions that change in future releases
// bootstrap.php will announce that main.php has to be updated
define( 'PHS_KNOWN_VERSION', '1.1.3.5' );

// Site build version
define( 'PHS_SITEBUILD_VERSION', '{{PHS_SITEBUILD_VERSION}}' ); // Default: 1.0.0

@date_default_timezone_set( '{{PHS_DEFAULT_TIMEZONE}}' ); // Default: Europe/London

if( @function_exists( 'mb_internal_encoding' ) )
    @mb_internal_encoding( '{{PHS_MB_INTERNAL_ENCODING}}' ); // UTF-8

// Platform full absolute path (eg. /var/www/html/phs/) - ending with /
define( 'PHS_PATH', '{{PHS_PATH}}' );

// Prety name of the site (will be displayed to visitors as site name) (eg. MyNiceSite.com)
define( 'PHS_DEFAULT_SITE_NAME', '{{PHS_SITE_NAME}}' );

// Where should Contact Us send emails (this email(s) might be shown to users) this can contain comma separated emails
define( 'PHS_CONTACT_EMAIL', '{{PHS_CONTACT_EMAIL}}' );

// If no domain is defined for current request in config directory system will use PHS_DEFAULT_* values
define( 'PHS_DEFAULT_COOKIE_DOMAIN', '{{PHS_COOKIE_DOMAIN}}' ); // domain to set session cookie (could be .example.com serving all subdomains)
define( 'PHS_DEFAULT_DOMAIN', '{{PHS_DOMAIN}}' ); // only domain name (used to set cookies)
define( 'PHS_DEFAULT_SSL_DOMAIN', '{{PHS_SSL_DOMAIN}}' ); // If diffrent than "normal" domain (eg. secure.example.com)
define( 'PHS_DEFAULT_PORT', '{{PHS_PORT}}' ); // port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_SSL_PORT', '{{PHS_SSL_PORT}}' ); // https port (if applicable) if using default port don't put anything here
define( 'PHS_DEFAULT_DOMAIN_PATH', '{{PHS_DOMAIN_PATH}}' ); // tells the path from domain to get to root URL of the platform - ending with /

// Default database settings (these settings will be used when creating default database connection)
define( 'PHS_DB_DRIVER', 'mysqli' );
define( 'PHS_DB_HOSTNAME', '{{PHS_DB_HOSTNAME}}' );
define( 'PHS_DB_USERNAME', '{{PHS_DB_USERNAME}}' );
define( 'PHS_DB_PASSWORD', '{{PHS_DB_PASSWORD}}' );
define( 'PHS_DB_DATABASE', '{{PHS_DB_DATABASE}}' );
define( 'PHS_DB_PREFIX', '{{PHS_DB_PREFIX}}' ); // Database tables prefix (Default: '')
define( 'PHS_DB_PORT', '{{PHS_DB_PORT}}' ); // Default: 3306
define( 'PHS_DB_TIMEZONE', {{PHS_DB_TIMEZONE}} ); // Default: date( 'P' )
define( 'PHS_DB_CHARSET', {{PHS_DB_CHARSET}} ); // Default: 'UTF8'
define( 'PHS_DB_USE_PCONNECT', {{PHS_DB_PCONNECT}} ); // Default: true
define( 'PHS_DB_DRIVER_SETTINGS', {{PHS_DB_DRIVER_SETTINGS}} ); // Default: @json_encode( array( 'sql_mode' => '-ONLY_FULL_GROUP_BY' ) )

// Controlling database library behaviour (if different than default one)
// if( !defined( 'PHS_DB_SILENT_ERRORS' ) )
//     define( 'PHS_DB_SILENT_ERRORS', false );
// if( !defined( 'PHS_DB_DIE_ON_ERROR' ) )
//     define( 'PHS_DB_DIE_ON_ERROR', true );
// if( !defined( 'PHS_DB_CLOSE_AFTER_QUERY' ) )
//     define( 'PHS_DB_CLOSE_AFTER_QUERY', true );
// if( !defined( 'PHS_DB_USE_PCONNECT' ) )
//     define( 'PHS_DB_USE_PCONNECT', true );

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
define( 'PHS_DEFAULT_SESSION_DIR', {{PHS_SESSION_DIR}} ); // Default: PHS_PATH.'sess/'
define( 'PHS_DEFAULT_SESSION_NAME', '{{PHS_SESSION_NAME}}' ); // Default: PHS_SESS
// 0 to close session when browser closes... This is session lifetime, not how long user will be logged in
// We can save in session language or other details that should be available for a longer period
define( 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME', {{PHS_SESSION_COOKIE_LIFETIME}} ); // Default: 432000 (5 days)
define( 'PHS_DEFAULT_SESSION_COOKIE_PATH', {{PHS_SESSION_COOKIE_PATH}} ); // Default: '/'.trim( PHS_DEFAULT_DOMAIN_PATH, '/' )
// SameSite session cookie settings (can be None, Lax or Strict)
define( 'PHS_DEFAULT_SESSION_SAMESITE', '{{PHS_SESSION_SAMESITE}}' ); // Default: Lax
// Session starts automatically if it is required a variable.
// If system gets to the point to start displaying something and this constant is set to true, session will be started before displaying
// It is important to start the session before sending headers, as it cannot be started once headers were sent to browser
define( 'PHS_DEFAULT_SESSION_AUTOSTART', {{PHS_SESSION_AUTOSTART}} ); // Default: false
// END Default session settings

// Default framework logging dir... (you can setup logs dir per domain in config/* files by defining PHS_LOGS_DIR)
define( 'PHS_FRAMEWORK_LOGS_DIR', {{PHS_FRAMEWORK_LOGS_DIR}} ); // Default: PHS_PATH.'system/logs/'

// Default framework uploads dir... (you can setup upload dir per domain in config/* files by defining PHS_UPLOADS_DIR)
define( 'PHS_FRAMEWORK_UPLOADS_DIR', {{PHS_FRAMEWORK_UPLOADS_DIR}} ); // Default: PHS_PATH.'_uploads/'

// Default framework assets dir... (you can setup assets dir per domain in config/* files by defining PHS_ASSETS_DIR)
define( 'PHS_FRAMEWORK_ASSETS_DIR', {{PHS_FRAMEWORK_ASSETS_DIR}} ); // Default: PHS_PATH.'assets/'

// Default theme... (this is the fallback theme where template files are. Change this only if you know what you are doing!!!)
// You can setup a cascade of themes after boostrap.php is included (scroll down)
if( !defined( 'PHS_DEFAULT_THEME' ) )
    define( 'PHS_DEFAULT_THEME', '{{PHS_DEFAULT_THEME}}' ); // Default: default
if( !defined( 'PHS_THEME' ) )
    define( 'PHS_THEME', '{{PHS_THEME}}' );

// Default crypting keys...
define( 'PHS_DEFAULT_CRYPT_KEY', '{{PHS_CRYPT_KEY}}' );
global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;
// You should use _new_crypt_keys.php script to generate new crypting internal keys before using platform.
// Copy&paste result of the script in this array
// After you complete this step you can delete _new_crypt_keys.php script
// You can replace PHS_CRYPT_INTERNAL_KEYS with comma separated strings (see _new_crypt_keys.php). !!! THIS CANNOT BE EMPTY !!!
$PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR = array(
{{PHS_CRYPT_INTERNAL_KEYS}}
);

// php binary executable full path (point this to your CLI executable)
define( 'PHP_EXEC', '{{PHS_PHP_EXEC}}' ); // Default: /usr/bin/php

// Debugging mode?
define( 'PHS_DEBUG_MODE', {{PHS_DEBUG_MODE}} ); // Development: true, Production: false
define( 'PHS_DEBUG_THROW_ERRORS', {{PHS_DEBUG_THROW_ERRORS}} ); // Default: false

include_once( PHS_PATH.'bootstrap.php' );

use \phs\PHS;
use \phs\PHS_ajax;
use \phs\PHS_db;
use \phs\libraries\PHS_Logger;

// Set any cascading themes here...
// You don't have to add default or current theme here.
// Order in which resources will be searched in themes is current theme, cascade theme1, cascade theme2, ..., cascade themeX, default theme
// PHS::set_cascading_themes( array( 'theme1', 'theme2' ) );

// If you want to add themes to cascade (in a plugin bootstrap for example) you should use PHS::add_theme_to_cascading_themes();
// PHS::add_theme_to_cascading_themes( 'phs_reactjs' );
// PHS_CASCADING_THEMES should contain more lines with PHS::add_theme_to_cascading_themes( 'theme1' ); or be an empty string
{{PHS_CASCADING_THEMES}}

// Tell the system if it should use multi language feature
PHS::set_multi_language( {{PHS_MULTI_LANGUAGE}} ); // Default: true
PHS::set_utf8_conversion( {{PHS_LANGUAGE_UTF8_CONVERSION}} ); // Default: true

// After how many seconds will an Ajax URL expire (if user stays on page and javascript will request same URL)
PHS_ajax::checksum_timeout( {{PHS_AJAX_CHECKSUM_TIMEOUT}} ); // Default: 86400

// Loggin settings (overwritten from bootstrap.php)
PHS_Logger::logging_enabled( {{PHS_LOGGING_ENABLED}} ); // Default: true
PHS_Logger::log_channels( {{PHS_LOG_CHANNELS}} ); // Default: PHS_Logger::TYPE_DEF_ALL
PHS_Logger::logging_dir( {{PHS_LOGS_DIR}} ); // Default: PHS_LOGS_DIR

// Tell database drivers to try to restrict data sent to database based on field boundaries
// defined in table definition (if applicable)
PHS_db::check_db_fields_boundaries( {{PHS_CHECK_DB_FIELDS_BOUNDARIES}} ); // Default: true

// This can be changed with anything required (includes or function calls) for current platform
// Default: ''
{{PHS_ANYTHING_AFTER_BOOTSTRAP}}
