<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;

class PHS_Action_Forgot_password_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
         || !is_array($params)
         || empty($params['uid'])
         || !($accounts_plugin = PHS::load_plugin($this->instance_plugin_name()))
         || !($accounts_model = PHS::load_model('accounts', $this->instance_plugin_name()))
         || !($account_arr = $accounts_model->get_details($params['uid']))
         || empty($account_arr['email'])
         || !$accounts_model->is_active($account_arr)
         || $accounts_model->is_locked($account_arr)) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Cannot send forgot password email to this account.'));

            return false;
        }

        $hook_args = [];
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file('forgot');
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Password reset link');
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'forgot_link'     => $accounts_plugin->get_confirmation_link($account_arr, $accounts_plugin::CONF_REASON_FORGOT),
            'contact_us_link' => PHS::url(['a' => 'contact_us']),
            'login_link'      => PHS::url(['p' => 'accounts', 'a' => 'login'], ['nick' => $account_arr['nick']]),
        ];

        if (($hook_results = PHS_Hooks::trigger_email($hook_args)) === null) {
            return self::default_action_result();
        }

        if (empty($hook_results) || !is_array($hook_results)
         || empty($hook_results['send_result'])) {
            if (self::st_has_error()) {
                $this->copy_static_error(self::ERR_SEND_EMAIL);
            } else {
                $this->set_error(self::ERR_SEND_EMAIL, $this->_pt('Error sending forgot password email to %s.', $account_arr['email']));
            }

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
