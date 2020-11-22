<?php

namespace phs\libraries;

if( (!defined( 'PHS_SETUP_FLOW' ) or !constant( 'PHS_SETUP_FLOW' ))
and !defined( 'PHS_VERSION' ) )
    exit;

use \phs\PHS;

class PHS_Language_Container extends PHS_Error
{
    // We take error codes from 100000+ to let 1-99999 for custom defined constant errors
    const ERR_LANGUAGE_DEFINITION = 100000, ERR_LANGUAGE_LOAD = 100001, ERR_NOT_STRING = 100002;

    //! Tells if language files should be converted to UTF8 file if UTF8 version doesn't exist
    //! This helps systems with no shell access
    private static $CONVERT_LANG_FILES_TO_UTF8 = true;
    //! Tells if multi language should be enabled
    private static $MULTI_LANGUAGE_ENABLED = true;
    //! Fallback language in case we try to translate a text which is not defined in current language
    private static $DEFAULT_LANGUAGE = '';
    //! Current language
    private static $CURRENT_LANGUAGE = '';
    //! Contains defined language which can be used by the system. These are not necessary loaded in memory in order to optimize memory
    //! eg. $DEFINED_LANGUAGES['en'] = array(
    // 'title' => 'English (friendly name of language)',
    // 'dir' => '{server path to language directory}',
    // 'www' => '{URL to language directory}',
    // 'files' => array( 'path_to_csv_file1', 'path_to_csv_file2' ),
    // 'browser_lang' => 'en-GB', // what should be sent to browser as language
    // 'browser_charset' => 'utf8', // What charset to use in browser
    // 'flag_file' => 'http://www.example.com/full/url/to/flag/file/en.png', // used when displaying language option, if not provided will not put image
    // system will add automatically phs_language_{language index} class to language container so you can customize display with css
    // );
    private static $DEFINED_LANGUAGES = array();

    private static $LANGUAGE_INDEXES = array();

    private static $_RELOAD_LANGUAGES = array();

    private static $_LOADED_FILES = array();

    private static $csv_settings = false;

    public function __construct()
    {
        self::$csv_settings = self::default_lang_files_csv_settings();

        parent::__construct();
    }

    public static function st_get_utf8_conversion()
    {
        return self::$CONVERT_LANG_FILES_TO_UTF8;
    }

    public static function st_set_utf8_conversion( $enabled )
    {
        self::$CONVERT_LANG_FILES_TO_UTF8 = (!empty( $enabled ));
        return self::$CONVERT_LANG_FILES_TO_UTF8;
    }

    public function get_utf8_conversion_enabled()
    {
        return self::st_get_utf8_conversion();
    }

    public function set_utf8_conversion( $enabled )
    {
        return self::st_set_utf8_conversion( $enabled );
    }

    public static function st_get_multi_language_enabled()
    {
        return self::$MULTI_LANGUAGE_ENABLED;
    }

    public static function st_set_multi_language( $enabled )
    {
        self::$MULTI_LANGUAGE_ENABLED = (!empty( $enabled ));
        return self::$MULTI_LANGUAGE_ENABLED;
    }

    public function get_multi_language_enabled()
    {
        return self::st_get_multi_language_enabled();
    }

    public function set_multi_language( $enabled )
    {
        return self::st_set_multi_language( $enabled );
    }

    public static function default_lang_files_csv_settings()
    {
        return array(
            'line_delimiter' => "\n",
            'columns_delimiter' => ',',
            'columns_enclosure' => '"',
            'enclosure_escape' => '"',
        );
    }

    /**
     * @param bool|array $settings
     *
     * @return array|bool
     */
    public static function lang_files_csv_settings( $settings = false )
    {
        if( empty( self::$csv_settings ) )
            self::$csv_settings = self::default_lang_files_csv_settings();

        if( $settings === false )
            return self::$csv_settings;

        if( empty( $settings ) or !is_array( $settings ) )
            return false;

        foreach( self::$csv_settings as $key => $cur_val )
        {
            if( array_key_exists( $key, $settings ) )
                self::$csv_settings[$key] = $settings[$key];
        }

        return self::$csv_settings;
    }

    public static function st_get_default_language()
    {
        return self::$DEFAULT_LANGUAGE;
    }

    /**
     * @return string
     */
    public static function st_get_current_language()
    {
        return self::$CURRENT_LANGUAGE;
    }

    /**
     * @param string $lang
     *
     * @return bool|string
     */
    public static function st_set_current_language( $lang )
    {
        if( !($lang = self::st_valid_language( $lang )) )
            return false;

        self::$CURRENT_LANGUAGE = $lang;
        return self::$CURRENT_LANGUAGE;
    }

    /**
     * @param string $lang
     *
     * @return bool|string
     */
    public static function st_set_default_language( $lang )
    {
        if( !($lang = self::st_valid_language( $lang )) )
            return false;

        self::$DEFAULT_LANGUAGE = $lang;
        return self::$DEFAULT_LANGUAGE;
    }

    /**
     * @param string $key Property in current language definition array
     *
     * @return bool
     */
    public static function st_get_current_language_key( $key )
    {
        $clang = self::st_get_current_language();
        if( empty( $clang )
         or !is_scalar( $key )
         or empty( self::$DEFINED_LANGUAGES[$clang] ) or !is_array( self::$DEFINED_LANGUAGES[$clang] )
         or !array_key_exists( $key, self::$DEFINED_LANGUAGES[$clang] ) )
            return false;

        return self::$DEFINED_LANGUAGES[$clang][$key];
    }

    /**
     * Returns defined languages array (defined using self::define_language())
     *
     * @return array
     */
    public static function st_get_defined_languages()
    {
        return self::$DEFINED_LANGUAGES;
    }

    public function get_current_language_key( $key )
    {
        return self::st_get_current_language_key( $key );
    }

    public function get_defined_languages()
    {
        return self::st_get_defined_languages();
    }

    /**
     * @return string
     */
    public function get_default_language()
    {
        return self::st_get_default_language();
    }

    /**
     * @return string
     */
    public function get_current_language()
    {
        return self::st_get_current_language();
    }

    public function set_current_language( $lang )
    {
        return self::st_set_current_language( $lang );
    }

    public function set_default_language( $lang )
    {
        return self::st_set_default_language( $lang );
    }

    public function valid_language( $lang )
    {
        return self::st_valid_language( $lang );
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public static function prepare_lang_index( $lang )
    {
        return strtolower( trim( $lang ) );
    }

    /**
     *
     * Reset all indexes loaded for provided language if $lang !== false or all loaded indexes if $lang === false
     *
     * @param string|bool $lang
     *
     * @return $this
     */
    public function reset_language_indexes( $lang = false )
    {
        if( $lang === false )
        {
            self::$LANGUAGE_INDEXES = array();
            self::$_LOADED_FILES = array();
        } else
        {
            $lang = self::prepare_lang_index( $lang );
            if( isset( self::$LANGUAGE_INDEXES[$lang] ) )
                self::$LANGUAGE_INDEXES[$lang] = array();
            if( isset( self::$_LOADED_FILES[$lang] ) )
                self::$_LOADED_FILES[$lang] = array();
        }

        return $this;
    }

    /**
     * Tells if language $lang is a valid defined language in the system
     *
     * @param string $lang
     *
     * @return bool|string
     */
    public static function st_valid_language( $lang )
    {
        $lang = self::prepare_lang_index( $lang );
        return (isset( self::$DEFINED_LANGUAGES[$lang] )?$lang:false);
    }

    /**
     * Tells if language $lang is loaded (files were parsed and added to indexes array)
     *
     * @param $lang
     *
     * @return bool|string
     */
    public static function language_loaded( $lang )
    {
        if( !self::st_get_multi_language_enabled() )
            return true;

        $lang = self::prepare_lang_index( $lang );
        return (isset( self::$LANGUAGE_INDEXES[$lang] )?$lang:false);
    }

    public static function get_default_language_structure()
    {
        return array(
            'title' => '',
            'dir' => '',
            'www' => '',
            'files' => array(),
            'browser_lang' => '',
            'browser_charset' => '',
            'flag_file' =>'',
        );
    }

    /**
     * Define a language used by platform
     *
     * @param string $lang ISO 2 chars (lowercase) language code
     * @param array $lang_params Language details
     *
     * @return bool True if adding language was successful, false otherwise
     */
    public function define_language( $lang, array $lang_params )
    {
        $this->reset_error();

        $lang = self::prepare_lang_index( $lang );
        if( empty( $lang )
         or empty( $lang_params ) or !is_array( $lang_params )
         or empty( $lang_params['title'] ) )
        {
            $this->set_error( self::ERR_LANGUAGE_DEFINITION, 'Please provide valid parameters for language definition.' );
            return false;
        }

        if( empty( self::$DEFINED_LANGUAGES[$lang] ) )
            self::$DEFINED_LANGUAGES[$lang] = array();

        self::$DEFINED_LANGUAGES[$lang]['title'] = trim( $lang_params['title'] );

        if( empty( self::$DEFINED_LANGUAGES[$lang]['files'] ) )
            self::$DEFINED_LANGUAGES[$lang]['files'] = array();

        if( !empty( $lang_params['files'] ) )
        {
            if( !$this->add_language_files( $lang, $lang_params['files'] ) )
                return false;
        }

        $default_language_structure = self::get_default_language_structure();
        foreach( $default_language_structure as $key => $def_value )
        {
            if( array_key_exists( $key, $lang_params ) )
                self::$DEFINED_LANGUAGES[$lang][$key] = $lang_params[$key];

            elseif( !array_key_exists( $key, self::$DEFINED_LANGUAGES[$lang] ) )
                self::$DEFINED_LANGUAGES[$lang][$key] = $def_value;
        }

        return true;
    }

    /**
     * @param string $dir Directory to scan for language files ({en|gb|de|ro}.csv)
     *
     * @return bool True on success, false if directory is not readable
     */
    public function scan_for_language_files( $dir )
    {
        if( !self::st_get_multi_language_enabled() )
            return true;

        $dir = rtrim( PHS::from_relative_path( PHS::relative_path( $dir ) ), '/\\' );
        if( empty( $dir )
         or !@is_dir( $dir ) or !@is_readable( $dir ) )
            return false;

        if( ($languages_arr = $this->get_defined_languages())
        and is_array( $languages_arr ) )
        {
            foreach( $languages_arr as $lang_key => $lang_arr )
            {
                $language_file = $dir.'/'.$lang_key.'.csv';
                if( @file_exists( $language_file ) )
                    $this->add_language_files( $lang_key, array( $language_file ) );
            }
        }

        return true;
    }

    public function force_reload_language_files( $lang )
    {
        $this->reset_error();

        $lang = self::prepare_lang_index( $lang );
        if( empty( $lang )
         or !($lang = self::st_valid_language( $lang )) )
        {
            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Language not defined.' );
            return false;
        }

        self::$_RELOAD_LANGUAGES[$lang] = true;

        return true;
    }

    public function should_reload_language_files( $lang )
    {
        $this->reset_error();

        $lang = self::prepare_lang_index( $lang );
        if( empty( $lang )
         or !($lang = self::st_valid_language( $lang )) )
        {
            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Language not defined.' );
            return false;
        }

        return (!empty( self::$_RELOAD_LANGUAGES[$lang] )?true:false);
    }

    public function add_language_files( $lang, array $files_arr )
    {
        $this->reset_error();

        if( !self::st_get_multi_language_enabled() )
            return true;

        $lang = self::prepare_lang_index( $lang );
        if( empty( $lang )
         or !($lang = self::st_valid_language( $lang )) )
        {
            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Language not defined.' );
            return false;
        }

        if( !is_array( $files_arr ) )
        {
            $this->set_error( self::ERR_LANGUAGE_DEFINITION, 'You should provide an array of files to be added to language ['.$lang.'].' );
            return false;
        }

        foreach( $files_arr as $lang_file )
        {
            if( empty( $lang_file )
             or !@file_exists( $lang_file ) or !@is_readable( $lang_file ) )
            {
                $this->set_error( self::ERR_LANGUAGE_DEFINITION, 'Language file ['.@basename( $lang_file ).'] for language ['.$lang.'] not found or not readable.' );
                return false;
            }

            if( !in_array( $lang_file, self::$DEFINED_LANGUAGES[$lang]['files'], true ) )
            {
                self::$DEFINED_LANGUAGES[$lang]['files'][] = $lang_file;
                $this->force_reload_language_files( $lang );
            }
        }

        return true;
    }

    /**
     * Returns language details as it was defined using self::define_language()
     *
     * @param string $lang
     *
     * @return bool|array
     */
    public static function get_defined_language( $lang )
    {
        if( !($lang = self::st_valid_language( $lang )) )
            return false;

        return self::$DEFINED_LANGUAGES[$lang];
    }

    private function _loading_language( $loading = null )
    {
        static $is_loading = false;

        if( $loading === null )
            return $is_loading;

        $is_loading = (!empty( $loading ));

        return $is_loading;
    }

    /**
     * Loads provided CSV files in 'files' index of language definition array for language $lang
     *
     * @param string $lang ISO 2 chars (lowercase) language code
     * @param bool $force Force loading laguange files
     *
     * @return bool True if loading was with success, false otherwise
     */
    public function load_language( $lang, $force = false )
    {
        $this->reset_error();

        if( !self::st_get_multi_language_enabled() )
            return true;

        $this->_loading_language( true );

        if( !($lang = self::st_valid_language( $lang ))
         or !($lang_details = self::get_defined_language( $lang )) )
        {
            $this->_loading_language( false );

            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Language ['.(!empty( $lang )?$lang:'N/A').'] not defined or has no files to be loaded.' );
            return false;
        }

        // PHS::get_theme_language_paths() might set static errors. We don't want to check errors here and we will preserve static error
        $old_static_error = self::st_stack_error();
        if( ($default_theme = PHS::get_default_theme())
        and ($theme_language_paths = PHS::get_theme_language_paths( $default_theme ))
        and !empty( $theme_language_paths['path'] ) )
        {
            if( !$this->scan_for_language_files( $theme_language_paths['path'] ) )
            {
                $this->_loading_language( false );
                return false;
            }
        }

        if( ($current_theme = PHS::get_theme())
        and $default_theme !== $current_theme
        and ($theme_language_paths = PHS::get_theme_language_paths( $current_theme ))
        and !empty( $theme_language_paths['path'] ) )
        {
            if( !$this->scan_for_language_files( $theme_language_paths['path'] ) )
            {
                $this->_loading_language( false );
                return false;
            }
        }

        self::st_restore_errors( $old_static_error );

        // After theme directories were scanned start loading files...
        if( !($lang_details = self::get_defined_language( $lang )) )
        {
            $this->_loading_language( false );

            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Language ['.$lang.'] not defined or has no files to be loaded.' );
            return false;
        }

        // Don't throw error if language is defined, but has no files to be loaded...
        if( !empty( $lang_details['files'] ) and is_array( $lang_details['files'] ) )
        {
            foreach( $lang_details['files'] as $file )
            {
                if( !$this->load_language_file( $file, $lang, $force ) )
                {
                    $this->_loading_language( false );
                    return false;
                }
            }
        }

        if( !empty( self::$_RELOAD_LANGUAGES[$lang] ) )
            unset( self::$_RELOAD_LANGUAGES[$lang] );

        $this->_loading_language( false );

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
    public function load_language_file( $file, $lang, $force = false )
    {
        if( !self::st_get_multi_language_enabled() )
            return true;

        if( empty( $force )
        and !empty( self::$_LOADED_FILES[$lang][$file] ) )
            return true;

        if( !($language_arr = $this->get_language_file_lines( $file, $lang )) )
            return false;

        foreach( $language_arr as $index => $index_lang )
            self::$LANGUAGE_INDEXES[$lang][$index] = $index_lang;

        if( empty( self::$_LOADED_FILES[$lang] ) )
            self::$_LOADED_FILES[$lang] = array();

        self::$_LOADED_FILES[$lang][$file] = true;

       return true;
    }

    public function get_language_file_header_arr()
    {
        return array(
            '# !!!!! DON\'T EDIT THIS COLUMN AT ALL !!!!!' => 'TEXT IN THIS COLUMN SHOULD BE TRANSLATED IN DESIRED LANGUAGE',
            '# Please use Excel (or similar) as editor for this file to be sure formatting will not be broken!!!' => '',
            '# Lines starting with # will be ignored.' => 'Column separator is comma!! (configure Excel or similar to use comma)',
        );
    }

    public function get_language_file_header_str()
    {
        if( !($lines_arr = $this->get_language_file_header_arr()) )
            return '';

        if( !($csv_settings = self::lang_files_csv_settings()) )
            $csv_settings = self::default_lang_files_csv_settings();

        $return_str = '';
        foreach( $lines_arr as $lang_key => $lang_val )
        {
            $return_str .= PHS_Utils::csv_line( array( $lang_key, $lang_val ),
                                                $csv_settings['line_delimiter'],
                                                $csv_settings['columns_delimiter'],
                                                $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape'] );
        }

        return $return_str;
    }

    /**
     * Parse specified language file and get language array
     *
     * @param string $file
     * @param string $lang
     *
     * @return bool|array Returns parsed lines from language CSV file or false on error
     */
    public function get_language_file_lines( $file, $lang )
    {
        $this->reset_error();

        if( !self::st_get_multi_language_enabled() )
            return array();

        if( !($lang = self::st_valid_language( $lang )) )
        {
            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Language ['.(!empty( $lang )?$lang:'N/A').'] not defined.' );
            return false;
        }

        if( !($utf8_file = self::convert_to_utf8( $file )) )
        {
            $this->set_error( self::ERR_LANGUAGE_LOAD, 'Couldn\'t convert language file ['.@basename( $file ).'], language ['.$lang.'] to UTF-8 encoding.' );
            return false;
        }

        if( !@file_exists( $utf8_file ) or !is_readable( $utf8_file )
         or !($fil = @fopen( $utf8_file, 'rb' )) )
        {
            $this->set_error( self::ERR_LANGUAGE_LOAD, 'File ['.@basename( $utf8_file ).'] doesn\'t exist or is not readable for language ['.$lang.'].' );
            return false;
        }

        if( !($csv_settings = self::lang_files_csv_settings()) )
            $csv_settings = self::default_lang_files_csv_settings();

        if( function_exists( 'mb_internal_encoding' ) )
            @mb_internal_encoding( 'UTF-8' );

        $mb_substr_exists = false;
        if( function_exists( 'mb_substr' ) )
            $mb_substr_exists = true;

        $return_arr = array();
        while( ($buf = @fgets( $fil )) )
        {
            if( ($mb_substr_exists and @mb_substr( ltrim( $buf ), 0, 1 ) === '#')
             or (!$mb_substr_exists and @substr( ltrim( $buf ), 0, 1 ) === '#') )
                continue;

            $buf = rtrim( $buf, "\r\n" );

            if( !($csv_line = @str_getcsv( $buf, $csv_settings['columns_delimiter'], $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape'] ))
             or !is_array( $csv_line )
             or count( $csv_line ) !== 2 )
                continue;

            $index = $csv_line[0];
            $index_lang = $csv_line[1];

            $return_arr[$index] = $index_lang;
        }

        @fclose( $fil );

       return $return_arr;
    }

    /**
     * Given an absolute file path, this method will return file name which should contain UTF-8 encoded content of original file
     *
     * @param string $file ablsolute path of file which should be converted to UTF-8 encoding
     *
     * @return string Resulting file name which will hold UTF-8 encoded content of original file
     */
    public static function get_utf8_file_name( $file )
    {
        $path_info = @pathinfo( $file );
        return $path_info['dirname'].'/'.$path_info['filename'].'-utf8.'.$path_info['extension'];
    }

    /**
     * Converts a given file to a UTF-8 encoded content.
     *
     * @param string $file ablsolute path of file which should be converted to UTF-8 encoding
     * @param bool|array $params Method parameters allows to overwrite UTF-8 encoded file name
     *
     * @return bool|string Returns absolute path of UTF-8 encoded file
     */
    public static function convert_to_utf8( $file, $params = false )
    {
        if( empty( $file ) or !@file_exists( $file ) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['utf8_file'] ) )
            $params['utf8_file'] = self::get_utf8_file_name( $file );

        // On some systems file and iconv binaries are not available and will display the results of which command
        // So, as long as utf8 files exist, just let it be...
        if( !self::st_get_utf8_conversion()
        and @file_exists( $params['utf8_file'] ) )
            return $params['utf8_file'];

        ob_start();
        if( !($file_bin = @system( 'which file' ))
         or !($iconv_bin = @system( 'which iconv' )) )
        {
            ob_end_clean();

            // we don't have required files to convert csv to utf8... check if we have a utf8 language file...
            if( @file_exists( $params['utf8_file'] ) )
                return $params['utf8_file'];

            return false;
        }

        if( !($file_mime = @system( $file_bin.' --mime-encoding '.escapeshellarg( $file ) )) )
            return false;

        $file_mime = str_replace( $file.': ', '', $file_mime );

        if( !in_array( strtolower( $file_mime ), array( 'utf8', 'utf-8' ), true ) )
        {
            if( @system( $iconv_bin.' -f ' . escapeshellarg( $file_mime ) . ' -t utf-8 ' . escapeshellarg( $file ) . ' > ' . escapeshellarg( $params['utf8_file'] ) ) === false
             or !@file_exists( $params['utf8_file'] ) )
            {
                ob_end_clean();

                return false;
            }
        } else
            @copy( $file, $params['utf8_file'] );
        ob_end_clean();

        if( !@file_exists( $params['utf8_file'] ) )
            return false;

        return $params['utf8_file'];
    }

    /**
     * Translate text $index. If $index contains %XX format (@see vsprintf), arguments will be passed in $args parameter.
     *
     * @param string $index Language index to be translated
     * @param array $args Array of arguments to be used to populate $index (@see vsprintf)
     *
     * @return string Translated string
     */
    public function _t( $index, array $args = array() )
    {
        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( !isset( $args[0] )
         or !$this->valid_language( $args[0] ) )
            $t_lang = $this->get_current_language();

        else
        {
            $t_lang = $args[0];
            @array_shift( $args );
        }

        return $this->_tl( $index, $t_lang, $args );
    }

    /**
     * Translate text $index for language $lang. If $index contains %XX format (@see vsprintf), arguments will be passed in $args parameter.
     *
     * @param string $index Language index to be translated
     * @param string $lang
     * @param array $args Array of arguments to be used to populate $index (@see vsprintf)
     *
     * @return string
     */
    public function _tl( $index, $lang, array $args = array() )
    {
        if( !is_string( $index ) )
            return 'Language index is not a string ('.gettype( $index ).' provided)';

        $lang = self::st_valid_language( $lang );

        if( empty( $args ) or !is_array( $args ) )
            $args = array();

        if( self::st_get_multi_language_enabled()
        and !empty( $lang )
        and !$this->_loading_language()
        and (!self::language_loaded( $lang )
            or $this->should_reload_language_files( $lang )
            ) )
        {
            if( !$this->load_language( $lang ) )
            {
                if( ($error_arr = $this->get_error()) and !empty( $error_arr['error_simple_msg'] ) )
                    $error_msg = $error_arr['error_simple_msg'];
                else
                    $error_msg = 'Error loading language [' . $lang . ']';

                return $error_msg;
            }
        }

        if( self::st_get_multi_language_enabled()
        and !empty( $lang )
        and isset( self::$LANGUAGE_INDEXES[$lang][$index] ) )
            $working_index = self::$LANGUAGE_INDEXES[$lang][$index];
        else
            $working_index = $index;

        if( !empty( $args )
        and isset( $args[0] )
        and $args[0] === false )
            @array_shift( $args );

        if( !empty( $args ) )
        {
            // we should replace some %s...
            if( ($result = @vsprintf( $working_index, $args )) !== false )
                return $result;

            return $working_index.' ['.count( $args ).' args]';
        }

        return $working_index;
    }

}
