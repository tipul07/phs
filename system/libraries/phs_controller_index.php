<?php
namespace phs\libraries;

use phs\PHS_Scope;

abstract class PHS_Controller_Index extends PHS_Controller
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    protected function _execute_action($action, $plugin, $action_dir = '')
    {
        $this->is_admin_controller(false);

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
