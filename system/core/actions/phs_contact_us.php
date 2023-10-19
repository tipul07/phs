<?php
namespace phs\system\core\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;

class PHS_Action_Contact_us extends PHS_Action
{
    public const ERR_SEND_EMAIL = 40000;

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $email = PHS_Params::_pg('email', PHS_Params::T_NOHTML);
        $subject = PHS_Params::_pg('subject', PHS_Params::T_NOHTML);
        $body = PHS_Params::_pg('body', PHS_Params::T_NOHTML);
        $vcode = PHS_Params::_p('vcode', PHS_Params::T_NOHTML);
        $do_submit = PHS_Params::_p('do_submit');

        $sent = PHS_Params::_g('sent', PHS_Params::T_INT);

        if (!empty($sent)) {
            PHS_Notifications::add_success_notice(self::_t('Your message was succesfully sent. Thank you!'));
        }

        if (!($user_logged_in = PHS::user_logged_in())) {
            $user_logged_in = false;
        }
        if (!($current_user = PHS::current_user())) {
            $current_user = false;
        }

        if (!empty($user_logged_in)
         && empty($foobar)) {
            $email = $current_user['email'];
        }

        if (!can(PHS_Roles::ROLEU_CONTACT_US)) {
            PHS_Notifications::add_error_notice(self::_t('You don\'t have rights to access this section.'));
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!empty($do_submit)
         && !PHS_Notifications::have_notifications_errors()) {
            $emails_arr = [];
            if (defined('PHS_CONTACT_EMAIL')
             && ($emails_str = constant('PHS_CONTACT_EMAIL'))
             && ($emails_parts_arr = explode(',', $emails_str))
             && is_array($emails_parts_arr)) {
                foreach ($emails_parts_arr as $email_addr) {
                    $email_addr = trim($email_addr);
                    if (empty($email_addr)
                     || !PHS_Params::check_type($email_addr, PHS_Params::T_EMAIL)) {
                        PHS_Notifications::add_error_notice('['.$email_addr.'] doesn\'t seem to be an email address. Please change your PHS_CONTACT_EMAIL constant in main.php file.');
                        continue;
                    }

                    $emails_arr[] = $email_addr;
                }
            }

            if (empty($emails_arr)) {
                PHS_Notifications::add_error_notice(self::_t('No email addresses setup in the platform.'));
            } elseif (empty($email) || empty($subject) || empty($body)
             || (empty($user_logged_in) && empty($vcode))) {
                PHS_Notifications::add_error_notice(self::_t('Please provide mandatory fields in the form.'));
            } elseif (!PHS_Params::check_type($email, PHS_Params::T_EMAIL)) {
                PHS_Notifications::add_error_notice(self::_t('Please provide a valid email address.'));
            } elseif (empty($user_logged_in)
                && !($captcha_plugin = PHS::load_plugin('captcha'))) {
                PHS_Notifications::add_error_notice(self::_t('Couldn\'t load captcha plugin.'));
            } elseif (empty($user_logged_in)
            && ($hook_result = PHS_Hooks::trigger_captcha_check($vcode)) !== null
            && empty($hook_result['check_valid'])) {
                if (PHS_Error::arr_has_error($hook_result['hook_errors'])) {
                    PHS_Notifications::add_error_notice(PHS_Error::arr_get_error_message($hook_result['hook_errors']));
                } else {
                    PHS_Notifications::add_error_notice(self::_t('Invalid validation code.'));
                }
            }

            if (!PHS_Notifications::have_notifications_errors()) {
                PHS_Hooks::trigger_captcha_regeneration();

                $hook_args = [];
                $hook_args['template'] = ['file' => 'contact_us'];
                $hook_args['from'] = $email;
                $hook_args['from_name'] = self::_t('Site Contact');
                $hook_args['subject'] = self::_t('Contact Us: %s', $subject);
                $hook_args['email_vars'] = [
                    'current_user' => (!empty($user_logged_in) ? $current_user : false),
                    'user_agent'   => (!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : self::_t('N/A')),
                    'request_ip'   => request_ip(),
                    'subject'      => $subject,
                    'email'        => $email,
                    'body'         => str_replace('  ', '&nbsp; ', nl2br($body)),
                ];

                $email_failed = true;
                foreach ($emails_arr as $email_address) {
                    $hook_args['to'] = $email_address;
                    $hook_args['to_name'] = self::_t('Site Contact');

                    if (!($hook_results = PHS_Hooks::trigger_email($hook_args))
                     || !is_array($hook_results)
                     || empty($hook_results['send_result'])) {
                        PHS_Logger::error(self::_t('Error sending email from contact form to [%s].', $email_address),
                            PHS_Logger::TYPE_DEBUG);
                    } else {
                        $email_failed = false;
                    }
                }

                if (!$email_failed) {
                    return action_redirect(['a' => 'contact_us'], ['sent' => 1]);
                }

                PHS_Notifications::add_error_notice(self::_t('Failed sending email. Please try again.'));
            }
        }

        $data = [
            'email'   => $email,
            'subject' => $subject,
            'body'    => $body,
            'vcode'   => $vcode,
        ];

        return $this->quick_render_template('contact_us', $data);
    }
}
