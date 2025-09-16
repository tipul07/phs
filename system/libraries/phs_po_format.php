<?php
namespace phs\libraries;

use phs\PHS;

class PHS_Po_format extends PHS_Registry
{
    public const ERR_PO_FILE = 1, ERR_INPUT_BUFFER = 2, ERR_LANGUAGE_FILE = 3, ERR_EXPORT = 4;

    public const PLUGINS_DIST_DIRNAME = 'plugins.dist', THEME_DIST_DIRNAME = 'default.dist';

    public const WRAP_LINE_CHARS = 79;

    private string $filename = '';

    private array $lines_arr = [];

    private int $_li = 0;

    private int $header_lines = 0;

    // Header values as they are defined in PO file
    private array $header_arr = [];

    // After parsing a PO file class caches results here...
    private string $parsed_language = '';

    private string $base_path = '';

    private array $po_units = [];

    private array $parsed_indexes = [];

    private int $indexes_count = 0;

    private int $translations_count = 0;

    private static array $pot_ignore_list = [
        'vendor', 'tests/behat', 'tests/phpunit', 'themes/default.dist', 'system/logs',
        'plugins/accounts_3rd/libraries/google', 'plugins/phs_libs/libraries/qrcode',
        'plugins/sendgrid/libraries/sendgrid',
    ];

    public function set_filename(string $f) : bool
    {
        $this->reset_error();

        if (!$f
            || !@file_exists($f)
            || !@is_readable($f)) {
            $this->set_error(self::ERR_PO_FILE, self::_t('PO file not found or not readable.'));

            return false;
        }

        if (!($buf = @file_get_contents($f))) {
            $this->set_error(self::ERR_PO_FILE, self::_t('Couldn\'t read PO file or it is empty.'));

            return false;
        }

        $this->filename = $f;

        return $this->set_buffer($buf);
    }

    public function set_buffer(string $b) : bool
    {
        $this->_reset_lines_arr();

        return (bool)$this->_get_lines($b);
    }

    public function get_parsed_indexes() : array
    {
        return [
            'count'              => $this->indexes_count,
            'translations_count' => $this->translations_count,
            'language'           => $this->parsed_language,
            'language_files'     => $this->parsed_indexes ? array_keys($this->parsed_indexes) : [],
            'indexes'            => $this->parsed_indexes,
        ];
    }

    public function get_po_units() : array
    {
        return $this->po_units;
    }

    public function get_translation_existing_files() : array
    {
        $files_arr = [];

        @clearstatcache();
        $file = self::get_default_pot_file();
        $files_arr['pot_file'] = [
            'file'     => $file,
            'modified' => @filemtime($file) ?: 0,
            'size'     => @filesize($file) ?: 0,
        ];

        $file = self::get_filename_with_files_list_for_pot_file();
        $files_arr['pot_list'] = [
            'file'     => $file,
            'modified' => @filemtime($file) ?: 0,
            'size'     => @filesize($file) ?: 0,
        ];

        $files_arr['languages'] = [];
        if (($languages_arr = self::get_defined_languages())) {
            foreach ($languages_arr as $lang => $lang_arr) {
                if (!($po_file = self::get_po_filepath_by_language($lang))) {
                    continue;
                }

                $files_arr['languages'][] = [
                    'lang'     => $lang,
                    'file'     => $po_file,
                    'modified' => @filemtime($po_file) ?: 0,
                    'size'     => @filesize($po_file) ?: 0,
                ];
            }
        }

        return $files_arr;
    }

    public function validate_filename(string $filename) : ?string
    {
        return str_replace(['/', '\\', '.'], '', $filename);
    }

    public function generate_pot_file(string $pot_file = '', array $params = []) : bool
    {
        $this->reset_error();

        $params['regenerate_pot_files_list'] = !isset($params['regenerate_pot_files_list']) || !empty($params['regenerate_pot_files_list']);
        $params['overwrite_pot_file'] = !isset($params['overwrite_pot_file']) || !empty($params['overwrite_pot_file']);
        $params['xgettext_bin'] ??= '';

        $pot_file = !$pot_file
            ? self::get_default_pot_file()
            : LANG_PO_DIR.$this->validate_filename($pot_file).'.pot';

        $pot_exists = @file_exists($pot_file);
        if ($pot_exists && (!$params['overwrite_pot_file'] || !@is_writable($pot_file))) {
            $this->set_error(self::ERR_PARAMETERS,
                'POT file already exists or is not writable: '.$pot_file.'.');

            return false;
        }

        $pot_files_list = self::get_filename_with_files_list_for_pot_file();
        if ($params['regenerate_pot_files_list']
           || !@file_exists($pot_files_list)) {
            if (!self::_generate_files_list_for_pot_file($pot_files_list)) {
                $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, 'Error generating files list for POT file.');

                return false;
            }
        }

        @clearstatcache();
        $command_str = self::_get_xgettext_command($pot_file, $params['xgettext_bin']);
        if (false === @system($command_str)
           || !@file_exists($pot_file)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                'Error generating POT file with xgettext command: '.$command_str.'.');

            return false;
        }

        return true;
    }

    public function refresh_po_file_from_pot(string $lang, string $pot_file = '', array $params = []) : bool
    {
        $this->reset_error();

        $params['msgmerge_bin'] ??= '';

        if (!self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, 'Language is invalid.');

            return false;
        }

        if (!$this->generate_pot_file($pot_file, $params)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                'Error generating POT file.');

            return false;
        }

        $pot_file = !$pot_file
            ? self::get_default_pot_file()
            : LANG_PO_DIR.$this->validate_filename($pot_file).'.pot';

        $old_po_file = self::get_po_filepath_by_language($lang);
        $new_po_file = self::get_po_filepath_by_language($lang, 'new');
        if (!@file_exists($old_po_file)
           && (!($empty_po = $this->generate_empty_po_file($lang))
               || !@file_exists($empty_po['po_file']))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                'Cannot create empty PO file: '.$old_po_file.'. Check directory rights.');

            return false;
        }

        $command_str = self::_get_mergemsg_command($lang, $old_po_file, $new_po_file, $pot_file, $params['msgmerge_bin']);
        if (false === @system($command_str)
           || !@file_exists($new_po_file)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                'Error generating PO file with mergemsg command: '.$command_str.'.');

            return false;
        }

        @unlink($old_po_file);
        @rename($new_po_file, $old_po_file);

        return true;
    }

    public function generate_po_file_from_pot_file(string $lang, string $pot_file = '', array $params = []) : ?array
    {
        $this->reset_error();

        $params['overwrite_po_file'] = !empty($params['overwrite_po_file']);

        $pot_file = $pot_file ?: self::get_default_pot_file();
        if (!@file_exists($pot_file)
            || !@is_readable($pot_file)) {
            $this->set_error(self::ERR_PARAMETERS,
                'Translations POT file doesn\'t exist or is not readable: '.$pot_file.'.');

            return null;
        }

        $po_file = self::get_po_filepath_by_language($lang);
        $po_dir = dirname($po_file);
        if (!@is_dir($po_dir)
            || !@is_writable($po_dir)) {
            $this->set_error(self::ERR_PARAMETERS,
                'Translations PO files directory is not writable: '.$po_file.'.');

            return null;
        }

        $po_exists = @file_exists($po_file);
        if ($po_exists && (!$params['overwrite_po_file'] || !@is_writable($po_file))) {
            $this->set_error(self::ERR_PARAMETERS,
                'PO file already exists or is not writable: '.$po_file.'.');

            return null;
        }

        if (!($fil = @fopen($po_file, 'wb'))) {
            $this->set_error(self::ERR_PARAMETERS, 'Cannot open PO file for write '.$po_file.'.');

            return null;
        }

        if (!@fwrite($fil, self::_generate_po_headers_as_po_string($lang))) {
            $this->set_error(self::ERR_PARAMETERS, 'Cannot write PO file headers to '.$po_file.'.');

            @fclose($fil);

            return null;
        }

        @fflush($fil);
        @fclose($fil);

        return [
            'ok' => true,
        ];
    }

    public function generate_empty_po_file(string $lang, string $pot_file = '', array $params = []) : ?array
    {
        $this->reset_error();

        $params['overwrite_po_file'] = !empty($params['overwrite_po_file']);

        $pot_file = $pot_file ?: self::get_default_pot_file();
        if (!@file_exists($pot_file)
           || !@is_readable($pot_file)) {
            $this->set_error(self::ERR_PARAMETERS,
                'Translations POT file doesn\'t exist or is not readable: '.$pot_file.'.');

            return null;
        }

        $po_file = self::get_po_filepath_by_language($lang);
        $po_dir = dirname($po_file);
        if (!@is_dir($po_dir)
           || !@is_writable($po_dir)) {
            $this->set_error(self::ERR_PARAMETERS,
                'Translations PO files directory is not writable: '.$po_file.'.');

            return null;
        }

        $po_exists = @file_exists($po_file);
        if ($po_exists && (!$params['overwrite_po_file'] || !@is_writable($po_file))) {
            $this->set_error(self::ERR_PARAMETERS,
                'PO file already exists or is not writable: '.$po_file.'.');

            return null;
        }

        if (!($fil = @fopen($po_file, 'wb'))) {
            $this->set_error(self::ERR_PARAMETERS, 'Cannot open PO file for write '.$po_file.'.');

            return null;
        }

        if (!@fwrite($fil, self::_generate_po_headers_as_po_string($lang))) {
            $this->set_error(self::ERR_PARAMETERS, 'Cannot write PO file headers to '.$po_file.'.');

            @fclose($fil);

            return null;
        }

        @fflush($fil);
        @fclose($fil);

        return [
            'po_file'  => $po_file,
            'pot_file' => $pot_file,
        ];
    }

    public function export_csv_from_po(string $po_file, array $params = []) : bool
    {
        if (!$this->set_filename($po_file)) {
            $this->set_error_if_not_set(self::ERR_PO_FILE, self::_t('Couldn\'t read PO file or it is empty.'));

            return false;
        }

        $params['language'] ??= null;
        $params['csv_line_delimiter'] = ($params['csv_line_delimiter'] ?? null) ?: "\n";
        $params['csv_column_delimiter'] = ($params['csv_column_delimiter'] ?? null) ?: ',';
        $params['csv_column_enclosure'] = ($params['csv_column_enclosure'] ?? null) ?: '"';
        $params['csv_column_escape'] = ($params['csv_column_escape'] ?? null) ?: '"';
        $params['export_to_output'] = !isset($params['export_to_output']) || !empty($params['export_to_output']);
        $params['export_to_filename'] ??= null;

        if ($params['export_to_filename']
            && (!is_string($params['export_to_filename'])
                || !($csv_dir = rtrim(@dirname($params['export_to_filename']), '/\\'))
                || !@is_dir($csv_dir)
                || !@is_writable($csv_dir)
            )) {
            $this->set_error(self::ERR_EXPORT,
                self::_t('Please provide a valid export csv filename and make sure directory is writable.'));

            return false;
        }

        $this->_reset_parsed_indexes();

        $this->parsed_language = $this->_validate_language_from_po($params['language']) ?: '';

        $csv_f = null;
        if ($params['export_to_filename']
            && !($csv_f = @fopen($params['export_to_filename'], 'wb'))) {
            $this->set_error(self::ERR_EXPORT, self::_t('Couldn\'t open export csv filename for writing.'));

            return false;
        }

        while (($translation_arr = $this->extract_po_translation())) {
            if (empty($translation_arr['index'])) {
                continue;
            }

            $csv_line = PHS_Utils::csv_line([$translation_arr['index'], $translation_arr['translation'] ?? ''],
                $params['csv_line_delimiter'], $params['csv_column_delimiter'],
                $params['csv_column_enclosure'], $params['csv_column_escape']);

            if ($csv_f !== null) {
                @fwrite($csv_f, $csv_line);
                @fflush($csv_f);
            } elseif ($params['export_to_output']) {
                echo $csv_line;
            }
        }

        if ($csv_f !== null) {
            @fflush($csv_f);
            @fclose($csv_f);
        }

        return true;
    }

    public function update_language_files(string $po_file, array $params = []) : ?array
    {
        if (empty($params['language'])) {
            $params['language'] = null;
        } elseif (!self::valid_language($params['language'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Language is invalid.'));

            return null;
        }

        $params['update_language_files'] = !empty($params['update_language_files']);
        $params['backup_language_files'] = !isset($params['backup_language_files']) || !empty($params['update_language_files']);
        $params['merge_with_old_files'] = !isset($params['merge_with_old_files']) || !empty($params['merge_with_old_files']);

        if (!$this->parse_details_from_po_file($po_file, $params)) {
            return null;
        }

        $return_arr = [];
        $return_arr['new_indexes'] = 0;
        $return_arr['updated_indexes'] = 0;
        $return_arr['updated_files'] = [];
        $return_arr['update_errors'] = [];

        if (!$this->indexes_count
            || !$this->parsed_indexes) {
            return $return_arr;
        }

        $csv_settings = self::lang_files_csv_settings() ?: self::default_lang_files_csv_settings();

        foreach ($this->parsed_indexes as $lang_file => $indexes_arr) {
            if (!is_array($indexes_arr)) {
                continue;
            }

            $new_indexes = 0;
            $updated_indexes = 0;
            if (!$params['merge_with_old_files']) {
                $resulting_arr = $indexes_arr;
                $new_indexes = count($indexes_arr);
            } else {
                if (!@file_exists($lang_file)
                    || !($existing_lines_arr = self::get_language_file_lines($lang_file, $this->parsed_language))) {
                    $existing_lines_arr = [];
                }

                // Language file exists... see what we can update / add
                $resulting_arr = $existing_lines_arr;
                foreach ($indexes_arr as $lang_key => $lang_val) {
                    if (!isset($resulting_arr[$lang_key])) {
                        $new_indexes++;
                    } elseif ($lang_val !== $resulting_arr[$lang_key]) {
                        $updated_indexes++;
                    } else {
                        // Same thing...
                        continue;
                    }

                    $resulting_arr[$lang_key] = $lang_val;
                }
            }

            if (!$new_indexes && !$updated_indexes
                && $resulting_arr) {
                continue;
            }

            $fp = null;
            if ($params['update_language_files']) {
                if ($params['backup_language_files']
                    && !$this->_backup_language_file($lang_file)) {
                    $error_msg = self::_t('Error creating backup for language file [%s]', $lang_file);

                    $return_arr['update_errors'][$lang_file] = $error_msg;

                    PHS_Logger::critical($error_msg, PHS_Logger::TYPE_MAINTENANCE);
                    continue;
                }

                if (!($fp = @fopen($lang_file, 'wb'))) {
                    $error_msg = self::_t('Error creating language file [%s]', $lang_file);

                    $return_arr['update_errors'][$lang_file] = $error_msg;

                    PHS_Logger::critical($error_msg, PHS_Logger::TYPE_MAINTENANCE);
                    continue;
                }

                if (($header_str = self::get_language_file_header_str())
                    && !@fwrite($fp, $header_str)) {
                    PHS_Logger::error('Error writing header for language file ['.$lang_file.']', PHS_Logger::TYPE_MAINTENANCE);
                }
            }

            if (!$new_indexes && !$updated_indexes) {
                if ($params['update_language_files']
                    && $fp) {
                    @fflush($fp);
                    @fclose($fp);
                }

                self::load_language_file($lang_file, $this->parsed_language, true);

                continue;
            }

            if ($params['update_language_files']) {
                $line = 1;
                foreach ($resulting_arr as $lang_key => $lang_val) {
                    if (!($csv_line = PHS_Utils::csv_line([$lang_key, $lang_val],
                        $csv_settings['line_delimiter'], $csv_settings['columns_delimiter'],
                        $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape'])
                    )
                     || !@fwrite($fp, $csv_line)) {
                        PHS_Logger::error('Error writing ['.$lang_key.'] language index at position ['.$line.']',
                            PHS_Logger::TYPE_MAINTENANCE);
                    }

                    $line++;
                }

                @fflush($fp);
                @fclose($fp);

                self::load_language_file($lang_file, $this->parsed_language, true);
            }

            $return_arr['new_indexes'] += $new_indexes;
            $return_arr['updated_indexes'] += $updated_indexes;

            $return_arr['updated_files'][] = $lang_file;
        }

        return $return_arr;
    }

    public function parse_details_from_po_file_by_language(string $lang, array $params = []) : bool
    {
        $this->reset_error();

        if (!$lang
           || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid language.'));

            return false;
        }

        $po_file = LANG_PO_DIR.$lang.'.po';
        if (!@file_exists($po_file)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('PO file doesn\'t exist.'));

            return false;
        }

        $params['language'] = $lang;

        return $this->parse_details_from_po_file($po_file, $params);
    }

    public function parse_details_from_po_file(string $po_file, array $params = []) : bool
    {
        $this->reset_error();

        if (!$po_file
            || !$this->set_filename($po_file)) {
            $this->set_error_if_not_set(self::ERR_PO_FILE, self::_t('Couldn\'t read PO file or it is empty.'));

            return false;
        }

        $params['language'] ??= $this->guess_language_from_header()['valid_language'] ?? null;

        if (!$params['language']
            || !($lang_details = $this->_validate_language_from_po($params['language']))) {
            $this->set_error(self::ERR_PO_FILE, self::_t('Language is invalid.'));

            return false;
        }

        if (empty($lang_details['dir'])
         || !@is_dir(rtrim($lang_details['dir'], '/'))) {
            $this->set_error(self::ERR_PO_FILE,
                self::_t('System language directory %s doesn\'t exist.',
                    (!empty($lang_details['dir']) ? $lang_details['dir'] : 'N/A')));

            return false;
        }

        $this->_reset_parsed_indexes();

        $this->parsed_language = $params['language'];

        $language_directory = $lang_details['dir'];

        $plugins_relative_path = PHS::relative_path(PHS_PLUGINS_DIR);
        $plugins_relative_path_len = strlen($plugins_relative_path);
        $plugins_dist_dirname_len = strlen(self::PLUGINS_DIST_DIRNAME);

        $themes_relative_path = PHS::relative_path(PHS_THEMES_DIR);
        $themes_relative_path_len = strlen($themes_relative_path);

        while (($translation_arr = $this->extract_po_translation())) {
            if (empty($translation_arr['index'])
             || empty($translation_arr['files'])
             || !is_array($translation_arr['files'])) {
                continue;
            }

            $translation_arr['translation'] ??= '';

            if (!$this->_add_po_unit_from_array($translation_arr)) {
                $this->set_error_if_not_set(self::ERR_PO_FILE, self::_t('Error adding PO unit from array.'));

                return false;
            }

            if ($translation_arr['translation'] !== '') {
                $this->translations_count++;
            }

            foreach ($translation_arr['files'] as $lang_file_arr) {
                if (empty($lang_file_arr['file'])
                    || str_starts_with($lang_file_arr['file'], self::PLUGINS_DIST_DIRNAME)) {
                    continue;
                }

                $plugin_name = '';
                $theme_name = '';
                if (str_starts_with($lang_file_arr['file'], $plugins_relative_path)
                    && ($path_rest = substr($lang_file_arr['file'], $plugins_relative_path_len))
                    && ($path_rest_arr = explode('/', $path_rest, 2))
                    && !empty($path_rest_arr[0])) {
                    $plugin_name = $path_rest_arr[0];
                }

                if (str_starts_with($lang_file_arr['file'], $themes_relative_path)
                    && ($path_rest = substr($lang_file_arr['file'], $themes_relative_path_len))
                    && ($path_rest_arr = explode('/', $path_rest, 2))
                    && !empty($path_rest_arr[0])) {
                    $theme_name = $path_rest_arr[0];
                }

                if ($theme_name === self::THEME_DIST_DIRNAME) {
                    continue;
                }

                if ($theme_name
                    && ($language_dirs = PHS::get_theme_language_paths($theme_name))
                    && !empty($language_dirs['path'])) {
                    // Add index to theme
                    $lang_file = $language_dirs['path'].$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                if ($plugin_name
                    && ($instance_dirs = PHS_Instantiable::get_instance_details(
                        'PHS_Plugin_'.ucfirst($plugin_name), $plugin_name, PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                    ))
                    && !empty($instance_dirs['plugin_paths'][PHS_Instantiable::LANGUAGES_DIR])) {
                    // Add index to plugin
                    $lang_file = $instance_dirs['plugin_paths'][PHS_Instantiable::LANGUAGES_DIR].$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                if (!$theme_name && !$plugin_name) {
                    // Add to generic language file
                    $lang_file = $language_directory.$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                $this->indexes_count++;
            }
        }

        return true;
    }

    public function extract_po_translation() : ?array
    {
        if (!$this->header_arr) {
            $this->_extract_headers();
        }

        return $this->_extract_po_unit();
    }

    public function get_po_header(?string $key = null) : null | string | array
    {
        static $lower_header_arr = null;

        if (!$this->header_arr) {
            $this->_extract_headers();
        }

        if ($lower_header_arr === null) {
            $lower_header_arr = [];
            foreach ($this->header_arr as $k => $v) {
                $lower_header_arr[strtolower($k)] = $v;
            }
        }

        if ($key !== null) {
            if (isset($lower_header_arr[strtolower($key)])) {
                return $lower_header_arr[$key];
            }

            return null;
        }

        return [
            'header_lines'     => $this->header_lines,
            'header_arr'       => $this->header_arr,
            'lower_header_arr' => $lower_header_arr,
        ];
    }

    public function guess_language_from_header() : ?array
    {
        if (!($language = $this->get_po_header('language'))) {
            return null;
        }

        $guessed_language = '';
        // Although BCP 47 itself only allows "-" as a separator; for compatibility, Unicode language identifiers allows both "-" and "_".
        // Implementations should also accept both.
        if (str_contains($language, '_')) {
            if (($header_val = explode('_', $language, 2))
                && !empty($header_val[0])) {
                $guessed_language = strtolower($header_val[0]);
            }
        } elseif (str_contains($language, '-')) {
            if (($header_val = explode('-', $language, 2))
                && !empty($header_val[0])) {
                $guessed_language = strtolower($header_val[0]);
            }
        } else {
            $guessed_language = strtolower($language);
        }

        $return_arr = [];
        $return_arr['guessed_language'] = $guessed_language;
        $return_arr['valid_language'] = self::valid_language($guessed_language);

        return $return_arr;
    }

    public function add_translation_for_po_unit(int $po_index, string $translation) : bool
    {
        if (!($this->po_units[$po_index]['index'] ?? null)) {
            return false;
        }

        $this->po_units[$po_index]['translation'] = $translation;

        return true;
    }

    public function write_po_units_to_file(string $file_name, bool $force = false) : bool
    {
        $this->reset_error();

        if (!($file_name = $this->validate_filename($file_name))
            || !($full_file = LANG_PO_DIR.$file_name.'.po')
            || (!$force && @file_exists($full_file))
            || !($file_handle = @fopen($full_file, 'wb'))) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, 'Couldn\'t open PO file for writing.');

            return false;
        }

        if (!($header_str = self::_generate_po_headers_as_po_string($this->parsed_language))
            || !@fwrite($file_handle, $header_str)) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, 'Couldn\'t write PO file headers to file.');

            @fclose($file_handle);

            return false;
        }

        @fflush($file_handle);

        if (empty($this->po_units)
            || !is_array($this->po_units)) {
            @fclose($file_handle);

            return true;
        }

        foreach ($this->po_units as $po_unit) {
            if (!$this->write_po_unit_to_file_handle($po_unit, $file_handle)) {
                $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, 'Couldn\'t write PO unit to file.');

                @fclose($file_handle);

                return false;
            }
        }

        @fflush($file_handle);
        @fclose($file_handle);

        return true;
    }

    public function write_po_unit_to_file_handle(array $po_unit, $file_handle) : bool
    {
        if (!($po_string = $this->get_po_unit_as_string($po_unit))
            || !is_resource($file_handle)
            || !@fwrite($file_handle, $po_string)) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, 'Couldn\'t write PO unit to file handler.');

            return false;
        }

        @fflush($file_handle);

        return true;
    }

    public function get_po_unit_as_string(array $po_unit) : ?string
    {
        $this->reset_error();

        if (!($po_unit['index'] ?? null)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('PO unit index is required.'));

            return null;
        }

        $return_str = $this->_get_po_comments_as_string($po_unit['comments'] ?? []);
        $return_str .= $this->_get_po_files_as_string($po_unit['files'] ?? []);
        $return_str .= self::_get_po_formatted_string($po_unit['index'], 'msgid', true)."\n";
        $return_str .= self::_get_po_formatted_string($po_unit['translation'] ?? '', 'msgstr', true)."\n\n";

        return $return_str;
    }

    private function _get_po_comments_as_string(array $comments) : string
    {
        $comment_str = '';
        foreach ($comments as $comment) {
            if ($comment === '') {
                continue;
            }

            $comment_str .= '# '.$comment."\n";
        }

        return $comment_str !== '' ? $comment_str."\n" : '';
    }

    private function _get_po_files_as_string(array $files) : string
    {
        $files_str = '';
        $line_str = '';
        foreach ($files as $file_arr) {
            if (!($file_arr['file'] ?? null)
                || !($file_arr['line'] ?? null)) {
                continue;
            }

            $fstr = $file_arr['file'].':'.$file_arr['line'];

            if ($line_str === '') {
                $line_str = '#:';
            } elseif (mb_strlen($line_str.' '.$fstr) > self::WRAP_LINE_CHARS) {
                $files_str .= $line_str."\n";
                $line_str = '#:';
            }

            $line_str .= ' '.$fstr;
        }

        $files_str .= $line_str;

        return $files_str !== '' ? $files_str."\n" : '';
    }

    private function _backup_language_file(string $lang_file) : ?array
    {
        $this->reset_error();

        $return_arr = [];
        $return_arr['backup_created'] = false;
        $return_arr['path'] = '';
        $return_arr['file_name'] = '';
        $return_arr['full_name'] = '';

        if (empty($lang_file)
         || !@file_exists($lang_file)) {
            return $return_arr;
        }

        if (!($path_info = PHS_Utils::mypathinfo($lang_file))
         || empty($path_info['extension']) || $path_info['extension'] !== 'csv'
         || empty($path_info['basename']) || !self::valid_language($path_info['basename'])
         || $lang_file === PHS::relative_path($path_info['dirname'])) {
            $this->set_error(self::ERR_LANGUAGE_FILE, self::_t('Couldn\'t get details about language file or language file is invalid.'));

            return null;
        }

        if (!@is_dir($path_info['dirname'])
         || !@is_writable($path_info['dirname'])) {
            $this->set_error(self::ERR_LANGUAGE_FILE, self::_t('Destination directory is not writable. Please check write rights.'));

            return null;
        }

        $backup_file_name = $path_info['basename'].'_bk'.date('YmdHis').'.csv';

        if (($bk_files = @glob($path_info['dirname'].'/'.$path_info['basename'].'_bk*.csv'))) {
            foreach ($bk_files as $bk_file) {
                @unlink($bk_file);
            }
        }

        $return_arr['backup_created'] = true;
        $return_arr['path'] = $path_info['dirname'];
        $return_arr['file_name'] = $backup_file_name;
        $return_arr['full_name'] = $path_info['dirname'].'/'.$backup_file_name;

        if (!@copy($lang_file, $return_arr['full_name'])) {
            $this->set_error(self::ERR_LANGUAGE_FILE, self::_t('Error creating backup file in destination directory. Please check write rights.'));

            return null;
        }

        return $return_arr;
    }

    private function _reset_parsed_indexes() : void
    {
        $this->indexes_count = 0;
        $this->parsed_language = '';
        $this->parsed_indexes = [];
        $this->po_units = [];
    }

    private function _add_po_unit_from_array(array $po_unit) : bool
    {
        if (!$po_unit
        || empty($po_unit['index'])) {
            return false;
        }

        $po_unit['comments'] ??= [];
        $po_unit['files'] ??= [];
        $po_unit['translation'] ??= '';

        $this->po_units[] = $po_unit;

        return true;
    }

    private function _get_empty_po_unit() : array
    {
        return [
            'index'       => '',
            'translation' => '',
            'comments'    => [],
            'files'       => [],
        ];
    }

    private function _extract_po_unit() : ?array
    {
        // read empty lines till first translation unit
        do {
            if (($line_str = $this->get_line($this->_li)) === null) {
                break;
            }

            $this->_li++;
        } while ($line_str === '');

        if ($line_str === null) {
            return null;
        }

        $unit_arr = $this->_get_empty_po_unit();
        do {
            if (($comment_str = $this->_parse_comments_line($line_str))) {
                $unit_arr['comments'][] = $comment_str;
            } elseif (($files_arr = $this->_parse_files_line($line_str))) {
                $unit_arr['files'] = array_merge($unit_arr['files'], $files_arr);
            } elseif (($msgid = $this->_parse_msgid_line($line_str))) {
                $unit_arr['index'] = $msgid;
            } elseif (($msgstr = $this->_parse_msgstr_line($line_str))) {
                $unit_arr['translation'] = $msgstr;
            }

            $line_str = $this->get_line($this->_li);
            $this->_li++;
        } while ($line_str);

        return $unit_arr;
    }

    private function _parse_comments_line(string $line_str) : ?string
    {
        if (!str_starts_with($line_str, '# ')) {
            return null;
        }

        return substr($line_str, 2);
    }

    private function _parse_files_line(string $line_str) : ?array
    {
        if (!str_starts_with($line_str, '#: ')) {
            return null;
        }

        // POEdit (or PO format) has errors if file name contains spaces or :
        if (!($file_str = substr($line_str, 3))
            || !($parts_arr = explode(':', $file_str))) {
            return [];
        }

        $files_arr = [];
        $file = null;
        foreach ($parts_arr as $file_data) {
            $file_line = explode(' ', $file_data);

            if ($file === null) {
                $file = trim($file_line[0]);
                continue;
            }

            $line = (int)$file_line[0];

            if ($file !== false && $line) {
                $files_arr[] = [
                    'file' => $file,
                    'line' => $line,
                ];

                $file = false;
            }

            if (!empty($file_line[1])) {
                $file = trim($file_line[1]);
            }
        }

        return $files_arr;
    }

    private function _parse_msgid_line(string $line_str) : ?string
    {
        if (!str_starts_with($line_str, 'msgid ')) {
            return null;
        }

        $msgid = trim(substr($line_str, 6), '"');
        while (($next_line_str = $this->get_line($this->_li))) {
            if (!str_starts_with($next_line_str, '"')) {
                break;
            }

            $msgid .= trim($next_line_str, '"');
            $this->_li++;
        }

        return $msgid;
    }

    private function _parse_msgstr_line(string $line_str) : ?string
    {
        if (!str_starts_with($line_str, 'msgstr ')) {
            return null;
        }

        $msgstr = trim(substr($line_str, 7), '"');
        while (($next_line_str = $this->get_line($this->_li))) {
            if (!str_starts_with($next_line_str, '"')) {
                break;
            }

            $msgstr .= trim($next_line_str, '"');
            $this->_li++;
        }

        return $msgstr;
    }

    private function _reset_lines_arr() : void
    {
        $this->lines_arr = [];
        $this->_li = 0;

        $this->header_arr = [];
        $this->header_lines = 0;

        $this->_reset_parsed_indexes();
    }

    private function _get_lines(?string $buffer = null) : bool | array
    {
        if ($buffer === null) {
            return $this->lines_arr;
        }

        $this->reset_error();

        if (!$buffer) {
            $this->set_error(self::ERR_INPUT_BUFFER, self::_t('Invalid input buffer.'));

            return false;
        }

        $this->_reset_lines_arr();
        if (!($this->lines_arr = explode("\n", $buffer))) {
            $this->lines_arr = [];

            return false;
        }

        foreach ($this->lines_arr as $knti => $line) {
            $this->lines_arr[$knti] = trim($line);
        }

        return true;
    }

    /**
     * @param int $index Line index if we use full buffer of po file. If we use generators function will yield next line in file
     *
     * @return null|string
     */
    private function get_line(int $index) : ?string
    {
        return $this->lines_arr[$index] ?? null;
    }

    private function _validate_language_from_po(?string $provided_language = null) : ?array
    {
        if (!$provided_language
            && ($guessed_language = $this->guess_language_from_header())) {
            $provided_language = $guessed_language['guessed_language'] ?? null;
        }

        if (!$provided_language
            || !($language_details = self::get_defined_language($provided_language))) {
            return null;
        }

        return $language_details;
    }

    private function _extract_headers() : void
    {
        $this->_li = 0;
        $this->header_lines = 0;

        while (($line_str = $this->get_line($this->_li))) {
            $this->_li++;

            if (str_starts_with($line_str, 'msgid')
                || str_starts_with($line_str, 'msgstr')
                || str_starts_with($line_str, '#')) {
                continue;
            }

            if ($line_str === ''
                || str_starts_with($line_str, '#:')
                || !str_starts_with($line_str, '"')) {
                break;
            }

            $line_str = trim($line_str, '"');

            while (!str_ends_with($line_str, '\\n')) {
                if (($next_line = $this->get_line($this->_li)) === null
                    || $next_line === '') {
                    break 2;
                }

                $this->_li++;

                $next_line = trim($next_line, '"');

                $line_str .= $next_line;
            }

            if (!($header_vals = explode(':', $line_str, 2))
                || empty($header_vals[0]) || !isset($header_vals[1])) {
                continue;
            }

            $header_val = trim($header_vals[1]);
            $header_val = substr($header_val, 0, -2);

            $this->header_arr[trim($header_vals[0])] = $header_val;
        }

        $this->header_lines = $this->_li;
    }

    public static function get_default_pot_file() : string
    {
        return LANG_PO_DIR.'project.pot';
    }

    public static function get_filename_with_files_list_for_pot_file() : string
    {
        return LANG_PO_DIR.'potfiles.txt';
    }

    public static function get_po_filepath_by_language(string $lang, string $prefix = '') : ?string
    {
        if (!($lang = self::valid_language($lang))) {
            return null;
        }

        return LANG_PO_DIR.$prefix.$lang.'.po';
    }

    public static function add_to_ignored_directories_for_pot_list(array $ignore_dirs) : void
    {
        self::$pot_ignore_list = array_merge(self::$pot_ignore_list, $ignore_dirs);
    }

    private static function _generate_files_list_for_pot_file(string $filename) : bool
    {
        self::st_reset_error();

        $pot_files_list = $filename ?: self::get_filename_with_files_list_for_pot_file();
        if (!($fil = @fopen($pot_files_list, 'wb'))) {
            self::st_set_error(self::ERR_PARAMETERS,
                'Cannot open POT files list for writing: '.$pot_files_list.'.');

            return false;
        }

        if (null === ($files_arr = self::_get_php_files_from_dir(PHS_PATH, false))) {
            @fclose($fil);

            return false;
        }

        if ($files_arr) {
            @fwrite($fil, implode("\n", $files_arr)."\n");
            @fflush($fil);
        }

        foreach (self::_get_root_directories_for_pot_list() as $dir) {
            if (null === ($files_arr = self::_get_php_files_from_dir(PHS_PATH.$dir))) {
                @fclose($fil);

                return false;
            }

            @fwrite($fil, implode("\n", $files_arr)."\n");
            @fflush($fil);
        }

        @fclose($fil);

        return true;
    }

    private static function _get_php_files_from_dir(string $dir, bool $recursive = true) : ?array
    {
        if (!@is_dir($dir)) {
            self::st_set_error(self::ERR_PARAMETERS, 'Directory not found: '.$dir.'.');

            return null;
        }

        $files_arr = [];
        if (!($dir_handle = @opendir($dir))) {
            self::st_set_error(self::ERR_PARAMETERS, 'Cannot open directory: '.$dir.'.');

            return null;
        }

        while (($file = @readdir($dir_handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $full_path = rtrim($dir, '/\\').'/'.$file;
            if (@is_file($full_path) && @is_readable($full_path)
                && str_ends_with($file, '.php')) {
                $files_arr[] = PHS::relative_path($full_path);
            } elseif ($recursive
                      && @is_dir($full_path)
                      && !self::_should_ignore_directory($full_path)
                      && ($sub_files_arr = self::_get_php_files_from_dir($full_path))) {
                $files_arr = array_merge($files_arr, $sub_files_arr);
            }
        }

        @closedir($dir_handle);

        return $files_arr;
    }

    private static function _get_root_directories_for_pot_list() : array
    {
        return ['_setup', 'bin', 'config', 'graphql', 'plugins', 'system', 'tests', 'themes'];
    }

    private static function _should_ignore_directory(string $dir) : bool
    {
        if (PHS::running_on_windows()) {
            $dir = str_replace('\\', '/', $dir);
        }

        $ignored_dirs = self::_get_ignored_directories_for_pot_list();
        foreach ($ignored_dirs as $ignored_dir) {
            if (str_contains($dir, $ignored_dir)) {
                return true;
            }
        }

        return false;
    }

    private static function _get_ignored_directories_for_pot_list() : array
    {
        return self::$pot_ignore_list;
    }

    private static function _generate_po_headers_as_po_string(string $language = '') : string
    {
        $result_str = 'msgid ""'."\n"
                      .'msgstr ""'."\n";

        $headers_arr = self::_generate_po_headers_as_array($language);
        foreach ($headers_arr as $key => $val) {
            $result_str .= self::_get_po_formatted_string($key.': '.$val)."\n";
        }

        $result_str .= "\n";

        return $result_str;
    }

    private static function _get_po_formatted_string(string $str, string $prefix = '', bool $prefix_multiline = false) : string
    {
        if ($prefix !== '') {
            $prefix .= ' ';
        }

        if (!$str || mb_strlen($str) <= self::WRAP_LINE_CHARS) {
            return $prefix.'"'.$str.'"';
        }

        $result_str = '';
        $line_str = '';
        $words_arr = explode(' ', $str);
        foreach ($words_arr as $word) {
            if (mb_strlen($line_str.' '.$word) > self::WRAP_LINE_CHARS) {
                $result_str .= ($result_str !== '' ? "\n" : '').'"'.$line_str.'"';
                $line_str = '';
            }

            $line_str .= $word.' ';
        }

        $result_str .= ($result_str !== '' ? "\n" : '').'"'.rtrim($line_str).'"';

        return $prefix.($prefix_multiline ? '""'."\n" : '').$result_str;
    }

    private static function _generate_po_headers_as_array(string $language = '') : array
    {
        // lines end with \n as string, not as EOL
        $headers_arr = [];
        $headers_arr['Project-Id-Version'] = PHS_SITE_NAME.' '.PHS_SITEBUILD_VERSION.'\n';
        $headers_arr['Report-Msgid-Bugs-To'] = PHS_CONTACT_EMAIL.'\n';
        $headers_arr['POT-Creation-Date'] = date('Y-m-d H:iO').'\n';
        $headers_arr['PO-Revision-Date'] = date('Y-m-d H:iO').'\n';
        $headers_arr['Last-Translator'] = PHS_SITE_NAME.' Team <'.PHS_CONTACT_EMAIL.'>\n';
        $headers_arr['Language-Team'] = PHS_SITE_NAME.' Team <'.PHS_CONTACT_EMAIL.'>\n';
        $headers_arr['Language'] = $language.'\n';
        $headers_arr['MIME-Version'] = '1.0\n';
        $headers_arr['Content-Type'] = 'text/plain; charset=UTF-8\n';
        $headers_arr['Content-Transfer-Encoding'] = '8bit\n';
        $headers_arr['X-PHS-Version'] = PHS_KNOWN_VERSION.'\n';
        $headers_arr['X-Generator'] = 'PHS '.PHS_KNOWN_VERSION.'\n';
        $headers_arr['X-Poedit-SourceCharset'] = 'UTF-8\n';
        $headers_arr['X-Poedit-KeywordsList'] = implode(';', self::_language_translation_methods()).'\n';
        $headers_arr['X-Poedit-Basepath'] = '.\n';
        $headers_arr['X-Poedit-SearchPath-0'] = '.\n';

        return $headers_arr;
    }

    private static function _language_translation_methods() : array
    {
        return ['_pt', '_t', '_pte', 'st_pt', '_te', '_tl'];
    }

    private static function _get_xgettext_command(string $pot_filename, string $xgettext_bin = '') : string
    {
        $xgettext_bin = self::_get_xgettext_bin($xgettext_bin);

        $k_str = '';
        foreach (self::_language_translation_methods() as $method) {
            $k_str .= ' -k'.$method;
        }

        return $xgettext_bin.' -o '.$pot_filename.' -L PHP --from-code=UTF-8 --force-po'
               .' --package-name="'.PHS_SITE_NAME.'" --package-version="'.PHS_SITEBUILD_VERSION.'"'
               .' -D "'.PHS_PATH.'"'
               .$k_str
               .' -f "'.self::get_filename_with_files_list_for_pot_file().'"';
    }

    private static function _get_mergemsg_command(string $lang, string $old_po_file, string $new_po_file, string $pot_filename, string $mergemsg_bin = '') : string
    {
        return self::_get_msgmerge_bin($mergemsg_bin).' -o '.$new_po_file
               .' -D "'.PHS_PATH.'" --lang='.$lang.' --previous --no-fuzzy-matching --force-po --quiet'
               .' "'.$old_po_file.'" "'.$pot_filename.'"';
    }

    private static function _get_xgettext_bin(string $bin = '') : string
    {
        return self::_get_generic_bin_path('xgettext', $bin);
    }

    private static function _get_msgmerge_bin(string $bin = '') : string
    {
        return self::_get_generic_bin_path('msgmerge', $bin);
    }

    private static function _get_generic_bin_path(string $bin_name, string $provided_bin = '') : string
    {
        @ob_start();
        $provided_bin = $provided_bin ?: @system('which '.$bin_name) ?: $bin_name;
        @ob_get_clean();

        return $provided_bin;
    }
}
