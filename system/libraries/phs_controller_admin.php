<?php
namespace phs\libraries;

use phs\PHS_Scope;
use phs\libraries\PHS_Controller;

abstract class PHS_Controller_Admin extends PHS_Controller
{
    /**
     * Overwrite this method to tell controller to redirect user to login page if not logged in
     * @return bool
     */
    public function should_request_have_logged_in_user()
    {
        return true;
    }

    /**
     * Returns an array of scopes in which controller is allowed to run
     *
     * @return array If empty array, controller is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @param string $action Action to be loaded and executed
     * @param bool|string $plugin false means core plugin, string is name of plugin
     * @param string $action_dir Directory (relative from actions dir) where action class is found
     *
     * @return bool|array Returns false on error or an action array on success
     */
    protected function _execute_action($action, $plugin, $action_dir = '')
    {
        $this->is_admin_controller(true);

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
