<?php

namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;
use phs\plugins\mobileapi\PHS_Plugin_Mobileapi;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\mobileapi\models\PHS_Model_Api_online;

class PHS_Action_Forgot extends PHS_Api_action
{
    public function action_roles() : array
    {
        return [self::ACT_ROLE_FORGOT_PASSWORD];
    }

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_API];
    }

    public function execute()
    {
        /** @var PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error loading required resources.'));
        }

        if (!($request_arr = PHS_Api::get_request_body_as_json_array())) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Invalid account.'));
        }

        if (empty($request_arr['email'])
            || !($account_arr = $accounts_model->get_details_fields(['email' => $request_arr['email'], 'status' => $accounts_model::STATUS_ACTIVE]))) {
            // Because of security reasons, let them think it's ok
            return $this->send_api_success(
                ['email_queued' => true],
                PHS_Api_base::H_CODE_OK,
                null,
                ['only_response_data_node' => true]
            );
        }

        if ($accounts_model->is_locked($account_arr)) {
            return $this->send_api_error(PHS_Api_base::H_CODE_FORBIDDEN, $accounts_model::ERR_LOGIN,
                $this->_pt('Account locked temporarily because of too many login attempts.'));
        }

        if (!PHS_Bg_jobs::run(['p' => 'accounts', 'c' => 'index_bg', 'a' => 'forgot_password_bg'], ['uid' => $account_arr['id']])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, $accounts_model::ERR_LOGIN,
                $this->_pt('Error sending forgot password email. Please try again.'));
        }

        return $this->send_api_success(['email_queued' => true]);
    }
}
