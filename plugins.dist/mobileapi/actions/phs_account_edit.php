<?php
namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;

class PHS_Action_Account_edit extends PHS_Api_action
{
    public function action_roles() : array
    {
        return [self::ACT_ROLE_EDIT_PROFILE];
    }

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_API];
    }

    public function execute()
    {
        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($online_model = PHS::load_model('api_online', 'mobileapi'))
         || !($mobile_plugin = PHS::load_plugin('mobileapi'))
         || !($accounts_model = PHS::load_model('accounts', 'accounts'))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error loading required resources.'));
        }

        if (!($session_data = $mobile_plugin::api_session())
         || empty($session_data['session_arr'])
         || empty($session_data['account_arr'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_API_INIT,
                $this->_pt('Not authenticated.'));
        }

        if (!($request_arr = PHS_Api::get_request_body_as_json_array())) {
            if (!$api_obj->send_header_response($api_obj::H_CODE_UNAUTHORIZED, $this->_pt('Please provide account details you want to change.'))) {
                $this->set_error($api_obj::ERR_AUTHENTICATION, $this->_pt('Please provide account details you want to change.'));

                return false;
            }

            exit;
        }

        $account_arr = $session_data['account_arr'];
        $session_arr = $session_data['session_arr'];

        if (false === ($result = $mobile_plugin->import_api_data_for_account_data($account_arr, $request_arr))
         && $mobile_plugin->has_error()) {
            if (!($error_msg = $mobile_plugin->get_simple_error_message())) {
                $error_msg = $this->_pt('Error saving account details.');
            }

            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $error_msg);
        }

        $response_arr = $mobile_plugin->export_data_account_and_session($account_arr['id'], $session_arr['id']);
        $response_arr['profile_saved'] = true;

        return $this->send_api_success($response_arr);
    }
}
