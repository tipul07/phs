<?php
namespace phs\libraries;

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
        private mixed $_line_feeder_callback,
        string $_outer_boundary = '',
    ) {
        if (!@is_callable($this->_line_feeder_callback)) {
            $this->_line_feeder_callback = null;
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
        if (!$this->_line_feeder_callback) {
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
            'content'           => $this->get_content(),
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
        if (!$this->_line_feeder_callback) {
            return;
        }

        $is_text_plain = $this->is_text_plain();
        $boundary = $this->get_part_boundary();
        $outer_boundary = $this->get_part_outer_boundary();
        $this->_parts_arr = [];

        $advance_line = true;
        while (null !== ($line = ($this->_line_feeder_callback)($advance_line))) {
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
                $part = new self($this->_line_feeder_callback, $boundary);
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

        while (null !== ($line = ($this->_line_feeder_callback)())
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
                $this->_settings['name'] = $this->_cleanup_filename(trim(trim($attrs[1] ?? ''), '"'));
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
                $this->_settings['filename'] = $this->_cleanup_filename(trim(trim($attrs[1] ?? ''), '"'));
            } elseif ($attr === 'size') {
                $this->_settings['size'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'creation-date') {
                $this->_settings['creation_date'] = trim(trim($attrs[1] ?? ''), '"');
            } elseif ($attr === 'modification-date') {
                $this->_settings['modification_date'] = trim(trim($attrs[1] ?? ''), '"');
            }
        }
    }

    private function _cleanup_filename(string $filename) : string
    {
        return str_replace(['..', '/', '\\', '~', ':'], '', $filename);
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
