<?php
namespace phs\system\core\libraries;

use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\system\core\views\PHS_View_email;
use phs\system\core\events\emails\PHS_Event_Emails_send;
use phs\system\core\events\emails\PHS_Event_Emails_settings;

class PHS_Email extends PHS_Library
{
    public const ERR_ATTACHMENTS = 40000, ERR_SEND = 40001, ERR_TEMPLATE = 40002;

    public const DEFAULT_MAIN_TEMPLATE = 'template_emails';

    private ?string $_to = null;

    private ?string $_to_name = null;

    private ?string $_from_name = null;

    private ?string $_from_email = null;

    private ?string $_reply_name = null;

    private ?string $_reply_email = null;

    private ?string $_no_reply_name = null;

    private ?string $_no_reply_email = null;

    private ?string $_force_language = null;

    private ?string $_main_template = null;

    private ?string $_subject = null;

    private ?string $_full_body = null;

    private ?array $_template = null;

    private ?PHS_Plugin $_template_plugin = null;

    private array $_attach_files = [];

    private bool $_with_priority = false;

    private bool $_as_noreply = false;

    private array $_custom_headers = [];

    private array $_email_vars = [];

    private bool $_enriched_vars = false;

    public function subject(string $subject) : self
    {
        $this->_subject = $subject;

        return $this;
    }

    public function full_body(string $full_body) : self
    {
        $this->_full_body = $full_body;

        return $this;
    }

    public function to(string $to_email, ?string $to_name = null) : self
    {
        if (!PHS_Params::check_type($to_email, PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid TO email address.'));

            return $this;
        }

        $this->_to = $to_email;
        $this->_to_name = $to_name;

        return $this;
    }

    public function from(string $from_email, ?string $from_name = null) : self
    {
        if (!PHS_Params::check_type($from_email, PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid FROM email address.'));

            return $this;
        }

        $this->_from_email = $from_email;
        $this->_from_name = $from_name;

        return $this;
    }

    public function reply(string $reply_email, ?string $reply_name = null) : self
    {
        if (!PHS_Params::check_type($reply_email, PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid REPLY email address.'));

            return $this;
        }

        $this->_reply_email = $reply_email;
        $this->_reply_name = $reply_name;

        return $this;
    }

    public function no_reply(string $no_reply_email, ?string $no_reply_name = null) : self
    {
        if (!PHS_Params::check_type($no_reply_email, PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid NO REPLY email address.'));

            return $this;
        }

        $this->_no_reply_email = $no_reply_email;
        $this->_no_reply_name = $no_reply_name;

        return $this;
    }

    public function force_language(string $force_language) : self
    {
        $this->_force_language = $force_language;

        return $this;
    }

    public function with_priority(bool $with_priority = true) : self
    {
        $this->_with_priority = $with_priority;

        return $this;
    }

    public function as_noreply(bool $as_noreply = true) : self
    {
        $this->_as_noreply = $as_noreply;

        return $this;
    }

    public function headers(array $custom_headers) : self
    {
        $this->_custom_headers = array_merge($this->_custom_headers, $custom_headers);

        return $this;
    }

    public function email_variables(array $email_vars) : self
    {
        $this->_email_vars = array_merge($this->_email_vars, $email_vars);

        return $this;
    }

    public function main_template(string $template) : self
    {
        $this->_main_template = $template;

        return $this;
    }

    public function template(
        string | array $template,
        null | string | PHS_Plugin $plugin = null,
        ?string $force_language = null
    ) : self {
        $this->reset_error();

        if (is_array($template)) {
            $extra_paths = ($template['extra_paths'] ?? []);
            $file = ($template['file'] ?? '');

            $this->_template = [
                'file'        => is_string($file) ? $file : '',
                'extra_paths' => is_array($extra_paths) ? $extra_paths : [],
            ];

            return $this;
        }

        if ($plugin) {
            if (is_string($plugin)) {
                if (!($plugin_obj = $plugin::get_instance())
                    || !($plugin_obj instanceof PHS_Plugin)) {
                    $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin class.'));

                    return $this;
                }

                $plugin = $plugin_obj;
            }

            if (!($plugin instanceof PHS_Plugin)) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin object for email template.'));

                return $this;
            }
        }

        $this->_template_plugin = $plugin;
        $this->_template = $plugin
            ? $plugin->email_template_resource_from_file($template, $force_language ?? $this->_force_language)
            : PHS_View_email::validate_template_resource($template);

        return $this;
    }

    public function attach_files(array $attach_files) : self
    {
        $email_settings = PHS_Event_Emails_settings::get_settings();

        $default_file_details = $this->_file_attachment_details();
        $attachments = [];
        foreach ($attach_files as $knti => $file_details) {
            if (empty($file_details) || !is_array($file_details)) {
                continue;
            }

            $file_details = self::validate_array($file_details, $default_file_details);

            if (!empty($file_details['file']) && empty($file_details['file_name'])) {
                $file_details['file_name'] = @basename($file_details['file']);
            }

            if ((empty($file_details['file_base64_buffer']) && empty($file_details['file']))
                || (!empty($file_details['file_base64_buffer']) && empty($file_details['file_name']))
                || (!empty($file_details['file']) && !@file_exists($file_details['file']))) {
                $this->set_error(
                    self::ERR_ATTACHMENTS,
                    $this->_pt('Invalid parameters for attachment #%s.', $knti)
                );

                break;
            }

            if (empty($file_details['content_disposition'])
                || !in_array($file_details['content_disposition'], ['attachment', 'inline'])) {
                $file_details['content_disposition'] = 'attachment';
            }

            if (!empty($file_details['file_base64_buffer'])) {
                $file_details['size'] = strlen(base64_decode($file_details['file_base64_buffer']) ?: '');
            } elseif (!empty($file_details['file'])) {
                if (false === ($file_size = @filesize($file_details['file']))
                    || false === ($file_content = @file_get_contents($file_details['file']))) {
                    $this->set_error(
                        self::ERR_ATTACHMENTS,
                        $this->_pt('Couldn\'t obtain attachment file content for attachment #%s.', $knti)
                    );

                    break;
                }

                $file_details['size'] = $file_size;
                $file_details['file_base64_buffer'] = $file_details['transfer_encoding'] === 'base64'
                    ? @base64_encode($file_content)
                    : $file_content;

                // Free up some memory
                unset($file_content);
            }

            if ($file_details['size'] && !empty($email_settings['max_attachment_size'])
               && $file_details['size'] > $email_settings['max_attachment_size']) {
                $this->set_error(
                    self::ERR_ATTACHMENTS,
                    $this->_pt('Attachment #%s exceeds maximum allowed size %s.',
                        $knti, $email_settings['max_attachment_size']
                    )
                );

                break;
            }

            $file_details['file_base64_buffer'] = $file_details['file_base64_buffer'] ?: '';

            $attachments[] = $file_details;
        }

        if ($this->has_error()) {
            unset($attachments);

            return $this;
        }

        $this->_attach_files = $attachments;

        return $this;
    }

    public function send() : bool
    {
        if ($this->has_error()) {
            return false;
        }

        if (!$this->_to
           || !PHS_Params::check_type($this->_to, PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_SEND, $this->_pt('Destination is not an email.'));

            return false;
        }

        $this->_check_email_parameters();

        $template_params = [
            'theme_relative_dirs' => [PHS_EMAILS_DIRS],
        ];

        if (!($main_template = PHS_View_email::validate_template_resource(
            $this->_main_template ?? self::DEFAULT_MAIN_TEMPLATE,
            $template_params
        ))) {
            $this->set_error(
                self::ERR_TEMPLATE, self::_t('Failed validating main email template file.')
            );

            return false;
        }

        $view_params = [];
        $view_params['plugin_obj'] = $this->_template_plugin;
        $view_params['template_data'] = [
            'email_obj'  => $this,
            'email_vars' => $this->get_email_vars(),
        ];

        if ($this->_full_body) {
            $body_buffer = $this->_full_body;
        } else {
            if (!$this->_template) {
                $this->set_error(self::ERR_TEMPLATE, $this->_pt('Please provide an email template file.'));

                return false;
            }

            if (!($body_template = PHS_View_email::init_view($this->_template, $view_params))
                || null === ($body_buffer = $body_template->render(force_language: $this->_force_language))) {
                $this->copy_or_set_static_error(
                    self::ERR_TEMPLATE,
                    $this->_pt('Failed rendering email template file.')
                );

                return false;
            }
        }

        $view_params['template_data']['email_content'] = $body_buffer;

        if (!($email_template = PHS_View_email::init_view($main_template, $view_params))
           || null === ($email_html_body = $email_template->render(force_language: $this->_force_language))) {
            $this->copy_or_set_static_error(
                self::ERR_TEMPLATE,
                $this->_pt('Failed rendering email template file.')
            );

            return false;
        }

        $email_text_body = strip_tags(preg_replace("/[\r\n]+/", "\n",
            str_ireplace(['<p>', '</p>'], "\n", preg_replace('/\<br(\s*)?\/?\>/i', "\n", $email_html_body))));

        if (!($event_obj = PHS_Event_Emails_send::trigger([
            'force_language' => $this->_force_language,
            'to'             => $this->_to,
            'to_name'        => $this->_to_name,
            'from_name'      => $this->_from_name,
            'from_email'     => $this->_from_email,
            'reply_name'     => $this->_reply_name,
            'reply_email'    => $this->_reply_email,
            'subject'        => $this->_subject,

            'with_priority'   => $this->_with_priority,
            'custom_headers'  => $this->_custom_headers,
            'email_html_body' => $email_html_body,
            'email_text_body' => $email_text_body,

            'attachments' => $this->_attach_files,
        ]))) {
            if (self::st_has_error()) {
                $this->copy_static_error(self::ERR_SEND);
            } elseif ($event_obj->has_error()) {
                $this->copy_error($event_obj, self::ERR_SEND);
            }

            if (!$this->has_error()) {
                $this->set_error(self::ERR_SEND, self::_t('Error triggering send email.'));
            }

            return false;
        }

        if (!$event_obj->is_success()) {
            if (($event_error = $event_obj->result_error())
               && self::arr_has_error($event_error)) {
                $this->copy_error_from_array($event_error, self::ERR_SEND);
            } else {
                $this->set_error(self::ERR_SEND, self::_t('Error sending the email.'));
            }

            return false;
        }

        return true;
    }

    public function get_email_vars() : array
    {
        if ($this->_enriched_vars) {
            return $this->_email_vars;
        }

        $this->_email_vars = array_merge(
            $this->_email_vars,
            PHS_Event_Emails_settings::get_settings('email_vars') ?: []
        );

        return $this->_email_vars;
    }

    private function _check_email_parameters() : void
    {
        $this->get_email_vars();

        if ($this->_as_noreply) {
            $this->_from_email = $this->_email_vars['from_noreply'] ?? $this->_no_reply_email;
            $this->_from_name = $this->_email_vars['from_noreply_name'] ?? $this->_no_reply_name;
        }

        if (!$this->_from_name) {
            $this->_from_name = $this->_email_vars['from_name'] ?? $this->_email_vars['site_name'] ?? PHS_SITE_NAME;
        }
        if (!$this->_from_email) {
            $this->_from_email = $this->_email_vars['from_email'] ?? PHS_CONTACT_EMAIL;
        }

        if (!$this->_reply_email) {
            $this->reply($this->_from_email, $this->_from_name ?? '');
        }

        if (!$this->_subject) {
            $this->_subject = self::_t('Email from %s.', $this->_email_vars['site_name'] ?? PHS_SITE_NAME);
        }
    }

    private function _file_attachment_details() : array
    {
        return [
            'file'                => '',
            'file_name'           => '',
            'content_type'        => 'application/octet-stream',
            'transfer_encoding'   => 'base64',
            'content_disposition' => 'attachment', // attachment or inline
            'file_base64_buffer'  => '',
            'size'                => 0,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function instances_as_singletons() : bool
    {
        return false;
    }
}
