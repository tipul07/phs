<?php
namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Api_action;
use phs\plugins\mobileapi\PHS_Plugin_Mobileapi;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Register extends PHS_Api_action
{
    public const ERR_DEPENDENCIES = 1, ERR_MODEL_DATA = 2, ERR_REGISTRATION = 3;

    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_REGISTER];
    }

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_API];
    }

    public function execute()
    {
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($mobile_plugin = PHS_Plugin_Mobileapi::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error loading required resources.'));
        }

        if (!can(PHS_Roles::ROLEU_REGISTER)) {
            return $this->send_api_error(PHS_Api_base::H_CODE_NOT_IMPLEMENTED, self::ERR_DEPENDENCIES,
                $this->_pt('Registration is closed for this site.'));
        }

        if (!($request_arr = PHS_Api::get_request_body_as_json_array())
            || empty($request_arr['nick'])
            || empty($request_arr['email'])
            || !isset($request_arr['pass'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Please provide required fields.'));
        }

        $account_fields = $mobile_plugin::import_api_data_with_definition_as_array($request_arr, $mobile_plugin::get_api_data_account_fields());
        $account_details = false;
        if (!empty($account_fields[$mobile_plugin::ACCOUNT_DETAILS_KEY])) {
            $account_details = $account_fields[$mobile_plugin::ACCOUNT_DETAILS_KEY];
            unset($account_fields[$mobile_plugin::ACCOUNT_DETAILS_KEY]);
        }

        $insert_fields_arr = $account_fields;
        if (empty($insert_fields_arr['pass'])) {
            $insert_fields_arr['pass'] = '';
        }
        $insert_fields_arr['level'] = $accounts_model::LVL_MEMBER;
        $insert_fields_arr['lastip'] = request_ip();

        $insert_arr = $accounts_model->fetch_default_flow_params(['table_name' => 'users']);
        $insert_arr['fields'] = $insert_fields_arr;
        if (!empty($account_details)) {
            $insert_arr['{users_details}'] = $account_details;
        }

        if (!($account_arr = $accounts_model->insert($insert_arr))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_REGISTRATION,
                $accounts_model->get_simple_error_message($this->_pt('Couldn\'t register user. Please try again.')));
        }

        $response_arr = [
            'registered'   => true,
            'account_data' => $mobile_plugin->export_data_from_account_data($account_arr),
        ];

        return $this->send_api_success($response_arr);
    }
}
