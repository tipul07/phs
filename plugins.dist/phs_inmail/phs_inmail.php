<?php
namespace phs\plugins\phs_inmail;

use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;

class PHS_Plugin_Phs_inmail extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_inmail.log';

    public const INMAIL_DIR = '_inmail';

    public const COND_OR = 'OR', COND_AND = 'AND';

    public const ATTACHMENT_IGNORE = 'Ignore', ATTACHMENT_YES = 'Yes', ATTACHMENT_NO = 'No';

    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            'generic_settings_group' => [
                'display_name' => $this->_pt('InMail Generic Settings'),
                'display_hint' => $this->_pt('Generic settings for emails received by email entry-point.'),
                'group_fields' => [
                    'inmail_enabled' => [
                        'display_name' => $this->_pt('Enable InMail Functionality'),
                        'display_hint' => $this->_pt('Should framework take in consideration any emails?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'accept_attachments_ext' => [
                        'display_name' => $this->_pt('Accept attachments extensions'),
                        'display_hint' => $this->_pt('From email attachments take only files with following extensions. No value means accept all.'),
                        'type'         => PHS_Params::T_ARRAY,
                        'extra_type'   => ['type' => PHS_Params::T_NOHTML],
                        'input_type'   => self::INPUT_TYPE_ONE_OR_MORE_MULTISELECT,
                        'values_arr'   => PHS_Utils::extension_to_mimetype_array(),
                        'default'      => [],
                    ],
                ],
            ],
            'triggering_settings_group' => [
                'display_name' => $this->_pt('Trigger InMail Event'),
                'display_hint' => $this->_pt('Settings related to triggering incoming email event. Empty conditions are not used.'),
                'group_fields' => [
                    'logic_condition' => [
                        'display_name' => $this->_pt('Condition Operand'),
                        'display_hint' => $this->_pt('What logic operand should be used for all conditions below'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => self::COND_OR,
                        'values_arr'   => [self::COND_OR => 'OR', self::COND_AND => 'AND'],
                    ],
                    'from_field_contains' => [
                        'display_name' => $this->_pt('FROM contains'),
                        'display_hint' => $this->_pt('A comma separated emails. If ONE of the emails is found in FROM field this condition is true'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => '',
                    ],
                    'to_field_contains' => [
                        'display_name' => $this->_pt('TO contains'),
                        'display_hint' => $this->_pt('A comma separated emails. If ONE of the emails is found in TO field this condition is true'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => '',
                    ],
                    'cc_field_contains' => [
                        'display_name' => $this->_pt('CC contains'),
                        'display_hint' => $this->_pt('A comma separated emails. If ONE of the emails is found in CC field this condition is true'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => '',
                    ],
                    'bcc_field_contains' => [
                        'display_name' => $this->_pt('BCC contains'),
                        'display_hint' => $this->_pt('A comma separated emails. If ONE of the emails is found in BCC field this condition is true'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => '',
                    ],
                    'subject_regex' => [
                        'display_name' => $this->_pt('Subject Regex'),
                        'display_hint' => $this->_pt('Regex to be applied on subject of the incoming email. Regex limits will be / and check is case insensitive.'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => '',
                    ],
                    'has_attachments' => [
                        'display_name' => $this->_pt('Has attachments'),
                        'display_hint' => $this->_pt('Does the incoming email have attachments?'),
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => self::ATTACHMENT_IGNORE,
                        'values_arr'   => [
                            self::ATTACHMENT_IGNORE => 'Ignore',
                            self::ATTACHMENT_YES    => 'Yes',
                            self::ATTACHMENT_NO     => 'No',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function is_inmail_enabled() : bool
    {
        return (bool)($this->get_plugin_settings()['inmail_enabled'] ?? false);
    }

    public function get_accept_attachments_ext() : array
    {
        $extenstions_arr = $this->get_plugin_settings()['accept_attachments_ext'] ?? [];

        return !is_array($extenstions_arr) ? [] : $extenstions_arr;
    }

    public function get_logic_condition() : string
    {
        $condition = $this->get_plugin_settings()['logic_condition'] ?? self::COND_OR;

        return in_array($condition, [self::COND_AND, self::COND_OR], true) ? $condition : self::COND_OR;
    }

    public function get_from_field_emails() : array
    {
        return $this->_extract_emails_from_comma_separated($this->get_plugin_settings()['from_field_contains'] ?? '');
    }

    public function get_to_field_emails() : array
    {
        return $this->_extract_emails_from_comma_separated($this->get_plugin_settings()['to_field_contains'] ?? '');
    }

    public function get_cc_field_emails() : array
    {
        return $this->_extract_emails_from_comma_separated($this->get_plugin_settings()['cc_field_contains'] ?? '');
    }

    public function get_bcc_field_emails() : array
    {
        return $this->_extract_emails_from_comma_separated($this->get_plugin_settings()['bcc_field_contains'] ?? '');
    }

    public function get_subject_regex() : string
    {
        return $this->get_plugin_settings()['subject_regex'] ?? '';
    }

    public function get_has_attachment() : ?bool
    {
        if (self::ATTACHMENT_IGNORE
           === ($has_attachments = $this->get_plugin_settings()['has_attachments'] ?? self::ATTACHMENT_IGNORE)) {
            return null;
        }

        return $has_attachments === self::ATTACHMENT_YES;
    }

    public function get_inmail_dir(bool $slash_ended = true) : string
    {
        return rtrim(PHS_UPLOADS_DIR, '/').'/'.self::INMAIL_DIR.($slash_ended ? '/' : '');
    }

    public function get_inmail_www(bool $slash_ended = true) : string
    {
        return rtrim(PHS_UPLOADS_WWW, '/').'/'.self::INMAIL_DIR.($slash_ended ? '/' : '');
    }

    protected function custom_activate($plugin_arr) : bool
    {
        if (!$this->_create_required_directories()) {
            return false;
        }

        // Make sure we don't have static errors...
        self::st_reset_error();

        return true;
    }

    protected function custom_update($old_version, $new_version) : bool
    {
        if (!$this->_create_required_directories()) {
            return false;
        }

        // Make sure we don't have static errors...
        self::st_reset_error();

        return true;
    }

    private function _create_required_directories() : bool
    {
        $this->reset_error();

        if (!($inmail_dir = $this->get_inmail_dir(false))) {
            $this->set_error(self::ERR_ACTIVATE, $this->_pt('Error obtaining products import export directory path.'));

            return false;
        }

        if (!@file_exists($inmail_dir)
            && !@mkdir($inmail_dir, 0775)
            && !@is_dir($inmail_dir)) {
            $this->set_error(self::ERR_ACTIVATE, $this->_pt('Error creating inmail directory: [%s].', $inmail_dir));

            return false;
        }

        return true;
    }

    private function _extract_emails_from_comma_separated(string $str) : array
    {
        if (!$str
           || !($emails_arr = self::extract_strings_from_comma_separated($str, ['to_lowercase' => true]))) {
            return [];
        }

        $return_arr = [];
        foreach ($emails_arr as $email) {
            if (!PHS_Params::check_type($email, PHS_Params::T_EMAIL)) {
                continue;
            }

            $return_arr[] = $email;
        }

        return $return_arr;
    }
}
