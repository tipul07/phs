<?php

namespace phs\plugins\accounts\actions\api;

use phs\PHS;
use phs\PHS_Api_base;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Api_action;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\actions\PHS_Event_Action_start;
use phs\plugins\accounts\contracts\PHS_Contract_Account_basic;

class PHS_Action_Logout extends PHS_Api_action
{
    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_LOGOUT];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::LOGOUT, $this))
            && !empty($event_result['action_result']) && is_array($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        if (!($current_user = PHS::user_logged_in())) {
            return $this->send_api_success(['account' => null, 'account_logged_out' => true]);
        }

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Contract_Account_basic $account_contract */
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
            $user_payload_arr = null;
        }

        $payload_arr = [];
        $payload_arr['account'] = $user_payload_arr;
        $payload_arr['account_logged_out'] = $logged_out;

        return $this->send_api_success($payload_arr);
    }
}
