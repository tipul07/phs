<?php
namespace phs\libraries;

use phs\PHS_Scope;

abstract class PHS_Controller_Index extends PHS_Controller
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @inheritdoc
     */
    protected function _execute_action($action, $plugin, $action_dir = '')
    {
        $this->is_admin_controller(false);

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
