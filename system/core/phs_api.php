<?php

namespace phs;

use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Hooks;

//! @version 1.00

class PHS_api extends PHS_api_base
{
    const ERR_API_INIT = 40000, ERR_API_ROUTE = 40001;

    /** @var array $_route_aliases */
    private static $_route_aliases = array();

    // Last API instance obtained with self::api_factory()
    /** @var bool|\phs\PHS_api_base $_last_api_obj */
    private static $_last_api_obj = false;

    // THE API instance that should respond to current request
    /** @var bool|\phs\PHS_api_base $_global_api_obj */
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
            if( !($api_obj instanceof PHS_api_base) )
            {
                self::st_set_error( self::ERR_API_INIT, self::_t( 'Invalid API instance obtained from hook call.' ) );
                return false;
            }
        }

        // If we don't have an instance provided by hook result, instantiate default API class
        if( empty( $api_obj )
        and !($api_obj = new PHS_api()) )
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
        return self::$_route_aliases;
    }

    public static function prepare_http_method( $method )
    {
        if( !is_string( $method ) )
            return false;

        return strtolower( trim( $method ) );
    }

    public static function get_phs_route_from_api_route( $api_route, $method = 'get' )
    {
        self::st_reset_error();

        if( !($method = self::prepare_http_method( $method )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Please provide a valid API method.' ) );
            return false;
        }

        if( empty( $api_route )
         or !($api_route = PHS::parse_route( $api_route, true )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Couldn\'t parse provided API route.' ) );
            return false;
        }

        if( empty( self::$_route_aliases[$api_route['p']] )
         or empty( self::$_route_aliases[$api_route['p']][$api_route['c']] )
         or empty( self::$_route_aliases[$api_route['p']][$api_route['c']][$api_route['a']] )
         or empty( self::$_route_aliases[$api_route['p']][$api_route['c']][$api_route['a']][$method] ) )
            return false;

        return self::$_route_aliases[$api_route['p']][$api_route['c']][$api_route['a']][$method];
    }

    public static function api_parts_to_route( $api_parts )
    {
        if( empty( $api_parts ) or !is_array( $api_parts ) )
            $api_parts = array();

        if( empty( $api_parts['p1'] ) )
            $api_parts['p1'] = false;
        if( empty( $api_parts['p2'] ) )
            $api_parts['p2'] = false;
        if( empty( $api_parts['p3'] ) )
            $api_parts['p3'] = false;

        /**
         *
         * array(4) {
        ["p"]=>
        bool(false)
        ["c"]=>
        string(5) "index"
        ["a"]=>
        string(5) "index"
        ["force_https"]=>
        bool(false)
        }
         *
         * array(4) {
        ["p"]=>
        bool(false)
        ["c"]=>
        string(5) "index"
        ["a"]=>
        string(2) "p1"
        ["force_https"]=>
        bool(false)
        }
         *
         * array(4) {
        ["p"]=>
        string(2) "p1"
        ["c"]=>
        string(5) "index"
        ["a"]=>
        string(2) "p2"
        ["force_https"]=>
        bool(false)
        }
         *
         * array(4) {
        ["p"]=>
        string(2) "p1"
        ["c"]=>
        string(2) "p2"
        ["a"]=>
        string(2) "p3"
        ["force_https"]=>
        bool(false)
        }
         *
         */

        if( empty( $api_parts['p1'] ) )
            return PHS::parse_route();

        if( empty( $api_parts['p2'] ) and empty( $api_parts['p3'] ) )
            return PHS::parse_route( array( 'a' => $api_parts['p1'] ), true );

        if( empty( $api_parts['p2'] ) and !empty( $api_parts['p3'] ) )
            return false;

        if( empty( $api_parts['p3'] ) )
            return PHS::parse_route( array( 'p' => $api_parts['p1'], 'a' => $api_parts['p2'] ), true );

        return PHS::parse_route( array( 'p' => $api_parts['p1'], 'c' => $api_parts['p2'], 'a' => $api_parts['p3'] ), true );
    }

    /**
     * @param $api_route
     * @param $phs_route
     * @param string $method
     *
     * @return bool true on success or false on error
     */
    public static function register_api_route( $api_route, $phs_route, $method = 'get' )
    {
        self::st_reset_error();

        if( !($method = self::prepare_http_method( $method )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Please provide a valid API method.' ) );
            return false;
        }

        if( empty( $api_route )
         or !($pca_api_route = self::api_parts_to_route( $api_route ))
         or !($api_route = PHS::parse_route( $pca_api_route, true )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Couldn\'t parse provided API route.' ) );
            return false;
        }

        if( empty( $phs_route )
         or !($phs_route = PHS::parse_route( $phs_route, true )) )
        {
            self::st_set_error( self::ERR_API_ROUTE, self::_t( 'Couldn\'t parse provided PHS route for API calls.' ) );
            return false;
        }

        self::$_route_aliases[$api_route['p']][$api_route['c']][$api_route['a']][$method] = $phs_route;

        return true;
    }

    /**
     * @param null|PHS_api_base $api_obj API instance to be set as request API instance
     *
     * @return bool|PHS_api_base Return request API instance or false if none set
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
         or !($api_obj instanceof PHS_api_base) )
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
                // In case we run in an environment where $_POST is not a global variable
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
         or !($api_pass = $this->api_flow_value( 'api_pass' )) )
        {
            if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED, 'Please provide credentials' ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Please provide credentials' ) );
                return false;
            }

            exit;
        }

        if( !($apikey_arr = $this->get_apikey_by_apikey( $api_user ))
         or $apikey_arr['api_secret'] != $api_pass )
        {
            if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Not authorized.' ) );
                return false;
            }

            exit;
        }

        if( !empty( $apikey_arr['allowed_methods'] )
        and !in_array( $this->http_method(), self::extract_strings_from_comma_separated( $apikey_arr['allowed_methods'], array( 'to_lowercase' => true ) ) ) )
        {
            if( !$this->send_header_response( self::H_CODE_METHOD_NOT_ALLOWED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Method not allowed.' ) );
                return false;
            }

            exit;
        }

        if( !empty( $apikey_arr['denied_methods'] )
        and in_array( $this->http_method(), self::extract_strings_from_comma_separated( $apikey_arr['denied_methods'], array( 'to_lowercase' => true ) ) ) )
        {
            if( !$this->send_header_response( self::H_CODE_METHOD_NOT_ALLOWED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Method not allowed.' ) );
                return false;
            }

            exit;
        }

        return true;
    }
}

