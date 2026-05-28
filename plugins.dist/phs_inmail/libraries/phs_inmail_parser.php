<?php
namespace phs\plugins\phs_inmail\libraries;

use phs\libraries\PHS_Library;
use phs\system\core\attributes\PHS_Dependency;
use phs\system\core\libraries\PHS_Mime_parser;
use phs\plugins\phs_inmail\PHS_Plugin_Phs_inmail;

class PHS_Inmail_parser extends PHS_Library
{
    #[PHS_Dependency]
    private ?PHS_Plugin_Phs_inmail $_inmail_plugin = null;

    public function check_incoming_email_from_buffer(string $buf) : bool
    {
        $this->reset_error();

        if (!$this->_inmail_plugin->is_inmail_enabled()) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Incoming email is not enabled.'));

            return false;
        }

        if (!($mime_lib = PHS_Mime_parser::get_instance(as_singleton: true))) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        $mime_lib->set_buffer($buf);

        return $this->_check_incoming_email($mime_lib);
    }

    public function check_incoming_email_from_file(string $file) : bool
    {
        $this->reset_error();

        if (!$this->_inmail_plugin->is_inmail_enabled()) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Incoming email is not enabled.'));

            return false;
        }

        if (!($mime_lib = PHS_Mime_parser::get_instance(as_singleton: true))) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        if (!$mime_lib->set_input_file($file)) {
            $this->copy_or_set_error($mime_lib,
                self::ERR_FUNCTIONALITY, $this->_pt('Error using email file in incoming email.'));

            return false;
        }

        return $this->_check_incoming_email($mime_lib);
    }

    private function _check_incoming_email(PHS_Mime_parser $mime_lib) : bool
    {
        if (!$this->_check_incoming_email_conditions($mime_lib)) {
            return false;
        }
    }

    private function _check_incoming_email_conditions(PHS_Mime_parser $mime_lib) : bool
    {
        $logic_condition = $this->_inmail_plugin->get_logic_condition();

        $to_emails = $this->_inmail_plugin->get_to_field_emails();
        $cc_emails = $this->_inmail_plugin->get_cc_field_emails();
        $bcc_emails = $this->_inmail_plugin->get_bcc_field_emails();
        $subject_regex = $this->_inmail_plugin->get_subject_regex();
        $has_attachment = $this->_inmail_plugin->get_has_attachment();

        if (!$to_emails
           && !$cc_emails
           && !$bcc_emails
           && !$subject_regex
           && $has_attachment === null) {
            return true;
        }

        if ($to_emails
           && null !== ($cond = $this->_check_email_list($to_emails, $logic_condition,
               fn(string $email) => $mime_lib->email_to_contains_email($email))
           )) {
            return $cond;
        }

        if ($cc_emails
           && null !== ($cond = $this->_check_email_list($cc_emails, $logic_condition,
               fn(string $email) => $mime_lib->email_cc_contains_email($email))
           )) {
            return $cond;
        }

        if ($bcc_emails
           && null !== ($cond = $this->_check_email_list($bcc_emails, $logic_condition,
               fn(string $email) => $mime_lib->email_bcc_contains_email($email))
           )) {
            return $cond;
        }

        if ($subject_regex) {
            $result = ($subject = $mime_lib->get_email_subject())
                      && @preg_match('/'.$subject_regex.'/i', $subject);

            if (null !== ($cond = $this->_check_condition_result_with_logic_condition($result, $logic_condition))) {
                return $cond;
            }
        }

        if ($has_attachment !== null) {
            $result = $has_attachment && $mime_lib->has_attachments();

            if (null !== ($cond = $this->_check_condition_result_with_logic_condition($result, $logic_condition))) {
                return $cond;
            }
        }

        return true;
    }

    private function _check_email_list(array $emails_arr, string $logic_condition, callable $callback) : ?bool
    {
        $cond = false;
        foreach ($emails_arr as $email) {
            if (!$callback($email)) {
                continue;
            }

            $cond = true;
            break;
        }

        return $this->_check_condition_result_with_logic_condition($cond, $logic_condition);
    }

    private function _check_condition_result_with_logic_condition(bool $result, string $logic_condition) : ?bool
    {
        if ($result) {
            if ($logic_condition === $this->_inmail_plugin::COND_OR) {
                return true;
            }
        } elseif ($logic_condition === $this->_inmail_plugin::COND_AND) {
            return false;
        }

        return null;
    }
}
