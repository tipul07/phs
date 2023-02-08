<?php
namespace phs\plugins\accounts\actions\api;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Session;
use phs\PHS_Api_base;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Api_action;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\contracts\PHS_Contract_Account_basic;

class PHS_Action_Logout extends PHS_Api_action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_LOGOUT];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;

        if (($new_hook_args = PHS::trigger_hooks(PHS_Hooks::H_USERS_LOGOUT_ACTION_START, $hook_args))
        && is_array($new_hook_args) && !empty($new_hook_args['action_result'])) {
            $action_result = self::validate_array($new_hook_args['action_result'], self::default_action_result());

            if (!empty($new_hook_args['stop_execution'])) {
                $this->set_action_result($action_result);

                return $action_result;
            }
        }

        if (!($current_user = PHS::user_logged_in())) {
            return $this->send_api_success(['account' => false, 'account_logged_out' => true]);
        }

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts\contracts\PHS_Contract_Account_basic $account_contract */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($account_contract = PHS_Contract_Account_basic::get_instance())) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error loading required resources.'));
        }

        $logged_out = true;
        if (!$accounts_plugin->do_logout()) {
            $logged_out = false;

            if (self::st_debugging_mode()
             && $accounts_plugin->has_error()) {
                PHS_Logger::error('Error loggining out: '.$accounts_plugin->get_error_message(), PHS_Logger::TYPE_DEBUG);
            }

            PHS_Notifications::add_error_notice($this->_pt('Error logging out... Please try again.'));
        }

        if (!($user_payload_arr = $accounts_model->populate_account_data_for_account_contract($current_user))
         || !($user_payload_arr = $account_contract->parse_data_from_inside_source($user_payload_arr))) {
            $user_payload_arr = [];
        }

        $payload_arr = [];
        $payload_arr['account'] = $user_payload_arr;
        $payload_arr['account_logged_out'] = $logged_out;

        return $this->send_api_success($payload_arr);
    }
}
