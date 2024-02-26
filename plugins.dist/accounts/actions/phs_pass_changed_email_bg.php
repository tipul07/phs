<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;

class PHS_Action_Pass_changed_email_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_CHANGE_PASSWORD];
    }

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
         || !($accounts_plugin = $this->get_plugin_instance())
         || !($accounts_model = PHS::load_model('accounts', 'accounts'))
         || !($accounts_settings = $this->get_plugin_settings())
         || !is_array($accounts_settings)
         || empty($accounts_settings['announce_pass_change'])
         || !($account_arr = $accounts_model->get_details($params['uid']))
         || !$accounts_model->is_active($account_arr)
         || empty($account_arr['email'])) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Account doesn\'t need password change notification.'));

            return false;
        }

        $hook_args = [];
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file('password_changed');
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Password changed');
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'obfuscated_pass' => $accounts_model->obfuscate_password($account_arr),
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
                $this->set_error(self::ERR_SEND_EMAIL, $this->_pt('Error sending password changed message to %s.', $account_arr['email']));
            }

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
