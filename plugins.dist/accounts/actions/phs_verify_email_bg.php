<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\system\core\libraries\PHS_Email;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Verify_email_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000;

    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_ACTIVATION];
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
         || !$accounts_model->needs_email_verification($account_arr)) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Account doesn\'t need email verification.'));

            return null;
        }

        $lang = $accounts_model->get_account_language($account_arr) ?: self::get_default_language();

        $email_obj
            = PHS_Email::get_instance()
                ?->force_language($lang)
                ->template('verify_email', $accounts_plugin)
                ->to($account_arr['email'], $account_arr['nick'])
                ->subject($this->_pt('Verify Email', $lang))
                ->email_variables([
                    'nick'            => $account_arr['nick'],
                    'activation_link' => $accounts_plugin->get_confirmation_link($account_arr, $accounts_plugin::CONF_REASON_EMAIL),
                    'contact_us_link' => PHS::url(['a' => 'contact_us']),
                ]);

        if (!$email_obj?->send()) {
            PHS_Logger::error(
                'Error sending verify email: '.$email_obj?->get_simple_error_message() ?? 'Unknown error.',
                PHS_Logger::TYPE_DEBUG
            );
        }

        return self::default_action_result();
    }
}
