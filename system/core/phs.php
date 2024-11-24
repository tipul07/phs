<?php

namespace phs;

use phs\libraries\PHS_Event;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Contract;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Controller;
use phs\libraries\PHS_Record_data;
use phs\libraries\PHS_Graphql_Type;
use phs\libraries\PHS_Instantiable;
use phs\system\core\views\PHS_View;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Undefined_instantiable;
use phs\system\core\events\routing\PHS_Event_Route;
use phs\system\core\events\routing\PHS_Event_Url_rewrite;

final class PHS extends PHS_Registry
{
    public const ERR_HOOK_REGISTRATION = 2000,
        ERR_LOAD_MODEL = 2001, ERR_LOAD_CONTROLLER = 2002, ERR_LOAD_ACTION = 2003, ERR_LOAD_VIEW = 2004, ERR_LOAD_PLUGIN = 2005,
        ERR_LOAD_SCOPE = 2006, ERR_ROUTE = 2007, ERR_EXECUTE_ROUTE = 2008, ERR_THEME = 2009, ERR_SCOPE = 2010,
        ERR_SCRIPT_FILES = 2011, ERR_LIBRARY = 2012, ERR_LOAD_CONTRACT = 2013, ERR_LOAD_EVENT = 2014, ERR_LOAD_GRAPHQL = 2015;

    public const ACTION_DIR_ACTION_SEPARATOR = '__';

    public const REQUEST_HOST_CONFIG = 'request_host_config', REQUEST_HOST = 'request_host', REQUEST_PORT = 'request_port', REQUEST_HTTPS = 'request_https',

        ROUTE_PLUGIN = 'route_plugin', ROUTE_CONTROLLER = 'route_controller', ROUTE_ACTION = 'route_action', ROUTE_ACTION_DIR = 'route_action_dir',

        CURRENT_THEME = 'c_theme', DEFAULT_THEME = 'd_theme', CASCADE_THEMES = 'cascade_themes',

        PHS_START_TIME = 'phs_start_time', PHS_BOOTSTRAP_END_TIME = 'phs_bootstrap_end_time', PHS_END_TIME = 'phs_end_time',

        // Generic current page settings (saved as array)
        PHS_PAGE_SETTINGS = 'phs_page_settings';

    public const ROUTE_PARAM = '_route', ROUTE_DEFAULT_CONTROLLER = 'index', ROUTE_DEFAULT_ACTION = 'index';

    public const RUNNING_ACTION = 'r_action', RUNNING_CONTROLLER = 'r_controller';

    // "Hard-coded", Lowest level theme
    public const BASE_THEME = '_phs_base', THEME_DIST_DIRNAME = 'default.dist';

    private static bool $inited = false;

    private static ?PHS $instance = null;

    private static array $hooks = [];

    private static array $_core_libraries_instances = [];

    private static string $_INTERPRET_SCRIPT = 'index';

    private static string $_BACKGROUND_SCRIPT = '_bg';

    private static string $_AGENT_SCRIPT = '_agent_bg';

    private static string $_AJAX_SCRIPT = '_ajax';

    private static string $_API_SCRIPT = '_api';

    private static string $_REMOTE_SCRIPT = '_remote';

    private static string $_UPDATE_SCRIPT = '_update';

    public function __construct()
    {
        parent::__construct();

        self::init();
    }

    public static function get_distribution_plugins() : array
    {
        // All plugins that come with the framework (these will be installed by default)
        // Rest of plugins will be managed in plugins interface in admin interface
        return ['phs_libs', 'accounts', 'accounts_3rd', 'admin', 'backup', 'bbeditor', 'captcha', 'cookie_notice',
            'emails', 'hubspot', 'mailchimp', 'messages', 'mobileapi', 'notifications', 'remote_phs', 'sendgrid', ];
    }

    public static function get_always_active_plugins() : array
    {
        // These plugins cannot be inactivated as they provide basic functionality for the platform
        return ['accounts', 'admin', 'captcha', 'notifications'];
    }

    public static function get_core_models() : array
    {
        // !!! Don't change order of models here unless you know what you're doing !!!
        // Models should be placed in this array depending on their dependencies
        // (e.g. bg_jobs depends on agent_jobs - it adds an agent job for timed bg jobs)
        return [
            'migrations', 'tenants', 'agent_jobs', 'bg_jobs', 'roles', 'api_keys',
            'agent_jobs_monitor', 'api_monitor', 'data_retention', 'request_queue',
        ];
    }

    /**
     * Check what server receives in request
     */
    public static function init() : void
    {
        if (self::$inited) {
            return;
        }

        if (empty($_SERVER)) {
            $_SERVER = [];
        }

        self::reset_registry();

        self::set_data(self::PHS_START_TIME, microtime(true));
        self::set_data(self::PHS_BOOTSTRAP_END_TIME, 0);
        self::set_data(self::PHS_END_TIME, 0);

        $secure_request = self::detect_secure_request();

        if (empty($_SERVER['SERVER_NAME'])) {
            $request_host = '127.0.0.1';
        } else {
            $request_host = $_SERVER['SERVER_NAME'];
        }

        if (empty($_SERVER['SERVER_PORT'])) {
            if ($secure_request) {
                $request_port = '443';
            } else {
                $request_port = '80';
            }
        } else {
            $request_port = $_SERVER['SERVER_PORT'];
        }

        if ($secure_request) {
            self::set_data(self::REQUEST_HTTPS, true);
        } else {
            self::set_data(self::REQUEST_HTTPS, false);
        }

        self::set_data(self::REQUEST_HOST_CONFIG, self::get_request_host_config());
        self::set_data(self::REQUEST_HOST, $request_host);
        self::set_data(self::REQUEST_PORT, $request_port);

        self::$inited = true;
    }

    /**
     * Checks if there is a config file to be included and return its path. We don't include it here as we want to include in global scope...
     *
     * @param null|string $config_dir Directory where we should check for config file
     * @return null|string File to be included or null if nothing to include
     */
    public static function check_custom_config(?string $config_dir = null) : ?string
    {
        if (!self::$inited) {
            self::init();
        }

        if (empty($config_dir)) {
            if (!defined('PHS_CONFIG_DIR')) {
                return null;
            }

            $config_dir = PHS_CONFIG_DIR;
        }

        if (!($host_config = self::get_data(self::REQUEST_HOST_CONFIG))
            || empty($host_config['server_name'])) {
            return null;
        }

        if (empty($host_config['server_port'])) {
            $host_config['server_port'] = '';
        }

        if (!empty($host_config['server_port'])
            && @is_file($config_dir.$host_config['server_name'].'_'.$host_config['server_port'].'.php')) {
            return $config_dir.$host_config['server_name'].'_'.$host_config['server_port'].'.php';
        }

        if (@is_file($config_dir.$host_config['server_name'].'.php')) {
            return $config_dir.$host_config['server_name'].'.php';
        }

        return null;
    }

    /**
     * @return bool Tells if current request is done on a secure connection (HTTPS || HTTP)
     */
    public static function detect_secure_request() : bool
    {
        return (!empty($_SERVER)
             && isset($_SERVER['HTTPS'])
             && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1' || $_SERVER['HTTPS'] === 1))
               // If we run in cli mode assume we are on https calls in order to force https URL generation
               || PHP_SAPI === 'cli';
    }

    /**
     * Returns request full hostname (based on this system will check for custom configuration files)
     *
     * @return array Returns array with request full hostname and port (based on this system will check for custom configuration files)
     */
    public static function get_request_host_config() : array
    {
        $_SERVER ??= [];

        if (empty($_SERVER['SERVER_NAME'])) {
            $server_name = 'default';
        } else {
            $server_name = trim(str_replace(
                ['..', '/', '~', '<', '>', '|', '&', '%', '!', '`'],
                ['.', '', '', '', '', '', '', '', '', ''],
                $_SERVER['SERVER_NAME']));
        }

        if (empty($_SERVER['SERVER_PORT'])
            || in_array((int)$_SERVER['SERVER_PORT'], [80, 443], true)) {
            $server_port = '';
        } else {
            $server_port = $_SERVER['SERVER_PORT'];
        }

        return [
            'server_name' => $server_name,
            'server_port' => $server_port,
        ];
    }

    public static function get_default_page_settings() : array
    {
        return [
            'page_title'       => '',
            'page_keywords'    => '',
            'page_description' => '',
            // anything that is required in head tag
            'page_in_header'   => '',
            'page_body_class'  => '',
            'page_only_buffer' => false,
        ];
    }

    public static function page_settings(string | array | null $key = null, mixed $val = null) : mixed
    {
        $current_settings = self::get_data(self::PHS_PAGE_SETTINGS) ?: [];
        if ($key === null) {
            return $current_settings;
        }

        if ($val === null) {
            if (is_array($key)) {
                $def_settings = self::get_default_page_settings();
                foreach ($key as $kkey => $kval) {
                    if (array_key_exists($kkey, $def_settings)) {
                        $current_settings[$kkey] = $kval;
                    }
                }

                self::set_data(self::PHS_PAGE_SETTINGS, $current_settings);

                return true;
            }

            if (is_string($key)
                && array_key_exists($key, $current_settings)) {
                return $current_settings[$key];
            }

            return null;
        }

        if (array_key_exists($key, self::get_default_page_settings())) {
            $current_settings[$key] = $val;

            self::set_data(self::PHS_PAGE_SETTINGS, $current_settings);

            return true;
        }

        return null;
    }

    public static function page_body_class(string $css_class, bool $append = true)
    {
        $existing_body_classes = $append
            ? (self::page_settings('page_body_class') ?: '')
            : '';

        return self::page_settings('page_body_class', trim($existing_body_classes.' '.ltrim($css_class)));
    }

    /**
     * @return bool
     */
    public static function is_secured_request() : bool
    {
        return (bool)self::get_data(self::REQUEST_HTTPS);
    }

    /**
     * @return bool
     */
    public static function is_multi_tenant() : bool
    {
        return defined('PHS_MULTI_TENANT') && constant('PHS_MULTI_TENANT');
    }

    /**
     * @return bool
     */
    public static function prevent_session() : bool
    {
        return defined('PHS_PREVENT_SESSION') && constant('PHS_PREVENT_SESSION');
    }

    public static function user_logged_in(bool $force = false) : bool | array
    {
        return (($cuser_arr = self::current_user($force)) && !empty($cuser_arr['id'])) ? $cuser_arr : false;
    }

    public static function current_user(bool $force = false)
    {
        if (!($hook_args = self::_current_user_trigger($force))
         || empty($hook_args['user_db_data']) || !is_array($hook_args['user_db_data'])) {
            return false;
        }

        return $hook_args['user_db_data'];
    }

    public static function current_user_session(bool $force = false)
    {
        if (!($hook_args = self::_current_user_trigger($force))
            || empty($hook_args['session_db_data']) || !is_array($hook_args['session_db_data'])) {
            return false;
        }

        return $hook_args['session_db_data'];
    }

    public static function account_structure(int | array | PHS_Record_data $account_data) : null | array | PHS_Record_data
    {
        $hook_args = PHS_Hooks::default_account_structure_hook_args();
        $hook_args['account_data'] = $account_data;

        if (!($hook_result = PHS_Hooks::trigger_account_structure($hook_args))
            || empty($hook_result['account_structure'])) {
            return null;
        }

        return $hook_result['account_structure'];
    }

    public static function current_user_password_expiration(bool $force = false) : array
    {
        if (!($hook_args = self::_current_user_trigger($force))
         || empty($hook_args['password_expired_data']) || !is_array($hook_args['password_expired_data'])) {
            return PHS_Hooks::default_password_expiration_data();
        }

        return self::validate_array_recursive($hook_args['password_expired_data'], PHS_Hooks::default_password_expiration_data());
    }

    /**
     * @param null|PHS_Action $action_obj
     * @return null|bool|PHS_Action
     */
    public static function running_action(?PHS_Action $action_obj = null)
    {
        if ($action_obj === null) {
            return self::get_data(self::RUNNING_ACTION);
        }

        if (!($action_obj instanceof PHS_Action)) {
            return false;
        }

        return self::set_data(self::RUNNING_ACTION, $action_obj);
    }

    /**
     * @param null|PHS_Controller $controller_obj
     * @return null|bool|PHS_Controller
     */
    public static function running_controller(?PHS_Controller $controller_obj = null)
    {
        if ($controller_obj === null) {
            return self::get_data(self::RUNNING_CONTROLLER);
        }

        if (!($controller_obj instanceof PHS_Controller)) {
            return false;
        }

        return self::set_data(self::RUNNING_CONTROLLER, $controller_obj);
    }

    /**
     * @param null|string $theme
     *
     * @return string
     */
    public static function valid_theme(?string $theme) : string
    {
        self::st_reset_error();

        if (empty($theme)
         || !($theme = PHS_Instantiable::safe_escape_theme_name($theme))
         || !@file_exists(PHS_THEMES_DIR.$theme)
         || !@is_dir(PHS_THEMES_DIR.$theme)
         || !@is_readable(PHS_THEMES_DIR.$theme)) {
            self::st_set_error(self::ERR_THEME, self::_t('Theme %s doesn\'t exist or directory is not readable.', ($theme ?: 'N/A')));

            return '';
        }

        return $theme;
    }

    /**
     * @param null|string $theme
     *
     * @return null|string[]
     */
    public static function get_theme_language_paths(?string $theme = null) : ?array
    {
        self::st_reset_error();

        if ($theme === null) {
            $theme = self::get_theme();
        }

        if (!($theme = self::valid_theme($theme))) {
            return null;
        }

        if (!@file_exists(PHS_THEMES_DIR.$theme.'/'.PHS_Instantiable::LANGUAGES_DIR)
         || !@is_dir(PHS_THEMES_DIR.$theme.'/'.PHS_Instantiable::LANGUAGES_DIR)
         || !@is_readable(PHS_THEMES_DIR.$theme)) {
            return null;
        }

        if (!@is_readable(PHS_THEMES_DIR.$theme.'/'.PHS_Instantiable::LANGUAGES_DIR)) {
            self::st_set_error(self::ERR_THEME, self::_t('Theme (%s) languages directory is not readable.', $theme));

            return null;
        }

        return [
            'path' => PHS_THEMES_DIR.$theme.'/'.PHS_Instantiable::LANGUAGES_DIR.'/',
            'www'  => PHS_THEMES_WWW.$theme.'/'.PHS_Instantiable::LANGUAGES_DIR.'/',
        ];
    }

    /**
     * @param string $theme
     *
     * @return bool
     */
    public static function set_theme(string $theme) : bool
    {
        if (!($theme = self::valid_theme($theme))) {
            return false;
        }

        self::set_data(self::CURRENT_THEME, $theme);

        if (!self::get_data(self::DEFAULT_THEME)) {
            self::set_data(self::DEFAULT_THEME, $theme);
        }

        return true;
    }

    /**
     * @param string $theme
     *
     * @return bool
     */
    public static function set_defaut_theme(string $theme) : bool
    {
        if (!($theme = self::valid_theme($theme))) {
            return false;
        }

        self::set_data(self::DEFAULT_THEME, $theme);

        return true;
    }

    /**
     * Set a cascading themes array. You don't have to include default and current themes here.
     * When searching for templates, system will check current theme, then each cascading theme and lastly default theme.
     *
     * @param array $themes_arr
     *
     * @return bool
     */
    public static function set_cascading_themes(array $themes_arr) : bool
    {
        self::st_reset_error();

        $new_themes = [];
        foreach ($themes_arr as $theme) {
            if (!is_string($theme)
             || !($theme = self::valid_theme($theme))) {
                return false;
            }

            $new_themes[$theme] = true;
        }

        if (empty($new_themes)
         || !($themes_arr = @array_keys($new_themes))) {
            $themes_arr = [];
        }

        self::set_data(self::CASCADE_THEMES, $themes_arr);

        return true;
    }

    /**
     * @param string $theme
     *
     * @return bool
     */
    public static function add_theme_to_cascading_themes(string $theme) : bool
    {
        if (!($theme = self::valid_theme($theme))) {
            return false;
        }

        if (!($themes_arr = self::get_data(self::CASCADE_THEMES))
         || !is_array($themes_arr)) {
            $themes_arr = [];
        }

        if (in_array($theme, $themes_arr, true)) {
            return true;
        }

        $themes_arr[] = $theme;

        self::set_data(self::CASCADE_THEMES, $themes_arr);

        return true;
    }

    /**
     * @return bool
     */
    public static function resolve_theme() : bool
    {
        // First set default, so it doesn't get auto-set in set_theme() method
        if (defined('PHS_DEFAULT_THEME') && !self::get_data(self::DEFAULT_THEME) && !self::set_defaut_theme(PHS_DEFAULT_THEME)) {
            return false;
        }

        return !(defined('PHS_THEME') && !self::get_data(self::CURRENT_THEME) && !self::set_theme(PHS_THEME));
    }

    public static function get_defined_themes() : array
    {
        if (empty(PHS_THEMES_DIR)
            || !($themes_dir = rtrim(PHS_THEMES_DIR, '/'))
            || !@file_exists($themes_dir)
            || !@is_dir($themes_dir)
            || !@is_readable($themes_dir)
            || !($files_arr = @scandir($themes_dir))) {
            return [];
        }

        $defined_themes = [];
        foreach ($files_arr as $dir) {
            if ('.' === $dir || '..' === $dir
                || $dir === self::BASE_THEME
                || $dir === self::THEME_DIST_DIRNAME
                || !@is_dir($themes_dir.'/'.$dir)) {
                continue;
            }

            $defined_themes[$dir] = $themes_dir.'/'.$dir;
        }

        return $defined_themes;
    }

    /**
     * @return string
     */
    public static function get_theme() : string
    {
        $theme = self::get_data(self::CURRENT_THEME);

        if (!$theme) {
            if (!self::resolve_theme()
             || !($theme = self::get_data(self::CURRENT_THEME))) {
                return '';
            }
        }

        return $theme;
    }

    public static function get_default_theme() : ?string
    {
        $theme = self::get_data(self::DEFAULT_THEME);

        if (!$theme) {
            if (!self::resolve_theme()
                || !($theme = self::get_data(self::DEFAULT_THEME))
                || !is_string($theme)) {
                return null;
            }
        }

        return $theme;
    }

    /**
     * Return an array with cascading themes
     * @return array
     */
    public static function get_cascading_themes() : array
    {
        if (!($themes = self::get_data(self::CASCADE_THEMES))
         || !is_array($themes)) {
            $themes = [];
        }

        return $themes;
    }

    public static function get_all_themes_stack(?string $theme = null) : array
    {
        $themes_stack = [];
        if (empty($theme)
         || !($theme = self::valid_theme($theme))) {
            $theme = self::get_theme();
        }

        if (!empty($theme)) {
            $themes_stack[$theme] = true;
        }

        if (($themes_arr = self::get_cascading_themes())) {
            foreach ($themes_arr as $c_theme) {
                if (!empty($c_theme)) {
                    $themes_stack[$c_theme] = true;
                }
            }
        }

        if (($default_theme = self::get_default_theme())) {
            $themes_stack[$default_theme] = true;
        }

        $themes_stack[self::BASE_THEME] = true;

        return !empty($themes_stack) ? array_keys($themes_stack) : [];
    }

    public static function domain_constants() : array
    {
        return [
            // configuration constants
            'PHS_SITE_NAME'     => 'PHS_DEFAULT_SITE_NAME',
            'PHS_COOKIE_DOMAIN' => 'PHS_DEFAULT_COOKIE_DOMAIN',
            'PHS_DOMAIN'        => 'PHS_DEFAULT_DOMAIN',
            'PHS_SSL_DOMAIN'    => 'PHS_DEFAULT_SSL_DOMAIN',
            'PHS_PORT'          => 'PHS_DEFAULT_PORT',
            'PHS_SSL_PORT'      => 'PHS_DEFAULT_SSL_PORT',
            'PHS_DOMAIN_PATH'   => 'PHS_DEFAULT_DOMAIN_PATH',
            'PHS_CONTACT_EMAIL' => 'PHS_DEFAULT_CONTACT_EMAIL',

            'PHS_THEME' => 'PHS_DEFAULT_THEME',

            'PHS_CRYPT_KEY' => 'PHS_DEFAULT_CRYPT_KEY',

            'PHS_DB_CONNECTION' => 'PHS_DB_DEFAULT_CONNECTION',

            'PHS_SESSION_DIR'             => 'PHS_DEFAULT_SESSION_DIR',
            'PHS_SESSION_NAME'            => 'PHS_DEFAULT_SESSION_NAME',
            'PHS_SESSION_COOKIE_LIFETIME' => 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME',
            'PHS_SESSION_COOKIE_PATH'     => 'PHS_DEFAULT_SESSION_COOKIE_PATH',
            'PHS_SESSION_SAMESITE'        => 'PHS_DEFAULT_SESSION_SAMESITE',
            'PHS_SESSION_AUTOSTART'       => 'PHS_DEFAULT_SESSION_AUTOSTART',
        ];
    }

    public static function define_constants() : void
    {
        $constants_arr = self::domain_constants();
        foreach ($constants_arr as $domain_constant => $default_constant) {
            if (defined($domain_constant)) {
                continue;
            }

            $constant_value = '';
            if (defined($default_constant)) {
                $constant_value = constant($default_constant);
            }

            define($domain_constant, $constant_value);
        }
    }

    /**
     * @param bool $force_https
     *
     * @return string
     */
    public static function get_base_url(bool $force_https = false) : string
    {
        if (!empty($force_https)
            || self::is_secured_request()) {
            // if domain settings are set
            if (defined('PHS_HTTPS')) {
                return PHS_HTTPS;
            }

            // if default domain settings are set
            if (defined('PHS_DEFAULT_HTTPS')) {
                return PHS_DEFAULT_HTTPS;
            }
        } else {
            // if domain settings are set
            if (defined('PHS_HTTP')) {
                return PHS_HTTP;
            }

            // if default domain settings are set
            if (defined('PHS_DEFAULT_HTTP')) {
                return PHS_DEFAULT_HTTP;
            }
        }

        return '';
    }

    /**
     * @param bool $force_https
     *
     * @return bool|string
     */
    public static function get_base_domain_and_path(bool $force_https = false)
    {
        if (!empty($force_https)
         || self::is_secured_request()) {
            // if domain settings are set
            if (defined('PHS_FULL_SSL_PATH_WWW')) {
                return PHS_FULL_SSL_PATH_WWW;
            }

            // if default domain settings are set
            if (defined('PHS_DEFAULT_FULL_SSL_PATH_WWW')) {
                return PHS_DEFAULT_FULL_SSL_PATH_WWW;
            }
        } else {
            // if domain settings are set
            if (defined('PHS_FULL_PATH_WWW')) {
                return PHS_FULL_PATH_WWW;
            }

            // if default domain settings are set
            if (defined('PHS_DEFAULT_FULL_PATH_WWW')) {
                return PHS_DEFAULT_FULL_PATH_WWW;
            }
        }

        return false;
    }

    public static function are_we_in_a_background_thread() : bool
    {
        return ($cscope = PHS_Scope::current_scope()) === PHS_Scope::SCOPE_BACKGROUND
                || $cscope === PHS_Scope::SCOPE_AGENT;
    }

    public static function get_instance() : ?self
    {
        if (!empty(self::$instance)) {
            return self::$instance;
        }

        self::$instance = new self();

        return self::$instance;
    }

    public static function extract_route()
    {
        if (empty($_GET) || empty($_GET[self::ROUTE_PARAM])
         || !is_string($_GET[self::ROUTE_PARAM])) {
            return '';
        }

        return $_GET[self::ROUTE_PARAM];
    }

    /**
     * Parse request route. Route is something like:
     * {plugin}/{controller}/{action} If controller is part of a plugin
     * or
     * {controller}/{action} If controller is a core controller
     * or
     * {plugin}-{action} Controller will be 'index'
     *
     * @param string|array|bool $route If a non-empty string, method will try parsing provided route,
     *                                 if an array will try paring array, otherwise exract route from context
     * @param bool $use_short_names If we need short names for plugin,
     *                              controller and action in returned keys (eg. p, c, a, ad)
     *
     * @return null|array Returns true on success or null on error
     */
    public static function parse_route($route = false, bool $use_short_names = false) : ?array
    {
        self::st_reset_error();

        $plugin = null;
        $controller = '';
        $action = '';
        $action_dir = '';
        $force_https = false;

        $route_parts = [];
        if (!empty($route)) {
            if (is_array($route)) {
                if (!empty($route['plugin'])) {
                    $plugin = $route['plugin'];
                } elseif (!empty($route['p'])) {
                    $plugin = $route['p'];
                }

                if (!empty($route['controller'])) {
                    $controller = $route['controller'];
                } elseif (!empty($route['c'])) {
                    $controller = $route['c'];
                }

                if (!empty($route['action'])) {
                    $action = $route['action'];
                } elseif (!empty($route['a'])) {
                    $action = $route['a'];
                }

                if (!empty($route['action_dir'])) {
                    $action_dir = $route['action_dir'];
                } elseif (!empty($route['ad'])) {
                    $action_dir = $route['ad'];
                }

                if (!empty($route['force_https'])) {
                    $force_https = true;
                }
            } else {
                if (str_contains($route, '-')) {
                    if (!($route_parts_tmp = explode('-', $route, 2))
                     || empty($route_parts_tmp[0])) {
                        self::st_set_error(self::ERR_ROUTE, self::_t('Couldn\'t obtain route.'));

                        return null;
                    }

                    $route_parts[0] = $route_parts_tmp[0];
                    $route_parts[1] = self::ROUTE_DEFAULT_CONTROLLER;
                    $route_parts[2] = $route_parts_tmp[1] ?? '';
                } elseif (!($route_parts = explode('/', $route, 3))) {
                    self::st_set_error(self::ERR_ROUTE, self::_t('Couldn\'t obtain route.'));

                    return null;
                }

                $rp_count = count($route_parts);
                if ($rp_count === 1) {
                    $action = (!empty($route_parts[0]) ? trim($route_parts[0]) : '');
                } elseif ($rp_count === 2) {
                    $plugin = (!empty($route_parts[0]) ? trim($route_parts[0]) : null);
                    $action = (!empty($route_parts[1]) ? trim($route_parts[1]) : '');
                } elseif ($rp_count === 3) {
                    $plugin = (!empty($route_parts[0]) ? trim($route_parts[0]) : null);
                    $controller = (!empty($route_parts[1]) ? trim($route_parts[1]) : '');
                    $action = (!empty($route_parts[2]) ? trim($route_parts[2]) : '');
                }

                // Check action dir
                if (str_contains($action, self::ACTION_DIR_ACTION_SEPARATOR)
                    && ($action_parts = explode(self::ACTION_DIR_ACTION_SEPARATOR, $action, 2))
                    && is_array($action_parts)
                    && count($action_parts) === 2) {
                    $action_dir = (!empty($action_parts[0]) ? trim($action_parts[0]) : '');
                    $action = (!empty($action_parts[1]) ? trim($action_parts[1]) : '');
                }
            }
        }

        if (empty($controller)) {
            $controller = self::ROUTE_DEFAULT_CONTROLLER;
        }
        if (empty($action)) {
            $action = self::ROUTE_DEFAULT_ACTION;
        }

        if (($plugin !== null && !($plugin = PHS_Instantiable::safe_escape_plugin_name($plugin)))
         || !($controller = PHS_Instantiable::safe_escape_class_name($controller))
         || !($action = PHS_Instantiable::safe_escape_action_name($action))
         || (!empty($action_dir) && !($action_dir = PHS_Instantiable::safe_escape_instance_subdir($action_dir)))
        ) {
            self::st_set_error(self::ERR_ROUTE, self::_t('Bad route in request.'));

            return null;
        }

        if ($use_short_names) {
            return [
                'p'           => $plugin,
                'c'           => $controller,
                'ad'          => $action_dir,
                'a'           => $action,
                'force_https' => $force_https,
            ];
        }

        return [
            'plugin'      => $plugin,
            'controller'  => $controller,
            'action_dir'  => $action_dir,
            'action'      => $action,
            'force_https' => $force_https,
        ];
    }

    public static function route_exists(null | string | array $route, array $params = []) : ?array
    {
        self::st_reset_error();

        if (empty($params['action_accepts_scopes'])) {
            $params['action_accepts_scopes'] = false;
        } elseif (!is_array($params['action_accepts_scopes'])) {
            if (!PHS_Scope::valid_scope($params['action_accepts_scopes'])) {
                self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Invalid scopes provided for action of the route.'));

                return null;
            }

            $params['action_accepts_scopes'] = [$params['action_accepts_scopes']];
        } else {
            $action_accepts_scopes_arr = [];
            foreach ($params['action_accepts_scopes'] as $scope) {
                if (!PHS_Scope::valid_scope($scope)) {
                    self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Invalid scopes provided for action of the route.'));

                    return null;
                }

                $action_accepts_scopes_arr[] = $scope;
            }

            $params['action_accepts_scopes'] = $action_accepts_scopes_arr;
        }

        if (!($route_parts = self::parse_route($route, false))) {
            self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Couldn\'t parse route.'));

            return null;
        }

        if (empty($route_parts) || !is_array($route_parts)) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Route is invalid.'));

            return null;
        }

        $route_parts = self::validate_route_from_parts($route_parts, false);

        /** @var bool|PHS_Plugin $plugin_obj */
        $plugin_obj = false;
        if (!empty($route_parts['plugin'])
            && !($plugin_obj = self::load_plugin($route_parts['plugin']))) {
            self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Couldn\'t instantiate plugin from route.'));

            return null;
        }

        /** @var bool|PHS_Controller $controller_obj */
        $controller_obj = false;
        if (!empty($route_parts['controller'])
            && !($controller_obj = self::load_controller($route_parts['controller'], ($plugin_obj ? $plugin_obj->instance_plugin_name() : false)))) {
            self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Couldn\'t instantiate controller from route.'));

            return null;
        }

        /** @var bool|PHS_Action $action_obj */
        $action_obj = null;
        if (!empty($route_parts['action'])
            && !($action_obj = self::load_action($route_parts['action'], ($plugin_obj ? $plugin_obj->instance_plugin_name() : false), $route_parts['action_dir']))) {
            self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Couldn\'t instantiate action from route.'));

            return null;
        }

        if (empty($action_obj)) {
            self::st_set_error(self::ERR_ROUTE, self::_t('Couldn\'t instantiate action from route.'));

            return null;
        }

        if (!empty($params['action_accepts_scopes'])) {
            if (empty($controller_obj)
             || !($controller_scopes_arr = $controller_obj->allowed_scopes())) {
                $controller_scopes_arr = [];
            }

            $action_scopes_arr = $action_obj->allowed_scopes() ?: [];

            $scopes_check_arr = self::array_merge_unique_values($controller_scopes_arr, $action_scopes_arr);

            if (!empty($scopes_check_arr)) {
                foreach ($params['action_accepts_scopes'] as $scope) {
                    $scope = (int)$scope;
                    if (!in_array($scope, $scopes_check_arr, true)) {
                        $scope_title = '(???)';
                        if (($scope_details = PHS_Scope::valid_scope($scope))) {
                            $scope_title = $scope_details['title'];
                        }

                        self::st_set_error(self::ERR_ROUTE,
                            self::_t('Action %s%s is not ment to run in scope %s.',
                                (!empty($route_parts['action_dir']) ? $route_parts['action_dir'].'/' : ''),
                                $route_parts['action'],
                                $scope_title));

                        return null;
                    }
                }
            }
        }

        return $route_parts;
    }

    /**
     * Parse request route. Route is something like:
     *
     * {plugin}/{controller}/[{action_dir}__]{action} If controller is part of a plugin
     * or
     * {controller}/[{action_dir}__]{action} If controller is a core controller
     * or
     * {plugin}-[{action_dir}__]{action} Controller will be 'index'
     *
     * @param null|string|array $route If a non-empty string, method will try parsing provided route, otherwise exract route from context
     * @return bool Returns true on success || false on error
     */
    public static function set_route(null | string | array $route = null) : bool
    {
        self::st_reset_error();

        if (empty($route)) {
            $route = self::extract_route();
        }

        if (!($route_parts = self::parse_route($route, false))) {
            self::st_set_error_if_not_set(self::ERR_ROUTE, self::_t('Couldn\'t parse route.'));

            return false;
        }

        /** @var PHS_Event_Route $event_obj */
        if (($event_obj = PHS_Event_Route::trigger(['route' => $route_parts]))
            && ($new_route = $event_obj->get_output('route'))
            && ($new_route = self::parse_route($new_route, false))) {
            $route_parts = $new_route;
        }

        self::set_data(self::ROUTE_PLUGIN, $route_parts['plugin']);
        self::set_data(self::ROUTE_CONTROLLER, $route_parts['controller']);
        self::set_data(self::ROUTE_ACTION_DIR, $route_parts['action_dir']);
        self::set_data(self::ROUTE_ACTION, $route_parts['action']);

        return true;
    }

    public static function safe_escape_root_script(string $script) : ?string
    {
        if (empty($script)
            || preg_match('@[^a-zA-Z0-9_\-]@', $script)) {
            return null;
        }

        return $script;
    }

    public static function safe_escape_route_parts($part) : ?string
    {
        if (empty($part) || !is_string($part)
            || preg_match('@[^a-zA-Z0-9_]@', $part)) {
            return null;
        }

        return $part;
    }

    // No _ allowed in action directories
    public static function safe_escape_route_action_dir($part) : ?string
    {
        if (empty($part) || !is_string($part)
            || preg_match('@[^a-zA-Z0-9/_]@', $part)) {
            return null;
        }

        return $part;
    }

    /**
     * Change default route interpret script (default is index). .php file extension will be added by platform.
     *
     * @param null|string $script New interpreter script (default is index). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function interpret_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_INTERPRET_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_INTERPRET_SCRIPT = $script;

        return self::$_INTERPRET_SCRIPT.'.php';
    }

    /**
     * Change default background interpret script (default is _bg). .php file extension will be added by platform.
     *
     * @param null|string $script New background script (default is _bg). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function background_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_BACKGROUND_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_BACKGROUND_SCRIPT = $script;

        return self::$_BACKGROUND_SCRIPT.'.php';
    }

    /**
     * Change default agent interpret script (default is _agent). .php file extension will be added by platform.
     *
     * @param null|string $script New agent script (default is _agent). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function agent_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_AGENT_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_AGENT_SCRIPT = $script;

        return self::$_AGENT_SCRIPT.'.php';
    }

    /**
     * Change default ajax script (default is _ajax). .php file extension will be added by platform.
     *
     * @param null|string $script New ajax script (default is _ajax). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function ajax_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_AJAX_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_AJAX_SCRIPT = $script;

        return self::$_AJAX_SCRIPT.'.php';
    }

    /**
     * Change default api script (default is _api). .php file extension will be added by platform.
     *
     * @param null|string $script New api script (default is _api). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function api_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_API_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_API_SCRIPT = $script;

        return self::$_API_SCRIPT.'.php';
    }

    /**
     * Change default remote script (default is _remote). .php file extension will be added by platform.
     *
     * @param null|string $script New remote script (default is _remote). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function remote_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_REMOTE_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_REMOTE_SCRIPT = $script;

        return self::$_REMOTE_SCRIPT.'.php';
    }

    /**
     * Change default update script (default is _update). .php file extension will be added by platform.
     *
     * @param null|string $script New upate script (default is _update). No extension should be provided (.php will be appended)
     *
     * @return string
     */
    public static function update_script(?string $script = null) : string
    {
        if ($script === null) {
            return self::$_UPDATE_SCRIPT.'.php';
        }

        if (!self::safe_escape_root_script($script)
            || !@file_exists(PHS_PATH.$script.'.php')) {
            return '';
        }

        self::$_UPDATE_SCRIPT = $script;

        return self::$_UPDATE_SCRIPT.'.php';
    }

    public static function get_background_path() : string
    {
        return PHS_PATH.self::background_script();
    }

    public static function get_agent_path() : string
    {
        return PHS_PATH.self::agent_script();
    }

    /**
     * @param bool $force_https
     * @param bool $slash_terminated
     * @param null|string $for_domain
     *
     * @return null|string
     */
    public static function get_domain_url(bool $force_https = false, bool $slash_terminated = false, ?string $for_domain = null) : ?string
    {
        if ($for_domain !== null) {
            if ($force_https
             || self::is_secured_request()) {
                if (stripos($for_domain, 'https://') !== 0) {
                    $for_domain = 'https://'.$for_domain;
                }
            } elseif (stripos($for_domain, 'http://') !== 0) {
                $for_domain = 'http://'.$for_domain;
            }
            $base_url = $for_domain;
        } elseif (!($base_url = self::get_base_url($force_https))) {
            return null;
        }

        if ($slash_terminated
            && !str_ends_with($base_url, '/')) {
            $base_url .= '/';
        }

        return $base_url;
    }

    /**
     * @param bool $force_https
     * @param null|string $for_domain
     *
     * @return null|string
     */
    public static function get_interpret_url(bool $force_https = false, ?string $for_domain = null) : ?string
    {
        if (!($base_url = self::get_domain_url($force_https, true, $for_domain))) {
            return null;
        }

        return $base_url.self::interpret_script();
    }

    public static function get_interpret_path() : string
    {
        return PHS_PATH.self::interpret_script();
    }

    /**
     * @param bool $force_https
     * @param null|string $for_domain
     *
     * @return null|string
     */
    public static function get_ajax_url(bool $force_https = false, ?string $for_domain = null) : ?string
    {
        if (!($base_url = self::get_domain_url($force_https, true, $for_domain))) {
            return null;
        }

        return $base_url.self::ajax_script();
    }

    public static function get_ajax_path() : string
    {
        return PHS_PATH.self::ajax_script();
    }

    /**
     * @param bool $force_https
     * @param bool $use_rewrite
     * @param null|string $for_domain
     *
     * @return null|string
     */
    public static function get_api_url(bool $force_https = false, bool $use_rewrite = true, ?string $for_domain = null) : ?string
    {
        if (!($base_url = self::get_domain_url($force_https, true, $for_domain))) {
            return null;
        }

        if (!$use_rewrite) {
            return $base_url.self::api_script();
        }

        return $base_url.'api/v1/';
    }

    public static function get_api_path() : string
    {
        return PHS_PATH.self::api_script();
    }

    /**
     * @param bool $force_https
     * @param bool $use_rewrite
     * @param null|string $for_domain
     *
     * @return null|string
     */
    public static function get_remote_script_url(bool $force_https = false, bool $use_rewrite = true, ?string $for_domain = null) : ?string
    {
        if (!($base_url = self::get_domain_url($force_https, true, $for_domain))) {
            return null;
        }

        if (!$use_rewrite) {
            return $base_url.self::remote_script();
        }

        return $base_url.'remote/v1/';
    }

    public static function get_remote_script_path() : string
    {
        return PHS_PATH.self::remote_script();
    }

    /**
     * @param bool $force_https
     * @param null|string $for_domain
     *
     * @return null|string
     */
    public static function get_update_script_url(bool $force_https = false, ?string $for_domain = null) : ?string
    {
        if (!($base_url = self::get_domain_url($force_https, true, $for_domain))) {
            return null;
        }

        return $base_url.self::update_script();
    }

    public static function get_update_script_path() : string
    {
        return PHS_PATH.self::update_script();
    }

    public static function current_url() : ?string
    {
        if (!($plugin = self::get_data(self::ROUTE_PLUGIN))) {
            $plugin = false;
        }
        if (!($controller = self::get_data(self::ROUTE_CONTROLLER))
         || $controller === self::ROUTE_DEFAULT_CONTROLLER) {
            $controller = false;
        }
        if (!($action_dir = self::get_data(self::ROUTE_ACTION_DIR))) {
            $action_dir = false;
        }
        if (!($action = self::get_data(self::ROUTE_ACTION))
         || $action === self::ROUTE_DEFAULT_ACTION) {
            $action = false;
        }

        return self::url([
            'p' => $plugin, 'c' => $controller, 'ad' => $action_dir, 'a' => $action],
            self::current_page_query_string_as_array()
        );
    }

    public static function current_page_query_string_as_array(array $params = []) : array
    {
        if (empty($_SERVER)) {
            $_SERVER = [];
        }

        $params['remove_route_param'] = !isset($params['remove_route_param']) || !empty($params['remove_route_param']);

        if (!isset($params['exclude_params']) || !is_array($params['exclude_params'])) {
            $params['exclude_params'] = [];
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            @parse_str($_SERVER['QUERY_STRING'], $query_arr);
        }

        $query_arr ??= [];

        if (!empty($params['remove_route_param'])
            && isset($query_arr[self::ROUTE_PARAM])) {
            unset($query_arr[self::ROUTE_PARAM]);
        }

        if (!empty($params['exclude_params'])) {
            foreach ($params['exclude_params'] as $param_name) {
                if (isset($query_arr[$param_name])) {
                    unset($query_arr[$param_name]);
                }
            }
        }

        return $query_arr;
    }

    public static function validate_action_dir_in_url($ad) : string
    {
        if (!is_string($ad)
            || $ad === '') {
            return '';
        }

        // Allow actions subdirs, but replace / (subdirs separators) with _ in action directory parameter
        return str_replace('/', '_', str_replace('_', '', $ad));
    }

    /**
     * @param bool|array $parts
     *
     * @return null|string
     */
    public static function route_from_parts($parts = false) : ?string
    {
        if (empty($parts) || !is_array($parts)) {
            $parts = [];
        }

        $parts = self::validate_route_from_parts($parts, true);

        if (!self::validate_short_name_route_parts($parts, false)) {
            return null;
        }

        $action_str = (!empty($parts['a']) ? $parts['a'] : '');
        if (!empty($parts['ad'])
            && !empty($action_str)) {
            $action_str = self::validate_action_dir_in_url(str_replace('_', '/', $parts['ad'])).self::ACTION_DIR_ACTION_SEPARATOR.$action_str;
        }

        if (empty($parts['p'])) {
            $parts['p'] = '';
        }

        if (!empty($parts['c'])) {
            $route = $parts['p'].'/'.$parts['c'].'/'.$action_str;
        } elseif ($parts['p'] === '') {
            $route = $action_str;
        } else {
            $route = $parts['p'].'-'.$action_str;
        }

        return $route;
    }

    /**
     * Convert route from long plugin, controller and action names into p, c, a, ad names
     * @param array $route_arr
     *
     * @return null|array
     */
    public static function convert_route_to_short_parts(array $route_arr) : ?array
    {
        if (empty($route_arr)
         || (empty($route_arr['plugin']) && empty($route_arr['controller']) && empty($route_arr['action']) && empty($route_arr['action_dir']))) {
            return $route_arr;
        }

        $converted_route = $route_arr;
        if (isset($route_arr['plugin'])) {
            unset($converted_route['plugin']);
            $converted_route['p'] = $route_arr['plugin'];
        }
        if (isset($route_arr['controller'])) {
            unset($converted_route['controller']);
            $converted_route['c'] = $route_arr['controller'];
        }
        if (isset($route_arr['action'])) {
            unset($converted_route['action']);
            $converted_route['a'] = $route_arr['action'];
        }
        if (isset($route_arr['action_dir'])) {
            unset($converted_route['action_dir']);
            $converted_route['ad'] = $route_arr['action_dir'];
        }

        return self::validate_short_name_route_parts($converted_route, true);
    }

    /**
     * Checks if provided route has valid short name parts
     * @param array $route_arr
     * @param bool $check_if_empty
     *
     * @return null|array
     */
    public static function validate_short_name_route_parts(array $route_arr, bool $check_if_empty = true) : ?array
    {
        if ((!empty($check_if_empty)
             && empty($route_arr['p']) && empty($route_arr['c']) && empty($route_arr['a']))
         || (!empty($route_arr['p']) && !self::safe_escape_route_parts($route_arr['p']))
         || (!empty($route_arr['c']) && !self::safe_escape_route_parts($route_arr['c']))
         || (!empty($route_arr['a']) && !self::safe_escape_route_parts($route_arr['a']))
         || (!empty($route_arr['ad']) && !self::safe_escape_route_action_dir($route_arr['ad']))) {
            return null;
        }

        return $route_arr;
    }

    public static function validate_route_from_parts(?array $route_arr, bool $use_short_names = false) : array
    {
        $route_arr ??= [];
        $route_arr['force_https'] = !empty($route_arr['force_https']);

        if ($use_short_names) {
            if (empty($route_arr['p'])) {
                $route_arr['p'] = false;
            }
            if (empty($route_arr['c'])) {
                $route_arr['c'] = false;
            }
            // action directory (if applicable)
            if (empty($route_arr['ad'])) {
                $route_arr['ad'] = false;
            }
            if (empty($route_arr['a'])) {
                $route_arr['a'] = self::ROUTE_DEFAULT_ACTION;
            }
        } else {
            if (empty($route_arr['plugin'])) {
                $route_arr['plugin'] = false;
            }
            if (empty($route_arr['controller'])) {
                $route_arr['controller'] = false;
            }
            if (empty($route_arr['action_dir'])) {
                $route_arr['action_dir'] = false;
            }
            if (empty($route_arr['action'])) {
                $route_arr['action'] = self::ROUTE_DEFAULT_ACTION;
            }
        }

        return $route_arr;
    }

    public static function url(?array $route_arr = null, null | bool | array $args = null, ?array $extra = null) : string
    {
        $route_arr = self::validate_route_from_parts($route_arr, true);

        // As we run in a background job we don't know if initial request was made using https, so we force https links
        if (self::are_we_in_a_background_thread()) {
            $route_arr['force_https'] = true;
        }

        $args = $args ?: [];
        $extra ??= [];

        $extra['anchor'] ??= '';

        if (empty($extra['http']) || !is_array($extra['http'])) {
            $extra['http'] = [];
        }

        if (empty($extra['http']['arg_separator'])
            || !is_string($extra['http']['arg_separator'])) {
            $extra['http']['arg_separator'] = '&';
        }

        $extra['http']['enc_type'] ??= PHP_QUERY_RFC1738;

        if (empty($extra['raw_params']) || !is_array($extra['raw_params'])) {
            $extra['raw_params'] = [];
        }

        // Changed raw_params to raw_args (backward compatibility)
        if (empty($extra['raw_args']) || !is_array($extra['raw_args'])) {
            $extra['raw_args'] = $extra['raw_params'];
        }

        $extra['skip_formatters'] = !empty($extra['skip_formatters']);

        if (empty($extra['for_scope']) || !PHS_Scope::valid_scope($extra['for_scope'])) {
            $extra['for_scope'] = PHS_Scope::SCOPE_WEB;
        }

        if (empty($extra['for_domain']) || !is_string($extra['for_domain'])) {
            $extra['for_domain'] = null;
        }

        // Rewrite URLs are supported only for API and Remote scopes...
        if (!in_array($extra['for_scope'], [PHS_Scope::SCOPE_API, PHS_Scope::SCOPE_REMOTE, PHS_Scope::SCOPE_GRAPHQL], true)) {
            $extra['use_rewrite_url'] = false;
        } else {
            $extra['use_rewrite_url'] = !empty($extra['use_rewrite_url']);
        }

        $new_args = [];
        $rewrite_route = '';

        // We can pass raw route as a string (obtained as PHS::route_from_parts())
        // which is passed as a parameter in a JavaScript script
        // (e.g. data_call( route, data ) { PHS_JSEN.createAjaxDialog( { ... url: "PHS_Ajax::url( false, [], [ 'raw_route' => '" + route + "' ] )", ... }
        if (!empty($extra['raw_route']) && is_string($extra['raw_route'])) {
            if ($extra['use_rewrite_url']) {
                $rewrite_route = $extra['raw_route'];
            } else {
                $extra['raw_args'][self::ROUTE_PARAM] = $extra['raw_route'];
            }
        } elseif (!($route = self::route_from_parts($route_arr))) {
            return '#invalid_path['
                   .(!empty($route_arr['p']) ? $route_arr['p'] : '').'::'
                   .(!empty($route_arr['c']) ? $route_arr['c'] : '').'::'
                   .(!empty($route_arr['ad']) ? $route_arr['ad'] : '').'/'
                   .(!empty($route_arr['a']) ? $route_arr['a'] : '').']'.$extra['anchor'];
        } else {
            if ($extra['use_rewrite_url']) {
                $rewrite_route = $route;
            } else {
                $new_args[self::ROUTE_PARAM] = $route;
            }
        }

        if (isset($args[self::ROUTE_PARAM])) {
            unset($args[self::ROUTE_PARAM]);
        }

        foreach ($args as $key => $val) {
            $new_args[$key] = $val;
        }

        if (!($query_string = @http_build_query($new_args, '', $extra['http']['arg_separator'], $extra['http']['enc_type']))) {
            $query_string = '';
        }

        if (!empty($extra['raw_args']) && is_array($extra['raw_args'])) {
            // Parameters that shouldn't be run through http_build_query as values will be rawurlencoded, and we might add javascript code in parameters
            // e.g. $extra['raw_args'] might be an id passed as javascript function parameter
            if (($raw_query = array_to_query_string($extra['raw_args'],
                ['arg_separator' => $extra['http']['arg_separator'], 'raw_encode_values' => false]))) {
                $query_string .= ($query_string !== '' ? $extra['http']['arg_separator'] : '').$raw_query;
            }
        }

        switch ($extra['for_scope']) {
            default:
                $stock_url = self::get_interpret_url($route_arr['force_https'], $extra['for_domain'])
                             .($query_string !== '' ? '?'.$query_string : '')
                             .$extra['anchor'];
                break;

            case PHS_Scope::SCOPE_AJAX:
                $stock_url = self::get_ajax_url($route_arr['force_https'], $extra['for_domain'])
                             .($query_string !== '' ? '?'.$query_string : '');
                break;

            case PHS_Scope::SCOPE_API:
                $stock_url = self::get_api_url($route_arr['force_https'], $extra['use_rewrite_url'], $extra['for_domain'])
                             .($extra['use_rewrite_url'] ? $rewrite_route : '')
                             .($query_string !== '' ? '?'.$query_string : '');
                break;

            case PHS_Scope::SCOPE_REMOTE:
                $stock_url = self::get_remote_script_url($route_arr['force_https'], $extra['use_rewrite_url'], $extra['for_domain'])
                             .($extra['use_rewrite_url'] ? $rewrite_route : '')
                             .($query_string !== '' ? '?'.$query_string : '');
                break;
        }

        $final_url = $stock_url;

        if (empty($extra['skip_formatters'])) {
            // Let plugins change API provided route in actual plugin, controller, action route (if required)
            /** @var PHS_Event_Url_rewrite $event_obj */
            if (($event_obj = PHS_Event_Url_rewrite::trigger([
                'route'      => $route_arr, 'args' => $args, 'raw_args' => $extra['raw_args'],
                'stock_args' => $new_args, 'stock_query_string' => $query_string, 'stock_url' => $stock_url,
            ]))
             && ($new_url = $event_obj->get_output('url'))) {
                $final_url = $new_url;
            }
        }

        return $final_url;
    }

    public static function relative_url(?string $url) : string
    {
        if (empty($url)) {
            return '';
        }

        // check on "non https" url first
        if (($base_url = self::get_base_url(false))
            && ($base_len = strlen($base_url))
            && str_starts_with($url, $base_url)) {
            return substr($url, $base_len);
        }

        // check "https" url
        if (($base_url = self::get_base_url(true))
            && ($base_len = strlen($base_url))
            && str_starts_with($url, $base_url)) {
            return substr($url, $base_len);
        }

        return $url;
    }

    public static function from_relative_url(string $url, bool $force_https = false) : string
    {
        if (($base_url = self::get_base_url($force_https))
            && str_starts_with($url, $base_url)) {
            return $url;
        }

        return $base_url.$url;
    }

    public static function relative_path(string $path) : string
    {
        if (($base_len = strlen(PHS_PATH))
            && str_starts_with($path, PHS_PATH)) {
            return substr($path, $base_len);
        }

        return $path;
    }

    public static function from_relative_path(?string $path) : string
    {
        if (empty($path)) {
            return PHS_PATH;
        }

        if (str_starts_with($path, PHS_PATH)) {
            return $path;
        }

        return PHS_PATH.$path;
    }

    public static function get_route_details() : ?array
    {
        if (null === ($controller = self::get_data(self::ROUTE_CONTROLLER))) {
            self::set_route();

            if (null === ($controller = self::get_data(self::ROUTE_CONTROLLER))) {
                return null;
            }
        }

        $return_arr = [];
        $return_arr[self::ROUTE_PLUGIN] = self::get_data(self::ROUTE_PLUGIN);
        $return_arr[self::ROUTE_CONTROLLER] = $controller;
        $return_arr[self::ROUTE_ACTION] = self::get_data(self::ROUTE_ACTION);
        $return_arr[self::ROUTE_ACTION_DIR] = self::get_data(self::ROUTE_ACTION_DIR);

        return $return_arr;
    }

    public static function get_route_details_for_url($use_short_names = true) : ?array
    {
        if (!($route_arr = self::get_route_details())) {
            return null;
        }

        if ($use_short_names) {
            return [
                'p'  => $route_arr[self::ROUTE_PLUGIN],
                'c'  => $route_arr[self::ROUTE_CONTROLLER],
                'a'  => $route_arr[self::ROUTE_ACTION],
                'ad' => $route_arr[self::ROUTE_ACTION_DIR],
            ];
        }

        return [
            'plugin'     => $route_arr[self::ROUTE_PLUGIN],
            'controller' => $route_arr[self::ROUTE_CONTROLLER],
            'action'     => $route_arr[self::ROUTE_ACTION],
            'action_dir' => $route_arr[self::ROUTE_ACTION_DIR],
        ];
    }

    public static function get_route_as_string() : ?string
    {
        if (($controller = self::get_data(self::ROUTE_CONTROLLER)) === null) {
            self::set_route();

            if (($controller = self::get_data(self::ROUTE_CONTROLLER)) === null) {
                return null;
            }
        }

        $route_arr = [];
        $route_arr['p'] = self::get_data(self::ROUTE_PLUGIN);
        $route_arr['c'] = $controller;
        $route_arr['a'] = self::get_data(self::ROUTE_ACTION);
        $route_arr['ad'] = self::get_data(self::ROUTE_ACTION_DIR);

        return self::route_from_parts($route_arr);
    }

    /**
     * @param null|array $params
     *
     * @return null|array|bool
     */
    public static function execute_route(?array $params = null)
    {
        self::st_reset_error();

        $params ??= [];
        $params['die_on_error'] = (!isset($params['die_on_error']) || !empty($params['die_on_error']));

        $action_result = false;

        if (!($route_details = self::get_route_details())
            || empty($route_details[self::ROUTE_CONTROLLER])) {
            self::st_set_error(self::ERR_EXECUTE_ROUTE, self::_t('Couldn\'t obtain route details.'));
        }

        /** @var PHS_Controller $controller_obj */
        elseif (!($controller_obj = self::load_controller($route_details[self::ROUTE_CONTROLLER], $route_details[self::ROUTE_PLUGIN]))) {
            self::st_set_error(self::ERR_EXECUTE_ROUTE, self::_t('Couldn\'t obtain controller instance for %s.', $route_details[self::ROUTE_CONTROLLER]));
        } elseif (!($action_result = $controller_obj->run_action($route_details[self::ROUTE_ACTION], null, $route_details[self::ROUTE_ACTION_DIR]))) {
            self::st_copy_or_set_error($controller_obj,
                self::ERR_EXECUTE_ROUTE, self::_t('Error executing action [%s].', $route_details[self::ROUTE_ACTION]));
        }

        if (self::st_has_error()) {
            $controller_error_arr = $technical_error_arr = self::st_get_error();
        } elseif (!empty($action_result)
                  && PHS_Action::action_result_has_errors($action_result)) {
            $controller_error_arr = PHS_Action::get_end_user_error_from_action_result($action_result);
            $technical_error_arr = PHS_Action::get_technical_error_from_action_result($action_result);
        } else {
            $controller_error_arr = $technical_error_arr = null;
        }

        self::st_reset_error();

        if (!($scope_obj = PHS_Scope::get_scope_instance())) {
            self::st_set_error_if_not_set(self::ERR_EXECUTE_ROUTE, self::_t('Error spawning scope instance.'));

            if (!empty($params['die_on_error'])) {
                $error_msg = self::st_get_full_error_message();

                PHS_Logger::critical($error_msg, PHS_Logger::TYPE_DEF_DEBUG);

                echo 'Error spawining scope.';
                exit;
            }

            return false;
        }

        // Don't display technical stuff to end-user...
        if (!self::st_debugging_mode()
            && self::arr_has_error($controller_error_arr)) {
            $controller_error_arr = self::arr_change_error_code_and_message($controller_error_arr,
                self::ERR_EXECUTE_ROUTE, self::_t('Error serving request.'));
        }

        if (!empty($action_result) && is_array($action_result)
            && !empty($action_result['custom_headers']) && is_array($action_result['custom_headers'])) {
            $action_result['custom_headers']
                = self::unify_array_insensitive($action_result['custom_headers'], ['trim_keys' => true]);
        }

        $scope_action_result = $scope_obj->generate_response($action_result, $controller_error_arr, $technical_error_arr);

        if (empty($action_result)) {
            $error_msg = null;
            if (self::arr_has_error($technical_error_arr)) {
                $error_msg = '['.self::get_route_as_string().'] - '
                             .self::arr_get_full_error_message($technical_error_arr);
            } elseif ($scope_obj->has_error()) {
                $error_msg = $scope_obj->get_full_error_message();
            }

            if (!empty($error_msg)) {
                PHS_Logger::critical($error_msg, PHS_Logger::TYPE_DEBUG);
            }

            return false;
        }

        return $scope_action_result;
    }

    /**
     * This method spawns a view object with provided route as context for script which require resources
     * (javascript URLs, image URLs, etc.), but they are not running in a view context. Usually javascript scripts
     * which output javascript directly without rendering the code in a view context
     *
     * @param array|string $route_arr
     * @param string|array $template
     * @param array $template_data
     *
     * @return null|PHS_View
     */
    public static function spawn_view_in_context(string | array $route_arr, string | array $template, array $template_data = []) : ?PHS_View
    {
        self::st_reset_error();

        $plugin_obj = null;
        /** @var PHS_Plugin $plugin_obj */
        /** @var PHS_Controller $controller_obj */
        /** @var PHS_Action $action_obj */
        if (!($route_arr = self::parse_route($route_arr, true))
         || (!empty($route_arr['p']) && !($plugin_obj = self::load_plugin($route_arr['p'])))
         || !($controller_obj = self::load_controller($route_arr['c'], $route_arr['p']))
         || !($action_obj = self::load_action($route_arr['a'], $route_arr['p'], $route_arr['ad']))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Error instantiating controller or action from provided route.'));

            return null;
        }

        $view_params = [];
        $view_params['action_obj'] = $action_obj;
        $view_params['controller_obj'] = $controller_obj;
        $view_params['parent_plugin_obj'] = $plugin_obj;
        $view_params['plugin'] = $plugin_obj?->instance_plugin_name();
        $view_params['template_data'] = $template_data;

        if (!($view_obj = PHS_View::init_view($template, $view_params))) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Error instantiating view in provided context.'));

            return null;
        }

        return $view_obj;
    }

    public static function platform_debug_data() : array
    {
        $now_secs = microtime(true);
        if (!($start_secs = self::get_data(self::PHS_START_TIME))) {
            $start_secs = $now_secs;
        }
        if (!($bootstrap_secs = self::get_data(self::PHS_BOOTSTRAP_END_TIME))) {
            $bootstrap_secs = $now_secs;
        }
        if (!($end_secs = self::get_data(self::PHS_END_TIME))) {
            $end_secs = $now_secs;
        }

        $bootstrap_time = $bootstrap_secs - $start_secs;
        $running_time = $end_secs - $start_secs;

        $return_arr = [];
        $return_arr['db_queries_count'] = db_query_count();
        $return_arr['bootstrap_time'] = $bootstrap_time;
        $return_arr['running_time'] = $running_time;
        $return_arr['memory_usage'] = memory_get_usage();
        $return_arr['memory_peak'] = memory_get_peak_usage();

        return $return_arr;
    }

    public static function get_current_user_db_details()
    {
        if (!($hook_result = self::_get_db_user_details())) {
            $hook_result = PHS_Hooks::default_user_db_details_hook_args();
        }

        return $hook_result['user_db_data'];
    }

    public static function get_current_session_db_details()
    {
        if (!($hook_result = self::_get_db_user_details())) {
            $hook_result = PHS_Hooks::default_user_db_details_hook_args();
        }

        return $hook_result['session_db_data'];
    }

    /**
     * @param string $library
     *
     * @return string
     */
    public static function get_core_library_full_path(string $library) : string
    {
        $library = PHS_Instantiable::safe_escape_library_name($library);
        if (empty($library)
         || !@file_exists(PHS_CORE_LIBRARIES_DIR.$library.'.php')) {
            return '';
        }

        return PHS_CORE_LIBRARIES_DIR.$library.'.php';
    }

    public static function spl_autoload_register(string $class_name) : void
    {
        self::st_reset_error();

        if (@class_exists($class_name, false)
         || !($class_details = PHS_Instantiable::extract_details_from_full_namespace_name($class_name))
         || empty($class_details['instance_type'])
         || $class_details['instance_type'] === PHS_Instantiable::INSTANCE_TYPE_UNDEFINED) {
            return;
        }

        if (!PHS_Instantiable::get_instance(true, $class_name)) {
            // class/file cannot be loaded, so we create an undefined instatiable...

            $newclass = new class extends PHS_Undefined_instantiable {
            };
            class_alias(get_class($newclass), $class_name);
        }
    }

    /**
     * Try loading a core library
     *
     * @param string $library Core library file to be loaded
     * @param null|array $params Loading parameters
     *
     * @return null|PHS_Library
     */
    public static function load_core_library(string $library, ?array $params = null) : ?PHS_Library
    {
        self::st_reset_error();

        $params ??= [];

        // We assume $library represents class name without namespace (otherwise it won't be a valid library name)
        // so class name is from "root" namespace
        if (empty($params['full_class_name'])) {
            $params['full_class_name'] = '\\'.ltrim($library, '\\');
        }
        if (empty($params['init_params'])) {
            $params['init_params'] = null;
        }
        $params['as_singleton'] = !empty($params['as_singleton']);

        if (!($library = PHS_Instantiable::safe_escape_library_name($library))) {
            self::st_set_error(self::ERR_LIBRARY, self::_t('Couldn\'t load core library.'));

            return null;
        }

        if (!empty($params['as_singleton'])
            && !empty(self::$_core_libraries_instances[$library])) {
            return self::$_core_libraries_instances[$library];
        }

        if (!($file_path = self::get_core_library_full_path($library))) {
            self::st_set_error(self::ERR_LIBRARY, self::_t('Couldn\'t load core library [%s].', $library));

            return null;
        }

        ob_start();
        include_once $file_path;
        ob_get_clean();

        if (!@class_exists($params['full_class_name'], false)) {
            self::st_set_error(self::ERR_LIBRARY, self::_t('Couldn\'t instantiate library class for core library [%s].', $library));

            return null;
        }

        /** @var PHS_Library $library_instance */
        if (empty($params['init_params'])) {
            $library_instance = new $params['full_class_name']();
        } else {
            $library_instance = new $params['full_class_name']($params['init_params']);
        }

        if (!($library_instance instanceof PHS_Library)) {
            self::st_set_error(self::ERR_LIBRARY, self::_t('Core library [%s] is not a PHS library.', $library));

            return null;
        }

        $location_details = $library_instance::get_library_default_location_paths();
        $location_details['library_file'] = $file_path;
        $location_details['library_path'] = rtrim(PHS_CORE_LIBRARIES_DIR, '/\\');
        $location_details['library_www'] = rtrim(PHS_CORE_LIBRARIES_WWW, '/');

        if (!$library_instance->set_library_location_paths($location_details)) {
            self::st_set_error(self::ERR_LIBRARY, self::_t('Core library [%s] couldn\'t set location paths.', $library));

            return null;
        }

        if (!empty($params['as_singleton'])) {
            self::$_core_libraries_instances[$library] = $library_instance;
        }

        return $library_instance;
    }

    /**
     * @param string $core_library Short library name (eg. ftp, paginator_exporter_csv, etc)
     * @param null|array $params Parameters passed to self::load_core_library() method
     *
     * @return null|PHS_Library Helper for self::load_core_library() method call which prepares class name and file name
     */
    public static function get_core_library_instance(string $core_library, ?array $params = null) : ?PHS_Library
    {
        self::st_reset_error();

        if (empty($core_library)
            || !PHS_Instantiable::safe_escape_library_name($core_library)) {
            self::st_set_error(self::ERR_LIBRARY, self::_t('Couldn\'t load core library.'));

            return null;
        }

        $core_library = strtolower($core_library);

        $library_params = $params ?? [];
        $library_params['full_class_name'] = '\\phs\\system\\core\\libraries\\PHS_'.ucfirst($core_library);

        if (!($library_obj = self::load_core_library('phs_'.$core_library, $library_params))) {
            return null;
        }

        return $library_obj;
    }

    /**
     * Returns an instance of a model. If model is part of a plugin $plugin will contain name of that plugin.
     *
     * @param string $model Model to be loaded (part of class name after PHS_Model_)
     * @param null|string $plugin Plugin where model is located (false means a core model)
     *
     * @return null|PHS_Model
     */
    public static function load_model(string $model, ?string $plugin = null) : ?PHS_Model
    {
        self::st_reset_error();

        if (!($model_name = PHS_Instantiable::safe_escape_class_name($model))) {
            self::st_set_error(self::ERR_LOAD_MODEL, self::_t('Couldn\'t load model %s from plugin %s.',
                $model, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        $class_name = 'PHS_Model_'.ucfirst(strtolower($model_name));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        /** @var PHS_Model $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_MODEL))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_MODEL, self::_t('Couldn\'t obtain instance for model %s from plugin %s.',
                $model, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        return $instance_obj;
    }

    /**
     * Returns an instance of a view. If view is part of a plugin $plugin will contain name of that plugin.
     *
     * @param null|string $view View to be loaded (part of class name after PHS_View_)
     * @param null|string $plugin Plugin where view is located (false means a core view)
     * @param bool $as_singleton Tells if view instance should be loaded as singleton or new instance
     *
     * @return null|PHS_View Returns false on error or an instance of loaded view
     */
    public static function load_view(?string $view = null, ?string $plugin = null, bool $as_singleton = true) : ?PHS_View
    {
        self::st_reset_error();

        $view_class = '';
        if (!empty($view)
            && !($view_class = PHS_Instantiable::safe_escape_class_name($view))) {
            self::st_set_error(self::ERR_LOAD_VIEW, self::_t('Couldn\'t load view %s from plugin %s.',
                $view, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        if (!empty($view_class)) {
            $class_name = 'PHS_View_'.ucfirst(strtolower($view_class));
        } else {
            $class_name = 'PHS_View';
        }

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        // Views are not singletons
        /** @var PHS_View $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_VIEW, $as_singleton))) {
            if (empty($plugin)) {
                self::st_set_error_if_not_set(self::ERR_LOAD_VIEW,
                    self::_t('Couldn\'t obtain instance for view %s from plugin %s.', $view,
                        PHS_Instantiable::CORE_PLUGIN));

                return null;
            }

            self::st_reset_error();

            // We tried loading plugin view, try again with a core view...
            if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, null, PHS_Instantiable::INSTANCE_TYPE_VIEW, $as_singleton))) {
                self::st_set_error_if_not_set(self::ERR_LOAD_VIEW,
                    self::_t('Couldn\'t obtain instance for view %s from plugin %s.', $view,
                        PHS_Instantiable::CORE_PLUGIN));

                return null;
            }
        }

        return $instance_obj;
    }

    /**
     * @param string $controller
     * @param null|string $plugin
     *
     * @return null|PHS_Controller Returns false on error or an instance of loaded controller
     */
    public static function load_controller(string $controller, ?string $plugin = null) : ?PHS_Controller
    {
        self::st_reset_error();

        if (!($controller_name = PHS_Instantiable::safe_escape_class_name($controller))) {
            self::st_set_error(self::ERR_LOAD_CONTROLLER,
                self::_t('Couldn\'t load controller %s from plugin %s.',
                    $controller, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        $class_name = 'PHS_Controller_'.ucfirst(strtolower($controller_name));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        /** @var PHS_Controller $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_CONTROLLER,
                self::_t('Couldn\'t obtain instance for controller %s from plugin %s.',
                    $controller, (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        return $instance_obj;
    }

    /**
     * @param string $action
     * @param string|bool $plugin
     * @param string $action_dir
     *
     * @return null|PHS_Action Returns false on error or an instance of loaded action
     */
    public static function load_action(string $action, $plugin = false, string $action_dir = '') : ?PHS_Action
    {
        self::st_reset_error();

        if (!is_string($action_dir)) {
            $action_dir = '';
        } else {
            $action_dir = trim(trim($action_dir), '/\\');
        }

        if (!($action_name = PHS_Instantiable::safe_escape_class_name($action))) {
            self::st_set_error(self::ERR_LOAD_ACTION,
                self::_t('Couldn\'t load action %s from plugin %s.',
                    ($action_dir !== '' ? $action_dir.'/' : '').$action,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        if ('' !== $action_dir
         && !($action_dir = PHS_Instantiable::safe_escape_instance_subdir($action_dir))) {
            self::st_set_error(self::ERR_LOAD_ACTION,
                self::_t('Couldn\'t load action %s from plugin %s.',
                    $action_dir.'/'.$action,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        $class_name = 'PHS_Action_'.ucfirst(strtolower($action_name));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = false;
        }

        // From this point on, $action_dir is a system path...
        if ($action_dir !== '') {
            $action_dir = str_replace('_', '/', $action_dir);
        }

        /** @var PHS_Action $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads(
            $class_name,
            $plugin,
            PHS_Instantiable::INSTANCE_TYPE_ACTION,
            true,
            $action_dir))
        ) {
            self::st_set_error_if_not_set(self::ERR_LOAD_ACTION,
                self::_t('Couldn\'t obtain instance for action %s from plugin %s.',
                    ($action_dir !== '' ? $action_dir.'/' : '').$action,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        return $instance_obj;
    }

    /**
     * @param string $contract
     * @param string|bool $plugin
     * @param string $contract_dir
     *
     * @return null|PHS_Contract Returns false on error || an instance of loaded contract
     */
    public static function load_contract(string $contract, $plugin = false, string $contract_dir = '') : ?PHS_Contract
    {
        self::st_reset_error();

        if (!is_string($contract_dir)) {
            $contract_dir = '';
        } else {
            $contract_dir = trim(trim($contract_dir), '/\\');
        }

        if (!($contract_name = PHS_Instantiable::safe_escape_class_name($contract))) {
            self::st_set_error(self::ERR_LOAD_CONTRACT,
                self::_t('Couldn\'t load contract %s from plugin %s.',
                    ($contract_dir !== '' ? $contract_dir.'/' : '').$contract,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        if ('' !== $contract_dir
         && !($contract_dir = PHS_Instantiable::safe_escape_instance_subdir($contract_dir))) {
            self::st_set_error(self::ERR_LOAD_CONTRACT,
                self::_t('Couldn\'t load contract %s from plugin %s.',
                    $contract_dir.'/'.$contract,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        $class_name = 'PHS_Contract_'.ucfirst(strtolower($contract_name));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = false;
        }

        // From this point on, $contract_dir is a system path...
        if ($contract_dir !== '') {
            $contract_dir = str_replace('_', '/', $contract_dir);
        }

        /** @var PHS_Action $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_CONTRACT, true, $contract_dir))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_CONTRACT,
                self::_t('Couldn\'t obtain instance for contract %s from plugin %s.',
                    ($contract_dir !== '' ? $contract_dir.'/' : '').$contract,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        return $instance_obj;
    }

    /**
     * @param string $event
     * @param string|bool $plugin
     * @param string $event_dir
     *
     * @return null|PHS_Event Returns false on error or an instance of loaded event
     */
    public static function load_event(string $event, $plugin = false, string $event_dir = '') : ?PHS_Event
    {
        self::st_reset_error();

        if (!is_string($event_dir)) {
            $event_dir = '';
        } else {
            $event_dir = trim(trim($event_dir), '/\\');
        }

        if (!($event_name = PHS_Instantiable::safe_escape_class_name($event))) {
            self::st_set_error(self::ERR_LOAD_EVENT,
                self::_t('Couldn\'t load event %s from plugin %s.',
                    ($event_dir !== '' ? $event_dir.'/' : '').$event,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        if ('' !== $event_dir
         && !($event_dir = PHS_Instantiable::safe_escape_instance_subdir($event_dir))) {
            self::st_set_error(self::ERR_LOAD_EVENT,
                self::_t('Couldn\'t load event %s from plugin %s.',
                    $event_dir.'/'.$event,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        $class_name = 'PHS_Event_'.ucfirst(strtolower($event_name));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = false;
        }

        // From this point on, $event_dir is a system path...
        if ($event_dir !== '') {
            $event_dir = str_replace('_', '/', $event_dir);
        }

        /** @var PHS_Event $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin,
            PHS_Instantiable::INSTANCE_TYPE_EVENT, true, $event_dir))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_EVENT,
                self::_t('Couldn\'t obtain instance for event %s from plugin %s.',
                    ($event_dir !== '' ? $event_dir.'/' : '').$event,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        return $instance_obj;
    }

    /**
     * @param string $graphql_type
     * @param null|string $plugin
     * @param string $type_dir
     *
     * @return null|PHS_Graphql_Type Returns null on error or an instance of loaded GraphQL type
     */
    public static function load_graphql_type(string $graphql_type, ?string $plugin = null, string $type_dir = '') : ?PHS_Graphql_Type
    {
        self::st_reset_error();

        $type_dir = trim(trim($type_dir), '/\\');

        if (!($graphql_class = PHS_Instantiable::safe_escape_class_name($graphql_type))) {
            self::st_set_error(self::ERR_LOAD_GRAPHQL,
                self::_t('Couldn\'t load GraphQL type %s from plugin %s.',
                    ($type_dir !== '' ? $type_dir.'/' : '').$graphql_type,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        if ('' !== $type_dir
            && !($type_dir = PHS_Instantiable::safe_escape_instance_subdir($type_dir))) {
            self::st_set_error(self::ERR_LOAD_GRAPHQL,
                self::_t('Couldn\'t load GraphQL type %s from plugin %s.',
                    $type_dir.'/'.$graphql_type,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        $class_name = 'PHS_Graphql_'.ucfirst(strtolower($graphql_class));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        // From this point on, $type_dir is a system path...
        if ($type_dir !== '') {
            $type_dir = str_replace('_', '/', $type_dir);
        }

        /** @var PHS_Graphql_Type $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin,
            PHS_Instantiable::INSTANCE_TYPE_GRAPHQL, true, $type_dir))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_GRAPHQL,
                self::_t('Couldn\'t obtain instance for GraphQL type %s from plugin %s.',
                    ($type_dir !== '' ? $type_dir.'/' : '').$graphql_type,
                    (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        return $instance_obj;
    }

    /**
     * @param string $scope
     * @param null|string $plugin
     *
     * @return null|PHS_Scope Returns false on error or an instance of loaded scope
     */
    public static function load_scope(string $scope, ?string $plugin = null) : ?PHS_Scope
    {
        self::st_reset_error();

        if (!($scope_name = PHS_Instantiable::safe_escape_class_name($scope))) {
            self::st_set_error(self::ERR_LOAD_SCOPE, self::_t('Couldn\'t load scope %s from plugin %s.',
                $scope, $plugin ?? PHS_Instantiable::CORE_PLUGIN));

            return null;
        }

        $class_name = 'PHS_Scope_'.ucfirst(strtolower($scope_name));

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        /** @var PHS_Scope $instance_obj */
        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_SCOPE))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_SCOPE, self::_t('Couldn\'t obtain instance for scope %s from plugin %s.',
                $scope, $plugin ?? PHS_Instantiable::CORE_PLUGIN));

            return null;
        }

        return $instance_obj;
    }

    /**
     * @param string $plugin_name Plugin name to be loaded
     *
     * @return null|PHS_Plugin Returns false on error || an instance of loaded plugin
     */
    public static function load_plugin(string $plugin_name) : ?PHS_Plugin
    {
        self::st_reset_error();

        if (empty($plugin_name)
            || $plugin_name === PHS_Instantiable::CORE_PLUGIN
            || !($plugin_safe_name = PHS_Instantiable::safe_escape_class_name($plugin_name))) {
            self::st_set_error(self::ERR_LOAD_PLUGIN, self::_t('Couldn\'t load plugin %s.',
                (empty($plugin_name) ? PHS_Instantiable::CORE_PLUGIN : $plugin_name)));

            return null;
        }

        $class_name = 'PHS_Plugin_'.ucfirst(strtolower($plugin_safe_name));

        if (!($instance_obj = PHS_Instantiable::get_instance_for_loads($class_name, $plugin_name, PHS_Instantiable::INSTANCE_TYPE_PLUGIN))) {
            self::st_set_error_if_not_set(self::ERR_LOAD_PLUGIN,
                self::_t('Couldn\'t obtain instance for plugin class %s from plugin %s.', $class_name, $plugin_name));

            return null;
        }

        return $instance_obj;
    }

    /**
     * Read directory corresponding to $instance_type from $plugin and return instance type names (as required for PHS::load_* method)
     * This only returns file names, does no check if class is instantiable...
     *
     * @param null|string $plugin Core plugin if false || plugin name as string
     * @param string $instance_type What script files should we check PHS_Instantiable::INSTANCE_TYPE_*
     *
     * @return null|array
     */
    public static function get_plugin_scripts_from_dir(?string $plugin = null, string $instance_type = PHS_Instantiable::INSTANCE_TYPE_PLUGIN) : ?array
    {
        self::st_reset_error();

        if (!($class_name = PHS_Instantiable::get_class_name_from_instance_name($instance_type))) {
            self::st_set_error(self::ERR_SCRIPT_FILES, self::_t('Invalid instance type to obtain script files list.'));

            return null;
        }

        if (empty($plugin) || $plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;

            if ($instance_type === PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                self::st_set_error(self::ERR_SCRIPT_FILES, self::_t('There is no CORE plugin.'));

                return null;
            }
        } elseif (!($plugin = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_SCRIPT_FILES, self::_t('Invalid plugin name to obtain script files list.'));

            return null;
        }

        // Get generic information about an index instance to obtain paths to be checked...
        if (!($instance_details = PHS_Instantiable::get_instance_details($class_name, $plugin, $instance_type))) {
            self::st_set_error_if_not_set(self::ERR_SCRIPT_FILES, self::_t('Couldn\'t obtain instance details for generic controller index from plugin %s .', (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        if (empty($instance_details['instance_path'])) {
            self::st_set_error_if_not_set(self::ERR_SCRIPT_FILES, self::_t('Couldn\'t read controllers directory from plugin %s .', (empty($plugin) ? PHS_Instantiable::CORE_PLUGIN : $plugin)));

            return null;
        }

        // Plugin might not have even directory created meaning no script files
        if (!@is_dir($instance_details['instance_path'])
            || !@is_readable($instance_details['instance_path'])) {
            return [];
        }

        // Check spacial case if we are asked for plugin script (as there's only one)
        $resulting_instance_names = [];
        if ($plugin !== PHS_Instantiable::CORE_PLUGIN
            && $instance_type === PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
            if (!@file_exists($instance_details['instance_path'].'phs_'.$plugin.'.php')) {
                self::st_set_error_if_not_set(self::ERR_SCRIPT_FILES, self::_t('Couldn\'t read plugin script file for plugin %s .', $plugin));

                return null;
            }

            $resulting_instance_names[] = $plugin;
        } else {
            if (empty($instance_details['instance_type_accepts_subdirs'])) {
                $file_scripts = @glob($instance_details['instance_path'].'phs_*.php');
            } else {
                $file_scripts = PHS_Utils::get_files_recursive($instance_details['instance_path'], ['basename_regex' => '/phs_(.+).php/']);
            }

            if (!empty($file_scripts) && is_array($file_scripts)) {
                foreach ($file_scripts as $file_script) {
                    $script_file_name = basename($file_script);
                    // Special check as plugin script is only one
                    if (!str_starts_with($script_file_name, 'phs_')
                        || !str_ends_with($script_file_name, '.php')) {
                        continue;
                    }

                    $instance_file_name = substr(substr($script_file_name, 4), 0, -4);
                    $instance_file_dir = trim(str_replace([$script_file_name, $instance_details['instance_path']],
                        '', $file_script),
                        '/\\');

                    $resulting_instance_names[] = [
                        'file' => $instance_file_name,
                        'dir'  => $instance_file_dir,
                    ];
                }
            }
        }

        return $resulting_instance_names;
    }

    /**
     * Validates a hook name and returns valid value or false if hook name is not valid.
     *
     * @param string $hook_name
     *
     * @return string Valid hook name or false if hook_name is not valid.
     */
    public static function prepare_hook_name(string $hook_name) : string
    {
        if (!($hook_name = strtolower(trim($hook_name)))) {
            return '';
        }

        return $hook_name;
    }

    public static function get_registered_hooks() : array
    {
        return self::$hooks;
    }

    /**
     * Adds a hook in call queue. When a hook is fired, script will call each callback function in order of their
     * priority. Along with standard hook parameters (check each hook definition to see which are these) you can add
     * extra parameters which you pass at hook definition
     *
     * @param string $hook_name Hook name
     * @param null|callable $hook_callback Method/Function to be called
     * @param null|array $hook_extra_args Extra arguments to be passed when hook is fired
     * @param null|array $extra Extra details related to current hook:
     *                          chained_hook    If true result of hook call will overwrite parameters of next hook callback (can be used as filters)
     *                          priority        Order in which hooks are fired is given by $priority parameter
     *                          stop_chain      If true will stop hooks execution (used if chained_hook is true)
     *
     * @return bool True if hook was added with success || false otherwise
     */
    public static function register_hook(string $hook_name, $hook_callback = null, ?array $hook_extra_args = null, ?array $extra = null) : bool
    {
        self::st_reset_error();

        if (!($hook_name = self::prepare_hook_name($hook_name))) {
            self::st_set_error(self::ERR_HOOK_REGISTRATION, self::_t('Please provide a valid hook name.'));

            return false;
        }

        if (empty($hook_callback) && !is_callable($hook_callback)) {
            self::st_set_error(self::ERR_HOOK_REGISTRATION, self::_t('Couldn\'t add callback for hook %s.', $hook_name));

            return false;
        }

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        if (empty($extra['overwrite_result'])) {
            $extra['overwrite_result'] = false;
        }
        if (empty($extra['chained_hook'])) {
            $extra['chained_hook'] = false;
        }
        if (empty($extra['stop_chain'])) {
            $extra['stop_chain'] = false;
        }
        if (!isset($extra['priority'])) {
            $extra['priority'] = 10;
        } else {
            $extra['priority'] = (int)$extra['priority'];
        }

        $hookdata = [];
        $hookdata['callback'] = $hook_callback;
        $hookdata['args'] = $hook_extra_args;
        $hookdata['chained'] = !empty($extra['chained_hook']);
        $hookdata['stop_chain'] = !empty($extra['stop_chain']);
        $hookdata['overwrite_result'] = !empty($extra['overwrite_result']);

        self::$hooks[$hook_name][$extra['priority']][] = $hookdata;

        ksort(self::$hooks[$hook_name], SORT_NUMERIC);

        return true;
    }

    /**
     * @param bool|string $hook_name
     *
     * @return bool
     */
    public static function unregister_hooks($hook_name = false) : bool
    {
        if ($hook_name === false) {
            self::$hooks = [];

            return true;
        }

        if (!($hook_name = self::prepare_hook_name($hook_name))
         || !isset(self::$hooks[$hook_name])) {
            return false;
        }

        unset(self::$hooks[$hook_name]);

        return true;
    }

    /**
     * @param string $hook_name
     *
     * @return bool
     */
    public static function hook_has_callbacks(string $hook_name) : bool
    {
        return !(!($hook_name = self::prepare_hook_name($hook_name))
         || empty(self::$hooks[$hook_name]));
    }

    /**
     * @param string $hook_name Hook name
     * @param array $hook_args Hook arguments
     * @param array|bool $params Any specific parameters required on this trigger only
     *
     * @return null|array
     */
    public static function trigger_hooks(string $hook_name, array $hook_args = [], $params = false) : ?array
    {
        if (!($hook_name = self::prepare_hook_name($hook_name))
         || empty(self::$hooks[$hook_name]) || !is_array(self::$hooks[$hook_name])) {
            return null;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        $params['stop_on_first_error'] = !empty($params['stop_on_first_error']);

        if (empty($hook_args) || !is_array($hook_args)) {
            $hook_args = PHS_Hooks::default_common_hook_args();
        } else {
            $hook_args = PHS_Hooks::hook_args_reset_error($hook_args);
        }

        foreach (self::$hooks[$hook_name] as $priority => $hooks_array) {
            if (empty($hooks_array) || !is_array($hooks_array)) {
                continue;
            }

            foreach ($hooks_array as $hook_callback) {
                if (empty($hook_callback) || !is_array($hook_callback)
                 || empty($hook_callback['callback'])
                 || (is_array($hook_callback['callback'])
                     && ($instance_obj = $hook_callback['callback'][0] ?? null)
                     && $instance_obj instanceof PHS_Instantiable
                     && ($plugin_obj = $instance_obj->get_plugin_instance())
                     && !$plugin_obj->plugin_active())
                ) {
                    continue;
                }

                if (!@is_callable($hook_callback['callback'])) {
                    if (self::st_debugging_mode()) {
                        PHS_Logger::critical('Hook ['.$hook_name.'] not all callable ('
                            .@print_r($hook_callback['callback'], true).')', PHS_Logger::TYPE_DEBUG);
                    }
                    continue;
                }

                if (empty($hook_callback['args']) || !is_array($hook_callback['args'])) {
                    $hook_callback['args'] = [];
                }

                $call_hook_args = self::merge_array_assoc($hook_callback['args'], $hook_args);

                if (($result = @call_user_func($hook_callback['callback'], $call_hook_args)) === null
                 || $result === false) {
                    continue;
                }

                // If required for this trigger to stop on first error...
                // !!! Although there is an error we return a hook argument array, and it is up to you to check
                // !!! if any errors in resulting hook arguments
                if (!empty($params['stop_on_first_error'])
                 && PHS_Hooks::hook_args_has_error($result)) {
                    return $result;
                }

                $resulting_buffer = '';
                if (is_array($result)
                 && !empty($call_hook_args['concatenate_buffer']) && is_string($call_hook_args['concatenate_buffer'])) {
                    if (isset($result[$call_hook_args['concatenate_buffer']]) && is_string($result[$call_hook_args['concatenate_buffer']])
                     && isset($hook_args[$call_hook_args['concatenate_buffer']]) && is_string($hook_args[$call_hook_args['concatenate_buffer']])) {
                        $resulting_buffer = $hook_args[$call_hook_args['concatenate_buffer']].$result[$call_hook_args['concatenate_buffer']];
                    }
                }

                if (!empty($hook_callback['chained'])
                 && is_array($result)) {
                    if (!empty($hook_callback['overwrite_result'])) {
                        $hook_args = $result;
                    } else {
                        $hook_args = self::merge_array_assoc_recursive($hook_args, $result);
                    }
                }

                if (!empty($resulting_buffer)
                 && !empty($call_hook_args['concatenate_buffer']) && is_string($call_hook_args['concatenate_buffer'])
                 && isset($hook_args[$call_hook_args['concatenate_buffer']]) && is_string($hook_args[$call_hook_args['concatenate_buffer']])) {
                    $hook_args[$call_hook_args['concatenate_buffer']] = $resulting_buffer;
                    $resulting_buffer = '';
                }
            }
        }

        // Return final hook arguments as result of hook calls
        return $hook_args;
    }

    /**
     * @return int
     */
    public static function get_suppressed_errror_reporting_level() : int
    {
        if (defined('PHP_VERSION')
         && version_compare(constant('PHP_VERSION'), '8.0.0', '>=')) {
            return E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
        }

        return 0;
    }

    public static function error_handler($errno, $errstr, $errfile, $errline, $errcontext = false)
    {
        if (self::get_suppressed_errror_reporting_level() === error_reporting()) {
            return true;
        }

        $backtrace_str = self::st_debug_call_backtrace();

        $errno = (int)$errno;
        $error_type = 'Unknown error type';

        switch ($errno) {
            case E_ERROR:
            case E_USER_ERROR:
                // end all buffers
                while (@ob_end_flush());

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

        if (!@class_exists(PHS_Scope::class, false)) {
            echo $error_type.': ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr."\n";
            echo $backtrace_str."\n";
        } else {
            $current_scope = PHS_Scope::current_scope();

            switch ($current_scope) {
                default:
                case PHS_Scope::SCOPE_WEB:
                    echo '<strong>'.$error_type.'</strong> ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr.'<br/>'."\n";
                    echo '<pre>'.$backtrace_str.'</pre>';
                    break;

                case PHS_Scope::SCOPE_AJAX:
                case PHS_Scope::SCOPE_API:

                    if (self::st_debugging_mode()) {
                        if (!@class_exists(PHS_Notifications::class, false)) {
                            $error_arr = [
                                'backtrace'       => $backtrace_str,
                                'error_code'      => $errno,
                                'error_file'      => $errfile,
                                'error_line'      => $errline,
                                'response_status' => [
                                    'success_messages' => [],
                                    'warning_messages' => [],
                                    'error_messages'   => [$errstr],
                                ],
                            ];
                        } else {
                            $error_arr = [
                                'backtrace'       => $backtrace_str,
                                'error_code'      => $errno,
                                'error_file'      => $errfile,
                                'error_line'      => $errline,
                                'response_status' => [
                                    'success_messages' => PHS_Notifications::notifications_success(),
                                    'warning_messages' => PHS_Notifications::notifications_warnings(),
                                    'error_messages'   => array_merge(PHS_Notifications::notifications_errors(), [$errstr]),
                                ],
                            ];
                        }
                    } else {
                        $error_arr = [
                            'backtrace'       => '',
                            'error_code'      => -1,
                            'error_file'      => @basename($errfile),
                            'error_line'      => $errline,
                            'response_status' => [
                                'success_messages' => [],
                                'warning_messages' => [],
                                'error_messages'   => ['Internal error'],
                            ],
                        ];
                    }

                    if (!@headers_sent()) {
                        @header('HTTP/1.1 500 Application error');
                        @header('Content-Type: application/json');
                    }

                    echo @json_encode($error_arr);

                    exit;

                case PHS_Scope::SCOPE_BACKGROUND:
                case PHS_Scope::SCOPE_AGENT:
                    if (@class_exists(PHS_Logger::class, false)) {
                        $error_msg = $error_type.': ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr."\n"
                            .$backtrace_str;

                        PHS_Logger::error($error_msg,
                            ($current_scope === PHS_Scope::SCOPE_BACKGROUND ? PHS_Logger::TYPE_BACKGROUND : PHS_Logger::TYPE_AGENT)
                        );
                    }
                    break;
            }
        }

        if (@class_exists(PHS_Logger::class, false)) {
            PHS_Logger::error($error_type.': ['.$errno.'] ('.$errfile.':'.$errline.') '.$errstr."\n".$backtrace_str,
                PHS_Logger::TYPE_DEBUG);
        }

        if ($errno === E_ERROR || $errno === E_USER_ERROR) {
            exit(1);
        }

        return true;
    }

    /**
     * @noinspection ForgottenDebugOutputInspection
     */
    public static function tick_handler()
    {
        // var_dump(self::st_debug_call_backtrace(1));
    }

    private static function reset_registry() : void
    {
        self::set_data(self::REQUEST_HOST_CONFIG, false);
        self::set_data(self::REQUEST_HOST, '');
        self::set_data(self::REQUEST_PORT, '');
        self::set_data(self::REQUEST_HTTPS, false);

        self::set_data(self::CURRENT_THEME, '');
        self::set_data(self::DEFAULT_THEME, '');

        self::set_data(self::RUNNING_ACTION, false);
        self::set_data(self::RUNNING_CONTROLLER, false);

        self::set_data(self::PHS_PAGE_SETTINGS, false);
    }

    private static function _current_user_trigger(bool $force = false) : ?array
    {
        static $hook_result = null;

        if (!empty($hook_result)
            && empty($force)) {
            return $hook_result;
        }

        $hook_args = PHS_Hooks::default_user_db_details_hook_args();
        $hook_args['force_check'] = !empty($force);

        if (!($hook_result = PHS_Hooks::trigger_current_user($hook_args))) {
            $hook_result = null;
        }

        return $hook_result;
    }

    private static function _get_db_user_details($force = false)
    {
        static $hook_result = false;

        if (empty($force) && !empty($hook_result)) {
            return $hook_result;
        }

        if (!empty($force)) {
            $hook_result = PHS_Hooks::default_user_db_details_hook_args();
        }

        $hook_result = self::trigger_hooks(PHS_Hooks::H_USER_DB_DETAILS, $hook_result);

        return $hook_result;
    }
}
