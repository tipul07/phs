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
use phs\system\core\libraries\PHS_Email;
use phs\plugins\captcha\PHS_Plugin_Captcha;

class PHS_Action_Contact_us extends PHS_Action
{
    public function allowed_scopes() : array
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

        if (PHS_Params::_g('sent', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice(self::_t('Your message was succesfully sent. Thank you!'));
        }

        $user_logged_in = (bool)PHS::user_logged_in();
        $current_user = PHS::current_user();

        if ($current_user && !$foobar) {
            $email = $current_user['email'];
        }

        if (!can(PHS_Roles::ROLEU_CONTACT_US)) {
            PHS_Notifications::add_error_notice(self::_t('You don\'t have rights to access this section.'));
        }

        if ($do_submit
            && !PHS_Notifications::have_notifications_errors()) {
            $emails_arr = [];
            if (defined('PHS_CONTACT_EMAIL')
                && (($emails_str = constant('PHS_CONTACT_EMAIL')) ?: '')
                && ($emails_parts_arr = self::extract_strings_from_comma_separated($emails_str))) {
                foreach ($emails_parts_arr as $email_addr) {
                    if (!$email_addr
                        || !PHS_Params::check_type($email_addr, PHS_Params::T_EMAIL)) {
                        PHS_Notifications::add_error_notice('Invalid contact email address. Make sure contact email address is set.');
                        continue;
                    }

                    $emails_arr[] = $email_addr;
                }
            }

            if (!$emails_arr) {
                PHS_Notifications::add_error_notice(self::_t('No email addresses setup in the platform.'));
            } elseif (empty($email) || empty($subject) || empty($body)
                      || (!$current_user && !$vcode)) {
                PHS_Notifications::add_error_notice(self::_t('Please provide mandatory fields in the form.'));
            } elseif (!PHS_Params::check_type($email, PHS_Params::T_EMAIL)) {
                PHS_Notifications::add_error_notice(self::_t('Please provide a valid email address.'));
            } elseif (!$current_user
                      && !PHS_Plugin_Captcha::get_instance()) {
                PHS_Notifications::add_error_notice(self::_t('Couldn\'t load captcha plugin.'));
            } elseif (!$current_user
                      && ($hook_result = PHS_Hooks::trigger_captcha_check($vcode)) !== null
                      && empty($hook_result['check_valid'])) {
                PHS_Notifications::add_error_notice(
                    PHS_Error::arr_get_simple_error_message(
                        $hook_result['hook_errors'], self::_t('Invalid validation code.'))
                );
            }

            $email_obj
                = PHS_Email::get_instance()
                    ?->template('contact_us')
                    ->from($email, self::_t('Site Contact'))
                    ->subject(self::_t('Contact Us: %s', $subject))
                    ->email_variables([
                        'current_user' => ($user_logged_in ? $current_user : null),
                        'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? self::_t('N/A'),
                        'request_ip'   => request_ip(),
                        'subject'      => $subject,
                        'email'        => $email,
                        'body'         => str_replace('  ', '&nbsp; ', nl2br($body)),
                    ]);

            if (!$email_obj || $email_obj->has_error()) {
                PHS_Notifications::add_error_notice(
                    self::_t('Error obtaining email instance: %s',
                        $email_obj?->get_simple_error_message(self::_t('Unknown error')) ?? self::_t('Unknown error')
                    )
                );
            }

            if (!PHS_Notifications::have_notifications_errors()) {
                PHS_Hooks::trigger_captcha_regeneration();

                $email_failed = true;
                foreach ($emails_arr as $email_address) {
                    $email_obj->to($email_address, self::_t('Site Contact'));

                    if ($email_obj->send()) {
                        $email_failed = false;
                        continue;
                    }

                    PHS_Logger::error(
                        self::_t('Error sending email from contact form to [%s].', $email_address),
                        PHS_Logger::TYPE_DEBUG
                    );

                    $email_obj->reset_error();
                }

                if (!$email_failed) {
                    return action_redirect(['a' => 'contact_us'], ['sent' => 1]);
                }

                PHS_Notifications::add_error_notice(self::_t('Failed sending email. Please try again.'));
            }
        }

        return $this->quick_render_template('contact_us', [
            'email'   => $email,
            'subject' => $subject,
            'body'    => $body,
            'vcode'   => $vcode,
        ]);
    }
}
