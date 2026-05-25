<?php
namespace phs\libraries;

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
