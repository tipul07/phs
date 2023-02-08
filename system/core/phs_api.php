<?php
namespace phs;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;

// ! @version 1.00

class PHS_Api extends PHS_Api_base
{
    public const ERR_API_INIT = 40000, ERR_API_ROUTE = 40001;

    /** @var array */
    private static array $_api_routes = [];

    // Last API instance obtained with self::api_factory()
    /** @var null|\phs\PHS_Api_base */
    private static ?PHS_Api_base $_last_api_obj = null;

    // THE API instance that should respond to current request
    /** @var null|\phs\PHS_Api_base */
    private static ?PHS_Api_base $_global_api_obj = null;

    public function __construct(?array $init_query_params = null)
    {
        parent::__construct();

        if ($init_query_params !== null
         && !($this->_init_api_query_params($init_query_params))
         && !$this->has_error()) {
            $this->set_error(self::ERR_API_INIT, self::_t('Couldn\'t initialize API object.'));
        }

        // We don't update self::$_last_api_obj or self::$_global_api_obj as we explicitly asked for this instance
    }

    /**
     * @inheritdoc
     */
    final public function run_route(array $extra = [])
    {
        $this->reset_error();

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_API)) {
            $this->set_error(self::ERR_RUN_ROUTE, self::_t('Error preparing API environment.'));

            return false;
        }

        if (null === ($final_api_route_tokens = self::tokenize_api_route($this->get_api_route()))) {
            $this->set_error(self::ERR_RUN_ROUTE, self::_t('Couldn\'t parse provided API route.'));

            return false;
        }

        if (PHS::st_debugging_mode()) {
            PHS_Logger::debug('Request API route tokens ['.implode('/', $final_api_route_tokens).']',
                PHS_Logger::TYPE_API);
        }

        $this->api_flow_value('original_api_route_tokens', $final_api_route_tokens);

        // Let plugins change provided API route tokens
        $hook_args = PHS_Hooks::default_api_hook_args();
        $hook_args['api_obj'] = $this;
        $hook_args['api_route_tokens'] = $final_api_route_tokens;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_API_ROUTE, $hook_args))
         && is_array($hook_args)
         && !empty($hook_args['altered_api_route_tokens']) && is_array($hook_args['altered_api_route_tokens'])
         && !($final_api_route_tokens = self::_validate_tokenized_api_route($hook_args['altered_api_route_tokens']))) {
            $this->set_error(self::ERR_RUN_ROUTE, self::_t('Invalid API route tokens obtained from plugins.'));

            return false;
        }

        $this->api_flow_value('final_api_route_tokens', $final_api_route_tokens);

        if (PHS::st_debugging_mode()) {
            PHS_Logger::debug('Final API route tokens ['.implode('/', $final_api_route_tokens).']',
                PHS_Logger::TYPE_API);
        }

        $api_route = null;
        if (($matched_route = self::_get_phs_route_from_api_route($final_api_route_tokens, $this->http_method()))) {
            $phs_route = $matched_route['phs_route'];
            $api_route = $matched_route['api_route'];
        } else {
            if (PHS::st_debugging_mode()) {
                PHS_Logger::debug('No defined API route matched request.', PHS_Logger::TYPE_API);
            }

            if (!($phs_route = PHS::parse_route(implode('/', $final_api_route_tokens), true))) {
                if (self::st_has_error()) {
                    $this->copy_static_error(self::ERR_RUN_ROUTE);
                } else {
                    $this->set_error(self::ERR_RUN_ROUTE,
                        self::_t('Couldn\'t parse provided API route into a framework route.'));
                }

                return false;
            }
        }

        $phs_route = PHS::validate_route_from_parts($phs_route, true);

        if (PHS::st_debugging_mode()) {
            if (!($route_str = PHS::route_from_parts($phs_route))) {
                $route_str = 'N/A';
            }

            PHS_Logger::debug('Resulting PHS route ['.$route_str.']', PHS_Logger::TYPE_API);
        }

        $this->api_flow_value('phs_route', $phs_route);
        $this->api_flow_value('api_route', $api_route);

        PHS::set_route($phs_route);

        if (!empty($api_route['authentication_methods'])) {
            $this->allowed_authentication_methods($api_route['authentication_methods']);
        }

        if (($authentication_failed = $this->_api_route_authentication_failed($api_route, $phs_route))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_RUN_ROUTE, self::_t('Authentication failed.'));
            }

            if (!$this->send_header_response(
                $authentication_failed['http_code'] ?? self::H_CODE_UNAUTHORIZED,
                $authentication_failed['error_msg'] ?? self::_t('Authentication failed.')
            )) {
                return false;
            }

            exit;
        }

        if (!$this->_before_route_run()) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_RUN_ROUTE, self::_t('Running action was stopped by API instance.'));
            }

            return false;
        }

        $execution_params = [];
        $execution_params['die_on_error'] = false;

        if (!($action_result = PHS::execute_route($execution_params))) {
            if (self::st_has_error()) {
                $this->copy_static_error(self::ERR_RUN_ROUTE);
            } else {
                $this->set_error(self::ERR_RUN_ROUTE,
                    self::_t('Error executing route [%s].', PHS::get_route_as_string()));
            }

            return false;
        }

        if (!$this->_after_route_run()) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_RUN_ROUTE, self::_t('Flow was stopped by API instance after action run.'));
            }

            return false;
        }

        return $action_result;
    }

    /**
     * @inheritdoc
     */
    public function create_response_envelope(array $response_arr, ?array $errors_arr = null) : ?array
    {
        return $this->default_response_envelope($response_arr, $errors_arr);
    }

    protected function _before_route_run() : bool
    {
        if ($this->is_web_simulation()) {
            PHS_Scope::emulated_scope(PHS_Scope::SCOPE_WEB);

            if (($request_body = $this::get_php_input())
             && ($json_arr = @json_decode($request_body, true))) {
                // In case we run in an environment where $_POST is not defined
                global $_POST;

                if (empty($_POST) || !is_array($_POST)) {
                    $_POST = [];
                }

                foreach ($json_arr as $key => $val) {
                    $_POST[$key] = $val;
                }
            }
        }

        return true;
    }

    protected function _after_route_run() : bool
    {
        if ($this->is_web_simulation()) {
            PHS_Scope::emulated_scope(0);
        }

        return true;
    }

    /**
     * Check if provided API route passes authentication.
     * RETURNS null IF AUTHENTICATION IS OK
     * An array containing errors if authentication fails...
     *
     * @param null|array $api_route
     * @param null|array $phs_route
     *
     * @return ?array
     */
    protected function _api_route_authentication_failed(?array $api_route, ?array $phs_route) : ?array
    {
        $this->reset_error();

        if (empty($api_route['authentication_required'])) {
            if (PHS::st_debugging_mode()) {
                PHS_Logger::debug('Authentication not required!', PHS_Logger::TYPE_API);
            }

            return null;
        }

        // Check if we should have authentication...
        // If we didn't find an API route, we found a "standard" route to be run which requires authentication
        // If we have a matching API route check if API route requires authentication, custom authentication or no authentication at all
        $no_authentication_callback = (empty($api_route['authentication_callback'])
                                       && (empty($api_route['authentication_callback_cascade'])
                                           || !is_array($api_route['authentication_callback_cascade'])));

        if (empty($api_route) || $no_authentication_callback) {
            if (($authentication_failed = $this->_api_authentication_failed())) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_RUN_ROUTE, self::_t('Authentication failed.'));
                }

                return $authentication_failed;
            }

            return null;
        }

        if (empty($api_route['authentication_callback_cascade'])
           || !is_array($api_route['authentication_callback_cascade'])) {
            $api_route['authentication_callback_cascade'] = [];
        }

        if (!empty($api_route['authentication_callback'])) {
            array_unshift($api_route['authentication_callback_cascade'],
                $api_route['authentication_callback']);
        }

        $route_str = '';
        foreach ($api_route['authentication_callback_cascade'] as $auth_callback) {
            if (!@is_callable($auth_callback)) {
                if ($route_str === ''
                 && !($route_str = PHS::route_from_parts($phs_route))) {
                    $route_str = 'N/A';
                }

                PHS_Logger::error('Bad API authentication callback for route '
                                  .'['.($api_route['name'] ?? 'N/A').'] - '.$route_str, PHS_Logger::TYPE_API);

                continue;
            }

            $callback_params = self::default_api_authentication_callback_params();
            $callback_params['api_obj'] = $this;
            $callback_params['api_route'] = $api_route;
            $callback_params['phs_route'] = $phs_route;

            if (($result = @$auth_callback($callback_params)) === null
                || $result === false) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_AUTHENTICATION, self::_t('Authentication failed.'));
                }

                return [
                    'http_code' => self::H_CODE_UNAUTHORIZED,
                    'error_msg' => $this->get_simple_error_message(),
                ];
            }
        }

        return null;
    }

    final public static function api_factory(?array $init_query_params = null)
    {
        self::st_reset_error();

        $api_obj = null;

        // Tell plugins we are starting an API request and check if any of them has an API object to offer
        $hook_args = PHS_Hooks::default_api_hook_args();
        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_API_REQUEST_INIT, $hook_args))
         && is_array($hook_args)
         && !empty($hook_args['api_obj'])
         && ($api_obj = $hook_args['api_obj'])
         && !($api_obj instanceof PHS_Api_base)) {
            self::st_set_error(self::ERR_API_INIT, self::_t('Invalid API instance obtained from hook call.'));

            return false;
        }

        // If we don't have an instance provided by hook result, instantiate default API class
        if (empty($api_obj)
         && !($api_obj = new self())) {
            self::st_set_error(self::ERR_API_INIT, self::_t('Error obtaining API instance.'));

            return false;
        }

        if (!($api_obj->_init_api_query_params($init_query_params))) {
            if ($api_obj->has_error()) {
                self::st_copy_error($api_obj);
            } else {
                self::st_set_error(self::ERR_API_INIT, self::_t('Couldn\'t initialize API object.'));
            }

            return false;
        }

        self::$_last_api_obj = $api_obj;
        if (empty(self::$_global_api_obj)) {
            self::$_global_api_obj = $api_obj;
        }

        return $api_obj;
    }

    public static function get_api_routes() : array
    {
        return self::$_api_routes;
    }

    public static function tokenize_api_route($route_str) : ?array
    {
        if (!is_string($route_str)) {
            return null;
        }

        $route_parts = explode('/', trim(trim($route_str), '/'));
        $route_tokens = [];
        foreach ($route_parts as $part) {
            // Allow empty API paths (empty string)
            $part = trim($part);
            if (!empty($route_tokens)
             && $part === '') {
                continue;
            }

            $route_tokens[] = $part;
        }

        return $route_tokens;
    }

    public static function default_api_route_node() : array
    {
        return [
            'exact_match'       => '', // spare a regexp check if we want something static
            'regexp'            => '',
            'regexp_modifiers'  => '', // provide
            'insensitive_match' => true, // case-insensitive match on exact_match or regexp

            // in case this node is dynamic we should check if it's value respects the type, see if we should consider this
            // as parameter in action and where to move it (if required): in get or post
            'type'           => PHS_Params::T_ASIS,
            'extra_type'     => false,
            'default'        => null,
            'var_name'       => '', // in case we should move this from route to _GET or _POST, how should we call this variable
            'append_to_get'  => false,
            'append_to_post' => false,

            // for documentation / errors
            'name'        => '',
            'description' => '',
        ];
    }

    public static function default_api_route_structure() : array
    {
        $route_structure = self::default_api_route_params();
        $route_structure['api_route'] = [];
        $route_structure['phs_route'] = [];

        return $route_structure;
    }

    public static function default_api_route_params() : array
    {
        return [
            'method' => 'get',
            // these are useful when creating aliases for common requests
            // e.g. /companies/get_latest_20 will change to list companies action with sort descending on creation date, offset 0 and limit 20
            // this means you will add filtering and sorting in get_params or post_params as required
            'get_params'  => [],
            'post_params' => [],

            // If API route doesn't require authentication to run put this to false
            'authentication_required' => true,
            // If API route requires special API authentication you can define here what method/function to call to do the authentication
            // Method receives as parameters an array (like PHS_Api_base::default_api_authentication_callback_params()) and should return false
            // in case authentication failed, or it can safetly send headers back to browser and exit directly
            // !!! If authetication passes it MUST return true
            'authentication_callback' => false,
            // Provide an array of authentication methods which will be called in the order presented in this array
            // If authentication_callback is provided, that callback is called first
            // Custom error can be set using {$param['api_obj']}->set_error().
            // This will be sent in header as response. Don't put a long string here...
            'authentication_callback_cascade' => [],
            // This is an array which specifies what default authentication methods to be used (basic or bearer)
            // null means default ones
            // @see self::AUTH_METHOD_BASIC, self::AUTH_METHOD_BEARER
            'authentication_methods' => null,

            // for documentation / errors
            'name'        => '',
            'description' => '',
        ];
    }

    public static function normalize_api_route_api_nodes($api_route_nodes) : array
    {
        if (empty($api_route_nodes) || !is_array($api_route_nodes)) {
            return [];
        }

        $new_api_route_nodes = [];
        $default_node = self::default_api_route_node();
        foreach ($api_route_nodes as $route_node) {
            $new_api_route_nodes[] = self::validate_array($route_node, $default_node);
        }

        return $new_api_route_nodes;
    }

    /**
     * @param array $api_route_parts An array of tokens to be matched agains an API route (exploding route on /)
     * @param array $phs_route A PHS route using short names for plugin, controller and action (p, c and a)
     * @param null|array $route_params Route parameters (@see self::default_api_route_params())
     *
     * @return bool true on success or false on error
     */
    public static function register_api_route(array $api_route_parts, array $phs_route, ?array $route_params = null) : bool
    {
        self::st_reset_error();

        $route_params = self::validate_array($route_params, self::default_api_route_params());

        if (!($method = self::prepare_http_method($route_params['method']))) {
            self::st_set_error(self::ERR_API_ROUTE, self::_t('Please provide a valid API method.'));

            return false;
        }

        $route_params['method'] = $method;

        if (!empty($phs_route)) {
            if (!empty($phs_route['ad'])) {
                $phs_route['ad'] = PHS::validate_action_dir_in_url($phs_route['ad']);
            }
            if (!empty($phs_route['action_dir'])) {
                $phs_route['action_dir'] = PHS::validate_action_dir_in_url($phs_route['action_dir']);
            }
        }

        if (empty($phs_route)
         || !($phs_route = PHS::parse_route($phs_route, true))) {
            self::st_set_error(self::ERR_API_ROUTE, self::_t('Couldn\'t parse provided PHS route for API calls.'));

            return false;
        }

        $api_route = self::merge_array_assoc(self::default_api_route_structure(), $route_params);
        $api_route['api_route'] = $api_route_parts;
        $api_route['phs_route'] = $phs_route;

        $api_route = self::_normalize_api_route($api_route);

        self::$_api_routes[] = $api_route;

        return true;
    }

    /**
     * @param null|PHS_Api_base $api_obj API instance to be set as request API instance
     *
     * @return null|PHS_Api_base Return request API instance or false if none set
     */
    public static function global_api_instance(?PHS_Api_base $api_obj = null) : ?PHS_Api_base
    {
        self::st_reset_error();

        if ($api_obj === null) {
            return self::$_global_api_obj;
        }

        if (!is_object($api_obj)
         || !($api_obj instanceof PHS_Api_base)) {
            self::st_set_error(self::ERR_API_INIT, self::_t('Invalid API instance.'));

            return null;
        }

        self::$_global_api_obj = $api_obj;

        return null;
    }

    /**
     * @return null|PHS_Api_base Return request API instance or false if none set
     */
    public static function last_api_instance() : ?PHS_Api_base
    {
        return self::$_last_api_obj;
    }

    protected static function _validate_tokenized_api_route(array $route_arr) : ?array
    {
        $validated_route = [];
        foreach ($route_arr as $part) {
            if (!is_string($part)) {
                return null;
            }

            $validated_route[] = trim(trim($part), '/');
        }

        return $validated_route;
    }

    /**
     * @param array $api_route Defined API route to be checked against route from request ($tokenized_request_route)
     * @param null|array $tokenized_request_route a tokenized API route from request
     * @param string $method Requested HTTP method
     * @param bool $skip_validations true if validation of parameters should be skipped (if already validated)
     *
     * @return null|array Return false if provided $api_route doesn't march $tokenized_request_route
     */
    protected static function _check_route_for_tokenized_api_route(
        array $api_route, ?array $tokenized_request_route = null,
        string $method = 'get', bool $skip_validations = false) : ?array
    {
        if (empty($skip_validations)
         && (!($api_route = self::_normalize_api_route($api_route))
             || !$tokenized_request_route
             || !($tokenized_request_route = self::_validate_tokenized_api_route($tokenized_request_route))
         )) {
            return null;
        }

        // First check if we have a good method...
        if (empty($api_route['method'])
         || !($method = self::prepare_http_method($method))
         || $api_route['method'] !== $method) {
            return null;
        }

        $api_route_tokens_count = (empty($api_route['api_route']) ? 0 : count($api_route['api_route']));
        $request_route_tokens_count = count($tokenized_request_route);

        if ($api_route_tokens_count !== $request_route_tokens_count) {
            return null;
        }

        $knti = 0;
        $append_to_get = (!empty($api_route['get_params']) && is_array($api_route['get_params'])) ? $api_route['get_params'] : [];
        $append_to_post = (!empty($api_route['post_params']) && is_array($api_route['post_params'])) ? $api_route['post_params'] : [];
        while ($knti < $api_route_tokens_count) {
            $api_element = $api_route['api_route'][$knti];
            $request_token = $tokenized_request_route[$knti];

            $knti++;

            if ($api_element['exact_match'] === ''
             && empty($api_element['regexp'])) {
                return null;
            }

            if ($api_element['exact_match'] !== '') {
                if (!empty($api_element['insensitive_match'])) {
                    $exact_match = strtolower($api_element['exact_match']);
                    $check_token = strtolower($request_token);
                } else {
                    $exact_match = $api_element['exact_match'];
                    $check_token = $request_token;
                }

                if ($exact_match !== $check_token) {
                    return null;
                }
            } elseif (!empty($api_element['regexp'])) {
                $modifiers = '';
                if (!empty($api_element['regexp_modifiers'])) {
                    $modifiers .= trim($api_element['regexp_modifiers']);
                }
                if (!empty($api_element['insensitive_match'])) {
                    $modifiers .= 'i';
                }

                if (!@preg_match('/'.$api_element['regexp'].'/'.$modifiers, $request_token)) {
                    return null;
                }
            }

            if (!empty($api_element['append_to_get'])
             || !empty($api_element['append_to_post'])) {
                if (empty($api_element['var_name'])) {
                    return null;
                }

                if (null === ($el_value = PHS_Params::set_type($request_token, $api_element['type'], $api_element['extra_type']))) {
                    $el_value = $api_element['default'];
                }

                if (!empty($api_element['append_to_get'])) {
                    $append_to_get[$api_element['var_name']] = $el_value;
                } elseif (!empty($api_element['append_to_post'])) {
                    $append_to_post[$api_element['var_name']] = $el_value;
                }
            }
        }

        // safe
        if (empty($api_route['phs_route'])) {
            return null;
        }

        if (!empty($append_to_get)) {
            if (empty($_GET) || !is_array($_GET)) {
                $_GET = [];
            }

            foreach ($append_to_get as $key => $val) {
                $_GET[$key] = $val;
            }
        }

        if (!empty($append_to_post)) {
            if (empty($_POST) || !is_array($_POST)) {
                $_POST = [];
            }

            foreach ($append_to_post as $key => $val) {
                $_POST[$key] = $val;
            }
        }

        return $api_route['phs_route'];
    }

    /**
     * @param null|array $tokenized_api_route A tokenized API route
     * @param string $method Method used in request (eg. get, post, delete, etc)
     *
     * @return null|array
     */
    protected static function _get_phs_route_from_api_route(?array $tokenized_api_route = null, string $method = 'get') : ?array
    {
        self::st_reset_error();

        if (empty(self::$_api_routes)) {
            return null;
        }

        if (!($method = self::prepare_http_method($method))) {
            self::st_set_error(self::ERR_API_ROUTE, self::_t('Please provide a valid API method.'));

            return null;
        }

        if (!$tokenized_api_route
         || !($tokenized_api_route = self::_validate_tokenized_api_route($tokenized_api_route))) {
            self::st_set_error(self::ERR_API_ROUTE, self::_t('Provided API route failed validation.'));

            return null;
        }

        foreach (self::$_api_routes as $api_route) {
            if (($phs_route = self::_check_route_for_tokenized_api_route($api_route, $tokenized_api_route, $method, true))) {
                return [
                    'phs_route' => $phs_route,
                    'api_route' => $api_route,
                ];
            }
        }

        return null;
    }

    protected static function _normalize_api_route($api_route) : array
    {
        $default_api_route_structure = self::default_api_route_structure();
        if (empty($api_route) || !is_array($api_route)) {
            return $default_api_route_structure;
        }

        $api_route = self::validate_array($api_route, $default_api_route_structure);
        $api_route['api_route'] = self::normalize_api_route_api_nodes($api_route['api_route']);
        $api_route['phs_route'] = PHS::validate_route_from_parts($api_route['phs_route'], true);

        return $api_route;
    }
}
