<?php

namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;

class PHS_Action_Device_session_details extends PHS_Api_action
{
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
         || empty($session_data['session_arr'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_API_INIT,
                $this->_pt('Not authenticated.'));
        }

        $session_arr = $session_data['session_arr'];
        $account_arr = (!empty($session_data['account_arr']) ? $session_data['account_arr'] : false);

        // Device is already cached in session data if present
        if (!($device_arr = $online_model->get_session_device($session_arr))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_NOT_FOUND, self::ERR_AUTHENTICATION,
                $this->_pt('Device not found in database.'));
        }

        return $this->send_api_success(
            $mobile_plugin->export_data_account_and_session($account_arr, $session_arr)
        );
    }
}
