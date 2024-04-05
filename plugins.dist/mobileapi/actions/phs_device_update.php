<?php
namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;

class PHS_Action_Device_update extends PHS_Api_action
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
                $this->_pt('No session.'));
        }

        $session_arr = $session_data['session_arr'];

        if (!($device_arr = $online_model->get_session_device($session_arr))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_NOT_FOUND, self::ERR_AUTHENTICATION,
                $this->_pt('Device not found in database.'));
        }

        if (!($request_arr = PHS_Api::get_request_body_as_json_array())
         || empty($request_arr['device_type'])
         || empty($request_arr['device_token'])
         || !$online_model->valid_device_type($request_arr['device_type'])
         || empty($device_arr['device_type']) || (int)$device_arr['device_type'] !== (int)$request_arr['device_type']
         || empty($device_arr['device_token']) || (string)$device_arr['device_token'] !== (string)$request_arr['device_token']) {
            return $this->send_api_error(PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_AUTHENTICATION,
                $this->_pt('Please provide device details.'));
        }

        $device_data = $mobile_plugin::import_api_data_with_definition_as_array($request_arr, $online_model::get_api_data_device_fields());

        $new_device_arr = false;
        if (!empty($device_data)
         && !($new_device_arr = $online_model->update_device($device_data, $device_arr))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error updating device.'));
        }

        if (!empty($new_device_arr)) {
            $device_arr = $new_device_arr;
        }

        $session_arr[$online_model::DEVICE_KEY] = $device_arr;

        // In case user already logged out, and we have a "late" request or user was inactivated between requests...
        if (empty($device_arr['uid'])
         || !($account_arr = $accounts_model->get_details($device_arr['uid'], ['table_name' => 'users']))
         || !$accounts_model->is_active($account_arr)) {
            $account_arr = false;
        }

        return $this->send_api_success(
            $mobile_plugin->export_data_account_and_session($account_arr, $session_arr)
        );
    }
}
