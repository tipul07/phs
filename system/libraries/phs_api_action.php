<?php
namespace phs\libraries;

use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\system\core\models\PHS_Model_Api_monitor;

abstract class PHS_Api_action extends PHS_Action
{
    public const ERR_API_INIT = 40000, ERR_AUTHENTICATION = 40001;

    protected ?PHS_Api_base $api_obj = null;

    /**
     * API route requires authentication to run
     *
     * @var null|bool
     */
    protected static ?bool $_authentication_required = null;

    /**
     * Run this API action even if there is no authentication (used for actions required for both authenticated or not users)
     *
     * @var null|bool
     */
    protected static ?bool $_authentication_is_optional = null;

    /**
     * Run a single method for authentication
     * Method receives as parameters an array and should return false in case authentication failed,
     * or it can safetly send headers back to browser and exit directly
     * !!! If authetication passes it MUST return true
     *
     * @var null|callable
     * @see PHS_Api_base::default_api_authentication_callback_params()
     */
    protected static $_authentication_callback;

    /**
     * Provide an array of authentication methods which will be called in the order presented in this array
     * If authentication_callback is provided, that callback is called first
     * Custom error can be set using {$param['api_obj']}->set_error().
     * This will be sent in header as response. Don't put a long string here...
     *
     * @var null|array
     */
    protected static ?array $_authentication_callback_cascade = null;

    /**
     * This is an array which specifies what default authentication methods to be used (basic or bearer)
     * null means default ones
     *
     * @var null|array
     * @see self::AUTH_METHOD_BASIC, self::AUTH_METHOD_BEARER
     */
    protected static ?array $_authentication_methods = null;

    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_API, PHS_Scope::SCOPE_AJAX];
    }

    public function get_action_api_instance(): ?PHS_Api_base
    {
        if (!$this->api_obj
            && PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
            $this->api_obj = PHS_Api::global_api_instance();
        }

        return $this->api_obj;
    }

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

        $action_result = self::default_action_result();
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
                        .($response_data['error']['message'] ?? 'Unknown error.'),
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

    public function send_api_error(int $http_error, int $error_no, string $error_msg,
        ?array $action_result_defaults = null, ?array $extra_arr = [])
    {
        if (!PHS_Api::valid_http_code($http_error)) {
            $http_error = PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR;
        }

        $extra_arr['only_response_data_node'] = !empty($extra_arr['only_response_data_node']);

        $response_params = self::default_api_response();
        $response_params['api_obj'] = $this->get_action_api_instance();
        $response_params['http_code'] = $http_error;
        $response_params['only_response_data_node'] = $extra_arr['only_response_data_node'];
        $response_params['http_code_is_error'] = true;
        $response_params['error']['code'] = $error_no ?: self::ERR_FRAMEWORK;
        $response_params['error']['message'] = $error_msg ?: $this->_pt('Error not provided.');

        return $this->send_api_response($response_params, $action_result_defaults);
    }

    public function send_api_success(
        $payload_arr,
        int $http_code = PHS_Api_base::H_CODE_OK,
        ?array $action_result_defaults = null,
        ?array $extra_arr = [],
    ) {
        if (!PHS_Api::valid_http_code($http_code)) {
            $http_code = PHS_Api_base::H_CODE_OK;
        }

        $extra_arr['only_response_data_node'] = !empty($extra_arr['only_response_data_node']);

        $response_params = self::default_api_response();
        $response_params['api_obj'] = $this->get_action_api_instance();
        $response_params['http_code'] = $http_code;
        $response_params['only_response_data_node'] = $extra_arr['only_response_data_node'];
        $response_params['response_data'] = $payload_arr;

        return $this->send_api_response($response_params, $action_result_defaults);
    }

    public function get_request_body(): ?array
    {
        return PHS_Api_base::get_request_body_as_json_array();
    }

    /**
     * @param string $var_name What variable are we looking for
     * @param int $type Type used for value validation
     * @param mixed $default Default value if variable not found in request
     * @param array $type_extra Extra params used in variable validation
     * @param string $order Order in which we will do the checks (b - request body as JSON, g - get, p - post)
     *
     * @return mixed
     */
    public function request_var(
        string $var_name,
        int $type = PHS_Params::T_ASIS,
        mixed $default = null,
        array $type_extra = [],
        string $order = 'bpg',
    ): mixed {
        if ($order === '') {
            return $default;
        }

        $json_request = $this->get_request_body();

        $val = null;
        while (($ch = substr($order, 0, 1))) {
            switch (strtolower($ch)) {
                case 'b':
                    if ($json_request
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
     * @return array{force_scope:false|int, api_obj:null|PHS_Api, response_data:null|array, only_response_data_node:bool, http_code_is_error:bool, http_code:int, error: array{code: int, message:string}}
     */
    public static function default_api_response(): array
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

    final public static function get(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('get', $api_route, $params, $controller_class);
    }

    final public static function post(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('post', $api_route, $params, $controller_class);
    }

    final public static function patch(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('patch', $api_route, $params, $controller_class);
    }

    final public static function put(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('put', $api_route, $params, $controller_class);
    }

    final public static function delete(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('delete', $api_route, $params, $controller_class);
    }

    final public static function head(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('head', $api_route, $params, $controller_class);
    }

    final public static function options(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('options', $api_route, $params, $controller_class);
    }

    final public static function trace(string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        return static::method('trace', $api_route, $params, $controller_class);
    }

    final public static function method(string $method, string | array $api_route, array $params = [], ?string $controller_class = null): bool
    {
        if (is_string($api_route)) {
            $api_route = explode('/', $api_route);
        }

        $api_route = array_map(static function(string | array $item) {
            return is_string($item) ? ['exact_match' => $item] : $item;
        }, $api_route);

        return PHS_Api::register_api_route(
            $api_route,
            static::get_route_from_class($controller_class),
            array_merge([
                'method'                          => $method,
                'name'                            => static::$_action_name,
                'description'                     => static::$_action_description,
                'authentication_required'         => static::$_authentication_required,
                'authentication_is_optional'      => static::$_authentication_required,
                'authentication_callback'         => static::$_authentication_callback,
                'authentication_callback_cascade' => static::$_authentication_callback_cascade,
                'authentication_methods'          => static::$_authentication_methods,
            ], $params),
        );
    }
}
