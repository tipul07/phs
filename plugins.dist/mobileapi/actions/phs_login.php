<?php
namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;
use phs\plugins\mobileapi\PHS_Plugin_Mobileapi;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\mobileapi\models\PHS_Model_Api_online;

class PHS_Action_Login extends PHS_Api_action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_LOGIN];
    }

    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_API];
    }

    public function execute()
    {
        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($online_model = PHS_Model_Api_online::get_instance())
         || !($mobile_plugin = PHS_Plugin_Mobileapi::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error loading required resources.'));
        }

        if (!($session_data = $mobile_plugin::api_session())
         || empty($session_data['session_arr'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_API_INIT,
                $this->_pt('No session.'));
        }

        $session_arr = $session_data['session_arr'];

        if (!($request_arr = PHS_Api::get_request_body_as_json_array())
         || empty($request_arr['nick'])
         || empty($request_arr['pass'])
         || empty($request_arr['device_info'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_AUTHENTICATION,
                $this->_pt('Please provide credentials.'));
        }

        if (!($account_arr = $accounts_model->get_details_fields(['nick' => $request_arr['nick']]))
         || !$accounts_model->is_active($account_arr)) {
            return $this->send_api_error(PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_AUTHENTICATION,
                $this->_pt('Authentication failed.'));
        }

        if ($accounts_model->is_locked($account_arr)) {
            return $this->send_api_error(PHS_Api_base::H_CODE_FORBIDDEN, $accounts_model::ERR_LOGIN,
                $this->_pt('Account locked temporarily because of too many login attempts.'));
        }

        if (!$accounts_model->check_pass($account_arr, $request_arr['pass'])) {
            if (($new_account = $accounts_model->manage_failed_password($account_arr))) {
                $account_arr = $new_account;

                if ($accounts_model->is_locked($account_arr)) {
                    return $this->send_api_error(PHS_Api_base::H_CODE_FORBIDDEN, $accounts_model::ERR_LOGIN,
                        $this->_pt('Account locked temporarily because of too many login attempts.'));
                }
            }

            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, $accounts_model::ERR_LOGIN,
                $this->_pt('Bad user or password.'));
        }

        $device_data = $mobile_plugin::import_api_data_with_definition_as_array($request_arr['device_info'], $online_model::get_api_data_device_fields());
        if (!($new_session_arr = $online_model->update_session($session_arr, $device_data, $account_arr['id'], ['regenerate_keys' => true]))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error updating session.'));
        }
        $session_arr = $new_session_arr;

        return $this->send_api_success($mobile_plugin->export_data_account_and_session($account_arr, $session_arr));
    }
}
