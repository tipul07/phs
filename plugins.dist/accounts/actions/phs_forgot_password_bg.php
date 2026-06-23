<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Forgot_password_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

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
         || empty($account_arr['email'])
         || !$accounts_model->is_active($account_arr)
         || $accounts_model->is_locked($account_arr)) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Cannot send forgot password email to this account.'));

            return null;
        }

        $lang = $accounts_model->get_account_language($account_arr) ?: self::get_default_language();

        $hook_args = [];
        $hook_args['force_language'] = $lang;
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file('forgot', $lang);
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Password reset link', $lang);
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'forgot_link'     => $accounts_plugin->get_confirmation_link($account_arr, $accounts_plugin::CONF_REASON_FORGOT),
            'contact_us_link' => PHS::url(['a' => 'contact_us']),
            'login_link'      => PHS::url(['p' => 'accounts', 'a' => 'login'], ['nick' => $account_arr['nick']]),
        ];

        if (($hook_results = PHS_Hooks::trigger_email($hook_args)) === null) {
            return self::default_action_result();
        }

        if (empty($hook_results['send_result'])) {
            $this->copy_or_set_static_error(
                self::ERR_SEND_EMAIL,
                $this->_pt('Error sending forgot password email to %s.', $account_arr['email'])
            );

            PHS_Logger::error(
                'Error sending forgot password email: '.$this->get_simple_error_message(),
                PHS_Logger::TYPE_DEBUG
            );

            $this->reset_error();
        }

        return self::default_action_result();
    }
}
