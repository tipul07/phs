<?php
namespace phs\plugins\mobileapi\controllers;

use phs\PHS;
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

        /** @var PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_RUN_ACTION, $this->_pt('Error loading accounts model.'));

            return false;
        }

        if (!$accounts_model->acc_is_operator(PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have enough rights to access this section.'));

            return $this->execute_foobar_action();
        }

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
