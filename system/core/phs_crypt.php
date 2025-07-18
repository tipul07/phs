<?php
namespace phs;

use phs\libraries\PHS_Encdec;
use phs\libraries\PHS_Language;

// ! @version 1.10

class PHS_Crypt extends PHS_Language
{
    public const CRYPT_EXPORT_VERSION = 1;

    private static $internal_keys = [];

    private static $crypt_key = '';

    /**
     * @param bool|string $key
     *
     * @return bool|string
     */
    public static function crypting_key($key = false)
    {
        if ($key === false) {
            return self::$crypt_key;
        }

        self::$crypt_key = $key;

        return self::$crypt_key;
    }

    /**
     * @return array
     */
    public static function get_internal_keys()
    {
        return self::$internal_keys;
    }

    /**
     * @param array $keys_arr
     *
     * @return bool
     */
    public static function set_internal_keys($keys_arr = [])
    {
        if (empty($keys_arr) || !is_array($keys_arr)) {
            return false;
        }

        self::$internal_keys = $keys_arr;

        return true;
    }

    public static function quick_encode($str, array $params = []) : ?string
    {
        if (!($enc_dec = self::_create_encdec_instance($params))) {
            return null;
        }

        return $enc_dec->encrypt($str);
    }

    public static function quick_decode($str, array $params = []) : ?string
    {
        if (!is_string($str)
           || !($enc_dec = self::_create_encdec_instance($params))) {
            return null;
        }

        return $enc_dec->decrypt($str);
    }

    public static function quick_encode_buffer_for_export_as_json(string $buf, string $crypting_key, array $params = []) : ?string
    {
        if (!($json_arr = self::quick_encode_buffer_for_export_as_array($buf, $crypting_key, $params))
            || !($json_buf = @json_encode($json_arr))) {
            return null;
        }

        return $json_buf;
    }

    public static function quick_encode_buffer_for_export_as_array(string $buf, string $crypting_key, array $params = []) : ?array
    {
        self::st_reset_error();

        if (empty($crypting_key)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Crypting internal keys not provided.'));

            return null;
        }

        $params['crypting_key'] = $crypting_key;
        $params['internal_keys'] = self::generate_crypt_internal_keys();

        $enc_buf = '';
        if ($buf !== ''
            && !($enc_buf = self::quick_encode($buf, $params))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Error encrypting buffer.'));

            return null;
        }

        return [
            'version' => self::CRYPT_EXPORT_VERSION,
            'ik'      => $params['internal_keys'],
            'data'    => $enc_buf,
        ];
    }

    public static function quick_decode_from_export_json_string(string $json_str, string $crypting_key, array $params = []) : ?string
    {
        self::st_reset_error();

        if (empty($json_str)
         || !($json_arr = @json_decode($json_str, true))
         || !is_array($json_arr)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Error encrypting buffer.'));

            return null;
        }

        return self::quick_decode_from_export_array($json_arr, $crypting_key, $params);
    }

    public static function quick_decode_from_export_array(array $export_arr, string $crypting_key, array $params = []) : ?string
    {
        self::st_reset_error();

        if (empty($crypting_key)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Crypting key not provided.'));

            return null;
        }

        if (empty($export_arr['ik']) || !is_array($export_arr['ik'])) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid export data provided.'));

            return null;
        }

        $params['crypting_key'] = $crypting_key;
        $params['internal_keys'] = $export_arr['ik'];

        if (empty($export_arr['data'])
            || !($dec_buf = self::quick_decode($export_arr['data'], $params))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Error decrypting buffer.'));

            return null;
        }

        return $dec_buf;
    }

    /**
     * Generate crypt key used in crypting functionality
     * @param int $len
     *
     * @return string
     */
    public static function generate_crypt_key(int $len = 128) : string
    {
        return self::generate_random_string($len);
    }

    /**
     * Generate crypt internal keys used in crypting functionality
     *
     * @return array
     */
    public static function generate_crypt_internal_keys() : array
    {
        $return_arr = [];
        for ($i = 0; $i < 34; $i++) {
            $return_arr[] = md5(microtime().self::generate_random_string(128));
        }

        return $return_arr;
    }

    public static function generate_random_string(int $len = 128, ?array $params = null) : string
    {
        if (empty($params)) {
            $params = [];
        }

        if (empty($params['percents']) || !is_array($params['percents'])) {
            $params['percents'] = ['spacial_chars' => 10, 'digits_chars' => 20, 'normal_chars' => 70, ];
        }

        if (!isset($params['percents']['spacial_chars'])) {
            $params['percents']['spacial_chars'] = 15;
        }
        if (!isset($params['percents']['digits_chars'])) {
            $params['percents']['digits_chars'] = 15;
        }
        if (!isset($params['percents']['normal_chars'])) {
            $params['percents']['normal_chars'] = 70;
        }

        $spacial_chars_perc = (int)$params['percents']['spacial_chars'];
        $digits_chars_perc = (int)$params['percents']['digits_chars'];
        $normal_chars_perc = (int)$params['percents']['normal_chars'];

        if ($spacial_chars_perc + $digits_chars_perc + $normal_chars_perc > 100) {
            $spacial_chars_perc = 15;
            $digits_chars_perc = 15;
            $normal_chars_perc = 70;
        }

        $special_chars_dict = '!@#%^&*()_-+=}{:;?/.,<>\\|';
        $digits_dict = '1234567890';
        $letters_dict = 'abcdbefghijklmnopqrstuvwxyz';
        $special_chars_dict_len = strlen($special_chars_dict);
        $digits_dict_len = strlen($digits_dict);
        $letters_dict_len = strlen($letters_dict);

        $uppercase_chars = 0;
        $special_chars = 0;
        $digit_chars = 0;

        $ret = '';
        for ($ret_len = 0; $ret_len < $len; $ret_len++) {
            $uppercase_char = false;
            // 10% spacial char, 20% digit, 70% letter
            $dict_index = mt_rand(0, 100);
            if ($dict_index <= $spacial_chars_perc) {
                $current_dict = $special_chars_dict;
                $dict_len = $special_chars_dict_len;
                $special_chars++;
            } elseif ($dict_index <= $spacial_chars_perc + $digits_chars_perc) {
                $current_dict = $digits_dict;
                $dict_len = $digits_dict_len;
                $digit_chars++;
            } else {
                $current_dict = $letters_dict;
                $dict_len = $letters_dict_len;
                if (mt_rand(0, 100) > 50) {
                    $uppercase_char = true;
                    $uppercase_chars++;
                }
            }

            $ch = substr($current_dict, mt_rand(0, $dict_len - 1), 1);
            if ($uppercase_char) {
                $ch = strtoupper($ch);
            }

            $ret .= $ch;
        }

        // Add a special char if none was added already
        if (!$special_chars) {
            $ch = substr($special_chars_dict, mt_rand(0, $special_chars_dict_len - 1), 1);
            // 50% in front or in back of the result
            if (mt_rand(0, 100) > 50) {
                $ret .= $ch;
            } else {
                $ret = $ch.$ret;
            }
        }

        // Add a digit char if none was added already
        while ($digit_chars < 2) {
            $ch = substr($digits_dict, mt_rand(0, $digits_dict_len - 1), 1);
            // 50% in front or in back of the result
            if (mt_rand(0, 100) > 50) {
                $ret .= $ch;
            } else {
                $ret = $ch.$ret;
            }

            $digit_chars++;
        }

        return $ret;
    }

    private static function _create_encdec_instance(array $params = []) : ?PHS_Encdec
    {
        $params['use_base64'] = !isset($params['use_base64']) || !empty($params['use_base64']);

        if (empty($params['crypting_key']) || !is_string($params['crypting_key'])) {
            $params['crypting_key'] = self::crypting_key();
        }
        if (empty($params['internal_keys']) || !is_array($params['internal_keys'])) {
            $params['internal_keys'] = self::get_internal_keys();
        }

        $enc_dec = new PHS_Encdec($params['crypting_key'], !empty($params['use_base64']), $params['internal_keys']);

        if ($enc_dec->has_error()) {
            return null;
        }

        return $enc_dec;
    }
}
