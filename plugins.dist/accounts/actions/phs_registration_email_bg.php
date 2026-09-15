<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\system\core\libraries\PHS_Email;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Registration_email_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000;

    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_REGISTER];
    }

    public function allowed_scopes() : array
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
            || !$accounts_model->needs_activation($account_arr)) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Cannot send registration email to this account.'));

            return false;
        }

        $lang = $accounts_model->get_account_language($account_arr) ?: self::get_default_language();

        $email_obj
            = phs_email()
                ?->force_language($lang)
                ->to($account_arr['email'], $account_arr['nick'])
                ->template('registration', $accounts_plugin)
                ->subject($this->_pt('Account Activation', $lang))
                ->email_variables([
                    'nick'            => $account_arr['nick'],
                    'pass_generated'  => $account_arr['pass_generated'],
                    'activation_link' => $accounts_plugin->get_confirmation_link($account_arr, $accounts_plugin::CONF_REASON_ACTIVATION),
                    'contact_us_link' => PHS::url(['a' => 'contact_us']),
                    'login_link'      => PHS::url(['p' => 'accounts', 'a' => 'login'], ['nick' => $account_arr['nick']]),
                ]);

        if (!$email_obj?->send()) {
            PHS_Logger::error(
                'Error sending registration email: '.$email_obj?->get_simple_error_message() ?? 'Unknown error.',
                PHS_Logger::TYPE_DEBUG
            );
        }

        return self::default_action_result();
    }
}
