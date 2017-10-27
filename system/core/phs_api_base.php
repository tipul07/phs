<?php

namespace phs;

use \phs\PHS_api;
use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;

//! @version 1.00

abstract class PHS_api_base extends PHS_Registry
{
    const ERR_RUN_ROUTE = 30001, ERR_AUTHENTICATION = 30002, ERR_HTTP_METHOD = 30003, ERR_HTTP_PROTOCOL = 30004, ERR_APIKEY = 30005;

    const DEFAULT_VERSION = 1;

    const GENERIC_ERROR_CODE = 500, GENERIC_OK_CODE = 200;

    // Most used HTTP error codes
    const H_CODE_OK = 200, H_CODE_OK_CREATED = 201, H_CODE_OK_ACCEPTED = 202, H_CODE_OK_NO_CONTENT = 204,
          H_CODE_BAD_REQUEST = 400, H_CODE_UNAUTHORIZED = 401, H_CODE_FORBIDDEN = 403, H_CODE_NOT_FOUND = 404, H_CODE_METHOD_NOT_ALLOWED = 405,
          H_CODE_INTERNAL_SERVER_ERROR = 500, H_CODE_NOT_IMPLEMENTED = 501, H_CODE_SERVICE_UNAVAILABLE = 503;

    // API version
    const PARAM_VERSION = 'v',
        // This is an API route (NOT necessary PHS route) This can be translated from aliases into a PHS route (if required) by plugins
        PARAM_API_ROUTE = '_ar',
        // Tells API class to arrange request parameters in such way that normal SCOPE_WEB actions can be used in API calls
        PARAM_WEB_SIMULATION = '_sw',
        // Tells API class that original request was done using apache mod_rewrite (or similar).
        // This parameter is appended to the request in rewrite rule
        PARAM_USING_REWRITE = '_rw';

    /** @var bool|array $raw_query_params */
    protected $raw_query_params = false;

    /** @var bool|array $init_params */
    protected $init_query_params = false;

    /** @var array $allowed_http_methods All allowed HTTP methods in lowercase */
    protected $allowed_http_methods = array( 'get', 'post', 'delete', 'patch' );

    /** @var bool|array $my_flow Instance API flow  */
    protected $my_flow = false;

    /** @var bool|array $_framework_settings API settings set in admin plugin settings */
    protected static $_framework_settings = array();

    /**
     * @return bool|array Returns true if custom authentication is ok or false if authentication failed
     */
    abstract protected function _check_api_authentication();

    /**
     * Override this method in case you want special code to be run before running actual action
     *
     * @return bool Return true to continue running or false and set an error in case running action should stop
     */
    protected function _before_route_run()
    {
        return true;
    }

    /**
     * Override this method in case you want special code to be run after running actual action
     *
     * @return bool Return true to continue running or false and set an error in case flow should stop
     */
    protected function _after_route_run()
    {
        return true;
    }

    protected function _default_api_flow()
    {
        return array(
            'die_when_needed' => true,

            'response_headers' => array(),
            'raw_response_headers' => array(),
            'response_body' => '',
            'response_array' => array(),

            'http_protocol' => 'HTTP/1.1',
            'api_method' => 'get',
            'content_type' => 'application/json',

            'original_api_route' => false,
            'final_api_route' => false,
            'phs_route' => false,

            // Values used in HTTP Authorization header (not necessary an user and password in the system)
            'api_user' => '',
            'api_pass' => '',

            // Any information related to API Key used in the request (any API implementation will use this as required)
            'api_key_data' => false,
            // In case API key wants to consider this request authenticated as a specific user (from users table), put users.id value here...
            'api_key_user_id' => 0,

            // User under which API actions are taken
            'api_account_data' => false,
        );
    }

    private function _special_flow_keys()
    {
        return array( 'api_method', 'http_protocol', 'content_type', 'response_headers', 'raw_response_headers' );
    }

    public function api_flow_value( $key = null, $val = null )
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        if( $key === null )
            return $this->my_flow;

        // 'api_method' will be set using $this->set_http_method();
        if( $val === null )
        {
            if( !is_array( $key ) )
            {
                if( is_scalar( $key )
                and array_key_exists( $key, $this->my_flow ) )
                    return $this->my_flow[$key];

                return null;
            }

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey )
                 or !array_key_exists( $kkey, $this->my_flow )
                 or in_array( $kkey, $this->_special_flow_keys() ) )
                    continue;

                $this->my_flow[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key )
         or !array_key_exists( $key, $this->my_flow )
         or in_array( $key, $this->_special_flow_keys() ) )
            return null;

        $this->my_flow[$key] = $val;

        return true;
    }

    public function allowed_http_methods( $methods_arr = false )
    {
        if( $methods_arr === false )
            return $this->allowed_http_methods;

        if( is_array( $methods_arr ) )
        {
            if( !($new_methods = self::extract_strings_from_array( $methods_arr, array( 'to_lowercase' => true ) )) )
                return false;

            $this->allowed_http_methods = $new_methods;

            return $new_methods;
        }

        return false;
    }

    /**
     * @param bool|array $args Arguments which must be added in query string (other than predefined ones)
     *
     * @return array Arguments to be added to query string of API URL
     */
    protected function _get_predefined_api_url_params( $args = false, $extra = false )
    {
        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( !isset( $extra['include_version'] ) )
            $extra['include_version'] = true;
        else
            $extra['include_version'] = (!empty( $extra['include_version'] ));

        if( !empty( $this->raw_query_params ) and is_array( $this->raw_query_params ) )
        {
            foreach( $this->raw_query_params as $key => $val )
            {
                // rewrite parameter is set in rewrite rule...
                // put in parameters parsed value
                if( $key == self::PARAM_USING_REWRITE
                 or $key == self::PARAM_API_ROUTE
                 or !isset( $this->init_query_params[$key] )
                 or ($key == self::PARAM_VERSION and empty( $extra['include_version'] )) )
                    continue;

                $args[$key] = $this->init_query_params[$key];
            }
        }

        return $args;
    }

    /**
     * Initialize API object
     *
     * @param bool|array $init_params Array with parameters required to initialize API object
     *
     * @return bool If any errors return false and set error
     */
    public function _init_api_query_params( $init_params = false )
    {
        $this->reset_error();

        if( empty( $init_params ) or !is_array( $init_params ) )
            $init_params = array();

        $this->raw_query_params = $init_params;
        $this->init_query_params = $this->default_query_params();

        if( !empty( $this->raw_query_params[self::PARAM_VERSION] ) )
            $this->init_query_params[self::PARAM_VERSION] = floatval( $this->raw_query_params[self::PARAM_VERSION] );

        else
            $this->init_query_params[self::PARAM_VERSION] = self::DEFAULT_VERSION;

        $this->init_query_params[self::PARAM_USING_REWRITE] = (!empty( $this->raw_query_params[self::PARAM_USING_REWRITE] ));

        if( !PHS_api::framework_api_can_simulate_web() )
            $this->init_query_params[self::PARAM_WEB_SIMULATION] = false;
        else
            $this->init_query_params[self::PARAM_WEB_SIMULATION] = (!empty( $this->raw_query_params[self::PARAM_WEB_SIMULATION] ));

        $this->init_query_params[self::PARAM_API_ROUTE] = (!empty( $this->raw_query_params[self::PARAM_API_ROUTE] )?self::prepare_api_route_string( $this->raw_query_params[self::PARAM_API_ROUTE] ):'');

        return true;
    }

    public static function prepare_api_route_string( $route_str )
    {
        if( !is_string( $route_str ) )
            return '';

        return trim( $route_str, '/- ' );
    }

    public function api_authentication( $credentials_arr = false )
    {
        $this->reset_error();

        $new_credentials_arr = array(
            'api_user' => '',
            'api_pass' => '',
        );

        if( !empty( $credentials_arr ) and is_array( $credentials_arr ) )
        {
            $new_credentials_arr['api_user'] = (isset( $credentials_arr['api_user'] )?$credentials_arr['api_user']:'');
            $new_credentials_arr['api_pass'] = (isset( $credentials_arr['api_pass'] )?$credentials_arr['api_pass']:'');
        } else
        {
            if( isset( $_SERVER['PHP_AUTH_USER'] ) )
                $new_credentials_arr['api_user'] = $_SERVER['PHP_AUTH_USER'];

            if( isset( $_SERVER['PHP_AUTH_PW'] ) )
                $new_credentials_arr['api_pass'] = $_SERVER['PHP_AUTH_PW'];
        }

        $this->api_flow_value( $new_credentials_arr );

        return $this->_check_api_authentication();
    }

    /**
     * @param bool|array $route_arr Route array defining plugin, controller and action to be used
     * @param bool|array $args Query parameters to be set for this URL
     * @param bool|array $extra Extra parameters sent to method
     *
     * @return mixed
     */
    final public function url( $route_arr = false, $args = false, $extra = false )
    {
        if( empty( $route_arr ) or !is_array( $route_arr ) )
            $route_arr = array();

        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        $api_url_params = array();
        $api_url_params['include_version'] = (empty( $this->init_query_params[self::PARAM_USING_REWRITE] ));

        if( !($args = $this->_get_predefined_api_url_params( $args ))
         or !is_array( $args ) )
            $args = array();

        $extra['for_scope'] = PHS_Scope::SCOPE_API;

        // Using special rewrite for api routes
        if( !empty( $this->init_query_params[self::PARAM_USING_REWRITE] ) )
        {
            $route_arr = PHS::validate_route_from_parts( $route_arr, true );

            if( !($route = PHS::route_from_parts( $route_arr )) )
                $route = 'invalidApiRoute_'.
                    (!empty( $route_arr['p'] )?$route_arr['p']:'').'::'.
                    (!empty( $route_arr['c'] )?$route_arr['c']:'').'::'.
                    (!empty( $route_arr['a'] )?$route_arr['a']:'');

            if( !($query_string = @http_build_query( $args )) )
                $query_string = '';

            if( !empty( $extra['raw_params'] ) and is_array( $extra['raw_params'] ) )
            {
                // Parameters that shouldn't be run through http_build_query as values will be rawurlencoded and we might add javascript code in parameters
                // eg. $extra['raw_params'] might be an id passed as javascript function parameter
                if( ($raw_query = array_to_query_string( $extra['raw_params'], array( 'raw_encode_values' => false ) )) )
                    $query_string .= ($query_string!=''?'&':'').$raw_query;
            }

            return PHS::get_base_url( $route_arr['force_https'] ).'api/v'.$this->get_api_version().'/'.$route.($query_string!=''?'?'.$query_string:'');
        }

        return PHS::url( $route_arr, $args, $extra );
    }

    /**
     * @param array|bool $extra Parameters for method
     *
     * @return array|bool False in case of error or an action result array
     */
    final public function run_route( $extra = false )
    {
        self::st_reset_error();

        if( !PHS_Scope::current_scope( PHS_Scope::SCOPE_API ) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_RUN_ROUTE );
            else
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Error preparing API environment.' ) );

            return false;
        }

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( ($final_api_route = PHS_api::tokenize_api_route( $this->get_api_route() )) === false )
        {
            $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Couldn\'t parse provided API route.' ) );
            return false;
        }

        if( PHS::st_debugging_mode() )
            PHS_Logger::logf( 'Request route ['.implode( '/', $final_api_route ).']', PHS_Logger::TYPE_API );

        $this->api_flow_value( 'original_api_route', $final_api_route );

        // Let plugins change API provided route
        $hook_args = PHS_Hooks::default_api_hook_args();
        $hook_args['api_obj'] = $this;
        $hook_args['api_route'] = $final_api_route;

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_API_ROUTE, $hook_args ))
        and is_array( $hook_args )
        and !empty( $hook_args['altered_api_route'] ) and is_array( $hook_args['altered_api_route'] ) )
        {
            if( !($final_api_route = PHS_api::validate_tokenized_api_route( $hook_args['altered_api_route'] )) )
            {
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Invalid API route obtained from plugins.' ) );
                return false;
            }
        }

        $this->api_flow_value( 'final_api_route', $final_api_route );

        if( PHS::st_debugging_mode() )
            PHS_Logger::logf( 'Final API route ['.implode( '/', $final_api_route ).']', PHS_Logger::TYPE_API );

        if( !($phs_route = PHS_api::get_phs_route_from_api_route( $final_api_route, $this->http_method() )) )
        {
            if( PHS::st_debugging_mode() )
                PHS_Logger::logf( 'No PHS route matched API route.', PHS_Logger::TYPE_API );

            if( !($phs_route = PHS::parse_route( implode( '/', $final_api_route ), true )) )
            {
                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_RUN_ROUTE );
                else
                    $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Couldn\'t parse provided API route into a framework route.' ) );

                return false;
            }
        }

        $phs_route = PHS::validate_route_from_parts( $phs_route, true );

        if( PHS::st_debugging_mode() )
        {
            if( !($route_str = PHS::route_from_parts( $phs_route )) )
                $route_str = 'N/A';

            PHS_Logger::logf( 'Resulting PHS route ['.$route_str.']', PHS_Logger::TYPE_API );
        }

        $this->api_flow_value( 'phs_route', $phs_route );

        PHS::set_route( $phs_route );

        if( !$this->_before_route_run() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Running action was stopped by API instance.' ) );

            return false;
        }

        $execution_params = array();
        $execution_params['die_on_error'] = false;

        if( !($action_result = PHS::execute_route( $execution_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_RUN_ROUTE );
            else
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Error executing route [%s].', PHS::get_route_as_string() ) );

            return false;
        }

        if( !$this->_after_route_run() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Flow was stopped by API instance after action run.' ) );

            return false;
        }

        return $action_result;
    }

    public function get_apikey_by_apikey( $apikey )
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
        if( empty( $apikey )
         or !($apikeys_model = PHS::load_model( 'api_keys' ))
         or !($apikey_arr = $apikeys_model->get_details_fields( array( 'api_key' => $apikey ) )) )
        {
            $this->set_error( self::ERR_APIKEY, $this->_pt( '' ) );
            return false;
        }

        $this->api_flow_value( 'api_key_data', $apikey_arr );

        if( !empty( $apikey_arr['uid'] ) )
            $this->api_flow_value( 'api_key_user_id', intval( $apikey_arr['uid'] ) );
        else
            $this->api_flow_value( 'api_key_user_id', 0 );

        return $apikey_arr;
    }

    public function default_query_params()
    {
        return array(
            self::PARAM_VERSION => self::DEFAULT_VERSION,
            self::PARAM_API_ROUTE => '',
            self::PARAM_USING_REWRITE => false,
            self::PARAM_WEB_SIMULATION => false,
        );
    }

    public function get_api_version()
    {
        if( empty( $this->init_query_params ) or !is_array( $this->init_query_params ) )
            return false;

        return (empty( $this->init_query_params[self::PARAM_VERSION] )?self::DEFAULT_VERSION:$this->init_query_params[self::PARAM_VERSION]);
    }

    public function get_api_route()
    {
        if( empty( $this->init_query_params ) or !is_array( $this->init_query_params )
         or empty( $this->init_query_params[self::PARAM_API_ROUTE] ) )
            return '';

        return $this->init_query_params[self::PARAM_API_ROUTE];
    }

    public function is_rewrite_request()
    {
        if( empty( $this->init_query_params ) or !is_array( $this->init_query_params )
         or empty( $this->init_query_params[self::PARAM_USING_REWRITE] ) )
            return false;

        return true;
    }

    public function is_web_simulation()
    {
        if( empty( $this->init_query_params ) or !is_array( $this->init_query_params )
         or empty( $this->init_query_params[self::PARAM_WEB_SIMULATION] ) )
            return false;

        return true;
    }

    public function response_header_set( $key )
    {
        if( !is_string( $key ) )
            return null;

        $key = strtolower( trim( $key ) );
        if( !array_key_exists( $key, $this->my_flow['response_headers'] ) )
            return null;

        return $this->my_flow['response_headers'][$key];
    }

    public function response_headers( $raw = false )
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        if( !empty( $raw ) )
            return $this->my_flow['raw_response_headers'];

        return $this->my_flow['response_headers'];
    }

    public function set_response_headers( $headers_arr, $append = true )
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        if( !is_array( $headers_arr ) )
            return false;

        if( empty( $append ) )
        {
            $this->my_flow['response_headers'] = array();
            $this->my_flow['raw_response_headers'] = array();
        }

        $lower_to_raw_arr = false;
        foreach( $headers_arr as $key => $val )
        {
            $lower_key = strtolower( trim( $key ) );
            if( $lower_key == '' )
                continue;

            // Check if there is already a header like this, but letters are lower or upper case different
            if( isset( $this->my_flow['response_headers'][$lower_key] ) )
            {
                if( empty( $lower_to_raw_arr ) )
                {
                    // create an index array with keys from lowercase to raw (if we have more cases like this)
                    $lower_to_raw_arr = array();
                    foreach( $this->my_flow['raw_response_headers'] as $rrh_key => $rrh_some_val )
                    {
                        if( !($rh_key = strtolower( trim( $rrh_key ) )) )
                            continue;

                        $lower_to_raw_arr[$rh_key] = $rrh_key;
                    }
                }

                // Take letters capitalization as in first header value
                if( !empty( $lower_to_raw_arr[$lower_key] ) )
                    $key = $lower_to_raw_arr[$lower_key];
            }

            $this->my_flow['raw_response_headers'][$key] = $val;

            $this->my_flow['response_headers'][$lower_key] = $val;

            $lower_to_raw_arr[$lower_key] = $key;
        }

        return $this->my_flow['raw_response_headers'];
    }

    public function response_body( $body_str = false )
    {
        if( $body_str === false )
            return $this->my_flow['response_body'];

        if( !is_string( $body_str ) )
            return false;

        $this->my_flow['response_body'] = $body_str;

        return true;
    }

    public function http_method()
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        return $this->my_flow['api_method'];
    }

    public function set_http_method( $method )
    {
        $this->reset_error();

        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        if( !is_string( $method ) )
        {
            $this->set_error( self::ERR_HTTP_METHOD, self::_t( 'Invalid HTTP method.' ) );
            return false;
        }

        $method = PHS_api::prepare_http_method( $method );
        if( empty( $method )
         or !in_array( $method, $this->allowed_http_methods() ) )
        {
            $this->set_error( self::ERR_HTTP_METHOD, self::_t( 'HTTP method %s not allowed.', $method ) );
            return false;
        }

        $this->my_flow['api_method'] = $method;

        return $this->my_flow['api_method'];
    }

    public function http_protocol()
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        return $this->my_flow['http_protocol'];
    }

    public function set_http_protocol( $protocol )
    {
        $this->reset_error();

        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        if( empty( $protocol )
         or !is_string( $protocol ) )
        {
            $this->set_error( self::ERR_HTTP_PROTOCOL, self::_t( 'Invalid HTTP protocol.' ) );
            return false;
        }

        $this->my_flow['http_protocol'] = strtoupper( trim( $protocol ) );

        return $this->my_flow['http_protocol'];
    }

    public function content_type()
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        return $this->my_flow['content_type'];
    }

    public function set_content_type( $type )
    {
        $this->reset_error();

        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        if( empty( $type )
         or !is_string( $type ) )
        {
            $this->set_error( self::ERR_HTTP_PROTOCOL, self::_t( 'Invalid content type.' ) );
            return false;
        }

        $this->my_flow['content_type'] = strtoupper( trim( $type ) );

        return $this->my_flow['content_type'];
    }

    public function api_user_account_id()
    {
        if( empty( $this->my_flow ) )
            $this->my_flow = $this->_default_api_flow();

        return $this->my_flow['api_key_user_id'];
    }

    public static function default_framework_api_settings()
    {
        return array(
            'allow_api_calls' => false,
            'allow_api_calls_over_http' => false,
            'api_can_simulate_web' => false,
        );
    }

    public static function get_framework_api_settings()
    {
        if( !empty( self::$_framework_settings ) )
            return self::$_framework_settings;

        self::$_framework_settings = self::default_framework_api_settings();

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        if( !($admin_plugin = PHS::load_plugin( 'admin' ))
         or !($admin_plugin_settings = $admin_plugin->get_plugin_settings()) )
            return self::$_framework_settings;

        self::$_framework_settings['allow_api_calls'] = (!empty( $admin_plugin_settings['allow_api_calls'] ));
        self::$_framework_settings['allow_api_calls_over_http'] = (!empty( $admin_plugin_settings['allow_api_calls_over_http'] ));
        self::$_framework_settings['api_can_simulate_web'] = (!empty( $admin_plugin_settings['api_can_simulate_web'] ));

        return self::$_framework_settings;
    }

    public static function framework_allows_api_calls()
    {
        if( !($settings = self::get_framework_api_settings())
         or !is_array( $settings ) )
            return false;

        return (!empty( $settings['allow_api_calls'] ));
    }

    public static function framework_allows_api_calls_over_http()
    {
        if( !($settings = self::get_framework_api_settings())
         or !is_array( $settings ) )
            return false;

        return (!empty( $settings['allow_api_calls_over_http'] ));
    }

    public static function framework_api_can_simulate_web()
    {
        if( !($settings = self::get_framework_api_settings())
         or !is_array( $settings ) )
            return false;

        return (!empty( $settings['api_can_simulate_web'] ));
    }

    public function send_header_response( $code, $msg = false )
    {
        return self::http_header_response( $code, $msg, $this->http_protocol() );
    }

    public static function get_php_input()
    {
        static $input = false;

        if( $input !== false )
            return $input;

        if( ($input = @file_get_contents( 'php://input' )) === false )
            return false;

        return $input;
    }

    public static function generic_error( $msg = false )
    {
        return self::http_header_response( self::GENERIC_ERROR_CODE, $msg );
    }

    public static function http_header_response( $code, $msg = false, $protocol = false )
    {
        if( @headers_sent() )
            return false;

        if( !($code = intval( $code )) )
            $code = self::GENERIC_OK_CODE;

        if( !is_string( $msg ) )
        {
            if( !($msg = self::valid_http_code( $code )) )
                $msg = '';
        }

        if( !is_string( $protocol ) )
            $protocol = 'HTTP/1.1';

        $msg = trim( $msg );

        @header( $protocol.' '.$code.' '.$msg );

        return true;
    }


    public static function valid_http_code( $code )
    {
        $code = intval( $code );
        if( !($all_codes = self::http_response_codes())
         or empty( $all_codes[$code] ) )
            return false;

        return $all_codes[$code];
    }

    public static function http_response_codes()
    {
        return array(

            0 => 'Host not found / Timed out',

            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing (WebDAV)',

            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status (WebDAV)',
            208 => 'Already Reported (WebDAV)',
            226 => 'IM Used',

            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect (experimental)',

            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot (RFC 2324)',
            420 => 'Enhance Your Calm (Twitter)',
            422 => 'Unprocessable Entity (WebDAV)',
            423 => 'Locked (WebDAV)',
            424 => 'Failed Dependency (WebDAV)',
            425 => 'Reserved for WebDAV',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            444 => 'No Response (Nginx)',
            449 => 'Retry With (Microsoft)',
            450 => 'Blocked by Windows Parental Controls (Microsoft)',
            499 => 'Client Closed Request (Nginx)',

            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates (Experimental)',
            507 => 'Insufficient Storage (WebDAV)',
            508 => 'Loop Detected (WebDAV)',
            509 => 'Bandwidth Limit Exceeded (Apache)',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            598 => 'Network read timeout error',
            599 => 'Network connect timeout error',
        );
    }
}

