<?php

namespace phs\libraries;

use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\system\core\models\PHS_Model_Api_monitor;

abstract class PHS_Api_action extends PHS_Action
{
    public const ERR_API_INIT = 40000, ERR_AUTHENTICATION = 40001;

    /** @var null|PHS_Api_base */
    protected ?PHS_Api_base $api_obj = null;

    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_API, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return null|PHS_Api_base
     */
    public function get_action_api_instance() : ?PHS_Api_base
    {
        if (!$this->api_obj
            && PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
            $this->api_obj = PHS_Api::global_api_instance();
        }

        return $this->api_obj;
    }

    /**
     * @param array $response_data
     * @param null|array $action_result_defaults Array with keys that should replace action array keys
     *
     * @return array|false
     */
    public function send_api_response(array $response_data, ?array $action_result_defaults = null)
    {
        $response_data = self::validate_array_recursive($response_data, self::default_api_response());

        if (!empty($response_data['force_scope'])
         && PHS_Scope::valid_scope($response_data['force_scope'])) {
            $scope = $response_data['force_scope'];
        } else {
            $scope = PHS_Scope::current_scope();
        }

        if (!empty($response_data['error']['code'])
         && (empty($response_data['http_code'])
                || in_array((int)$response_data['http_code'],
                    [PHS_Api_base::H_CODE_OK, PHS_Api_base::H_CODE_OK_CREATED, PHS_Api_base::H_CODE_OK_NO_CONTENT], true)
         )) {
            $response_data['http_code'] = PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR;
        }

        $action_result = PHS_Action::default_action_result();
        if ($action_result_defaults !== null) {
            $action_result = self::merge_array_assoc($action_result, $action_result_defaults);
        }

        if (empty($response_data['only_response_data_node'])) {
            $response = [
                'response' => $response_data['response_data'],
                'error'    => $response_data['error'],
            ];

            $response_data['response_data'] = $response;
        }

        if ($scope === PHS_Scope::SCOPE_API
         || $scope === PHS_Scope::SCOPE_REMOTE) {
            if (!empty($response_data['api_obj'])
             && ($response_data['api_obj'] instanceof PHS_Api_base)) {
                $api_obj = $response_data['api_obj'];
            } else {
                $api_obj = PHS_Api::global_api_instance();
            }

            if (!$api_obj->send_header_response($response_data['http_code'])) {
                return false;
            }

            if ($scope === PHS_Scope::SCOPE_API) {
                // We don't have now the full request body, log only HTTP code
                if (!empty($response_data['http_code_is_error'])) {
                    PHS_Model_Api_monitor::api_incoming_request_error($response_data['http_code'],
                        (!empty($response_data['error']['code']) ? $response_data['error']['code'].': ' : '')
                        .($response_data['error']['message'] ?? 'Unknown error.')
                    );
                } else {
                    PHS_Model_Api_monitor::api_incoming_request_success($response_data['http_code']);
                }
            }
        } elseif ($scope === PHS_Scope::SCOPE_AJAX) {
            if (!empty($response_data['http_code_is_error'])
             && !PHS_Api_base::http_header_response($response_data['http_code'])) {
                return false;
            }
        }

        $action_result['ajax_result'] = $response_data['response_data'];

        return $action_result;
    }

    /**
     * @param int $http_error
     * @param int $error_no
     * @param string $error_msg
     * @param null|array $action_result_defaults
     * @param null|array $extra_arr
     *
     * @return array|false
     */
    public function send_api_error(int $http_error, int $error_no, string $error_msg,
        ?array $action_result_defaults = null, ?array $extra_arr = null)
    {
        if (!PHS_Api::valid_http_code($http_error)) {
            $http_error = PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR;
        }

        if (empty($extra_arr)) {
            $extra_arr = [];
        }

        $extra_arr['only_response_data_node'] = (!empty($extra_arr['only_response_data_node']));

        if (empty($error_no)) {
            $error_no = self::ERR_FRAMEWORK;
        }
        if (empty($error_msg)) {
            $error_msg = $this->_pt('Error not provided.');
        }

        $response_params = self::default_api_response();
        $response_params['api_obj'] = $this->get_action_api_instance();
        $response_params['http_code'] = $http_error;
        $response_params['only_response_data_node'] = $extra_arr['only_response_data_node'];
        $response_params['http_code_is_error'] = true;
        $response_params['error']['code'] = $error_no;
        $response_params['error']['message'] = $error_msg;

        return $this->send_api_response($response_params, $action_result_defaults);
    }

    /**
     * @param bool|array $payload_arr
     * @param int $http_code
     * @param null|array $action_result_defaults
     * @param null|array $extra_arr
     *
     * @return array|false
     */
    public function send_api_success($payload_arr, int $http_code = PHS_Api_base::H_CODE_OK,
        ?array $action_result_defaults = null, ?array $extra_arr = null)
    {
        if (!PHS_Api::valid_http_code($http_code)) {
            $http_code = PHS_Api_base::H_CODE_OK;
        }

        if (empty($extra_arr)) {
            $extra_arr = [];
        }

        $extra_arr['only_response_data_node'] = (!empty($extra_arr['only_response_data_node']));

        $response_params = self::default_api_response();
        $response_params['api_obj'] = $this->get_action_api_instance();
        $response_params['http_code'] = $http_code;
        $response_params['only_response_data_node'] = $extra_arr['only_response_data_node'];
        $response_params['response_data'] = $payload_arr;

        return $this->send_api_response($response_params, $action_result_defaults);
    }

    public function get_request_body() : ?array
    {
        static $json_request = null;

        if ($json_request === null
            && !($json_request = PHS_Api_base::get_request_body_as_json_array())) {
            $json_request = [];
        }

        return $json_request;
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
    public function request_var(string $var_name, int $type = PHS_Params::T_ASIS, $default = null, $type_extra = false, string $order = 'bpg')
    {
        if ($order === '') {
            return $default;
        }

        $json_request = $this->get_request_body();

        $val = null;
        while (($ch = substr($order, 0, 1))) {
            switch (strtolower($ch)) {
                case 'b':
                    if (!empty($json_request)
                     && isset($json_request[$var_name])) {
                        $val = $json_request[$var_name];
                        break 2;
                    }
                    break;

                case 'p':
                    if (null !== ($val = PHS_Params::_p($var_name, $type, $type_extra))) {
                        return $val;
                    }
                    break;

                case 'g':
                    if (null !== ($val = PHS_Params::_g($var_name, $type, $type_extra))) {
                        return $val;
                    }
                    break;
            }

            if (!($order = substr($order, 1))) {
                break;
            }
        }

        if ($val === null
         || null === ($type_val = PHS_Params::set_type($val, $type, $type_extra))) {
            return $default;
        }

        return $type_val;
    }

    /**
     * @return array{force_scope:false|int, api_obj:null|\phs\PHS_Api, response_data:null|array, only_response_data_node:bool, http_code_is_error:bool, http_code:int, error: array{code: int, message:string}}
     */
    public static function default_api_response() : array
    {
        return [
            // Scope ID if you want to force a scope
            'force_scope' => false,
            // API instance (if already instanciated)
            /** @var null|PHS_Api $api_obj */
            'api_obj' => null,

            // An array which will be JSON encoded as response
            'response_data' => null,
            // Don't include error node automatically in response_data (send response_data node only to client)
            'only_response_data_node' => false,
            // Tells if HTTP code should be interpretted as error
            // In Ajax scope this means we will set response headers, so AJAX request can be interpreted as error
            'http_code_is_error' => false,
            // HTTP code to return in response
            'http_code' => PHS_Api_base::H_CODE_OK,
            // If error.code != 0 and http_code == 200 => http_code = 500
            'error' => [
                'code'    => 0,
                'message' => '',
            ],
        ];
    }
}
