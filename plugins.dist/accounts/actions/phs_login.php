<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Session;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\actions\PHS_Event_Action_after;
use phs\system\core\events\actions\PHS_Event_Action_start;

class PHS_Action_Login extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_LOGIN];
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
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::LOGIN, $this))
            && !empty($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        PHS::page_settings('page_title', $this->_pt('Login'));

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $nick = PHS_Params::_pg('nick', PHS_Params::T_NOHTML);
        $pass = PHS_Params::_p('pass', PHS_Params::T_NOHTML);
        $do_remember = PHS_Params::_pg('do_remember', PHS_Params::T_INT);
        $do_submit = PHS_Params::_p('do_submit');

        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML);

        $reason = PHS_Params::_g('reason', PHS_Params::T_NOHTML);

        if (PHS_Params::_g('expired_secs', PHS_Params::T_INT)) {
            PHS_Notifications::add_warning_notice($this->_pt('Your session expired. Please login again into your account.'));
        }

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load accounts plugin.'));
        }

        if (!empty($accounts_plugin)
         && !empty($reason)
         && ($reason_success_text = $accounts_plugin->valid_confirmation_reason($reason))) {
            PHS_Notifications::add_success_notice($reason_success_text);
        }

        if (PHS_Params::_g('registered', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Account registered and active. You can login now.'));
        }
        if (PHS_Params::_g('password_changed', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Password changed with success. You can login now.'));
        }
        if (PHS_Params::_g('confirmation_email', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('An email with your password was sent to email provided in your account details.'));
        }

        if (empty($foobar)
         && PHS::user_logged_in()
         && !PHS_Notifications::have_notifications_errors()) {
            if (($event_result = PHS_Event_Action_after::action(PHS_Event_Action_after::LOGIN, $this))
                && !empty($event_result['action_result'])) {
                $this->set_action_result($event_result['action_result']);

                return $event_result['action_result'];
            }

            PHS_Notifications::add_success_notice($this->_pt('Already logged in...'));

            return action_redirect(!empty($back_page) ? from_safe_url($back_page) : PHS::url());
        }

        if (!($plugin_settings = $this->get_plugin_settings())) {
            $plugin_settings = [];
        }

        if (empty($plugin_settings['session_expire_minutes_remember'])) {
            $plugin_settings['session_expire_minutes_remember'] = 43200;
        } // 30 days
        if (empty($plugin_settings['session_expire_minutes_normal'])) {
            $plugin_settings['session_expire_minutes_normal'] = 0;
        } // till browser closes

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!empty($do_submit)
         && !PHS_Notifications::have_notifications_errors()) {
            if (empty($nick) || empty($pass)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide complete mandatory fields.'));
            } elseif (!($accounts_model = PHS_Model_Accounts::get_instance())) {
                PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load accounts model.'));
            } elseif (!($account_arr = $accounts_model->get_details_fields(['nick' => $nick]))
                 || !$accounts_model->is_active($account_arr)) {
                PHS_Notifications::add_error_notice($this->_pt('Bad username or password.'));
            } elseif (!$accounts_model->is_locked($account_arr)
                      && !$accounts_model->check_pass($account_arr, $pass)) {

                if( ($new_account = $accounts_model->manage_failed_password($account_arr)) ) {
                    $account_arr = $new_account;
                }

                PHS_Notifications::add_error_notice($this->_pt('Bad username or password.'));
            } elseif (!$accounts_model->is_locked($account_arr) ) {
                $login_params = [];
                $login_params['expire_mins'] = (!empty($do_remember) ? $plugin_settings['session_expire_minutes_remember'] : $plugin_settings['session_expire_minutes_normal']);

                if ($accounts_plugin->do_login($account_arr, $login_params)) {
                    if (($account_language = $accounts_model->get_account_language($account_arr))) {
                        if (!($current_language = self::get_current_language())
                         || $current_language !== $account_language) {
                            self::set_current_language($account_language);
                            PHS_Session::_s(self::LANG_SESSION_KEY, $account_language);
                        }
                    }

                    if (($event_result = PHS_Event_Action_after::action(PHS_Event_Action_after::LOGIN, $this))
                        && !empty($event_result['action_result'])) {
                        $this->set_action_result($event_result['action_result']);

                        return $event_result['action_result'];
                    }

                    PHS_Notifications::add_success_notice($this->_pt('Successfully logged in...'));

                    return action_redirect(!empty($back_page) ? from_safe_url($back_page) : PHS::url());
                }

                if ($accounts_plugin->has_error()) {
                    PHS_Notifications::add_error_notice($accounts_plugin->get_simple_error_message());
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Error logging in... Please try again.'));
                }
            }

            if (!empty($account_arr)
                && $accounts_model->is_locked($account_arr)) {
                PHS_Notifications::add_error_notice($this->_pt('Account locked temporarily because of too many login attempts.'));
            }
        }

        $data = [
            'back_page'                   => $back_page,
            'nick'                        => $nick,
            'pass'                        => $pass,
            'remember_me_session_minutes' => $plugin_settings['session_expire_minutes_remember'],
            'normal_session_minutes'      => $plugin_settings['session_expire_minutes_normal'],
            'no_nickname_only_email'      => $plugin_settings['no_nickname_only_email'],
            'do_remember'                 => (!empty($do_remember) ? 'checked="checked"' : ''),
        ];

        return $this->quick_render_template('login', $data);
    }
}
