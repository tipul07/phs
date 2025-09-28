<?php
namespace phs\plugins\backup\controllers;

use phs\PHS;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Controller_Admin;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Controller_Index extends PHS_Controller_Admin
{
    /**
     * @inheritdoc
     */
    protected function _execute_action($action, $plugin = null, $action_dir = '')
    {
        $this->is_admin_controller(true);

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!PHS_Model_Accounts::get_instance()?->acc_is_operator()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have enough rights to access this section.'));

            return $this->execute_foobar_action();
        }

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
