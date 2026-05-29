<?php
namespace phs\system\core\libraries;

use phs\libraries\PHS_Library;
use phs\libraries\PHS_Mime_part;

class PHS_Mime_parser extends PHS_Library
{
    public const ERR_INPUT_BUFFER = 1;

    private array $_lines_arr = [];

    private bool $_lines_parsed = false;

    private ?string $_prev_line = null;

    private int $_full_read_filsezise_limit = 512000;

    private ?string $_filename = null;

    private mixed $_fh = null;

    private int $_li = 0;

    private string $_id = '';

    private ?PHS_Mime_part $_main_part = null;

    public function set_buffer(string $buffer) : void
    {
        $this->_parse_lines_from_buffer($buffer);
    }

    public function set_input_file(string $filename) : bool
    {
        $this->reset_error();
        $this->_reset_lines_arr();

        if (!@is_file($filename)
           || !@is_readable($filename)
           || !($fsize = @filesize($filename))) {
            $this->set_error(self::ERR_INPUT_BUFFER, self::_t('Provided file is not readable.'));

            return false;
        }

        $this->_filename = $filename;

        if (($limit = $this->full_read_filsezise_limit())
           && $fsize > $limit) {
            return true;
        }

        if (!($buffer = @file_get_contents($filename))) {
            $this->set_error(self::ERR_INPUT_BUFFER, self::_t('Error reading provided file.'));

            return false;
        }

        $this->_parse_lines_from_buffer($buffer);

        return true;
    }

    public function get_email_parsing_id() : string
    {
        $this->get_main_part();

        return $this->_id;
    }

    public function get_main_part() : ?PHS_Mime_part
    {
        if (!$this->_main_part) {
            $this->parse_email();
        }

        return $this->_main_part;
    }

    public function get_headers() : array
    {
        return $this->get_main_part()?->get_headers() ?: [];
    }

    public function get_parts() : array
    {
        return $this->get_main_part()?->get_parts() ?: [];
    }

    public function get_email_from() : ?string
    {
        return $this->get_main_part()?->get_header_from();
    }

    public function get_email_from_as_recipients() : array
    {
        return $this->as_recipients($this->get_email_from());
    }

    public function email_from_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->get_email_from_as_recipients() as $recipient) {
            if (!empty($recipient['email']) && strtolower($recipient['email']) === $email) {
                return true;
            }
        }

        return false;
    }

    public function get_email_to() : ?string
    {
        return $this->get_main_part()?->get_header_to();
    }

    public function get_email_to_as_recipients() : array
    {
        return $this->as_recipients($this->get_email_to());
    }

    public function email_to_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->get_email_to_as_recipients() as $recipient) {
            if (!empty($recipient['email']) && strtolower($recipient['email']) === $email) {
                return true;
            }
        }

        return false;
    }

    public function get_email_cc() : ?string
    {
        return $this->get_main_part()?->get_header_cc();
    }

    public function get_email_cc_as_recipients() : array
    {
        return $this->as_recipients($this->get_email_cc());
    }

    public function email_cc_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->get_email_cc_as_recipients() as $recipient) {
            if (!empty($recipient['email']) && strtolower($recipient['email']) === $email) {
                return true;
            }
        }

        return false;
    }

    public function get_email_bcc() : ?string
    {
        return $this->get_main_part()?->get_header_bcc();
    }

    public function get_email_bcc_as_recipients() : array
    {
        return $this->as_recipients($this->get_email_bcc());
    }

    public function email_bcc_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->get_email_bcc_as_recipients() as $recipient) {
            if (!empty($recipient['email']) && strtolower($recipient['email']) === $email) {
                return true;
            }
        }

        return false;
    }

    public function get_email_delivered_to() : ?string
    {
        return $this->get_main_part()?->get_header_delivered_to();
    }

    public function get_email_delivered_to_as_recipients() : array
    {
        return $this->as_recipients($this->get_email_delivered_to());
    }

    public function email_delivered_to_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->get_email_delivered_to_as_recipients() as $recipient) {
            if (!empty($recipient['email']) && strtolower($recipient['email']) === $email) {
                return true;
            }
        }

        return false;
    }

    public function get_email_reply_to() : ?string
    {
        return $this->get_main_part()?->get_header_reply_to();
    }

    public function get_email_reply_to_as_recipients() : array
    {
        return $this->as_recipients($this->get_email_reply_to());
    }

    public function as_recipients(?string $str) : array
    {
        return PHS_Mime_part::parse_recipients($str);
    }

    public function get_email_subject() : ?string
    {
        return $this->get_main_part()?->get_header_subject();
    }

    public function get_email_content() : ?string
    {
        return $this->get_main_part()?->get_content() ?: null;
    }

    public function get_email_encoded_content() : ?string
    {
        return $this->get_main_part()?->get_encoded_content() ?: null;
    }

    public function get_email_html_body() : ?string
    {
        if (!($main_part = $this->get_main_part())) {
            return null;
        }

        if ($main_part->is_text_html()) {
            return $main_part->get_content();
        }

        if (!($parts = $main_part->get_parts())) {
            return null;
        }

        return $this->_get_email_html_body_from_parts($parts);
    }

    public function get_email_text_body() : ?string
    {
        if (!($main_part = $this->get_main_part())) {
            return null;
        }

        if ($main_part->is_text_plain()) {
            return $main_part->get_content();
        }

        if (!($parts = $main_part->get_parts())) {
            return null;
        }

        return $this->_get_email_text_body_from_parts($parts);
    }

    public function get_email_attachments() : array
    {
        if (!($main_part = $this->get_main_part())
            || !($parts = $main_part->get_parts())) {
            return [];
        }

        return $this->_get_email_attachments_from_parts($parts);
    }

    public function has_attachments() : bool
    {
        return (bool)$this->get_email_attachments();
    }

    public function has_parts() : bool
    {
        return $this->get_main_part()?->has_parts() ?: false;
    }

    public function full_read_filsezise_limit(?int $limit = null) : int
    {
        if ($limit !== null) {
            $this->_full_read_filsezise_limit = $limit;
        }

        return $this->_full_read_filsezise_limit;
    }

    public function is_valid_email() : bool
    {
        return $this->get_main_part()?->is_valid_email();
    }

    public function parse_email() : void
    {
        $this->reset_error();

        $this->_id = microtime(true);
        $this->_main_part = new PHS_Mime_part([$this, 'get_next_line']);
        $this->_main_part->parse_parts();

        $this->_close_fh();
    }

    public function get_next_line(bool $advance = true) : ?string
    {
        if ($this->_lines_parsed) {
            if (($this->_lines_arr[$this->_li] ?? null) === null) {
                return null;
            }

            if ($advance) {
                $this->_li++;
            }

            return $this->_lines_arr[$this->_li - 1];
        }

        if ($advance) {
            $this->_li++;
        }

        return $this->_get_line_from_file($advance);
    }

    /**
     * @param array<\phs\libraries\PHS_Mime_part> $parts
     *
     * @return null|string
     */
    private function _get_email_html_body_from_parts(array $parts) : ?string
    {
        foreach ($parts as $part) {
            if ($part->is_text_html()) {
                return $part->get_content();
            }

            if ($part->has_parts()) {
                return $this->_get_email_html_body_from_parts($part->get_parts());
            }
        }

        return null;
    }

    /**
     * @param array<\phs\libraries\PHS_Mime_part> $parts
     *
     * @return null|string
     */
    private function _get_email_text_body_from_parts(array $parts) : ?string
    {
        foreach ($parts as $part) {
            if ($part->is_text_plain()) {
                return $part->get_content();
            }

            if ($part->has_parts()) {
                return $this->_get_email_text_body_from_parts($part->get_parts());
            }
        }

        return null;
    }

    /**
     * @param array<\phs\libraries\PHS_Mime_part> $parts
     *
     * @return array
     */
    private function _get_email_attachments_from_parts(array $parts) : array
    {
        $return_arr = [];
        foreach ($parts as $part) {
            if ($part->is_attachment()) {
                $return_arr[] = $part->get_attachment_details();
            }

            if ($part->has_parts()) {
                $return_arr = array_merge($return_arr, $this->_get_email_attachments_from_parts($part->get_parts()));
            }
        }

        return $return_arr;
    }

    private function _get_line_from_file(bool $advance = true) : ?string
    {
        if ($this->_filename === null) {
            return null;
        }

        if (!($fh = $this->_get_fh())) {
            return null;
        }

        if (!$advance) {
            return $this->_prev_line;
        }

        if (($line = @fgets($fh)) === false) {
            $this->_close_fh();

            return null;
        }

        $this->_prev_line = $line;

        return $line;
    }

    private function _close_fh() : void
    {
        if ($this->_fh !== null) {
            @fclose($this->_fh);
            $this->_fh = null;
        }
    }

    private function _get_fh()
    {
        if ($this->_fh !== null) {
            return $this->_fh;
        }

        if (!$this->_filename
           || !($handle = @fopen($this->_filename, 'rb'))) {
            return null;
        }

        $this->_fh = $handle;

        return $this->_fh;
    }

    private function _reset_lines_arr() : void
    {
        $this->_lines_arr = [];
        $this->_li = 0;
        $this->_prev_line = null;
    }

    private function _parse_lines_from_buffer(string $buffer) : void
    {
        $this->_reset_lines_arr();

        $this->_lines_arr = explode("\n", $buffer) ?: [];

        foreach ($this->_lines_arr as $knti => $line) {
            $this->_lines_arr[$knti] = trim($line, "\r");
        }

        $this->_lines_parsed = true;
    }

    public static function instances_as_singletons() : bool
    {
        return false;
    }
}
