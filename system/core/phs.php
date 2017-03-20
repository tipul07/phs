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
    const ERR_HOOK_REGISTRATION = 2000, ERR_LOAD_MODEL = 2001, ERR_LOAD_CONTROLLER = 2002, ERR_LOAD_ACTION = 2003, ERR_LOAD_VIEW = 2004, ERR_LOAD_PLUGIN = 2005,
          ERR_LOAD_SCOPE = 2006, ERR_ROUTE = 2007, ERR_EXECUTE_ROUTE = 2008, ERR_THEME = 2009, ERR_SCOPE = 2010,
          ERR_SCRIPT_FILES = 2011, ERR_LIBRARY = 2012;

    const REQUEST_HOST_CONFIG = 'request_host_config', REQUEST_HOST = 'request_host', REQUEST_PORT = 'request_port', REQUEST_HTTPS = 'request_https',

          ROUTE_PLUGIN = 'route_plugin', ROUTE_CONTROLLER = 'route_controller', ROUTE_ACTION = 'route_action',

          CURRENT_THEME = 'c_theme', DEFAULT_THEME = 'd_theme',

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

    function __construct()
    {
        parent::__construct();

        self::init();
    }

    public static function get_distribution_plugins()
    {
        // All plugins that come with the framework (these will be installed by default)
        // Rest of plugins will be managed in plugins interface in admin interface
        return array( 'accounts', 'admin', 'messages', 'captcha', 'emails', 'notifications' );
    }

    public static function get_core_models()
    {
        // !!! Don't change order of models here unless you know what you'r doing !!!
        // Models should be placed in this array after their dependencies (eg. bg_jobs depends on agent_jobs - it adds an agent job for timed bg jobs)
        return array( 'agent_jobs', 'bg_jobs', 'roles' );
    }

    /**
     * Check what server receives in request
     */
    public static function init()
    {
        if( self::$inited )
            return;

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
        if( isset( $_SERVER['HTTPS'] )
        and ($_SERVER['HTTPS'] == 'on' or $_SERVER['HTTPS'] == '1') )
            return true;

        return false;
    }

    /**
     * Returns request full hostname (based on this system will check for custom configuration files)
     *
     * @return string Returns request full hostname (based on this system will check for custom configuration files)
     */
    public static function get_request_host_config()
    {
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

    public static function valid_theme( $theme )
    {
        self::st_reset_error();

        if( empty( $theme )
         or !($theme = PHS_Instantiable::safe_escape_theme_name( $theme ))
         or !@is_dir( PHS_THEMES_DIR . $theme ) or !@is_readable( PHS_THEMES_DIR . $theme ) )
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

        if( !@is_dir( PHS_THEMES_DIR . $theme. '/' . PHS_Instantiable::LANGUAGES_DIR ) or !@is_readable( PHS_THEMES_DIR . $theme ) )
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

    public static function set_defaut_theme( $theme )
    {
        if( !($theme = self::valid_theme( $theme )) )
            return false;

        self::set_data( self::DEFAULT_THEME, $theme );

        return true;
    }

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

    public static function get_base_url( $force_https = false )
    {
        if( !empty( $force_https )
         or self::get_data( self::REQUEST_HTTPS ) )
        {
            // if domain settings are set
            if( defined( 'PHS_HTTPS' ) )
                return PHS_HTTPS;
            // if default domain settings are set
            elseif( defined( 'PHS_DEFAULT_HTTPS' ) )
                return PHS_DEFAULT_HTTPS;
        } else
        {
            // if domain settings are set
            if( defined( 'PHS_HTTP' ) )
                return PHS_HTTP;
            // if default domain settings are set
            elseif( defined( 'PHS_DEFAULT_HTTP' ) )
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
     * @param string|bool $route If a non empty string, method will try parsing provided route, otherwise exract route from context
     * @return bool|array Returns true on success or false on error
     */
    public static function parse_route( $route = false )
    {
        self::st_reset_error();

        $route_parts = array();
        if( !empty( $route ) )
        {
            if( strstr( $route, '-' ) !== false )
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
        }

        $plugin = false;
        $rp_count = count( $route_parts );
        if( $rp_count == 1 )
        {
            $action = (!empty( $route_parts[0] )?trim( $route_parts[0] ):'');
        } elseif( $rp_count == 2 )
        {
            $plugin = (!empty( $route_parts[0] )?trim( $route_parts[0] ):false);
            $action = (!empty( $route_parts[1] )?trim( $route_parts[1] ):'');
        } elseif( $rp_count == 3 )
        {
            $plugin = (!empty( $route_parts[0] )?trim( $route_parts[0] ):false);
            $controller = (!empty( $route_parts[1] )?trim( $route_parts[1] ):'');
            $action = (!empty( $route_parts[2] )?trim( $route_parts[2] ):'');
        }

        if( empty( $controller ) )
            $controller = self::ROUTE_DEFAULT_CONTROLLER;
        if( empty( $action ) )
            $action = self::ROUTE_DEFAULT_ACTION;

        if( ($plugin !== false and !($plugin = PHS_Instantiable::safe_escape_plugin_name( $plugin )))
         or !($controller = PHS_Instantiable::safe_escape_class_name( $controller ))
         or !($action = PHS_Instantiable::safe_escape_action_name( $action ))
        )
        {
            self::st_set_error( self::ERR_ROUTE, self::_t( 'Bad route in request.' ) );
            return false;
        }

        return array(
            'plugin' => $plugin,
            'controller' => $controller,
            'action' => $action,
        );
    }

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


        $route_parts = false;
        if( is_string( $route )
        and !($route_parts = self::parse_route( $route )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t parse route.' ) );
            return false;
        }

        if( is_array( $route ) )
            $route_parts = $route;

        if( empty( $route_parts ) or !is_array( $route_parts ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Route is invalid.' ) );

            return false;
        }

        if( !isset( $route_parts['plugin'] ) )
            $route_parts['plugin'] = false;
        if( !isset( $route_parts['controller'] ) )
            $route_parts['controller'] = false;
        if( !isset( $route_parts['action'] ) )
            $route_parts['action'] = false;

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
        and !($controller_obj = self::load_controller( $route_parts['controller'], $plugin_obj->instance_plugin_name() )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t instantiate controller from route.' ) );
            return false;
        }

        /** @var bool|\phs\libraries\PHS_Action $action_obj */
        $action_obj = false;
        if( empty( $route_parts['action'] )
         or !($action_obj = self::load_action( $route_parts['action'], $plugin_obj->instance_plugin_name() )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t instantiate action from route.' ) );
            return false;
        }

        if( !empty( $params['action_accepts_scopes'] )
        and ($action_scopes = $action_obj->allowed_scopes())
        and is_array( $action_scopes ) )
        {
            foreach( $params['action_accepts_scopes'] as $scope )
            {
                if( !in_array( $scope, $action_scopes ) )
                {
                    $scope_title = '(???)';
                    if( ($scope_details = PHS_Scope::valid_scope( $scope )) )
                        $scope_title = $scope_details['title'];

                    self::st_set_error( self::ERR_ROUTE, self::_t( 'Action %s is not ment to run in scope %s.', $route_parts['action'], $scope_title ) );
                    return false;
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

        if( !($route_parts = self::parse_route( $route )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_ROUTE, self::_t( 'Couldn\'t parse route.' ) );
            return false;
        }

        self::set_data( self::ROUTE_PLUGIN, $route_parts['plugin'] );
        self::set_data( self::ROUTE_CONTROLLER, $route_parts['controller'] );
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

    public static function get_interpret_url( $force_https = false )
    {
        if( !($base_url = self::get_base_url( $force_https )) )
            return false;

        if( substr( $base_url, -1 ) != '/' )
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

        if( substr( $base_url, -1 ) != '/' )
            $base_url .= '/';

        return $base_url.self::ajax_script();
    }

    public static function current_url()
    {
        if( !($plugin = self::get_data( self::ROUTE_PLUGIN )) )
            $plugin = false;
        if( !($controller = self::get_data( self::ROUTE_CONTROLLER ))
         or $controller == self::ROUTE_DEFAULT_CONTROLLER )
            $controller = false;
        if( !($action = self::get_data( self::ROUTE_ACTION ))
         or $action == self::ROUTE_DEFAULT_ACTION )
            $action = false;

        if( !($query_string = self::current_page_query_string_as_array()) )
            $query_string = false;

        return self::url( array( 'p' => $plugin, 'c' => $controller, 'a' => $action ), $query_string );
    }

    public static function current_page_query_string_as_array( $params = false )
    {
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

    public static function route_from_parts( $parts = false )
    {
        if( empty( $parts ) or !is_array( $parts ) )
            $parts = array();

        if( empty( $parts['p'] ) )
            $parts['p'] = false;
        if( empty( $parts['c'] ) )
            $parts['c'] = false;
        if( empty( $parts['a'] ) )
            $parts['a'] = self::ROUTE_DEFAULT_ACTION;

        if( (!empty( $parts['p'] ) and !self::safe_escape_route_parts( $parts['p'] ))
         or (!empty( $parts['c'] ) and !self::safe_escape_route_parts( $parts['c'] ))
         or (!empty( $parts['a'] ) and !self::safe_escape_route_parts( $parts['a'] )) )
            return false;

        $route = false;
        if( !empty( $parts['c'] ) )
        {
            if( empty( $parts['p'] ) )
                $parts['p'] = '';

            $route = $parts['p'].'/'.$parts['c'].'/'.$parts['a'];
        } else
        {
            if( empty( $parts['p'] ) )
                $route = $parts['a'];
            else
                $route = $parts['p'].'-'.$parts['a'];
        }

        return $route;
    }

    public static function url( $params = false, $args = false, $extra = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $params['force_https'] ) )
            $params['force_https'] = false;
        if( empty( $params['p'] ) )
            $params['p'] = false;
        if( empty( $params['c'] ) )
            $params['c'] = false;
        if( empty( $params['a'] ) )
            $params['a'] = self::ROUTE_DEFAULT_ACTION;

        if( empty( $extra['raw_params'] ) )
            $extra['raw_params'] = false;

        if( !($route = self::route_from_parts( $params )) )
            return '#invalid_path['.
                   (!empty( $params['p'] )?$params['p']:'').'::'.
                   (!empty( $params['c'] )?$params['c']:'').'::'.
                   (!empty( $params['a'] )?$params['a']:'').']';

        $new_args = array();
        $new_args[self::ROUTE_PARAM] = $route;

        if( isset( $args[self::ROUTE_PARAM] ) )
            unset( $args[self::ROUTE_PARAM] );

        foreach( $args as $key => $val )
            $new_args[$key] = $val;

        if( !($query_string = @http_build_query( $new_args )) )
            $query_string = '';

        if( !empty( $extra['raw_params'] ) and is_array( $extra['raw_params'] ) )
        {
            // Parameters that shouldn't be run through http_build_query as values will be rawurlencoded and we might add javascript code in parameters
            // eg. $extra['raw_params'] might be an id passed as javascript function parameter
            if( ($raw_query = array_to_query_string( $extra['raw_params'], array( 'raw_encode_values' => false ) )) )
                $query_string .= ($query_string!=''?'&':'').$raw_query;
        }

        //$hook_params = array(
        //  'args' => $new_args,
        //  'params' => $params,
        //);
        //
        //if( ($hook_result = self::trigger_hooks( PHS_Hooks::H_USER_DB_DETAILS, $hook_params ))
        //
        //H_URL_PARAMS

        if( empty( $extra['for_scope'] ) or !PHS_Scope::valid_scope( $extra['for_scope'] ) )
            $extra['for_scope'] = PHS_Scope::SCOPE_WEB;

        switch( $extra['for_scope'] )
        {
            default:
                return self::get_interpret_url( $params['force_https'] ).($query_string!=''?'?'.$query_string:'');

            case PHS_Scope::SCOPE_AJAX:
                return self::get_ajax_url( $params['force_https'] ).($query_string!=''?'?'.$query_string:'');
        }
    }

    public static function relative_url( $url )
    {
        // check on "non https" url first
        if( ($base_url = self::get_base_url( false ))
        and ($base_len = strlen( $base_url ))
        and substr( $url, 0, $base_len ) == $base_url )
            return substr( $url, $base_len );

        // check "https" url
        if( ($base_url = self::get_base_url( true ))
        and ($base_len = strlen( $base_url ))
        and substr( $url, 0, $base_len ) == $base_url )
            return substr( $url, $base_len );

        return $url;
    }

    public static function from_relative_url( $url, $force_https = false )
    {
        if( ($base_url = self::get_base_url( $force_https ))
        and ($base_len = strlen( $base_url ))
        and substr( $url, 0, $base_len ) == $base_url )
            return $url;

        return $base_url.$url;
    }

    public static function relative_path( $path )
    {
        if( ($base_len = strlen( PHS_PATH ))
        and substr( $path, 0, $base_len ) == PHS_PATH )
            return substr( $path, $base_len );

        return $path;
    }

    public static function from_relative_path( $path )
    {
        if( ($base_len = strlen( PHS_PATH ))
        and substr( $path, 0, $base_len ) == PHS_PATH )
            return $path;

        return PHS_PATH.$path;
    }

    public static function get_route_details()
    {
        if( ($controller = self::get_data( self::ROUTE_CONTROLLER )) === null )
        {
            self::set_route();

            if( ($controller = self::get_data( self::ROUTE_CONTROLLER )) === null )
                return false;
        }

        $return_arr = array();
        $return_arr[self::ROUTE_PLUGIN] = self::get_data( self::ROUTE_PLUGIN );
        $return_arr[self::ROUTE_CONTROLLER] = $controller;
        $return_arr[self::ROUTE_ACTION] = self::get_data( self::ROUTE_ACTION );

        return $return_arr;
    }

    public static function get_route_details_for_url()
    {
        if( !($route_arr = self::get_route_details()) )
            return false;

        return array(
            'p' => $route_arr[self::ROUTE_PLUGIN],
            'c' => $route_arr[self::ROUTE_CONTROLLER],
            'a' => $route_arr[self::ROUTE_ACTION],
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

    public static function execute_route( $params = false )
    {
        self::st_reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['die_on_error'] ) )
            $params['die_on_error'] = true;
        else
            $params['die_on_error'] = (!empty( $params['die_on_error'] )?true:false);

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

        elseif( !($action_result = $controller_obj->execute_action( $route_details[self::ROUTE_ACTION] )) )
        {
            if( $controller_obj->has_error() )
                self::st_copy_error( $controller_obj );
            else
                self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Error executing action [%s].', $route_details[self::ROUTE_ACTION] ) );
        }

        else
        {
            if( !empty( $action_result['scope'] )
            and $action_result['scope'] != PHS_Scope::current_scope() )
                PHS_Scope::current_scope( $action_result['scope'] );

            if( !($scope_obj = PHS_Scope::get_scope_instance()) )
            {
                if( !self::st_has_error() )
                    self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Error spawning scope instance.' ) );
            }
        }

        if( !empty( $params['die_on_error'] ) and self::st_has_error() )
        {
            if( self::st_has_error() )
                $error_msg = self::st_get_error_message();
            else
                $error_msg = self::_t( 'Couldn\'t instantiate scope object.' );

            PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_DEF_DEBUG );
            echo $error_msg;

            exit;
        }

        if( empty( $scope_obj ) )
            return false;

        return $scope_obj->generate_response( $action_result );
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
     * Returns an instance of a model. If model is part of a plugin $plugin will contain name of that plugin.
     *
     * @param string $model Model to be loaded (part of class name after PHS_Model_)
     * @param string|bool $plugin Plugin where model is located (false means a core model)
     *
     * @return false|\phs\libraries\PHS_Model Returns false on error or an instance of loaded model
     */
    public static function load_model( $model, $plugin = false )
    {
        if( !($model_name = PHS_Instantiable::safe_escape_class_name( $model )) )
        {
            self::st_set_error( self::ERR_LOAD_MODEL, self::_t( 'Couldn\'t load model %s from plugin %s.', $model, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Model_'.ucfirst( strtolower( $model_name ) );

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
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

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
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

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
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

            default:
                self::st_set_error( self::ERR_SCRIPT_FILES, self::_t( 'Invalid instance type to obtain script files list.' ) );
                return false;
            break;
        }

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
        {
            $plugin = false;

            if( $instance_type == PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
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

        // Get generic information about an index controller to obtain paths to be checked...
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
        if( $plugin != PHS_Instantiable::CORE_PLUGIN
        and $instance_type == PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
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
                    if( substr( $script_file_name, 0, 4 ) != 'phs_'
                     or substr( $script_file_name, -4 ) != '.php' )
                        continue;

                    $instance_file_name = substr( substr( $script_file_name, 4 ), 0, -4 );

                    $resulting_instance_names[] = $instance_file_name;
                }
            }
        }

        return $resulting_instance_names;
    }

    /**
     * @param string $action
     * @param string|bool $plugin
     *
     * @return false|\phs\libraries\PHS_Action Returns false on error or an instance of loaded action
     */
    public static function load_action( $action, $plugin = false )
    {
        if( !($action_name = PHS_Instantiable::safe_escape_class_name( $action )) )
        {
            self::st_set_error( self::ERR_LOAD_ACTION, self::_t( 'Couldn\'t load action %s from plugin %s.', $action, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Action_'.ucfirst( strtolower( $action_name ) );

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        /** @var \phs\libraries\PHS_Action */
        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_ACTION )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_ACTION, self::_t( 'Couldn\'t obtain instance for action %s from plugin %s .', $action, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * @param string $scope
     * @param string|bool $plugin
     
      
*
*@return false|\phs\PHS_Scope Returns false on error or an instance of loaded scope
     */
    public static function load_scope( $scope, $plugin = false )
    {
        if( !($scope_name = PHS_Instantiable::safe_escape_class_name( $scope )) )
        {
            self::st_set_error( self::ERR_LOAD_SCOPE, self::_t( 'Couldn\'t load scope %s from plugin %s.', $scope, (empty( $plugin )?PHS_Instantiable::CORE_PLUGIN:$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Scope_'.ucfirst( strtolower( $scope_name ) );

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
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

        if( $plugin_name == PHS_Instantiable::CORE_PLUGIN )
            $plugin_name = false;

        if( empty( $plugin_name )
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

        if( !is_null( $hook_callback ) and !is_callable( $hook_callback ) )
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
            $extra['priority'] = intval( $extra['priority'] );

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

    public static function trigger_hooks( $hook_name, array $hook_args = array() )
    {
        if( !($hook_name = self::prepare_hook_name( $hook_name ))
         or empty( self::$hooks[$hook_name] ) or !is_array( self::$hooks[$hook_name] ) )
            return null;

        if( empty( $hook_args ) or !is_array( $hook_args ) )
            $hook_args = PHS_Hooks::default_common_hook_args();
        else
            $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_common_hook_args() );

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

                $resulting_buffer = '';
                if( !empty( $call_hook_args['concatenate_buffer'] ) and is_string( $call_hook_args['concatenate_buffer'] ) )
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

        echo '<pre>'.self::st_debug_call_backtrace().'</pre>';

        switch( $errno )
        {
            case E_ERROR:
            case E_USER_ERROR:
                // end all buffers
                while( @ob_end_flush() );

                echo "<b>ERROR</b> [$errno] ($errfile:$errline) $errstr<br />\n";
                exit(1);
            break;

            case E_WARNING:
            case E_USER_WARNING:
                echo "<b>WARNING</b> [$errno] ($errfile:$errline) $errstr<br />\n";
            break;

            case E_NOTICE:
            case E_USER_NOTICE:
                echo "<b>NOTICE</b> [$errno] ($errfile:$errline) $errstr<br />\n";
            break;

            default:
                echo "Unknown error type: [$errno] ($errfile:$errline) $errstr<br />\n";
            break;
        }

        return true;
    }
}
