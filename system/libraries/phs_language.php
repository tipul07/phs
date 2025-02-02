<?php
namespace phs\libraries;

if (!defined('PHS_VERSION')
 && (!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))) {
    exit;
}

use phs\PHS;

class PHS_Language extends PHS_Error
{
    // Key in session to hold current language
    public const LANG_SESSION_KEY = 'phs_lang';

    // Parameter received in _GET or _POST to set the language for current page
    public const LANG_URL_PARAMETER = '__phsl';

    public const LANG_LINE_DELIMITER = "\n", LANG_COLUMNS_DELIMITER = ',', LANG_COLUMNS_ENCLOSURE = '"', LANG_ENCLOSURE_ESCAPE = '"';

    /** @var null|PHS_Language_Container */
    private static ?PHS_Language_Container $lang_callable_obj = null;

    public function _pte(string $index, string $ch = '"') : string
    {
        return self::_e($this->_pt($index), $ch);
    }

    public function _pt(string $index) : string
    {
        /** @var PHS_Plugin|PHS_Library $this */
        if ((!($this instanceof PHS_Instantiable) && !($this instanceof PHS_Library))
            || !($plugin_obj = $this->get_plugin_instance())) {
            return self::_t(func_get_args());
        }

        $plugin_obj->include_plugin_language_files();

        if (!($result = @forward_static_call_array([__CLASS__, '_t'], func_get_args()))) {
            $result = '';
        }

        return $result;
    }

    public static function language_container() : PHS_Language_Container
    {
        if (empty(self::$lang_callable_obj)) {
            self::$lang_callable_obj = new PHS_Language_Container();
        }

        return self::$lang_callable_obj;
    }

    public static function get_utf8_conversion_enabled() : bool
    {
        return self::language_container()->get_utf8_conversion_enabled();
    }

    public static function set_utf8_conversion(bool $enabled) : bool
    {
        return self::language_container()->set_utf8_conversion($enabled);
    }

    public static function get_multi_language_enabled() : bool
    {
        return self::language_container()->get_multi_language_enabled();
    }

    public static function set_multi_language(bool $enabled) : bool
    {
        return self::language_container()->set_multi_language($enabled);
    }

    public static function get_defined_languages() : array
    {
        return self::language_container()->get_defined_languages();
    }

    public static function get_defined_language(string $lang) : ?array
    {
        return self::language_container()->get_defined_language($lang);
    }

    public static function get_current_language_key(string $key) : mixed
    {
        return self::language_container()->get_current_language_key($key);
    }

    public static function get_default_language() : string
    {
        return self::language_container()->get_default_language();
    }

    public static function get_current_language() : string
    {
        return self::language_container()->get_current_language();
    }

    public static function set_current_language(string $lang) : ?string
    {
        return self::language_container()->set_current_language($lang);
    }

    public static function set_default_language(string $lang) : ?string
    {
        return self::language_container()->set_default_language($lang);
    }

    public static function add_language_files(string $lang, array $files_arr) : bool
    {
        return self::language_container()->add_language_files($lang, $files_arr);
    }

    public static function scan_for_language_files(string $dir) : bool
    {
        return self::language_container()->scan_for_language_files($dir);
    }

    public static function force_reload_language_files(string $lang) : bool
    {
        return self::language_container()->force_reload_language_files($lang);
    }

    public static function valid_language($lang) : string
    {
        return self::language_container()->valid_language($lang);
    }

    public static function define_language(string $lang, array $lang_params) : bool
    {
        if (!self::language_container()->define_language($lang, $lang_params)) {
            self::st_copy_error(self::language_container());

            return false;
        }

        return true;
    }

    public static function get_language_file_lines(string $file, string $lang) : ?array
    {
        self::st_reset_error();

        if (!($language_container = self::language_container())) {
            return null;
        }

        if (null === ($return_arr = $language_container->get_language_file_lines($file, $lang))) {
            if ($language_container->has_error()) {
                self::st_copy_error($language_container);
            }

            return null;
        }

        return $return_arr;
    }

    public static function load_language_file(string $file, string $lang, bool $force = false) : bool
    {
        self::st_reset_error();

        $language_container = self::language_container();

        if (!($return_arr = $language_container->load_language_file($file, $lang, $force))) {
            if ($language_container->has_error()) {
                self::st_copy_error($language_container);
            }

            return false;
        }

        return true;
    }

    public static function get_language_file_header_arr() : array
    {
        return self::language_container()->get_language_file_header_arr();
    }

    public static function get_language_file_header_str() : string
    {
        return self::language_container()->get_language_file_header_str();
    }

    public static function lang_files_csv_settings(?array $settings = null) : array
    {
        $lang_container = self::language_container();

        return $lang_container::lang_files_csv_settings($settings);
    }

    public static function default_lang_files_csv_settings() : array
    {
        $lang_container = self::language_container();

        return $lang_container::default_lang_files_csv_settings();
    }

    public static function st_pt(string $index) : string
    {
        if (!($called_class = @static::class)
         || !($clean_class_name = ltrim($called_class, '\\'))
         || stripos($clean_class_name, 'phs\\plugins\\') !== 0
         || !($parts_arr = explode('\\', $clean_class_name, 4))
         || empty($parts_arr[2])
         || !($plugin_obj = PHS::load_plugin($parts_arr[2]))) {
            return self::_t(func_get_args());
        }

        $plugin_obj->include_plugin_language_files();

        return @forward_static_call_array([__CLASS__, '_t'], @func_get_args()) ?: '';
    }

    /**
     * Translate a specific text in currently selected language. This method receives a variable number of parameters in same way as sprintf works.
     * @param string|array $index Language index to be translated or array of arguments to be processed
     *
     * @return string Translated string
     */
    public static function _t($index) : string
    {
        if (is_array($index)) {
            $arg_list = $index;
            $numargs = count($arg_list);
            if (isset($arg_list[0])) {
                $index = $arg_list[0];
            }
        } else {
            $numargs = func_num_args();
            $arg_list = func_get_args();
        }

        if ($numargs > 1) {
            @array_shift($arg_list);
        } else {
            $arg_list = [];
        }

        return self::language_container()->_t($index, $arg_list);
    }

    /**
     * Translate a specific text in currently selected language then escape the resulting string.
     * This method receives a variable number of parameters in same way as sprintf works.
     *
     * @param string $index Language index to be translated
     * @param string $ch Escaping character
     *
     * @return string Translated and escaped string
     */
    public static function _te(string $index, string $ch = '"') : string
    {
        return self::_e(self::_t($index), $ch);
    }

    /**
     * Escapes ' or " in string parameter (useful when displaying strings in html/javascript strings)
     *
     * @param string $str String to be escaped
     * @param string $ch Escaping character (' or ")
     *
     * @return string
     */
    public static function _e(string $str, string $ch = '"') : string
    {
        return str_replace($ch, ($ch === '\'' ? '\\\'' : '\\"'), $str);
    }

    /**
     * Translate a text into a specific language.
     * This method receives a variable number of parameters in same way as sprintf works.
     *
     * @param string $index Language index to be translated
     * @param string $lang ISO 2 chars (lowercase) language code
     *
     * @return string Translated text
     */
    public static function _tl(string $index, string $lang) : string
    {
        $numargs = func_num_args();
        $arg_list = func_get_args();

        if ($numargs > 2) {
            @array_shift($arg_list);
            @array_shift($arg_list);
        } else {
            $arg_list = [];
        }

        return self::language_container()->_tl($index, $lang, $arg_list);
    }
}
