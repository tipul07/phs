<?php

namespace phs;

use phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Controller;
use \phs\libraries\PHS_Hooks;

final class PHS extends PHS_Registry
{
    const ERR_HOOK_REGISTRATION = 2000, ERR_LOAD_MODEL = 2001, ERR_LOAD_CONTROLLER = 2002, ERR_LOAD_ACTION = 2003, ERR_LOAD_VIEW = 2004, ERR_LOAD_PLUGIN = 2005,
          ERR_LOAD_SCOPE = 2006,
          ERR_ROUTE = 2007, ERR_EXECUTE_ROUTE = 2008, ERR_THEME = 2009, ERR_SCOPE = 2010;

    const REQUEST_FULL_HOST = 'request_full_host', REQUEST_HOST = 'request_host', REQUEST_PORT = 'request_port', REQUEST_HTTPS = 'request_https',
          COOKIE_DOMAIN = 'cookie_domain',

          ROUTE_PLUGIN = 'route_plugin', ROUTE_CONTROLLER = 'route_controller', ROUTE_ACTION = 'route_action',

          CURRENT_THEME = 'c_theme', DEFAULT_THEME = 'd_theme',

          PHS_START_TIME = 'phs_start_time', PHS_BOOTSTRAP_END_TIME = 'phs_bootstrap_end_time', PHS_END_TIME = 'phs_end_time';

    const ROUTE_PARAM = '_route',
          ROUTE_DEFAULT_CONTROLLER = 'index',
          ROUTE_DEFAULT_ACTION = 'index';

    const RUNNING_ACTION = 'r_action', RUNNING_CONTROLLER = 'r_controller';

    private static $inited = false;
    private static $instance = false;
    private static $hooks = array();

    private static $_INTERPRET_SCRIPT = 'index';
    private static $_BACKGROUND_SCRIPT = '_bg';

    function __construct()
    {
        parent::__construct();

        self::init();
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

        $cookie_domain = PHS_DEFAULT_DOMAIN;
        $request_full_host = '';
        $request_host = '';
        $request_port = '';

        if( empty( $_SERVER['HTTP_HOST'] ) )
        {
            $request_full_host = $cookie_domain;
            $request_host = $cookie_domain;
        } else
        {
            // sanity cleaning (request comes from outside), $request_full_host will be used to load domain specific settings...
            $request_full_host = str_replace(
                                    array( '..', '/', '~', '<', '>', '|' ),
                                    array( '.',  '',  '',  '',  '',  '' ),
                                    $_SERVER['HTTP_HOST'] );

            if( strstr( $request_full_host, ':' ) !== false
            and ($host_details = explode( ':', $request_full_host, 2 )) )
            {
                $request_host = $host_details[0];
                $request_port = (!empty( $host_details[1] )?$host_details[1]:'');
            } else
            {
                $request_host = $request_full_host;
            }

            $cookie_domain = $request_host;
        }

        if( isset( $_SERVER['HTTPS'] )
        and ($_SERVER['HTTPS'] == 'on' or $_SERVER['HTTPS'] == '1') )
            self::set_data( self::REQUEST_HTTPS, true );
        else
            self::set_data( self::REQUEST_HTTPS, false );

        self::set_data( self::REQUEST_FULL_HOST, $request_full_host );
        self::set_data( self::REQUEST_HOST, $request_host );
        self::set_data( self::REQUEST_PORT, $request_port );
        self::set_data( self::COOKIE_DOMAIN, $cookie_domain );

        self::$inited = true;
    }

    private static function reset_registry()
    {
        self::set_data( self::REQUEST_FULL_HOST, '' );
        self::set_data( self::REQUEST_HOST, '' );
        self::set_data( self::REQUEST_PORT, '' );
        self::set_data( self::COOKIE_DOMAIN, '' );
        self::set_data( self::REQUEST_HTTPS, false );

        self::set_data( self::CURRENT_THEME, '' );
        self::set_data( self::DEFAULT_THEME, '' );

        self::set_data( self::RUNNING_ACTION, array(
            'name' => '',
            'instance' => false ) );
        self::set_data( self::RUNNING_CONTROLLER, array(
            'name' => '',
            'instance' => false ) );
    }

    public static function prevent_session()
    {
        return (defined( 'PHS_PREVENT_SESSION' ) and constant( 'PHS_PREVENT_SESSION' ));
    }

    public static function user_logged_in( $force = false )
    {
        return (($cuser_arr = self::current_user( $force )) and !empty( $cuser_arr['id'] ));
    }

    public static function current_user( $force = false )
    {
        if( !($hook_args = self::_current_user_trigger( $force ))
         or empty( $hook_args['user_db_data'] ) or !is_array( $hook_args['user_db_data'] )
         or empty( $hook_args['user_db_data']['id'] ) )
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
     * @param PHS_Action $action_obj
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
     * @param PHS_Controller $action_obj
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
        if( empty( $theme )
         or !($theme = PHS_Instantiable::safe_escape_theme_name( $theme ))
         or !@is_dir( PHS_THEMES_DIR . $theme ) or !@is_readable( PHS_THEMES_DIR . $theme ) )
        {
            self::st_set_error( self::ERR_THEME, self::_t( 'Theme %s doesn\'t exist or directory is not readable.', ($theme?$theme:'N/A') ) );
            return false;
        }

        return $theme;
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
            'PHS_DOMAIN' => 'PHS_DEFAULT_DOMAIN',
            'PHS_PORT' => 'PHS_DEFAULT_PORT',
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

    public static function db_user()
    {

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
    public static function parse_route( $route = false )
    {
        self::st_reset_error();

        //if( empty( $route ) )
        //{
        //    self::st_set_error( self::ERR_ROUTE, self::_t( 'Empty route.' ) );
        //    return false;
        //}

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
     * Change default route interpret script (default is index). .php file extension will be added by platform.
     *
     * @param bool|string $script New interpreter script (default is index). No extension should be provided (.php will be appended)
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

    public static function get_background_path()
    {
        return PHS_PATH.self::background_script();
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

    public static function current_url()
    {
        if( ($plugin = self::get_data( self::ROUTE_PLUGIN )) )
            $plugin = false;
        if( ($controller = self::get_data( self::ROUTE_CONTROLLER )) )
            $controller = false;
        if( ($action = self::get_data( self::ROUTE_ACTION )) )
            $action = false;

        @parse_str( $_SERVER['QUERY_STRING'], $query_string );

        if( empty( $query_string ) )
            $query_string = false;

        return self::url( array( 'p' => $plugin, 'c' => $controller, 'a' => $action ), $query_string );
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

    public static function url( $params = false, $args = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( empty( $params['force_https'] ) )
            $params['force_https'] = false;
        if( empty( $params['p'] ) )
            $params['p'] = false;
        if( empty( $params['c'] ) )
            $params['c'] = false;
        if( empty( $params['a'] ) )
            $params['a'] = self::ROUTE_DEFAULT_ACTION;

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

        //$hook_params = array(
        //  'args' => $new_args,
        //  'params' => $params,
        //);
        //
        //if( ($hook_result = self::trigger_hooks( PHS_Hooks::H_USER_DB_DETAILS, $hook_params ))
        //
        //H_URL_PARAMS

        return self::get_interpret_url( $params['force_https'] ).($query_string!=''?'?'.$query_string:'');
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
            self::st_set_error( self::ERR_EXECUTE_ROUTE, self::_t( 'Couldn\'t obtain controller instance.' ) );
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

        return $scope_obj->generate_response();
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
     * @param string $plugin_name
     * @param string|bool $plugin
     *
     * @return false|\phs\libraries\PHS_Plugin Returns false on error or an instance of loaded plugin
     */
    public static function load_plugin( $plugin_name )
    {
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
     * @param array|false $extra            Extra details related to current hook:
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
            $hook_args = array();

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

                $call_hook_args = array_merge( $hook_callback['args'], $hook_args );

                $result = @call_user_func( $hook_callback['callback'], $call_hook_args );

                if( !empty( $hook_callback['chained'] )
                and is_array( $result ) )
                    $hook_args = array_merge( $hook_args, $result );
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
