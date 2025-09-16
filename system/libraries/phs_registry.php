<?php
namespace phs\libraries;

if ((!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))
 && !defined('PHS_VERSION')) {
    exit;
}

class PHS_Registry extends PHS_Language
{
    // Array with variables set for current view only. General information will be set using self::set_data()
    protected array $_context = [];

    private static array $data = [];

    public function get_full_context() : array
    {
        return $this->_context;
    }

    public function get_context(string $key) : mixed
    {
        return $this->_context[$key] ?? null;
    }

    /**
     * @param array|string $key
     * @param null|mixed $val
     *
     * @return bool
     */
    public function set_context($key, mixed $val = null) : bool
    {
        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                $this->_context[$kkey] = $kval;
            }
        }

        if (!is_scalar($key)) {
            return false;
        }

        $this->_context[$key] = $val;

        return true;
    }

    /**
     * This is usually used when we have a numeric array holding records from database (eg. values returned by get_list() method on models.
     * If we want to get from $a[0]['name'], $a[1]['name'] => $a['name'][0], $a['name'][1] which is easier to work with (eg. implode all names)
     *
     * @param array $provided_arr Array to be walked
     * @param array $keys_arr Keys to be translated
     *
     * @return array Array with first keys from $keys_arr and second set of keys first set of keys from $strings_arr
     */
    public function extract_array_keys(array $provided_arr, array $keys_arr) : array
    {
        if (empty($provided_arr)) {
            return [];
        }

        if (empty($keys_arr)) {
            return $provided_arr;
        }

        $return_arr = [];
        foreach ($provided_arr as $key => $val_arr) {
            if (!is_array($val_arr)) {
                continue;
            }

            foreach ($keys_arr as $ret_key) {
                if (!isset($val_arr[$ret_key])) {
                    continue;
                }

                $return_arr[$ret_key][$key] = $val_arr[$ret_key];
            }
        }

        return $return_arr;
    }

    /**
     * Translate an array to provided language. It is expected that $strings_arr is an array of arrays and $keys_arr are keys inside "leafs" arrays.
     * This is useful when defining statuses, types, etc. arrays inside models which contains texts which normally should be translated.
     * Check $STATUSES_ARR found in built-in models to understand.
     *
     * @param array $strings_arr Array to be walked
     * @param array $keys_arr Keys to be translated
     * @param null|bool|string $lang Language in which we want array translated
     *
     * @return array Translated array
     */
    public function translate_array_keys(array $strings_arr, array $keys_arr, null | bool | string $lang = null) : array
    {
        if (!$strings_arr) {
            return [];
        }

        if (!$keys_arr) {
            return $strings_arr;
        }

        if (!$lang) {
            $lang = self::get_current_language();
        }

        foreach ($strings_arr as $key => $val_arr) {
            if (!is_array($val_arr)) {
                continue;
            }

            foreach ($keys_arr as $trans_key) {
                if (!isset($strings_arr[$key][$trans_key])
                 || !is_string($strings_arr[$key][$trans_key])) {
                    continue;
                }

                $strings_arr[$key][$trans_key] = $this->_pt($strings_arr[$key][$trans_key], $lang);
            }
        }

        return $strings_arr;
    }

    public static function get_full_data() : array
    {
        return self::$data;
    }

    public static function get_data(string $key) : mixed
    {
        return self::$data[$key] ?? null;
    }

    public static function set_full_data(array $arr, bool $merge = false) : bool
    {
        if (empty($merge)) {
            self::$data = $arr;
        } else {
            self::$data = self::merge_array_assoc(self::$data, $arr);
        }

        return true;
    }

    /**
     * @param string|array $key Key for which we want to change value or full array with key/value pairs
     * @param null|mixed $val Value of provided key or null in case we receive a full array of key/value pairs
     *
     * @return bool
     */
    public static function set_data(string | array $key, mixed $val = null) : bool
    {
        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                self::$data[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)) {
            return false;
        }

        self::$data[$key] = $val;

        return true;
    }

    /**
     * @param string $str
     * @param string|array $args
     *
     * @return string
     */
    public static function sprintf_all(string $str, $args) : string
    {
        if (!is_scalar($args) && !is_array($args)) {
            return $str;
        }

        if (!is_array($args)) {
            $args = [$args];
        }

        if (!($args_count = count($args))) {
            return $str;
        }

        // in case we don't have numeric indexes for the args array
        $keys = array_keys($args);
        // we will cycle through $args array
        if (!($perc_s = substr_count($str, '%s'))) {
            $perc_s = 0;
        }

        $keyi = 0;
        $new_args = [];
        while ($perc_s > 0) {
            // safe...
            if (!isset($args[$keys[$keyi]])) {
                $keyi = 0;
            }

            $new_args[] = $args[$keys[$keyi]];

            $keyi++;
            if ($keyi >= $args_count) {
                $keyi = 0;
            }

            $perc_s--;
        }

        if (empty($new_args)) {
            return $str;
        }

        return @vsprintf($str, $new_args);
    }

    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    public static function merge_array_assoc($arr1, $arr2) : array
    {
        if (empty($arr1) || !is_array($arr1)) {
            return is_array($arr2) ? $arr2 : [];
        }
        if (empty($arr2) || !is_array($arr2)) {
            return $arr1;
        }

        foreach ($arr2 as $key => $val) {
            $arr1[$key] = $val;
        }

        return $arr1;
    }

    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    public static function merge_array_assoc_existing($arr1, $arr2) : array
    {
        if (empty($arr1) || !is_array($arr1)) {
            return $arr2;
        }
        if (empty($arr2) || !is_array($arr2)) {
            return $arr1;
        }

        foreach ($arr2 as $key => $val) {
            if (!array_key_exists($key, $arr1)) {
                continue;
            }

            $arr1[$key] = $val;
        }

        return $arr1;
    }

    public static function unify_array_insensitive(array $arr1, array $params = []) : array
    {
        if (empty($arr1)) {
            return [];
        }

        $params['use_newer_key_case'] = !isset($params['use_newer_key_case']) || !empty($params['use_newer_key_case']);
        $params['trim_keys'] = !empty($params['trim_keys']);

        $lower_to_raw_arr = [];
        $result = [];
        foreach ($arr1 as $key => $val) {
            if (is_int($key)) {
                $result[$key] = $val;
                continue;
            }

            if (!empty($params['trim_keys'])) {
                $key = trim($key);
            }

            $lower_key = strtolower($key);

            if (isset($lower_to_raw_arr[$lower_key])) {
                if (empty($params['use_newer_key_case'])) {
                    $key = $lower_to_raw_arr[$lower_key];
                } elseif (!empty($lower_to_raw_arr[$lower_key])
                          && $lower_to_raw_arr[$lower_key] !== $key
                          && array_key_exists($lower_to_raw_arr[$lower_key], $result)) {
                    unset($result[$lower_to_raw_arr[$lower_key]]);
                }
            }

            $result[$key] = $val;
            $lower_to_raw_arr[$lower_key] = $key;
        }

        return $result;
    }

    public static function array_key_exists_insensitive(array $arr1, string $key) : bool
    {
        if (empty($arr1)) {
            return false;
        }

        $params['trim_keys'] = !empty($params['trim_keys']);

        $lower_key = strtolower($key);

        foreach ($arr1 as $a_key => $a_val) {
            if (!empty($params['trim_keys'])) {
                $a_key = trim($a_key);
            }

            if (strtolower($a_key) === $lower_key) {
                return true;
            }
        }

        return false;
    }

    public static function array_replace_value_key_insensitive(array $arr1, string $key, mixed $value) : array
    {
        if (empty($arr1)) {
            return [];
        }

        $params['trim_keys'] = !empty($params['trim_keys']);
        $params['only_first_value'] = !isset($params['only_first_value']) || !empty($params['only_first_value']);

        $lower_key = strtolower($key);

        foreach ($arr1 as $a_key => $a_val) {
            if (!empty($params['trim_keys'])) {
                $a_key = trim($a_key);
            }

            if (strtolower($a_key) === $lower_key) {
                $arr1[$a_key] = $value;

                if ($params['only_first_value']) {
                    break;
                }
            }
        }

        return $arr1;
    }

    /**
     * @param array $arr1
     * @param bool|array $params
     *
     * @return array
     */
    public static function array_lowercase_keys(array $arr1, array $params = []) : array
    {
        if (empty($arr1)) {
            return [];
        }

        $params['trim_keys'] = !empty($params['trim_keys']);

        $new_array = [];
        foreach ($arr1 as $key => $val) {
            if (!empty($params['trim_keys'])) {
                $key = trim($key);
            }

            $new_array[strtolower($key)] = $val;
        }

        return $new_array;
    }

    public static function merge_array_assoc_insensitive($arr1, $arr2, array $params = []) : array
    {
        if (empty($arr1) || !is_array($arr1)) {
            return is_array($arr2) ? $arr2 : [];
        }
        if (empty($arr2) || !is_array($arr2)) {
            return $arr1;
        }

        return self::unify_array_insensitive(self::merge_array_assoc($arr1, $arr2), $params);
    }

    public static function merge_array_assoc_recursive($arr1, $arr2) : array
    {
        if (empty($arr1) || !is_array($arr1)) {
            return is_array($arr2) ? $arr2 : [];
        }
        if (empty($arr2) || !is_array($arr2)) {
            return $arr1;
        }

        foreach ($arr2 as $key => $val) {
            if (!array_key_exists($key, $arr1)
             || !is_array($val)) {
                $arr1[$key] = $val;
            } else {
                $arr1[$key] = self::merge_array_assoc_recursive($arr1[$key], $val);
            }
        }

        return $arr1;
    }

    /**
     * @param array $arr Array to be validated for keys
     * @param array $definition_arr Array of key definitions. Keys not found in arr will not be returned in resulting array
     *
     * @return array If default array is not an array returns false else validated array is returned
     */
    public static function validate_array_keys_from_definition($arr, $definition_arr) : array
    {
        if (empty($definition_arr) || !is_array($definition_arr)
         || empty($arr) || !is_array($arr)) {
            return [];
        }

        $new_arr = [];
        foreach ($arr as $key => $val) {
            if (!array_key_exists($key, $definition_arr)) {
                continue;
            }

            $new_arr[$key] = $val;
        }

        return $new_arr;
    }

    /**
     * @param null|array $arr Array to be validated
     * @param null|array $default_arr Array keys and default values which should be present in array to be validated
     *
     * @return array If default array is not an array or is empty, returns original array,
     *               else validated array is returned
     */
    public static function validate_array($arr, $default_arr) : array
    {
        if (empty($arr) || !is_array($arr)) {
            $arr = [];
        }

        if (empty($default_arr) || !is_array($default_arr)) {
            return $arr;
        }

        foreach ($default_arr as $key => $val) {
            if (!array_key_exists($key, $arr)) {
                $arr[$key] = $val;
            }
        }

        return $arr;
    }

    /**
     * @param array $arr
     * @param array $default_arr
     *
     * @return array
     */
    public static function validate_array_recursive($arr, $default_arr) : array
    {
        if (empty($arr) || !is_array($arr)) {
            $arr = [];
        }

        if (empty($default_arr) || !is_array($default_arr)) {
            return $arr;
        }

        foreach ($default_arr as $key => $val) {
            if (!array_key_exists($key, $arr)) {
                $arr[$key] = $val;
            } elseif (is_array($val)) {
                if (!is_array($arr[$key])) {
                    $arr[$key] = [];
                }

                if (!empty($val)) {
                    $arr[$key] = self::validate_array_recursive($arr[$key], $val);
                }
            }
        }

        return $arr;
    }

    /**
     * @param array $arr
     * @param array $default_arr
     *
     * @return array
     */
    public static function validate_array_to_new_array($arr, $default_arr) : array
    {
        if (empty($default_arr) || !is_array($default_arr)) {
            return [];
        }

        if (empty($arr) || !is_array($arr)) {
            $arr = [];
        }

        $new_array = [];
        foreach ($default_arr as $key => $val) {
            if (!array_key_exists($key, $arr)) {
                $new_array[$key] = $val;
            } else {
                $new_array[$key] = $arr[$key];
            }
        }

        return $new_array;
    }

    /**
     * @param array $arr
     * @param array $default_arr
     *
     * @return array
     */
    public static function validate_array_to_new_array_recursive($arr, $default_arr) : array
    {
        if (empty($default_arr) || !is_array($default_arr)) {
            return [];
        }

        if (empty($arr) || !is_array($arr)) {
            $arr = [];
        }

        $new_array = [];
        foreach ($default_arr as $key => $val) {
            if (!array_key_exists($key, $arr)) {
                $new_array[$key] = $val;
            } elseif (is_array($val)) {
                if (!is_array($arr[$key])) {
                    $arr[$key] = [];
                    $new_array[$key] = [];
                }

                if (!empty($val)) {
                    $new_array[$key] = self::validate_array_to_new_array_recursive($arr[$key], $val);
                }
            } else {
                $new_array[$key] = $arr[$key];
            }
        }

        return $new_array;
    }

    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    public static function array_merge_unique_values($arr1, $arr2) : array
    {
        if (empty($arr1) || !is_array($arr1)) {
            $arr1 = [];
        }
        if (empty($arr2) || !is_array($arr2)) {
            $arr2 = [];
        }

        $return_arr = [];
        foreach ($arr2 as $val) {
            if (!is_scalar($val)) {
                continue;
            }

            $return_arr[$val] = 1;
        }
        foreach ($arr1 as $val) {
            if (!is_scalar($val)) {
                continue;
            }

            $return_arr[$val] = 1;
        }

        return @array_keys($return_arr);
    }

    /**
     * Checks if provided arrays values are same (order is not checked)
     * Values MUST BE SCALARS!!!
     *
     * @param array $arr1
     * @param array $arr2
     *
     * @return bool True is arrays hold same values (ignoring position in array)
     */
    public static function arrays_have_same_values($arr1, $arr2) : bool
    {
        if (!is_array($arr1) || !is_array($arr2)) {
            return false;
        }

        if (empty($arr1) && empty($arr2)) {
            return true;
        }

        if (empty($arr1) || empty($arr2)
         || count($arr1) !== count($arr2)) {
            return false;
        }

        $new_arr1 = [];
        foreach ($arr1 as $val) {
            if (!is_scalar($val)) {
                return false;
            }

            $new_arr1[$val] = true;
        }

        foreach ($arr2 as $val) {
            if (!is_scalar($val)
                || empty($new_arr1[$val])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells if provided array has only numeric indexes
     *
     * @param array $arr Array to be checked
     * @param array|bool $params Parameters
     *
     * @return bool True is array has only integers as indexes, false if indexes are something else than integers
     */
    public static function array_has_numeric_indexes($arr, $params = false) : bool
    {
        if (empty($arr) || !is_array($arr)) {
            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        // How many checks to be done. 0 means no limit, traverse whole array
        if (empty($params['max_iterations'])) {
            $params['max_iterations'] = 0;
        } else {
            $params['max_iterations'] = (int)$params['max_iterations'];
        }

        $knti = 0;
        foreach ($arr as $key => $junk) {
            if (!empty($params['max_iterations'])
             && $params['max_iterations'] <= $knti) {
                break;
            }

            if ((string)((int)$key) !== (string)$key) {
                return false;
            }

            $knti++;
        }

        return true;
    }

    /**
     * @param string $str
     * @param array $params
     *
     * @return string[]
     */
    public static function extract_strings_from_comma_separated($str, array $params = []) : array
    {
        if (!is_string($str)) {
            return [];
        }

        $params['trim_parts'] = !isset($params['trim_parts']) || !empty($params['trim_parts']);
        $params['dump_empty_parts'] = !isset($params['dump_empty_parts']) || !empty($params['dump_empty_parts']);
        $params['to_lowercase'] = !empty($params['to_lowercase']);
        $params['to_uppercase'] = !empty($params['to_uppercase']);

        $str_arr = explode(',', $str);
        $return_arr = [];
        foreach ($str_arr as $str_part) {
            if (!empty($params['trim_parts'])) {
                $str_part = trim($str_part);
            }

            if (!empty($params['dump_empty_parts'])
                && $str_part === '') {
                continue;
            }

            if (!empty($params['to_lowercase'])) {
                $str_part = strtolower($str_part);
            }
            if (!empty($params['to_uppercase'])) {
                $str_part = strtoupper($str_part);
            }

            $return_arr[] = $str_part;
        }

        return $return_arr;
    }

    /**
     * Returns array of integers cast from comma separated values from provided string
     *
     * @param string $str String to be checked
     * @param array $params Parameters
     *
     * @return array Array of cast integers
     */
    public static function extract_integers_from_comma_separated($str, array $params = []) : array
    {
        if (!is_string($str)) {
            return [];
        }

        $params['dump_empty_parts'] = !isset($params['dump_empty_parts']) || !empty($params['dump_empty_parts']);
        $params['dump_zeros'] = !empty($params['dump_zeros']);

        $str_arr = explode(',', $str);
        $return_arr = [];
        foreach ($str_arr as $orig_int_part) {
            $orig_int_part = trim($orig_int_part);
            $int_part = (int)$orig_int_part;

            if (($params['dump_empty_parts'] && $orig_int_part === '')
                || ($params['dump_zeros'] && $int_part === 0 && $orig_int_part !== '0')) {
                continue;
            }

            $return_arr[] = $int_part;
        }

        return $return_arr;
    }

    /**
     * Get all values in string that can be cast to non-empty integers.
     *
     * @param array $arr Array to be checked
     *
     * @return array
     */
    public static function extract_integers_from_array($arr) : array
    {
        if (empty($arr) || !is_array($arr)) {
            return [];
        }

        $return_arr = [];
        foreach ($arr as $orig_int_part) {
            $orig_int_part = trim($orig_int_part);
            $int_part = (int)$orig_int_part;

            if ($int_part === 0 && $orig_int_part !== '0') {
                continue;
            }

            $return_arr[] = $int_part;
        }

        return $return_arr;
    }

    /**
     * Get all values in string that can be cast to non-empty integers.
     *
     * @param array $arr Array to be checked
     * @param array $params Parameters
     *
     * @return array
     */
    public static function extract_strings_from_array($arr, array $params = []) : array
    {
        if (empty($arr) || !is_array($arr)) {
            return [];
        }

        $params['trim_parts'] = !isset($params['trim_parts']) || !empty($params['trim_parts']);
        $params['dump_empty_parts'] = !isset($params['dump_empty_parts']) || !empty($params['dump_empty_parts']);
        $params['to_lowercase'] = !empty($params['to_lowercase']);
        $params['to_uppercase'] = !empty($params['to_uppercase']);

        $return_arr = [];
        foreach ($arr as $key => $str_part) {
            if ($params['trim_parts']) {
                $str_part = trim($str_part);
            }

            if ($params['dump_empty_parts']
                && $str_part === '') {
                continue;
            }

            if ($params['to_lowercase']) {
                $str_part = strtolower($str_part);
            }
            if ($params['to_uppercase']) {
                $str_part = strtoupper($str_part);
            }

            if (is_string($key)) {
                $return_arr[$key] = $str_part;
            } else {
                $return_arr[] = $str_part;
            }
        }

        return $return_arr;
    }

    /**
     * Extract all key-values pairs from an array for which key is prefixed with a provided string
     *
     * @param array $arr Array with keys-values pairs
     * @param string $prefix String which is to be checked as prefix in keys
     * @param array $params Optional parameters to the function
     *
     * @return array Resulting key-values pairs which are prefixed with provided string
     */
    public static function extract_keys_with_prefix($arr, string $prefix, array $params = []) : array
    {
        if (empty($arr) || !is_array($arr)) {
            return [];
        }

        $params['remove_prefix_from_keys'] = !isset($params['remove_prefix_from_keys'])
                                             || !empty($params['remove_prefix_from_keys']);

        if ($prefix === '') {
            return $arr;
        }

        $return_arr = [];
        $prefix_len = strlen($prefix);
        foreach ($arr as $key => $val) {
            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            if ($params['remove_prefix_from_keys']) {
                $key = substr($key, $prefix_len);
            }

            $return_arr[$key] = $val;
        }

        return $return_arr;
    }
}
