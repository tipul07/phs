<?php
namespace phs\plugins\phs_inmail\libraries;

use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;
use phs\system\core\attributes\PHS_Dependency;
use phs\system\core\libraries\PHS_Mime_parser;
use phs\plugins\phs_inmail\PHS_Plugin_Phs_inmail;
use phs\plugins\phs_inmail\events\PHS_Event_Inmail_new;

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
        if (!$this->_check_incoming_email_conditions($mime_lib)
            || null === ($attachments_arr = $this->_convert_attachments_to_files($mime_lib))) {
            return false;
        }

        if (!PHS_Event_Inmail_new::trigger(
            [
                'to_list'          => $mime_lib->get_email_to_as_recipients(),
                'cc_list'          => $mime_lib->get_email_cc(),
                'bcc_list'         => $mime_lib->get_email_bcc(),
                'subject'          => $mime_lib->get_email_subject(),
                'text_body'        => $mime_lib->get_email_text_body(),
                'html_body'        => $mime_lib->get_email_html_body(),
                'attachment_files' => $attachments_arr['files'] ?? [],
                'attachments_dir'  => $attachments_arr['directory'] ?? '',
                'mime_obj'         => $mime_lib,
            ]
        )) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error triggering incoming email event.'));

            return false;
        }

        return true;
    }

    private function _convert_attachments_to_files(PHS_Mime_parser $mime_lib) : ?array
    {
        if (!($attachments_arr = $mime_lib->get_email_attachments())) {
            return [];
        }

        if (!($event_dir = $this->_prepare_attachments_dir($mime_lib))) {
            return null;
        }

        $accepted_extensions = $this->_inmail_plugin->get_accept_attachments_ext();
        $parsing_id = $mime_lib->get_email_parsing_id();

        $return_arr = [];
        $return_arr['files'] = [];
        $return_arr['directory'] = $event_dir;

        foreach ($attachments_arr as $attachment) {
            if (empty($attachment['content'])) {
                continue;
            }

            $attachment_id = $attachment['filename'] ?? $attachment['content_id'] ?? 'N/A';

            if (!($ext = $this->_get_attachment_extension($attachment))) {
                PHS_Logger::warning(
                    'Couldn\'t obtain attachment file extension for inmail #'
                    .$parsing_id.', attachment #'.$attachment_id.'.',
                    $this->_inmail_plugin::LOG_CHANNEL
                );

                continue;
            }

            if ($accepted_extensions
               && !in_array($ext, $accepted_extensions, true)) {
                PHS_Logger::warning('Attachment file extension ['.$ext.'] is not accepted for inmail #'
                                    .$parsing_id.', attachment #'.$attachment_id.'.',
                    $this->_inmail_plugin::LOG_CHANNEL
                );

                continue;
            }

            $filename = microtime(true).'.'.$ext;

            $filepath = $event_dir.'/'.$filename;

            if (!@file_put_contents($filepath, $attachment['content'])) {
                PHS_Logger::warning('Error writing attachment file for inmail #'
                                    .$parsing_id.', attachment #'.$attachment_id.', path ['.$filepath.'].',
                    $this->_inmail_plugin::LOG_CHANNEL
                );

                $this->set_error(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error writing email attachment file.'));

                return null;
            }

            $return_arr['files'][] = [
                'original_name'  => $attachment['filename'] ?? '',
                'file_extension' => $ext,
                'file_name'      => $filename,
                'file_path'      => $filepath,
            ];
        }

        return $return_arr;
    }

    private function _get_attachment_extension(array $attachment) : string
    {
        $file_ext = '';
        if (!empty($attachment['filename'])) {
            if (($file_dots_arr = explode('.', $attachment['filename']))
                && is_array($file_dots_arr) && count($file_dots_arr) > 1) {
                $file_ext = array_pop($file_dots_arr);
            }
        } elseif (!empty($attachment['content_type'])) {
            $file_ext = PHS_Utils::mimetype_to_extension($attachment['content_type']);
        }

        return strtolower($file_ext);
    }

    private function _prepare_attachments_dir(PHS_Mime_parser $mime_lib) : ?string
    {
        $inmail_dir = $this->_inmail_plugin->get_inmail_dir(false);

        if (!@file_exists($inmail_dir)
            && !@is_dir($inmail_dir)
            && !@is_writable($inmail_dir)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Inmail directory is not writeable.'));

            return null;
        }

        $event_dir = $inmail_dir.'/'.$mime_lib->get_email_parsing_id();
        if (!@file_exists($event_dir)
            && !@mkdir($event_dir, 0775)
            && !@is_dir($event_dir)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_pt('Error creating inmail attachments directory.'));

            return null;
        }

        return $event_dir;
    }

    private function _check_incoming_email_conditions(PHS_Mime_parser $mime_lib) : bool
    {
        $logic_condition = $this->_inmail_plugin->get_logic_condition();

        $from_emails = $this->_inmail_plugin->get_from_field_emails();
        $to_emails = $this->_inmail_plugin->get_to_field_emails();
        $cc_emails = $this->_inmail_plugin->get_cc_field_emails();
        $bcc_emails = $this->_inmail_plugin->get_bcc_field_emails();
        $subject_regex = $this->_inmail_plugin->get_subject_regex();
        $has_attachment = $this->_inmail_plugin->get_has_attachment();

        if (!$from_emails
           && !$to_emails
           && !$cc_emails
           && !$bcc_emails
           && !$subject_regex
           && $has_attachment === null) {
            return true;
        }

        if ($from_emails
           && null !== ($cond = $this->_check_email_list($from_emails, $logic_condition,
               fn(string $email) => $mime_lib->email_from_contains_email($email))
           )) {
            return $cond;
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
