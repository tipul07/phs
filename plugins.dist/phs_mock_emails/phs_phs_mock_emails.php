<?php
namespace phs\plugins\phs_mock_emails;

use phs\PHS_Crypt;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\views\PHS_View;

class PHS_Plugin_Phs_mock_emails extends PHS_Plugin
{
    public const ERR_TEMPLATE = 40000, ERR_SEND = 40001, ERR_ATTACHMENTS = 40002;

    public const LOG_CHANNEL = 'phs_mock_emails.log';

    public static string $MAIL_AUTH_KEY = 'XMailAuth';

    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            'email_sending_group' => [
                'display_name' => $this->_pt('Mocking Email Settings'),
                'display_hint' => $this->_pt('Settings related to mocking emails.'),
                'group_fields' => [
                    'template_main' => [
                        'display_name' => 'Emails main template',
                        'display_hint' => 'What template should be used when mocking emails',
                        'type'         => PHS_Params::T_ASIS,
                        'input_type'   => self::INPUT_TYPE_TEMPLATE,
                        'default'      => $this->template_resource_from_file('template_emails'),
                    ],
                    'email_vars' => [
                        'display_name' => 'Emails variables',
                        'display_hint' => 'These variables will be available in email template',
                        'input_type'   => self::INPUT_TYPE_KEY_VAL_ARRAY,
                        'default'      => [
                            'site_name'    => PHS_SITE_NAME,
                            'from_name'    => PHS_SITE_NAME,
                            'from_email'   => 'office@'.PHS_DOMAIN,
                            'from_noreply' => 'noreply@'.PHS_DOMAIN,
                        ],
                    ],
                ],
            ],
            'email_logging_group' => [
                'display_name' => $this->_pt('What to log'),
                'display_hint' => $this->_pt('Settings related to logging email details.'),
                'group_fields' => [
                    'log_headers' => [
                        'display_name' => 'Log email headers',
                        'display_hint' => 'Log the MIME headers of emails',
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_text_body' => [
                        'display_name' => 'Log email text body',
                        'display_hint' => 'Log the text body of emails',
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                    'log_html_body' => [
                        'display_name' => 'Log email HTML body',
                        'display_hint' => 'Log the HTML body of emails',
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_attachment_names' => [
                        'display_name' => 'Log email attachment names',
                        'display_hint' => 'Log the names of email attachments',
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                ],
            ],
        ];
    }

    public function get_template_main() : string | array
    {
        return $this->get_plugin_settings()['template_main']
               ?? $this->template_resource_from_file('template_emails');
    }

    public function log_text_body() : bool
    {
        return (bool)($this->get_plugin_settings()['log_text_body'] ?? false);
    }

    public function log_html_body() : bool
    {
        return (bool)($this->get_plugin_settings()['log_html_body'] ?? false);
    }

    public function log_attachment_names() : bool
    {
        return (bool)($this->get_plugin_settings()['log_attachment_names'] ?? false);
    }

    public function log_headers() : bool
    {
        return (bool)($this->get_plugin_settings()['log_headers'] ?? false);
    }

    public function init_email_hook_args($hook_args) : array
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive($hook_args, PHS_Hooks::default_init_email_hook_args());

        if (!($settings_arr = $this->get_plugin_settings())
            || !($main_template = $this->get_template_main())) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Couldn\'t load template from plugin settings.'));

            PHS_Logger::error('Couldn\'t load template from plugin settings.', self::LOG_CHANNEL);

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $template_params = [];
        $template_params['theme_relative_dirs'] = [PHS_EMAILS_DIRS];

        if (!($email_main_template = PHS_View::validate_template_resource($main_template, $template_params))) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Failed validating main email template file.'));

            PHS_Logger::error('Failed validating main email template file.', self::LOG_CHANNEL);

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if (empty($hook_args['body_buffer'])
            && (empty($hook_args['template'])
            || !($email_template = PHS_View::validate_template_resource($hook_args['template'], $template_params))
            )) {
            $this->copy_or_set_static_error(self::ERR_TEMPLATE, $this->_pt('Failed validating email template file.'));

            PHS_Logger::error('Email template error ['.$this->get_simple_error_message().'].', self::LOG_CHANNEL);

            $hook_args['hook_errors'] = self::arr_set_error(self::ERR_TEMPLATE, $this->_pt('Failed validating email template file.'));

            return $hook_args;
        }

        if (empty($hook_args['from_name'])) {
            $hook_args['from_name'] = $settings_arr['email_vars']['site_name'];
        }
        if (empty($hook_args['from_email'])) {
            $hook_args['from_email'] = $settings_arr['email_vars']['from_email'];
        }
        if (empty($hook_args['from_noreply'])) {
            $hook_args['from_noreply'] = $settings_arr['email_vars']['from_noreply'];
        }

        if (empty($hook_args['email_vars']) || !is_array($hook_args['email_vars'])) {
            $hook_args['email_vars'] = [];
        }

        $hook_args['email_vars'] = self::validate_array($hook_args['email_vars'], $settings_arr['email_vars']);

        if (empty($hook_args['subject'])) {
            $hook_args['subject']
                = 'Email from '.(!empty($hook_args['email_vars']['site_name']) ? $hook_args['email_vars']['site_name'] : PHS_SITE_NAME);
        }

        $view_params = [];
        $view_params['action_obj'] = null;
        $view_params['controller_obj'] = null;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = [
            'hook_args'     => $hook_args,
            'email_content' => '',
        ];

        $email_template_obj = null;
        if (!empty($hook_args['body_buffer'])) {
            $email_content_buffer = $hook_args['body_buffer'];
        } elseif (empty($email_template)
                  || !($email_template_obj = PHS_View::init_view($email_template, $view_params))
                  || !($email_content_buffer = $email_template_obj->render(force_language: $hook_args['force_language'] ?? null))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            } elseif ($email_template_obj !== null && $email_template_obj->has_error()) {
                $this->copy_error($email_template_obj);
            }

            $this->set_error_if_not_set(self::ERR_TEMPLATE, $this->_pt('Rendering template %s resulted in empty buffer.',
                $email_template_obj?->get_template() ?: '(???)'));

            PHS_Logger::error('Email template render error ['.$this->get_error_message().'].', self::LOG_CHANNEL);

            $hook_args['hook_errors'] = self::arr_set_error(self::ERR_TEMPLATE, $this->_pt('Rendering template resulted in empty buffer.'));

            return $hook_args;
        }

        $view_params['template_data']['email_content'] = $email_content_buffer;

        if (!($main_template_obj = PHS_View::init_view($email_main_template, $view_params))
            || !($email_html_body = $main_template_obj->render(force_language: $hook_args['force_language'] ?? null))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            } elseif ($main_template_obj !== null && $main_template_obj->has_error()) {
                $this->copy_error($main_template_obj);
            }

            $this->set_error_if_not_set(self::ERR_TEMPLATE, $this->_pt('Rendering template %s resulted in empty buffer.',
                ($main_template_obj ? $main_template_obj->get_template() : '(???)')));

            PHS_Logger::error('Email main template render error ['.$this->get_error_message().'].', self::LOG_CHANNEL);

            $hook_args['hook_errors'] = self::arr_set_error(self::ERR_TEMPLATE, $this->_pt('Rendering main template resulted in empty buffer.'));

            return $hook_args;
        }

        $hook_args['email_html_body'] = $email_html_body;

        $attach_files = [];
        if ($this->log_attachment_names()
            && !empty($hook_args['attach_files']) && is_array($hook_args['attach_files'])) {
            $default_file_details = self::_default_file_attachment();
            foreach ($hook_args['attach_files'] as $knti => $file_details) {
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
                    $this->set_error(self::ERR_ATTACHMENTS, $this->_pt('Invalid parameters for attachment #%s.', $knti));

                    $hook_args['hook_errors'] = self::arr_set_error(self::ERR_ATTACHMENTS, $this->get_simple_error_message());

                    return $hook_args;
                }

                if (empty($file_details['content_disposition'])
                 || !in_array($file_details['content_disposition'], ['attachment', 'inline'])) {
                    $file_details['content_disposition'] = 'attachment';
                }

                $attach_files[] = $file_details;
            }
        }
        $hook_args['attach_files'] = $attach_files;

        if (!empty($hook_args['also_send'])) {
            $hook_args = $this->send_email($hook_args);
        }

        return $hook_args;
    }

    public function send_email(array $hook_args) : array
    {
        $this->reset_error();

        $hook_args = PHS_Hooks::reset_email_hook_args(self::validate_array_recursive($hook_args, PHS_Hooks::default_init_email_hook_args()));

        if (empty($hook_args['to'])
            || !PHS_Params::check_type($hook_args['to'], PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_SEND, $this->_pt('Destination is not an email.'));

            $hook_args['hook_errors'] = $this->get_error();

            PHS_Logger::error('To is not an email  ['.($hook_args['to'] ?? '-').'].', self::LOG_CHANNEL);

            return $hook_args;
        }

        if (empty($hook_args['email_html_body']) && empty($hook_args['email_text_body'])) {
            $this->set_error(self::ERR_SEND, $this->_pt('Email body is empty.'));

            PHS_Logger::error('Email body is empty.', self::LOG_CHANNEL);

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if (empty($hook_args['reply_email'])) {
            $hook_args['reply_email'] = $hook_args['from_email'];
        }
        if (empty($hook_args['reply_name'])) {
            $hook_args['reply_name'] = $hook_args['from_name'];
        }

        // Convert HTML version to text and text to HTML if necessary
        if (empty($hook_args['email_html_body']) && !empty($hook_args['email_text_body'])) {
            $hook_args['email_html_body'] = str_replace('  ', ' &nbsp;', nl2br($hook_args['email_text_body']));
        } elseif (!empty($hook_args['email_html_body']) && empty($hook_args['email_text_body'])) {
            $hook_args['email_text_body'] = strip_tags(preg_replace("/[\r\n]+/", "\n",
                str_ireplace(['<p>', '</p>', '<br>', '<br/>', '<br />'], "\n", $hook_args['email_html_body'])));
        }

        // set multipart boundary
        $hash = md5(microtime());
        $mime_boundary = '==MULTIPART_BOUNDARY_'.$hash;
        $mime_boundary_header = chr(34).$mime_boundary.chr(34);

        $predefined_headers = [];
        $predefined_headers['From'] = $hook_args['from_name'].' <'.$hook_args['from_email'].'>';
        $predefined_headers['X-Sender'] = '<'.$hook_args['from_email'].'>';
        $predefined_headers['Return-Path'] = '<'.$hook_args['from_email'].'>';
        $predefined_headers['Reply-To'] = $hook_args['reply_name'].' <'.$hook_args['reply_email'].'>';
        $predefined_headers['X-Mailer'] = 'PHP (PHS-MAIL-MOCKER-'.$this->get_plugin_version().')';
        if (!empty($hook_args['with_priority'])) {
            $predefined_headers['X-Priority'] = '1';
        }
        $predefined_headers['MIME-Version'] = '1.0';
        $predefined_headers['Content-Type'] = 'multipart/alternative; boundary='.$mime_boundary_header;
        $predefined_headers['Content-Transfer-Encoding'] = '7bit';
        $predefined_headers['X-Script-Time'] = time();

        if (empty($params['skip_mail_authentication'])
            && null !== ($mail_id = PHS_Crypt::quick_encode(self::mail_auth_key().':'.time()))) {
            // for single emails it's ok, but when sending multiple emails it might take too much time
            $predefined_headers['X-Mail-ID'] = $mail_id;
        } else {
            $predefined_headers['X-SMail-ID'] = md5(self::mail_auth_key().':'.time());
        }

        $final_headers_arr = $predefined_headers;
        if (!empty($hook_args['custom_headers']) && is_array($hook_args['custom_headers'])) {
            foreach ($hook_args['custom_headers'] as $key => $value) {
                $final_headers_arr[$key] = $value;
            }
        }

        $hook_args['full_body'] = 'This is a multi-part message in MIME format.'."\n\n"
                                  .'--'.$mime_boundary."\n"
                                  .'Content-Type: text/plain; charset=UTF-8'."\n"
                                  .'Content-Transfer-Encoding: 7bit'."\n\n"
                                  .$hook_args['email_text_body']."\n\n"
                                  .'--'.$mime_boundary."\n"
                                  .'Content-Type: text/html; charset=UTF-8'."\n"
                                  .'Content-Transfer-Encoding: 7bit'."\n\n"
                                  .$hook_args['email_html_body']."\n\n";

        $attach_files = [];
        if (!empty($hook_args['attach_files']) && is_array($hook_args['attach_files'])) {
            foreach ($hook_args['attach_files'] as $file_details) {
                if (empty($file_details) || !is_array($file_details)) {
                    continue;
                }

                $file_encoded = '';
                if (!empty($file_details['file_base64_buffer'])) {
                    $file_encoded = $file_details['file_base64_buffer'];
                } elseif (!empty($file_details['file'])) {
                    if (false === ($file_size = @filesize($file_details['file']))
                        || (!empty($settings_arr['max_attachment_size'])
                            && (int)$file_size > $settings_arr['max_attachment_size'])
                        || false === ($file_content = @file_get_contents($file_details['file']))) {
                        $this->set_error(self::ERR_SEND, $this->_pt('Couldn\'t obtain attachment file content.'));

                        $hook_args['hook_errors'] = $this->get_error();

                        return $hook_args;
                    }

                    $file_encoded = $file_details['transfer_encoding'] === 'base64'
                        ? @base64_encode($file_content)
                        : $file_content;

                    // Free up some memory
                    unset($file_content);
                }

                $file_details['file_base64_buffer'] = '--'.$mime_boundary."\n"
                    .'Content-Type: '.$file_details['content_type'].';'."\n\t".' name="'.$file_details['file_name'].'"'."\n"
                    .'Content-Transfer-Encoding: '.$file_details['transfer_encoding']."\n"
                    .'Content-Disposition: '.$file_details['content_disposition'].';'."\n\t".' filename="'.$file_details['file_name'].'"'."\n\n"
                    .chunk_split($file_encoded);

                $hook_args['full_body'] .= $file_details['file_base64_buffer'];

                $attach_files[] = $file_details;
            }
        }
        $hook_args['attach_files'] = $attach_files;

        $hook_args['full_body'] .= '--'.$mime_boundary."--\n\n";

        $hook_args['internal_vars']['full_headers'] = $final_headers_arr;

        $hook_args['internal_vars']['mime_boundary'] = $mime_boundary;

        $hook_args['internal_vars']['to_full_value'] = '';
        if (!empty($hook_args['to_name'])) {
            $hook_args['internal_vars']['to_full_value'] .= $hook_args['to_name'].' ';
        }
        $hook_args['internal_vars']['to_full_value'] .= '<'.$hook_args['to'].'>';

        $log_buf
            = 'Subject: "'.($hook_args['subject'] ?? '-')."\n"
            .'To: "'.($hook_args['to_name'] ?? '-').'" <'.$hook_args['to'].'>'."\n"
            .'From: "'.($hook_args['from_name'] ?? '-').'" <'.$hook_args['from_email'].'>'."\n"
            .'Reply To: "'.($hook_args['reply_name'] ?? '-').'" <'.$hook_args['reply_email'].'>'."\n";

        if ($this->log_text_body()) {
            $log_buf .= 'Text body:'."\n".($hook_args['email_text_body'] ?? '-')."\n";
        }
        if ($this->log_html_body()) {
            $log_buf .= 'HTML body:'."\n".($hook_args['email_html_body'] ?? '-')."\n";
        }
        if ($this->log_headers()) {
            $log_buf .= 'Headers:'."\n";
            foreach ($final_headers_arr as $key => $value) {
                $log_buf .= ' - '.$key.': '.$value."\n";
            }
        }
        if ($this->log_attachment_names()) {
            $attachments = $hook_args['attach_files'] ?? [];
            $log_buf .= "\n"
                        .'Attachments: '.count($attachments).' files'."\n";
            foreach ($attachments as $attachment) {
                $log_buf .= ' - '.$attachment['file_name'].' ('.$attachment['content_type'].'), '
                            .' encoding: '.$attachment['transfer_encoding'].', '
                            .'disposition: '.$attachment['content_disposition']."\n";
            }
        }

        PHS_Logger::error('New email:'."\n".$log_buf, self::LOG_CHANNEL);

        return $hook_args;
    }

    public static function mail_auth_key(?string $key = null) : string
    {
        if ($key === null) {
            return self::$MAIL_AUTH_KEY;
        }

        self::$MAIL_AUTH_KEY = $key;

        return self::$MAIL_AUTH_KEY;
    }

    private static function _default_file_attachment() : array
    {
        return [
            'file'                => '',
            'file_name'           => '',
            'content_type'        => 'application/octet-stream',
            'transfer_encoding'   => 'base64',
            'content_disposition' => 'attachment', // attachment or inline
            'file_base64_buffer'  => '',
        ];
    }
}
