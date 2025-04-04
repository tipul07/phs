<?php
namespace phs\libraries;

use phs\PHS;
use phs\libraries\PHS_Utils;

class PHS_Po_format extends PHS_Registry
{
    public const ERR_PO_FILE = 1, ERR_INPUT_BUFFER = 2, ERR_LANGUAGE_FILE = 3, ERR_EXPORT = 4;

    public const PLUGINS_DIST_DIRNAME = 'plugins.dist', THEME_DIST_DIRNAME = 'default.dist';

    private string $filename = '';

    private array $lines_arr = [];

    private int $_li = 0;

    private int $header_lines = 0;

    // Header values as they are defined in PO file
    private array $header_arr = [];

    // After parsing a PO file class caches results here...
    private string $parsed_language = '';

    private array $parsed_indexes = [];

    private int $indexes_count = 0;

    /**
     * @param string $f
     *
     * @return bool
     */
    public function set_filename(string $f) : bool
    {
        $this->reset_error();

        if (empty($f)
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
        $this->reset_lines_arr();

        return (bool)$this->_get_lines($b);
    }

    public function get_parsed_indexes() : array
    {
        return [
            'count'          => $this->indexes_count,
            'language'       => $this->parsed_language,
            'language_files' => (!empty($this->parsed_indexes) ? array_keys($this->parsed_indexes) : []),
            'indexes'        => $this->parsed_indexes,
        ];
    }

    /**
     * @param string $po_file
     * @param array|false $params
     *
     * @return bool
     */
    public function export_csv_from_po(string $po_file, $params = false) : bool
    {
        $this->reset_error();

        if (!$this->set_filename($po_file)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PO_FILE, self::_t('Couldn\'t read PO file or it is empty.'));
            }

            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['csv_line_delimiter'])) {
            $params['csv_line_delimiter'] = "\n";
        }
        if (empty($params['csv_column_delimiter'])) {
            $params['csv_column_delimiter'] = ',';
        }
        if (empty($params['csv_column_enclosure'])) {
            $params['csv_column_enclosure'] = '"';
        }
        if (empty($params['csv_column_escape'])) {
            $params['csv_column_escape'] = '"';
        }

        $params['export_to_output'] = (!isset($params['export_to_output']) || !empty($params['export_to_output']));
        $params['export_to_filename'] ??= null;

        if (!empty($params['export_to_filename'])
        && (!is_string($params['export_to_filename'])
            || !($csv_dir = rtrim(@dirname($params['export_to_filename']), '/\\'))
            || !@is_dir($csv_dir) || !@is_writable($csv_dir)
        )) {
            $this->set_error(self::ERR_EXPORT, self::_t('Please provide a valid export csv filename and make sure directory is writable.'));

            return false;
        }

        if (empty($params['language'])
            && ($guessed_language = $this->guess_language_from_header())
            && !empty($guessed_language['guessed_language'])) {
            $params['language'] = $guessed_language['guessed_language'];
        }

        if (empty($params['language'])
         || !self::get_defined_language($params['language'])) {
            $params['language'] = '';
        }

        $this->_reset_parsed_indexes();

        $this->parsed_language = $params['language'];

        $csv_f = null;
        if (!empty($params['export_to_filename'])
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

    /**
     * @param string $po_file
     * @param false|array $params
     *
     * @return array|false
     */
    public function update_language_files(string $po_file, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['language'])) {
            $params['language'] = false;
        } elseif (!self::valid_language($params['language'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Language is invalid.'));

            return false;
        }

        $params['update_language_files'] = (!empty($params['update_language_files']));
        $params['backup_language_files'] = (!isset($params['backup_language_files']) || !empty($params['update_language_files']));
        $params['merge_with_old_files'] = (!isset($params['merge_with_old_files']) || !empty($params['merge_with_old_files']));

        if (!$this->parse_language_from_po_file($po_file, $params)) {
            return false;
        }

        $return_arr = [];
        $return_arr['new_indexes'] = 0;
        $return_arr['updated_indexes'] = 0;
        $return_arr['updated_files'] = [];
        $return_arr['update_errors'] = [];

        if (!$this->indexes_count
         || empty($this->parsed_indexes)
         || !is_array($this->parsed_indexes)) {
            return $return_arr;
        }

        if (!($csv_settings = self::lang_files_csv_settings())) {
            $csv_settings = self::default_lang_files_csv_settings();
        }

        foreach ($this->parsed_indexes as $lang_file => $indexes_arr) {
            if (!is_array($indexes_arr)) {
                continue;
            }

            $new_indexes = 0;
            $updated_indexes = 0;
            if (empty($params['merge_with_old_files'])) {
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

            if (empty($new_indexes) && empty($updated_indexes)
             && !empty($resulting_arr)) {
                continue;
            }

            $fp = false;
            if (!empty($params['update_language_files'])) {
                if (!empty($params['backup_language_files'])
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

            if (empty($new_indexes) && empty($updated_indexes)) {
                if (!empty($params['update_language_files'])
                 && $fp) {
                    @fflush($fp);
                    @fclose($fp);
                }

                self::load_language_file($lang_file, $this->parsed_language, true);

                continue;
            }

            if (!empty($params['update_language_files'])) {
                $line = 1;
                foreach ($resulting_arr as $lang_key => $lang_val) {
                    if (!($csv_line = PHS_Utils::csv_line([$lang_key, $lang_val],
                        $csv_settings['line_delimiter'],
                        $csv_settings['columns_delimiter'],
                        $csv_settings['columns_enclosure'], $csv_settings['enclosure_escape']))
                     || !@fwrite($fp, $csv_line)) {
                        PHS_Logger::error('Error writing ['.$lang_key.'] language index at position ['.$line.']', PHS_Logger::TYPE_MAINTENANCE);
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

    /**
     * @param string $po_file
     * @param null|array $params
     *
     * @return bool
     */
    public function parse_language_from_po_file(string $po_file, ?array $params = null) : bool
    {
        $this->reset_error();

        if (!$this->set_filename($po_file)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PO_FILE, self::_t('Couldn\'t read PO file or it is empty.'));
            }

            return false;
        }

        $params ??= [];

        if (empty($params['language'])
            && ($guessed_language = $this->guess_language_from_header())
            && !empty($guessed_language['guessed_language'])) {
            $params['language'] = $guessed_language['guessed_language'];
        }

        if (empty($params['language'])
            || !($lang_details = self::get_defined_language($params['language']))) {
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
             || empty($translation_arr['translation'])
             || empty($translation_arr['files'])
             || !is_array($translation_arr['files'])) {
                continue;
            }

            foreach ($translation_arr['files'] as $lang_file_arr) {
                if (empty($lang_file_arr['file'])
                 || strpos($lang_file_arr['file'], self::PLUGINS_DIST_DIRNAME) === 0) {
                    continue;
                }

                $plugin_name = '';
                $theme_name = '';
                if (strpos($lang_file_arr['file'], $plugins_relative_path) === 0
                 && ($path_rest = substr($lang_file_arr['file'], $plugins_relative_path_len))
                 && ($path_rest_arr = explode('/', $path_rest, 2))
                 && !empty($path_rest_arr[0])) {
                    $plugin_name = $path_rest_arr[0];
                }

                if (strpos($lang_file_arr['file'], $themes_relative_path) === 0
                 && ($path_rest = substr($lang_file_arr['file'], $themes_relative_path_len))
                 && ($path_rest_arr = explode('/', $path_rest, 2))
                 && !empty($path_rest_arr[0])) {
                    $theme_name = $path_rest_arr[0];
                }

                if ($theme_name === self::THEME_DIST_DIRNAME) {
                    continue;
                }

                if (!empty($theme_name)
                 && ($language_dirs = PHS::get_theme_language_paths($theme_name))
                 && !empty($language_dirs['path'])) {
                    // Add index to theme
                    $lang_file = $language_dirs['path'].$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                if (!empty($plugin_name)
                 && ($instance_dirs = PHS_Instantiable::get_instance_details('PHS_Plugin_'.ucfirst($plugin_name), $plugin_name, PHS_Instantiable::INSTANCE_TYPE_PLUGIN))
                 && !empty($instance_dirs['plugin_paths'][PHS_Instantiable::LANGUAGES_DIR])) {
                    // Add index to plugin
                    $lang_file = $instance_dirs['plugin_paths'][PHS_Instantiable::LANGUAGES_DIR].$this->parsed_language.'.csv';

                    $this->parsed_indexes[$lang_file][$translation_arr['index']] = $translation_arr['translation'];
                }

                if (empty($theme_name) && empty($plugin_name)) {
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
        if (empty($this->header_arr)) {
            $this->extract_header();
        }

        return $this->extract_po_unit();
    }

    public function get_po_header() : array
    {
        static $lower_header_arr = false;

        if (empty($this->header_arr)) {
            $this->extract_header();
        }

        if ($lower_header_arr === false) {
            $lower_header_arr = [];
            foreach ($this->header_arr as $key => $val) {
                $lower_header_arr[strtolower($key)] = $val;
            }
        }

        return [
            'header_lines'     => $this->header_lines,
            'header_arr'       => $this->header_arr,
            'lower_header_arr' => $lower_header_arr,
        ];
    }

    public function guess_language_from_header() : ?array
    {
        if (!($header_data = $this->get_po_header())
         || empty($header_data['lower_header_arr'])
         || empty($header_data['lower_header_arr']['language'])) {
            return null;
        }

        $guessed_language = '';
        // Although BCP 47 itself only allows "-" as a separator; for compatibility, Unicode language identifiers allows both "-" and "_".
        // Implementations should also accept both.
        if (strpos($header_data['lower_header_arr']['language'], '_') !== false) {
            if (($header_val = explode('_', $header_data['lower_header_arr']['language'], 2))
            && !empty($header_val[0])) {
                $guessed_language = strtolower($header_val[0]);
            }
        } elseif (strpos($header_data['lower_header_arr']['language'], '-') !== false) {
            if (($header_val = explode('-', $header_data['lower_header_arr']['language'], 2))
            && !empty($header_val[0])) {
                $guessed_language = strtolower($header_val[0]);
            }
        } else {
            $guessed_language = strtolower($header_data['lower_header_arr']['language']);
        }

        $return_arr = [];
        $return_arr['guessed_language'] = $guessed_language;
        $return_arr['valid_language'] = self::valid_language($guessed_language);

        return $return_arr;
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

    private function _reset_parsed_indexes()
    {
        $this->indexes_count = 0;
        $this->parsed_language = '';
        $this->parsed_indexes = [];
    }

    private function extract_po_unit() : ?array
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

        $unit_arr = [];
        $unit_arr['index'] = '';
        $unit_arr['translation'] = '';
        $unit_arr['comment'] = '';
        $unit_arr['files'] = [];

        do {
            if (str_starts_with($line_str, '# ')) {
                // comment
                $unit_arr['comment'] = substr($line_str, 2);
            } elseif (str_starts_with($line_str, '#: ')) {
                // file...
                // POEdit (or PO format) has errors if file name contains spaces or :
                if (($file_str = substr($line_str, 3))
                && ($parts_arr = explode(':', $file_str))) {
                    $file = null;
                    $line = null;
                    foreach ($parts_arr as $file_data) {
                        $file_line = explode(' ', $file_data);

                        if ($file === null) {
                            $file = trim($file_line[0]);
                            continue;
                        }

                        $line = (int)$file_line[0];

                        if ($file !== false && !empty($line)) {
                            $unit_arr['files'][] = [
                                'file' => $file,
                                'line' => $line,
                            ];

                            $file = false;
                            $line = false;
                        }

                        if (!empty($file_line[1])) {
                            $file = trim($file_line[1]);
                        }
                    }
                }
            } elseif (str_starts_with($line_str, 'msgid ')) {
                $msgid = trim(substr($line_str, 6), '"');
                while (($next_line_str = $this->get_line($this->_li))) {
                    if (!str_starts_with($next_line_str, '"')) {
                        break;
                    }

                    $msgid .= trim($next_line_str, '"');
                    $this->_li++;
                }

                $unit_arr['index'] = $msgid;
            } elseif (str_starts_with($line_str, 'msgstr ')) {
                $msgstr = trim(substr($line_str, 7), '"');
                while (($next_line_str = $this->get_line($this->_li))) {
                    if (!str_starts_with($next_line_str, '"')) {
                        break;
                    }

                    $msgstr .= trim($next_line_str, '"');
                    $this->_li++;
                }

                $unit_arr['translation'] = $msgstr;
            }

            $line_str = $this->get_line($this->_li);
            $this->_li++;
        } while ($line_str);

        return $unit_arr;
    }

    private function reset_lines_arr() : void
    {
        $this->lines_arr = [];
        $this->_li = 0;

        $this->header_arr = [];
        $this->header_lines = 0;
    }

    /**
     * @param null|string $buffer
     *
     * @return array|bool
     */
    private function _get_lines(?string $buffer = null)
    {
        if ($buffer === null) {
            return $this->lines_arr;
        }

        $this->reset_error();

        if (empty($buffer)) {
            $this->set_error(self::ERR_INPUT_BUFFER, self::_t('Invalid input buffer.'));

            return false;
        }

        $this->reset_lines_arr();
        if (!($this->lines_arr = explode("\n", $buffer))) {
            $this->lines_arr = [];

            return false;
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

    private function extract_header()
    {
        $this->_li = 0;
        $this->header_lines = 0;

        while (($line_str = $this->get_line($this->_li))) {
            $line_str = trim($line_str);

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
                || empty($header_vals[0]) || empty($header_vals[1])) {
                continue;
            }

            $header_val = trim($header_vals[1]);
            $header_val = substr($header_val, 0, -2);

            $this->header_arr[trim($header_vals[0])] = $header_val;
        }

        $this->header_lines = $this->_li;
    }
}
