<?php

namespace phs\libraries;

if( !defined( 'PHS_VERSION' )
 && (!defined( 'PHS_SETUP_FLOW' ) || !constant( 'PHS_SETUP_FLOW' )) )
    exit;

use \phs\PHS;

class PHS_Language extends PHS_Error
{
    // Key in session to hold current language
    const LANG_SESSION_KEY = 'phs_lang';

    // Parameter received in _GET or _POST to set the language for current page
    const LANG_URL_PARAMETER = '__phsl';

    const LANG_LINE_DELIMITER = "\n", LANG_COLUMNS_DELIMITER = ',', LANG_COLUMNS_ENCLOSURE = '"', LANG_ENCLOSURE_ESCAPE = '"';

    /** @var PHS_Language_Container $lang_callable_obj */
    private static $lang_callable_obj = false;

    /**
     * Returns language class that handles translation tasks
     *
     * @return PHS_Language_Container
     */
    public static function language_container()
    {
        if( empty( self::$lang_callable_obj ) )
            self::$lang_callable_obj = new PHS_Language_Container();

        return self::$lang_callable_obj;
    }

    /**
     * @return bool Returns true if system should try converting language files to utf8
     */
    public static function get_utf8_conversion_enabled()
    {
        return self::language_container()->get_utf8_conversion_enabled();
    }

    /**
     * @param bool $enabled Whether utf8 conversion should be enabled or not
     * @return bool Returns utf8 conversion enabled value currently set
     */
    public static function set_utf8_conversion( $enabled )
    {
        return self::language_container()->set_utf8_conversion( $enabled );
    }

    /**
     * @return bool Returns true if multi-language is enabled or false otherwise
     */
    public static function get_multi_language_enabled()
    {
        return self::language_container()->get_multi_language_enabled();
    }

    /**
     * @param bool $enabled Whether multi-language should be enabled or not
     * @return bool Returns multi language enabled value currently set
     */
    public static function set_multi_language( $enabled )
    {
        return self::language_container()->set_multi_language( $enabled );
    }

    /**
     * @return array Returns array of defined languages
     */
    public static function get_defined_languages()
    {
        return self::language_container()->get_defined_languages();
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
        return self::language_container()->get_defined_language( $lang );
    }

    /**
     * @param string $key Language array key to be returned
     * @return mixed Returns currently selected language details from language array definition
     */
    public static function get_current_language_key( $key )
    {
        return self::language_container()->get_current_language_key( $key );
    }

    /**
     * @return string Returns currently selected language
     */
    public static function get_default_language()
    {
        return self::language_container()->get_default_language();
    }

    /**
     * @return string Returns currently selected language
     */
    public static function get_current_language()
    {
        return self::language_container()->get_current_language();
    }

    /**
     * @param string $lang Language to be set as current
     * @return string Returns currently selected language
     */
    public static function set_current_language( $lang )
    {
        return self::language_container()->set_current_language( $lang );
    }

    /**
     * @param string $lang Language to be set as default
     * @return string Returns default language
     */
    public static function set_default_language( $lang )
    {
        return self::language_container()->set_default_language( $lang );
    }

    /**
     * @param string $lang For which language to add files
     * @param array $files_arr Array of files to be added
     *
     * @return string Returns currently selected language
     */
    public static function add_language_files( $lang, $files_arr )
    {
        return self::language_container()->add_language_files( $lang, $files_arr );
    }

    /**
     * @param string $dir Directory to scan for language files ({en|gb|de|ro}.csv)
     *
     * @return bool True on success, false if directory is not readable
     */
    public static function scan_for_language_files( $dir )
    {
        return self::language_container()->scan_for_language_files( $dir );
    }

    /**
     * @param string $lang For which language we want to reload the files
     *
     * @return bool Returns if language will be reloaded or not
     */
    public static function force_reload_language_files( $lang )
    {
        return self::language_container()->force_reload_language_files( $lang );
    }

    /**
     * @param string $lang Language to be checked
     *
     * @return string|bool Returns language key if language is valid or false if language is not defined
     */
    public static function valid_language( $lang )
    {
        return self::language_container()->valid_language( $lang );
    }

    /**
     * Define a language used by platform
     *
     * @param string $lang ISO 2 chars (lowercase) language code
     * @param array $lang_params Language details
     *
     * @return bool True if adding language was successful, false otherwise
     */
    public static function define_language( $lang, array $lang_params )
    {
        if( !self::language_container()->define_language( $lang, $lang_params ) )
        {
            self::st_copy_error( self::language_container() );
            return false;
        }

        return true;
    }

    /**
     * Parse specified language file and get language array
     *
     * @param string $file
     * @param string $lang
     *
     * @return bool|array Returns parsed lines from language CSV file or false on error
     */
    public static function get_language_file_lines( $file, $lang )
    {
        self::st_reset_error();

        $language_container = self::language_container();

        if( false === ($return_arr = $language_container->get_language_file_lines( $file, $lang )) )
        {
            if( $language_container->has_error() )
                self::st_copy_error( $language_container );

            return false;
        }

        return $return_arr;
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
    public static function load_language_file( $file, $lang, $force = false )
    {
        self::st_reset_error();

        $language_container = self::language_container();

        if( !($return_arr = $language_container->load_language_file( $file, $lang, $force )) )
        {
            if( $language_container->has_error() )
                self::st_copy_error( $language_container );

            return false;
        }

        return $return_arr;
    }

    public static function get_language_file_header_arr()
    {
        return self::language_container()->get_language_file_header_arr();
    }

    public static function get_language_file_header_str()
    {
        return self::language_container()->get_language_file_header_str();
    }

    public static function lang_files_csv_settings( $settings = false )
    {
        $lang_container = self::language_container();

        return $lang_container::lang_files_csv_settings( $settings );
    }

    public static function default_lang_files_csv_settings()
    {
        $lang_container = self::language_container();

        return $lang_container::default_lang_files_csv_settings();
    }

    /**
     * @param string $index Language index
     * @param string $ch What to escape (quote or double quote)
     * @return string
     */
    public function _pte( $index, $ch = '"' )
    {
        return self::_e( $this->_pt( $index ), $ch );
    }

    /**
     * @param string $index Language index
     * @return string
     */
    public function _pt( $index )
    {
        /** @var PHS_Plugin|PHS_Library $this */
        if( (!($this instanceof PHS_Instantiable) && !($this instanceof PHS_Library))
         || !($plugin_obj = $this->get_plugin_instance()) )
            return self::_t( func_get_args() );

        $plugin_obj->include_plugin_language_files();

        if( !($result = @forward_static_call_array( [ '\phs\libraries\PHS_Language', '_t' ], func_get_args() )) )
            $result = '';

        return $result;
    }

    /**
     * @param string $index Language index
     *
     * @return mixed|string
     */
    public static function st_pt( $index )
    {
        if( !($called_class = @get_called_class())
         || !($clean_class_name = ltrim( $called_class, '\\' ))
         || stripos( $clean_class_name, 'phs\\plugins\\' ) !== 0
         || !($parts_arr = explode( '\\', $clean_class_name, 4 ))
         || empty( $parts_arr[2] )
         || !($plugin_obj = PHS::load_plugin( $parts_arr[2] )) )
            return self::_t( func_get_args() );

        $plugin_obj->include_plugin_language_files();

        if( !($result = @forward_static_call_array( [ '\phs\libraries\PHS_Language', '_t' ], @func_get_args() )) )
            $result = '';

        return $result;
    }

    /**
     * Translate a specific text in currently selected language. This method receives a variable number of parameters in same way as sprintf works.
     * @param string|array $index Language index to be translated or array of arguments to be processed
     *
     * @return string Translated string
     */
    public static function _t( $index )
    {
        if( is_array( $index ) )
        {
            $arg_list = $index;
            $numargs = count( $arg_list );
            if( isset( $arg_list[0] ) )
                $index = $arg_list[0];
        } else
        {
            $numargs = func_num_args();
            $arg_list = func_get_args();
        }

        if( $numargs > 1 )
            @array_shift( $arg_list );
        else
            $arg_list = [];

        return self::language_container()->_t( $index, $arg_list );
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
    public static function _te( $index, $ch = '"' )
    {
        return self::_e( self::_t( $index ), $ch );
    }

    /**
     * Escapes ' or " in string parameter (useful when displaying strings in html/javascript strings)
     *
     * @param string $str String to be escaped
     * @param string $ch Escaping character (' or ")
     * @return string
     */
    public static function _e( $str, $ch = '"' )
    {
        return str_replace( $ch, ($ch==='\''?'\\\'':'\\"'), $str );
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
    public static function _tl( $index, $lang )
    {
        $numargs = func_num_args();
        $arg_list = func_get_args();

        if( $numargs > 2 )
        {
            @array_shift( $arg_list );
            @array_shift( $arg_list );
        } else
            $arg_list = [];

        return self::language_container()->_tl( $index, $lang, $arg_list );
    }
}
