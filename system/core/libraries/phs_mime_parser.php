<?php
namespace phs\system\core\libraries;

use Throwable;
use phs\libraries\PHS_Library;

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

    public function email_from_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->as_recipients($this->get_email_from()) as $recipient) {
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

    public function email_to_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->as_recipients($this->get_email_to()) as $recipient) {
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

    public function email_cc_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->as_recipients($this->get_email_cc()) as $recipient) {
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

    public function email_bcc_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->as_recipients($this->get_email_bcc()) as $recipient) {
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

    public function email_delivered_to_contains_email(string $email) : bool
    {
        $email = strtolower($email);
        foreach ($this->as_recipients($this->get_email_delivered_to()) as $recipient) {
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
     * @param array<\phs\system\core\libraries\PHS_Mime_part> $parts
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
     * @param array<\phs\system\core\libraries\PHS_Mime_part> $parts
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
     * @param array<\phs\system\core\libraries\PHS_Mime_part> $parts
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

class PHS_Mime_part
{
    public const H_FROM = 'From', H_TO = 'To', H_CC = 'Cc', H_BCC = 'Bcc', H_SUBJECT = 'Subject', H_DATE = 'Date',
        H_DELIVERED_TO = 'Delivered-To', H_REPLY_TO = 'Reply-To', H_RETURN_PATH = 'Return-Path',
        H_CONTENT_TYPE = 'Content-Type', H_CONTENT_DISPOSITION = 'Content-Disposition', H_CONTENT_ID = 'Content-ID',
        H_CONTENT_TRANSFER_ENCODING = 'Content-Transfer-Encoding', H_MIME_VERSION = 'Mime-Version';

    private const TYPE_TEXT_PLAIN = 'text/plain', TYPE_TEXT_HTML = 'text/html';

    private const CONTENT_DISPOSITION_INLINE = 'inline', CONTENT_DISPOSITION_ATTACHMENT = 'attachment';

    // Headers' values
    private array $_hval_arr = [];

    private array $_parts_arr = [];

    private array $_settings = [];

    private ?string $_content = null;

    public function __construct(
        private mixed $_line_feeder,
        string $_outer_boundary = '',
    ) {
        if (!@is_callable($this->_line_feeder)) {
            $this->_line_feeder = null;
        }

        $this->_settings = $this->_default_part_settings();
        $this->_settings['outer_boundary'] = $_outer_boundary;
    }

    public function get_headers() : array
    {
        return $this->_hval_arr;
    }

    public function get_header_from() : ?string
    {
        return $this->get_header_by_key(self::H_FROM);
    }

    public function get_header_to() : ?string
    {
        return $this->get_header_by_key(self::H_TO);
    }

    public function get_header_cc() : ?string
    {
        return $this->get_header_by_key(self::H_CC);
    }

    public function get_header_bcc() : ?string
    {
        return $this->get_header_by_key(self::H_BCC);
    }

    public function get_header_delivered_to() : ?string
    {
        return $this->get_header_by_key(self::H_DELIVERED_TO);
    }

    public function get_header_reply_to() : ?string
    {
        return $this->get_header_by_key(self::H_REPLY_TO);
    }

    public function get_header_return_path() : ?string
    {
        return $this->get_header_by_key(self::H_RETURN_PATH);
    }

    public function get_header_subject() : ?string
    {
        return $this->get_header_by_key(self::H_SUBJECT);
    }

    public function get_header_mime_version() : ?string
    {
        return $this->get_header_by_key(self::H_MIME_VERSION);
    }

    public function is_valid_email() : bool
    {
        return $this->get_header_mime_version() !== null
               && $this->get_header_subject() !== null
               && $this->get_header_to() !== null
               && ($this->get_part_boundary() || $this->get_encoded_content());
    }

    public function get_header_by_key(string $hkey) : ?string
    {
        return $this->_hval_arr[$hkey] ?? null;
    }

    public function get_settings() : array
    {
        return $this->_settings;
    }

    /**
     * @return array<PHS_Mime_part>
     */
    public function get_parts() : array
    {
        return $this->_parts_arr;
    }

    public function has_parts() : bool
    {
        return (bool)$this->_parts_arr;
    }

    public function get_content() : ?string
    {
        return $this->_content
            ? $this->_convert_for_transfer_encoding($this->_content, $this->get_part_transfer_encoding())
            : null;
    }

    public function get_encoded_content() : ?string
    {
        return $this->_content;
    }

    public function get_predefined_headers() : array
    {
        return [
            self::H_FROM, self::H_TO, self::H_CC, self::H_BCC, self::H_SUBJECT, self::H_DATE,
            self::H_DELIVERED_TO, self::H_REPLY_TO, self::H_RETURN_PATH,
            self::H_CONTENT_TYPE, self::H_CONTENT_DISPOSITION, self::H_CONTENT_ID,
            self::H_CONTENT_TRANSFER_ENCODING, self::H_MIME_VERSION,
        ];
    }

    public function parse_parts() : bool
    {
        if (!$this->_line_feeder) {
            return false;
        }

        $this->_read_headers();
        $this->_check_predefined_headers();

        $this->_extract_parts();

        return true;
    }

    public function get_part_boundary() : ?string
    {
        return $this->_settings['boundary'] ?? null;
    }

    public function get_part_content_type() : ?string
    {
        return $this->_settings['content_type'] ?? null;
    }

    public function get_part_content_disposition() : ?string
    {
        return $this->_settings['content_disposition'] ?? null;
    }

    public function get_part_outer_boundary() : ?string
    {
        return $this->_settings['outer_boundary'] ?? null;
    }

    public function get_part_charset() : ?string
    {
        return $this->_settings['charset'] ?? null;
    }

    public function get_part_transfer_encoding() : ?string
    {
        return $this->_settings['transfer_encoding'] ?? null;
    }

    public function get_part_name() : ?string
    {
        return $this->_settings['name'] ?? null;
    }

    public function get_part_filename() : ?string
    {
        return $this->_settings['filename'] ?? null;
    }

    public function get_part_content_id() : ?string
    {
        return $this->_settings['content_id'] ?? null;
    }

    public function get_part_size() : ?string
    {
        return $this->_settings['size'] ?? null;
    }

    public function get_part_creation_date() : ?string
    {
        return $this->_settings['creation_date'] ?? null;
    }

    public function get_part_modification_date() : ?string
    {
        return $this->_settings['modification_date'] ?? null;
    }

    public function get_predefined_header_key(string $hkey) : ?string
    {
        static $lower_predefined_headers = null;

        $predefined_headers = $this->get_predefined_headers();

        if ($lower_predefined_headers === null) {
            $lower_predefined_headers = array_map(static fn(string $header) => strtolower($header), $predefined_headers);
        }

        return false === ($index = array_search(strtolower($hkey), $lower_predefined_headers, true))
            ? null
            : $predefined_headers[$index];
    }

    public function is_text_plain() : bool
    {
        return $this->get_part_content_type() === self::TYPE_TEXT_PLAIN;
    }

    public function is_text_html() : bool
    {
        return $this->get_part_content_type() === self::TYPE_TEXT_HTML;
    }

    public function is_attachment() : bool
    {
        return $this->is_disposition_inline()
               || $this->is_disposition_attachment();
    }

    public function get_attachment_details() : array
    {
        if (!$this->is_attachment()) {
            return [];
        }

        return [
            'filename'          => $this->get_part_filename() ?? $this->get_part_name() ?? '',
            'size'              => $this->get_part_size() ?? '',
            'creation_date'     => $this->get_part_creation_date(),
            'modification_date' => $this->get_part_modification_date(),
            'content_id'        => $this->get_part_content_id(),
            'content_type'      => $this->get_part_content_type(),
            'content'           => '', // $this->get_content(),
        ];
    }

    public function is_disposition_inline() : bool
    {
        return $this->get_part_content_disposition() === self::CONTENT_DISPOSITION_INLINE;
    }

    public function is_disposition_attachment() : bool
    {
        return $this->get_part_content_disposition() === self::CONTENT_DISPOSITION_ATTACHMENT;
    }

    private function _extract_parts() : void
    {
        if (!$this->_line_feeder) {
            return;
        }

        $is_text_plain = $this->is_text_plain();
        $boundary = $this->get_part_boundary();
        $outer_boundary = $this->get_part_outer_boundary();
        $this->_parts_arr = [];

        $advance_line = true;
        while (null !== ($line = ($this->_line_feeder)($advance_line))) {
            $tr_line = trim($line);

            $advance_line = true;
            if (($outer_boundary
                 && ($tr_line === '--'.$outer_boundary
                     || $tr_line === '--'.$outer_boundary.'--'))
                || ($boundary
                    && $tr_line === '--'.$boundary.'--')) {
                break;
            }

            if ($boundary
               && $tr_line === '--'.$boundary) {
                $part = new self($this->_line_feeder, $boundary);
                $part->parse_parts();

                $this->_parts_arr[] = $part;
                $advance_line = false;
                continue;
            }

            $end_with_eq = false;
            if (str_ends_with($line, '=')) {
                $end_with_eq = true;
                $line = substr($line, 0, -1);
            }

            $this->_content ??= '';
            $this->_content .= $line;

            if ($is_text_plain) {
                if (!$end_with_eq && $tr_line !== '') {
                    $this->_content .= "\n";
                }
                if ($tr_line === '') {
                    $this->_content .= "\n";
                }
            }
        }

        if ($this->_content) {
            $this->_content = PHS_Mime_charset::convert($this->_content, $this->get_part_charset());
        }
    }

    private function _read_headers() : void
    {
        $this->_hval_arr = [];
        $h_key = '';
        $h_val = '';

        while (null !== ($line = ($this->_line_feeder)())
              && trim($line) !== '') {
            if (in_array($line[0], [' ', "\t"], true)) {
                $h_val .= ' '.trim($line);
                continue;
            }

            if ($h_key !== '') {
                $this->_add_header($h_key, trim($h_val));

                $h_key = '';
                $h_val = '';
            }

            if ($h_key === '') {
                $line_parts = explode(':', $line, 2);
                $h_key = trim($line_parts[0]);
                $h_val = trim($line_parts[1] ?? '');
            }
        }

        if ($h_key !== '') {
            $this->_add_header($h_key, trim($h_val));
        }
    }

    private function _check_predefined_headers() : void
    {
        foreach ($this->_hval_arr as $hkey => $hval) {
            if (!($phkey = $this->get_predefined_header_key($hkey))
               || !($extractor_callback = $this->_header_extractor($phkey))) {
                continue;
            }

            $extractor_callback($hval);
        }
    }

    private function _add_header(string $key, string $val) : void
    {
        if (($phkey = $this->get_predefined_header_key($key))) {
            $key = $phkey;
        }

        if ($key) {
            if (!isset($this->_hval_arr[$key])) {
                $this->_hval_arr[$key] = PHS_Mime_charset::decode_mime_string($val);

                return;
            }
        }

        if (!is_array($this->_hval_arr[$key])) {
            $old_val = $this->_hval_arr[$key];

            $this->_hval_arr[$key] = [];
            $this->_hval_arr[$key][] = $old_val;
        }

        $this->_hval_arr[$key][] = PHS_Mime_charset::decode_mime_string($val);
    }

    private function _header_extractor(string $hkey) : ?array
    {
        return match ($hkey) {
            default                           => null,
            self::H_CONTENT_TYPE              => [$this, '_extract_content_type'],
            self::H_CONTENT_DISPOSITION       => [$this, '_extract_content_disposition'],
            self::H_CONTENT_ID                => [$this, '_extract_content_id'],
            self::H_CONTENT_TRANSFER_ENCODING => [$this, '_extract_transfer_encoding'],
        };
    }

    private function _convert_for_transfer_encoding(string $content, string $encoding) : string
    {
        return match ($encoding) {
            default            => $content,
            'base64'           => base64_decode($content),
            'quoted-printable' => quoted_printable_decode($content),
        };
    }

    private function _default_part_settings() : array
    {
        return [
            'outer_boundary'      => '',
            'boundary'            => '',
            'content_id'          => '',
            'name'                => '',
            'filename'            => '',
            'size'                => '',
            'creation_date'       => '',
            'modification_date'   => '',
            'transfer_encoding'   => '',
            'content_disposition' => '',
            'content_type'        => 'text/plain',
            'charset'             => PHS_Mime_charset::DEFAULT_CHARSET,
        ];
    }

    private function _extract_content_type(string $kval) : void
    {
        $parts = explode(';', $kval);
        foreach ($parts as $knti => $part) {
            if (!$part) {
                continue;
            }

            if ($knti === 0) {
                $this->_settings['content_type'] = strtolower(trim($part));
                continue;
            }

            $attrs = explode('=', $part, 2);
            if (empty($attrs[0])) {
                continue;
            }

            $attr = strtolower(trim($attrs[0]));
            if ($attr === 'charset') {
                $this->_settings['charset'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'boundary') {
                $this->_settings['boundary'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'name') {
                $this->_settings['name'] = trim(trim($attrs[1] ?? ''), '"');
            }
        }
    }

    private function _extract_content_disposition(string $kval) : void
    {
        $parts = explode(';', $kval);
        foreach ($parts as $knti => $part) {
            if (!$part) {
                continue;
            }

            if ($knti === 0) {
                $this->_settings['content_disposition'] = trim($part);
                continue;
            }

            $attrs = explode('=', $part, 2);
            if (empty($attrs[0])) {
                continue;
            }

            $attr = strtolower(trim($attrs[0]));
            if ($attr === 'filename') {
                $this->_settings['filename'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'size') {
                $this->_settings['size'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'creation-date') {
                $this->_settings['creation_date'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'modification-date') {
                $this->_settings['modification_date'] = trim(trim($attrs[1] ?? ''), '"');
            }
        }
    }

    private function _extract_content_id(string $kval) : void
    {
        $this->_settings['content_id'] = trim(trim($kval), '"<>');
    }

    private function _extract_transfer_encoding(string $kval) : void
    {
        $this->_settings['transfer_encoding'] = trim($kval);
    }

    public static function parse_recipients(?string $str) : array
    {
        if (!$str) {
            return [];
        }

        $len = strlen($str);
        $in_name = true;
        $in_email = false;
        $in_quotes = false;

        $name = '';
        $email = '';

        $return_arr = [];

        for ($i = 0; $i < $len; $i++) {
            if ($str[$i] === '"') {
                $in_name = $in_email = !$in_quotes;

                $in_quotes = !$in_quotes;
                continue;
            }

            if ($str[$i] === '<') {
                $in_email = true;
                $in_name = false;
                continue;
            }
            if ($str[$i] === '>') {
                $in_email = false;
                $in_name = false;
                continue;
            }

            if (!$in_name && !$in_email
               && $str[$i] === ',') {
                $return_arr[] = [
                    'name'  => trim($name),
                    'email' => trim($email),
                ];

                $name = '';
                $email = '';

                $in_name = true;
                $in_email = false;
                $in_quotes = false;
                continue;
            }

            if (!$in_quotes && !$in_name
               && $str[$i] === ' ') {
                $in_email = true;
                continue;
            }

            if ($in_name && !$in_quotes
               && $str[$i] === '@') {
                $in_name = false;
                $in_email = true;
                $email = $name;
                $name = '';
            }

            if ($in_name) {
                $name .= $str[$i];
                continue;
            }

            if ($in_email) {
                $email .= $str[$i];
                continue;
            }
        }

        if ($email !== '') {
            $return_arr[] = [
                'name'  => trim($name),
                'email' => trim($email),
            ];
        }

        return $return_arr;
    }
}

class PHS_Mime_charset
{
    public const DEFAULT_CHARSET = 'UTF-8';

    private static array $_charset_aliases = [
        'USASCII'       => 'WINDOWS-1252',
        'ANSIX31101983' => 'WINDOWS-1252',
        'ANSIX341968'   => 'WINDOWS-1252',
        'UNKNOWN8BIT'   => 'ISO-8859-15',
        'UNKNOWN'       => 'ISO-8859-15',
        'USERDEFINED'   => 'ISO-8859-15',
        'KSC56011987'   => 'EUC-KR',
        'GB2312'        => 'GBK',
        'GB231280'      => 'GBK',
        'UNICODE'       => 'UTF-8',
        'UTF7IMAP'      => 'UTF7-IMAP',
        'TIS620'        => 'WINDOWS-874',
        'ISO88599'      => 'WINDOWS-1254',
        'ISO885911'     => 'WINDOWS-874',
        'MACROMAN'      => 'MACINTOSH',
        '77'            => 'MAC',
        '128'           => 'SHIFT-JIS',
        '129'           => 'CP949',
        '130'           => 'CP1361',
        '134'           => 'GBK',
        '136'           => 'BIG5',
        '161'           => 'WINDOWS-1253',
        '162'           => 'WINDOWS-1254',
        '163'           => 'WINDOWS-1258',
        '177'           => 'WINDOWS-1255',
        '178'           => 'WINDOWS-1256',
        '186'           => 'WINDOWS-1257',
        '204'           => 'WINDOWS-1251',
        '222'           => 'WINDOWS-874',
        '238'           => 'WINDOWS-1250',
        'MS950'         => 'CP950',
        'WINDOWS31J'    => 'CP932',
        'WINDOWS949'    => 'UHC',
        'WINDOWS1257'   => 'ISO-8859-13',
        'ISO2022JP'     => 'ISO-2022-JP-MS',
    ];

    public static function parse_charset(string $input) : string
    {
        static $charsets = [];

        $charset = strtoupper($input);

        if (isset($charsets[$input])) {
            return $charsets[$input];
        }

        $charset = preg_replace([
            '/^[^0-9A-Z]+/',    // e.g. _ISO-8859-JP$SIO
            '/\$.*$/',          // e.g. _ISO-8859-JP$SIO
            '/UNICODE-1-1-*/',  // RFC1641/1642
            '/^X-/',            // X- prefix (e.g. X-ROMAN8 => ROMAN8)
            '/\*.*$/',           // lang code according to RFC 2231.5
        ], '', $charset);

        if ($charset === 'BINARY') {
            return $charsets[$input] = null;
        }

        $str = preg_replace('/[^A-Z0-9]/', '', $charset);

        $result = $charset;

        if (isset(self::$_charset_aliases[$str])) {
            $result = self::$_charset_aliases[$str];
        } elseif (preg_match('/U[A-Z][A-Z](7|8|16|32)(BE|LE)*/', $str, $m)) {
            $result = 'UTF-'.$m[1].(!empty($m[2]) ? $m[2] : '');
        } elseif (preg_match('/ISO8859([0-9]{0,2})/', $str, $m)) {
            $iso = 'ISO-8859-'.($m[1] ?: 1);
            $result = $iso === 'ISO-8859-1' ? 'WINDOWS-1252' : $iso;
        } elseif (preg_match('/(WIN|WINDOWS)([0-9]+)/', $str, $m)) {
            $result = 'WINDOWS-'.$m[2];
        } elseif (preg_match('/LATIN(.*)/', $str, $m)) {
            $aliases = ['2' => 2, '3' => 3, '4' => 4, '5' => 9, '6' => 10,
                '7'         => 13, '8' => 14, '9' => 15, '10' => 16,
                'ARABIC'    => 6, 'CYRILLIC' => 5, 'GREEK' => 7, 'GREEK1' => 7, 'HEBREW' => 8,
            ];

            if ($m[1] === 1) {
                $result = 'WINDOWS-1252';
            } elseif (!empty($aliases[$m[1]])) {
                $result = 'ISO-8859-'.$aliases[$m[1]];
            }
        }

        $charsets[$input] = $result;

        return $result;
    }

    public static function convert(string $str, string $from, ?string $to = null) : string
    {
        static $iconv_options;

        $to = !$to ? self::DEFAULT_CHARSET : self::parse_charset($to);
        $from = self::parse_charset($from);

        if ($from === 'UTF-16' && !preg_match('/[^\x00-\x7F]/', $str)) {
            $from = 'UTF-8';
        }

        if ($from === $to || !$str || !$from) {
            return $str;
        }

        $out = false;

        $mbstring_sc = mb_substitute_character();
        mb_substitute_character('none');

        try {
            $out = mb_convert_encoding($str, $to, $from);
        } catch (Throwable $e) {
        }

        mb_substitute_character($mbstring_sc);

        if ($out !== false) {
            return $out;
        }

        if ($iconv_options === null) {
            if (@function_exists('iconv')) {
                $iconv_options = '//IGNORE';
                if (@iconv('', $iconv_options, '') === false) {
                    $iconv_options = '';
                }
            } else {
                $iconv_options = false;
            }
        }

        if ($iconv_options !== false && $from !== 'UTF7-IMAP' && $to !== 'UTF7-IMAP' && $from !== 'ISO-2022-JP') {
            try {
                $out = @iconv($from, $to.$iconv_options, $str);
            } catch (Throwable $e) {
                $out = false;
            }

            if ($out !== false) {
                return $out;
            }
        }

        return $str;
    }

    public static function decode_mime_string(string $input, ?bool $fallback = null) : string
    {
        $input = preg_replace('/\?=\s+=\?/', '?==?', $input);

        $re = '/=\?([^?]+)\?([BbQq])\?([^\n]*?)\?=/';

        // Find all RFC2047's encoded words
        if (preg_match_all($re, $input, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            $tmp = [];
            $out = '';
            $start = 0;

            foreach ($matches as $idx => $m) {
                $pos = $m[0][1];
                $charset = $m[1][0];
                $encoding = $m[2][0];
                $text = $m[3][0];
                $length = strlen($m[0][0]);

                if ($start !== $pos) {
                    $substr = substr($input, $start, $pos - $start);
                    $out .= $fallback === false ? $substr : self::convert($substr, self::DEFAULT_CHARSET);
                    $start = $pos;
                }
                $start += $length;

                $tmp[] = $text;
                if (!empty($matches[$idx + 1])) {
                    $next_match = $matches[$idx + 1];
                    if ($next_match[0][1] === $start
                        && $next_match[1][0] === $charset
                        && $next_match[2][0] === $encoding
                    ) {
                        continue;
                    }
                }

                $count = count($tmp);
                $text = '';

                if ($encoding === 'B' || $encoding === 'b') {
                    $rest = '';
                    for ($i = 0; $i < $count; $i++) {
                        $chunk = $rest.$tmp[$i];
                        $length = strlen($chunk);
                        if ($length % 4) {
                            $length = floor($length / 4) * 4;
                            $rest = substr($chunk, $length);
                            $chunk = substr($chunk, 0, $length);
                        }

                        $text .= base64_decode($chunk);
                    }
                } else {
                    for ($i = 0; $i < $count; $i++) {
                        $text .= $tmp[$i];
                    }

                    $text = str_replace('_', ' ', $text);
                    $text = quoted_printable_decode($text);
                }

                $out .= self::convert($text, $charset);
                $tmp = [];
            }

            if ($start !== strlen($input)) {
                $input = substr($input, $start);
                $out .= $fallback === false ? $input : self::convert($input, self::DEFAULT_CHARSET);
            }

            return $out;
        }

        return $fallback === false ? $input : self::convert($input, self::DEFAULT_CHARSET);
    }
}
