<?php

    define( 'PHS_VERSION', '1.0.1.30' );

global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;

if( !defined( 'PHS_DEFAULT_CRYPT_KEY' ) or !constant( 'PHS_DEFAULT_CRYPT_KEY' )
 or empty( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR )
 or !is_array( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) )
{
    PHS_Notifications::add_warning_notice( PHS_Language::_t( 'You should generate first your crypting keys and update main.php <em>PHS_DEFAULT_CRYPT_KEY</em> constant '.
                     ' and <em>$PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR</em> array variable using <em>_new_crypt_keys.php</em> script.' ) );
    exit;
}

define( 'PHS_DEFAULT_FULL_PATH_WWW', PHS_DEFAULT_DOMAIN.(PHS_DEFAULT_PORT!=''?':':'').PHS_DEFAULT_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH );
define( 'PHS_DEFAULT_FULL_SSL_PATH_WWW', PHS_DEFAULT_DOMAIN.(PHS_DEFAULT_SSL_PORT!=''?':':'').PHS_DEFAULT_SSL_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH );

define( 'PHS_DEFAULT_HTTP', 'http://'.PHS_DEFAULT_FULL_PATH_WWW );
define( 'PHS_DEFAULT_HTTPS', 'https://'.PHS_DEFAULT_FULL_SSL_PATH_WWW );

// Root folders
define( 'PHS_CONFIG_DIR', PHS_PATH.'config/' );
define( 'PHS_SYSTEM_DIR', PHS_PATH.'system/' );
define( 'PHS_PLUGINS_DIR', PHS_PATH.'plugins/' );

// If logging dir is not setup in main.php or config/*, default location is in system/logs/
define( 'PHS_DEFAULT_LOGS_DIR', PHS_SYSTEM_DIR.'logs/' );

// If uploads dir is not setup in main.php or config/*, default location is in _uploads/
define( 'PHS_DEFAULT_UPLOADS_DIR', PHS_PATH.'_uploads/' );

// Second level folders
define( 'PHS_CORE_DIR', PHS_SYSTEM_DIR.'core/' );
define( 'PHS_LIBRARIES_DIR', PHS_SYSTEM_DIR.'libraries/' );

define( 'PHS_CORE_MODEL_DIR', PHS_CORE_DIR.'models/' );
define( 'PHS_CORE_CONTROLLER_DIR', PHS_CORE_DIR.'controllers/' );
define( 'PHS_CORE_VIEW_DIR', PHS_CORE_DIR.'views/' );
define( 'PHS_CORE_ACTION_DIR', PHS_CORE_DIR.'actions/' );
define( 'PHS_CORE_PLUGIN_DIR', PHS_CORE_DIR.'plugins/' );
define( 'PHS_CORE_SCOPE_DIR', PHS_CORE_DIR.'scopes/' );

// These paths will need a www pair, but after bootstrap
define( 'PHS_THEMES_DIR', PHS_PATH.'themes/' );
define( 'PHS_LANGUAGES_DIR', PHS_PATH.'languages/' );
define( 'PHS_DOWNLOADS_DIR', PHS_PATH.'downloads/' );

// name of directory where email templates are stored (either theme relative or plugin relative)
// eg. (themes/default/emails or plugins/accounts/templates/emails)
define( 'PHS_EMAILS_DIRS', 'emails' );

include_once( PHS_LIBRARIES_DIR.'phs_error.php' );
include_once( PHS_LIBRARIES_DIR.'phs_language.php' );
include_once( PHS_LIBRARIES_DIR.'phs_registry.php' );
include_once( PHS_LIBRARIES_DIR.'phs_library.php' );
include_once( PHS_LIBRARIES_DIR.'phs_roles.php' );
include_once( PHS_LIBRARIES_DIR.'phs_instantiable.php' );
include_once( PHS_LIBRARIES_DIR.'phs_signal_and_slot.php' );
include_once( PHS_LIBRARIES_DIR.'phs_has_db_settings.php' );
include_once( PHS_LIBRARIES_DIR.'phs_plugin.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model.php' );
include_once( PHS_LIBRARIES_DIR.'phs_controller.php' );
include_once( PHS_LIBRARIES_DIR.'phs_action.php' );
include_once( PHS_LIBRARIES_DIR.'phs_encdec.php' );
include_once( PHS_LIBRARIES_DIR.'phs_db_interface.php' );
include_once( PHS_LIBRARIES_DIR.'phs_params.php' );
include_once( PHS_LIBRARIES_DIR.'phs_line_params.php' );
include_once( PHS_LIBRARIES_DIR.'phs_hooks.php' );
include_once( PHS_LIBRARIES_DIR.'phs_logger.php' );
include_once( PHS_LIBRARIES_DIR.'phs_notifications.php' );
include_once( PHS_LIBRARIES_DIR.'phs_utils.php' );
include_once( PHS_LIBRARIES_DIR.'phs_file_upload.php' );
include_once( PHS_LIBRARIES_DIR.'phs_paginator.php' );
include_once( PHS_LIBRARIES_DIR.'phs_paginator_action.php' );
include_once( PHS_CORE_DIR.'phs.php' );
include_once( PHS_CORE_DIR.'phs_db.php' );
include_once( PHS_CORE_DIR.'phs_session.php' );
include_once( PHS_CORE_DIR.'phs_crypt.php' );
include_once( PHS_CORE_VIEW_DIR.'phs_view.php' );
include_once( PHS_CORE_DIR.'phs_scope.php' );
include_once( PHS_CORE_DIR.'phs_bg_jobs.php' );
include_once( PHS_CORE_DIR.'phs_agent.php' );
include_once( PHS_CORE_DIR.'phs_ajax.php' );
// Used to manage big number of files (initialize repostories in plugin's phs_bootstrap_x.php files)
// Make sure you create repositories' directories in uploads dir and you don't initialize a LDAP repository directly in uploads dir - unless you know what you'r doing!!!)
// eg. if uploads dfirectory is _uploads, create a repository directory first _uploads/r1 and use r1 as root of repository; don't use _uploads as root of repository
include_once( PHS_LIBRARIES_DIR.'phs_ldap.php' );

use \phs\PHS;
use \phs\PHS_db;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Language;

// These are special cases as there might be 3 definitions of same constant
// and framework will take first framework constant, then default constant if domain constant is not defined
// Uploads directory
if( !defined( 'PHS_UPLOADS_DIR' ) )
{
    if( defined( 'PHS_FRAMEWORK_UPLOADS_DIR' ) )
        define( 'PHS_UPLOADS_DIR', PHS_FRAMEWORK_UPLOADS_DIR );
    else
        define( 'PHS_UPLOADS_DIR', PHS_DEFAULT_UPLOADS_DIR );
}

// Default loggin settings (change if required in main.php)
if( !defined( 'PHS_LOGS_DIR' ) )
{
    if( defined( 'PHS_FRAMEWORK_LOGS_DIR' ) )
        define( 'PHS_LOGS_DIR', PHS_FRAMEWORK_LOGS_DIR );
    else
        define( 'PHS_LOGS_DIR', PHS_DEFAULT_LOGS_DIR );
}

// Site build version
if( !defined( 'PHS_SITEBUILD_VERSION' ) )
    define( 'PHS_SITEBUILD_VERSION', PHS_VERSION );

PHS_Logger::logging_enabled( true );
PHS_Logger::log_channels( PHS_Logger::TYPE_DEF_ALL );
PHS_Logger::logging_dir( PHS_LOGS_DIR );

// Default scope settings... These are overwritten when runing specific actions
PHS_Scope::default_scope( PHS_Scope::SCOPE_WEB );
PHS_Scope::current_scope( PHS_Scope::SCOPE_WEB );

PHS::init();

// Running in debugging mode?
PHS::st_debugging_mode( ((defined( 'PHS_DEBUG_MODE' ) and PHS_DEBUG_MODE)?true:false) );
// Should errors be thrown?
PHS::st_throw_errors( ((defined( 'PHS_DEBUG_THROW_ERRORS' ) and PHS_DEBUG_THROW_ERRORS)?true:false) );

if( PHS::st_debugging_mode() )
{
    // Make sure we get all errors if we are in debugging mode and set custom error handler
    error_reporting( -1 );
    ini_set( 'display_errors', true );
    ini_set( 'display_startup_errors', true );

    $old_error_handler = set_error_handler( array( '\phs\PHS', 'error_handler' ) );
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

$mysql_settings['driver_settings'] = array();
if( defined( 'PHS_DB_DRIVER_SETTINGS' ) )
    $mysql_settings['driver_settings'] = constant( 'PHS_DB_DRIVER_SETTINGS' );

if( !empty( $mysql_settings['driver_settings'] ) )
{
    if( is_string( $mysql_settings['driver_settings'] ) )
        $mysql_settings['driver_settings'] = @json_decode( $mysql_settings['driver_settings'], true );
}

if( !is_array( $mysql_settings['driver_settings'] ) )
    $mysql_settings['driver_settings'] = array();

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
    if( @is_file( PHS_CONFIG_DIR.$request_full_host.'.php' ) )
    {
        include_once( PHS_CONFIG_DIR.$request_full_host.'.php' );
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
include_once( PHS_SYSTEM_DIR.'database_init.php' );
//
// END Init database settings
//

define( 'PHS_FULL_PATH_WWW', PHS_DOMAIN.(PHS_PORT!=''?':':'').PHS_PORT.'/'.PHS_DOMAIN_PATH );
define( 'PHS_FULL_SSL_PATH_WWW', PHS_DOMAIN.(PHS_SSL_PORT!=''?':':'').PHS_SSL_PORT.'/'.PHS_DOMAIN_PATH );

define( 'PHS_HTTP', 'http://'.PHS_FULL_PATH_WWW );
define( 'PHS_HTTPS', 'https://'.PHS_FULL_SSL_PATH_WWW );

if( !($base_url = PHS::get_base_url()) )
    $base_url = '/';

define( 'PHS_PLUGINS_WWW', $base_url.'plugins/' );

define( 'PHS_THEMES_WWW', $base_url.'themes/' );
define( 'PHS_LANGUAGES_WWW', $base_url.'languages/' );
define( 'PHS_DOWNLOADS_WWW', $base_url.'downloads/' );

// Init session
include_once( PHS_SYSTEM_DIR.'session_init.php' );

// Init language system
include_once( PHS_SYSTEM_DIR.'languages_init.php' );

// Init crypting system
include_once( PHS_SYSTEM_DIR.'crypt_init.php' );

// most used functionalities defined as functions for quick access (doh...)
include_once( PHS_SYSTEM_DIR.'functions.php' );

// Walk thgrough plugins bootstrap scripts...
foreach( array( PHS_CORE_PLUGIN_DIR, PHS_PLUGINS_DIR ) as $bstrap_dir )
{
    if( ($bootstrap_scripts = @glob( $bstrap_dir . '*/phs_bootstrap_{0,10,20,30,40,50,60,70,80,90}.php', GLOB_BRACE ))
    and is_array( $bootstrap_scripts ) )
    {
        foreach( $bootstrap_scripts as $bootstrap_script )
        {
            include_once( $bootstrap_script );
        }
    }
}

PHS::trigger_hooks( PHS_Hooks::H_AFTER_BOOTSTRAP );

PHS::set_data( PHS::PHS_BOOTSTRAP_END_TIME, microtime( true ) );

if( version_compare( PHS_KNOWN_VERSION, PHS_VERSION ) )
{
    PHS_Notifications::add_warning_notice( PHS_Language::_t( 'PHS version changed from %s to %s. '.
                 'Make sure you change main.php file by comparing it with distribution version and in case there are changes recorded, please update your main.php file accordingly. '.
                 'After you are sure everything is updated, change PHS_KNOWN_VERSION constant in first lines of main.php file to %s.',
                                                             PHS_KNOWN_VERSION, PHS_VERSION, PHS_VERSION ) );
}
