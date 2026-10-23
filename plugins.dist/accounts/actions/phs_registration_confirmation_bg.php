<?php
namespace phs\plugins\accounts\actions;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\controllers\PHS_Controller_Index_bg;

class PHS_Action_Registration_confirmation_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

    protected static ?string $_default_controller_for_route = PHS_Controller_Index_bg::class;

    /**
     * @inheritdoc
     */
    public function action_roles(): array
    {
        return [self::ACT_ROLE_REGISTER];
    }

    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || empty($params['uid'])
            || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !($account_arr = $accounts_model->get_details($params['uid']))
            || $accounts_model->is_deleted($account_arr)) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Cannot send registration confirmation to this account.'));

            return null;
        }

        if ($accounts_model->must_setup_password($account_arr)) {
            if (!$accounts_plugin->send_account_password_setup($account_arr)) {
                $this->copy_error($accounts_plugin, self::ERR_SEND_EMAIL);

                return null;
            }
        } elseif (!$accounts_plugin->send_account_confirmation_email($account_arr)) {
            $this->copy_error($accounts_plugin, self::ERR_SEND_EMAIL);

            return null;
        }

        return self::default_action_result();
    }
}
