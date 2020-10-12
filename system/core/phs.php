<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Controller;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Library;

final class PHS extends PHS_Registry
{
    const ERR_HOOK_REGISTRATION = 2000,
          ERR_LOAD_MODEL = 2001, ERR_LOAD_CONTROLLER = 2002, ERR_LOAD_ACTION = 2003, ERR_LOAD_VIEW = 2004, ERR_LOAD_PLUGIN = 2005,
          ERR_LOAD_SCOPE = 2006, ERR_ROUTE = 2007, ERR_EXECUTE_ROUTE = 2008, ERR_THEME = 2009, ERR_SCOPE = 2010,
          ERR_SCRIPT_FILES = 2011, ERR_LIBRARY = 2012,
          ERR_LOAD_CONTRACT = 2013;

    // How much will an update token be available in seconds
    const UPDATE_TOKEN_LIFETIME = 86400;
    // Update token parameter names
    const PARAM_UPDATE_TOKEN_HASH = '_phs_uth', PARAM_UPDATE_TOKEN_PUBKEY = '_phs_upk';

    const ACTION_DIR_ACTION_SEPARATOR = '__';

    const REQUEST_HOST_CONFIG = 'request_host_config', REQUEST_HOST = 'request_host', REQUEST_PORT = 'request_port', REQUEST_HTTPS = 'request_https',

          ROUTE_PLUGIN = 'route_plugin', ROUTE_CONTROLLER = 'route_controller', ROUTE_ACTION = 'route_action', ROUTE_ACTION_DIR = 'route_action_dir',

          CURRENT_THEME = 'c_theme', DEFAULT_THEME = 'd_theme', CASCADE_THEMES = 'cascade_themes',

          PHS_START_TIME = 'phs_start_time', PHS_BOOTSTRAP_END_TIME = 'phs_bootstrap_end_time', PHS_END_TIME = 'phs_end_time',

          // Generic current page settings (saved as array)
          PHS_PAGE_SETTINGS = 'phs_page_settings';

    const ROUTE_PARAM = '_route',
          ROUTE_DEFAULT_CONTROLLER = 'index',
          ROUTE_DEFAULT_ACTION = 'index';

    const RUNNING_ACTION = 'r_action', RUNNING_CONTROLLER = 'r_controller';

    private static $inited = false;
    private static $instance = false;
    private static $hooks = array();

    private static $_core_libraries_instances = array();

    private static $_INTERPRET_SCRIPT = 'index';
    private static $_BACKGROUND_SCRIPT = '_bg';
    private static $_AGENT_SCRIPT = '_agent_bg';
    private static $_AJAX_SCRIPT = '_ajax';
    private static $_API_SCRIPT = '_api';
    private static $_UPDATE_SCRIPT = '_update';

    public function __construct()
    {
        parent::__construct();

        self::init();
    }

    public static function get_distribution_plugins()
    {
        // All plugins that come with the framework (these will be installed by default)
        // Rest of plugins will be managed in plugins interface in admin interface
        return array( 'accounts', 'admin', 'messages', 'captcha', 'emails', 'notifications', 'backup', 'cookie_notice', 'bbeditor', 'mailchimp' );
    }

    public static function get_always_active_plugins()
    {
        // These plugins cannot be inacivated as they provide basic functionality for the platform
        return array( 'accounts', 'admin', 'captcha', 'emails', 'notifications' );
    }

    public static function get_core_models()
    {
        // !!! Don't change order of models here unless you know what you'r doing !!!
        // Models should be placed in this array after their dependencies (eg. bg_jobs depends on agent_jobs - it adds an agent job for timed bg jobs)
        return array( 'agent_jobs', 'bg_jobs', 'roles', 'api_keys' );
    }

    /**
     * Check what server receives in request
     */
    public static function init()
    {
        if( self::$inited )
            return;

        if( empty( $_SERVER ) )
            $_SERVER = array();

        self::reset_registry();

        self::set_data( self::PHS_START_TIME, microtime( true ) );
        self::set_data( self::PHS_BOOTSTRAP_END_TIME, 0 );
        self::set_data( self::PHS_END_TIME, 0 );

        $secure_request = self::detect_secure_request();

        if( empty( $_SERVER['SERVER_NAME'] ) )
            $request_host = '127.0.0.1';
        else
            $request_host = $_SERVER['SERVER_NAME'];

        if( empty( $_SERVER['SERVER_PORT'] ) )
        {
            if( $secure_request )
                $request_port = '443';
            else
                $request_port = '80';
        } else
            $request_port = $_SERVER['SERVER_PORT'];


        if( $secure_request )
            self::set_data( self::REQUEST_HTTPS, true );
        else
            self::set_data( self::REQUEST_HTTPS, false );

        self::set_data( self::REQUEST_HOST_CONFIG, self::get_request_host_config() );
        self::set_data( self::REQUEST_HOST, $request_host );
        self::set_data( self::REQUEST_PORT, $request_port );

        self::$inited = true;
    }

    /**
     * Checks if there is a config file to be included and return it's path. We don't include it here as we want to include in global scope...
     *
     * @param bool|string $config_dir Directory where we should check for config file
     * @return bool|string File to be included or false if nothing to include
     */
    public static function check_custom_config( $config_dir = false )
    {
        if( !self::$inited )
            self::init();

        if( empty( $config_dir ) )
        {
            if( !defined( 'PHS_CONFIG_DIR' ) )
                return false;

            $config_dir = PHS_CONFIG_DIR;
        }

        if( !($host_config = self::get_data( self::REQUEST_HOST_CONFIG ))
         or empty( $host_config['server_name'] ) )
            return false;

        if( empty( $host_config['server_port'] ) )
            $host_config['server_port'] = '';

        if( !empty( $host_config['server_port'] )
        and @is_file( $config_dir.$host_config['server_name'].'_'.$host_config['server_port'].'.php' ) )
            return $config_dir.$host_config['server_name'].'_'.$host_config['server_port'].'.php';

        if( @is_file( $config_dir.$host_config['server_name'].'.php' ) )
            return $config_dir.$host_config['server_name'].'.php';

        return false;
    }

    /**
     * @return bool Tells if current request is done on a secure connection (HTTPS or HTTP)
     */
    public static function detect_secure_request()
    {
        if( !empty( $_SERVER )
        and isset( $_SERVER['HTTPS'] )
        and ($_SERVER['HTTPS'] === 'on' or $_SERVER['HTTPS'] === '1' or $_SERVER['HTTPS'] === 1) )
            return true;

        return false;
    }

    /**
     * Returns request full hostname (based on this system will check for custom configuration files)
     *
     * @return array Returns array with request full hostname and port (based on this system will check for custom configuration files)
     */
    public static function get_request_host_config()
    {
        if( empty( $_SERVER ) )
            $_SERVER = array();

        if( empty( $_SERVER['SERVER_NAME'] ) )
            $server_name = 'default';
        else
            $server_name = trim( str_replace(
                array( '..', '/', '~', '<', '>', '|', '&', '%', '!', '`' ),
                array( '.',  '',  '',  '',  '',  '',  '',  '',  '',  '' ),
                $_SERVER['SERVER_NAME'] ) );

        if( empty( $_SERVER['SERVER_PORT'] )
         or in_array( $_SERVER['SERVER_PORT'], array( '80', '443' ) ) )
            $server_port = '';
        else
            $server_port = $_SERVER['SERVER_PORT'];

        return array(
            'server_name' => $server_name,
            'server_port' => $server_port,
        );
    }

    private static function reset_registry()
    {
        self::set_data( self::REQUEST_HOST_CONFIG, false );
        self::set_data( self::REQUEST_HOST, '' );
        self::set_data( self::REQUEST_PORT, '' );
        self::set_data( self::REQUEST_HTTPS, false );

        self::set_data( self::CURRENT_THEME, '' );
        self::set_data( self::DEFAULT_THEME, '' );

        self::set_data( self::RUNNING_ACTION, false );
        self::set_data( self::RUNNING_CONTROLLER, false );

        self::set_data( self::PHS_PAGE_SETTINGS, false );
    }

    public static function get_default_page_settings()
    {
        return array(
            'page_title' => '',
            'page_keywords' => '',
            'page_description' => '',
            // anything that is required in head tag
            'page_in_header' => '',
            'page_body_class' => '',
            'page_only_buffer' => false,
        );
    }

    /**
     * @param string|array|bool $key
     * @param mixed|null $val
     *
     * @return bool|mixed|null
     */
    public static function page_settings( $key = false, $val = null )
    {
        if( $key === false )
            return self::get_data( self::PHS_PAGE_SETTINGS );

        $def_settings = self::get_default_page_settings();
        $current_settings = self::get_data( self::PHS_PAGE_SETTINGS );
        if( $val === null )
        {
            if( is_array( $key ) )
            {
                foreach( $key as $kkey => $kval )
                {
                    if( array_key_exists( $kkey, $def_settings ) )
                        $current_settings[$kkey] = $kval;
                }

                self::set_data( self::PHS_PAGE_SETTINGS, $current_settings );

                return true;
            }

            elseif( is_string( $key )
            and ($page_settings = self::get_data( self::PHS_PAGE_SETTINGS ))
            and is_array( $page_settings )
            and array_key_exists( $key, $page_settings ) )
                return $page_settings[$key];

            return null;
        }

        if( array_key_exists( $key, $def_settings ) )
        {
            $current_settings[$key] = $val;

            self::set_data( self::PHS_PAGE_SETTINGS, $current_settings );

            return true;
        }

        return null;
    }

    public static function page_body_class( $css_class, $append = true )
    {
        if( !empty( $append ) )
        {
            if( !($existing_body_classes = self::page_settings( 'page_body_class' )) )
                $existing_body_classes = '';
        } else
            $existing_body_classes = '';

        return self::page_settings( 'page_body_class', trim( $existing_body_classes.' '.ltrim( $css_class ) ) );
    }

    public static function is_secured_request()
    {
        return (self::get_data( self::REQUEST_HTTPS )?true:false);
    }

    public static function prevent_session()
    {
        return (defined( 'PHS_PREVENT_SESSION' ) and constant( 'PHS_PREVENT_SESSION' ));
    }

    public static function user_logged_in( $force = false )
    {
        return ((($cuser_arr = self::current_user( $force )) and !empty( $cuser_arr['id'] ))?$cuser_arr:false);
    }

    public static function current_user( $force = false )
    {
        if( !($hook_args = self::_current_user_trigger( $force ))
         or empty( $hook_args['user_db_data'] ) or !is_array( $hook_args['user_db_data'] ) )
            return false;

        return $hook_args['user_db_data'];
    }

    public static function current_user_session( $force = false )
    {
        if( !($hook_args = self::_current_user_trigger( $force ))
         or empty( $hook_args['session_db_data'] ) or !is_array( $hook_args['session_db_data'] ) )
            return false;

        return $hook_args['session_db_data'];
    }

    public static function account_structure( $account_data )
    {
        $hook_args = PHS_Hooks::default_account_structure_hook_args();
        $hook_args['account_data'] = $account_data;

        if( !($hook_result = PHS_Hooks::trigger_account_structure( $hook_args ))
         or empty( $hook_result['account_structure'] )
         or !is_array( $hook_result['account_structure'] ) )
            return false;

        return $hook_result['account_structure'];
    }

    public static function current_user_password_expiration( $force = false )
    {
        if( !($hook_args = self::_current_user_trigger( $force ))
         or empty( $hook_args['password_expired_data'] ) or !is_array( $hook_args['password_expired_data'] ) )
            return PHS_Hooks::default_password_expiration_data();

        return self::validate_array_recursive( $hook_args['password_expired_data'], PHS_Hooks::default_password_expiration_data() );
    }

    private static function _current_user_trigger( $force = false )
    {
        static $hook_result = false;

        if( !empty( $hook_result )
        and empty( $force ) )
            return $hook_result;

        $hook_args = PHS_Hooks::default_user_db_details_hook_args();
        $hook_args['force_check'] = (!empty( $force )?true:false);

        if( !($hook_result = PHS_Hooks::trigger_current_user( $hook_args )) )
            $hook_result = false;

        return $hook_result;
    }

    /**
     * @param null|PHS_Action $action_obj
     * @return bool|PHS_Action
     */
    public static function running_action( PHS_Action $action_obj = null )
    {
        if( $action_obj === null )
            return self::get_data( self::RUNNING_ACTION );

        if( !($action_obj instanceof PHS_Action) )
            return false;

        return self::set_data( self::RUNNING_ACTION, $action_obj );
    }

    /**
     * @param null|PHS_Controller $controller_obj
     * @return bool|PHS_Controller
     */
    public static function running_controller( PHS_Controller $controller_obj = null )
    {
        if( $controller_obj === null )
            return self::get_data( self::RUNNING_CONTROLLER );

        if( !($controller_obj instanceof PHS_Controller) )
            return false;

        return self::set_data( self::RUNNING_CONTROLLER, $controller_obj );
    }

    /**
     * @param string $theme
     *
     * @return bool|string
     */
    public static function valid_theme( $theme )
    {
        self::st_reset_error();

        if( empty( $theme )
         or !is_string( $theme )
         or !($theme = PHS_Instantiable::safe_escape_theme_name( $theme ))
         or !@file_exists( PHS_THEMES_DIR . $theme )
         or !@is_dir( PHS_THEMES_DIR . $theme )
         or !@is_readable( PHS_THEMES_DIR . $theme ) )
        {
            self::st_set_error( self::ERR_THEME, self::_t( 'Theme %s doesn\'t exist or directory is not readable.', ($theme?$theme:'N/A') ) );
            return false;
        }

        return $theme;
    }

    public static function get_theme_language_paths( $theme = false )
    {
        self::st_reset_error();

        if( $theme === false )
            $theme = self::get_theme();

        if( !($theme = self::valid_theme( $theme )) )
            return false;

        if( !@file_exists( PHS_THEMES_DIR . $theme. '/' . PHS_Instantiable::LANGUAGES_DIR )
         or !@is_dir( PHS_THEMES_DIR . $theme. '/' . PHS_Instantiable::LANGUAGES_DIR )
         or !@is_readable( PHS_THEMES_DIR . $theme ) )
            return false;

        if( !@is_readable( PHS_THEMES_DIR . $theme. '/' . PHS_Instantiable::LANGUAGES_DIR ) )
        {
            self::st_set_error( self::ERR_THEME, self::_t( 'Theme (%s) languages directory is not readable.', $theme ) );
            return false;
        }

        return array(
            'path' => PHS_THEMES_DIR . $theme. '/' . PHS_Instantiable::LANGUAGES_DIR . '/',
            'www' => PHS_THEMES_WWW . $theme. '/' . PHS_Instantiable::LANGUAGES_DIR . '/',
        );
    }

    public static function set_theme( $theme )
    {
        if( !($theme = self::valid_theme( $theme )) )
            return false;

        self::set_data( self::CURRENT_THEME, $theme );

        if( !self::get_data( self::DEFAULT_THEME ) )
            self::set_data( self::DEFAULT_THEME, $theme );

        return true;
    }

    /**
     * @param string $theme
     *
     * @return bool
     */
    public static function set_defaut_theme( $theme )
    {
        if( !($theme = self::valid_theme( $theme )) )
            return false;

        self::set_data( self::DEFAULT_THEME, $theme );

        return true;
    }

    /**
     * Set a cascading themes array. You don't have to include default and current themes here.
     * When searching for templates, system will check current theme, then each cascading theme and lastly default theme.
     * @param array $themes_arr
     *
     * @return bool
     */
    public static function set_cascading_themes( $themes_arr )
    {
        if( !is_array( $themes_arr ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Please provide a themes array.' ) );
            return false;
        }

        $new_themes = array();
        foreach( $themes_arr as $theme )
        {
            if( !self::valid_theme( $theme ) )
                return false;

            $new_themes[$theme] = true;
        }

        if( empty( $new_themes )
         or !($themes_arr = @array_keys( $new_themes )) )
            $themes_arr = array();

        self::set_data( self::CASCADE_THEMES, $themes_arr );

        return true;
    }

    /**
     * @param string $theme
     *
     * @return bool
     */
    public static function add_theme_to_cascading_themes( $theme )
    {
        if( !self::valid_theme( $theme ) )
            return false;

        if( !($themes_arr = self::get_data( self::CASCADE_THEMES ))
         or !is_array( $themes_arr ) )
            $themes_arr = array();

        if( in_array( $theme, $themes_arr, true ) )
            return true;

        $themes_arr[] =  $theme;

        self::set_data( self::CASCADE_THEMES, $themes_arr );

        return true;
    }

    /**
     * @return bool
     */
    public static function resolve_theme()
    {
        // First set default so it doesn't get auto-set in set_theme() method
        if( !self::get_data( self::DEFAULT_THEME )
        and defined( 'PHS_DEFAULT_THEME' ) )
        {
            if( !self::set_defaut_theme( PHS_DEFAULT_THEME ) )
                return false;
        }

        if( !self::get_data( self::CURRENT_THEME )
        and defined( 'PHS_THEME' ) )
        {
            if( !self::set_theme( PHS_THEME ) )
                return false;
        }

        return true;
    }

    public static function get_theme()
    {
        $theme = self::get_data( self::CURRENT_THEME );

        if( !$theme )
        {
            if( !self::resolve_theme()
             or !($theme = self::get_data( self::CURRENT_THEME )) )
                return false;
        }

        return $theme;
    }

    public static function get_default_theme()
    {
        $theme = self::get_data( self::DEFAULT_THEME );

        if( !$theme )
        {
            if( !self::resolve_theme()
             or !($theme = self::get_data( self::DEFAULT_THEME )) )
                return false;
        }

        return $theme;
    }

    /**
     * Return an array with cascading themes
     * @return array
     */
    public static function get_cascading_themes()
    {
        if( !($themes = self::get_data( self::CASCADE_THEMES ))
         or !is_array( $themes ) )
            $themes = array();

        return $themes;
    }

    public static function domain_constants()
    {
        return array(
            // configuration constants
            'PHS_SITE_NAME' => 'PHS_DEFAULT_SITE_NAME',
            'PHS_COOKIE_DOMAIN' => 'PHS_DEFAULT_COOKIE_DOMAIN',
            'PHS_DOMAIN' => 'PHS_DEFAULT_DOMAIN',
            'PHS_SSL_DOMAIN' => 'PHS_DEFAULT_SSL_DOMAIN',
            'PHS_PORT' => 'PHS_DEFAULT_PORT',
            'PHS_SSL_PORT' => 'PHS_DEFAULT_SSL_PORT',
            'PHS_DOMAIN_PATH' => 'PHS_DEFAULT_DOMAIN_PATH',

            'PHS_THEME' => 'PHS_DEFAULT_THEME',

            'PHS_CRYPT_KEY' => 'PHS_DEFAULT_CRYPT_KEY',

            'PHS_DB_CONNECTION' => 'PHS_DB_DEFAULT_CONNECTION',

            'PHS_SESSION_DIR' => 'PHS_DEFAULT_SESSION_DIR',
            'PHS_SESSION_NAME' => 'PHS_DEFAULT_SESSION_NAME',
            'PHS_SESSION_COOKIE_LIFETIME' => 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME',
            'PHS_SESSION_COOKIE_PATH' => 'PHS_DEFAULT_SESSION_COOKIE_PATH',
            'PHS_SESSION_SAMESITE' => 'PHS_DEFAULT_SESSION_SAMESITE',
            'PHS_SESSION_AUTOSTART' => 'PHS_DEFAULT_SESSION_AUTOSTART',
        );
    }

    public static function define_constants()
    {
        $constants_arr = self::domain_constants();
        foreach( $constants_arr as $domain_constant => $default_constant )
        {
            if( defined( $domain_constant ) )
                continue;

            $constant_value = '';
            if( defined( $default_constant ) )
                $constant_value = constant( $default_constant );

            define( $domain_constant, $constant_value );
        }
    }

    /**
     * @param bool $force_https
     *
     * @return bool|string
     */
    public static function get_base_url( $force_https = false )
    {
        if( !empty( $force_https )
         or self::get_data( self::REQUEST_HTTPS ) )
        {
            // if domain settings are set
            if( defined( 'PHS_HTTPS' ) )
                return PHS_HTTPS;

            // if default domain settings are set
            if( defined( 'PHS_DEFAULT_HTTPS' ) )
                return PHS_DEFAULT_HTTPS;
        } else
        {
            // if domain settings are set
            if( defined( 'PHS_HTTP' ) )
                return PHS_HTTP;

            // if default domain settings are set
            if( defined( 'PHS_DEFAULT_HTTP' ) )
                return PHS_DEFAULT_HTTP;
        }

        return false;
    }

    public static function get_instance()
    {
        if( !empty( self::$instance ) )
            return self::$instance;

        self::$instance = new PHS();
        return self::$instance;
    }

    public static function extract_route()
    {
        if( empty( $_GET ) or empty( $_GET[self::ROUTE_PARAM] )
         or !is_string( $_GET[self::ROUTE_PARAM] ) )
            return '';

        return $_GET[self::ROUTE_PARAM];
    }

    /**
     * Parse request route. Route is something like:
     *
     * {plugin}/{controller}/{action} If controller is part of a plugin
     * or
     * {controller}/{action} If controller is a core controller
     * or
     * {plugin}-{action} Controller will be 'index'
     *
     * @param string|array|bool $route If a non empty string, method will try parsing provided route, if an array will try paring array, otherwise exract route from context
     * @param bool $use_short_names If we need short names for plugin, controller and action in returned keys (eg. p, c, a)
     * @return bool|array Returns true on success or false on error
     */
    public static function parse_route( $route = false, $use_short_names = false )
    {
        self::st_reset_error();

        $plugin = false;
        $controller = '';
        $action = '';
        $action_dir = '';
        $force_https = false;

        $route_parts = array();
        if( !empty( $route ) )
        {
            if( is_array( $route ) )
            {
                if( !empty( $route['plugin'] ) )
                    $plugin = $route['plugin'];
                elseif( !empty( $route['p'] ) )
                    $plugin = $route['p'];

                if( !empty( $route['controller'] ) )
                    $controller = $route['controller'];
                elseif( !empty( $route['c'] ) )
                    $controller = $route['c'];

                if( !empty( $route['action'] ) )
                    $action = $route['action'];
                elseif( !empty( $route['a'] ) )
                    $action = $route['a'];

                if( !empty( $route['action_dir'] ) )
                    $action_dir = $route['action_dir'];
                elseif( !empty( $route['ad'] ) )
                    $action_dir = $route['ad'];

                if( !empty( $route['force_https'] ) )
                    $force_https = true;
            } else
            {
                if( strpos( $route, '-' ) !== false )
                {
                    if( !($route_parts_tmp = explode( '-', $route, 2 ))
                     or empty( $route_parts_tmp[0] ) )
                    {
                        self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t obtain route.' ) );
                        return false;
                    }

                    $route_parts[0] = $route_parts_tmp[0];
                    $route_parts[1] = self::ROUTE_DEFAULT_CONTROLLER;
                    $route_parts[2] = (!empty( $route_parts_tmp[1] )?$route_parts_tmp[1]:'');
                } elseif( !($route_parts = explode( '/', $route, 3 )) )
                {
                    self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t obtain route.' ) );
                    return false;
                }

                $rp_count = count( $route_parts );
                if( $rp_count === 1 )
                {
                    $action = (!empty( $route_parts[0] )?trim( $route_parts[0] ):'');
                } elseif( $rp_count === 2 )
                {
                    $plugin = (!empty( $route_parts[0] )?trim( $route_parts[0] ):false);
                    $action = (!empty( $route_parts[1] )?trim( $route_parts[1] ):'');
                } elseif( $rp_count === 3 )
                {
                    $plugin = (!empty( $route_parts[0] )?trim( $route_parts[0] ):false);
                    $controller = (!empty( $route_parts[1] )?trim( $route_parts[1] ):'');
                    $action = (!empty( $route_parts[2] )?trim( $route_parts[2] ):'');
                }

                // Check action dir
                if( false !== strpos( $action, self::ACTION_DIR_ACTION_SEPARATOR )
                and ($action_parts = explode( self::ACTION_DIR_ACTION_SEPARATOR, $action, 2 ))
                and is_array( $action_parts )
                and count( $action_parts ) === 2 )
                {
                    $action_dir = (!empty( $action_parts[0] )?trim( $action_parts[0] ):'');
                    $action = (!empty( $action_parts[1] )?trim( $action_parts[1] ):'');
                }
            }
        }

        if( empty( $controller ) )
            $controller = self::ROUTE_DEFAULT_CONTROLLER;
        if( empty( $action ) )
            $action = self::ROUTE_DEFAULT_ACTION;

        if( ($plugin !== false and !($plugin = PHS_Instantiable::safe_escape_plugin_name( $plugin )))
         or !($controller = PHS_Instantiable::safe_escape_class_name( $controller ))
         or !($action = PHS_Instantiable::safe_escape_action_name( $action ))
         or (!empty( $action_dir ) and !($action_dir = PHS_Instantiable::safe_escape_instance_subdir( $action_dir )))
        )
        {
            self::st_set_error( self::ERR_ROUTE, self::_t( 'Bad route in request.' ) );
            return false;
        }

        if( $use_short_names )
            return array(
                'p' => $plugin,
                'c' => $controller,
                'ad' => $action_dir,
                'a' => $action,
                'force_https' => $force_https,
            );

        return array(
            'plugin' => $plugin,
            'controller' => $controller,
            'action_dir' => $action_dir,
            'action' => $action,
            'force_https' => $force_https,
        );
    }

    /**
     * @param string|array $route
     * @param bool|array $params
     *
     * @return array|bool
     */
    public static function route_exists( $route, $params = false )
    {
        self::st_reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['action_accepts_scopes'] ) )
            $params['action_accepts_scopes'] = false;

        elseif( !is_array( $params['action_accepts_scopes'] ) )
        {
            if( !PHS_Scope::valid_scope( $params['action_accepts_scopes'] ) )
            {
                if( !self::st_has_error() )
                    self::st_set_error( self::ERR_ROUTE, self::_t( 'Invalid scopes provided for action of the route.' ) );
                return false;
            }

            $params['action_accepts_scopes'] = array( $params['action_accepts_scopes'] );
        } else
        {
            $action_accepts_scopes_arr = array();
            foreach( $params['action_accepts_scopes'] as $scope )
            {
                if( !PHS_Scope::valid_scope( $scope ) )
                {
                    if( !self::st_has_error() )
                        self::st_set_error( self::ERR_ROUTE, self::_t( 'Invalid scopes provided for action of the route.' ) );
                    return false;
                }

                $action_accepts_scopes_arr[] = $scope;
            }

            $params['action_accepts_scopes'] = $action_accepts_scopes_arr;
        }

        if( !($route_parts = self::parse_route( $route, false )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t parse route.' ) );
            return false;
        }

        if( empty( $route_parts ) or !is_array( $route_parts ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Route is invalid.' ) );

            return false;
        }

        $route_parts = self::validate_route_from_parts( $route_parts, false );

        /** @var bool|\phs\libraries\PHS_Plugin $plugin_obj */
        $plugin_obj = false;
        if( !empty( $route_parts['plugin'] )
        and !($plugin_obj = self::load_plugin( $route_parts['plugin'] )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t instantiate plugin from route.' ) );
            return false;
        }

        /** @var bool|\phs\libraries\PHS_Controller $controller_obj */
        $controller_obj = false;
        if( !empty( $route_parts['controller'] )
        and !($controller_obj = self::load_controller( $route_parts['controller'], ($plugin_obj?$plugin_obj->instance_plugin_name():false) )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t instantiate controller from route.' ) );
            return false;
        }

        /** @var bool|\phs\libraries\PHS_Action $action_obj */
        $action_obj = false;
        if( !empty( $route_parts['action'] )
        and !($action_obj = self::load_action( $route_parts['action'], ($plugin_obj?$plugin_obj->instance_plugin_name():false) )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t instantiate action from route.' ) );
            return false;
        }

        if( empty( $action_obj ) )
        {
            self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t instantiate action from route.' ) );
            return false;
        }

        if( !empty( $params['action_accepts_scopes'] ) )
        {
            if( empty( $controller_obj )
             or !($controller_scopes_arr = $controller_obj->allowed_scopes()) )
                $controller_scopes_arr = array();

            if( !($action_scopes_arr = $action_obj->allowed_scopes()) )
                $action_scopes_arr = array();

            $scopes_check_arr = self::array_merge_unique_values( $controller_scopes_arr, $action_scopes_arr );

            if( !empty( $scopes_check_arr ) )
            {
                foreach( $params['action_accepts_scopes'] as $scope )
                {
                    $scope = (int)$scope;
                    if( !in_array( $scope, $scopes_check_arr, true ) )
                    {
                        $scope_title = '(???)';
                        if( ($scope_details = PHS_Scope::valid_scope( $scope )) )
                            $scope_title = $scope_details['title'];

                        self::st_set_error( self::ERR_ROUTE, self::_t( 'Action %s is not ment to run in scope %s.', $route_parts['action'], $scope_title ) );
                        return false;
                    }
                }
            }
        }

        return $route_parts;
    }

    /**
     * Parse request route. Route is something like:
     *
     * {plugin}/{controller}/{action} If controller is part of a plugin
     * or
     * {controller}/{action} If controller is a core controller
     * or
     * {plugin}-{action} Controller will be 'index'
     *
     * @param string|bool $route If a non empty string, method will try parsing provided route, otherwise exract route from context
     * @return bool Returns true on success or false on error
     */
    public static function set_route( $route = false )
    {
        self::st_reset_error();

        if( empty( $route ) )
            $route = self::extract_route();

        if( !($route_parts = self::parse_route( $route, false )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t parse route.' ) );
            return false;
        }

        // Let plugins change API provided route in actual plugin, controller, action route (if required)
        $hook_args = PHS_Hooks::default_phs_route_hook_args();
        $hook_args['original_route'] = $route_parts;

        if( ($hook_args = self::trigger_hooks( PHS_Hooks::H_API_ROUTE, $hook_args ))
        and is_array( $hook_args )
        and !empty( $hook_args['altered_route'] ) and is_array( $hook_args['altered_route'] ) )
            $route_parts = self::parse_route( $hook_args['altered_route'], false );

        self::set_data( self::ROUTE_PLUGIN, $route_parts['plugin'] );
        self::set_data( self::ROUTE_CONTROLLER, $route_parts['controller'] );
        self::set_data( self::ROUTE_ACTION_DIR, $route_parts['action_dir'] );
        self::set_data( self::ROUTE_ACTION, $route_parts['action'] );

        return true;
    }

    public static function safe_escape_root_script( $script )
    {
        if( empty( $script ) or !is_string( $script )
         or preg_match( '@[^a-zA-Z0-9_\-]@', $script ) )
            return false;

        return $script;
    }

    public static function safe_escape_route_parts( $part )
    {
        if( empty( $part ) or !is_string( $part )
         or preg_match( '@[^a-zA-Z0-9_]@', $part ) )
            return false;

        return $part;
    }

    /**
     * Change default route interpret script (default is index). .php file extension will be added by platform.
     *
     * @param bool|string $script New interpreter script (default is index). No extension should be provided (.php will be appended)
     *
     * @return bool|string
     */
    public static function interpret_script( $script = false )
    {
        if( $script === false )
            return self::$_INTERPRET_SCRIPT.'.php';

        if( !self::safe_escape_root_script( $script )
         or !@file_exists( PHS_PATH.$script.'.php' ) )
            return false;

        self::$_INTERPRET_SCRIPT = $script;
        return self::$_INTERPRET_SCRIPT.'.php';
    }

    /**
     * Change default background interpret script (default is _bg). .php file extension will be added by platform.
     *
     * @param bool|string $script New background script (default is _bg). No extension should be provided (.php will be appended)
     *
     * @return bool|string
     */
    public static function background_script( $script = false )
    {
        if( $script === false )
            return self::$_BACKGROUND_SCRIPT.'.php';

        if( !self::safe_escape_root_script( $script )
         or !@file_exists( PHS_PATH.$script.'.php' ) )
            return false;

        self::$_BACKGROUND_SCRIPT = $script;
        return self::$_BACKGROUND_SCRIPT.'.php';
    }

    /**
     * Change default agent interpret script (default is _agent). .php file extension will be added by platform.
     *
     * @param bool|string $script New agent script (default is _agent). No extension should be provided (.php will be appended)
     *
     * @return bool|string
     */
    public static function agent_script( $script = false )
    {
        if( $script === false )
            return self::$_AGENT_SCRIPT.'.php';

        if( !self::safe_escape_root_script( $script )
         or !@file_exists( PHS_PATH.$script.'.php' ) )
            return false;

        self::$_AGENT_SCRIPT = $script;
        return self::$_AGENT_SCRIPT.'.php';
    }

    /**
     * Change default ajax script (default is _ajax). .php file extension will be added by platform.
     *
     * @param bool|string $script New ajax script (default is _ajax). No extension should be provided (.php will be appended)
     *
     * @return bool|string
     */
    public static function ajax_script( $script = false )
    {
        if( $script === false )
            return self::$_AJAX_SCRIPT.'.php';

        if( !self::safe_escape_root_script( $script )
         or !@file_exists( PHS_PATH.$script.'.php' ) )
            return false;

        self::$_AJAX_SCRIPT = $script;
        return self::$_AJAX_SCRIPT.'.php';
    }

    /**
     * Change default api script (default is _api). .php file extension will be added by platform.
     *
     * @param bool|string $script New api script (default is _api). No extension should be provided (.php will be appended)
     *
     * @return bool|string
     */
    public static function api_script( $script = false )
    {
        if( $script === false )
            return self::$_API_SCRIPT.'.php';

        if( !self::safe_escape_root_script( $script )
         or !@file_exists( PHS_PATH.$script.'.php' ) )
            return false;

        self::$_API_SCRIPT = $script;
        return self::$_API_SCRIPT.'.php';
    }

    /**
     * Change default update script (default is _update). .php file extension will be added by platform.
     *
     * @param bool|string $script New upate script (default is _update). No extension should be provided (.php will be appended)
     *
     * @return bool|string
     */
    public static function update_script( $script = false )
    {
        if( $script === false )
            return self::$_UPDATE_SCRIPT.'.php';

        if( !self::safe_escape_root_script( $script )
         or !@file_exists( PHS_PATH.$script.'.php' ) )
            return false;

        self::$_UPDATE_SCRIPT = $script;
        return self::$_UPDATE_SCRIPT.'.php';
    }

    public static function get_background_path()
    {
        return PHS_PATH.self::background_script();
    }

    public static function get_agent_path()
    {
        return PHS_PATH.self::agent_script();
    }

    public static function get_interpret_path()
    {
        return PHS_PATH.self::interpret_script();
    }

    public static function get_update_script_path()
    {
        return PHS_PATH.self::update_script();
    }

    /**
     * @param bool $force_https
     *
     * @return bool|string
     */
    public static function get_interpret_url( $force_https = false )
    {
        if( !($base_url = self::get_base_url( $force_https )) )
            return false;

        if( substr( $base_url, -1 ) !== '/' )
            $base_url .= '/';

        return $base_url.self::interpret_script();
    }

    public static function get_ajax_path()
    {
        return PHS_PATH.self::ajax_script();
    }

    public static function get_ajax_url( $force_https = false )
    {
        if( !($base_url = self::get_base_url( $force_https )) )
            return false;

        if( substr( $base_url, -1 ) !== '/' )
            $base_url .= '/';

        return $base_url.self::ajax_script();
    }

    public static function get_api_path()
    {
        return PHS_PATH.self::api_script();
    }

    public static function get_api_url( $force_https = false )
    {
        if( !($base_url = self::get_base_url( $force_https )) )
            return false;

        if( substr( $base_url, -1 ) !== '/' )
            $base_url .= '/';

        return $base_url.self::api_script();
    }

    public static function get_update_script_url( $force_https = false )
    {
        if( !($base_url = self::get_base_url( $force_https )) )
            return false;

        if( substr( $base_url, -1 ) !== '/' )
            $base_url .= '/';

        return $base_url.self::update_script();
    }

    public static function generate_framework_update_token()
    {
        $pub_key = time() + self::UPDATE_TOKEN_LIFETIME;
        $clean_str = $pub_key.':'.PHS_crypt::crypting_key();
        if( @function_exists( 'hash' )
         && ($hash_algos = @hash_algos())
         && in_array( 'sha256', $hash_algos, true ) )
            $hashed_str = hash( 'sha256', $clean_str );
        else
            $hashed_str = md5( $clean_str );

        return [
            'pub_key' => $pub_key,
            'hash' => $hashed_str,
        ];
    }

    public static function validate_framework_update_params( $pub_key, $hash )
    {
        $pub_key = (int)$pub_key;
        if( empty( $pub_key ) || empty( $hash )
         || !is_string( $hash )
         || $pub_key < time() )
            return false;

        $clean_str = $pub_key.':'.PHS_crypt::crypting_key();
        if( @function_exists( 'hash' )
         && ($hash_algos = @hash_algos())
         && @in_array( 'sha256', $hash_algos, true ) )
        {
            // hash_equals available in PHP >= 5.6.0
            $generated_hash = hash( 'sha256', $clean_str );
            if( @function_exists( 'hash_equals' ) )
                return (@hash_equals( $generated_hash, $hash ) ? true : false);

            return ($generated_hash === $hash);
        }

        return (md5( $clean_str ) === $hash);
    }

    public static function get_framework_update_url_with_token()
    {
        $token = self::generate_framework_update_token();

        $args = [
            self::PARAM_UPDATE_TOKEN_HASH => $token['hash'],
            self::PARAM_UPDATE_TOKEN_PUBKEY => $token['pub_key'],
        ];

        if( !($query_string = @http_build_query( $args )) )
            $query_string = '';

        return self::get_update_script_url( true ).'?'.$query_string;
    }

    public static function current_url()
    {
        if( !($plugin = self::get_data( self::ROUTE_PLUGIN )) )
            $plugin = false;
        if( !($controller = self::get_data( self::ROUTE_CONTROLLER ))
         or $controller === self::ROUTE_DEFAULT_CONTROLLER )
            $controller = false;
        if( !($action_dir = self::get_data( self::ROUTE_ACTION_DIR )) )
            $action_dir = false;
        if( !($action = self::get_data( self::ROUTE_ACTION ))
         or $action === self::ROUTE_DEFAULT_ACTION )
            $action = false;

        if( !($query_string = self::current_page_query_string_as_array()) )
            $query_string = false;

        return self::url( array( 'p' => $plugin, 'c' => $controller, 'ad' => $action_dir, 'a' => $action ), $query_string );
    }

    /**
     * @param bool|array $params
     *
     * @return array
     */
    public static function current_page_query_string_as_array( $params = false )
    {
        if( empty( $_SERVER ) )
            $_SERVER = array();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['remove_route_param'] ) )
            $params['remove_route_param'] = true;
        if( !isset( $params['exclude_params'] ) or !is_array( $params['exclude_params'] ) )
            $params['exclude_params'] = array();

        if( !empty( $_SERVER['QUERY_STRING'] ) )
            @parse_str( $_SERVER['QUERY_STRING'], $query_arr );

        if( empty( $query_arr ) )
            $query_arr = array();

        if( !empty( $params['remove_route_param'] )
        and isset( $query_arr[self::ROUTE_PARAM] ) )
            unset( $query_arr[self::ROUTE_PARAM] );

        if( !empty( $params['exclude_params'] ) )
        {
            foreach( $params['exclude_params'] as $param_name )
            {
                if( isset( $query_arr[$param_name] ) )
                    unset( $query_arr[$param_name] );
            }
        }

        return $query_arr;
    }

    /**
     * @param bool|array $parts
     *
     * @return bool|mixed|string
     */
    public static function route_from_parts( $parts = false )
    {
        if( empty( $parts ) or !is_array( $parts ) )
            $parts = array();

        $parts = self::validate_route_from_parts( $parts, true );

        if( !self::validate_short_name_route_parts( $parts, false ) )
            return false;

        $action_str = (!empty( $parts['a'] )?$parts['a']:'');
        if( !empty( $parts['ad'] )
        and !empty( $action_str ) )
            $action_str = $parts['ad'].self::ACTION_DIR_ACTION_SEPARATOR.$action_str;

        if( empty( $parts['p'] ) )
            $parts['p'] = '';

        if( !empty( $parts['c'] ) )
        {
            $route = $parts['p'].'/'.$parts['c'].'/'.$action_str;
        } elseif( $parts['p'] === '' )
        {
            $route = $action_str;
        } else
        {
            $route = $parts['p'] . '-' . $action_str;
        }

        return $route;
    }

    /**
     * Convert route from long plugin, controller and action names into p, c, a names
     * @param array $route_arr
     *
     * @return array|bool
     */
    public static function convert_route_to_short_parts( $route_arr )
    {
        if( empty( $route_arr ) or !is_array( $route_arr )
         or (empty( $route_arr['plugin'] ) and empty( $route_arr['controller'] ) and empty( $route_arr['action'] )) )
            return $route_arr;

        $converted_route = $route_arr;
        if( isset( $route_arr['plugin'] ) )
        {
            unset( $converted_route['plugin'] );
            $converted_route['p'] = $route_arr['plugin'];
        }
        if( isset( $route_arr['controller'] ) )
        {
            unset( $converted_route['controller'] );
            $converted_route['c'] = $route_arr['controller'];
        }
        if( isset( $route_arr['action'] ) )
        {
            unset( $converted_route['action'] );
            $converted_route['a'] = $route_arr['action'];
        }
        if( isset( $route_arr['action_dir'] ) )
        {
            unset( $converted_route['action_dir'] );
            $converted_route['ad'] = $route_arr['action_dir'];
        }

        return self::validate_short_name_route_parts( $converted_route, true );
    }

    /**
     * Checks if provided route has valid short name parts
     * @param array $route_arr
     * @param bool $check_if_empty
     *
     * @return array|bool
     */
    public static function validate_short_name_route_parts( $route_arr, $check_if_empty = true )
    {
        if( empty( $route_arr ) or !is_array( $route_arr ) )
            $route_arr = array();

        if( (!empty( $check_if_empty )
             and empty( $route_arr['p'] ) and empty( $route_arr['c'] ) and empty( $route_arr['a'] ))
         or (!empty( $route_arr['p'] ) and !self::safe_escape_route_parts( $route_arr['p'] ))
         or (!empty( $route_arr['c'] ) and !self::safe_escape_route_parts( $route_arr['c'] ))
         or (!empty( $route_arr['a'] ) and !self::safe_escape_route_parts( $route_arr['a'] ))
         or (!empty( $route_arr['ad'] ) and !self::safe_escape_route_parts( $route_arr['ad'] )) )
            return false;

        return $route_arr;
    }

    public static function validate_route_from_parts( $route_arr, $use_short_names = false )
    {
        if( empty( $route_arr ) or !is_array( $route_arr ) )
            $route_arr = array();

        if( empty( $route_arr['force_https'] ) )
            $route_arr['force_https'] = false;

        if( $use_short_names )
        {
            if( empty( $route_arr['p'] ) )
                $route_arr['p'] = false;
            if( empty( $route_arr['c'] ) )
                $route_arr['c'] = false;
            // action directory (if applicable)
            if( empty( $route_arr['ad'] ) )
                $route_arr['ad'] = false;
            if( empty( $route_arr['a'] ) )
                $route_arr['a'] = self::ROUTE_DEFAULT_ACTION;
        } else
        {
            if( empty( $route_arr['plugin'] ) )
                $route_arr['plugin'] = false;
            if( empty( $route_arr['controller'] ) )
                $route_arr['controller'] = false;
            if( empty( $route_arr['action_dir'] ) )
                $route_arr['action_dir'] = false;
            if( empty( $route_arr['action'] ) )
                $route_arr['action'] = self::ROUTE_DEFAULT_ACTION;
        }

        return $route_arr;
    }

    /**
     * @param bool|array $route_arr
     * @param bool|array $args
     * @param bool|array $extra
     *
     * @return mixed|string
     */
    public static function url( $route_arr = false, $args = false, $extra = false )
    {
        $route_arr = self::validate_route_from_parts( $route_arr, true );

        if( in_array( PHS_Scope::current_scope(), [ PHS_Scope::SCOPE_BACKGROUND, PHS_Scope::SCOPE_AGENT ], true ) )
            $route_arr['force_https'] = true;

        if( empty( $args ) || !is_array( $args ) )
            $args = [];

        if( empty( $extra ) || !is_array( $extra ) )
            $extra = [];

        if( empty( $extra['http'] ) || !is_array( $extra['http'] ) )
            $extra['http'] = [];

        if( empty( $extra['http']['arg_separator'] )
         || !is_string( $extra['http']['arg_separator'] ) )
            $extra['http']['arg_separator'] = '&';

        if( empty( $extra['http']['enc_type'] ) )
            $extra['http']['enc_type'] = PHP_QUERY_RFC1738;

        if( empty( $extra['raw_params'] ) || !is_array( $extra['raw_params'] ) )
            $extra['raw_params'] = [];

        // Changed raw_params to raw_args (backward compatibility)
        if( empty( $extra['raw_args'] ) || !is_array( $extra['raw_args'] ) )
            $extra['raw_args'] = $extra['raw_params'];

        if( empty( $extra['skip_formatters'] ) )
            $extra['skip_formatters'] = false;
        else
            $extra['skip_formatters'] = (!empty( $extra['skip_formatters'] ));

        if( empty( $extra['for_scope'] ) || !PHS_Scope::valid_scope( $extra['for_scope'] ) )
            $extra['for_scope'] = PHS_Scope::SCOPE_WEB;

        $new_args = [];

        // We can pass raw route as a string (obtained as PHS::route_from_parts())
        // which is passed as a parameter in a JavaScript script
        // (eg. data_call( route, data ) { PHS_JSEN.createAjaxDialog( { ... url: "PHS_ajax::url( false, [], [ 'raw_route' => '" + route + "' ] )", ... }
        if( !empty( $extra['raw_route'] ) && is_string( $extra['raw_route'] ) )
        {
            $extra['raw_args'][self::ROUTE_PARAM] = $extra['raw_route'];
        } elseif( !($route = self::route_from_parts( $route_arr )) )
        {
            return '#invalid_path['.
                   (!empty( $route_arr['p'] )?$route_arr['p']:'').'::'.
                   (!empty( $route_arr['c'] )?$route_arr['c']:'').'::'.
                   (!empty( $route_arr['ad'] )?$route_arr['ad']:'').'/'.
                   (!empty( $route_arr['a'] )?$route_arr['a']:'').']';
        } else
        {
            $new_args[self::ROUTE_PARAM] = $route;
        }

        if( isset( $args[self::ROUTE_PARAM] ) )
            unset( $args[self::ROUTE_PARAM] );

        foreach( $args as $key => $val )
            $new_args[$key] = $val;

        if( !($query_string = @http_build_query( $new_args, null, $extra['http']['arg_separator'], $extra['enc_type'] )) )
            $query_string = '';

        if( !empty( $extra['raw_args'] ) && is_array( $extra['raw_args'] ) )
        {
            // Parameters that shouldn't be run through http_build_query as values will be rawurlencoded and we might add javascript code in parameters
            // eg. $extra['raw_args'] might be an id passed as javascript function parameter
            if( ($raw_query = array_to_query_string( $extra['raw_args'],
                                                     [ 'arg_separator' => $extra['http']['arg_separator'], 'raw_encode_values' => false ] )) )
                $query_string .= ($query_string!==''?$extra['http']['arg_separator']:'').$raw_query;
        }

        switch( $extra['for_scope'] )
        {
            default:
                $stock_url = self::get_interpret_url( $route_arr['force_https'] ).($query_string!==''?'?'.$query_string:'');
            break;

            case PHS_Scope::SCOPE_AJAX:
                $stock_url = self::get_ajax_url( $route_arr['force_https'] ).($query_string!==''?'?'.$query_string:'');
            break;

            case PHS_Scope::SCOPE_API:
                $stock_url = self::get_api_url( $route_arr['force_https'] ).($query_string!==''?'?'.$query_string:'');
            break;
        }

        $final_url = $stock_url;

        if( empty( $extra['skip_formatters'] ) )
        {
            // Let plugins change API provided route in actual plugin, controller, action route (if required)
            $hook_args = PHS_Hooks::default_url_rewrite_hook_args();
            $hook_args['route_arr'] = $route_arr;
            $hook_args['args'] = $args;
            $hook_args['raw_args'] = $extra['raw_args'];

            $hook_args['stock_args'] = $new_args;
            $hook_args['stock_query_string'] = $query_string;
            $hook_args['stock_url'] = $stock_url;

            if( ($hook_args = self::trigger_hooks( PHS_Hooks::H_URL_REWRITE, $hook_args ))
             && is_array( $hook_args )
             && !empty( $hook_args['new_url'] ) && is_string( $hook_args['new_url'] ) )
                $final_url = $hook_args['new_url'];
        }

        return $final_url;
    }

    public static function relative_url( $url )
    {
        // check on "non https" url first
        if( ($base_url = self::get_base_url( false ))
        and ($base_len = strlen( $base_url ))
        and strpos( $url, $base_url ) === 0 )
            return substr( $url, $base_len );

        // check "https" url
        if( ($base_url = self::get_base_url( true ))
        and ($base_len = strlen( $base_url ))
        and strpos( $url, $base_url ) === 0 )
            return substr( $url, $base_len );

        return $url;
    }

    public static function from_relative_url( $url, $force_https = false )
    {
        if( ($base_url = self::get_base_url( $force_https ))
        and strpos( $url, $base_url ) === 0 )
            return $url;

        return $base_url.$url;
    }

    public static function relative_path( $path )
    {
        if( ($base_len = strlen( PHS_PATH ))
        and strpos( $path, PHS_PATH ) === 0 )
            return substr( $path, $base_len );

        return $path;
    }

    public static function from_relative_path( $path )
    {
        if( strpos( $path, PHS_PATH ) === 0 )
            return $path;

        return PHS_PATH.$path;
    }

    public static function get_route_details()
    {
        if( ($controller = self::get_data( self::ROUTE_CONTROLLER )) === null )
        {
            self::set_route();

            if( null === ($controller = self::get_data( self::ROUTE_CONTROLLER )) )
                return false;
        }

        $return_arr = array();
        $return_arr[self::ROUTE_PLUGIN] = self::get_data( self::ROUTE_PLUGIN );
        $return_arr[self::ROUTE_CONTROLLER] = $controller;
        $return_arr[self::ROUTE_ACTION] = self::get_data( self::ROUTE_ACTION );
        $return_arr[self::ROUTE_ACTION_DIR] = self::get_data( self::ROUTE_ACTION_DIR );

        return $return_arr;
    }

    public static function get_route_details_for_url( $use_short_names = true )
    {
        if( !($route_arr = self::get_route_details()) )
            return false;

        if( $use_short_names )
            return array(
                'p' => $route_arr[self::ROUTE_PLUGIN],
                'c' => $route_arr[self::ROUTE_CONTROLLER],
                'a' => $route_arr[self::ROUTE_ACTION],
            );

        return array(
            'plugin' => $route_arr[self::ROUTE_PLUGIN],
            'controller' => $route_arr[self::ROUTE_CONTROLLER],
            'action' => $route_arr[self::ROUTE_ACTION],
        );
    }

    public static function get_route_as_string()
    {
        if( ($controller = self::get_data( self::ROUTE_CONTROLLER )) === null )
        {
            self::set_route();

            if( ($controller = self::get_data( self::ROUTE_CONTROLLER )) === null )
                return false;
        }

        $route_arr = array();
        $route_arr['p'] = self::get_data( self::ROUTE_PLUGIN );
        $route_arr['c'] = $controller;
        $route_arr['a'] = self::get_data( self::ROUTE_ACTION );

        return self::route_from_parts( $route_arr );
    }

    /**
     * @param bool|array $params
     *
     * @return array|bool|null
     */
    public static function execute_route( $params = false )
    {
        self::st_reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['die_on_error'] ) )
            $params['die_on_error'] = true;
        else
            $params['die_on_error'] = (!empty( $params['die_on_error'] ));

        $action_result = false;

        if( !($route_details = self::get_route_details())
         or empty( $route_details[self::ROUTE_CONTROLLER] ) )
        {
            self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Couldn\'t obtain route details.' ) );
        }

        /** @var \phs\libraries\PHS_Controller $controller_obj */
        elseif( !($controller_obj = self::load_controller( $route_details[self::ROUTE_CONTROLLER], $route_details[self::ROUTE_PLUGIN] )) )
        {
            self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Couldn\'t obtain controller instance for %s.', $route_details[self::ROUTE_CONTROLLER] ) );
        }

        elseif( !($action_result = $controller_obj->run_action( $route_details[self::ROUTE_ACTION], null, $route_details[self::ROUTE_ACTION_DIR] )) )
        {
            if( $controller_obj->has_error() )
                self::st_copy_error( $controller_obj );
            else
                self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Error executing action [%s].', $route_details[self::ROUTE_ACTION] ) );
        }

        $controller_error_arr = self::st_get_error();

        self::st_reset_error();

        if( is_array( $action_result )
        and !empty( $action_result['scope'] )
        and (int)$action_result['scope'] !== PHS_Scope::current_scope() )
            PHS_Scope::current_scope( $action_result['scope'] );

        if( !($scope_obj = PHS_Scope::get_scope_instance()) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Error spawning scope instance.' ) );

            if( !empty( $params['die_on_error'] ) )
            {
                $error_msg = self::st_get_error_message();

                PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_DEF_DEBUG );

                echo 'Error spawining scope.';
                exit;
            }

            return false;
        }

        // Don't display technical stuff to end-user...
        if( !self::st_debugging_mode()
        and self::arr_has_error( $controller_error_arr ) )
            $controller_error_arr = self::arr_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Error serving request.' ) );

        if( !empty( $action_result ) and is_array( $action_result )
        and !empty( $action_result['custom_headers'] ) and is_array( $action_result['custom_headers'] ) )
            $action_result['custom_headers'] = self::unify_array_insensitive( $action_result['custom_headers'], array( 'trim_keys' => true ) );

        $scope_action_result = $scope_obj->generate_response( $action_result, $controller_error_arr );

        $error_msg = false;
        if( self::st_has_error() )
            $error_msg = self::st_get_error_message();
        elseif( self::arr_has_error( $controller_error_arr ) )
        {
            $error_msg = '['.self::get_route_as_string().'] - '.
                         self::arr_get_simple_error_message( $controller_error_arr );
        } elseif( $scope_obj->has_error() )
            $error_msg = $scope_obj->get_error_message();

        if( $error_msg !== false )
        {
            PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_DEBUG );

            return false;
        }

        return $scope_action_result;
    }

    /**
     * This method spawns a view object with provided route as context for script which require resources
     * (javascript URLs, image URLs, etc), but they are not running in a view context. Usually javascript scripts
     * which output javascript directly without rendering the code in a view context
     *
     * @param array|string $route_arr
     * @param string|array $template
     * @param bool|array $template_data
     *
     * @return bool|\phs\system\core\views\PHS_view
     */
    public static function spawn_view_in_context( $route_arr, $template, $template_data = false )
    {
        self::st_reset_error();

        $plugin_obj = false;
        /** @var \phs\libraries\PHS_Plugin $plugin_obj */
        /** @var \phs\libraries\PHS_Controller $controller_obj */
        /** @var \phs\libraries\PHS_Action $action_obj */
        if( !($route_arr = self::parse_route( $route_arr, true ))
         || (!empty( $route_arr['p'] ) && !($plugin_obj = self::load_plugin( $route_arr['p'] )))
         || !($controller_obj = self::load_controller( $route_arr['c'], $route_arr['p'] ))
         || !($action_obj = self::load_action( $route_arr['a'], $route_arr['p'], $route_arr['ad'] )) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Error instantiating controller or action from provided route.' ) );
            return false;
        }

        $view_params = array();
        $view_params['action_obj'] = $action_obj;
        $view_params['controller_obj'] = $controller_obj;
        $view_params['parent_plugin_obj'] = $plugin_obj;
        $view_params['plugin'] = ($plugin_obj?$plugin_obj->instance_plugin_name():false);
        $view_params['template_data'] = $template_data;

        if( !($view_obj = \phs\system\core\views\PHS_View::init_view( $template, $view_params )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Error instantiating view in provided context.' ) );

            return false;
        }

        return $view_obj;
    }

    public static function platform_debug_data()
    {
        $now_secs = microtime( true );
        if( !($start_secs = self::get_data( self::PHS_START_TIME )) )
            $start_secs = $now_secs;
        if( !($bootstrap_secs = self::get_data( self::PHS_BOOTSTRAP_END_TIME )) )
            $bootstrap_secs = $now_secs;
        if( !($end_secs = self::get_data( self::PHS_END_TIME )) )
            $end_secs = $now_secs;

        $bootstrap_time = $bootstrap_secs - $start_secs;
        $running_time = $end_secs - $start_secs;

        $return_arr = array();
        $return_arr['db_queries_count'] = db_query_count();
        $return_arr['bootstrap_time'] = $bootstrap_time;
        $return_arr['running_time'] = $running_time;
        $return_arr['memory_usage'] = memory_get_usage();
        $return_arr['memory_peak'] = memory_get_peak_usage();

        return $return_arr;
    }

    private static function _get_db_user_details( $force = false )
    {
        static $hook_result = false;

        if( empty( $force ) and !empty( $hook_result ) )
            return $hook_result;

        if( !empty( $force ) )
            $hook_result = PHS_Hooks::default_user_db_details_hook_args();

        $hook_result = self::trigger_hooks( PHS_Hooks::H_USER_DB_DETAILS, $hook_result );

        return $hook_result;
    }

    public static function get_current_user_db_details()
    {
        if( !($hook_result = self::_get_db_user_details()) )
            $hook_result = PHS_Hooks::default_user_db_details_hook_args();

        return $hook_result['user_db_data'];
    }

    public static function get_current_session_db_details()
    {
        if( !($hook_result = self::_get_db_user_details()) )
            $hook_result = PHS_Hooks::default_user_db_details_hook_args();

        return $hook_result['session_db_data'];
    }

    public static function get_core_library_full_path( $library )
    {
        $library = PHS_Instantiable::safe_escape_library_name( $library );
        if( empty( $library )
         or !@file_exists( PHS_CORE_LIBRARIES_DIR.$library.'.php' ) )
            return false;

        return PHS_CORE_LIBRARIES_DIR.$library.'.php';
    }

    /**
     * Try loading a core library
     *
     * @param string $library Core library file to be loaded
     * @param bool|array $params Loading parameters
     *
     * @return bool|libraries\PHS_Library
     */
    public static function load_core_library( $library, $params = false )
    {
        self::st_reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        // We assume $library represents class name without namespace (otherwise it won't be a valid library name)
        // so class name is from "root" namespace
        if( empty( $params['full_class_name'] ) )
            $params['full_class_name'] = '\\'.ltrim( $library, '\\' );
        if( empty( $params['init_params'] ) )
            $params['init_params'] = false;
        if( empty( $params['as_singleton'] ) )
            $params['as_singleton'] = true;

        if( !($library = PHS_Instantiable::safe_escape_library_name( $library )) )
        {
            self::st_set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load core library.' ) );
            return false;
        }

        if( !empty( $params['as_singleton'] )
        and !empty( self::$_core_libraries_instances[$library] ) )
            return self::$_core_libraries_instances[$library];

        if( !($file_path = self::get_core_library_full_path( $library )) )
        {
            self::st_set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load core library [%s].', $library ) );
            return false;
        }

        ob_start();
        include_once( $file_path );
        ob_get_clean();

        if( !@class_exists( $params['full_class_name'], false ) )
        {
            self::st_set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t instantiate library class for core library [%s].', $library ) );
            return false;
        }

        /** @var \phs\libraries\PHS_Library $library_instance */
        if( empty( $params['init_params'] ) )
            $library_instance = new $params['full_class_name']();
        else
            $library_instance = new $params['full_class_name']( $params['init_params'] );

        if( !($library_instance instanceof PHS_Library) )
        {
            self::st_set_error( self::ERR_LIBRARY, self::_t( 'Core library [%s] is not a PHS library.', $library ) );
            return false;
        }

        $location_details = $library_instance::get_library_default_location_paths();
        $location_details['library_file'] = $file_path;
        $location_details['library_path'] = rtrim( PHS_CORE_LIBRARIES_DIR, '/\\' );
        $location_details['library_www'] = rtrim( PHS_CORE_LIBRARIES_WWW, '/' );

        if( !$library_instance->set_library_location_paths( $location_details ) )
        {
            self::st_set_error( self::ERR_LIBRARY, self::_t( 'Core library [%s] couldn\'t set location paths.', $library ) );
            return false;
        }

        if( !empty( $params['as_singleton'] ) )
            self::$_core_libraries_instances[$library] = $library_instance;

        return $library_instance;
    }

    /**
     * @param string $core_library Short library name (eg. ftp, paginator_exporter_csv, etc)
     * @param bool|array $params Parameters passed to self::load_core_library() method
     *
     * @return bool|PHS_Library Helper for self::load_core_library() method call which prepares class name and file name
     */
    public static function get_core_library_instance( $core_library, $params = false )
    {
        if( empty( $core_library )
         or !($library_name = PHS_Instantiable::safe_escape_library_name( $core_library )) )
        {
            self::st_set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load core library.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        $core_library = strtolower( $core_library );

        $library_params = $params;
        $library_params['full_class_name'] = '\\phs\\system\\core\\libraries\\PHS_'.ucfirst( $core_library );

        if( !($library_obj = self::load_core_library( 'phs_'.$core_library, $library_params )) )
            return false;

        return $library_obj;
    }

    /**
     * Returns an instance of a model. If model is part of a plugin $plugin will contain name of that plugin.
     *
     * @param string $model Model to be loaded (part of class name after PHS_Model_)
     * @param string|bool $plugin Plugin where model is located (false means a core model)
     *
     * @return false|\phs\libraries\PHS_Model_Mysqli Returns false on error or an instance of loaded model
     */
    public static function load_model( $model, $plugin = false )
    {
        if( !($model_name = PHS_Instantiable::safe_escape_class_name( $model )) )
        {
            self::st_set_error( self::ERR_LOAD_MODEL, self::_t( 'Couldn\'t load model %s from plugin %s.', $model, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Model_'.ucfirst( strtolower( $model_name ) );

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_MODEL )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_MODEL, self::_t( 'Couldn\'t obtain instance for model %s from plugin %s .', $model, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * Returns an instance of a view. If view is part of a plugin $plugin will contain name of that plugin.
     *
     * @param string|bool $view View to be loaded (part of class name after PHS_View_)
     * @param string|bool $plugin Plugin where view is located (false means a core view)
     * @param bool $as_singleton Tells if view instance should be loaded as singleton or new instance
     *
     * @return false|\phs\system\core\views\PHS_View Returns false on error or an instance of loaded view
     */
    public static function load_view( $view = false, $plugin = false, $as_singleton = true )
    {
        self::st_reset_error();

        $view_class = '';
        if( !empty( $view )
        and !($view_class = PHS_Instantiable::safe_escape_class_name( $view )) )
        {
            self::st_set_error( self::ERR_LOAD_VIEW, self::_t( 'Couldn\'t load view %s from plugin %s.', $view, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        if( !empty( $view_class ) )
            $class_name = 'PHS_View_'.ucfirst( strtolower( $view_class ) );
        else
            $class_name = 'PHS_View';

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        // Views are not singletons
        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_VIEW, (bool)$as_singleton )) )
        {
            if( $plugin === false )
            {
                if( !self::st_has_error() )
                    self::st_set_error( self::ERR_LOAD_VIEW, self::_t( 'Couldn\'t obtain instance for model %s from plugin %s .', $view, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin) ) );

                return false;
            }

            $plugin = false;

            self::st_reset_error();

            // We tried loading plugin view, try again with a core view...
            if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_VIEW, (bool)$as_singleton )) )
            {
                if( !self::st_has_error() )
                    self::st_set_error( self::ERR_LOAD_VIEW, self::_t( 'Couldn\'t obtain instance for model %s from plugin %s .', $view, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin) ) );

                return false;
            }
        }

        return $instance_obj;
    }

    /**
     * @param string $controller
     * @param string|bool $plugin
     *
     * @return false|\phs\libraries\PHS_Controller Returns false on error or an instance of loaded controller
     */
    public static function load_controller( $controller, $plugin = false )
    {
        if( !($controller_name = PHS_Instantiable::safe_escape_class_name( $controller )) )
        {
            self::st_set_error( self::ERR_LOAD_CONTROLLER, self::_t( 'Couldn\'t load controller %s from plugin %s.', $controller, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Controller_'.ucfirst( strtolower( $controller_name ) );

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_CONTROLLER, self::_t( 'Couldn\'t obtain instance for controller %s from plugin %s .', $controller, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * @param string $action
     * @param string|bool $plugin
     * @param string $action_dir
     *
     * @return bool|\phs\libraries\PHS_Action Returns false on error or an instance of loaded action
     */
    public static function load_action( $action, $plugin = false, $action_dir = '' )
    {
        if( !is_string( $action_dir ) )
            $action_dir = '';
        else
            $action_dir = trim( trim( $action_dir ), '/\\' );

        if( !($action_name = PHS_Instantiable::safe_escape_class_name( $action )) )
        {
            self::st_set_error( self::ERR_LOAD_ACTION,
                                self::_t( 'Couldn\'t load action %s from plugin %s.',
                                          ($action_dir!==''?$action_dir.'/':'').$action,
                                          (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        if( '' !== $action_dir
        and !($action_dir = PHS_Instantiable::safe_escape_instance_subdir( $action_dir )) )
        {
            self::st_set_error( self::ERR_LOAD_ACTION,
                                self::_t( 'Couldn\'t load action %s from plugin %s.',
                                          ($action_dir!==''?$action_dir.'/':'').$action,
                                          (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Action_'.ucfirst( strtolower( $action_name ) );

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        /** @var \phs\libraries\PHS_Action */
        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_ACTION, true, $action_dir )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_ACTION,
                                    self::_t( 'Couldn\'t obtain instance for action %s from plugin %s .',
                                              ($action_dir!==''?$action_dir.'/':'').$action,
                                              (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * @param string $contract
     * @param string|bool $plugin
     * @param string $contract_dir
     *
     * @return bool|\phs\libraries\PHS_Contract Returns false on error or an instance of loaded contract
     */
    public static function load_contract( $contract, $plugin = false, $contract_dir = '' )
    {
        if( !is_string( $contract_dir ) )
            $contract_dir = '';
        else
            $contract_dir = trim( trim( $contract_dir ), '/\\' );

        if( !($contract_name = PHS_Instantiable::safe_escape_class_name( $contract )) )
        {
            self::st_set_error( self::ERR_LOAD_CONTRACT,
                                self::_t( 'Couldn\'t load contract %s from plugin %s.',
                                          ($contract_dir!==''?$contract_dir.'/':'').$contract,
                                          (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        if( '' !== $contract_dir
        and !($contract_dir = PHS_Instantiable::safe_escape_instance_subdir( $contract_dir )) )
        {
            self::st_set_error( self::ERR_LOAD_CONTRACT,
                                self::_t( 'Couldn\'t load contract %s from plugin %s.',
                                          ($contract_dir!==''?$contract_dir.'/':'').$contract,
                                          (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Contract_'.ucfirst( strtolower( $contract_name ) );

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        /** @var \phs\libraries\PHS_Action */
        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_CONTRACT, true, $contract_dir )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_CONTRACT,
                                    self::_t( 'Couldn\'t obtain instance for contract %s from plugin %s .',
                                              ($contract_dir!==''?$contract_dir.'/':'').$contract,
                                              (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * @param string $scope
     * @param string|bool $plugin
     *
     * @return false|\phs\PHS_Scope Returns false on error or an instance of loaded scope
     */
    public static function load_scope( $scope, $plugin = false )
    {
        if( !($scope_name = PHS_Instantiable::safe_escape_class_name( $scope )) )
        {
            self::st_set_error( self::ERR_LOAD_SCOPE, self::_t( 'Couldn\'t load scope %s from plugin %s.', $scope, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Scope_'.ucfirst( strtolower( $scope_name ) );

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        /** @var \phs\PHS_Scope */
        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_SCOPE )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_SCOPE, self::_t( 'Couldn\'t obtain instance for scope %s from plugin %s .', $scope, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * @param string $plugin_name Plugin name to be loaded
     *
     * @return false|\phs\libraries\PHS_Plugin Returns false on error or an instance of loaded plugin
     */
    public static function load_plugin( $plugin_name )
    {
        if( !is_string( $plugin_name ) )
        {
            self::st_set_error( self::ERR_LOAD_PLUGIN, self::_t( 'Plugin name is not a string.' ) );
            return false;
        }

        if( empty( $plugin_name )
         or $plugin_name === PHS_Instantiable::CORE_PLUGIN
         or !($plugin_safe_name = PHS_Instantiable::safe_escape_class_name( $plugin_name )) )
        {
            self::st_set_error( self::ERR_LOAD_PLUGIN, self::_t( 'Couldn\'t load plugin %s.', (empty( $plugin_name )?PHS_Instantiable::CORE_PLUGIN:$plugin_name) ) );
            return false;
        }

        $class_name = 'PHS_Plugin_'.ucfirst( strtolower( $plugin_safe_name ) );

        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin_name, PHS_Instantiable::INSTANCE_TYPE_PLUGIN )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_PLUGIN, self::_t( 'Couldn\'t obtain instance for plugin class %s from plugin %s.', $plugin_name ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * Read directory corresponding to $instance_type from $plugin and return instance type names (as required for PHS::load_* method)
     * This only returns file names, does no check if class is instantiable...
     *
     * @param bool|string $plugin Core plugin if false or plugin name as string
     * @param string $instance_type What script files should we check PHS_Instantiable::INSTANCE_TYPE_*
     *
     * @return array|bool
     */
    public static function get_plugin_scripts_from_dir( $plugin = false, $instance_type = PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
    {
        self::st_reset_error();

        switch( $instance_type )
        {
            case PHS_Instantiable::INSTANCE_TYPE_PLUGIN:
                $class_name = 'PHS_Plugin_Index';
            break;
            case PHS_Instantiable::INSTANCE_TYPE_MODEL:
                $class_name = 'PHS_Model_Index';
            break;
            case PHS_Instantiable::INSTANCE_TYPE_CONTROLLER:
                $class_name = 'PHS_Controller_Index';
            break;
            case PHS_Instantiable::INSTANCE_TYPE_ACTION:
                $class_name = 'PHS_Action_Index';
            break;
            case PHS_Instantiable::INSTANCE_TYPE_CONTRACT:
                $class_name = 'PHS_Contract_Index';
            break;
            case PHS_Instantiable::INSTANCE_TYPE_VIEW:
                $class_name = 'PHS_View_Index';
            break;
            case PHS_Instantiable::INSTANCE_TYPE_SCOPE:
                $class_name = 'PHS_Scope_Index';
            break;

            default:
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Invalid instance type to obtain script files list.' ) );
                return false;
            break;
        }

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
        {
            $plugin = false;

            if( $instance_type === PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
            {
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'There is no CORE plugin.' ) );
                return false;
            }
        }

        elseif( !($plugin = PHS_Instantiable::safe_escape_plugin_name( $plugin )) )
        {
            self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Invalid plugin name to obtain script files list.' ) );
            return false;
        }

        // Get generic information about an index instance to obtain paths to be checked...
        if( !($instance_details = PHS_Instantiable::get_instance_details( $class_name, $plugin, $instance_type )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Couldn\'t obtain instance details for generic controller index from plugin %s .', (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        if( empty( $instance_details['instance_path'] ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Couldn\'t read controllers directory from plugin %s .', (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        // Plugin might not have even directory created meaning no script files
        if( !@is_dir( $instance_details['instance_path'] )
         or !@is_readable( $instance_details['instance_path'] ) )
            return array();

        // Check spacial case if we are asked for plugin script (as there's only one)
        $resulting_instance_names = array();
        if( $plugin !== PHS_Instantiable::CORE_PLUGIN
        and $instance_type === PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
        {
            if( !@file_exists( $instance_details['instance_path'].'phs_'.$plugin.'.php' ) )
            {
                if( !self::st_has_error() )
                    self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Couldn\'t read plugin script file for plugin %s .', $plugin ) );
                return false;
            }

            $resulting_instance_names[] = $plugin;
        } else
        {
            if( ($file_scripts = @glob( $instance_details['instance_path'] . 'phs_*.php' ))
            and is_array( $file_scripts ) )
            {
                foreach( $file_scripts as $file_script )
                {
                    $script_file_name = basename( $file_script );
                    // Special check as plugin script is only one
                    if( strpos( $script_file_name, 'phs_' ) !== 0
                     or substr( $script_file_name, -4 ) !== '.php' )
                        continue;

                    $instance_file_name = substr( substr( $script_file_name, 4 ), 0, -4 );

                    $resulting_instance_names[] = $instance_file_name;
                }
            }
        }

        return $resulting_instance_names;
    }

    final public static function default_instance_json_fields()
    {
        return array(
            'data_from_json' => false, // tells if data is populated from JSON
            'vendor_id' => '', // unique vendor identifier
            'vendor_name' => '', // readable vendor name
            'name' => '',
            'description' => '',
            'version' => '0.0.0',
            // This is used internally by PHS
            'script_version' => '0.0.0',
            'update_url' => '',
            // Tells if plugin has any dependencies (key is plugin name and value is min version required)
            'requires' => array(),
            // only for plugins...
            'models' => array(),
            // only for plugins...
            'agent_jobs' => array(),
        );
    }

    /**
     * Read directory corresponding to $instance_type from $plugin and return instance type names (as required for PHS::load_* method)
     * This only returns file names, does no check if class is instantiable...
     *
     * @param string|bool $plugin Plugin name
     * @param string|bool $instance_name Model, controller, action, view or scope name
     * @param string $instance_type What instance type to check for JSON info (types PHS_Instantiable::INSTANCE_TYPE_*)
     * @param string $instance_subdir If instance allows subdirs, provide which subdir is that
     *
     * @return array|bool
     */
    private static function _get_instance_json_details( $plugin = false, $instance_name = false, $instance_type = PHS_Instantiable::INSTANCE_TYPE_PLUGIN, $instance_subdir = '' )
    {
        self::st_reset_error();

        if( $plugin === false )
            $plugin = PHS_Instantiable::CORE_PLUGIN;

        if( $instance_name === false
         or !is_string( $instance_name ) )
            $instance_name = 'Index';
        else
            $instance_name = ucfirst( strtolower( $instance_name ) );

        switch( $instance_type )
        {
            case PHS_Instantiable::INSTANCE_TYPE_PLUGIN:
                $class_name = 'PHS_Plugin_'.$instance_name;
            break;
            case PHS_Instantiable::INSTANCE_TYPE_MODEL:
                $class_name = 'PHS_Model_'.$instance_name;
            break;
            case PHS_Instantiable::INSTANCE_TYPE_CONTROLLER:
                $class_name = 'PHS_Controller_'.$instance_name;
            break;
            case PHS_Instantiable::INSTANCE_TYPE_ACTION:
                $class_name = 'PHS_Action_'.$instance_name;
            break;
            case PHS_Instantiable::INSTANCE_TYPE_CONTRACT:
                $class_name = 'PHS_Contract_'.$instance_name;
            break;
            case PHS_Instantiable::INSTANCE_TYPE_VIEW:
                $class_name = 'PHS_View_'.$instance_name;
            break;
            case PHS_Instantiable::INSTANCE_TYPE_SCOPE:
                $class_name = 'PHS_Scope_'.$instance_name;
            break;

            default:
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Invalid instance type to get JSON info.' ) );
                return false;
            break;
        }

        if( $plugin === PHS_Instantiable::CORE_PLUGIN )
        {
            $plugin = false;

            if( $instance_type === PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
            {
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'There is no CORE plugin.' ) );
                return false;
            }
        }

        elseif( !($plugin = PHS_Instantiable::safe_escape_plugin_name( $plugin )) )
        {
            self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Invalid plugin name to get JSON info.' ) );
            return false;
        }

        // Get generic information about an index controller to obtain paths to be checked...
        if( !($instance_details = PHS_Instantiable::get_instance_details( $class_name, $plugin, $instance_type, $instance_subdir )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Couldn\'t obtain instance details for generic controller index from plugin %s .', (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        if( empty( $instance_details['instance_path'] ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Couldn\'t obtain instance path for plugin %s, instance %s.',
                    (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin), $instance_name ) );

            return false;
        }

        // Plugin might not have even directory created meaning no script files
        if( !@is_dir( $instance_details['instance_path'] )
         or !@is_readable( $instance_details['instance_path'] )
         or !@file_exists( $instance_details['instance_path'].$instance_details['instance_json_file'] )
         or !($json_str = @file_get_contents( $instance_details['instance_path'].$instance_details['instance_json_file'] ))
         or !($json_arr = @json_decode( $json_str, true )) )
            return array();

        $json_arr = self::validate_array_to_new_array( $json_arr, self::default_instance_json_fields() );

        // script_version key is used internally by PHS
        if( !empty( $json_arr['version'] ) )
            $json_arr['script_version'] = $json_arr['version'];

        $json_arr['data_from_json'] = true;

        return $json_arr;
    }

    /**
     * @param string $plugin_name
     *
     * @return array|bool
     */
    public static function get_plugin_json_info( $plugin_name )
    {
        return self::_get_instance_json_details( $plugin_name, $plugin_name, PHS_Instantiable::INSTANCE_TYPE_PLUGIN );
    }

    /**
     * @param string $plugin_name
     * @param string $model_name
     *
     * @return array|bool
     */
    public static function get_model_json_info( $plugin_name, $model_name )
    {
        return self::_get_instance_json_details( $plugin_name, $model_name, PHS_Instantiable::INSTANCE_TYPE_MODEL );
    }

    /**
     * @param string $plugin_name
     * @param string $controller_name
     *
     * @return array|bool
     */
    public static function get_controller_json_info( $plugin_name, $controller_name )
    {
        return self::_get_instance_json_details( $plugin_name, $controller_name, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER );
    }

    /**
     * @param string $plugin_name
     * @param string $action_name
     *
     * @return array|bool
     */
    public static function get_action_json_info( $plugin_name, $action_name )
    {
        return self::_get_instance_json_details( $plugin_name, $action_name, PHS_Instantiable::INSTANCE_TYPE_ACTION );
    }

    /**
     * @param string $plugin_name
     * @param string $contract_name
     *
     * @return array|bool
     */
    public static function get_contract_json_info( $plugin_name, $contract_name )
    {
        return self::_get_instance_json_details( $plugin_name, $contract_name, PHS_Instantiable::INSTANCE_TYPE_CONTRACT );
    }

    /**
     * @param string $plugin_name
     * @param string $view_name
     *
     * @return array|bool
     */
    public static function get_view_json_info( $plugin_name, $view_name )
    {
        return self::_get_instance_json_details( $plugin_name, $view_name, PHS_Instantiable::INSTANCE_TYPE_VIEW );
    }

    /**
     * @param string $plugin_name
     * @param string $scope_name
     *
     * @return array|bool
     */
    public static function get_scope_json_info( $plugin_name, $scope_name )
    {
        return self::_get_instance_json_details( $plugin_name, $scope_name, PHS_Instantiable::INSTANCE_TYPE_SCOPE );
    }

    /**
     * Validates a hook name and returns valid value or false if hook name is not valid.
     *
     * @param string $hook_name
     *
     * @return bool|string Valid hook name or false if hook_name is not valid.
     */
    public static function prepare_hook_name( $hook_name )
    {
        if( !is_string( $hook_name )
         or !($hook_name = strtolower( trim( $hook_name ) )) )
            return false;

        return $hook_name;
    }

    public static function get_registered_hooks()
    {
        return self::$hooks;
    }

    /**
     * Adds a hook in call queue. When a hook is fired, script will call each callback function in order of their
     * priority. Along with standard hook parameters (check each hook definition to see which are these) you can add
     * extra parameters which you pass at hook definition
     *
     * @param string $hook_name             Hook name
     * @param callback $hook_callback       Method/Function to be called
     * @param null|array $hook_extra_args   Extra arguments to be passed when hook is fired
     * @param array|bool $extra             Extra details related to current hook:
     *      chained_hook    If true result of hook call will overwrite parameters of next hook callback (can be used as filters)
     *      priority        Order in which hooks are fired is given by $priority parameter
     *      stop_chain      If true will stop hooks execution (used if chained_hook is true)
     *
     * @return bool                     True if hook was added with success or false otherwise
     */
    public static function register_hook( $hook_name, $hook_callback = null, $hook_extra_args = null, $extra = false )
    {
        self::st_reset_error();

        if( !($hook_name = self::prepare_hook_name( $hook_name )) )
        {
            self::st_set_error( self::ERR_HOOK_REGISTRATION, self::_t( 'Please provide a valid hook name.' ) );
            return false;
        }

        if( $hook_callback !== null and !is_callable( $hook_callback ) )
        {
            self::st_set_error( self::ERR_HOOK_REGISTRATION, self::_t( 'Couldn\'t add callback for hook %s.', $hook_name ) );
            return false;
        }

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $extra['overwrite_result'] ) )
            $extra['overwrite_result'] = false;
        if( empty( $extra['chained_hook'] ) )
            $extra['chained_hook'] = false;
        if( empty( $extra['stop_chain'] ) )
            $extra['stop_chain'] = false;
        if( !isset( $extra['priority'] ) )
            $extra['priority'] = 10;
        else
            $extra['priority'] = (int)$extra['priority'];

        $hookdata = array();
        $hookdata['callback'] = $hook_callback;
        $hookdata['args'] = $hook_extra_args;
        $hookdata['chained'] = (!empty( $extra['chained_hook'] )?true:false);
        $hookdata['stop_chain'] = (!empty( $extra['stop_chain'] )?true:false);
        $hookdata['overwrite_result'] = (!empty( $extra['overwrite_result'] )?true:false);

        self::$hooks[$hook_name][$extra['priority']][] = $hookdata;

        ksort( self::$hooks[$hook_name], SORT_NUMERIC );

        return true;
    }

    /**
     * @param bool|string $hook_name
     *
     * @return bool
     */
    public static function unregister_hooks( $hook_name = false )
    {
        if( $hook_name === false )
        {
            self::$hooks[$hook_name] = array();
            return true;
        }

        if( !($hook_name = self::prepare_hook_name( $hook_name ))
         or !isset( self::$hooks[$hook_name] ) )
            return false;

        unset( self::$hooks[$hook_name] );

        return true;
    }

    /**
     * @param string $hook_name
     *
     * @return bool
     */
    public static function hook_has_callbacks( $hook_name )
    {
        if( !($hook_name = self::prepare_hook_name( $hook_name ))
         or empty( self::$hooks[$hook_name] ) )
            return false;

        return true;
    }

    /**
     * @param string $hook_name Hook name
     * @param array $hook_args Hook arguments
     * @param array|bool $params Any specific parameters required on this trigger only
     *
     * @return array|null
     */
    public static function trigger_hooks( $hook_name, array $hook_args = array(), $params = false )
    {
        if( !($hook_name = self::prepare_hook_name( $hook_name ))
         or empty( self::$hooks[$hook_name] ) or !is_array( self::$hooks[$hook_name] ) )
            return null;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['stop_on_first_error'] ) )
            $params['stop_on_first_error'] = false;
        else
            $params['stop_on_first_error'] = true;

        if( empty( $hook_args ) or !is_array( $hook_args ) )
            $hook_args = PHS_Hooks::default_common_hook_args();
        else
            $hook_args = PHS_Hooks::hook_args_reset_error( $hook_args );

        foreach( self::$hooks[$hook_name] as $priority => $hooks_array )
        {
            if( empty( $hooks_array ) or !is_array( $hooks_array ) )
                continue;

            foreach( $hooks_array as $hook_callback )
            {
                if( empty( $hook_callback ) or !is_array( $hook_callback )
                 or empty( $hook_callback['callback'] ) )
                    continue;

                if( empty( $hook_callback['args'] ) or !is_array( $hook_callback['args'] ) )
                    $hook_callback['args'] = array();

                $call_hook_args = self::merge_array_assoc( $hook_callback['args'], $hook_args );

                if( ($result = @call_user_func( $hook_callback['callback'], $call_hook_args )) === null
                 or $result === false )
                    continue;

                // If required for this trigger to stop on first error...
                //!!! Although there is an error we return a hook argument array and it is up to you to check
                //!!! if any errors in resulting hook arguments
                if( !empty( $params['stop_on_first_error'] )
                and PHS_Hooks::hook_args_has_error( $result ) )
                    return $result;

                $resulting_buffer = '';
                if( is_array( $result )
                and !empty( $call_hook_args['concatenate_buffer'] ) and is_string( $call_hook_args['concatenate_buffer'] ) )
                {
                    if( isset( $result[$call_hook_args['concatenate_buffer']] ) and is_string( $result[$call_hook_args['concatenate_buffer']] )
                    and isset( $hook_args[$call_hook_args['concatenate_buffer']] ) and is_string( $hook_args[$call_hook_args['concatenate_buffer']] ) )
                        $resulting_buffer = $hook_args[$call_hook_args['concatenate_buffer']].$result[$call_hook_args['concatenate_buffer']];
                }

                if( !empty( $hook_callback['chained'] )
                and is_array( $result ) )
                {
                    if( !empty( $hook_callback['overwrite_result'] ) )
                        $hook_args = $result;
                    else
                        $hook_args = self::merge_array_assoc_recursive( $hook_args, $result );
                }

                if( !empty( $resulting_buffer )
                and !empty( $call_hook_args['concatenate_buffer'] ) and is_string( $call_hook_args['concatenate_buffer'] )
                and isset( $hook_args[$call_hook_args['concatenate_buffer']] ) and is_string( $hook_args[$call_hook_args['concatenate_buffer']] ) )
                {
                    $hook_args[$call_hook_args['concatenate_buffer']] = $resulting_buffer;
                    $resulting_buffer = '';
                }
            }
        }

        // Return final hook arguments as result of hook calls
        return $hook_args;
    }

    public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext )
    {
        if( !error_reporting() )
            return true;

        $backtrace_str = self::st_debug_call_backtrace();

        $errno = (int)$errno;
        $error_type = 'Unknown error type';
        switch( $errno )
        {
            case E_ERROR:
            case E_USER_ERROR:
                // end all buffers
                while( @ob_end_flush() );

                $error_type = 'error';
            break;

            case E_WARNING:
            case E_USER_WARNING:
                $error_type = 'warning';
            break;

            case E_NOTICE:
            case E_USER_NOTICE:
                $error_type = 'notice';
            break;
        }

        if( !@class_exists( '\\phs\\PHS_Scope', false ) )
        {
            echo $error_type.': ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr."\n";
            echo $backtrace_str."\n";
        } else
        {
            $current_scope = PHS_Scope::current_scope();
            switch( $current_scope )
            {
                default:
                case PHS_Scope::SCOPE_AJAX:
                case PHS_Scope::SCOPE_WEB:
                    echo '<strong>'.$error_type.'</strong> ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr.'<br/>'."\n";
                    echo '<pre>'.$backtrace_str.'</pre>';
                break;

                case PHS_Scope::SCOPE_API:

                    if( self::st_debugging_mode() )
                    {
                        if( !@class_exists( '\\phs\\libraries\\PHS_Notifications', false ) )
                            $error_arr = array(
                                'backtrace' => $backtrace_str,
                                'error_code' => $errno,
                                'error_file' => $errfile,
                                'error_line' => $errline,
                                'response_status' => array(
                                    'success_messages' => array(),
                                    'warning_messages' => array(),
                                    'error_messages' => array( $errstr ),
                                )
                            );
                        else
                            $error_arr = array(
                                'backtrace' => $backtrace_str,
                                'error_code' => $errno,
                                'error_file' => $errfile,
                                'error_line' => $errline,
                                'response_status' => array(
                                    'success_messages' => \phs\libraries\PHS_Notifications::notifications_success(),
                                    'warning_messages' => \phs\libraries\PHS_Notifications::notifications_warnings(),
                                    'error_messages' => array_merge( \phs\libraries\PHS_Notifications::notifications_errors(), array( $errstr ) ),
                                )
                            );
                    } else
                    {
                        $error_arr = array(
                            'backtrace' => '',
                            'error_code' => -1,
                            'error_file' => @basename( $errfile ),
                            'error_line' => $errline,
                            'response_status' => array(
                                'success_messages' => array(),
                                'warning_messages' => array(),
                                'error_messages' => array( 'Internal error' ),
                            )
                        );
                    }

                    if( !@headers_sent() )
                    {
                        @header( 'HTTP/1.1 500 Application error' );
                        @header( 'Content-Type: application/json' );
                    }

                    echo @json_encode( $error_arr );

                    exit;
                break;

                case PHS_Scope::SCOPE_AGENT:
                case PHS_Scope::SCOPE_BACKGROUND:
                    PHS_Logger::logf( $error_type.': ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr."\n".$backtrace_str );
                break;
            }
        }

        if( @class_exists( '\\phs\\PHS_Logger', false ) )
            PHS_Logger::logf( $error_type.': ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr."\n".$backtrace_str, PHS_Logger::TYPE_DEBUG );

        if( $errno === E_ERROR or $errno === E_USER_ERROR )
            exit( 1 );

        return true;
    }

    /** @noinspection ForgottenDebugOutputInspection */
    public static function tick_handler()
    {
        var_dump( self::st_debug_call_backtrace( 1 ) );
    }
}
