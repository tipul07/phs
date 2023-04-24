<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\actions\PHS_Event_Action_start;

class PHS_Action_Change_password extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_CHANGE_PASSWORD, self::ACT_ROLE_PASSWORD_EXPIRED, ];
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
        if( ($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::CHANGE_PASSWORD, $this ))
            && !empty($event_result['action_result']) ) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        PHS::page_settings('page_title', $this->_pt('Change Password'));

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($accounts_model = PHS_Model_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        $forgot_account_arr = false;
        if (!($current_user = PHS::user_logged_in())) {
            if (!($confirmation_param = PHS_Params::_gp($accounts_plugin::PARAM_CONFIRMATION, PHS_Params::T_NOHTML))) {
                PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

                return action_request_login();
            }

            if (!($confirmation_parts = $accounts_plugin->decode_confirmation_param($confirmation_param))
             || empty($confirmation_parts['account_data']) || empty($confirmation_parts['reason'])
             || $confirmation_parts['reason'] !== $accounts_plugin::CONF_REASON_FORGOT) {
                if ($accounts_plugin->has_error()) {
                    PHS_Notifications::add_error_notice($accounts_plugin->get_error_message());
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Couldn\'t interpret confirmation parameter. Please try again.'));
                }
            } else {
                $forgot_account_arr = $confirmation_parts['account_data'];
            }
        }

        if (!($accounts_settings = $accounts_plugin->get_plugin_settings())) {
            $accounts_settings = [];
        }

        if (empty($accounts_settings['min_password_length'])) {
            $accounts_settings['min_password_length'] = $accounts_model::DEFAULT_MIN_PASSWORD_LENGTH;
        }

        if (!($external_args = PHS_Params::_gp('external_args', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_ASIS]))) {
            $external_args = [];
        }

        if (PHS_Params::_g('password_expired', PHS_Params::T_INT)) {
            PHS_Notifications::add_warning_notice($this->_pt('Your password expired. For security reasons, please change it.'));
        }

        if (($password_changed = (bool)PHS_Params::_g('password_changed', PHS_Params::T_INT))) {
            PHS_Notifications::add_success_notice($this->_pt('Password changed with success.'));
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $pass = PHS_Params::_p('pass', PHS_Params::T_ASIS);
        $pass1 = PHS_Params::_p('pass1', PHS_Params::T_ASIS);
        $pass2 = PHS_Params::_p('pass2', PHS_Params::T_ASIS);

        $do_submit = PHS_Params::_p('do_submit');

        if (!empty($do_submit)
         && !PHS_Notifications::have_notifications_errors()) {
            if ((!empty($current_user) && empty($pass))
             || empty($pass1) || empty($pass2)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide mandatory fields.'));
            } elseif (!empty($current_user) && !$accounts_model->check_pass($current_user, $pass)) {
                PHS_Notifications::add_error_notice($this->_pt('Wrong current password.'));
            } elseif ($pass1 !== $pass2) {
                PHS_Notifications::add_error_notice($this->_pt('Passwords mismatch.'));
            } else {
                $edit_arr = [];
                $edit_arr['pass'] = $pass1;

                $edit_params_arr = [];
                $edit_params_arr['fields'] = $edit_arr;

                $change_for_account = (!empty($current_user) ? $current_user : $forgot_account_arr);

                if ($accounts_model->edit($change_for_account, $edit_params_arr)) {
                    PHS_Notifications::add_success_notice($this->_pt('Password changed with success.'));

                    $args_arr = $external_args;
                    $args_arr['password_changed'] = 1;

                    if (!empty($current_user)) {
                        $redirect_to_url = PHS::url(['p' => 'accounts', 'a' => 'change_password'], $args_arr);
                    } else {
                        if (!empty($forgot_account_arr)) {
                            $args_arr['nick'] = $forgot_account_arr['nick'];
                        }

                        $redirect_to_url = PHS::url(['p' => 'accounts', 'a' => 'login'], $args_arr);
                    }

                    return action_redirect($redirect_to_url);
                }

                if (PHS::st_debugging_mode()
                 && $accounts_model->has_error()) {
                    PHS_Logger::debug('ERROR chaning password for account #'.$change_for_account['id'].': '.$accounts_model->get_error_message(),
                        PHS_Logger::TYPE_DEBUG);
                }

                PHS_Notifications::add_error_notice($this->_pt('Error changing password. Please try again.'));
            }
        }

        $url_extra_args = [];
        if (!empty($forgot_account_arr)) {
            if (!($confirmation_parts = $accounts_plugin->get_confirmation_params($forgot_account_arr, $accounts_plugin::CONF_REASON_FORGOT, ['link_expire_seconds' => 3600]))
             || empty($confirmation_parts['confirmation_param']) || empty($confirmation_parts['pub_key'])) {
                $url_extra_args = [$accounts_plugin::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param']];
            } elseif (!empty($confirmation_param)) {
                $url_extra_args = [$accounts_plugin::PARAM_CONFIRMATION => $confirmation_param];
            }
        }

        $data = [
            'external_args'          => $external_args,
            'url_extra_args'         => $url_extra_args,
            'nick'                   => (!empty($current_user) ? $current_user['nick'] : 'N/A'),
            'pass'                   => $pass,
            'accounts_settings'      => $accounts_settings,
            'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
            'min_password_length'    => $accounts_settings['min_password_length'],
            'password_regexp'        => $accounts_settings['password_regexp'],
            'password_changed'       => $password_changed,
        ];

        return $this->quick_render_template('change_password', $data);
    }
}
