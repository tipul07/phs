<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Template;
use phs\system\core\events\actions\PHS_Event_Action_start;

class PHS_Action_Register extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_REGISTER];
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
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::REGISTER, $this))
            && !empty($event_result['action_result']) && is_array($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        PHS::page_settings('page_title', $this->_pt('Register an Account'));

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        /** @var PHS_Model_Accounts $accounts_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($accounts_model = PHS_Model_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!can(PHS_Roles::ROLEU_REGISTER)) {
            PHS_Notifications::add_error_notice($this->_pt('Registration is closed for this site.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $nick = PHS_Params::_pg('nick', PHS_Params::T_NOHTML);
        $email = PHS_Params::_pg('email', PHS_Params::T_EMAIL);
        $pass1 = PHS_Params::_p('pass1', PHS_Params::T_ASIS);
        $pass2 = PHS_Params::_p('pass2', PHS_Params::T_ASIS);
        $vcode = PHS_Params::_p('vcode', PHS_Params::T_NOHTML);
        $do_submit = PHS_Params::_p('do_submit');

        $registered = PHS_Params::_g('registered', PHS_Params::T_INT);

        if (empty($foobar)
         && PHS::user_logged_in()) {
            PHS_Notifications::add_success_notice($this->_pt('Already logged in...'));

            return action_redirect();
        }

        if (!($accounts_settings = $accounts_plugin->get_plugin_settings())) {
            $accounts_settings = [];
        }

        if (empty($accounts_settings['min_password_length'])) {
            $accounts_settings['min_password_length'] = $accounts_model::DEFAULT_MIN_PASSWORD_LENGTH;
        }

        if (!empty($registered)) {
            PHS_Notifications::add_success_notice($this->_pt('User account registered with success...'));

            $data = [
                'nick'                   => $nick,
                'email'                  => $email,
                'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
            ];

            return $this->quick_render_template('register_thankyou', $data);
        }

        $template = 'register';
        $template_data = [
            'nick'  => $nick,
            'email' => $email,
            'pass1' => $pass1,
            'pass2' => $pass2,
            'vcode' => $vcode,

            // We pass it here so hook result can stop execution
            'do_submit' => $do_submit,

            'min_password_length'    => $accounts_settings['min_password_length'],
            'password_regexp'        => $accounts_settings['password_regexp'],
            'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
        ];

        if (($event_result = PHS_Event_Template::template(PHS_Event_Template::REGISTER, $template, $template_data))) {
            if (!empty($event_result['action_result']) && is_array($event_result['action_result'])) {
                return $event_result['action_result'];
            }

            if (!empty($event_result['page_template'])) {
                $template = $event_result['page_template'];
            }
            if (!empty($event_result['page_template_args'])) {
                $template_data = $event_result['page_template_args'];
            }
        }

        if (!empty($template_data['do_submit'])) {
            /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
            /*
            if( !($captcha_plugin = PHS::load_plugin( 'captcha' )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load captcha plugin.' ) );

            else
            */

            if (($hook_result = PHS_Hooks::trigger_captcha_check($template_data['vcode'])) !== null
             && empty($hook_result['check_valid'])) {
                if (PHS_Error::arr_has_error($hook_result['hook_errors'])) {
                    PHS_Notifications::add_error_notice(self::arr_get_error_message($hook_result['hook_errors']));
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Invalid validation code.'));
                }
            } elseif ($template_data['pass1'] != $template_data['pass2']) {
                PHS_Notifications::add_error_notice($this->_pt('Passwords mistmatch.'));
            } else {
                $insert_arr = [];
                $insert_arr['nick'] = $template_data['nick'];
                $insert_arr['pass'] = $template_data['pass1'];
                $insert_arr['email'] = $template_data['email'];
                $insert_arr['level'] = $accounts_model::LVL_MEMBER;
                $insert_arr['lastip'] = request_ip();

                if (!($account_arr = $accounts_model->insert(['fields' => $insert_arr]))) {
                    if ($accounts_model->has_error()) {
                        PHS_Notifications::add_error_notice($accounts_model->get_error_message());
                    } else {
                        PHS_Notifications::add_error_notice($this->_pt('Couldn\'t register user. Please try again.'));
                    }
                } else {
                    PHS_Hooks::trigger_captcha_regeneration();
                }
            }

            if (!empty($account_arr)
             && !PHS_Notifications::have_notifications_errors()) {
                if (!$accounts_model->is_active($account_arr)) {
                    $redirect_to_url = PHS::url(['p' => 'accounts', 'a' => 'register'], ['registered' => 1, 'nick' => $nick, 'email' => $email]);
                } else {
                    $redirect_to_url = PHS::url(['p' => 'accounts', 'a' => 'login'], ['registered' => 1, 'nick' => $nick]);
                }

                return action_redirect($redirect_to_url);
            }
        }

        return $this->quick_render_template($template, $template_data);
    }
}
