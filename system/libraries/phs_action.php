<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Scope;
use phs\system\core\views\PHS_View;

abstract class PHS_Action extends PHS_Instantiable
{
    public const ERR_CONTROLLER_INSTANCE = 40000, ERR_RUN_ACTION = 40001, ERR_RENDER = 40002, ERR_SCOPE = 40003, ERR_RIGHTS = 40004;

    public const ACT_ROLE_PAGE = 'phs_page', ACT_ROLE_LOGIN = 'phs_login', ACT_ROLE_LOGOUT = 'phs_logout',
    ACT_ROLE_REGISTER = 'phs_register', ACT_ROLE_ACTIVATION = 'phs_activation', ACT_ROLE_CHANGE_PASSWORD = 'phs_change_password',
    ACT_ROLE_PASSWORD_EXPIRED = 'phs_password_expired', ACT_ROLE_FORGOT_PASSWORD = 'phs_forgot_password',
    ACT_ROLE_EDIT_PROFILE = 'phs_edit_profile', ACT_ROLE_CHANGE_LANGUAGE = 'phs_change_language',
    ACT_ROLE_REMOTE_PHS_CALL = 'phs_remote_phs_call',
    ACT_ROLE_TFA_SETUP = 'phs_tfa_setup', ACT_ROLE_TFA_VERIFY = 'phs_tfa_verify', ACT_ROLE_TFA_SETTINGS = 'phs_tfa_settings';

    /** @var null|PHS_Controller */
    private ?PHS_Controller $_controller_obj = null;

    /** @var null|array */
    private ?array $_action_result = null;

    private static array $_action_roles = [];

    private static array $_custom_action_roles = [];

    private static array $_builtin_action_roles = [
        self::ACT_ROLE_PAGE             => ['title' => 'Common Page'],
        self::ACT_ROLE_LOGIN            => ['title' => 'Login'],
        self::ACT_ROLE_LOGOUT           => ['title' => 'Logout'],
        self::ACT_ROLE_REGISTER         => ['title' => 'Register'],
        self::ACT_ROLE_ACTIVATION       => ['title' => 'Activation'],
        self::ACT_ROLE_CHANGE_PASSWORD  => ['title' => 'Change Password'],
        self::ACT_ROLE_PASSWORD_EXPIRED => ['title' => 'Password Expired'],
        self::ACT_ROLE_FORGOT_PASSWORD  => ['title' => 'Forgot Password'],
        self::ACT_ROLE_EDIT_PROFILE     => ['title' => 'Edit Profile'],
        self::ACT_ROLE_CHANGE_LANGUAGE  => ['title' => 'Change Language'],
        self::ACT_ROLE_REMOTE_PHS_CALL  => ['title' => 'Remote PHS Call'],
        self::ACT_ROLE_TFA_SETUP        => ['title' => 'Setup Two Factor Authentication'],
        self::ACT_ROLE_TFA_VERIFY       => ['title' => 'Verify Two Factor Authentication'],
        self::ACT_ROLE_TFA_SETTINGS     => ['title' => 'Two Factor Authentication Settings'],
    ];

    /**
     * @return bool|array Returns an array with action result or false on an error
     * @see PHS_Action::default_action_result()
     */
    abstract public function execute();

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return int[] If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [];
    }

    /**
     * @return array Returns roles that current action have
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_PAGE];
    }

    /**
     * Define an action role
     *
     * @param string $role_key
     * @param array $role_arr Role definition array
     *
     * @return array|bool
     */
    final public function define_action_role(string $role_key, array $role_arr)
    {
        $this->reset_error();

        if (($defined_role = self::valid_action_role($role_key))) {
            return $defined_role;
        }

        if (empty($role_key)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Role key should be a string.'));

            return false;
        }

        $role_arr = self::merge_array_assoc($role_arr, self::default_action_role_definition_array());

        self::$_custom_action_roles[$role_key] = $role_arr;

        self::$_action_roles = [];

        self::get_action_roles();

        return $role_arr;
    }

    /**
     * Checks if current action has provided role(s)
     *
     * @param string[]|string $role_check action role (string) or action roles (array of strings) to be checked
     * @param array|bool $params Method extra parameters
     *
     * @return array|bool Return false if provided roles are not for current action or a list of matching action roles
     */
    final public function action_role_is($role_check, $params = false)
    {
        if (!is_array($role_check)) {
            $role_check = [$role_check];
        }

        if (!($action_roles = $this->action_roles())) {
            $action_roles = [self::ACT_ROLE_PAGE];
        }

        if (!is_array($action_roles)) {
            $action_roles = [$action_roles];
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        // Action has all provided roles
        if (empty($params['all_provided'])) {
            $params['all_provided'] = false;
        } else {
            $params['all_provided'] = (!empty($params['all_provided']));
        }

        $return_arr = [];
        foreach ($role_check as $role_key) {
            if (($role_arr = self::valid_action_role($role_key))
             && in_array($role_key, $action_roles, true)) {
                $return_arr[$role_key] = $role_arr;
                continue;
            }

            // Role is not for current action
            if (!empty($params['all_provided'])) {
                return false;
            }
        }

        return empty($return_arr) ? false : $return_arr;
    }

    /**
     * @return string
     */
    final public function instance_type() : string
    {
        return self::INSTANCE_TYPE_ACTION;
    }

    /**
     * @param int $scope Scope to be checked
     *
     * @return bool Returns true if controller is allowed to run in provided scope
     */
    final public function scope_is_allowed(int $scope) : bool
    {
        $this->reset_error();

        if (!PHS_Scope::valid_scope($scope)) {
            $this->set_error(self::ERR_SCOPE, self::_t('Invalid scope.'));

            return false;
        }

        return !(($allowed_scopes = $this->allowed_scopes())
         && is_array($allowed_scopes)
         && !in_array($scope, $allowed_scopes, true));
    }

    final public function set_action_defaults() : void
    {
        $this->_action_result = self::default_action_result();
    }

    /**
     * @return null|array
     */
    final public function get_action_result() : ?array
    {
        return $this->_action_result;
    }

    /**
     * Sets action result for current action
     * @param array $result
     *
     * @return array
     */
    final public function set_action_result(array $result) : array
    {
        $this->_action_result = self::validate_action_result($result);

        return $this->_action_result;
    }

    final public function quick_render_template($template, $template_data = false)
    {
        $this->reset_error();

        $view_params = [];
        $view_params['action_obj'] = $this;
        $view_params['controller_obj'] = $this->get_controller();
        $view_params['parent_plugin_obj'] = $this->get_plugin_instance();
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = $template_data;

        if (!($view_obj = PHS_View::init_view($template, $view_params))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            return false;
        }

        $action_result = self::default_action_result();

        if (($action_result['buffer'] = $view_obj->render()) === null) {
            if ($view_obj->has_error()) {
                $this->copy_error($view_obj);
            } else {
                $this->set_error(self::ERR_RENDER, self::_t('Error rendering template [%s].', $view_obj->get_template()));
            }

            return false;
        }

        if (empty($action_result['buffer'])) {
            $action_result['buffer'] = '';
        }

        $action_result['action_data'] = $view_obj->get_all_view_vars();

        return $action_result;
    }

    /**
     * @return null|array
     */
    final public function run_action() : ?array
    {
        PHS::running_action($this);

        $action_body_classes = '';
        if (($route_as_string = PHS::get_route_as_string())) {
            $action_body_classes .= str_replace(['/', '-'], '_', $route_as_string);
        }
        if (($route_as_array = PHS::get_route_details())) {
            $route_parts = [];
            foreach ($route_as_array as $part_type => $part_value) {
                if (empty($part_value)) {
                    continue;
                }

                $route_parts[$part_value] = true;
            }

            $action_body_classes .= ($action_body_classes !== '' ? ' ' : '').implode(' ', array_keys($route_parts));
        }

        if (!empty($action_body_classes)) {
            PHS::page_body_class($action_body_classes);
        }

        if (!$this->instance_is_core()
         && (!($plugin_instance = $this->get_plugin_instance())
                || !$plugin_instance->plugin_active())) {
            $this->set_error(self::ERR_RUN_ACTION, self::_t('Unknown or not active action.'));

            return null;
        }

        $this->set_action_defaults();

        if (($current_scope = PHS_Scope::current_scope())
         && !$this->scope_is_allowed($current_scope)) {
            if (!($emulated_scope = PHS_Scope::emulated_scope())
             || !$this->scope_is_allowed($emulated_scope)) {
                $this->set_error(self::ERR_RUN_ACTION, self::_t('Action not allowed to run in current scope.'));

                return null;
            }
        }

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;
        $hook_args['action_result'] = self::default_action_result();

        if (($hook_result = PHS::trigger_hooks(PHS_Hooks::H_BEFORE_ACTION_EXECUTE, $hook_args))) {
            if (!empty($hook_result['stop_execution'])
             && !empty($hook_result['action_result'])) {
                $action_result = self::validate_action_result($hook_result['action_result']);

                $this->set_action_result($action_result);

                return $action_result;
            }
        }

        self::st_reset_error();
        $this->reset_error();

        if (!($action_result = $this->execute())) {
            // if execute() fails, it means it's a logic error, not an action flow error, so we return an action result with an error
            $error_arr = $this->has_error()
                ? $this->get_error()
                : self::arr_set_error(self::ERR_RUN_ACTION, self::_t('Error in action execution.'));

            return self::set_action_result_errors([], $error_arr);
        }

        $action_result = self::validate_action_result($action_result);

        if (($page_settings = self::validate_array(PHS::page_settings(), PHS::get_default_page_settings()))) {
            if (empty($action_result['page_settings']) || !is_array($action_result['page_settings'])) {
                $action_result['page_settings'] = $page_settings;
            } else {
                foreach ($page_settings as $key => $val) {
                    if (!empty($val)
                        && (!array_key_exists($key, $action_result['page_settings'])
                            || empty($action_result['page_settings'][$key]))) {
                        $action_result['page_settings'][$key] = $val;
                    }
                }
            }
        }

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;
        $hook_args['action_result'] = $action_result;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_AFTER_ACTION_EXECUTE, $hook_args))
         && is_array($hook_args)
         && !empty($hook_args['action_result']) && is_array($hook_args['action_result'])) {
            $action_result = $hook_args['action_result'];
        }

        $this->set_action_result($action_result);

        return $action_result;
    }

    final public function set_controller(?PHS_Controller $controller_obj) : void
    {
        $this->_controller_obj = $controller_obj;
    }

    final public function get_controller() : ?PHS_Controller
    {
        return $this->_controller_obj;
    }

    final public function is_admin_controller() : bool
    {
        return $this->_controller_obj && $this->_controller_obj->is_admin_controller();
    }

    final public static function default_action_role_definition_array() : array
    {
        return [
            'title' => '',
        ];
    }

    /**
     * Return an array of defined action roles (custom and builtin)
     * @return array
     */
    final public static function get_action_roles() : array
    {
        if (!empty(self::$_action_roles)) {
            return self::$_action_roles;
        }

        self::$_action_roles = self::merge_array_assoc(self::$_custom_action_roles, self::$_builtin_action_roles);

        return self::$_action_roles;
    }

    /**
     * Check if $role_key is a defined action role. Return action role definiton if role is defined.
     *
     * @param string $role_key
     *
     * @return null|array
     */
    final public static function valid_action_role(string $role_key) : ?array
    {
        if (!($roles_arr = self::get_action_roles())
         || empty($roles_arr[$role_key])) {
            return null;
        }

        return $roles_arr[$role_key];
    }

    /**
     * Returns a default array as result of an action execution
     *
     * @param null|bool|array $action_result
     *
     * @return array
     */
    final public static function validate_action_result($action_result) : array
    {
        $default_action_result = self::default_action_result();
        if (empty($action_result)) {
            return $default_action_result;
        }

        return self::validate_array($action_result, $default_action_result);
    }

    final public static function action_result_has_errors(array $action_result) : bool
    {
        if (empty($action_result['end_user_error']) && empty($action_result['technical_error'])) {
            return false;
        }

        return (!empty($action_result['end_user_error']) && self::arr_has_error($action_result['end_user_error']))
               || (!empty($action_result['technical_error']) && self::arr_has_error($action_result['technical_error']));
    }

    final public static function set_action_result_errors(array $action_result, ?array $end_user_error, ?array $technical_error = null) : array
    {
        if ($technical_error === null && !empty($end_user_error)) {
            $technical_error = $end_user_error;
        }

        if (empty($action_result)) {
            $action_result = self::default_action_result();
        }

        $action_result['end_user_error'] = $end_user_error;
        $action_result['technical_error'] = $technical_error;

        return $action_result;
    }

    final public static function get_end_user_error_from_action_result(array $action_result) : ?array
    {
        return $action_result['end_user_error'] ?? null;
    }

    final public static function get_technical_error_from_action_result(array $action_result) : ?array
    {
        return $action_result['technical_error'] ?? null;
    }

    /**
     * Returns a default array as result of an action execution
     * @return array
     */
    final public static function default_action_result() : array
    {
        return [
            // Action "content"
            'action_data'      => [], // Data which was used when running action
            'buffer'           => '',
            'ajax_result'      => false,
            'ajax_only_result' => false,
            'custom_headers'   => [], // key - value headers...

            // This is specific to API calls.
            // When generating response, API class will check this array first, then ajax_result, then will check if we have an api_buffer set
            'api_json_result_array' => false,
            'api_buffer'            => '', // we don't use buffer as it might contain html returned in web scope

            // In case we activate password expiration, and password expired, set this to true to tell current scope current user password
            // did expire. Current scope will decide what to do when password is expired. (in agent or background scopes it will not have an impact)
            'password_expired' => false,
            // If current action requires a logged-in user set this to true.
            // Logging in is dependent on used scope (on API we should return Unauthenticated header, on web we redirect to login page, etc)
            'request_login'   => false,
            'redirect_to_url' => '', // any URLs that we should redirect to (we might have to do javascript redirect or header redirect)
            'page_template'   => 'template_main', // if empty, scope template will be used...

            // Errors in action execute flow
            'end_user_error'  => null,
            'technical_error' => null,

            // page related variables
            'page_settings' => PHS::get_default_page_settings(),
            // anything that is required as attributes to body tag
            'page_body_extra_tags' => '',
        ];
    }
}
