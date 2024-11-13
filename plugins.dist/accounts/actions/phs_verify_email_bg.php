<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Verify_email_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

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
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
         || !is_array($params)
         || empty($params['uid'])
         || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($account_arr = $accounts_model->get_details($params['uid']))
         || !$accounts_model->needs_email_verification($account_arr)) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Account doesn\'t need email verification.'));

            return null;
        }

        $hook_args = [];
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file('verify_email');
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Verify Email');
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'activation_link' => $accounts_plugin->get_confirmation_link($account_arr, $accounts_plugin::CONF_REASON_EMAIL),
            'contact_us_link' => PHS::url(['a' => 'contact_us']),
        ];

        if (($hook_results = PHS_Hooks::trigger_email($hook_args)) === null) {
            return self::default_action_result();
        }

        if (empty($hook_results) || !is_array($hook_results)
         || empty($hook_results['send_result'])) {
            if (self::st_has_error()) {
                $this->copy_static_error(self::ERR_SEND_EMAIL);
            } else {
                $this->set_error(self::ERR_SEND_EMAIL, $this->_pt('Error sending verify email message to %s.', $account_arr['email']));
            }

            return null;
        }

        return PHS_Action::default_action_result();
    }
}
