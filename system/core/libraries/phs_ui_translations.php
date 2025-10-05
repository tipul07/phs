<?php
namespace phs\system\core\libraries;

use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Po_format;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\traits\PHS_Model_Trait_statuses;
use phs\plugins\admin\libraries\PHS_Ai_translations;

class PHS_Ui_translations extends PHS_Library
{
    use PHS_Model_Trait_statuses;

    // how many times to retry reading import/export status file contents in case of errors...
    public const STATUS_FILE_RETRIES = 5;

    public const SECONDS_STARTED_RETRY = 120, SECONDS_RUNNING_RETRY = 300;

    public const STATUS_STARTING = 1, STATUS_RUNNING = 2, STATUS_ERROR = 3, STATUS_FINISHED = 4, STATUS_FORCE_STOPPED = 5;

    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Ai_translations $_ai_lib = null;

    public static array $STATUSES_ARR = [
        self::STATUS_STARTING      => ['title' => 'Starting'],
        self::STATUS_RUNNING       => ['title' => 'Running'],
        self::STATUS_ERROR         => ['title' => 'Error'],
        self::STATUS_FINISHED      => ['title' => 'Finished'],
        self::STATUS_FORCE_STOPPED => ['title' => 'Force stopped'],
    ];

    public function get_po_instance() : ?PHS_Po_format
    {
        if (!class_exists(PHS_Po_format::class, false)) {
            include_once PHS_LIBRARIES_DIR.'phs_po_format.php';

            if (!@class_exists(PHS_Po_format::class)) {
                $this->set_error(self::ERR_DEPENDENCIES, 'Error loading PO format library.');

                return null;
            }
        }

        return new PHS_Po_format();
    }

    public function check_ui_translations_results() : ?array
    {
        $languages_arr = self::get_defined_languages();

        @clearstatcache();
        $results_arr = [];
        foreach ($languages_arr as $l_id => $l_arr) {
            if (!($resources = $this->_get_status_resources_details($l_id))
               || empty($resources['translation_file'])
               || empty($resources['full_translation_file'])
               || !($file_size = @filesize($resources['full_translation_file']))) {
                continue;
            }

            $results_arr[$l_id] = [
                'id'            => $l_id,
                'file'          => $resources['translation_file'],
                'file_size'     => $file_size,
                'last_modified' => @filemtime($resources['full_translation_file']),
            ];
        }

        return $results_arr;
    }

    public function start_ui_translations(string $lang, bool $force = false) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!$lang || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid language provided.'));

            return null;
        }

        if (!($status_arr = $this->get_status($lang))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error obtaining trnaslation status for provided language.'));

            return null;
        }

        if (!empty($status_arr['status']) && !empty($status_arr['last_update'])
            && $this->valid_status($status_arr['status'])) {
            $last_update_seconds = seconds_passed($status_arr['last_update']);
            // conditions to stop starting a new assets import
            if ($last_update_seconds < self::SECONDS_STARTED_RETRY
                && $this->status_is_just_started($status_arr)) {
                $this->set_error(
                    self::ERR_FUNCTIONALITY,
                    self::_t('There is already an task running. You should wait %s secods before retry starting a new translation task.',
                        self::SECONDS_STARTED_RETRY)
                );

                return null;
            }

            if ($status_arr['status'] === self::STATUS_RUNNING
                && $last_update_seconds < self::SECONDS_RUNNING_RETRY) {
                $this->set_error(
                    self::ERR_FUNCTIONALITY,
                    self::_t('You should wait %s secods before retry starting a new translation task while another task is still running.',
                        self::SECONDS_RUNNING_RETRY)
                );

                return null;
            }
        }

        if (!($po_obj = $this->get_po_instance())
           || !$po_obj->parse_details_from_po_file_by_language($lang)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error obtaining parsing PO file for language %s.', $lang));

            return null;
        }

        $po_details = $po_obj->get_parsed_indexes();

        $status_arr = $this->get_status_structure();
        $status_arr['language'] = $lang;
        $status_arr['max_records'] = $po_details['count'] ?? 0;
        $status_arr['started'] = time();
        $status_arr['status'] = self::STATUS_STARTING;
        $status_arr['status_title'] = self::_t('Starting');
        $status_arr['log'] = self::_t('Launching translation script...');

        if (!$status_arr['max_records']) {
            $status_arr['ended'] = $status_arr['started'];
            $status_arr['status'] = self::STATUS_FINISHED;
            $status_arr['status_title'] = self::_t('Finished');
            $status_arr['log'] = self::_t('Nothing to translate!');
        }

        if (!$this->_update_status($lang, $status_arr)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error saving translations status. Please try again.'));

            return null;
        }

        if ($this->status_is_success($status_arr)) {
            return $status_arr;
        }

        if (!PHS_Bg_jobs::run(['c' => 'index_bg', 'a' => 'ui_translation_bg'], ['lang' => $lang, 'force_run' => $force])) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error launching background job for UI translation for provided language.'));

            return null;
        }

        return $status_arr;
    }

    public function start_ui_translations_bg(string $lang, bool $force = false, array $params = []) : ?array
    {
        if (!$this->_load_dependencies()
            || !($resources = $this->_get_status_resources_details($lang))) {
            return null;
        }

        $params['translate_all'] ??= false;

        if (!$lang || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid language provided.'));

            return null;
        }

        if (!($status_arr = $this->get_status($lang))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error obtaining required resources for translations status.'));

            return null;
        }

        if (!$force
            && (empty($status_arr['status'])
                || !$this->status_is_just_started($status_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Current bulk translations status is not set for new run.'));

            return null;
        }

        $this->_update_status($lang, [
            'status' => self::STATUS_RUNNING,
            'log'    => self::_t('Translations running...'),
        ]);

        if (!($po_obj = $this->get_po_instance())) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error obtaining PO format instance.'));

            return null;
        }

        $po_obj::add_to_ignored_directories_for_pot_list($this->_admin_plugin->get_ui_translation_excluding_paths());

        if (!$po_obj->refresh_po_file_from_pot($lang)) {
            $this->copy_or_set_error($po_obj,
                self::ERR_FUNCTIONALITY, self::_t('Error generating PO file.'));

            return null;
        }

        if (!$po_obj->parse_details_from_po_file_by_language($lang)) {
            $this->copy_or_set_error($po_obj,
                self::ERR_FUNCTIONALITY, self::_t('Error reading details from PO file.'));

            return null;
        }

        $update_status_count = 5;
        $update_po_translation_file_count = 10;
        $current_records = 0;
        $records_errors = 0;
        $records_success = 0;
        $records_translated_already = 0;
        $status_final_update = true;

        $result = $po_obj->get_po_units();
        foreach ($result as $knti => $po_unit) {
            $current_records++;

            if (!($current_records % $update_status_count)) {
                if (($status_arr = $this->get_status($lang))
                    && $this->status_is_force_stopped($status_arr)) {
                    $this->_update_status($lang, [
                        'log' => self::_t('Force stopped by user.'),
                    ]);

                    $status_final_update = false;
                    break;
                }

                $this->_update_status($lang, [
                    'status'                     => self::STATUS_RUNNING,
                    'current_records'            => $current_records,
                    'records_errors'             => $records_errors,
                    'records_success'            => $records_success,
                    'records_translated_already' => $records_translated_already,
                    'log'                        => self::_t('Translations running...'),
                ]);
            }

            if (!($current_records % $update_po_translation_file_count)
                && !$po_obj->write_po_units_to_file($resources['translation_filename'], true)) {
                PHS_Logger::error('Error updating translation PO file '.$resources['full_translation_file'].': '
                    .$po_obj->get_simple_error_message(self::_t('Unknown error.')),
                    $this->_admin_plugin::LOG_UI_TRANSLATIONS);
            }

            if (!($po_unit['index'] ?? null)) {
                $records_errors++;
                PHS_Logger::error('Error translating PO unit #'.$knti.': No index defined.',
                    $this->_admin_plugin::LOG_UI_TRANSLATIONS);
                continue;
            }

            if (($po_unit['translation'] ?? null) && !$params['translate_all']) {
                $records_translated_already++;
                PHS_Logger::debug('PO unit #'.$knti.': Already translated.',
                    $this->_admin_plugin::LOG_UI_TRANSLATIONS);
                continue;
            }

            PHS_Logger::debug('Translating PO unit #'.$knti.' ['.$po_unit['index'].']',
                $this->_admin_plugin::LOG_UI_TRANSLATIONS);

            if (!($translation = $this->_translate_po_unit($po_unit, $lang))) {
                $records_errors++;
                PHS_Logger::error('Error translating PO unit '.$po_unit['id'].': '
                                .$this->get_simple_error_message('Unknown error.'),
                    $this->_admin_plugin::LOG_UI_TRANSLATIONS);
                continue;
            }

            PHS_Logger::debug('Got translation for PO unit #'.$knti.' "'.$po_unit['index'].'" - "'.$translation.'"',
                $this->_admin_plugin::LOG_UI_TRANSLATIONS);

            if (!$po_obj->add_translation_for_po_unit($knti, $translation)) {
                $records_errors++;
                PHS_Logger::error('Error adding translation for PO unit #'.$knti.': '
                                .$this->get_simple_error_message('Unknown error.'),
                    $this->_admin_plugin::LOG_UI_TRANSLATIONS);
                continue;
            }

            $records_success++;
        }

        if ($status_final_update) {
            $this->_update_status($lang, [
                'ended'                      => time(),
                'status'                     => self::STATUS_FINISHED,
                'max_records'                => $current_records,
                'current_records'            => $current_records,
                'records_errors'             => $records_errors,
                'records_success'            => $records_success,
                'records_translated_already' => $records_translated_already,
                'log'                        => self::_t('Finished'),
            ]);
        }

        return $this->get_status($lang);
    }

    public function update_language_files_with_translation_result(string $lang, array $params = []) : ?array
    {
        if (!$this->_load_dependencies()
            || !($po_obj = $this->get_po_instance())) {
            return null;
        }

        $params['update_language_files'] = !isset($params['update_language_files']) || !empty($params['update_language_files']);
        $params['backup_language_files'] = !isset($params['backup_language_files']) || !empty($params['backup_language_files']);
        $params['merge_with_old_files'] = !isset($params['merge_with_old_files']) || !empty($params['merge_with_old_files']);
        $params['language'] = $lang;

        if (!$lang || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid language for translation.'));

            return null;
        }

        if (!($resources_arr = $this->_get_status_resources_details($lang))
            || empty($resources_arr['full_translation_file'])
            || empty($resources_arr['full_lang_old_file'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error obtaining required resources for translations status.'));

            return null;
        }

        @clearstatcache();
        if (!@file_exists($resources_arr['full_translation_file'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('There is no translation file for provided language.'));

            return null;
        }

        if (@file_exists($resources_arr['full_lang_old_file'])) {
            @unlink($resources_arr['full_lang_old_file']);
        }

        if (!@copy($resources_arr['full_lang_file'], $resources_arr['full_lang_old_file'])) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t create a backup of old language file.'));

            return null;
        }

        if (!@rename($resources_arr['full_translation_file'], $resources_arr['full_lang_file'])) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t rename translation file as main language file.'));

            return null;
        }

        return $po_obj->update_language_files($resources_arr['full_lang_file'], $params);
    }

    public function force_stop_ui_translation(string $lang) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!$lang || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid language for translation.'));

            return null;
        }

        if (!($status_arr = $this->get_status($lang))
            || !$this->status_is_running($status_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Seems that translations task is not running.'));

            return null;
        }

        $update_status = [];
        $update_status['ended'] = time();
        $update_status['status'] = self::STATUS_FORCE_STOPPED;
        $update_status['log'] = self::_t('Force stopping at next tick...');

        if (!($new_status = $this->_update_status($lang, $update_status))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating translations status.'));

            return null;
        }

        return $new_status;
    }

    public function get_status(string $lang) : ?array
    {
        $this->reset_error();

        if (!($resources_arr = $this->_get_status_resources_details($lang))
            || empty($resources_arr['full_stats_file'])) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining required resources for translations status.'));

            return null;
        }

        $status_structure = $this->get_status_structure();
        if (!@file_exists($resources_arr['full_stats_file'])
            || !@is_readable($resources_arr['full_stats_file'])) {
            return $status_structure;
        }

        $status_buff = '';
        $retries = self::STATUS_FILE_RETRIES;
        while ($retries > 0
               && false === ($status_buff = @file_get_contents($resources_arr['full_stats_file']))) {
            $retries--;
        }

        if (!$status_buff
            || !($status_arr = @json_decode($status_buff, true))) {
            return $status_structure;
        }

        return self::validate_array($status_arr, $status_structure);
    }

    public function get_status_structure() : array
    {
        return [
            'started'                    => 0, // timestamp
            'ended'                      => 0, // timestamp
            'status'                     => 0,
            'status_title'               => self::_t('N/A'),
            'language'                   => '',
            'max_records'                => 0,
            'current_records'            => 0,
            'records_errors'             => 0,
            'records_success'            => 0,
            'records_translated_already' => 0,
            'last_update'                => 0, // timestamp
            'log'                        => '',
        ];
    }

    public function status_is_finished(array $status_arr) : bool
    {
        $status_arr = self::validate_array($status_arr, $this->get_status_structure());

        return in_array($status_arr['status'],
            [self::STATUS_FINISHED, self::STATUS_ERROR, self::STATUS_FORCE_STOPPED], true);
    }

    public function status_is_running(array $status_arr) : bool
    {
        $status_arr = self::validate_array($status_arr, $this->get_status_structure());

        return $status_arr['status'] === self::STATUS_RUNNING;
    }

    public function status_is_just_started(array $status_arr) : bool
    {
        $status_arr = self::validate_array($status_arr, $this->get_status_structure());

        return $status_arr['status'] === self::STATUS_STARTING;
    }

    public function status_is_success(array $status_arr) : bool
    {
        $status_arr = self::validate_array($status_arr, $this->get_status_structure());

        return $status_arr['status'] === self::STATUS_FINISHED;
    }

    public function status_is_error(array $status_arr) : bool
    {
        $status_arr = self::validate_array($status_arr, $this->get_status_structure());

        return $status_arr['status'] === self::STATUS_ERROR;
    }

    public function status_is_force_stopped(array $status_arr) : bool
    {
        $status_arr = self::validate_array($status_arr, $this->get_status_structure());

        return $status_arr['status'] === self::STATUS_FORCE_STOPPED;
    }

    private function _translate_po_unit(array $po_unit, string $lang) : ?string
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($po_unit['index'] ?? null)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid PO unit for translation.'));

            return null;
        }

        $payload = ['text' => $po_unit['index']];
        if (!($result_payload = $this->_ai_lib->translate($payload, LANG_EN, $lang))) {
            PHS_Logger::error('Error translating PO unit: '
                              .' Source payload: '.print_r($payload, true),
                $this->_admin_plugin::LOG_UI_TRANSLATIONS);

            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_ai_lib->get_simple_error_message(self::_t('Unknown error.')));

            return null;
        }

        return $result_payload['text'] ?? null;
    }

    private function _get_status_resources_details(string $lang) : ?array
    {
        $this->reset_error();

        if (!$lang
            || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid language.'));

            return null;
        }

        $return_arr = [
            'dir_path'             => LANG_PO_DIR,
            'translation_filename' => $lang.'_translation',
            'translation_file'     => $lang.'_translation.po',
            'lang_filename'        => $lang,
            'lang_file'            => $lang.'.po',
            'lang_old_filename'    => $lang.'_old',
            'lang_old_file'        => $lang.'_old.po',
            'stats_file'           => $lang.'_translation.json',
            'log_file'             => $lang.'_translation.log',
        ];

        $return_arr['full_stats_file'] = $return_arr['dir_path'].$return_arr['stats_file'];
        $return_arr['full_log_file'] = $return_arr['dir_path'].$return_arr['log_file'];
        $return_arr['full_translation_file'] = $return_arr['dir_path'].$return_arr['translation_file'];
        $return_arr['full_lang_file'] = $return_arr['dir_path'].$return_arr['lang_file'];
        $return_arr['full_lang_old_file'] = $return_arr['dir_path'].$return_arr['lang_old_file'];

        return $return_arr;
    }

    private function _update_status(string $lang, array $payload_arr) : ?array
    {
        $this->reset_error();

        if (!$payload_arr) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide export status details for update.'));

            PHS_Logger::error('ERROR (lang:'.$lang.'): '.$this->get_simple_error_message(), $this->_admin_plugin::LOG_UI_TRANSLATIONS);

            return null;
        }

        if (!($resources_arr = $this->_get_status_resources_details($lang))
            || empty($resources_arr['full_stats_file'])) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining required resources for export status.'));

            PHS_Logger::error('ERROR (lang:'.$lang.'): '.$this->get_simple_error_message(), $this->_admin_plugin::LOG_UI_TRANSLATIONS);

            return null;
        }

        $status_structure = $this->get_status_structure();

        $new_payload_arr = !($current_status = $this->get_status($lang))
            ? self::validate_array($payload_arr, $status_structure)
            : self::merge_array_assoc($current_status, self::validate_array_keys_from_definition($payload_arr, $status_structure));

        $new_payload_arr['status_title'] = ($status_arr = $this->valid_status($new_payload_arr['status']))
            ? $status_arr['title']
            : self::_t('N/A');

        $new_payload_arr['last_update'] = time();

        if (!@file_put_contents($resources_arr['full_stats_file'], @json_encode($new_payload_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating translations stats JSON file.'));

            PHS_Logger::error('ERROR (lang:'.$lang.'): '.$this->get_simple_error_message(), $this->_admin_plugin::LOG_UI_TRANSLATIONS);

            return null;
        }

        return $new_payload_arr;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            (!$this->_admin_plugin && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
            || (!$this->_ai_lib && !($this->_ai_lib = PHS_Ai_translations::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
