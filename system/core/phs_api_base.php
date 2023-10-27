<?php
namespace phs;

use phs\PHS_Api;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Registry;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Api_keys;
use phs\plugins\accounts\models\PHS_Model_Accounts;

// ! @version 1.00

abstract class PHS_Api_base extends PHS_Registry
{
    public const ERR_RUN_ROUTE = 30001, ERR_AUTHENTICATION = 30002, ERR_HTTP_METHOD = 30003, ERR_HTTP_PROTOCOL = 30004, ERR_APIKEY = 30005;

    public const DEFAULT_VERSION = 1;

    public const GENERIC_ERROR_CODE = 500, GENERIC_OK_CODE = 200;

    // Most used HTTP error codes
    public const H_CODE_OK = 200, H_CODE_OK_CREATED = 201, H_CODE_OK_ACCEPTED = 202, H_CODE_OK_NO_CONTENT = 204,
    H_CODE_MOVED_PERMANENTLY = 301, H_CODE_NOT_MODIFIED = 304, H_CODE_TEMPORARY_REDIRECT = 307, H_CODE_PERMANENT_REDIRECT = 308,
    H_CODE_BAD_REQUEST = 400, H_CODE_UNAUTHORIZED = 401, H_CODE_FORBIDDEN = 403, H_CODE_NOT_FOUND = 404, H_CODE_METHOD_NOT_ALLOWED = 405,
    H_CODE_NOT_ACCEPTABLE = 406, H_CODE_CONFLICT = 409, H_CODE_UNSUPPORTED_MEDIA_TYPE = 415, H_CODE_TOO_MANY_REQUESTS = 429,
    H_CODE_INTERNAL_SERVER_ERROR = 500, H_CODE_NOT_IMPLEMENTED = 501, H_CODE_BAD_GATEWAY = 502, H_CODE_SERVICE_UNAVAILABLE = 503, H_CODE_GATEWAY_TIMEOUT = 504,
    H_CODE_INSUFFICIENT_STORAGE = 507;

    // API version
    public const PARAM_VERSION = 'v',
    // This is an API route (NOT necessary PHS route) This can be translated from aliases into a PHS route (if required) by plugins
    PARAM_API_ROUTE = '_ar',
    // Tells API class to arrange request parameters in such way that normal SCOPE_WEB actions can be used in API calls
    PARAM_WEB_SIMULATION = '_sw',
    // Tells API class that original request was done using apache mod_rewrite (or similar).
    // This parameter is appended to the request in rewrite rule
    PARAM_USING_REWRITE = '_rw';

    // Built-in authentication methods
    public const AUTH_METHOD_BASIC = 'basic', AUTH_METHOD_BEARER = 'bearer';

    /** @var array */
    protected array $raw_query_params = [];

    /** @var array */
    protected array $init_query_params = [];

    /** @var array All allowed HTTP methods in lowercase */
    protected array $allowed_http_methods = ['get', 'post', 'delete', 'patch'];

    /** @var null|array Allowed authentication methods (basic, bearer), null means default ones */
    protected ?array $allowed_authentication_methods = null;

    /** @var array Instance API flow */
    protected array $my_flow = [];

    /**
     * Just method name which should be defined in $this when calling
     * @see \phs\PHS_Api_base::_api_authentication_failed()
     */
    protected static array $AUTH_METHODS_CALLBACKS = [
        self::AUTH_METHOD_BASIC  => ['method' => '_basic_api_authentication_failed', ],
        self::AUTH_METHOD_BEARER => ['method' => '_bearer_api_authentication_failed'],
    ];

    /** @var array API settings set in admin plugin settings */
    protected static array $_framework_settings = [];

    /**
     * @param array $extra Parameters for method
     *
     * @return array|bool False in case of error or an action result array
     */
    abstract public function run_route(array $extra = []);

    /**
     * Override this method in case you want to envelope each response in a "standard" response structure
     *
     * @param array $response_arr Response which should be enveloped
     * @param null|array $errors_arr Any errors that should be added in envelope in case we don't have access to PHS_Notifications class
     *
     * @return null|array Return response envelope array or null on error
     */
    public function create_response_envelope(array $response_arr, ?array $errors_arr = null) : ?array
    {
        return $response_arr;
    }

    public function api_flow_value($key = null, $val = null)
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        if ($key === null) {
            return $this->my_flow;
        }

        // 'api_method' will be set using $this->set_http_method();
        if ($val === null) {
            if (!is_array($key)) {
                if (is_scalar($key)
                 && array_key_exists($key, $this->my_flow)) {
                    return $this->my_flow[$key];
                }

                return null;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)
                 || !array_key_exists($kkey, $this->my_flow)
                 || in_array($kkey, $this->_special_flow_keys(), true)) {
                    continue;
                }

                $this->my_flow[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)
         || !array_key_exists($key, $this->my_flow)
         || in_array($key, $this->_special_flow_keys(), true)) {
            return null;
        }

        $this->my_flow[$key] = $val;

        return true;
    }

    /**
     * @param null|array $methods_arr
     *
     * @return null|array
     */
    public function allowed_http_methods(?array $methods_arr = null) : ?array
    {
        if ($methods_arr === null) {
            return $this->allowed_http_methods;
        }

        if (!($new_methods = self::extract_strings_from_array($methods_arr, ['to_lowercase' => true]))) {
            return null;
        }

        $this->allowed_http_methods = $new_methods;

        return $new_methods;
    }

    /**
     * @param null|array $methods_arr
     *
     * @return null|array
     */
    public function allowed_authentication_methods(?array $methods_arr = null) : ?array
    {
        if ($methods_arr === null) {
            return $this->allowed_authentication_methods;
        }

        if (!($new_methods = self::extract_strings_from_array($methods_arr, ['to_lowercase' => true]))) {
            return null;
        }

        $this->allowed_authentication_methods = $new_methods;

        return $new_methods;
    }

    /**
     * Initialize API object
     *
     * @param null|array $init_params Array with parameters required to initialize API object
     *
     * @return bool If any errors return false and set error
     */
    public function _init_api_query_params(?array $init_params = null) : bool
    {
        $this->reset_error();

        if (empty($init_params)) {
            $init_params = [];
        }

        $this->raw_query_params = $init_params;
        $this->init_query_params = $this->default_query_params();

        if (!empty($this->raw_query_params[self::PARAM_VERSION])) {
            $this->init_query_params[self::PARAM_VERSION] = (int)$this->raw_query_params[self::PARAM_VERSION];
        } else {
            $this->init_query_params[self::PARAM_VERSION] = self::DEFAULT_VERSION;
        }

        $this->init_query_params[self::PARAM_USING_REWRITE] = (!empty($this->raw_query_params[self::PARAM_USING_REWRITE]));

        if (!PHS_Api::framework_api_can_simulate_web()) {
            $this->init_query_params[self::PARAM_WEB_SIMULATION] = false;
        } else {
            $this->init_query_params[self::PARAM_WEB_SIMULATION]
                = (!empty($this->raw_query_params[self::PARAM_WEB_SIMULATION]));
        }

        $this->init_query_params[self::PARAM_API_ROUTE]
            = (!empty($this->raw_query_params[self::PARAM_API_ROUTE])
                ? self::prepare_api_route_string($this->raw_query_params[self::PARAM_API_ROUTE])
                : '');

        return true;
    }

    /**
     * @return bool
     */
    public function extract_api_request_details() : bool
    {
        if (empty($_SERVER) || !is_array($_SERVER)) {
            return true;
        }

        $content_type = false;
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $content_type = $_SERVER['CONTENT_TYPE'];
        } elseif (!empty($_SERVER['HTTP_CONTENT_TYPE'])) {
            $content_type = $_SERVER['HTTP_CONTENT_TYPE'];
        }

        if (!empty($content_type)
         && !$this->set_content_type(strtolower(trim($content_type)))) {
            if ($this->has_error()) {
                $error_msg = $this->get_simple_error_message();
            } else {
                $error_msg = self::_t('Couldn\'t set content type to API object.');
            }

            PHS_Logger::error('Error setting content type in API instance: ['.$error_msg.']', PHS_Logger::TYPE_DEBUG);

            $this->set_error(self::ERR_PARAMETERS, $error_msg);

            return false;
        }

        if (!empty($_SERVER['REQUEST_METHOD'])
         && !$this->set_http_method($_SERVER['REQUEST_METHOD'])) {
            if ($this->has_error()) {
                $error_msg = $this->get_simple_error_message();
            } else {
                $error_msg = self::_t('Couldn\'t set HTTP method to API object.');
            }

            PHS_Logger::error('Error setting HTTP method in API instance: ['.$error_msg.']', PHS_Logger::TYPE_DEBUG);

            $this->set_error(self::ERR_PARAMETERS, $error_msg);

            return false;
        }

        if (!empty($_SERVER['SERVER_PROTOCOL'])
         && !$this->set_http_protocol(trim($_SERVER['SERVER_PROTOCOL']))) {
            if ($this->has_error()) {
                $error_msg = $this->get_simple_error_message();
            } else {
                $error_msg = self::_t('Couldn\'t set response protocol to API object.');
            }

            PHS_Logger::error('Error setting response protocol in API instance: ['.$error_msg.']', PHS_Logger::TYPE_DEBUG);

            $this->set_error(self::ERR_PARAMETERS, $error_msg);

            return false;
        }

        return true;
    }

    /**
     * @param null|array $credentials_arr
     */
    public function set_api_credentials(?array $credentials_arr = null) : void
    {
        $new_credentials_arr = [
            'api_user'     => '',
            'api_pass'     => '',
            'bearer_token' => '',
        ];

        if (!empty($credentials_arr)) {
            $new_credentials_arr['api_user'] = ($credentials_arr['api_user'] ?? '');
            $new_credentials_arr['api_pass'] = ($credentials_arr['api_pass'] ?? '');
            $new_credentials_arr['bearer_token'] = ($credentials_arr['bearer_token'] ?? '');
        } else {
            if (($basic_credentials = $this->_set_basic_api_credentials($credentials_arr))) {
                $new_credentials_arr = self::merge_array_assoc($new_credentials_arr, $basic_credentials);
            }
            if (self::framework_allow_bearer_token_authentication()
             && ($token_credentials = $this->_set_bearer_token_api_credentials($credentials_arr))) {
                $new_credentials_arr = self::merge_array_assoc($new_credentials_arr, $token_credentials);
            }
        }

        $this->api_flow_value($new_credentials_arr);
    }

    public function get_api_credentials() : array
    {
        return [
            'api_user'     => $this->api_flow_value('api_user'),
            'api_pass'     => $this->api_flow_value('api_pass'),
            'bearer_token' => $this->api_flow_value('bearer_token'),
        ];
    }

    /**
     * @param bool|array $route_arr Route array defining plugin, controller and action to be used
     * @param bool|array $args Query parameters to be set for this URL
     * @param bool|array $extra Extra parameters sent to method
     *
     * @return mixed
     */
    final public function url($route_arr = false, $args = false, $extra = false)
    {
        if (empty($route_arr) || !is_array($route_arr)) {
            $route_arr = [];
        }

        if (empty($args) || !is_array($args)) {
            $args = [];
        }

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        $api_url_params = [];
        $api_url_params['include_version'] = !$this->is_rewrite_request();

        if (!($args = $this->_get_predefined_api_url_params($args, $api_url_params))) {
            $args = [];
        }

        $extra['for_scope'] = PHS_Scope::SCOPE_API;

        // Using special rewrite for api routes
        if ($this->is_rewrite_request()) {
            $route_arr = PHS::validate_route_from_parts($route_arr, true);

            if (!($route = PHS::route_from_parts($route_arr))) {
                $route = 'invalidApiRoute_'
                         .($route_arr['p'] ?? '').'::'.($route_arr['c'] ?? '').'::'.($route_arr['ad'] ?? '').'__'.($route_arr['a'] ?? '');
            }

            if (!($query_string = @http_build_query($args))) {
                $query_string = '';
            }

            // Parameters that shouldn't be run through http_build_query as values will be rawurlencoded,
            // and we might add javascript code in parameters
            // e.g. $extra['raw_args'] might be an id passed as javascript function parameter
            if (!empty($extra['raw_args']) && is_array($extra['raw_args'])
                && ($raw_query = array_to_query_string($extra['raw_args'], ['raw_encode_values' => false]))) {
                $query_string .= ($query_string !== '' ? '&' : '').$raw_query;
            }

            return PHS::get_base_url($route_arr['force_https']).'api/v'.$this->get_api_version().'/'.$route.($query_string !== '' ? '?'.$query_string : '');
        }

        return PHS::url($route_arr, $args, $extra);
    }

    /**
     * Based on API Key sent in request, return Api Key record from api_keys table (if available)
     *
     * @param null|string $apikey
     *
     * @return null|array
     */
    public function get_apikey_by_apikey(?string $apikey = null) : ?array
    {
        $this->reset_error();

        $account_arr = null;
        /** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (empty($apikey)
         || !($apikeys_model = PHS_Model_Api_keys::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($apikey_arr = $apikeys_model->get_details_fields(['api_key' => $apikey]))
         || !$apikeys_model->is_active($apikey_arr)
         || (!empty($apikey_arr['uid'])
            && (!($account_arr = $accounts_model->get_details($apikey_arr['uid']))
               || !$accounts_model->is_active($account_arr))
         )) {
            $this->set_error(self::ERR_APIKEY, $this->_pt('Api key not found in database.'));

            return null;
        }

        return [
            'apikey'  => $apikey_arr,
            'account' => $account_arr,
        ];
    }

    /**
     * Returns Api Key used when authenticating request (if any)
     * @return false|array
     */
    public function get_request_apikey()
    {
        if (!($apikey_arr = $this->api_flow_value('api_key_data'))) {
            return false;
        }

        return $apikey_arr;
    }

    public function default_query_params() : array
    {
        return [
            self::PARAM_VERSION        => self::DEFAULT_VERSION,
            self::PARAM_API_ROUTE      => '',
            self::PARAM_USING_REWRITE  => false,
            self::PARAM_WEB_SIMULATION => false,
        ];
    }

    public function get_api_version() : int
    {
        return $this->init_query_params[self::PARAM_VERSION] ?? self::DEFAULT_VERSION;
    }

    public function get_api_route() : string
    {
        return $this->init_query_params[self::PARAM_API_ROUTE] ?? '';
    }

    public function is_rewrite_request() : bool
    {
        return !empty($this->init_query_params[self::PARAM_USING_REWRITE]);
    }

    public function is_web_simulation() : bool
    {
        return !empty($this->init_query_params[self::PARAM_WEB_SIMULATION]);
    }

    public function response_header_set(string $key)
    {
        if (empty($this->my_flow['response_headers'])
         || !($key = strtolower(trim($key)))
         || !array_key_exists($key, $this->my_flow['response_headers'])) {
            return null;
        }

        return $this->my_flow['response_headers'][$key];
    }

    public function response_headers(bool $raw = false)
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        if (!empty($raw)) {
            return $this->my_flow['raw_response_headers'] ?? [];
        }

        return $this->my_flow['response_headers'] ?? [];
    }

    public function set_response_headers($headers_arr, bool $append = true)
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        if (!is_array($headers_arr)) {
            return false;
        }

        if (empty($append)) {
            $this->my_flow['response_headers'] = [];
            $this->my_flow['raw_response_headers'] = [];
        }

        $lower_to_raw_arr = [];
        foreach ($headers_arr as $key => $val) {
            $lower_key = strtolower(trim($key));
            if ($lower_key === '') {
                continue;
            }

            // Check if there is already a header like this, but letters are lower or upper case different
            if (isset($this->my_flow['response_headers'][$lower_key])) {
                if (empty($lower_to_raw_arr)) {
                    // create an index array with keys from lowercase to raw (if we have more cases like this)
                    $lower_to_raw_arr = [];
                    foreach ($this->my_flow['raw_response_headers'] as $rrh_key => $rrh_some_val) {
                        if (!($rh_key = strtolower(trim($rrh_key)))) {
                            continue;
                        }

                        $lower_to_raw_arr[$rh_key] = $rrh_key;
                    }
                }

                // Take letters capitalization as in first header value
                if (!empty($lower_to_raw_arr[$lower_key])) {
                    $key = $lower_to_raw_arr[$lower_key];
                }
            }

            $this->my_flow['raw_response_headers'][$key] = $val;

            $this->my_flow['response_headers'][$lower_key] = $val;

            $lower_to_raw_arr[$lower_key] = $key;
        }

        return $this->my_flow['raw_response_headers'];
    }

    /**
     * @param null|string $body_str
     *
     * @return bool|string
     */
    public function response_body(?string $body_str = null)
    {
        if ($body_str === null) {
            return $this->my_flow['response_body'];
        }

        $this->my_flow['response_body'] = $body_str;

        return true;
    }

    public function http_method()
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        return $this->my_flow['api_method'];
    }

    /**
     * @param string $method
     *
     * @return false|string
     */
    public function set_http_method(string $method)
    {
        $this->reset_error();

        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        $method = self::prepare_http_method($method);
        if (empty($method)
         || !in_array($method, $this->allowed_http_methods(), true)) {
            $this->set_error(self::ERR_HTTP_METHOD, self::_t('HTTP method %s not allowed.', $method));

            return false;
        }

        $this->my_flow['api_method'] = $method;

        return $this->my_flow['api_method'];
    }

    /**
     * @return string
     */
    public function http_protocol() : string
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        return $this->my_flow['http_protocol'];
    }

    /**
     * @param string $protocol
     *
     * @return null|string
     */
    public function set_http_protocol(string $protocol) : ?string
    {
        $this->reset_error();

        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        if (empty($protocol)) {
            $this->set_error(self::ERR_HTTP_PROTOCOL, self::_t('Invalid HTTP protocol.'));

            return null;
        }

        $this->my_flow['http_protocol'] = strtoupper(trim($protocol));

        return $this->my_flow['http_protocol'];
    }

    /**
     * @return string
     */
    public function content_type() : string
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        return $this->my_flow['content_type'];
    }

    /**
     * @param string $type
     *
     * @return null|string
     */
    public function set_content_type(string $type) : ?string
    {
        $this->reset_error();

        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        if (empty($type)) {
            $this->set_error(self::ERR_HTTP_PROTOCOL, self::_t('Invalid content type.'));

            return null;
        }

        $this->my_flow['content_type'] = strtoupper(trim($type));

        return $this->my_flow['content_type'];
    }

    /**
     * @return int
     */
    public function api_user_account_id() : int
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        return $this->my_flow['api_key_user_id'] ?? 0;
    }

    /**
     * @return null|array
     */
    public function api_account_data() : ?array
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        return !empty($this->my_flow['api_account_data']) ? $this->my_flow['api_account_data'] : null;
    }

    /**
     * @return null|array
     */
    public function api_session_data() : ?array
    {
        if (empty($this->my_flow)) {
            $this->my_flow = $this->_default_api_flow();
        }

        return !empty($this->my_flow['api_session_data']) ? $this->my_flow['api_session_data'] : null;
    }

    /**
     * @param int $code
     * @param null|string $msg
     *
     * @return bool
     */
    public function send_header_response(int $code, ?string $msg = null) : bool
    {
        return self::http_header_response($code, $msg, $this->http_protocol());
    }

    protected function default_response_envelope(array $response_arr, ?array $errors_arr = null) : ?array
    {
        if (!array_key_exists('response_status', $response_arr)
         || is_array($response_arr['response_status'])) {
            if (@class_exists(PHS_Notifications::class, false)) {
                $status_data = [
                    'success_messages' => PHS_Notifications::notifications_success(),
                    'warning_messages' => PHS_Notifications::notifications_warnings(),
                    'error_messages'   => PHS_Notifications::notifications_errors(),
                ];
            } else {
                if (empty($errors_arr)) {
                    $errors_arr = [];
                }

                $status_data = [
                    'success_messages' => [],
                    'warning_messages' => [],
                    'error_messages'   => $errors_arr,
                ];
            }

            if (empty($response_arr['response_status'])) {
                $response_arr['response_status'] = [];
            }

            $response_arr['response_status'] = self::validate_array($response_arr['response_status'], $status_data);
        }

        // Check if we should remove response_status key from response
        if (array_key_exists('response_status', $response_arr)
         && $response_arr['response_status'] === null) {
            unset($response_arr['response_status']);
        }

        return $response_arr;
    }

    protected function _api_authentication_failed(?array $auth_methods = null) : ?array
    {
        if (empty($auth_methods)
         && !($auth_methods = $this->allowed_authentication_methods())) {
            $auth_methods = static::get_default_authentication_methods();
        }

        $callback_called = false;
        $authentication_failed = null;
        foreach ($auth_methods as $auth_method) {
            if (empty(self::$AUTH_METHODS_CALLBACKS[$auth_method]['method'])
             || !@method_exists($this, self::$AUTH_METHODS_CALLBACKS[$auth_method]['method'])) {
                continue;
            }

            $callback_called = true;
            $method_name = self::$AUTH_METHODS_CALLBACKS[$auth_method]['method'];
            if (!($authentication_failed = $this->$method_name())) {
                // Make sure $authentication_failed is null
                $authentication_failed = null;
                break;
            }
        }

        if (!$callback_called) {
            return [
                'http_code' => self::H_CODE_UNAUTHORIZED,
                'error_msg' => $this->_pt('No authentication available'),
            ];
        }

        return $authentication_failed;
    }

    protected function _bearer_api_authentication_failed() : ?array
    {
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())) {
            return [
                'http_code' => self::H_CODE_UNAUTHORIZED,
                'error_msg' => $this->_pt('Please provide credentials'),
            ];
        }

        if (!($token = $this->api_flow_value('bearer_token'))
         || !($token_arr = $accounts_plugin->decode_bearer_token($token))
         || !($online_arr = $accounts_model->get_details_fields(['wid' => $token], ['table_name' => 'online']))
         || empty($online_arr['uid'])
         || $token_arr['account_id'] !== (int)$online_arr['uid']
         || !($account_arr = $accounts_model->get_details($online_arr['uid']))
         || !$accounts_model->is_active($account_arr)
        ) {
            return [
                'http_code' => self::H_CODE_UNAUTHORIZED,
                'error_msg' => $this->_pt('Not authorized.'),
            ];
        }

        // We don't use api keys
        $this->api_flow_value('api_key_data', false);
        $this->api_flow_value('api_key_user_id', 0);

        if (!empty($account_arr)) {
            $this->api_flow_value('api_account_data', $account_arr);
        } else {
            $this->api_flow_value('api_account_data', false);
        }

        if (!empty($online_arr)) {
            $this->api_flow_value('api_session_data', $online_arr);
        } else {
            $this->api_flow_value('api_session_data', false);
        }

        return null;
    }

    protected function _basic_api_authentication_failed() : ?array
    {
        if (!($api_user = $this->api_flow_value('api_user'))
         || null === ($api_pass = $this->api_flow_value('api_pass'))) {
            return [
                'http_code' => self::H_CODE_UNAUTHORIZED,
                'error_msg' => $this->_pt('Please provide credentials'),
            ];
        }

        if (!($apikey_details = $this->get_apikey_by_apikey($api_user))
         || !($apikey_arr = ($apikey_details['apikey'] ?? null))
         || (string)$apikey_arr['api_secret'] !== (string)$api_pass) {
            return [
                'http_code' => self::H_CODE_UNAUTHORIZED,
                'error_msg' => $this->_pt('Not authorized.'),
            ];
        }
        $account_arr = ($apikey_details['account'] ?? null);

        if (empty($apikey_arr['allow_sw'])
         && $this->is_web_simulation()) {
            PHS_Logger::warning('Web simulation not allowed (#'.$apikey_arr['id'].').', PHS_Logger::TYPE_API);

            return [
                'http_code' => self::H_CODE_FORBIDDEN,
                'error_msg' => $this->_pt('Access not allowed.'),
            ];
        }

        $http_method = $this->http_method();

        if (!empty($apikey_arr['allowed_methods'])
         && !in_array($http_method, self::extract_strings_from_comma_separated($apikey_arr['allowed_methods'], ['to_lowercase' => true]), true)) {
            PHS_Logger::warning('Method not allowed (#'.$apikey_arr['id'].', '.$http_method.').', PHS_Logger::TYPE_API);

            return [
                'http_code' => self::H_CODE_METHOD_NOT_ALLOWED,
                'error_msg' => $this->_pt('Access not allowed.'),
            ];
        }

        if (!empty($apikey_arr['denied_methods'])
         && (empty($http_method)
             || in_array($http_method, self::extract_strings_from_comma_separated($apikey_arr['denied_methods'], ['to_lowercase' => true]), true)
         )) {
            PHS_Logger::warning('Method denied (#'.$apikey_arr['id'].', '.$http_method.').', PHS_Logger::TYPE_API);

            return [
                'http_code' => self::H_CODE_METHOD_NOT_ALLOWED,
                'error_msg' => $this->_pt('Access not allowed.'),
            ];
        }

        $request_ip = request_ip();
        if (!empty($apikey_arr['allowed_ips'])
         && !in_array($request_ip, self::extract_strings_from_comma_separated($apikey_arr['allowed_ips'], ['to_lowercase' => true]), true)) {
            PHS_Logger::warning('IP denied (#'.$apikey_arr['id'].', '.$request_ip.').', PHS_Logger::TYPE_API);

            return [
                'http_code' => self::H_CODE_FORBIDDEN,
                'error_msg' => $this->_pt('Access not allowed.'),
            ];
        }

        $this->api_flow_value('api_key_data', $apikey_arr);
        if (!empty($account_arr)) {
            $this->api_flow_value('api_key_user_id', (int)$account_arr['id']);
            $this->api_flow_value('api_account_data', $account_arr);
        } else {
            $this->api_flow_value('api_key_user_id', 0);
            $this->api_flow_value('api_account_data', false);
        }

        return null;
    }

    protected function _set_basic_api_credentials(?array $credentials_arr = null) : ?array
    {
        if (empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW'])) {
            $authorization_keys = ['AUTHORIZATION', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'];
            foreach ($authorization_keys as $key) {
                if (!empty($_SERVER[$key])
                    && stripos($_SERVER[$key], 'basic') === 0
                    && ($auth_arr = explode(':', @base64_decode(trim(substr($_SERVER[$key], 6)))))
                    && count($auth_arr) === 2) {
                    $_SERVER['PHP_AUTH_USER'] = $auth_arr[0];
                    $_SERVER['PHP_AUTH_PW'] = $auth_arr[1];
                    break;
                }
            }
        }

        if (empty($credentials_arr)) {
            $credentials_arr = [];
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $credentials_arr['api_user'] = $_SERVER['PHP_AUTH_USER'];
        }

        if (isset($_SERVER['PHP_AUTH_PW'])) {
            $credentials_arr['api_pass'] = $_SERVER['PHP_AUTH_PW'];
        }

        return $credentials_arr;
    }

    protected function _set_bearer_token_api_credentials(?array $credentials_arr = null) : ?array
    {
        $header_token = '';
        if (empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW'])) {
            $authorization_keys = ['AUTHORIZATION', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'];
            foreach ($authorization_keys as $key) {
                if (!empty($_SERVER[$key])
                    && stripos($_SERVER[$key], 'bearer') === 0) {
                    $header_token = trim(substr($_SERVER[$key], 7));
                    break;
                }
            }
        }

        if (empty($credentials_arr)) {
            $credentials_arr = [];
        }

        if ($header_token !== '') {
            $credentials_arr['bearer_token'] = $header_token;
        }

        return $credentials_arr;
    }

    /**
     * Override this method in case you want special code to be run before running actual action
     *
     * @return bool Return true to continue running or false and set an error in case running action should stop
     */
    protected function _before_route_run() : bool
    {
        return true;
    }

    /**
     * Override this method in case you want special code to be run after running actual action
     *
     * @return bool Return true to continue running or false and set an error in case flow should stop
     */
    protected function _after_route_run() : bool
    {
        return true;
    }

    protected function _default_api_flow() : array
    {
        return [
            'die_when_needed' => true,

            'response_headers'     => [],
            'raw_response_headers' => [],
            'response_body'        => '',
            'response_array'       => [],

            'http_protocol' => 'HTTP/1.1',
            'api_method'    => 'get',
            'content_type'  => 'application/json',

            'original_api_route_tokens' => false,
            'final_api_route_tokens'    => false,
            'phs_route'                 => null,
            'api_route'                 => null,

            // Remote domain record
            'remote_domain' => false,
            // Remote domain request message
            'remote_domain_message' => false,

            // Values used in HTTP Authorization header (not necessary a user and password in the system)
            'api_user' => '',
            'api_pass' => '',

            // In case we have a Bearer token Autorization header
            'bearer_token' => '',

            // Any information related to API Key used in the request (any API implementation will use this as required)
            'api_key_data' => false,
            // In case API key wants to consider this request authenticated as a specific user (from users table), put users.id value here...
            'api_key_user_id' => 0,

            // User under which API actions are taken
            'api_account_data' => false,
            // Session, from online table, (if any created)
            'api_session_data' => false,
        ];
    }

    /**
     * @param null|array $args Arguments which must be added in query string (other than predefined ones)
     * @param null|array $extra Call parameters
     *
     * @return array Arguments to be added to query string of API URL
     */
    protected function _get_predefined_api_url_params(?array $args = null, ?array $extra = null) : array
    {
        if (empty($args) || !is_array($args)) {
            $args = [];
        }

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        $extra['include_version'] = (!isset($extra['include_version']) || !empty($extra['include_version']));

        if (!empty($this->raw_query_params)) {
            foreach ($this->raw_query_params as $key => $val) {
                // rewrite parameter is set in rewrite rule...
                // put in parameters parsed value
                if ($key === self::PARAM_USING_REWRITE
                 || $key === self::PARAM_API_ROUTE
                 || !isset($this->init_query_params[$key])
                 || ($key === self::PARAM_VERSION && empty($extra['include_version']))) {
                    continue;
                }

                $args[$key] = $this->init_query_params[$key];
            }
        }

        return $args;
    }

    private function _special_flow_keys() : array
    {
        return ['api_method', 'http_protocol', 'content_type', 'response_headers', 'raw_response_headers'];
    }

    public static function get_default_authentication_methods() : array
    {
        return [self::AUTH_METHOD_BASIC, self::AUTH_METHOD_BEARER, ];
    }

    public static function prepare_api_route_string(string $route_str) : string
    {
        return trim($route_str, '/- ');
    }

    /**
     * @param string $method
     *
     * @return string
     */
    public static function prepare_http_method(string $method) : string
    {
        return strtolower(trim($method));
    }

    /**
     * @return array{api_obj: null|\phs\PHS_Api_base, api_route: null|array, phs_route: null|array}
     */
    public static function default_api_authentication_callback_params() : array
    {
        return [
            'api_obj'   => null,
            'api_route' => null,
            'phs_route' => null,
        ];
    }

    /**
     * @return array{api_obj: null|\phs\PHS_Api_base, api_route: null|array, phs_route: null|array}
     */
    public static function default_api_authentication_callback_response() : array
    {
        return [
            'api_obj'   => null,
            'api_route' => null,
            'phs_route' => null,
        ];
    }

    /**
     * @return array{"allow_api_calls": bool, "allow_api_calls_over_http": bool,
     *      "api_can_simulate_web": bool, "allow_bearer_token_authentication": bool}
     */
    public static function default_framework_api_settings() : array
    {
        return [
            'allow_api_calls'                   => false,
            'allow_api_calls_over_http'         => false,
            'api_can_simulate_web'              => false,
            'allow_bearer_token_authentication' => false,
        ];
    }

    /**
     * @return array
     */
    public static function get_framework_api_settings() : array
    {
        if (!empty(self::$_framework_settings)) {
            return self::$_framework_settings;
        }

        self::$_framework_settings = self::default_framework_api_settings();

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        if (!($admin_plugin = PHS::load_plugin('admin'))
         || !($admin_plugin_settings = $admin_plugin->get_plugin_settings())) {
            return self::$_framework_settings;
        }

        self::$_framework_settings['allow_api_calls'] = (!empty($admin_plugin_settings['allow_api_calls']));
        self::$_framework_settings['allow_api_calls_over_http'] = (!empty($admin_plugin_settings['allow_api_calls_over_http']));
        self::$_framework_settings['api_can_simulate_web'] = (!empty($admin_plugin_settings['api_can_simulate_web']));
        self::$_framework_settings['allow_bearer_token_authentication'] = (!empty($admin_plugin_settings['allow_bearer_token_authentication']));

        return self::$_framework_settings;
    }

    /**
     * @return bool
     */
    public static function framework_allows_api_calls() : bool
    {
        return ($settings = self::get_framework_api_settings()) && !empty($settings['allow_api_calls']);
    }

    /**
     * @return bool
     */
    public static function framework_allows_api_calls_over_http() : bool
    {
        return ($settings = self::get_framework_api_settings()) && !empty($settings['allow_api_calls_over_http']);
    }

    /**
     * @return bool
     */
    public static function framework_api_can_simulate_web() : bool
    {
        return ($settings = self::get_framework_api_settings()) && !empty($settings['api_can_simulate_web']);
    }

    /**
     * @return bool
     */
    public static function framework_allow_bearer_token_authentication() : bool
    {
        return ($settings = self::get_framework_api_settings()) && !empty($settings['allow_bearer_token_authentication']);
    }

    /**
     * @return array
     */
    public static function get_request_body_as_json_array() : array
    {
        static $json_arr = null;

        if ($json_arr !== null) {
            return $json_arr;
        }

        if (!($request_body = PHS_Api::get_php_input())
         || !($json_arr = @json_decode($request_body, true))) {
            return [];
        }

        return $json_arr;
    }

    /**
     * @return false|string
     */
    public static function get_php_input() : ?string
    {
        static $input = null;

        if ($input !== null) {
            return $input;
        }

        if (($input = @file_get_contents('php://input')) === false) {
            return null;
        }

        return $input;
    }

    /**
     * @param null|string $msg
     *
     * @return bool
     */
    public static function generic_error(?string $msg = null) : bool
    {
        return self::http_header_response(self::GENERIC_ERROR_CODE, $msg);
    }

    /**
     * @param int $code
     * @param null|string $msg
     * @param null|string $protocol
     *
     * @return bool
     */
    public static function http_header_response(int $code, ?string $msg = null, ?string $protocol = null) : bool
    {
        if (@headers_sent()) {
            return false;
        }

        if (empty($code)) {
            $code = self::GENERIC_OK_CODE;
        }

        if (empty($msg)
        && !($msg = self::valid_http_code($code))) {
            $msg = '';
        }

        if (empty($protocol)) {
            $protocol = 'HTTP/1.1';
        }

        @header($protocol.' '.$code.' '.trim($msg));

        return true;
    }

    /**
     * @param int $code
     *
     * @return null|string
     */
    public static function valid_http_code(int $code) : ?string
    {
        if (!($all_codes = self::http_response_codes())
         || empty($all_codes[$code])) {
            return null;
        }

        return $all_codes[$code];
    }

    /**
     * @return array<int, string>
     */
    public static function http_response_codes() : array
    {
        return [
            0 => 'Host not found / Timed out',

            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing (WebDAV)',
            103 => 'Checkpoint',

            self::H_CODE_OK            => 'OK',
            self::H_CODE_OK_CREATED    => 'Created',
            self::H_CODE_OK_ACCEPTED   => 'Accepted',
            203                        => 'Non-Authoritative Information',
            self::H_CODE_OK_NO_CONTENT => 'No Content',
            205                        => 'Reset Content',
            206                        => 'Partial Content',
            207                        => 'Multi-Status', // (WebDAV)
            208                        => 'Already Reported', // (WebDAV)
            218                        => 'This is fine',
            226                        => 'IM Used',

            300                             => 'Multiple Choices',
            self::H_CODE_MOVED_PERMANENTLY  => 'Moved Permanently',
            302                             => 'Found',
            303                             => 'See Other',
            self::H_CODE_NOT_MODIFIED       => 'Not Modified',
            305                             => 'Use Proxy',
            306                             => '(Unused)',
            self::H_CODE_TEMPORARY_REDIRECT => 'Temporary Redirect',
            self::H_CODE_PERMANENT_REDIRECT => 'Permanent Redirect', // Redirect while keeping requerst method

            self::H_CODE_BAD_REQUEST            => 'Bad Request',
            self::H_CODE_UNAUTHORIZED           => 'Unauthorized',
            402                                 => 'Payment Required',
            self::H_CODE_FORBIDDEN              => 'Forbidden',
            self::H_CODE_NOT_FOUND              => 'Not Found',
            self::H_CODE_METHOD_NOT_ALLOWED     => 'Method Not Allowed',
            self::H_CODE_NOT_ACCEPTABLE         => 'Not Acceptable',
            407                                 => 'Proxy Authentication Required',
            408                                 => 'Request Timeout',
            self::H_CODE_CONFLICT               => 'Conflict',
            410                                 => 'Gone',
            411                                 => 'Length Required',
            412                                 => 'Precondition Failed',
            413                                 => 'Payload Too Large',
            414                                 => 'URI Too Long',
            self::H_CODE_UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
            416                                 => 'Range Not Satisfiable',
            417                                 => 'Expectation Failed',
            418                                 => 'I\'m a teapot', // (RFC 2324)
            419                                 => 'Page Expired', // (Laravel Framework)
            420                                 => 'Enhance Your Calm', // (Twitter)
            421                                 => 'Misdirected Request',
            422                                 => 'Unprocessable Entity', // (WebDAV)
            423                                 => 'Locked', // (WebDAV)
            424                                 => 'Failed Dependency', // (WebDAV)
            425                                 => 'Too Early', // (WebDAV)
            426                                 => 'Upgrade Required',
            428                                 => 'Precondition Required',
            self::H_CODE_TOO_MANY_REQUESTS      => 'Too Many Requests',
            431                                 => 'Request Header Fields Too Large',
            444                                 => 'No Response', // (Nginx)
            449                                 => 'Retry With', // (Microsoft)
            450                                 => 'Blocked by Windows Parental Controls', // (Microsoft)
            451                                 => 'Unavailable For Legal Reasons',
            499                                 => 'Client Closed Request (Nginx)',

            self::H_CODE_INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::H_CODE_NOT_IMPLEMENTED       => 'Not Implemented',
            self::H_CODE_BAD_GATEWAY           => 'Bad Gateway',
            self::H_CODE_SERVICE_UNAVAILABLE   => 'Service Unavailable',
            self::H_CODE_GATEWAY_TIMEOUT       => 'Gateway Timeout',
            505                                => 'HTTP Version Not Supported',
            506                                => 'Variant Also Negotiates',
            self::H_CODE_INSUFFICIENT_STORAGE  => 'Insufficient Storage', // (WebDAV)
            508                                => 'Loop Detected', // (WebDAV)
            509                                => 'Bandwidth Limit Exceeded', // (Apache)
            510                                => 'Not Extended',
            511                                => 'Network Authentication Required',
            598                                => 'Network read timeout error',
            599                                => 'Network connect timeout error',
        ];
    }
}
