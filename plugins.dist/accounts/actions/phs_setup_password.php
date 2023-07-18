<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\actions\PHS_Event_Action_start;

class PHS_Action_Setup_password extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_REGISTER, self::ACT_ROLE_CHANGE_PASSWORD, ];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::SETUP_PASSWORD, $this))
         && !empty($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        PHS::page_settings('page_title', $this->_pt('Password Setup'));

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($accounts_model = PHS_Model_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (PHS_Params::_g('setup_not_required', PHS_Params::T_INT)) {
            PHS_Notifications::add_warning_notice(
                $this->_pt('Password setup not required. Go to %s.',
                    sprintf(
                        '<a href="%s">%s</a>',
                        PHS::url(['a' => 'login', 'p' => 'accounts']),
                        $this->_pt('login page')
                    )
                ));

            return self::default_action_result();
        }

        if (!($confirmation_param = PHS_Params::_gp($accounts_plugin::PARAM_CONFIRMATION, PHS_Params::T_NOHTML))
            || !($confirmation_parts = $accounts_plugin->decode_confirmation_param($confirmation_param))
            || empty($confirmation_parts['account_data']) || empty($confirmation_parts['reason'])
            || $confirmation_parts['reason'] !== $accounts_plugin::CONF_REASON_PASS_SETUP
            || !$accounts_model->must_setup_password($confirmation_parts['account_data'])) {
            PHS_Notifications::add_warning_notice($this->_pt('Password setup link expired. Please try again.'));

            return self::default_action_result();
        }

        $account_arr = $confirmation_parts['account_data'];

        if( $accounts_model->is_locked($account_arr) ) {
            PHS_Notifications::add_error_notice($this->_pt('Account locked temporarily because of too many login attempts.'));
        }

        if (!($accounts_settings = $accounts_plugin->get_plugin_settings())) {
            $accounts_settings = [];
        }

        if (empty($accounts_settings['min_password_length'])) {
            $accounts_settings['min_password_length'] = $accounts_model::DEFAULT_MIN_PASSWORD_LENGTH;
        }

        if (($password_setup = (bool)PHS_Params::_g('password_setup', PHS_Params::T_INT))) {
            PHS_Notifications::add_success_notice($this->_pt('Password setup with success.'));
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $pass1 = PHS_Params::_p('pass1', PHS_Params::T_ASIS);
        $pass2 = PHS_Params::_p('pass2', PHS_Params::T_ASIS);

        $do_submit = PHS_Params::_p('do_submit');

        if (!empty($do_submit)
         && !PHS_Notifications::have_notifications_errors()) {
            if (empty($pass1) || empty($pass2)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide mandatory fields.'));
            } elseif ($pass1 !== $pass2) {
                PHS_Notifications::add_error_notice($this->_pt('Passwords mismatch.'));
            } else {
                $edit_arr = [];
                $edit_arr['pass'] = $pass1;

                $edit_params_arr = [];
                $edit_params_arr['fields'] = $edit_arr;

                if ($accounts_model->edit($account_arr, $edit_params_arr)) {
                    PHS_Notifications::add_success_notice($this->_pt('Password setup with success.'));

                    return action_redirect(['p' => 'accounts', 'a' => 'login'], ['nick' => $account_arr['nick'], 'password_setup' => 1]);
                }

                PHS_Notifications::add_error_notice($this->_pt('Error settings up a password. Please try again.'));
            }
        }

        $url_extra_args = [];
        if (($confirmation_parts = $accounts_plugin->get_confirmation_params($account_arr, $accounts_plugin::CONF_REASON_PASS_SETUP, ['link_expire_seconds' => 3600]))
         && !empty($confirmation_parts['confirmation_param']) && !empty($confirmation_parts['pub_key'])) {
            $url_extra_args = [$accounts_plugin::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param']];
        } elseif (!empty($confirmation_param)) {
            $url_extra_args = [$accounts_plugin::PARAM_CONFIRMATION => $confirmation_param];
        }

        $data = [
            'url_extra_args'         => $url_extra_args,
            'nick'                   => $account_arr['nick'],
            'accounts_settings'      => $accounts_settings,
            'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
            'min_password_length'    => $accounts_settings['min_password_length'],
            'password_regexp'        => $accounts_settings['password_regexp'],
            'password_setup'         => $password_setup,
        ];

        return $this->quick_render_template('setup_password', $data);
    }
}
