<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Utils;

class PHS_Po_format extends PHS_Registry
{
    const ERR_PO_FILE = 1, ERR_INPUT_BUFFER = 2, ERR_LANGUAGE_FILE = 3, ERR_EXPORT = 4;

    const PLUGINS_DIST_DIRNAME = 'plugins.dist', THEME_DIST_DIRNAME = 'default.dist';

    private $can_use_generator = false;

    private $filename = '';

    private $lines_arr = array();
    private $_li = 0;

    private $header_lines = 0;
    // Header values as they are defined in PO file
    private $header_arr = array();

    // After parsing a PO file class caches results here...
    private $parsed_language = '';
    private $parsed_indexes = array();
    private $indexes_count = 0;

    public function set_filename( $f )
    {
        $this->reset_error();

        if( empty( $f )
         or !@file_exists( $f )
         or !@is_readable( $f ) )
        {
            $this->set_error( self::ERR_PO_FILE, self::_t( 'PO file not found or not readable.' ) );
            return false;
        }

        if( version_compare( PHP_VERSION, '5.5.0', '>' ) )
            $this->can_use_generator = true;
        else
            $this->can_use_generator = false;

        if( !($buf = @file_get_contents( $f )) )
        {
            $this->set_error( self::ERR_PO_FILE, self::_t( 'Couldn\'t read PO file or it is empty.' ) );
            return false;
        }

        $this->filename = $f;

        return $this->set_buffer( $buf );
    }

    public function set_buffer( $b )
    {
        $this->reset_lines_arr();

        if( !$this->get_lines( $b ) )
            return false;

        return true;
    }

    private function _reset_parsed_indexes()
    {
        $this->indexes_count = 0;
        $this->parsed_language = '';
        $this->parsed_indexes = array();
    }

    public function get_parsed_indexes()
    {
        return array(
            'count' => $this->indexes_count,
            'language' => $this->parsed_language,
            'language_files' => ((!empty( $this->parsed_indexes ) and is_array( $this->parsed_indexes ))?array_keys( $this->parsed_indexes ):array()),
            'indexes' => $this->parsed_indexes,
        );
    }

    public function backup_language_file( $lang_file )
    {
        $this->reset_error();

        $return_arr = array();
        $return_arr['backup_created'] = false;
        $return_arr['path'] = '';
        $return_arr['file_name'] = '';
        $return_arr['full_name'] = '';

        if( empty( $lang_file )
         or !@file_exists( $lang_file ) )
            return $return_arr;

        if( !($path_info = PHS_Utils::mypathinfo( $lang_file ))
         or $lang_file == PHS::relative_path( $path_info['dirname'] )
         or empty( $path_info['extension'] ) or $path_info['extension'] != 'csv'
         or empty( $path_info['basename'] ) or !self::valid_language( $path_info['basename'] ) )
        {
            $this->set_error( self::ERR_LANGUAGE_FILE, self::_t( 'Couldn\'t get details about language file or language file is invalid.' ) );
            return false;
        }

        if( !@is_dir( $path_info['dirname'] )
         or !@is_writable( $path_info['dirname'] ) )
        {
            $this->set_error( self::ERR_LANGUAGE_FILE, self::_t( 'Destination directory is not writable. Please check write rights.' ) );
            return false;
        }

        $backup_file_name = $path_info['basename'].'_bk'.date( 'YmdHis' ).'.csv';

        if( ($bk_files = @glob( $path_info['dirname'].'/'.$path_info['basename'].'_bk*.csv' )) )
        {
            foreach( $bk_files as $bk_file )
                @unlink( $bk_file );
        }

        $return_arr['backup_created'] = true;
        $return_arr['path'] = $path_info['dirname'];
        $return_arr['file_name'] = $backup_file_name;
        $return_arr['full_name'] = $path_info['dirname'].'/'.$backup_file_name;

        if( !@copy( $lang_file, $return_arr['full_name'] ) )
        {
            $this->set_error( self::ERR_LANGUAGE_FILE, self::_t( 'Error creating backup file in destination directory. Please check write rights.' ) );
            return false;
        }

        return $return_arr;
    }

    public function export_csv_from_po( $po_file, $params = false )
    {
        $this->reset_error();

        if( !$this->set_filename( $po_file ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PO_FILE, self::_t( 'Couldn\'t read PO file or it is empty.' ) );

            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['csv_line_delimiter'] ) )
            $params['csv_line_delimiter'] = "\n";
        if( empty( $params['csv_column_delimiter'] ) )
            $params['csv_column_delimiter'] = ',';
        if( empty( $params['csv_column_enclosure'] ) )
            $params['csv_column_enclosure'] = '"';
        if( empty( $params['csv_column_escape'] ) )
            $params['csv_column_escape'] = '"';

        if( !isset( $params['export_to_output'] ) )
            $params['export_to_output'] = true;
        else
            $params['export_to_output'] = (!empty( $params['export_to_output'] )?true:false);

        if( empty( $params['export_to_filename'] ) )
            $params['export_to_filename'] = false;

        $csv_dir = false;
        if( !empty( $params['export_to_filename'] )
        and (!is_string( $params['export_to_filename'] )
            or !($csv_dir = rtrim( @dirname( $params['export_to_filename'] ), '/\\' ))
            or !@is_dir( $csv_dir ) or !@is_writable( $csv_dir )
            ) )
        {
            $this->set_error( self::ERR_EXPORT, self::_t( 'Please provide a valid export csv filename and make sure directory is writable.' ) );
            return false;
        }

        $csv_real_file = $params['export_to_filename'];

        if( empty( $params['language'] ) )
        {
            if( ($guessed_language = $this->guess_language_from_header())
            and !empty( $guessed_language['guessed_language'] ) )
                $params['language'] = $guessed_language['guessed_language'];
        }

        if( empty( $params['language'] )
         or !($lang_details = self::get_defined_language( $params['language'] )) )
            $params['language'] = false;

        $this->_reset_parsed_indexes();

        $this->parsed_language = $params['language'];

        $csv_f = false;
        if( !empty( $csv_real_file ) )
        {
            if( !($csv_f = @fopen( $csv_real_file, 'w' )) )
            {
                $this->set_error( self::ERR_EXPORT, self::_t( 'Couldn\'t open export csv filename for writing.' ) );
                return false;
            }
        }

        while( ($translation_arr = $this->extract_po_translation()) )
        {
            if( empty( $translation_arr['index'] ) )
                continue;

            if( !isset( $translation_arr['translation'] ) )
                $translation_arr['translation'] = '';

            $csv_line = PHS_Utils::csv_line( array( $translation_arr['index'], $translation_arr['translation'] ),
                                             $params['csv_line_delimiter'], $params['csv_column_delimiter'],
                                             $params['csv_column_enclosure'], $params['csv_column_escape'] );

            if( !empty( $csv_f ) )
            {
                @fputs( $csv_f, $csv_line );
                @fflush( $csv_f );
            } else
                echo $csv_line;
        }

        if( !empty( $csv_f ) )
        {
            @fflush( $csv_f );
            @fclose( $csv_f );
        }

        return true;
    }

    public function update_language_files( $po_file, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['language'] ) )
            $params['language'] = false;

        elseif( !self::valid_language( $params['language'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Language is invalid.' ) );
            return false;
        }

        if( empty( $params['update_language_files'] ) )
            $params['update_language_files'] = false;
        else
            $params['update_language_files'] = true;

        if( !isset( $params['backup_language_files'] ) )
            $params['backup_language_files'] = true;
        else
            $params['backup_language_files'] = (!empty( $params['backup_language_files'] )?true:false);

        if( !isset( $params['merge_with_old_files'] ) )
            $params['merge_with_old_files'] = true;
        else
            $params['merge_with_old_files'] = (!empty( $params['merge_with_old_files'] )?true:false);

        if( !$this->parse_language_from_po_file( $po_file, $params ) )
            return false;

        $return_arr = array();
        $return_arr['new_indexes'] = 0;
        $return_arr['updated_indexes'] = 0;
        $return_arr['updated_files'] = array();
        $return_arr['update_errors'] = array();

        if( !$this->indexes_count
         or empty( $this->parsed_indexes )
         or !is_array( $this->parsed_indexes ) )
            return $return_arr;

        if( !($csv_settings = self::lang_files_csv_settings()) )
            $csv_settings = self::default_lang_files_csv_settings();

        foreach( $this->parsed_indexes as $lang_file => $indexes_arr )
        {
            if( !is_array( $indexes_arr ) )
                continue;

            $new_indexes = 0;
            $updated_indexes = 0;
            if( empty( $params['merge_with_old_files'] ) )
            {
                $resulting_arr = $indexes_arr;
                $new_indexes = count( $indexes_arr );
            } else
            {
                if( !@file_exists( $lang_file )
                 or !($existing_lines_arr = self::get_language_file_lines( $lang_file, $this->parsed_language )) )
                    $existing_lines_arr = array();

                // Language file exists... see what we can update / add
                $resulting_arr = $existing_lines_arr;
                foreach( $indexes_arr as $lang_key => $lang_val )
                {
                    if( !isset( $resulting_arr[$lang_key] ) )
                        $new_indexes++;
                    elseif( $lang_val != $resulting_arr[$lang_key] )
                        $updated_indexes ++;
                    else
                        // Same thing...
                        continue;

                    $resulting_arr[$lang_key] = $lang_val;
                }
            }

            if( empty( $new_indexes ) and empty( $updated_indexes )
            and !empty( $resulting_arr ) )
                continue;

            if( !empty( $params['update_language_files'] ) )
            {
                if( !empty( $params['backup_language_files'] )
                and !$this->backup_language_file( $lang_file ) )
                {
                    $error_msg = self::_t( 'Error creating backup for language file [%s]', $lang_file );

                    $return_arr['update_errors'][$lang_file] = $error_msg;

                    PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_MAINTENANCE );
                    continue;
                }

                if( !($fp = @fopen( $lang_file, 'w' )) )
                {
                    $error_msg = self::_t( 'Error creating language file [%s]', $lang_file );

                    $return_arr['update_errors'][$lang_file] = $error_msg;

                    PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_MAINTENANCE );
                    continue;
                }

                if( ($header_str = self::get_language_file_header_str())
                and !@fwrite( $fp, $header_str ) )
                {
                    PHS_Logger::logf( 'Error writing header for language file ['.$lang_file.']', PHS_Logger::TYPE_MAINTENANCE );
                }
            }

            if( empty( $new_indexes ) and empty( $updated_indexes ) )
            {
                if( !empty( $params['update_language_files'] ) )
                {
                    @fflush( $fp );
                    @fclose( $fp );
                }

                self::load_language_file( $lang_file, $this->parsed_language, true );

                continue;
            }

            if( !empty( $params['update_language_files'] ) )
            {
                $line = 1;
                foreach( $resulting_arr as $lang_key => $lang_val )
                {
                    if( !($csv_line = PHS_Utils::csv_line( array( $lang_key, $lang_val ),
                                                          $csv_settings['line_delimiter'],
                                                          $csv_settings['columns_delimiter'],
                                                          $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape'] ))
                     or !@fwrite( $fp, $csv_line ) )
                    {
                        PHS_Logger::logf( 'Error writing ['.$lang_key.'] language index at position ['.$line.']', PHS_Logger::TYPE_MAINTENANCE );
                    }

                    $line++;
                }

                @fflush( $fp );
                @fclose( $fp );

                self::load_language_file( $lang_file, $this->parsed_language, true );
            }

            $return_arr['new_indexes'] += $new_indexes;
            $return_arr['updated_indexes'] += $updated_indexes;

            $return_arr['updated_files'][] = $lang_file;
        }

        return $return_arr;
    }

    public function parse_language_from_po_file( $po_file, $params = false )
    {
        $this->reset_error();

        if( !$this->set_filename( $po_file ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PO_FILE, self::_t( 'Couldn\'t read PO file or it is empty.' ) );

            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['language'] ) )
        {
            if( ($guessed_language = $this->guess_language_from_header())
            and !empty( $guessed_language['guessed_language'] ) )
                $params['language'] = $guessed_language['guessed_language'];
        }

        if( empty( $params['language'] )
         or !($lang_details = self::get_defined_language( $params['language'] )) )
        {
            $this->set_error( self::ERR_PO_FILE, self::_t( 'Language is invalid.' ) );
            return false;
        }

        if( empty( $lang_details['dir'] )
         or !@is_dir( rtrim( $lang_details['dir'], '/' ) ) )
        {
            $this->set_error( self::ERR_PO_FILE, self::_t( 'System language directory %s doesn\'t exist.', (!empty( $lang_details['dir'] )?$lang_details['dir']:'N/A') ) );
            return false;
        }

        $this->_reset_parsed_indexes();

        $this->parsed_language = $params['language'];

        $language_directory = $lang_details['dir'];

        $plugins_relative_path = PHS::relative_path( PHS_PLUGINS_DIR );
        $plugins_relative_path_len = strlen( $plugins_relative_path );
        $plugins_dist_dirname_len = strlen( self::PLUGINS_DIST_DIRNAME );

        $themes_relative_path = PHS::relative_path( PHS_THEMES_DIR );
        $themes_relative_path_len = strlen( $themes_relative_path );

        while( ($translation_arr = $this->extract_po_translation()) )
        {
            if( empty( $translation_arr['index'] )
             or empty( $translation_arr['translation'] )
             or empty( $translation_arr['files'] )
             or !is_array( $translation_arr['files'] ) )
                continue;

            foreach( $translation_arr['files'] as $lang_file_arr )
            {
                if( empty( $lang_file_arr['file'] )
                 or substr( $lang_file_arr['file'], 0, $plugins_dist_dirname_len ) == self::PLUGINS_DIST_DIRNAME )
                    continue;

                $plugin_name = '';
                $theme_name = '';
                if( substr( $lang_file_arr['file'], 0, $plugins_relative_path_len ) == $plugins_relative_path
                and ($path_rest = substr( $lang_file_arr['file'], $plugins_relative_path_len ))
                and ($path_rest_arr = explode( '/', $path_rest, 2 ))
                and !empty( $path_rest_arr[0] ) )
                    $plugin_name = $path_rest_arr[0];

                if( substr( $lang_file_arr['file'], 0, $themes_relative_path_len ) == $themes_relative_path
                and ($path_rest = substr( $lang_file_arr['file'], $themes_relative_path_len ))
                and ($path_rest_arr = explode( '/', $path_rest, 2 ))
                and !empty( $path_rest_arr[0] ) )
                    $theme_name = $path_rest_arr[0];

                if( $theme_name == self::THEME_DIST_DIRNAME )
                    continue;

                if( !empty( $theme_name )
                and ($language_dirs = PHS::get_theme_language_paths( $theme_name ))
                and !empty( $language_dirs['path'] ) )
                {
                    // Add index to theme
                    $lang_file = $language_dirs['path'].$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                if( !empty( $plugin_name )
                and ($instance_dirs = PHS_Instantiable::get_instance_details( 'PHS_Plugin_'.ucfirst( $plugin_name ), $plugin_name, PHS_Instantiable::INSTANCE_TYPE_PLUGIN ))
                and !empty( $instance_dirs['plugin_paths'] ) and is_array( $instance_dirs['plugin_paths'] )
                and !empty( $instance_dirs['plugin_paths'][PHS_Instantiable::LANGUAGES_DIR] ) )
                {
                    // Add index to plugin
                    $lang_file = $instance_dirs['plugin_paths'][PHS_Instantiable::LANGUAGES_DIR].$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                if( empty( $theme_name ) and empty( $plugin_name ) )
                {
                    // Add to generic language file
                    $lang_file = $language_directory.$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                $this->indexes_count++;
            }
        }

        return $this->get_parsed_indexes();
    }

    public function extract_po_translation()
    {
        if( empty( $this->header_arr ) )
            $this->extract_header();

        return $this->extract_po_unit();
    }

    public function get_po_header()
    {
        static $lower_header_arr = false;

        if( empty( $this->header_arr ) )
            $this->extract_header();

        if( $lower_header_arr === false )
        {
            $lower_header_arr = array();
            foreach( $this->header_arr as $key => $val )
                $lower_header_arr[strtolower($key)] = $val;
        }

        return array(
            'header_lines' => $this->header_lines,
            'header_arr' => $this->header_arr,
            'lower_header_arr' => $lower_header_arr,
        );
    }

    public function guess_language_from_header()
    {
        if( !($header_data = $this->get_po_header())
         or empty( $header_data['lower_header_arr'] )
         or empty( $header_data['lower_header_arr']['language'] ) )
            return false;

        $guessed_language = '';
        // Although BCP 47 itself only allows "-" as a separator; for compatibility, Unicode language identifiers allows both "-" and "_".
        // Implementations should also accept both.
        if( strstr( $header_data['lower_header_arr']['language'], '_' ) !== false )
        {
            if( ($header_val = explode( '_', $header_data['lower_header_arr']['language'], 2 ))
            and !empty( $header_val[0] ) )
                $guessed_language = strtolower( $header_val[0] );
        } elseif( strstr( $header_data['lower_header_arr']['language'], '-' ) !== false )
        {
            if( ($header_val = explode( '-', $header_data['lower_header_arr']['language'], 2 ))
            and !empty( $header_val[0] ) )
                $guessed_language = strtolower( $header_val[0] );
        } else
            $guessed_language = strtolower( $header_data['lower_header_arr']['language'] );

        $return_arr = array();
        $return_arr['guessed_language'] = $guessed_language;
        $return_arr['valid_language'] = self::valid_language( $guessed_language );

        return $return_arr;
    }

    private function extract_po_unit()
    {
        // read empty lines till first translation unit
        do
        {
            if( ($line_str = $this->get_line( $this->_li )) === false )
                break;

            $this->_li++;

        } while( $line_str === '' );

        if( $line_str === false )
            return false;

        $unit_arr = array();
        $unit_arr['index'] = '';
        $unit_arr['translation'] = '';
        $unit_arr['comment'] = '';
        $unit_arr['files'] = array();

        do
        {
            if( substr( $line_str, 0, 2 ) == '# ' )
            {
                // comment
                $unit_arr['comment'] = substr( $line_str, 2 );
            } elseif( substr( $line_str, 0, 3 ) == '#: ' )
            {
                // file...
                // POEdit (or PO format) has errors if file name contains spaces or :
                if( ($file_str = substr( $line_str, 3 ))
                and ($parts_arr = explode( ':', $file_str )) )
                {
                    $file = null;
                    $line = null;
                    foreach( $parts_arr as $file_data )
                    {
                        $file_line = explode( ' ', $file_data );

                        if( $file === null )
                        {
                            $file = trim( $file_line[0] );
                            continue;
                        }

                        $line = intval( $file_line[0] );

                        if( $file !== false and $line !== false )
                        {
                            $unit_arr['files'][] = array(
                                'file' => $file,
                                'line' => $line,
                            );

                            $file = false;
                            $line = false;
                        }

                        if( !empty( $file_line[1] ) )
                            $file = trim( $file_line[1] );
                    }
                }
            } elseif( substr( $line_str, 0, 6 ) == 'msgid ' )
            {
                $msgid = trim( substr( $line_str, 6 ), '"' );
                while( ($next_line_str = $this->get_line( $this->_li )) )
                {
                    if( substr( $next_line_str, 0, 1 ) != '"' )
                        break;

                    $msgid .= trim( $next_line_str, '"' );
                    $this->_li++;
                }

                $unit_arr['index'] = $msgid;
            } elseif( substr( $line_str, 0, 7 ) == 'msgstr ' )
            {
                $msgstr = trim( substr( $line_str, 7 ), '"' );
                while( ($next_line_str = $this->get_line( $this->_li )) )
                {
                    if( substr( $next_line_str, 0, 1 ) != '"' )
                        break;

                    $msgstr .= trim( $next_line_str, '"' );
                    $this->_li++;
                }

                $unit_arr['translation'] = $msgstr;
            }

            $line_str = $this->get_line( $this->_li );
            $this->_li++;
        } while( $line_str );

        return $unit_arr;
    }

    private function reset_lines_arr()
    {
        $this->lines_arr = array();
        $this->_li = 0;

        $this->header_arr = array();
        $this->header_lines = 0;
    }

    private function get_lines( $buffer = false )
    {
        if( $buffer === false )
            return $this->lines_arr;

        $this->reset_error();

        if( !is_string( $buffer ) )
        {
            $this->set_error( self::ERR_INPUT_BUFFER, self::_t( 'Invalid input buffer.' ) );
            return false;
        }

        $this->reset_lines_arr();
        if( !($this->lines_arr = explode( "\n", $buffer )) )
        {
            $this->lines_arr = array();
            return false;
        }

        return true;
    }

    /**
     * @param int $index Line index if we use full buffer of po file. If we use generators function will yield next line in file
     * @return bool|string
     */
    private function get_line( $index )
    {
        if( !isset( $this->lines_arr[$index] ) )
            return false;

        return $this->lines_arr[$index];
    }

    private function extract_header()
    {
        $this->_li = 0;
        $this->header_lines = 0;

        while( ($line_str = $this->get_line( $this->_li )) )
        {
            $line_str = trim( $line_str );

            $this->_li++;

            if( substr( $line_str, 0, 5 ) == 'msgid'
             or substr( $line_str, 0, 6 ) == 'msgstr'
             or substr( $line_str, 0, 1 ) == '#' )
                continue;

            if( $line_str === ''
             or substr( $line_str, 0, 2 ) == '#:'
             or substr( $line_str, 0, 1 ) != '"' )
                break;

            $line_str = trim( $line_str, '"' );

            while( substr( $line_str, -2 ) != '\\n' )
            {
                if( ($next_line = $this->get_line( $this->_li )) === false
                 or $next_line == '' )
                    break 2;

                $this->_li++;

                $next_line = trim( $next_line, '"' );

                $line_str .= $next_line;
            }

            if( !($header_vals = explode( ':', $line_str, 2 ))
             or empty( $header_vals[0] ) or empty( $header_vals[1] ) )
                continue;

            $header_val = trim( $header_vals[1] );
                $header_val = substr( $header_val, 0, -2 );

            $this->header_arr[trim($header_vals[0])] = $header_val;
        }

        $this->header_lines = $this->_li;
    }
}
