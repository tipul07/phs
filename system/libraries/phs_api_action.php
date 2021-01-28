<?php

namespace phs\libraries;

use \phs\PHS_Scope;
use \phs\PHS_Api;
use \phs\PHS_Api_base;
use \phs\libraries\PHS_Params;

abstract class PHS_Api_action extends PHS_Action
{
    /** @var bool|\phs\PHS_Api_base $api_obj */
    protected $api_obj = false;

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_API, PHS_Scope::SCOPE_AJAX ];
    }

    /**
     * @return bool|\phs\PHS_Api_base
     */
    public function get_action_api_instance()
    {
        if( PHS_Scope::current_scope() === PHS_Scope::SCOPE_API
         && !$this->api_obj )
            $this->api_obj = PHS_Api::global_api_instance();

        return $this->api_obj;
    }

    /**
     * @return array
     */
    public static function default_api_response()
    {
        return [
            // Scope ID if you want to force a scope
            'force_scope' => false,
            // API instance (if already instanciated)
            /** @var \phs\PHS_Api $api_obj */
            'api_obj' => false,

            // An array which will be JSON encoded as response
            'response_data' => null,
            // Don't include error node automatically in response_data (send response_data node only to client)
            'only_response_data_node' => false,
            // HTTP code to return in response
            'http_code' => PHS_Api::H_CODE_OK,
            // If error.code != 0 and http_code == 200 => http_code = 500
            'error' => [
                'code' => 0,
                'message' => '',
            ],
        ];
    }

    /**
     * @param array $response_data
     * @param false|array $action_result_defaults Array with keys that should replace action array keys
     *
     * @return array|false
     */
    public function send_api_response( $response_data, $action_result_defaults = false )
    {
        $response_data = self::validate_array_recursive( $response_data, self::default_api_response() );

        if( !empty( $response_data['force_scope'] )
         && PHS_Scope::valid_scope( $response_data['force_scope'] ) )
            $scope = $response_data['force_scope'];
        else
            $scope = PHS_Scope::current_scope();

        if( !empty( $response_data['error']['code'] )
         && (empty( $response_data['http_code'] )
                || in_array( (int)$response_data['http_code'], [ PHS_Api::H_CODE_OK, PHS_Api::H_CODE_OK_CREATED, PHS_Api::H_CODE_OK_NO_CONTENT ], true )
            ) )
            $response_data['http_code'] = PHS_Api::H_CODE_INTERNAL_SERVER_ERROR;

        $action_result = PHS_Action::default_action_result();
        if( $action_result_defaults !== false
         && is_array( $action_result_defaults ) )
            $action_result = self::merge_array_assoc( $action_result, $action_result_defaults );

        if( empty( $response_data['only_response_data_node'] ) )
        {
            $response = [
                'response' => $response_data['response_data'],
                'error' => $response_data['error'],
            ];

            $response_data['response_data'] = $response;
        }

        if( $scope === PHS_Scope::SCOPE_API )
        {
            if( !empty( $response_data['api_obj'] )
             && ($response_data['api_obj'] instanceof PHS_Api_base))
                $api_obj = $response_data['api_obj'];
            else
                $api_obj = PHS_Api::global_api_instance();

            if( !$api_obj->send_header_response( $response_data['http_code'] ) )
            {
                return false;
            }
        } elseif( $scope === PHS_Scope::SCOPE_AJAX )
        {
        }

        $action_result['ajax_result'] = $response_data['response_data'];

        return $action_result;
    }

    /**
     * @param int $http_error
     * @param int $error_no
     * @param string $error_msg
     * @param false|array $action_result_defaults
     *
     * @return array|false
     */
    public function send_api_error( $http_error, $error_no, $error_msg, $action_result_defaults = false )
    {
        if( !PHS_Api::valid_http_code( $http_error ) )
            $http_error = PHS_Api::H_CODE_INTERNAL_SERVER_ERROR;

        if( empty( $error_no ) )
            $error_no = self::ERR_FRAMEWORK;
        if( empty( $error_msg ) )
            $error_msg = $this->_pt( 'Error not provided.' );

        $response_params = self::default_api_response();
        $response_params['api_obj'] = $this->get_action_api_instance();
        $response_params['http_code'] = $http_error;
        $response_params['error']['code'] = $error_no;
        $response_params['error']['message'] = $error_msg;

        return $this->send_api_response( $response_params, $action_result_defaults );
    }

    /**
     * @param array|null $payload_arr
     * @param int $http_code
     * @param false|array $action_result_defaults
     *
     * @return array|false
     */
    public function send_api_success( $payload_arr, $http_code = PHS_Api::H_CODE_OK, $action_result_defaults = false )
    {
        if( !PHS_Api::valid_http_code( $http_code ) )
            $http_code = PHS_Api::H_CODE_OK;

        $response_params = self::default_api_response();
        $response_params['api_obj'] = $this->get_action_api_instance();
        $response_params['http_code'] = $http_code;
        $response_params['response_data'] = $payload_arr;

        return $this->send_api_response( $response_params, $action_result_defaults );
    }

    /**
     * @param string $var_name What variable are we looking for
     * @param int $type Type used for value validation
     * @param mixed $default Default value if variable not found in request
     * @param false|array $type_extra Extra params used in variable validation
     * @param string $order Order in which we will do the checks (b - request body as JSON, g - get, p - post)
     *
     * @return mixed
     */
    public function request_var( $var_name, $type = PHS_Params::T_ASIS, $default = null, $type_extra = false, $order = 'bpg' )
    {
        static $json_request = null;

        if( $json_request === null
         && !($json_request = PHS_Api_base::get_request_body_as_json_array()) ) {
            $json_request = false;
        }

        if( !is_string( $order )
         || $order === '' ) {
            return $default;
        }

        $val = null;
        while( ($ch = substr( $order, 0, 1 )) )
        {
            switch( strtolower( $ch ) )
            {
                case 'b':
                    if( !empty( $json_request ) && is_array( $json_request )
                     && isset( $json_request[$var_name] ) ) {
                        $val = $json_request[$var_name];
                        break 2;
                    }
                break;

                case 'p':
                    if( null !== ($val = PHS_Params::_p( $var_name, $type, $type_extra )) ) {
                        return $val;
                    }
                break;

                case 'g':
                    if( null !== ($val = PHS_Params::_g( $var_name, $type, $type_extra )) ) {
                        return $val;
                    }
                break;
            }

            if( !($order = substr( $order, 1 )) ) {
                break;
            }
        }

        if( $val === null
         || null === ($type_val = PHS_Params::set_type( $val, $type, $type_extra )) ) {
            return $default;
        }

        return $type_val;
    }
}
