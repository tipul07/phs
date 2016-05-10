<?php

define( 'PHS_VERSION', '1.0.0.3' );

define( 'PHS_DEFAULT_FULL_PATH_WWW', PHS_DEFAULT_DOMAIN.(PHS_DEFAULT_PORT!=''?':':'').PHS_DEFAULT_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH );

define( 'PHS_DEFAULT_HTTP', 'http://'.PHS_DEFAULT_FULL_PATH_WWW );
define( 'PHS_DEFAULT_HTTPS', 'http://'.PHS_DEFAULT_FULL_PATH_WWW );

// Root folders
define( 'PHS_CONFIG_DIR', PHS_PATH.'config/' );
define( 'PHS_SYSTEM_DIR', PHS_PATH.'system/' );
define( 'PHS_PLUGINS_DIR', PHS_PATH.'plugins/' );
define( 'PHS_LOGS_DIR', PHS_SYSTEM_DIR.'logs/' );

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
include_once( PHS_LIBRARIES_DIR.'phs_paginator.php' );
include_once( PHS_LIBRARIES_DIR.'phs_paginator_action.php' );
include_once( PHS_CORE_DIR.'phs.php' );
include_once( PHS_CORE_DIR.'phs_db.php' );
include_once( PHS_CORE_DIR.'phs_session.php' );
include_once( PHS_CORE_DIR.'phs_crypt.php' );
include_once( PHS_CORE_VIEW_DIR.'phs_view.php' );
include_once( PHS_CORE_DIR.'phs_scope.php' );
include_once( PHS_CORE_DIR.'phs_bg_jobs.php' );

use \phs\PHS;
use \phs\PHS_db;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Language;

// Default loggin settings (change if required in main.php)
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

define( 'PHS_HTTP', 'http://'.PHS_FULL_PATH_WWW );
define( 'PHS_HTTPS', 'http://'.PHS_FULL_PATH_WWW );

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
