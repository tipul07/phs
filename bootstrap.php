<?php

    if( !defined( 'PHS_PATH' ) )
        exit;

    include_once( PHS_PATH.'system/functions.php' );

    define( 'PHS_VERSION', phs_version() );

global $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR;

if( !defined( 'PHS_DEFAULT_CRYPT_KEY' ) or !constant( 'PHS_DEFAULT_CRYPT_KEY' ) )
{
    echo 'You should generate first your crypting key and update main.php <em>PHS_DEFAULT_CRYPT_KEY</em> constant.';
    exit;
}

if( empty( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) or !is_array( $PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR ) )
{
    echo 'You should generate first your crypting keys and update main.php '.
         ' <em>$PHS_DEFAULT_CRYPT_INTERNAL_KEYS_ARR</em> array variable using <em>_new_crypt_keys.php</em> script.';
    exit;
}

phs_init_before_bootstrap();

include_once( PHS_LIBRARIES_DIR.'phs_error.php' );
include_once( PHS_LIBRARIES_DIR.'phs_language_container.php' );
include_once( PHS_LIBRARIES_DIR.'phs_language.php' );
include_once( PHS_LIBRARIES_DIR.'phs_registry.php' );
// Make sure we can use maintenance things anytime
include_once( PHS_CORE_DIR.'phs_maintenance.php' );
include_once( PHS_LIBRARIES_DIR.'phs_library.php' );
include_once( PHS_LIBRARIES_DIR.'phs_roles.php' );
include_once( PHS_LIBRARIES_DIR.'phs_instantiable.php' );
include_once( PHS_LIBRARIES_DIR.'phs_has_db_settings.php' );
include_once( PHS_LIBRARIES_DIR.'phs_has_db_registry.php' );
include_once( PHS_LIBRARIES_DIR.'phs_plugin.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model_base.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model_mysqli.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model_mongo.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model_trait_statuses.php' );
include_once( PHS_LIBRARIES_DIR.'phs_model_trait_record_types.php' );
include_once( PHS_LIBRARIES_DIR.'phs_controller.php' );
include_once( PHS_LIBRARIES_DIR.'phs_controller_index.php' );
include_once( PHS_LIBRARIES_DIR.'phs_controller_api.php' );
include_once( PHS_LIBRARIES_DIR.'phs_controller_admin.php' );
include_once( PHS_LIBRARIES_DIR.'phs_controller_background.php' );
include_once( PHS_LIBRARIES_DIR.'phs_action.php' );
include_once( PHS_LIBRARIES_DIR.'phs_api_action.php' );
include_once( PHS_LIBRARIES_DIR.'phs_contract.php' );
include_once( PHS_LIBRARIES_DIR.'phs_contract_list.php' );
include_once( PHS_LIBRARIES_DIR.'phs_encdec.php' );
include_once( PHS_LIBRARIES_DIR.'phs_db_interface.php' );
include_once( PHS_LIBRARIES_DIR.'phs_db_class.php' );
include_once( PHS_LIBRARIES_DIR.'phs_params.php' );
include_once( PHS_LIBRARIES_DIR.'phs_line_params.php' );
include_once( PHS_LIBRARIES_DIR.'phs_hooks.php' );
include_once( PHS_LIBRARIES_DIR.'phs_logger.php' );
include_once( PHS_LIBRARIES_DIR.'phs_notifications.php' );
include_once( PHS_LIBRARIES_DIR.'phs_utils.php' );
include_once( PHS_LIBRARIES_DIR.'phs_file_upload.php' );
include_once( PHS_LIBRARIES_DIR.'phs_paginator.php' );
include_once( PHS_LIBRARIES_DIR.'phs_paginator_action.php' );
include_once( PHS_LIBRARIES_DIR.'phs_paginator_exporter_library.php' );
include_once( PHS_LIBRARIES_DIR.'phs_autocomplete_action.php' );
include_once( PHS_CORE_DIR.'phs.php' );
include_once( PHS_CORE_DIR.'phs_db.php' );
include_once( PHS_CORE_DIR.'phs_session.php' );
include_once( PHS_CORE_DIR.'phs_crypt.php' );
include_once( PHS_CORE_VIEW_DIR.'phs_view.php' );
include_once( PHS_CORE_DIR.'phs_scope.php' );
include_once( PHS_CORE_DIR.'phs_bg_jobs.php' );
include_once( PHS_CORE_DIR.'phs_agent.php' );
include_once( PHS_CORE_DIR.'phs_ajax.php' );
include_once( PHS_CORE_DIR.'phs_api_base.php' );
include_once( PHS_CORE_DIR.'phs_api.php' );
// Used to manage big number of files (initialize repositories in plugin's phs_bootstrap_x.php files)
// Make sure you create repositories' directories in uploads dir and you don't initialize a LDAP repository directly in uploads dir - unless you know what you'r doing!!!)
// eg. if uploads directory is _uploads, create a repository directory first _uploads/r1 and use r1 as root of repository; don't use _uploads as root of repository
include_once( PHS_LIBRARIES_DIR.'phs_ldap.php' );


use \phs\PHS;
use \phs\PHS_Db;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Params;

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

// Assets directory
if( !defined( 'PHS_ASSETS_DIR' ) )
{
    if( defined( 'PHS_FRAMEWORK_ASSETS_DIR' ) )
        define( 'PHS_ASSETS_DIR', PHS_FRAMEWORK_ASSETS_DIR );
    else
        define( 'PHS_ASSETS_DIR', PHS_DEFAULT_ASSETS_DIR );
}

// Default logging settings (change if required in main.php)
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

// Tell system if it should use multi language...
// We will enable it for the moment (it can be changed in main or particular config file)
PHS::set_multi_language( true );

// Running in debugging mode?
PHS::st_debugging_mode( (defined( 'PHS_DEBUG_MODE' ) && PHS_DEBUG_MODE) );
// Should errors be thrown?
PHS::st_throw_errors( (defined( 'PHS_DEBUG_THROW_ERRORS' ) && PHS_DEBUG_THROW_ERRORS) );

PHS_Logger::logging_enabled( true );
PHS_Logger::log_channels( PHS_Logger::TYPE_DEF_ALL );
PHS_Logger::logging_dir( PHS_LOGS_DIR );

// Default scope settings... These are overwritten when running specific actions
PHS_Scope::default_scope( PHS_Scope::SCOPE_WEB );
if( defined( 'PHS_SCRIPT_SCOPE' )
 && ($script_scope = PHS_Scope::valid_constant_scope( PHS_SCRIPT_SCOPE )) )
    PHS_Scope::current_scope( $script_scope );

if( !PHS_Scope::current_scope_is_set() )
    PHS_Scope::current_scope( PHS_Scope::SCOPE_WEB );

PHS::init();

if( PHS::st_debugging_mode() )
{
    // Make sure we get all errors if we are in debugging mode and set custom error handler
    error_reporting( -1 );
    ini_set( 'display_errors', true );
    ini_set( 'display_startup_errors', true );

    $old_error_handler = @set_error_handler( array( '\phs\PHS', 'error_handler' ) );
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
$mysql_settings['driver'] = PHS_Db::DB_DRIVER_MYSQLI;
$mysql_settings['host'] = PHS_DB_HOSTNAME;
$mysql_settings['user'] = PHS_DB_USERNAME;
$mysql_settings['password'] = PHS_DB_PASSWORD;
$mysql_settings['database'] = PHS_DB_DATABASE;
$mysql_settings['prefix'] = PHS_DB_PREFIX;
$mysql_settings['port'] = PHS_DB_PORT;
$mysql_settings['timezone'] = PHS_DB_TIMEZONE;
$mysql_settings['charset'] = PHS_DB_CHARSET;
$mysql_settings['use_pconnect'] = PHS_DB_USE_PCONNECT;

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

PHS_Db::default_db_driver( PHS_Db::DB_DRIVER_MYSQLI );
PHS_Db::check_db_fields_boundaries( true );

if( !PHS_Db::add_db_connection( PHS_DB_DEFAULT_CONNECTION, $mysql_settings ) )
{
    echo 'ERROR ';
    exit;
}
//
// END Default database settings
//

//
// Request domain settings (if available)
//
if( ($custom_config_file = PHS::check_custom_config()) )
{
    include_once( $custom_config_file );
}
//
// END Request domain settings (if available)
//

PHS::define_constants();

// Init crypting system
include_once( PHS_SYSTEM_DIR.'crypt_init.php' );

// If we are in WEB update script check if we have update token
if( defined( 'PHS_IN_WEB_UPDATE_SCRIPT' ) && defined( 'PHS_INSTALLING_FLOW' )
 && constant( 'PHS_IN_WEB_UPDATE_SCRIPT' ) && constant( 'PHS_INSTALLING_FLOW' )
 && (
     !($pub_key = PHS_Params::_gp( PHS::PARAM_UPDATE_TOKEN_PUBKEY, PHS_Params::T_NOHTML ))
     || !($hash = PHS_Params::_gp( PHS::PARAM_UPDATE_TOKEN_HASH, PHS_Params::T_NOHTML ))
     || !PHS::validate_framework_update_params( $pub_key, $hash )
    ) )
{
    echo PHS::_t( 'Update token invalid.' );
    exit;
}

//
// Init database settings
// We don't create a connection with database server yet, we just instantiate database objects and
// validate database settings
//
include_once( PHS_SYSTEM_DIR.'database_init.php' );
//
// END Init database settings
//

define( 'PHS_FULL_PATH_WWW', PHS_DOMAIN.(PHS_PORT!==''?':':'').PHS_PORT.'/'.PHS_DOMAIN_PATH );
define( 'PHS_FULL_SSL_PATH_WWW', PHS_SSL_DOMAIN.(PHS_SSL_PORT!==''?':':'').PHS_SSL_PORT.'/'.PHS_DOMAIN_PATH );

define( 'PHS_HTTP', 'http://'.PHS_FULL_PATH_WWW );
define( 'PHS_HTTPS', 'https://'.PHS_FULL_SSL_PATH_WWW );

if( !($base_url = PHS::get_base_url()) )
    $base_url = '/';

define( 'PHS_SETUP_WWW', $base_url.'_setup/' );
define( 'PHS_PLUGINS_WWW', $base_url.'plugins/' );
define( 'PHS_CORE_LIBRARIES_WWW', $base_url.'system/core/libraries/' );

define( 'PHS_THEMES_WWW', $base_url.'themes/' );
define( 'PHS_LANGUAGES_WWW', $base_url.'languages/' );
define( 'PHS_UPLOADS_WWW', $base_url.'_uploads/' );

// most used functionalities defined as functions for quick access (doh...)
include_once( PHS_SYSTEM_DIR.'functions.php' );

// Init session
// !!!NOTE!!! When working with session variables you HAVE to use PHS_Session::_* (_g - get, _s - set and _d - delete)
// $_SESSION array will be overwritten by PHS_Session class and variables set directly in $_SESSION array will be lost
include_once( PHS_SYSTEM_DIR.'session_init.php' );

// Init language system
include_once( PHS_SYSTEM_DIR.'languages_init.php' );

/** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
if( !($plugins_model = PHS::load_model( 'plugins' )) )
{
    echo PHS::_t( 'ERROR Instantiating plugins model.' )."\n";
    if( PHS::st_debugging_mode() )
        PHS::var_dump( PHS::st_get_error(), array( 'max_level' => 5 ) );
    exit;
}

//
// Check if we are in install flow...
//
if( !defined( 'PHS_INSTALLING_FLOW' ) || !constant( 'PHS_INSTALLING_FLOW' ) )
{
    if( !($active_plugins = $plugins_model->get_all_active_plugins()) )
    {
        $plugins_model_err = $plugins_model->get_error();
        if( !$plugins_model->test_db_connection() )
        {
            echo PHS::_t( 'ERROR Connecting to database. Please check your database connection settings.' );

            if( PHS::arr_has_error( $plugins_model_err )
            and PHS::st_debugging_mode() )
                PHS::var_dump( $plugins_model_err, array( 'max_level' => 5 ) );
            exit;
        }

        $active_plugins = array();
    }

    if( empty( $active_plugins )
    and !$plugins_model->check_table_exists( array( 'table_name' => 'plugins' )) )
    {
        if( !@is_dir( PHS_SETUP_DIR ) )
            echo 'It seems you didn\'t run yet install script.';

        // If we have a main.php script it means platform was setup before
        // Don't redirect to setup script
        elseif( !@is_file( PHS_PATH.'main.php' ) )
            echo 'It seems plugins table is missing. Please check framework setup.';

        else
            @header( 'Location: '.PHS_SETUP_WWW );

        exit;
    }
} else
{
    if( !($active_plugins = $plugins_model->get_all_plugins()) )
        $active_plugins = array();

    echo 'Checking plugins module installation... ';

    if( !$plugins_model->check_install_plugins_db() )
    {
        echo PHS::_t( 'ERROR checking plugins model install:' )."\n";
        var_dump( $plugins_model->get_error() );
        exit;
    }
    echo PHS::_t( 'DONE' )."\n\n";
}

$bootstrap_scripts = array();
$bootstrap_scripts_numbers = array( 0, 10, 20, 30, 40, 50, 60, 70, 80, 90 );
// Make sure we have right order for keys in array
foreach( $bootstrap_scripts_numbers as $bootstrap_scripts_number_i )
    $bootstrap_scripts[$bootstrap_scripts_number_i] = array();

foreach( $active_plugins as $plugin_name => $plugin_db_arr )
{
    foreach( $bootstrap_scripts_numbers as $bootstrap_scripts_number_i )
    {
        if( @file_exists( PHS_PLUGINS_DIR.$plugin_name.'/phs_bootstrap_'.$bootstrap_scripts_number_i.'.php' ) )
            $bootstrap_scripts[$bootstrap_scripts_number_i][] = PHS_PLUGINS_DIR.$plugin_name.'/phs_bootstrap_'.$bootstrap_scripts_number_i.'.php';
    }
}

foreach( $bootstrap_scripts as $bootstrap_scripts_number_i => $bootstrap_scripts_arr )
{
    if( empty( $bootstrap_scripts_arr ) or !is_array( $bootstrap_scripts_arr ) )
        continue;

    foreach( $bootstrap_scripts_arr as $bootstrap_script )
    {
        include_once( $bootstrap_script );
    }
}

// Start language system
include_once( PHS_SYSTEM_DIR.'languages_start.php' );

PHS::trigger_hooks( PHS_Hooks::H_AFTER_BOOTSTRAP );

PHS::set_data( PHS::PHS_BOOTSTRAP_END_TIME, microtime( true ) );

if( version_compare( PHS_KNOWN_VERSION, PHS_VERSION ) )
{
    PHS_Notifications::add_warning_notice( PHS_Language::_t( 'PHS version changed from %s to %s. '.
                 'Make sure you change main.php file by comparing it with distribution version and in case there are changes recorded, please update your main.php file accordingly. '.
                 'After you are sure everything is updated, change PHS_KNOWN_VERSION constant in first lines of main.php file to %s.',
                                                             PHS_KNOWN_VERSION, PHS_VERSION, PHS_VERSION ) );
}
