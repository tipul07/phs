<?php

namespace phs\libraries;

// ! \version 2.01

class PHS_Line_params extends PHS_Language
{
    public const NEW_LINE_REPLACEMENT = '{{PHS_LP_NL}}';

    public static function new_line_in_string($str)
    {
        $str = str_replace("\r", "\n", str_replace(["\r\n", "\n\r"], "\n", $str));

        return str_replace("\n", self::NEW_LINE_REPLACEMENT, $str);
    }

    public static function from_string_to_new_line($str)
    {
        return str_replace(self::NEW_LINE_REPLACEMENT, "\r\n", $str);
    }

    /**
     * @param mixed $val Value to be converted to string
     *
     * @return bool|string String converted value
     */
    public static function value_to_string($val)
    {
        if (is_object($val) || is_resource($val)) {
            return false;
        }

        if (is_array($val)) {
            return self::new_line_in_string(@json_encode($val));
        }

        if (is_string($val)) {
            return '\''.self::new_line_in_string($val).'\'';
        }

        if (is_bool($val)) {
            return !empty($val) ? 'true' : 'false';
        }

        if ($val === null) {
            return 'null';
        }

        if (is_numeric($val)) {
            return $val;
        }

        return false;
    }

    /**
     * @param string $str Value (from key-value pair) to be converted
     *
     * @return null|bool|int|float|string Converted value
     */
    public static function string_to_value($str)
    {
        if (!is_string($str)) {
            return null;
        }

        if (($val = @json_decode(self::from_string_to_new_line($str), true)) !== null) {
            return $val;
        }

        if (is_numeric($str)) {
            return $str;
        }

        if (($tch = substr($str, 0, 1)) === '\'' || $tch = '"') {
            $str = substr($str, 1);
        }
        if (($tch = substr($str, -1)) === '\'' || $tch = '"') {
            $str = substr($str, 0, -1);
        }

        $str_lower = strtolower($str);
        if ($str_lower === 'null') {
            return null;
        }

        if ($str_lower === 'false') {
            return false;
        }

        if ($str_lower === 'true') {
            return true;
        }

        return self::from_string_to_new_line($str);
    }

    /**
     * @param string $line_str Line to be parsed
     * @param int $comment_no Internal counter to know what line of comment is this string (if comment)
     *
     * @return array|bool
     */
    public static function parse_string_line($line_str, int $comment_no = 0)
    {
        if (!is_string($line_str)) {
            $line_str = '';
        }

        // allow empty lines (keeps file 'styling' same)
        if (trim($line_str) === '') {
            $line_str = '';
        }

        $return_arr = [];
        $return_arr['key'] = '';
        $return_arr['val'] = '';
        $return_arr['comment_no'] = $comment_no;

        $first_char = substr($line_str, 0, 1);
        if ($line_str === '' || $first_char === '#' || $first_char === ';') {
            $comment_no++;

            $return_arr['key'] = '='.$comment_no.'='; // comment count added to avoid comment key overwrite
            $return_arr['val'] = $line_str;
            $return_arr['comment_no'] = $comment_no;

            return $return_arr;
        }

        $line_details = explode('=', $line_str, 2);
        $key = trim($line_details[0]);

        if ($key === '') {
            return false;
        }

        if (!isset($line_details[1])) {
            $return_arr['key'] = $key;
            $return_arr['val'] = '';

            return $return_arr;
        }

        $return_arr['key'] = $key;
        $return_arr['val'] = self::string_to_value($line_details[1]);

        return $return_arr;
    }

    /**
     * @param array $lines_data Parsed line parameters
     *
     * @return string String representing converted array of line parameters
     */
    public static function to_string($lines_data) : string
    {
        if (empty($lines_data) || !is_array($lines_data)) {
            return '';
        }

        $lines_str = '';
        $first_line = true;
        foreach ($lines_data as $key => $val) {
            if (!$first_line) {
                $lines_str .= "\r\n";
            }

            $first_line = false;

            // In normal cases there cannot be '=' char in key, so we interpret that value should just be passed as-it-is
            if (substr($key, 0, 1) === '=') {
                $lines_str .= $val;
                continue;
            }

            // Don't save if error converting to string
            if (($line_val = self::value_to_string($val)) === false) {
                continue;
            }

            $lines_str .= $key.'='.$line_val;
        }

        return $lines_str;
    }

    /**
     * @param array|string $string String to be parsed or a parsed array
     *
     * @return array Parse line parameters in an array
     */
    public static function parse_string($string) : array
    {
        if (empty($string)
         || (!is_array($string) && !is_string($string))) {
            return [];
        }

        if (is_array($string)) {
            return $string;
        }

        $string = str_replace("\r", "\n", str_replace(["\r\n", "\n\r"], "\n", $string));
        $lines_arr = explode("\n", $string);

        $return_arr = [];
        $comment_no = 1;
        foreach ($lines_arr as $line_nr => $line_str) {
            if (!($line_data = self::parse_string_line($line_str, $comment_no))
             || !is_array($line_data) || !isset($line_data['key']) || $line_data['key'] === '') {
                continue;
            }

            $return_arr[$line_data['key']] = $line_data['val'];
            $comment_no = $line_data['comment_no'];
        }

        return $return_arr;
    }

    /**
     * @param array|string $current_data Current line parameters
     * @param array|string $append_data Appending line parameters
     *
     * @return array Merged array from parsed parameters
     */
    public static function update_line_params($current_data, $append_data) : array
    {
        if (empty($append_data) || (!is_array($append_data) && !is_string($append_data))) {
            $append_data = [];
        }
        if (empty($current_data) || (!is_array($current_data) && !is_string($current_data))) {
            $current_data = [];
        }

        if (!is_array($append_data)) {
            $append_arr = self::parse_string($append_data);
        } else {
            $append_arr = $append_data;
        }

        if (!is_array($current_data)) {
            $current_arr = self::parse_string($current_data);
        } else {
            $current_arr = $current_data;
        }

        if (!empty($append_arr)) {
            foreach ($append_arr as $key => $val) {
                $current_arr[$key] = $val;
            }
        }

        return $current_arr;
    }
}
