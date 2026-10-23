<?php
namespace phs\plugins\mobileapi\controllers;

use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Controller_Admin extends \phs\libraries\PHS_Controller_Admin
{
    /**
     * @inheritdoc
     */
    protected function _execute_action($action, $plugin = null, $action_dir = '')
    {
        $this->is_admin_controller(true);

        if (!PHS_Model_Accounts::get_instance()?->acc_is_operator()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have enough rights to access this section.'));

            return $this->execute_foobar_action();
        }

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
