<?php
namespace phs\libraries;

if (!defined('PHS_VERSION')
 && (!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW'))) {
    exit;
}

use phs\PHS;

class PHS_Language_Container extends PHS_Error
{
    // We take error codes from 100000+ to let 1-99999 for custom defined constant errors
    public const ERR_LANGUAGE_DEFINITION = 100000, ERR_LANGUAGE_LOAD = 100001, ERR_NOT_STRING = 100002;

    // ! Tells if language files should be converted to UTF8 file if UTF8 version doesn't exist
    // ! This helps systems with no shell access
    private static bool $CONVERT_LANG_FILES_TO_UTF8 = true;

    // ! Tells if multi-language should be enabled
    private static bool $MULTI_LANGUAGE_ENABLED = true;

    // ! Fallback language in case we try to translate a text which is not defined in current language
    private static string $DEFAULT_LANGUAGE = '';

    // ! Current language
    private static string $CURRENT_LANGUAGE = '';

    // ! Contains defined language which can be used by the system. These are not necessary loaded in memory in order to optimize memory
    // ! eg. $DEFINED_LANGUAGES['en'] = [
    // 'title' => 'English (friendly name of language)',
    // 'dir' => '{server path to language directory}',
    // 'www' => '{URL to language directory}',
    // 'files' => [ 'path_to_csv_file1', 'path_to_csv_file2' ],
    // 'browser_lang' => 'en-GB', // what should be sent to browser as language
    // 'browser_charset' => 'utf8', // What charset to use in browser
    // 'flag_file' => 'http://www.example.com/full/url/to/flag/file/en.png', // used when displaying language option, if not provided will not put image
    // system will add automatically phs_language_{language index} class to language container so you can customize display with css
    // ];
    private static array $DEFINED_LANGUAGES = [];

    private static array $LANGUAGE_INDEXES = [];

    private static array $_RELOAD_LANGUAGES = [];

    private static array $_LOADED_FILES = [];

    private static array $csv_settings = [];

    public function __construct()
    {
        self::$csv_settings = self::default_lang_files_csv_settings();

        parent::__construct();
    }

    public function get_utf8_conversion_enabled() : bool
    {
        return self::st_get_utf8_conversion();
    }

    public function set_utf8_conversion($enabled) : bool
    {
        return self::st_set_utf8_conversion($enabled);
    }

    public function get_multi_language_enabled() : bool
    {
        return self::st_get_multi_language_enabled();
    }

    public function set_multi_language(bool $enabled) : bool
    {
        return self::st_set_multi_language($enabled);
    }

    /**
     * @param $key
     *
     * @return null|mixed
     */
    public function get_current_language_key($key)
    {
        return self::st_get_current_language_key($key);
    }

    public function get_defined_languages() : array
    {
        return self::st_get_defined_languages();
    }

    /**
     * @return string
     */
    public function get_default_language() : string
    {
        return self::st_get_default_language();
    }

    /**
     * @return string
     */
    public function get_current_language() : string
    {
        return self::st_get_current_language();
    }

    public function set_current_language(string $lang) : ?string
    {
        return self::st_set_current_language($lang);
    }

    public function set_default_language($lang) : ?string
    {
        return self::st_set_default_language($lang);
    }

    public function valid_language(?string $lang) : string
    {
        return self::st_valid_language($lang);
    }

    /**
     * Reset all indexes loaded for provided language if $lang !== false or all loaded indexes if $lang === false
     *
     * @param string|bool $lang
     *
     * @return $this
     */
    public function reset_language_indexes($lang = false) : self
    {
        if ($lang === false) {
            self::$LANGUAGE_INDEXES = [];
            self::$_LOADED_FILES = [];
        } else {
            $lang = self::prepare_lang_index($lang);
            if (isset(self::$LANGUAGE_INDEXES[$lang])) {
                self::$LANGUAGE_INDEXES[$lang] = [];
            }
            if (isset(self::$_LOADED_FILES[$lang])) {
                self::$_LOADED_FILES[$lang] = [];
            }
        }

        return $this;
    }

    /**
     * Define a language used by platform
     *
     * @param string $lang ISO 2 chars (lowercase) language code
     * @param array $lang_params Language details
     *
     * @return bool True if adding language was successful, false otherwise
     */
    public function define_language(string $lang, array $lang_params) : bool
    {
        $this->reset_error();

        $lang = self::prepare_lang_index($lang);
        if (empty($lang)
         || empty($lang_params)
         || empty($lang_params['title'])) {
            $this->set_error(self::ERR_LANGUAGE_DEFINITION, 'Please provide valid parameters for language definition.');

            return false;
        }

        if (empty(self::$DEFINED_LANGUAGES[$lang])) {
            self::$DEFINED_LANGUAGES[$lang] = [];
        }

        self::$DEFINED_LANGUAGES[$lang]['title'] = trim($lang_params['title']);

        if (empty(self::$DEFINED_LANGUAGES[$lang]['files'])) {
            self::$DEFINED_LANGUAGES[$lang]['files'] = [];
        }

        if (!empty($lang_params['files'])
         && !$this->add_language_files($lang, $lang_params['files'])) {
            return false;
        }

        $default_language_structure = self::get_default_language_structure();
        foreach ($default_language_structure as $key => $def_value) {
            if (array_key_exists($key, $lang_params)) {
                self::$DEFINED_LANGUAGES[$lang][$key] = $lang_params[$key];
            } elseif (!array_key_exists($key, self::$DEFINED_LANGUAGES[$lang])) {
                self::$DEFINED_LANGUAGES[$lang][$key] = $def_value;
            }
        }

        return true;
    }

    /**
     * @param string $dir Directory to scan for language files ({en|gb|de|ro}.csv)
     *
     * @return bool True on success, false if directory is not readable
     */
    public function scan_for_language_files(string $dir) : bool
    {
        if (!self::st_get_multi_language_enabled()) {
            return true;
        }

        $dir = rtrim(PHS::from_relative_path(PHS::relative_path($dir)), '/\\');
        if (empty($dir)
            || !@is_dir($dir) || !@is_readable($dir)) {
            return false;
        }

        if (($languages_arr = $this->get_defined_languages())) {
            foreach ($languages_arr as $lang_key => $lang_arr) {
                $language_file = $dir.'/'.$lang_key.'.csv';
                if (@file_exists($language_file)) {
                    $this->add_language_files($lang_key, [$language_file]);
                }
            }
        }

        return true;
    }

    /**
     * @param string $lang
     *
     * @return bool
     */
    public function force_reload_language_files(string $lang) : bool
    {
        $this->reset_error();

        $lang = self::prepare_lang_index($lang);
        if (empty($lang)
            || !($lang = self::st_valid_language($lang))) {
            $this->set_error(self::ERR_LANGUAGE_LOAD, 'Language not defined.');

            return false;
        }

        self::$_RELOAD_LANGUAGES[$lang] = true;

        return true;
    }

    /**
     * @param string $lang
     *
     * @return bool
     */
    public function should_reload_language_files(string $lang) : bool
    {
        $this->reset_error();

        $lang = self::prepare_lang_index($lang);
        if (empty($lang)
         || !($lang = self::st_valid_language($lang))) {
            $this->set_error(self::ERR_LANGUAGE_LOAD, 'Language not defined.');

            return false;
        }

        return !empty(self::$_RELOAD_LANGUAGES[$lang]);
    }

    /**
     * @param string $lang
     * @param array $files_arr
     *
     * @return bool
     */
    public function add_language_files(string $lang, array $files_arr) : bool
    {
        $this->reset_error();

        if (!self::st_get_multi_language_enabled()) {
            return true;
        }

        $lang = self::prepare_lang_index($lang);
        if (empty($lang)
         || !($lang = self::st_valid_language($lang))) {
            $this->set_error(self::ERR_LANGUAGE_LOAD, 'Language not defined.');

            return false;
        }

        foreach ($files_arr as $lang_file) {
            if (empty($lang_file)
             || !@file_exists($lang_file) || !@is_readable($lang_file)) {
                $this->set_error(self::ERR_LANGUAGE_DEFINITION, 'Language file ['.@basename($lang_file).'] for language ['.$lang.'] not found or not readable.');

                return false;
            }

            if (!in_array($lang_file, self::$DEFINED_LANGUAGES[$lang]['files'], true)) {
                self::$DEFINED_LANGUAGES[$lang]['files'][] = $lang_file;
                $this->force_reload_language_files($lang);
            }
        }

        return true;
    }

    /**
     * Loads provided CSV files in 'files' index of language definition array for language $lang
     *
     * @param string $lang ISO 2 chars (lowercase) language code
     * @param bool $force Force loading laguange files
     *
     * @return bool True if loading was with success, false otherwise
     */
    public function load_language(?string $lang, bool $force = false) : bool
    {
        $this->reset_error();

        if (!self::st_get_multi_language_enabled()) {
            return true;
        }

        $this->_loading_language(true);

        if (!($lang = self::st_valid_language($lang))
            || !self::get_defined_language($lang)) {
            $this->_loading_language(false);

            $this->set_error(self::ERR_LANGUAGE_LOAD,
                'Language ['.(!empty($lang) ? $lang : 'N/A').'] not defined or has no files to be loaded.');

            return false;
        }

        // PHS::get_theme_language_paths() might set static errors.
        // We don't want to check errors here, and we will preserve static error
        $old_static_error = self::st_stack_error();
        if (($default_theme = PHS::get_default_theme())
         && ($theme_language_paths = PHS::get_theme_language_paths($default_theme))
         && !empty($theme_language_paths['path'])
         && !$this->scan_for_language_files($theme_language_paths['path'])) {
            $this->_loading_language(false);

            return false;
        }

        // Add cascading themes to language files...
        if (($themes_arr = PHS::get_cascading_themes())) {
            foreach ($themes_arr as $c_theme) {
                if (!empty($c_theme)
                 && ($theme_language_paths = PHS::get_theme_language_paths($c_theme))
                 && !empty($theme_language_paths['path'])) {
                    $this->scan_for_language_files($theme_language_paths['path']);
                }
            }
        }

        if (($current_theme = PHS::get_theme())
         && $default_theme !== $current_theme
         && ($theme_language_paths = PHS::get_theme_language_paths($current_theme))
         && !empty($theme_language_paths['path'])
         && !$this->scan_for_language_files($theme_language_paths['path'])) {
            $this->_loading_language(false);

            return false;
        }

        self::st_restore_errors($old_static_error);

        // After theme directories were scanned start loading files...
        if (!($lang_details = self::get_defined_language($lang))) {
            $this->_loading_language(false);

            $this->set_error(self::ERR_LANGUAGE_LOAD, 'Language ['.$lang.'] not defined or has no files to be loaded.');

            return false;
        }

        // Don't throw error if language is defined, but has no files to be loaded...
        if (!empty($lang_details['files']) && is_array($lang_details['files'])) {
            foreach ($lang_details['files'] as $file) {
                if (!$this->load_language_file($file, $lang, $force)) {
                    $this->set_error(self::ERR_LANGUAGE_LOAD, 'Error loading file ['.$lang.':'.$file.']');
                    $this->_loading_language(false);

                    return false;
                }
            }
        }

        if (!empty(self::$_RELOAD_LANGUAGES[$lang])) {
            unset(self::$_RELOAD_LANGUAGES[$lang]);
        }

        $this->_loading_language(false);

        return true;
    }

    /**
     * Loads a specific CSV file for language $lang. This file is provided in 'files' index of language definition array for provided language
     *
     * @param string $file
     * @param bool $force Force loading laguange files
     * @param string $lang
     *
     * @return bool
     */
    public function load_language_file(string $file, string $lang, bool $force = false) : bool
    {
        if ((empty($force)
             && !empty(self::$_LOADED_FILES[$lang][$file]))
         || !self::st_get_multi_language_enabled()) {
            return true;
        }

        if (null === ($language_arr = $this->get_language_file_lines($file, $lang))
         || !is_array($language_arr)) {
            return false;
        }

        foreach ($language_arr as $index => $index_lang) {
            self::$LANGUAGE_INDEXES[$lang][$index] = $index_lang;
        }

        if (empty(self::$_LOADED_FILES[$lang])) {
            self::$_LOADED_FILES[$lang] = [];
        }

        self::$_LOADED_FILES[$lang][$file] = true;

        return true;
    }

    public function get_language_file_header_arr() : array
    {
        return [
            '# !!!!! DON\'T EDIT THIS COLUMN AT ALL !!!!!'                                                        => 'TEXT IN THIS COLUMN SHOULD BE TRANSLATED IN DESIRED LANGUAGE',
            '# Please use Excel (or similar) as editor for this file to be sure formatting will not be broken!!!' => '',
            '# Lines starting with # will be ignored.'                                                            => 'Column separator is comma!! (configure Excel or similar to use comma)',
        ];
    }

    public function get_language_file_header_str() : string
    {
        if (!($lines_arr = $this->get_language_file_header_arr())) {
            return '';
        }

        if (!($csv_settings = self::lang_files_csv_settings())) {
            $csv_settings = self::default_lang_files_csv_settings();
        }

        $return_str = '';
        foreach ($lines_arr as $lang_key => $lang_val) {
            $return_str .= PHS_Utils::csv_line([$lang_key, $lang_val],
                $csv_settings['line_delimiter'],
                $csv_settings['columns_delimiter'],
                $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape']);
        }

        return $return_str;
    }

    /**
     * Parse specified language file and get language array
     *
     * @param string $file
     * @param string $lang
     *
     * @return null|array Returns parsed lines from language CSV file or false on error
     */
    public function get_language_file_lines(string $file, string $lang) : ?array
    {
        $this->reset_error();

        if (!self::st_get_multi_language_enabled()) {
            return [];
        }

        if (!($lang = self::st_valid_language($lang))) {
            $this->set_error(self::ERR_LANGUAGE_LOAD, 'Language not defined.');

            return null;
        }

        if (!($utf8_file = self::convert_to_utf8($file))) {
            $this->set_error(self::ERR_LANGUAGE_LOAD,
                'Couldn\'t convert language file ['.@basename($file).'], language ['.$lang.'] to UTF-8 encoding.');

            return null;
        }

        if (!@file_exists($utf8_file) || !is_readable($utf8_file)
         || !($fil = @fopen($utf8_file, 'rb'))) {
            $this->set_error(self::ERR_LANGUAGE_LOAD,
                'File ['.@basename($utf8_file).'] doesn\'t exist or is not readable for language ['.$lang.'].');

            return null;
        }

        if (!($csv_settings = self::lang_files_csv_settings())) {
            $csv_settings = self::default_lang_files_csv_settings();
        }

        if (function_exists('mb_internal_encoding')) {
            @mb_internal_encoding('UTF-8');
        }

        $mb_substr_exists = false;
        if (function_exists('mb_substr')) {
            $mb_substr_exists = true;
        }

        $return_arr = [];
        while (($buf = @fgets($fil))) {
            if (($mb_substr_exists && @mb_substr(ltrim($buf), 0, 1) === '#')
             || (!$mb_substr_exists && @substr(ltrim($buf), 0, 1) === '#')) {
                continue;
            }

            $buf = rtrim($buf, "\r\n");

            if (!($csv_line = @str_getcsv($buf, $csv_settings['columns_delimiter'], $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape']))
                || !is_array($csv_line)
                || count($csv_line) !== 2) {
                continue;
            }

            $index = $csv_line[0];
            $index_lang = $csv_line[1] ?? '';

            $return_arr[$index] = $index_lang;
        }

        @fclose($fil);

        return $return_arr;
    }

    /**
     * Translate text $index. If $index contains %XX format vsprintf, arguments will be passed in $args parameter.
     *
     * @param string $index Language index to be translated
     * @param array $args Array of arguments to be used to populate $index
     *
     * @return string Translated string
     * @see vsprintf
     */
    public function _t(string $index, array $args = []) : string
    {
        if (empty($args) || !is_array($args)) {
            $args = [];
        }

        if (!isset($args[0])
            || !$this->valid_language($args[0])) {
            $t_lang = $this->get_current_language();
        } else {
            $t_lang = $args[0];
            @array_shift($args);
        }

        return $this->_tl($index, $t_lang, $args);
    }

    /**
     * Translate text $index for language $lang. If $index contains %XX format vsprintf, arguments will be passed in $args parameter.
     *
     * @param string $index
     * @param null|string $lang
     * @param array $args Array of arguments to be used to populate $index
     *
     * @return string
     * @see vsprintf
     */
    public function _tl(string $index, ?string $lang, array $args = []) : string
    {
        $lang = self::st_valid_language($lang);

        if (empty($args) || !is_array($args)) {
            $args = [];
        }

        if (!empty($lang)
            && self::st_get_multi_language_enabled()
            && !$this->_loading_language()
            && (!self::language_loaded($lang)
                || $this->should_reload_language_files($lang))
            && !$this->load_language($lang)) {
            return $this->get_simple_error_message('Error loading language ['.$lang.']');
        }

        if (!empty($lang)
            && isset(self::$LANGUAGE_INDEXES[$lang][$index])
            && self::st_get_multi_language_enabled()) {
            $working_index = self::$LANGUAGE_INDEXES[$lang][$index];
        } else {
            $working_index = $index;
        }

        if (!empty($args)
            && isset($args[0])
            && $args[0] === false) {
            @array_shift($args);
        }

        if (!empty($args)) {
            // we should replace some %s...
            try {
                if (($result = @vsprintf($working_index, $args))) {
                    return $result;
                }
            } catch (\Exception $e) {
            }

            return $working_index.' ['.count($args).' args]';
        }

        return $working_index;
    }

    /**
     * @param null|bool $loading
     *
     * @return bool
     */
    private function _loading_language(?bool $loading = null) : bool
    {
        static $is_loading = false;

        if ($loading === null) {
            return $is_loading;
        }

        $is_loading = (!empty($loading));

        return $is_loading;
    }

    public static function st_get_utf8_conversion() : bool
    {
        return self::$CONVERT_LANG_FILES_TO_UTF8;
    }

    public static function st_set_utf8_conversion($enabled) : bool
    {
        self::$CONVERT_LANG_FILES_TO_UTF8 = (!empty($enabled));

        return self::$CONVERT_LANG_FILES_TO_UTF8;
    }

    public static function st_get_multi_language_enabled() : bool
    {
        return self::$MULTI_LANGUAGE_ENABLED;
    }

    public static function st_set_multi_language(bool $enabled) : bool
    {
        self::$MULTI_LANGUAGE_ENABLED = (!empty($enabled));

        return self::$MULTI_LANGUAGE_ENABLED;
    }

    public static function default_lang_files_csv_settings() : array
    {
        return [
            'line_delimiter'    => PHS_Language::LANG_LINE_DELIMITER,
            'columns_delimiter' => PHS_Language::LANG_COLUMNS_DELIMITER,
            'columns_enclosure' => PHS_Language::LANG_COLUMNS_ENCLOSURE,
            'enclosure_escape'  => PHS_Language::LANG_ENCLOSURE_ESCAPE,
        ];
    }

    public static function lang_files_csv_settings(?array $settings = null) : array
    {
        if (empty(self::$csv_settings)) {
            self::$csv_settings = self::default_lang_files_csv_settings();
        }

        if ($settings === null) {
            return self::$csv_settings;
        }

        foreach (self::$csv_settings as $key => $cur_val) {
            if (array_key_exists($key, $settings)) {
                self::$csv_settings[$key] = $settings[$key];
            }
        }

        return self::$csv_settings;
    }

    public static function st_get_default_language() : string
    {
        return self::$DEFAULT_LANGUAGE;
    }

    /**
     * @return string
     */
    public static function st_get_current_language() : string
    {
        return self::$CURRENT_LANGUAGE;
    }

    /**
     * @param string $lang
     *
     * @return null|string
     */
    public static function st_set_current_language(string $lang) : ?string
    {
        if (!($lang = self::st_valid_language($lang))) {
            return null;
        }

        self::$CURRENT_LANGUAGE = $lang;

        return self::$CURRENT_LANGUAGE;
    }

    /**
     * @param string $lang
     *
     * @return null|string
     */
    public static function st_set_default_language(string $lang) : ?string
    {
        if (!($lang = self::st_valid_language($lang))) {
            return null;
        }

        self::$DEFAULT_LANGUAGE = $lang;

        return self::$DEFAULT_LANGUAGE;
    }

    /**
     * @param string $key Property in current language definition array
     *
     * @return null|mixed
     */
    public static function st_get_current_language_key(string $key)
    {
        $clang = self::st_get_current_language();
        if (empty($clang)
         || empty(self::$DEFINED_LANGUAGES[$clang]) || !is_array(self::$DEFINED_LANGUAGES[$clang])
         || !array_key_exists($key, self::$DEFINED_LANGUAGES[$clang])) {
            return null;
        }

        return self::$DEFINED_LANGUAGES[$clang][$key];
    }

    /**
     * Returns defined languages array (defined using self::define_language())
     *
     * @return array
     */
    public static function st_get_defined_languages() : array
    {
        return self::$DEFINED_LANGUAGES;
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public static function prepare_lang_index(?string $lang) : string
    {
        return $lang === null ? '' : strtolower(trim($lang));
    }

    /**
     * Tells if language $lang is a valid language defined in the system
     *
     * @param null|string $lang
     *
     * @return string
     */
    public static function st_valid_language(?string $lang) : string
    {
        $lang = self::prepare_lang_index($lang);

        return isset(self::$DEFINED_LANGUAGES[$lang]) ? $lang : '';
    }

    /**
     * Tells if language $lang is loaded (files were parsed and added to indexes array)
     *
     * @param null|string $lang
     *
     * @return bool
     */
    public static function language_loaded(?string $lang) : bool
    {
        if (!self::st_get_multi_language_enabled()) {
            return true;
        }

        $lang = self::prepare_lang_index($lang);

        return isset(self::$LANGUAGE_INDEXES[$lang]);
    }

    public static function get_default_language_structure() : array
    {
        return [
            'title'           => '',
            'dir'             => '',
            'www'             => '',
            'files'           => [],
            'browser_lang'    => '',
            'browser_charset' => '',
            'flag_file'       => '',
        ];
    }

    /**
     * Returns language details as it was defined using self::define_language()
     *
     * @param null|string $lang
     *
     * @return null|array
     */
    public static function get_defined_language(?string $lang) : ?array
    {
        if (!($lang = self::st_valid_language($lang))) {
            return null;
        }

        return self::$DEFINED_LANGUAGES[$lang];
    }

    /**
     * Given an absolute file path, this method will return file name which should contain UTF-8 encoded content of original file
     *
     * @param string $file ablsolute path of file which should be converted to UTF-8 encoding
     *
     * @return string Resulting file name which will hold UTF-8 encoded content of original file
     */
    public static function get_utf8_file_name(string $file) : string
    {
        $path_info = @pathinfo($file);

        return $path_info['dirname'].'/'.$path_info['filename'].'-utf8.'.$path_info['extension'];
    }

    /**
     * Converts a given file to a UTF-8 encoded content.
     *
     * @param string $file ablsolute path of file which should be converted to UTF-8 encoding
     * @param null|array $params Method parameters allows to overwrite UTF-8 encoded file name
     *
     * @return null|string Returns absolute path of UTF-8 encoded file
     */
    public static function convert_to_utf8(string $file, array $params = []) : ?string
    {
        if (empty($file) || !@file_exists($file)) {
            return null;
        }

        if (empty($params['utf8_file'])) {
            $params['utf8_file'] = self::get_utf8_file_name($file);
        }

        // On some systems file and iconv binaries are not available and will display the results of which command
        // So, as long as utf8 files exist, just let it be...
        if (!self::st_get_utf8_conversion()
         && @file_exists($params['utf8_file'])) {
            return $params['utf8_file'];
        }

        ob_start();
        if (!($file_bin = @system('which file'))
         || !($iconv_bin = @system('which iconv'))) {
            ob_end_clean();

            // we don't have required files to convert csv to utf8... check if we have a utf8 language file...
            if (@file_exists($params['utf8_file'])) {
                return $params['utf8_file'];
            }

            return null;
        }

        if (!($file_mime = @system($file_bin.' --mime-encoding '.escapeshellarg($file)))) {
            return null;
        }

        $file_mime = str_replace($file.': ', '', $file_mime);

        if (!in_array(strtolower($file_mime), ['utf8', 'utf-8'], true)) {
            if (false === @system($iconv_bin.' -f '.escapeshellarg($file_mime)
                                   .' -t utf-8 '.escapeshellarg($file).' > '.escapeshellarg($params['utf8_file']))
             || !@file_exists($params['utf8_file'])) {
                ob_end_clean();

                return null;
            }
        } else {
            @copy($file, $params['utf8_file']);
        }
        ob_end_clean();

        if (!@file_exists($params['utf8_file'])) {
            return null;
        }

        return $params['utf8_file'];
    }
}
