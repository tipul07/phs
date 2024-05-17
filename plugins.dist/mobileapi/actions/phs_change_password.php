<?php

namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;

class PHS_Action_Change_password extends PHS_Api_action
{
    public function action_roles() : array
    {
        return [self::ACT_ROLE_CHANGE_PASSWORD];
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

        if (!($request_arr = PHS_Api::get_request_body_as_json_array())
         || empty($request_arr['pass'])
         || empty($request_arr['new_pass'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Please provide OLD and NEW password.'));
        }

        if (!$accounts_model->check_pass($session_data['account_arr'], $request_arr['pass'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_AUTHENTICATION,
                $this->_pt('Wrong current password.'));
        }

        $edit_arr = [];
        $edit_arr['pass'] = $request_arr['new_pass'];

        $edit_params_arr = $accounts_model->fetch_default_flow_params(['table_name' => 'users']);
        $edit_params_arr['fields'] = $edit_arr;

        if (!($new_account = $accounts_model->edit($session_data['account_arr'], $edit_params_arr))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error changing password.'));
        }

        return $this->send_api_success(['password_changed' => true]);
    }
}
