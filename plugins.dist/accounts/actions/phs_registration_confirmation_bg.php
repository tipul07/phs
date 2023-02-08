<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;

class PHS_Action_Registration_confirmation_bg extends PHS_Action
{
    public const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_REGISTER];
    }

    public function allowed_scopes()
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
         || !($accounts_plugin = PHS::load_plugin('accounts'))
         || !($accounts_model = PHS::load_model('accounts', 'accounts'))
         || !($account_arr = $accounts_model->get_details($params['uid']))) {
            $this->set_error(self::ERR_UNKNOWN_ACCOUNT, $this->_pt('Cannot send registration confirmation to this account.'));

            return false;
        }

        // if( false and !$accounts_model->needs_confirmation_email( $account_arr ) )
        // {
        //     $this->set_error( self::ERR_SEND_EMAIL, $this->_pt( 'This account doesn\'t need a confirmation email anymore. Logged in before or already active.' ) );
        //     return false;
        // }

        if (!$accounts_plugin->is_password_decryption_enabled()) {
            $clean_pass = $accounts_model->clean_password($account_arr);
        } else {
            $clean_pass = $accounts_model::OBFUSCATED_PASSWORD;
        }

        $hook_args = [];
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file('confirmation');
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Account Confirmation');
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'clean_pass'      => $clean_pass,
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
                $this->set_error(self::ERR_SEND_EMAIL, $this->_pt('Error sending confirmation email to %s.', $account_arr['email']));
            }

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
