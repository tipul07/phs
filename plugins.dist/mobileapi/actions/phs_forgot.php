<?php
namespace phs\plugins\mobileapi\actions;

use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\actions\PHS_Action_Forgot_password_bg;

class PHS_Action_Forgot extends PHS_Api_action
{
    #[PHS_Dependency]
    private ?PHS_Model_Accounts $_accounts_model = null;

    public function action_roles(): array
    {
        return [self::ACT_ROLE_FORGOT_PASSWORD];
    }

    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_API];
    }

    public function execute()
    {
        if (!($request_arr = PHS_Api::get_request_body_as_json_array())) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Invalid account.'));
        }

        if (empty($request_arr['email'])
            || !($account_arr = $this->_accounts_model->get_details_fields(
                ['email' => $request_arr['email'], 'status' => $this->_accounts_model::STATUS_ACTIVE],
            ))) {
            // Because of security reasons, let them think it's ok
            return $this->send_api_success(['email_queued' => true], extra_arr: ['only_response_data_node' => true]);
        }

        if ($this->_accounts_model->is_locked($account_arr)) {
            return $this->send_api_error(
                PHS_Api_base::H_CODE_FORBIDDEN,
                $this->_accounts_model::ERR_LOGIN,
                $this->_pt('Account locked temporarily because of too many login attempts.'),
            );
        }

        if (!PHS_Bg_jobs::run(PHS_Action_Forgot_password_bg::class, ['uid' => $account_arr['id']])) {
            return $this->send_api_error(
                PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR,
                $this->_accounts_model::ERR_LOGIN,
                $this->_pt('Error sending forgot password email. Please try again.'),
            );
        }

        return $this->send_api_success(['email_queued' => true]);
    }
}
