<?php
namespace phs\plugins\backup\controllers;

use phs\PHS;
use phs\libraries\PHS_Notifications;

class PHS_Controller_Index extends \phs\libraries\PHS_Controller_Admin
{
    /**
     * @param string $action Action to be loaded and executed
     * @param bool|string $plugin false means core plugin, string is name of plugin
     * @param string $action_dir Directory (relative from actions dir) where action class is found
     *
     * @return bool|array Returns false on error or an action array on success
     */
    protected function _execute_action($action, $plugin = null, $action_dir = '')
    {
        $this->is_admin_controller(true);

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS::load_model('accounts', 'accounts'))) {
            $this->set_error(self::ERR_RUN_ACTION, $this->_pt('Error loading accounts model.'));

            return false;
        }

        if (!$accounts_model->acc_is_operator(PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have enough rights to access this section...'));

            return $this->execute_foobar_action();
        }

        return parent::_execute_action($action, $plugin, $action_dir);
    }
}
