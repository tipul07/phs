<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\system\core\actions\PHS_Action_Foobar;

abstract class PHS_Controller extends PHS_Instantiable
{
    public const ERR_RUN_ACTION = 40000, ERR_SCOPE = 40001;

    private $_action = false;

    // ! Tells if running controller should choose admin template if Scope should display a layout
    /** @var bool */
    private bool $_is_admin_controller = false;

    /**
     * @return string
     */
    final public function instance_type() : string
    {
        return self::INSTANCE_TYPE_CONTROLLER;
    }

    /**
     * Returns action running currently using this controller
     * @return bool|PHS_Action
     */
    final public function get_action()
    {
        return $this->_action;
    }

    /**
     * Returns an array of scopes in which controller is allowed to run
     *
     * @return array If empty array, controller is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [];
    }

    final public function is_admin_controller($is_admin = null) : bool
    {
        if ($is_admin === null) {
            return $this->_is_admin_controller;
        }

        $this->_is_admin_controller = (!empty($is_admin));

        return $this->_is_admin_controller;
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

        return !($allowed_scopes = $this->allowed_scopes())
               || in_array($scope, $allowed_scopes, true);
    }

    /**
     * Overwrite this method to tell controller to redirect user to login page if not logged in
     * @return bool
     */
    public function should_request_have_logged_in_user() : bool
    {
        return false;
    }

    /**
     * Overwrite this method to tell controller that a check if current logged in user should have any role units defined in current plugin
     * If this method returns true, an user checked test is also made
     * @return bool
     */
    public function should_user_have_any_of_defined_role_units() : bool
    {
        return false;
    }

    /**
     * @param string $action Action to be loaded and executed
     * @param null|bool|string $plugin NULL means same plugin as controller (default), false means core plugin, string is name of plugin
     * @param string $action_dir Directory (relative from actions dir) where action class is found
     *
     * @return bool|array Returns false on error or an action array on success
     */
    final public function pre_run_action(string $action, $plugin = null, string $action_dir = '')
    {
        PHS::running_controller($this);

        if (!$this->instance_is_core()
        && (!($plugin_instance = $this->get_plugin_instance())
                || !$plugin_instance->plugin_active())) {
            $this->set_error(self::ERR_RUN_ACTION, self::_t('Unknown or not active controller.'));

            return false;
        }

        if (($current_scope = PHS_Scope::current_scope())
        && !$this->scope_is_allowed($current_scope)) {
            if (!($emulated_scope = PHS_Scope::emulated_scope())
             || !$this->scope_is_allowed($emulated_scope)) {
                $this->set_error(self::ERR_RUN_ACTION, self::_t('Controller not allowed to run in current scope.'));

                return false;
            }
        }

        if ($plugin === null) {
            $plugin = $this->instance_plugin_name();
        }

        return $this->_execute_action($action, $plugin, $action_dir);
    }

    /**
     * @param string $action Action to be loaded and executed
     * @param null|bool|string $plugin NULL means same plugin as controller (default), false means core plugin, string is name of plugin
     * @param string $action_dir Directory (relative from actions dir) where action class is found
     *
     * @return bool|array|null Returns false on error or an action array on success
     */
    final public function run_action(string $action, $plugin = null, string $action_dir = '')
    {
        PHS::running_controller($this);

        if (!$this->instance_is_core()
            && (!($plugin_instance = $this->get_plugin_instance())
                || !$plugin_instance->plugin_active())) {
            $this->set_error(self::ERR_RUN_ACTION, self::_t('Unknown or not active controller.'));

            return false;
        }

        if (($current_scope = PHS_Scope::current_scope())
        && !$this->scope_is_allowed($current_scope)) {
            if (!($emulated_scope = PHS_Scope::emulated_scope())
             || !$this->scope_is_allowed($emulated_scope)) {
                $this->set_error(self::ERR_RUN_ACTION, self::_t('Controller not allowed to run in current scope.'));

                return false;
            }
        }

        if ($plugin === null) {
            $plugin = $this->instance_plugin_name();
        }

        return $this->_execute_action($action, $plugin, $action_dir);
    }

    /**
     * @param null|array $action_result stop execution from controller level using a standard action, just to have nice display...
     *
     * @return null|array Returns an action result array which was generated from controller...
     */
    public function execute_foobar_action(?array $action_result = null): ?array
    {
        PHS::running_controller($this);

        if (!$this->instance_is_core()
            && (!($plugin_instance = $this->get_plugin_instance())
                || !$plugin_instance->plugin_active())) {
            $this->set_error(self::ERR_RUN_ACTION, self::_t('Unknown or not active controller.'));

            return null;
        }

        self::st_reset_error();

        /** @var \phs\system\core\actions\PHS_Action_Foobar $foobar_action_obj */
        if (!($foobar_action_obj = PHS_Action_Foobar::get_instance())) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            } else {
                $this->set_error(self::ERR_RUN_ACTION, self::_t('Couldn\'t load foobar action.'));
            }

            return null;
        }

        if (empty($action_result)) {
            $action_result = PHS_Action::default_action_result();
        }

        $foobar_action_obj->set_controller($this);
        $foobar_action_obj->run_action();

        return $foobar_action_obj->set_action_result($action_result);
    }

    /**
     * @param string $action Action to be loaded and executed
     * @param bool|string $plugin false means core plugin, string is name of plugin
     * @param string $action_dir Filesystem directory (relative from actions dir) where action class is found
     *
     * @return bool|array Returns false on error or an action array on success
     */
    protected function _execute_action($action, $plugin, $action_dir = '')
    {
        self::st_reset_error();

        if ($this->should_request_have_logged_in_user()
        && !PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return $this->execute_foobar_action(action_request_login());
        }

        if ($this->should_user_have_any_of_defined_role_units()) {
            /** @var \phs\libraries\PHS_Plugin $plugin_obj */
            if (!($plugin_obj = $this->get_plugin_instance())) {
                PHS_Notifications::add_warning_notice($this->_pt('Couldn\'t obtain plugin instance.'));

                return $this->execute_foobar_action();
            }

            if (!$plugin_obj->user_has_any_of_defined_role_units()) {
                PHS_Notifications::add_warning_notice($this->_pt('You don\'t have rights to access this section.'));

                return $this->execute_foobar_action();
            }
        }

        if (!is_string($action_dir)) {
            $action_dir = '';
        }

        /** @var \phs\libraries\PHS_Action $action_obj */
        if (!($action_obj = PHS::load_action($action, $plugin, $action_dir))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            } else {
                $this->set_error(self::ERR_RUN_ACTION, self::_t('Couldn\'t load action [%s].', ($action_dir !== '' ? $action_dir.'/' : '').$action));
            }

            return false;
        }

        $action_obj->set_controller($this);

        if (!($action_result = $action_obj->run_action())) {
            if ($action_obj->has_error()) {
                $this->copy_error($action_obj);
            } else {
                $this->set_error(self::ERR_RUN_ACTION, self::_t('Error executing action [%s].', ($action_dir !== '' ? $action_dir.'/' : '').$action));
            }

            return false;
        }

        // If page template is still "front-end" and controller told us it is an admin controller change page template
        // with default admin template
        if (!empty($action_result['page_template']) && $action_result['page_template'] === 'template_main'
            && $this->is_admin_controller()) {
            $action_result['page_template'] = 'template_admin';
        }

        return $action_result;
    }
}
