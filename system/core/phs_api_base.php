<?php

namespace phs;

use \phs\PHS_Api;
use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;

//! @version 1.00

abstract class PHS_Api_base extends PHS_Registry
{
    const ERR_RUN_ROUTE = 30001, ERR_AUTHENTICATION = 30002, ERR_HTTP_METHOD = 30003, ERR_HTTP_PROTOCOL = 30004, ERR_APIKEY = 30005;

    const DEFAULT_VERSION = 1;

    const GENERIC_ERROR_CODE = 500, GENERIC_OK_CODE = 200;

    // Most used HTTP error codes
    const H_CODE_OK = 200, H_CODE_OK_CREATED = 201, H_CODE_OK_ACCEPTED = 202, H_CODE_OK_NO_CONTENT = 204,
          H_CODE_BAD_REQUEST = 400, H_CODE_UNAUTHORIZED = 401, H_CODE_FORBIDDEN = 403, H_CODE_NOT_FOUND = 404, H_CODE_METHOD_NOT_ALLOWED = 405,
          H_CODE_INTERNAL_SERVER_ERROR = 500, H_CODE_NOT_IMPLEMENTED = 501, H_CODE_BAD_GATEWAY = 502, H_CODE_SERVICE_UNAVAILABLE = 503, H_CODE_GATEWAY_TIMEOUT = 504,
          H_CODE_INSUFFICIENT_STORAGE = 507;

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
    protected $allowed_http_methods = [ 'get', 'post', 'delete', 'patch' ];

    /** @var bool|array $my_flow Instance API flow  */
    protected $my_flow = false;

    /** @var bool|array $_framework_settings API settings set in admin plugin settings */
    protected static $_framework_settings = [];

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

    /**
     * Override this method in case you want to envelope each response in a "standard" response structure
     *
     * @param array|null $response_arr Response which should be enveloped
     * @param array|bool $errors_arr Any errors that should be added in envelope in case we don't have access to PHS_Notifications class
     *
     * @return bool|array Return response envelope array or false on error
     */
    public function create_response_envelope( $response_arr, $errors_arr = false )
    {
        return $response_arr;
    }

    protected function _default_api_flow()
    {
        return [
            'die_when_needed' => true,

            'response_headers' => [],
            'raw_response_headers' => [],
            'response_body' => '',
            'response_array' => [],

            'http_protocol' => 'HTTP/1.1',
            'api_method' => 'get',
            'content_type' => 'application/json',

            'original_api_route_tokens' => false,
            'final_api_route_tokens' => false,
            'phs_route' => false,
            'api_route' => false,

            // Values used in HTTP Authorization header (not necessary an user and password in the system)
            'api_user' => '',
            'api_pass' => '',

            // Any information related to API Key used in the request (any API implementation will use this as required)
            'api_key_data' => false,
            // In case API key wants to consider this request authenticated as a specific user (from users table), put users.id value here...
            'api_key_user_id' => 0,

            // User under which API actions are taken
            'api_account_data' => false,
        ];
    }

    private function _special_flow_keys()
    {
        return [ 'api_method', 'http_protocol', 'content_type', 'response_headers', 'raw_response_headers' ];
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
                 && array_key_exists( $key, $this->my_flow ) )
                    return $this->my_flow[$key];

                return null;
            }

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey )
                 || !array_key_exists( $kkey, $this->my_flow )
                 || in_array( $kkey, $this->_special_flow_keys(), true ) )
                    continue;

                $this->my_flow[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key )
         || !array_key_exists( $key, $this->my_flow )
         || in_array( $key, $this->_special_flow_keys(), true ) )
            return null;

        $this->my_flow[$key] = $val;

        return true;
    }

    /**
     * @param bool|array $methods_arr
     *
     * @return array|bool
     */
    public function allowed_http_methods( $methods_arr = false )
    {
        if( $methods_arr === false )
            return $this->allowed_http_methods;

        if( is_array( $methods_arr ) )
        {
            if( !($new_methods = self::extract_strings_from_array( $methods_arr, [ 'to_lowercase' => true ] )) )
                return false;

            $this->allowed_http_methods = $new_methods;

            return $new_methods;
        }

        return false;
    }

    /**
     * @param bool|array $args Arguments which must be added in query string (other than predefined ones)
     * @param bool|array $extra Call parameters
     *
     * @return array Arguments to be added to query string of API URL
     */
    protected function _get_predefined_api_url_params( $args = false, $extra = false )
    {
        if( empty( $args ) || !is_array( $args ) )
            $args = [];

        if( empty( $extra ) || !is_array( $extra ) )
            $extra = [];

        if( !isset( $extra['include_version'] ) )
            $extra['include_version'] = true;
        else
            $extra['include_version'] = (!empty( $extra['include_version'] ));

        if( !empty( $this->raw_query_params ) && is_array( $this->raw_query_params ) )
        {
            foreach( $this->raw_query_params as $key => $val )
            {
                // rewrite parameter is set in rewrite rule...
                // put in parameters parsed value
                if( $key === self::PARAM_USING_REWRITE
                 || $key === self::PARAM_API_ROUTE
                 || !isset( $this->init_query_params[$key] )
                 || ($key === self::PARAM_VERSION && empty( $extra['include_version'] )) )
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

        if( empty( $init_params ) || !is_array( $init_params ) )
            $init_params = [];

        $this->raw_query_params = $init_params;
        $this->init_query_params = $this->default_query_params();

        if( !empty( $this->raw_query_params[self::PARAM_VERSION] ) )
            $this->init_query_params[self::PARAM_VERSION] = floatval( $this->raw_query_params[self::PARAM_VERSION] );

        else
            $this->init_query_params[self::PARAM_VERSION] = self::DEFAULT_VERSION;

        $this->init_query_params[self::PARAM_USING_REWRITE] = (!empty( $this->raw_query_params[self::PARAM_USING_REWRITE] ));

        if( !PHS_Api::framework_api_can_simulate_web() )
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

    /**
     * @param bool|array $credentials_arr
     */
    public function set_api_credentials( $credentials_arr = false )
    {
        $new_credentials_arr = [
            'api_user' => '',
            'api_pass' => '',
        ];

        if( !empty( $credentials_arr ) && is_array( $credentials_arr ) )
        {
            $new_credentials_arr['api_user'] = (isset( $credentials_arr['api_user'] )?$credentials_arr['api_user']:'');
            $new_credentials_arr['api_pass'] = (isset( $credentials_arr['api_pass'] )?$credentials_arr['api_pass']:'');
        } else
        {
            if( empty( $_SERVER['PHP_AUTH_USER'] ) && empty( $_SERVER['PHP_AUTH_PW'] )
             && !empty( $_SERVER['HTTP_AUTHORIZATION'] )
             && stripos( $_SERVER['HTTP_AUTHORIZATION'], 'basic' ) === 0
             && ($auth_arr = explode(':', @base64_decode( trim( substr( $_SERVER['HTTP_AUTHORIZATION'], 6 ) ) ) ))
             && count( $auth_arr ) === 2 )
            {
                $_SERVER['PHP_AUTH_USER'] = $auth_arr[0];
                $_SERVER['PHP_AUTH_PW'] = $auth_arr[1];
            }

            if( empty( $_SERVER['PHP_AUTH_USER'] ) && empty( $_SERVER['PHP_AUTH_PW'] )
             && !empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
             && stripos( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 'basic' ) === 0
             && ($auth_arr = explode(':', @base64_decode( trim( substr( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 6 ) ) ) ))
             && count( $auth_arr ) === 2 )
            {
                $_SERVER['PHP_AUTH_USER'] = $auth_arr[0];
                $_SERVER['PHP_AUTH_PW'] = $auth_arr[1];
            }

            if( isset( $_SERVER['PHP_AUTH_USER'] ) )
                $new_credentials_arr['api_user'] = $_SERVER['PHP_AUTH_USER'];

            if( isset( $_SERVER['PHP_AUTH_PW'] ) )
                $new_credentials_arr['api_pass'] = $_SERVER['PHP_AUTH_PW'];
        }

        $this->api_flow_value( $new_credentials_arr );
    }

    public function get_api_credentials()
    {
        return [
            'api_user' => $this->api_flow_value( 'api_user' ),
            'api_pass' => $this->api_flow_value( 'api_pass' ),
        ];
    }

    /**
     * @param false|array $credentials_arr
     *
     * @return array|bool
     */
    public function api_authentication( $credentials_arr = false )
    {
        $this->reset_error();

        $this->set_api_credentials( $credentials_arr );

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
        if( empty( $route_arr ) || !is_array( $route_arr ) )
            $route_arr = [];

        if( empty( $args ) || !is_array( $args ) )
            $args = [];

        if( empty( $extra ) || !is_array( $extra ) )
            $extra = [];

        $api_url_params = [];
        $api_url_params['include_version'] = (empty( $this->init_query_params[self::PARAM_USING_REWRITE] ));

        if( !($args = $this->_get_predefined_api_url_params( $args ))
         || !is_array( $args ) )
            $args = [];

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

            if( !empty( $extra['raw_params'] ) && is_array( $extra['raw_params'] ) )
            {
                // Parameters that shouldn't be run through http_build_query as values will be rawurlencoded and we might add javascript code in parameters
                // eg. $extra['raw_params'] might be an id passed as javascript function parameter
                if( ($raw_query = array_to_query_string( $extra['raw_params'], [ 'raw_encode_values' => false ] )) )
                    $query_string .= ($query_string!==''?'&':'').$raw_query;
            }

            return PHS::get_base_url( $route_arr['force_https'] ).'api/v'.$this->get_api_version().'/'.$route.($query_string!==''?'?'.$query_string:'');
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

        if( empty( $extra ) || !is_array( $extra ) )
            $extra = [];

        if( ($final_api_route_tokens = PHS_Api::tokenize_api_route( $this->get_api_route() )) === false )
        {
            $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Couldn\'t parse provided API route.' ) );
            return false;
        }

        if( PHS::st_debugging_mode() )
            PHS_Logger::logf( 'Request API route tokens ['.implode( '/', $final_api_route_tokens ).']', PHS_Logger::TYPE_API );

        $this->api_flow_value( 'original_api_route_tokens', $final_api_route_tokens );

        // Let plugins change provided API route tokens
        $hook_args = PHS_Hooks::default_api_hook_args();
        $hook_args['api_obj'] = $this;
        $hook_args['api_route_tokens'] = $final_api_route_tokens;

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_API_ROUTE, $hook_args ))
         && is_array( $hook_args )
         && !empty( $hook_args['altered_api_route_tokens'] ) && is_array( $hook_args['altered_api_route_tokens'] ) )
        {
            if( !($final_api_route_tokens = PHS_Api::validate_tokenized_api_route( $hook_args['altered_api_route_tokens'] )) )
            {
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Invalid API route tokens obtained from plugins.' ) );
                return false;
            }
        }

        $this->api_flow_value( 'final_api_route_tokens', $final_api_route_tokens );

        if( PHS::st_debugging_mode() )
            PHS_Logger::logf( 'Final API route tokens ['.implode( '/', $final_api_route_tokens ).']', PHS_Logger::TYPE_API );

        $phs_route = false;
        $api_route = false;
        if( ($matched_route = PHS_Api::get_phs_route_from_api_route( $final_api_route_tokens, $this->http_method() )) )
        {
            $phs_route = $matched_route['phs_route'];
            $api_route = $matched_route['api_route'];
        } else
        {
            if( PHS::st_debugging_mode() )
                PHS_Logger::logf( 'No defined API route matched request.', PHS_Logger::TYPE_API );

            if( !($phs_route = PHS::parse_route( implode( '/', $final_api_route_tokens ), true )) )
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
        $this->api_flow_value( 'api_route', $api_route );

        PHS::set_route( $phs_route );

        // Check if we should have authentication...
        // If we didn't find an API route, we found a "standard" route to be run which requires authentication
        // If we have a matching API route check if API route requires authentication, custom authentication or no authentication at all
        if( empty( $api_route ) || !is_array( $api_route ) )
        {
            if( !$this->_check_api_authentication() )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Authentication failed.' ) );

                return false;
            }
        } elseif( !empty( $api_route['authentication_required'] ) )
        {
            if( empty( $api_route['authentication_callback'] ) )
            {
                if( !$this->_check_api_authentication() )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Authentication failed.' ) );

                    return false;
                }
            } else
            {
                if( !@is_callable( $api_route['authentication_callback'] ) )
                {
                    if( !($route_str = PHS::route_from_parts( $phs_route )) )
                        $route_str = 'N/A';

                    PHS_Logger::logf( 'API authentication callback failed for route ['.(!empty( $api_route['name'] )?$api_route['name']:'N/A').'] - '.$route_str, PHS_Logger::TYPE_API );

                    $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Authentication failed.' ) );
                    return false;
                }

                $callback_params = self::default_api_authentication_callback_params();
                $callback_params['api_obj'] = $this;
                $callback_params['api_route'] = $api_route;
                $callback_params['phs_route'] = $phs_route;

                if( ($result = @call_user_func( $api_route['authentication_callback'], $callback_params )) === null
                 || $result === false )
                {
                    if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED ) )
                    {
                        $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Not authorized.' ) );
                        return false;
                    }

                    exit;
                }
            }
        } else
        {
            if( PHS::st_debugging_mode() )
                PHS_Logger::logf( 'Authentication not required!', PHS_Logger::TYPE_API );
        }

        if( !$this->_before_route_run() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Running action was stopped by API instance.' ) );

            return false;
        }

        $execution_params = [];
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
         || !($apikeys_model = PHS::load_model( 'api_keys' ))
         || !($apikey_arr = $apikeys_model->get_details_fields( [ 'api_key' => $apikey ] )) )
        {
            $this->set_error( self::ERR_APIKEY, $this->_pt( 'Api key not found in database.' ) );
            return false;
        }

        $this->api_flow_value( 'api_key_data', $apikey_arr );

        if( !empty( $apikey_arr['uid'] ) )
            $this->api_flow_value( 'api_key_user_id', (int)$apikey_arr['uid'] );
        else
            $this->api_flow_value( 'api_key_user_id', 0 );

        return $apikey_arr;
    }

    public function default_query_params()
    {
        return [
            self::PARAM_VERSION => self::DEFAULT_VERSION,
            self::PARAM_API_ROUTE => '',
            self::PARAM_USING_REWRITE => false,
            self::PARAM_WEB_SIMULATION => false,
        ];
    }

    public function get_api_version()
    {
        if( empty( $this->init_query_params ) || !is_array( $this->init_query_params ) )
            return false;

        return (empty( $this->init_query_params[self::PARAM_VERSION] )?self::DEFAULT_VERSION:$this->init_query_params[self::PARAM_VERSION]);
    }

    public function get_api_route()
    {
        if( empty( $this->init_query_params ) || !is_array( $this->init_query_params )
         || empty( $this->init_query_params[self::PARAM_API_ROUTE] ) )
            return '';

        return $this->init_query_params[self::PARAM_API_ROUTE];
    }

    public function is_rewrite_request()
    {
        if( empty( $this->init_query_params ) || !is_array( $this->init_query_params )
         || empty( $this->init_query_params[self::PARAM_USING_REWRITE] ) )
            return false;

        return true;
    }

    public function is_web_simulation()
    {
        if( empty( $this->init_query_params ) || !is_array( $this->init_query_params )
         || empty( $this->init_query_params[self::PARAM_WEB_SIMULATION] ) )
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
            $this->my_flow['response_headers'] = [];
            $this->my_flow['raw_response_headers'] = [];
        }

        $lower_to_raw_arr = false;
        foreach( $headers_arr as $key => $val )
        {
            $lower_key = strtolower( trim( $key ) );
            if( $lower_key === '' )
                continue;

            // Check if there is already a header like this, but letters are lower or upper case different
            if( isset( $this->my_flow['response_headers'][$lower_key] ) )
            {
                if( empty( $lower_to_raw_arr ) )
                {
                    // create an index array with keys from lowercase to raw (if we have more cases like this)
                    $lower_to_raw_arr = [];
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

    /**
     * @param false|string $body_str
     *
     * @return bool|string
     */
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

        $method = PHS_Api::prepare_http_method( $method );
        if( empty( $method )
         || !in_array( $method, $this->allowed_http_methods() ) )
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
         || !is_string( $protocol ) )
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
         || !is_string( $type ) )
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

    public static function default_api_authentication_callback_params()
    {
        return [
            'api_obj' => false,
            'api_route' => false,
            'phs_route' => false,
        ];
    }

    public static function default_api_authentication_callback_response()
    {
        return [
            'api_obj' => false,
            'api_route' => false,
            'phs_route' => false,
        ];
    }

    public static function default_framework_api_settings()
    {
        return [
            'allow_api_calls' => false,
            'allow_api_calls_over_http' => false,
            'api_can_simulate_web' => false,
        ];
    }

    public static function get_framework_api_settings()
    {
        if( !empty( self::$_framework_settings ) )
            return self::$_framework_settings;

        self::$_framework_settings = self::default_framework_api_settings();

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        if( !($admin_plugin = PHS::load_plugin( 'admin' ))
         || !($admin_plugin_settings = $admin_plugin->get_plugin_settings()) )
            return self::$_framework_settings;

        self::$_framework_settings['allow_api_calls'] = (!empty( $admin_plugin_settings['allow_api_calls'] ));
        self::$_framework_settings['allow_api_calls_over_http'] = (!empty( $admin_plugin_settings['allow_api_calls_over_http'] ));
        self::$_framework_settings['api_can_simulate_web'] = (!empty( $admin_plugin_settings['api_can_simulate_web'] ));

        return self::$_framework_settings;
    }

    public static function framework_allows_api_calls()
    {
        if( !($settings = self::get_framework_api_settings())
         || !is_array( $settings ) )
            return false;

        return (!empty( $settings['allow_api_calls'] ));
    }

    public static function framework_allows_api_calls_over_http()
    {
        if( !($settings = self::get_framework_api_settings())
         || !is_array( $settings ) )
            return false;

        return (!empty( $settings['allow_api_calls_over_http'] ));
    }

    public static function framework_api_can_simulate_web()
    {
        if( !($settings = self::get_framework_api_settings())
         || !is_array( $settings ) )
            return false;

        return (!empty( $settings['api_can_simulate_web'] ));
    }

    public function send_header_response( $code, $msg = false )
    {
        return self::http_header_response( $code, $msg, $this->http_protocol() );
    }

    public static function get_request_body_as_json_array()
    {
        static $json_arr = false;

        if( $json_arr !== false )
            return $json_arr;

        if( !($request_body = PHS_Api::get_php_input())
         || !($json_arr = @json_decode( $request_body, true )) )
            return [];

        return $json_arr;
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

    /**
     * @param int $code
     * @param bool|string $msg
     * @param bool|string $protocol
     *
     * @return bool
     */
    public static function http_header_response( $code, $msg = false, $protocol = false )
    {
        if( @headers_sent() )
            return false;

        if( !($code = (int)$code) )
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
        $code = (int)$code;
        if( !($all_codes = self::http_response_codes())
         || empty( $all_codes[$code] ) )
            return false;

        return $all_codes[$code];
    }

    public static function http_response_codes()
    {
        return [

            0 => 'Host not found / Timed out',

            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing (WebDAV)',
            103 => 'Checkpoint',

            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status (WebDAV)',
            208 => 'Already Reported (WebDAV)',
            218 => 'This is fine',
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
            419 => 'Page Expired (Laravel Framework)',
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
        ];
    }
}

