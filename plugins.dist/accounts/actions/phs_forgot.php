<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\captcha\PHS_Plugin_Captcha;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\actions\PHS_Event_Action_start;

class PHS_Action_Forgot extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_FORGOT_PASSWORD];
    }

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::FORGOT_PASSWORD, $this))
            && !empty($event_result['action_result']) && is_array($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        PHS::page_settings('page_title', $this->_pt('Forgot Password'));

        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (PHS::user_logged_in()) {
            PHS_Notifications::add_success_notice($this->_pt('Already logged in...'));

            return action_redirect();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $email = PHS_Params::_pg('email', PHS_Params::T_EMAIL);
        $vcode = PHS_Params::_p('vcode', PHS_Params::T_NOHTML);
        $do_submit = PHS_Params::_p('do_submit');

        if (PHS_Params::_g('email_sent', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Email with instructions sent to provided email address.'));
        }

        if ($do_submit) {
            if (!($captcha_plugin = PHS_Plugin_Captcha::get_instance())) {
                PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));
            } elseif (($hook_result = PHS_Hooks::trigger_captcha_check($vcode)) !== null
                 && empty($hook_result['check_valid'])) {
                if (PHS_Error::arr_has_error($hook_result['hook_errors'])) {
                    PHS_Notifications::add_error_notice(PHS_Error::arr_get_error_message($hook_result['hook_errors']));
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Invalid validation code.'));
                }
            } elseif (!($account_arr = $accounts_model->get_details_fields(['email' => $email, 'status' => $accounts_model::STATUS_ACTIVE]))) {
                PHS_Notifications::add_error_notice($this->_pt('Invalid account.'));
            } elseif ($accounts_model->is_locked($account_arr)) {
                PHS_Notifications::add_error_notice($this->_pt('Account locked temporarily because of too many login attempts.'));
            } else {
                if (!PHS_Bg_jobs::run(['p' => 'accounts', 'a' => 'forgot_password_bg', 'c' => 'index_bg'], ['uid' => $account_arr['id']])) {
                    PHS_Notifications::add_error_notice(
                        self::st_get_simple_error_message(
                            $this->_pt('Error sending forgot password email. Please try again.'))
                    );
                }

                PHS_Hooks::trigger_captcha_regeneration();
            }

            if (!PHS_Notifications::have_notifications_errors()) {
                return action_redirect(['p' => 'accounts', 'a' => 'forgot'], ['email_sent' => 1]);
            }
        }

        $data = [
            'email' => $email,
            'vcode' => $vcode,
        ];

        return $this->quick_render_template('forgot', $data);
    }
}
