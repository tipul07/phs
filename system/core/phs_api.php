<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Hooks;

//! @version 1.00

class PHS_Api extends PHS_Api_base
{
    const ERR_API_INIT = 40000, ERR_API_ROUTE = 40001;

    /** @var array $_api_routes */
    private static $_api_routes = array();

    // Last API instance obtained with self::api_factory()
    /** @var bool|\phs\PHS_Api_base $_last_api_obj */
    private static $_last_api_obj = false;

    // THE API instance that should respond to current request
    /** @var bool|\phs\PHS_Api_base $_global_api_obj */
    private static $_global_api_obj = false;

    final public static function api_factory( $init_query_params = false )
    {
        self::st_reset_error();

        $api_obj = false;

        // Tell plugins we are starting an API request and check if any of them has an API object to offer
        $hook_args = PHS_Hooks::default_api_hook_args();
        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_API_REQUEST_INIT, $hook_args ))
        and is_array( $hook_args )
        and !empty( $hook_args['api_obj'] )
        and ($api_obj = $hook_args['api_obj']) )
        {
            if( !($api_obj instanceof PHS_Api_base) )
            {
                self::st_set_error( self::ERR_API_INIT, self::_t( 'Invalid API instance obtained from hook call.' ) );
                return false;
            }
        }

        // If we don't have an instance provided by hook result, instantiate default API class
        if( empty( $api_obj )
        and !($api_obj = new PHS_Api()) )
        {
            self::st_set_error( self::ERR_API_INIT, self::_t( 'Error obtaining API instance.' ) );
            return false;
        }

        if( !($api_obj->_init_api_query_params( $init_query_params )) )
        {
            if( $api_obj->has_error() )
                self::st_copy_error( $api_obj );
            else
                self::st_set_error( self::ERR_API_INIT, self::_t( 'Couldn\'t initialize API object.' ) );

            return false;
        }

        self::$_last_api_obj = $api_obj;
        if( empty( self::$_global_api_obj ) )
            self::$_global_api_obj = $api_obj;

        return $api_obj;
    }

    public static function get_api_routes()
    {
        return self::$_api_routes;
    }

    public static function prepare_http_method( $method )
    {
        if( !is_string( $method ) )
            return false;

        return strtolower( trim( $method ) );
    }

    public static function tokenize_api_route( $route_str )
    {
        if( !is_string( $route_str ) )
            return false;

        $route_parts = explode( '/', trim( trim( $route_str ), '/' ) );
        $route_tokens = array();
        foreach( $route_parts as $part )
        {
            // Allow empty API paths (empty string)
            $part = trim( $part );
            if( !empty( $route_tokens )
            and $part === '' )
                continue;

            $route_tokens[] = $part;
        }

        return $route_tokens;
    }

    public static function validate_tokenized_api_route( $route_arr )
    {
        if( !is_array( $route_arr ) )
            return false;

        $validated_route = array();
        foreach( $route_arr as $part )
        {
            if( !is_string( $part ) )
                return false;

            $validated_route[] = trim( trim( $part ), '/' );
        }

        return $validated_route;
    }

    public static function default_api_route_node()
    {
        return array(
            'exact_match' => '', // spare a regexp check if we want something static
            'regexp' => '',
            'regexp_modifiers' => '', // provide
            'insensitive_match' => true, // case insensitive match on exact_match or regexp

            // in case this node is dynamic we should check if it's value respects the type, see if we should consider this
            // as parameter in action and where to move it (if required): in get or post
            'type' => PHS_Params::T_ASIS,
            'extra_type' => false,
            'default' => null,
            'var_name' => '', // in case we should move this from route to _GET or _POST, how should we call this variable
            'append_to_get' => false,
            'append_to_post' => false,

            // for documentation / errors
            'name' => '',
            'description' => '',
        );
    }

    public static function default_api_route_structure()
    {
        $route_structure = self::default_api_route_params();
        $route_structure['api_route'] = array();
        $route_structure['phs_route'] = array();

        return $route_structure;
    }

    public static function default_api_route_params()
    {
        return array(
            'method' => 'get',
            // these are useful when creating aliases for common requests
            // eg. /companies/get_latest_20 will change to list companies action with sort descending on creation date, offset 0 and limit 20
            // this means you will add filtering and sorting in get_params or post_params as required
            'get_params' => array(),
            'post_params' => array(),

            // If API route doesn't require authentication to run put this to false
            'authentication_required' => true,
            // If API route requires special API authentication you can define here what method/function to call to do the authentication
            // Method receives as parameters an array (like PHS_Api_base::default_api_authentication_callback_params()) and should return false
            // in case authentication failed or it can safetly send headers back to browser and exit directly
            // !!! If authetication passes it MUST return true
            'authentication_callback' => false,

            // for documentation / errors
            'name' => '',
            'description' => '',
        );
    }

    /**
     * @param array $api_route Defined API route to be checked against route from request ($tokenized_request_route)
     * @param array $tokenized_request_route a tokenized API route from request
     * @param string $method Requested HTTP method
     * @param bool $skip_validations true if validation of parameters should be skipped (if already validated)
     *
     * @return bool|array Return false if provided $api_route doesn't march $tokenized_request_route
     */
    public static function check_route_for_tokenized_api_route( $api_route, $tokenized_request_route, $method = 'get', $skip_validations = false )
    {
        if( empty( $skip_validations )
        and (!($api_route = self::normalize_api_route( $api_route ))
                or !($tokenized_request_route = self::validate_tokenized_api_route( $tokenized_request_route ))
            ) )
            return false;

        // First check if we have a good method...
        if( !($method = self::prepare_http_method( $method ))
         or empty( $api_route['method'] )
         or $api_route['method'] != $method )
            return false;

        $api_route_tokens_count = (empty( $api_route['api_route'] )?0:count( $api_route['api_route'] ));
        $request_route_tokens_count = count( $tokenized_request_route );

        if( $api_route_tokens_count !== $request_route_tokens_count )
            return false;

        $knti = 0;
        $append_to_get = array();
        $append_to_post = array();
        while( $knti < $api_route_tokens_count )
        {
            $api_element = $api_route['api_route'][$knti];
            $request_token = $tokenized_request_route[$knti];

            $knti++;

            if( $api_element['exact_match'] === ''
            and empty( $api_element['regexp'] ) )
                return false;

            if( $api_element['exact_match'] !== '' )
            {
                if( !empty( $api_element['insensitive_match'] ) )
                {
                    $exact_match = strtolower( $api_element['exact_match'] );
                    $check_token = strtolower( $request_token );
                } else
                {
                    $exact_match = $api_element['exact_match'];
                    $check_token = $request_token;
                }

                if( $exact_match !== $check_token )
                    return false;
            } elseif( !empty( $api_element['regexp'] ) )
            {
                $modifiers = '';
                if( !empty( $api_element['regexp_modifiers'] ) )
                    $modifiers .= trim( $api_element['regexp_modifiers'] );
                if( !empty( $api_element['insensitive_match'] ) )
                    $modifiers .= 'i';

                if( !@preg_match( '/'.$api_element['regexp'].'/'.$modifiers, $request_token ) )
                    return false;
            }

            if( !empty( $api_element['append_to_get'] )
             or !empty( $api_element['move_in_post'] ) )
            {
                if( empty( $api_element['var_name'] ) )
                    return false;

                if( null === ($el_value = PHS_Params::set_type( $request_token, $api_element['type'], $api_element['extra_type'] )) )
                    $el_value = $api_element['default'];

                if( !empty( $api_element['append_to_get'] ) )
                    $append_to_get[$api_element['var_name']] = $el_value;

                elseif( !empty( $api_element['append_to_post'] ) )
                    $append_to_post[$api_element['var_name']] = $el_value;
            }
        }

        // safe
        if( empty( $api_route['phs_route'] ) )
            return false;

        if( !empty( $append_to_get ) )
        {
            if( empty( $_GET ) or !is_array( $_GET ) )
                $_GET = array();

            foreach( $append_to_get as $key => $val )
                $_GET[$key] = $val;
        }

        if( !empty( $append_to_post ) )
        {
            if( empty( $_POST ) or !is_array( $_POST ) )
                $_POST = array();

            foreach( $append_to_post as $key => $val )
                $_POST[$key] = $val;
        }

        return $api_route['phs_route'];
    }

    /**
     * @param array $tokenized_api_route A tokenized API route
     * @param string $method Method used in request (eg. get, post, delete, etc)
     *
     * @return bool|array
     */
    public static function get_phs_route_from_api_route( $tokenized_api_route, $method = 'get' )
    {
        self::st_reset_error();

        if( empty( self::$_api_routes ) )
            return false;

        if( !($method = self::prepare_http_method( $method )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Please provide a valid API method.' ) );
            return false;
        }

        if( !($tokenized_api_route = self::validate_tokenized_api_route( $tokenized_api_route )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Provided API route failed validation.' ) );
            return false;
        }

        foreach( self::$_api_routes as $api_route )
        {
            if( ($phs_route = self::check_route_for_tokenized_api_route( $api_route, $tokenized_api_route, $method, true )) )
            {
                return array(
                    'phs_route' => $phs_route,
                    'api_route' => $api_route,
                );
            }
        }

        return false;
    }

    public static function normalize_api_route_api_nodes( $api_route_nodes )
    {
        if( empty( $api_route_nodes ) or !is_array( $api_route_nodes ) )
            return array();

        $new_api_route_nodes = array();
        $default_node = self::default_api_route_node();
        foreach( $api_route_nodes as $route_node )
        {
            $new_api_route_nodes[] = self::validate_array( $route_node, $default_node );
        }

        return $new_api_route_nodes;
    }

    public static function normalize_api_route( $api_route )
    {
        $default_api_route_structure = self::default_api_route_structure();
        if( empty( $api_route ) or !is_array( $api_route ) )
            return $default_api_route_structure;

        $api_route = self::validate_array( $api_route, $default_api_route_structure );
        $api_route['api_route'] = self::normalize_api_route_api_nodes( $api_route['api_route'] );
        $api_route['phs_route'] = PHS::validate_route_from_parts( $api_route['phs_route'], true );

        return $api_route;
    }

    /**
     * @param array $api_route_parts An array of tokens to be matched agains an API route (exploding route on /)
     * @param array $phs_route A PHS route using short names for plugin, controller and action (p, c and a)
     * @param bool|array $route_params Route parameters (@see self::default_api_route_params())
     *
     * @return bool true on success or false on error
     */
    public static function register_api_route( $api_route_parts, $phs_route, $route_params = false )
    {
        self::st_reset_error();

        $route_params = self::validate_array( $route_params, self::default_api_route_params() );

        if( !($method = self::prepare_http_method( $route_params['method'] )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Please provide a valid API method.' ) );
            return false;
        }

        $route_params['method'] = $method;

        if( empty( $phs_route )
         or !($phs_route = PHS::parse_route( $phs_route, true )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Couldn\'t parse provided PHS route for API calls.' ) );
            return false;
        }

        $api_route = self::merge_array_assoc( self::default_api_route_structure(), $route_params );
        $api_route['api_route'] = $api_route_parts;
        $api_route['phs_route'] = $phs_route;

        $api_route = self::normalize_api_route( $api_route );

        self::$_api_routes[] = $api_route;

        return true;
    }

    /**
     * @param null|PHS_Api_base $api_obj API instance to be set as request API instance
     *
     * @return bool|PHS_Api_base Return request API instance or false if none set
     */
    public static function global_api_instance( $api_obj = null )
    {
        self::st_reset_error();

        if( $api_obj === null )
            return self::$_global_api_obj;

        if( empty( $api_obj ) )
        {
            self::$_global_api_obj = false;
            return true;
        }

        if( !is_object( $api_obj )
         or !($api_obj instanceof PHS_Api_base) )
        {
            self::st_set_error( self::ERR_API_INIT, self::_t( 'Invalid API instance.' ) );
            return false;
        }

        self::$_global_api_obj = $api_obj;

        return true;
    }

    protected function _before_route_run()
    {
        if( $this->is_web_simulation() )
        {
            PHS_Scope::emulated_scope( PHS_Scope::SCOPE_WEB );

            if( ($request_body = $this::get_php_input())
            and ($json_arr = @json_decode( $request_body, true )) )
            {
                // In case we run in an environment where $_POST is not defined
                global $_POST;

                if( empty( $_POST ) or !is_array( $_POST ) )
                    $_POST = array();

                foreach( $json_arr as $key => $val )
                {
                    $_POST[$key] = $val;
                }
            }
        }

        return true;
    }

    protected function _after_route_run()
    {
        if( $this->is_web_simulation() )
            PHS_Scope::emulated_scope( false );

        return true;
    }

    protected function _check_api_authentication()
    {
        if( !($api_user = $this->api_flow_value( 'api_user' ))
         or null === ($api_pass = $this->api_flow_value( 'api_pass' )) )
        {
            if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED, 'Please provide credentials' ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Please provide credentials' ) );
                return false;
            }

            exit;
        }

        if( !($apikey_arr = $this->get_apikey_by_apikey( $api_user ))
         or (string)$apikey_arr['api_secret'] !== (string)$api_pass )
        {
            if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Not authorized.' ) );
                return false;
            }

            exit;
        }

        if( $this->is_web_simulation()
        and empty( $apikey_arr['allow_sw'] ) )
        {
            if( !$this->send_header_response( self::H_CODE_FORBIDDEN ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Request not allowed.' ) );
                return false;
            }

            PHS_Logger::logf( 'Web simulation not allowed (#'.$apikey_arr['id'].').', PHS_Logger::TYPE_API );

            exit;
        }

        $http_method = $this->http_method();

        if( !empty( $apikey_arr['allowed_methods'] )
        and !in_array( $http_method, self::extract_strings_from_comma_separated( $apikey_arr['allowed_methods'], array( 'to_lowercase' => true ) ), true ) )
        {
            if( !$this->send_header_response( self::H_CODE_METHOD_NOT_ALLOWED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Method not allowed.' ) );
                return false;
            }

            PHS_Logger::logf( 'Method not allowed (#'.$apikey_arr['id'].', '.$http_method.').', PHS_Logger::TYPE_API );

            exit;
        }

        if( !empty( $apikey_arr['denied_methods'] )
        and in_array( $http_method, self::extract_strings_from_comma_separated( $apikey_arr['denied_methods'], array( 'to_lowercase' => true ) ), true ) )
        {
            if( !$this->send_header_response( self::H_CODE_METHOD_NOT_ALLOWED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Method not allowed.' ) );
                return false;
            }

            PHS_Logger::logf( 'Method denied (#'.$apikey_arr['id'].', '.$http_method.').', PHS_Logger::TYPE_API );

            exit;
        }

        $request_ip = request_ip();
        if( !empty( $apikey_arr['allowed_ips'] )
        and !in_array( $request_ip, self::extract_strings_from_comma_separated( $apikey_arr['allowed_ips'], array( 'to_lowercase' => true ) ), true ) )
        {
            if( !$this->send_header_response( self::H_CODE_FORBIDDEN ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'IP not allowed.' ) );
                return false;
            }

            PHS_Logger::logf( 'IP denied (#'.$apikey_arr['id'].', '.$request_ip.').', PHS_Logger::TYPE_API );

            exit;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function create_response_envelope( $response_arr, $errors_arr = false )
    {
        if( !is_array( $response_arr ) )
            $response_arr = array();

        if( !array_key_exists( 'response_status', $response_arr )
         or is_array( $response_arr['response_status'] ) )
        {
            if( @class_exists( '\\phs\\libraries\\PHS_Notifications', false ) )
                $status_data = array(
                    'success_messages' => PHS_Notifications::notifications_success(),
                    'warning_messages' => PHS_Notifications::notifications_warnings(),
                    'error_messages' => PHS_Notifications::notifications_errors(),
                );
            else
            {
                if( empty( $errors_arr ) or !is_array( $errors_arr ) )
                    $errors_arr = array();

                $status_data = array(
                    'success_messages' => array(),
                    'warning_messages' => array(),
                    'error_messages' => $errors_arr,
                );
            }

            if( empty( $response_arr['response_status'] ) )
                $response_arr['response_status'] = array();

            $response_arr['response_status'] = self::validate_array( $response_arr['response_status'], $status_data );
        }

        // Check if we should remove response_status key from response
        if( array_key_exists( 'response_status', $response_arr )
        and $response_arr['response_status'] === null )
            unset( $response_arr['response_status'] );

        return $response_arr;
    }
}

