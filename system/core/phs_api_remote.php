<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Hooks;

//! @version 1.00

class PHS_Api_remote extends PHS_Api_base
{
    const ERR_API_INIT = 40000, ERR_API_ROUTE = 40001;

    /** @var false|\phs\PHS_Api_remote $_api_obj */
    private static $_api_obj = false;

    /** @var false|\phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $_domains_model */
    private static $_domains_model = false;

    private function _load_dependencies()
    {
        $this->reset_error();

        if( empty( self::$_domains_model )
         && !(self::$_domains_model = PHS::load_model( 'phs_remote_domains', 'remote_phs' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading remote domains model.' ) );
            return false;
        }

        return true;
    }

    final public static function api_factory( $init_query_params = false )
    {
        self::st_reset_error();

        if( self::$_api_obj !== false )
            return self::$_api_obj;

        if( !($api_obj = new PHS_Api_remote( $init_query_params )) )
        {
            self::st_set_error( self::ERR_API_INIT, self::_t( 'Error obtaining remote API instance.' ) );
            return false;
        }

        self::$_api_obj = $api_obj;

        return self::$_api_obj;
    }

    public function __construct( $init_query_params = false )
    {
        parent::__construct();

        if( $init_query_params !== false
         && !($this->_init_api_query_params( $init_query_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_API_INIT, self::_t( 'Couldn\'t initialize remote API object.' ) );
        }
    }

    protected function _before_route_run()
    {
        if( $this->is_web_simulation() )
        {
            PHS_Scope::emulated_scope( PHS_Scope::SCOPE_WEB );

            if( ($request_body = $this::get_php_input())
             && ($json_arr = @json_decode( $request_body, true )) )
            {
                // In case we run in an environment where $_POST is not defined
                global $_POST;

                if( empty( $_POST ) || !is_array( $_POST ) )
                    $_POST = [];

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
        $this->reset_error();

        if( !($api_user = $this->api_flow_value( 'api_user' ))
         || null === ($api_pass = $this->api_flow_value( 'api_pass' )) )
        {
            if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED, 'Please provide credentials' ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Please provide credentials' ) );
                return false;
            }

            exit;
        }

        if( !($apikey_arr = $this->get_apikey_by_apikey( $api_user ))
         || (string)$apikey_arr['api_secret'] !== (string)$api_pass )
        {
            if( !$this->send_header_response( self::H_CODE_UNAUTHORIZED ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Not authorized.' ) );
                return false;
            }

            exit;
        }

        if( $this->is_web_simulation()
         && empty( $apikey_arr['allow_sw'] ) )
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
         && !in_array( $http_method, self::extract_strings_from_comma_separated( $apikey_arr['allowed_methods'], [ 'to_lowercase' => true ] ), true ) )
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
         && in_array( $http_method, self::extract_strings_from_comma_separated( $apikey_arr['denied_methods'], [ 'to_lowercase' => true ] ), true ) )
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
         && !in_array( $request_ip, self::extract_strings_from_comma_separated( $apikey_arr['allowed_ips'], [ 'to_lowercase' => true ] ), true ) )
        {
            if( !$this->send_header_response( self::H_CODE_FORBIDDEN ) )
            {
                $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Access not allowed.' ) );
                return false;
            }

            PHS_Logger::logf( 'IP denied (#'.$apikey_arr['id'].', '.$request_ip.').', PHS_Logger::TYPE_API );

            exit;
        }

        return true;
    }

    private function _parse_remote_message()
    {
        if( !$this->_load_dependencies() )
            return false;

        $domain_model = self::$_domains_model;

        // Process JSON body
        if( !($root_json_arr = self::get_request_body_as_json_array())
         || empty( $root_json_arr['remote_id'] )
         || !($remote_id = (int)$root_json_arr['remote_id'])
         || !($domain_arr = $domain_model->get_details( $remote_id, [ 'table_name' => 'phs_remote_domains' ] ))
         || !$domain_model->is_connected( $domain_arr ) )
        {
            $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Invalid request.' ) );
            return false;
        }

        if( !empty( $domain_arr['ips_whitelist'] )
         && (
               !($request_ip = request_ip())
            || !in_array( $request_ip, self::extract_strings_from_comma_separated( $domain_arr['ips_whitelist'], [ 'to_lowercase' => true ] ), true )
            ) )
        {
            PHS_Logger::logf( 'IP denied (#'.$domain_arr['id'].', '.$request_ip.').', PHS_Logger::TYPE_REMOTE );

            $this->set_error( self::ERR_AUTHENTICATION, $this->_pt( 'Access not allowed.' ) );
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    final public function run_route( $extra = false )
    {
        $this->reset_error();

        if( false === PHS_Scope::current_scope( PHS_Scope::SCOPE_REMOTE ) )
        {
            $this->set_error( self::ERR_RUN_ROUTE, self::_t( 'Error preparing API environment.' ) );
            return false;
        }

        if( empty( $extra ) || !is_array( $extra ) )
            $extra = [];

        if( !$this->_parse_remote_message() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error interpreting the request.' ) );

            return false;
        }


        if( PHS::st_debugging_mode() )
            PHS_Logger::logf( 'Request API route tokens ['.implode( '/', $final_api_route_tokens ).']', PHS_Logger::TYPE_API );

        $this->api_flow_value( 'original_api_route_tokens', $final_api_route_tokens );

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

    /**
     * @inheritdoc
     */
    public function create_response_envelope( $response_arr, $errors_arr = false )
    {
        if( !is_array( $response_arr ) )
            $response_arr = [];

        if( !array_key_exists( 'response_status', $response_arr )
         || is_array( $response_arr['response_status'] ) )
        {
            if( @class_exists( '\\phs\\libraries\\PHS_Notifications', false ) )
                $status_data = [
                    'success_messages' => PHS_Notifications::notifications_success(),
                    'warning_messages' => PHS_Notifications::notifications_warnings(),
                    'error_messages' => PHS_Notifications::notifications_errors(),
                ];
            else
            {
                if( empty( $errors_arr ) || !is_array( $errors_arr ) )
                    $errors_arr = [];

                $status_data = [
                    'success_messages' => [],
                    'warning_messages' => [],
                    'error_messages' => $errors_arr,
                ];
            }

            if( empty( $response_arr['response_status'] ) )
                $response_arr['response_status'] = [];

            $response_arr['response_status'] = self::validate_array( $response_arr['response_status'], $status_data );
        }

        // Check if we should remove response_status key from response
        if( array_key_exists( 'response_status', $response_arr )
         && $response_arr['response_status'] === null )
            unset( $response_arr['response_status'] );

        return $response_arr;
    }
}

