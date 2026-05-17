<?php
namespace phs\libraries;

class PHS_Params
{
    public const T_ASIS = 1, T_INT = 2, T_FLOAT = 3, T_ALPHANUM = 4, T_SAFEHTML = 5, T_NOHTML = 6, T_EMAIL = 7,
        T_REMSQL_CHARS = 8, T_ARRAY = 9, T_DATE = 10, T_URL = 11, T_BOOL = 12, T_NUMERIC_BOOL = 13, T_TIMESTAMP = 14,
        T_GUID = 15;

    public const FLOAT_PRECISION = 10;

    public const REGEX_INT = '/^[+-]?\d+$/', REGEX_FLOAT = '/^[+-]?\d+\.?\d*$/',
        REGEX_EMAIL = '/^[a-zA-Z0-9]+[a-zA-Z0-9\._\-\+]*@[a-zA-Z0-9_-]+\.[a-zA-Z0-9\._-]+$/',
        REGEX_URL = '_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS',
        REGEX_GUID = '/^(\{)?[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}(?(1)\})$/i';

    public static function get_valid_types() : array
    {
        return [
            self::T_ASIS, self::T_INT, self::T_FLOAT, self::T_ALPHANUM, self::T_SAFEHTML, self::T_NOHTML, self::T_EMAIL,
            self::T_REMSQL_CHARS, self::T_ARRAY, self::T_DATE, self::T_URL, self::T_BOOL, self::T_NUMERIC_BOOL,
            self::T_TIMESTAMP, self::T_GUID,
        ];
    }

    public static function valid_type(int $type) : bool
    {
        return in_array($type, self::get_valid_types(), true);
    }

    public static function check_type(mixed $val, int $type) : bool
    {
        return match ($type) {
            self::T_INT       => preg_match(self::REGEX_INT, $val),
            self::T_FLOAT     => preg_match(self::REGEX_FLOAT, $val),
            self::T_ALPHANUM  => ctype_alnum((string)$val),
            self::T_EMAIL     => preg_match(self::REGEX_EMAIL, $val),
            self::T_DATE      => !empty($val) && @strtotime($val) !== false,
            self::T_TIMESTAMP => !empty($val) && (is_numeric($val) || @strtotime($val) !== false),
            self::T_URL       => preg_match(self::REGEX_URL, $val),
            self::T_GUID      => preg_match(self::REGEX_GUID, $val),
        };
    }

    public static function set_type(mixed $val, int $type, array $extra = []) : mixed
    {
        if ($val === null) {
            return null;
        }

        $extra['trim_before'] = !empty($extra['trim_before']);

        if ($extra['trim_before']
            && is_scalar($val)) {
            $val = trim($val);
        }

        switch ($type) {
            default:
            case self::T_ASIS:
                return $val;
            case self::T_INT:
                // Make sure we trim
                if (!$extra['trim_before']) {
                    $val = trim($val);
                }

                if ($val !== '') {
                    $val = (int)$val;
                }

                return $val;
            case self::T_FLOAT:
                // Make sure we trim
                if (!$extra['trim_before']) {
                    $val = trim($val);
                }

                if (empty($extra['digits'])) {
                    $extra['digits'] = self::FLOAT_PRECISION;
                }

                if ($val !== '') {
                    if (@function_exists('bcmul')) {
                        $val = @bcmul($val, 1, $extra['digits']);
                    } else {
                        $val = @number_format($val, $extra['digits'], '.', '');
                    }

                    if (str_contains($val, '.')) {
                        $val = trim($val, '0');
                        if (str_ends_with($val, '.')) {
                            $val = substr($val, 0, -1);
                        }
                        if (str_starts_with($val, '.')) {
                            $val = '0'.$val;
                        }
                    }

                    $val = (float)$val;
                }

                return $val;
            case self::T_ALPHANUM:
                return preg_replace('/^([a-zA-Z0-9]+)$/', '$1', strip_tags($val));
            case self::T_SAFEHTML:
                return htmlspecialchars($val);
            case self::T_EMAIL:
            case self::T_NOHTML:
            case self::T_URL:
                return strip_tags($val);
            case self::T_REMSQL_CHARS:
                return str_replace(['--', '\b', '\Z', '%'], '', $val);
            case self::T_ARRAY:
                if (empty($val) || !is_array($val)) {
                    return [];
                }

                if (empty($extra['type'])) {
                    $extra['type'] = self::T_ASIS;
                }

                foreach ($val as $key => $vval) {
                    $val[$key] = self::set_type($vval, $extra['type'], $extra);
                }

                return $val;
            case self::T_DATE:
                if (!$extra['trim_before']) {
                    $val = trim($val);
                }

                if (empty($val) || ($val = @strtotime($val)) === false || $val === -1) {
                    $val = false;
                } elseif (!empty($extra['format'])) {
                    $val = @date($extra['format'], $val);
                }

                return $val;
            case self::T_TIMESTAMP:
                if (!$extra['trim_before']) {
                    $val = trim($val);
                }

                if (empty($val)) {
                    $val = 0;
                } elseif (is_numeric($val)) {
                    $val = (int)$val;
                } elseif (($val = @strtotime($val)) === false || $val === -1) {
                    $val = 0;
                }

                if ($val < 0) {
                    $val = 0;
                }

                return $val;
            case self::T_BOOL:
            case self::T_NUMERIC_BOOL:
                if (is_string($val)) {
                    if (!$extra['trim_before']) {
                        $val = trim($val);
                    }

                    $low_val = strtolower($val);

                    if ($low_val === 'true') {
                        $val = true;
                    } elseif ($low_val === 'false') {
                        $val = false;
                    }
                }

                if ($type === self::T_BOOL) {
                    return !empty($val);
                }

                if ($type === self::T_NUMERIC_BOOL) {
                    return !empty($val) ? 1 : 0;
                }
                break;

            case self::T_GUID:
                // Make sure we trim
                if (!$extra['trim_before']) {
                    $val = trim($val);
                }

                if (!self::check_type($val, self::T_GUID)) {
                    $val = null;
                }

                return $val;
        }

        return null;
    }

    /**
     * Checks _GET (g), _POST (p), _SESSION (s), _FILES (f), _COOKIE (c), _REQUEST (r), _ENV (e), _SERVER (v) arrays to find $v key in provided order in $from
     *
     * @param string $from Order in which to check arrays as string _GET (g), _POST (p), _SESSION (s), _FILES (f), _COOKIE (c), _REQUEST (r), _ENV (e), _SERVER (v)
     * @param null|string $v Key to be search in provided order in arrays
     * @param int $type
     * @param array $extra
     *
     * @return null|mixed
     */
    public static function _var(string $from, ?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if (!$from) {
            return null;
        }

        $from = strtolower($from);
        while (!empty($from[0])) {
            switch ($from[0]) {
                case 'g':
                    if (isset($_GET[$v])) {
                        return self::_g($v, $type, $extra);
                    }
                    break;
                case 'p':
                    if (isset($_POST[$v])) {
                        return self::_p($v, $type, $extra);
                    }
                    break;
                case 's':
                    if (isset($_SESSION[$v])) {
                        return self::_s($v, $type, $extra);
                    }
                    break;
                case 'f':
                    if (isset($_FILES[$v])) {
                        return self::_f($v);
                    }
                    break;
                case 'c':
                    if (isset($_COOKIE[$v])) {
                        return self::_c($v, $type, $extra);
                    }
                    break;
                case 'r':
                    if (isset($_REQUEST[$v])) {
                        return self::_r($v, $type, $extra);
                    }
                    break;
                case 'e':
                    if (isset($_ENV[$v])) {
                        return self::_e($v, $type, $extra);
                    }
                    break;
                case 'v':
                    if (isset($_SERVER[$v])) {
                        return self::_v($v, $type, $extra);
                    }
                    break;
            }

            $from = substr($from, 1);
        }

        return null;
    }

    public static function _gp(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        $var = $_GET[$v] ?? $_POST[$v] ?? null;
        if ($v === null || $var === null) {
            return null;
        }

        return self::set_type($var, $type, $extra);
    }

    public static function _pg(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        $var = $_POST[$v] ?? $_GET[$v] ?? null;
        if ($v === null || $var === null) {
            return null;
        }

        return self::set_type($var, $type, $extra);
    }

    public static function _g(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_GET[$v])) {
            return null;
        }

        return self::set_type($_GET[$v], $type, $extra);
    }

    public static function _p(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_POST[$v])) {
            return null;
        }

        return self::set_type($_POST[$v], $type, $extra);
    }

    public static function _f(?string $v) : ?array
    {
        if ($v === null
            || !isset($_FILES[$v]['name'])
            || $_FILES[$v]['name'] === '') {
            return null;
        }

        return $_FILES[$v];
    }

    public static function _s(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_SESSION[$v])) {
            return null;
        }

        return self::set_type($_SESSION[$v], $type, $extra);
    }

    public static function _c(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_COOKIE[$v])) {
            return null;
        }

        return self::set_type($_COOKIE[$v], $type, $extra);
    }

    public static function _r(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_REQUEST[$v])) {
            return null;
        }

        return self::set_type($_REQUEST[$v], $type, $extra);
    }

    public static function _v(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_SERVER[$v])) {
            return null;
        }

        return self::set_type($_SERVER[$v], $type, $extra);
    }

    public static function _e(?string $v, int $type = self::T_ASIS, array $extra = []) : mixed
    {
        if ($v === null || !isset($_ENV[$v])) {
            return null;
        }

        return self::set_type($_ENV[$v], $type, $extra);
    }
}
