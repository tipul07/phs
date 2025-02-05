<?php
namespace phs\system\core\libraries;

use phs\PHS;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Paginator;
use phs\libraries\PHS_Record_data;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\traits\PHS_Model_Trait_statuses;
use phs\libraries\PHS_Action_Generic_list;
use phs\libraries\PHS_Paginator_exporter_library;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Paginator_exporter_manager extends PHS_Library
{
    use PHS_Model_Trait_statuses;

    // After how many seconds of non updating export status can we reset the export
    public const RESET_EXPORT_TIME = 120;

    public const EXPORT_DIR = 'phs_list_exports';

    public const STATUS_LAUNCHED = 1, STATUS_STARTED = 2, STATUS_ERROR = 3, STATUS_FINISHED = 4, STATUS_CANCELLED = 5;

    private array $_export_context = [];

    private ?PHS_Model_Accounts $_accounts_model = null;

    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Action_Generic_list $_paginator_action = null;

    private ?PHS_Record_data $_account_data = null;

    protected static array $STATUSES_ARR = [
        self::STATUS_LAUNCHED  => ['title' => 'Launched'],
        self::STATUS_STARTED   => ['title' => 'Started'],
        self::STATUS_ERROR     => ['title' => 'Error'],
        self::STATUS_FINISHED  => ['title' => 'Finished'],
        self::STATUS_CANCELLED => ['title' => 'Cancelled'],
    ];

    public function get_account_data() : ?PHS_Record_data
    {
        return $this->_account_data;
    }

    public function get_paginator_action() : ?PHS_Action_Generic_list
    {
        return $this->_paginator_action;
    }

    public function launch_export_action_in_background(
        PHS_Action_Generic_list $action_obj,
        string $bulk_action,
        array $export_params,
        null | int | array | PHS_Record_data $account_data,
    ) : bool {
        $this->reset_error();

        if (!$this->_set_account_data($account_data)) {
            return false;
        }

        $this->_set_paginator_action($action_obj);

        if (!$this->_create_export_folder()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Error creating export directory.'));

            return false;
        }

        $this->_update_export_status(
            action: $action_obj::class,
            export_format: $export_params['export_format'] ?? 'csv',
            status: self::STATUS_LAUNCHED,
            current_count: 0,
            max_count: 0,
            start_time: time(),
            msg: self::_t('Launching background script...'),
            clean_update: true,
        );

        if (!PHS_Bg_jobs::run(
            ['a' => 'paginator_exporter_bg', 'ad' => 'paginator', 'c' => 'index_bg'],
            ['export_context' => $this->_create_export_context($action_obj::class, $bulk_action, $export_params, $action_obj->get_paginator()),
            ],
            ['with_foreground_user' => true])
        ) {
            $error_msg = self::_t('Error launching export background action.');

            $this->_update_export_status(status: self::STATUS_ERROR, msg: $error_msg);

            $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, $error_msg);

            return false;
        }

        return true;
    }

    public function start_export_from_background_action(array $export_context) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $this->_set_export_context($export_context);

        if (!($current_user = PHS::current_user())
            || !($account_arr = $this->_set_account_data($current_user))) {
            PHS_Logger::warning('[EXPORT] No user logged in.', $this->_admin_plugin::LOG_PAGINATOR);

            $this->set_error(self::ERR_PARAMETERS, 'No user logged in.');

            return null;
        }

        if (!($action_class = $this->_get_export_context('action'))
            || !($action_obj = $action_class::get_instance())
            || !($action_obj instanceof PHS_Action_Generic_list)) {
            PHS_Logger::warning('[EXPORT] Error instantiating paginator action.', $this->_admin_plugin::LOG_PAGINATOR);

            $this->set_error(self::ERR_PARAMETERS, 'No paginator action provided in export context.');

            return null;
        }
        $this->_set_paginator_action($action_obj);

        $this->_update_export_status(status: self::STATUS_STARTED, msg: self::_t('Background script taking over.'));

        $bulk_action = $this->_get_export_context('bulk_action');
        $export_params = $this->_get_export_context('export_params');
        $scope_arr = $this->_get_export_context('scope');
        $pagination_params = $this->_get_export_context('pagination_params');

        if (!($paginator_obj = $action_obj->initialize_paginator($scope_arr, $pagination_params))) {
            $error_msg = self::_t('Error initializing paginator for export.');

            $this->_update_export_status(status: self::STATUS_ERROR, msg: $error_msg);
            $this->copy_or_set_error($action_obj, self::ERR_FUNCTIONALITY, $error_msg);

            return null;
        }

        $export_format = $export_params['export_format'] ?? 'csv';

        $friendly_name = $this->_get_friendly_filename_for_current_export($export_format) ?: 'export_file.'.$this->_get_export_file_extension($export_format);
        $actual_name = $this->_get_actual_filename_for_current_export($export_format) ?: 'export_file.'.$this->_get_export_file_extension($export_format);

        $this->_update_export_status(friendly_file: $friendly_name, actual_file: $actual_name);

        $exporter_params = [];
        $exporter_params['export_all_records'] = false;
        $exporter_params['exporter_library_params'] = [
            'export_encoding'     => 'UTF-8',
            'export_to'           => PHS_Paginator_exporter_library::EXPORT_TO_FILE,
            'request_render_type' => $this->_get_export_render_type($paginator_obj),
            'export_file_dir'     => $this->_get_export_path(),
            'export_file_name'    => $actual_name,
            'export_mime_type'    => $this->_get_export_file_mime_type($export_format),
            'csv_format'          => [
                'line_delimiter'   => "\n",
                'column_delimiter' => $export_params['column_delimiter'] ?? ',',
                'field_enclosure'  => '"',
                'enclosure_escape' => '"',
            ],
            'model_query_params' => [
                'query_model_for_records_params' => [
                    // we need the count of records
                    'force_query_count' => true,
                ],
            ],
        ];

        if ($bulk_action === $action_obj::ACTION_EXPORT_ALL) {
            $exporter_params['export_all_records'] = true;
        } elseif ($bulk_action === $action_obj::ACTION_EXPORT_SELECTED) {
            if (!($export_action = $paginator_obj->get_export_selection_bulk_action())
                || empty($export_action['checkbox_column'])) {
                $error_msg = self::_t('Export action is not configured correctly.');

                $this->_update_export_status(status: self::STATUS_ERROR, msg: $error_msg);
                $this->set_error(self::ERR_PARAMETERS, $error_msg);

                return null;
            }

            if (!($scope_arr = $paginator_obj->get_scope())
                || !($ids_checkboxes_name = $paginator_obj->get_checkbox_name_format())
                || !($scope_key = @sprintf($ids_checkboxes_name, $export_action['checkbox_column']))
                || empty($scope_arr[$scope_key])
                || !is_array($scope_arr[$scope_key])
            ) {
                $error_msg = self::_t('Couldn\'t extract selection from provided scope.');

                $this->_update_export_status(status: self::STATUS_ERROR, msg: $error_msg);
                $this->set_error(self::ERR_PARAMETERS, $error_msg);

                return null;
            }

            $exporter_params['filter_records_fields'] = [
                'id' => $scope_arr[$scope_key],
            ];
        }

        $exporter_params['callbacks'] = [
            'export_started' => function(int $total_records) {
                $this->_update_export_status(max_count: $total_records, msg: self::_t('Exporting...'));
            },
            'export_tick_count' => 5,
            'export_tick'       => function(int $current_count) {
                $this->_update_export_status(current_count: $current_count);
                if ($this->_should_cancel_export_on_tick()) {
                    return ['force_stop' => true];
                }

                return [];
            },
            'export_ended' => function(int $max_records, int $current_count, bool $force_stopped) {
                if (!$force_stopped) {
                    $this->_update_export_status(current_count: max($max_records, $current_count));

                    return;
                }

                $this->_update_export_status(status: self::STATUS_CANCELLED, current_count: $current_count, msg: self::_t('Export cancelled.'));
                $this->_export_just_cancelled();
            },
        ];

        if (!($export_result = $paginator_obj->do_export_records($exporter_params))) {
            $this->copy_or_set_error($paginator_obj,
                self::ERR_FUNCTIONALITY, self::_t('Error when running export action.'));

            $error_stack = $this->stack_error();

            $this->_update_export_status(status: self::STATUS_ERROR, msg: $this->get_simple_error_message());

            $this->restore_errors($error_stack);

            return null;
        }

        if (!$this->_is_cancelled_status()) {
            $this->_update_export_status(
                status: self::STATUS_FINISHED,
                end_time: time(),
                msg: self::_t('Export finished.'),
            );
        }

        // Make sure we don't have status update errors
        $this->reset_error();

        return $export_result;
    }

    public function can_cancel_export(array $export_status) : bool
    {
        return !$this->is_final_status($export_status);
    }

    public function can_reset_export(array $export_status) : bool
    {
        return $this->is_final_status($export_status)
               || empty($export_status['now_time'])
               || $export_status['now_time'] + self::RESET_EXPORT_TIME < time();
    }

    public function is_final_status(array $export_status) : bool
    {
        return !empty($export_status['status'])
               && in_array((int)$export_status['status'], [self::STATUS_ERROR, self::STATUS_FINISHED, self::STATUS_CANCELLED], true);
    }

    public function is_success_status(array $export_status) : bool
    {
        return !empty($export_status['status'])
               && (int)$export_status['status'] === self::STATUS_FINISHED;
    }

    public function read_export_details(
        ?PHS_Action_Generic_list $action_obj = null,
        null | int | array | PHS_Record_data $account_data = null,
    ) : ?array {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if ($action_obj !== null) {
            $this->_set_paginator_action($action_obj);
        }
        if ($account_data !== null
           && !$this->_set_account_data($account_data)) {
            return null;
        }

        if (!($status_file = $this->_get_export_status_file())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Cannot obtain export status file name.'));

            return null;
        }

        if (!@file_exists($status_file)) {
            return [];
        }

        if (!($json_buf = @file_get_contents($status_file))
           || !($json_arr = @json_decode($json_buf, true))) {
            return null;
        }

        return $json_arr;
    }

    public function download_export_file(
        ?PHS_Action_Generic_list $action_obj = null,
        null | int | array | PHS_Record_data $account_data = null,
    ) : ?array {
        if (@headers_sent()) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Headers already sent. Cannot send export file to browser.'));

            return null;
        }

        if (!($export_status = $this->read_export_details($action_obj, $account_data))
            || !$this->is_final_status($export_status)
            || empty($export_status['actual_file'])
            || !@file_exists($this->_get_export_path().$export_status['actual_file'])) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Export file is not available.'));

            return null;
        }

        @header('Content-Transfer-Encoding: binary');
        @header('Content-Disposition: attachment; filename="'.($export_status['friendly_file'] ?? $export_status['actual_file']).'"');
        @header('Expires: 0');
        @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        @header('Pragma: public');
        @header('Content-Type: '.$this->_get_export_file_mime_type($export_status['export_format'] ?? 'csv').'; charset=UTF-8');

        echo @readfile($this->_get_export_path().$export_status['actual_file']);
        exit;
    }

    public function reset_export(
        ?PHS_Action_Generic_list $action_obj = null,
        null | int | array | PHS_Record_data $account_data = null,
    ) : ?bool {
        if (!($export_status = $this->read_export_details($action_obj, $account_data))
            || !$this->can_reset_export($export_status)) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Resetting export is not available.'));

            return null;
        }

        if (!empty($export_status['actual_file'])
           && @file_exists($this->_get_export_path().$export_status['actual_file'])) {
            @unlink($this->_get_export_path().$export_status['actual_file']);
        }

        if (($status_file = $this->_get_export_status_file())) {
            @unlink($status_file);
        }

        if (($cancel_file = $this->_get_cancel_export_file())) {
            @unlink($cancel_file);
        }

        return true;
    }

    public function cancel_export(
        ?PHS_Action_Generic_list $action_obj = null,
        null | int | array | PHS_Record_data $account_data = null,
    ) : ?bool {
        if (!($export_status = $this->read_export_details($action_obj, $account_data))
            || !$this->can_cancel_export($export_status)) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Cancelling export is not available.'));

            return null;
        }

        if (!@touch($this->_get_cancel_export_file())) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error creating cancel export flag file.'));

            return null;
        }

        return true;
    }

    public function _get_export_status_file() : ?string
    {
        if (!($action_obj = $this->get_paginator_action())
           || !($current_user = $this->get_account_data())) {
            return null;
        }

        return $this->_get_export_path().'status_'.$current_user['id'].'_'.md5($action_obj::class).'.json';
    }

    public function _get_cancel_export_file() : ?string
    {
        if (!($action_obj = $this->get_paginator_action())
           || !($current_user = $this->get_account_data())) {
            return null;
        }

        return $this->_get_export_path().'cancel_'.$current_user['id'].'_'.md5($action_obj::class).'.json';
    }

    private function _is_cancelled_status() : bool
    {
        return ($this->read_export_details()['status'] ?? 0) === self::STATUS_CANCELLED;
    }

    private function _should_cancel_export_on_tick() : bool
    {
        @clearstatcache();

        return ($cancel_file = $this->_get_cancel_export_file())
               && @file_exists($cancel_file);
    }

    private function _export_just_cancelled() : void
    {
        if (($cancel_file = $this->_get_cancel_export_file())) {
            @unlink($cancel_file);
        }
    }

    private function _set_export_context(array $context) : void
    {
        $context['bulk_action'] ??= '';
        $context['action'] ??= null;
        $context['export_params'] ??= [];
        $context['scope'] ??= [];
        $context['pagination_params'] ??= [];

        $this->_export_context = $context;
    }

    private function _get_export_context(string $key) : mixed
    {
        return $this->_export_context[$key] ?? null;
    }

    private function _get_friendly_filename_for_current_export(string $format) : ?string
    {
        if (!($action_obj = $this->get_paginator_action())
           || !($paginator_obj = $action_obj->get_paginator())) {
            return null;
        }

        return ($paginator_obj->flow_param('term_plural') ?: 'records')
               .($this->_get_export_context('bulk_action') === $action_obj::ACTION_EXPORT_SELECTED ? '_selected' : '')
               .'_'.date('Y_m_d_H_i')
               .'.'.$this->_get_export_file_extension($format);
    }

    private function _get_actual_filename_for_current_export(string $format) : ?string
    {
        if (!($action_obj = $this->get_paginator_action())
           || !($current_user = $this->get_account_data())) {
            return null;
        }

        return 'export_'.$current_user['id'].'_'.md5($action_obj::class).'.'.$this->_get_export_file_extension($format);
    }

    private function _get_export_render_type(PHS_Paginator $paginator_obj) : int
    {
        return match ($this->_get_export_context('export_params')['export_format'] ?? '') {
            'xls'   => $paginator_obj::CELL_RENDER_EXCEL,
            default => $paginator_obj::CELL_RENDER_CSV,
        };
    }

    private function _get_export_file_mime_type(string $format) : string
    {
        return match ($format) {
            'xls'   => 'application/vnd.ms-excel',
            default => 'text/csv',
        };
    }

    private function _get_export_file_extension(string $format) : string
    {
        return match ($format) {
            'xls'   => 'xlsx',
            default => 'csv',
        };
    }

    private function _update_export_status(
        ?string $action = null,
        ?string $export_format = null,
        ?int $status = null,
        ?int $current_count = null,
        ?int $max_count = null,
        ?int $start_time = null,
        ?int $end_time = null,
        ?string $msg = null,
        ?string $friendly_file = null,
        ?string $actual_file = null,
        bool $clean_update = false,
    ) : bool {
        $new_values = [];
        if ($action !== null) {
            $new_values['action'] = $action;
        }
        if ($export_format !== null) {
            $new_values['export_format'] = $export_format;
        }
        if ($start_time !== null) {
            $new_values['start_time'] = $start_time;
        }
        if ($end_time !== null) {
            $new_values['end_time'] = $end_time;
        }
        if ($status !== null) {
            $new_values['status'] = $status;
        }
        if ($current_count !== null) {
            $new_values['current_count'] = $current_count;
        }
        if ($max_count !== null) {
            $new_values['max_count'] = $max_count;
        }
        if ($msg !== null) {
            $new_values['msg'] = $msg;
        }
        if ($friendly_file !== null) {
            $new_values['friendly_file'] = $friendly_file;
        }
        if ($actual_file !== null) {
            $new_values['actual_file'] = $actual_file;
        }

        if ($new_values) {
            $new_values['now_time'] = time();
        }

        if (!$new_values
           || $this->_update_export_payload($new_values, $clean_update)) {
            return true;
        }

        PHS_Logger::warning('Action '.($this->get_paginator_action()::class ?? 'N/A').' error updating satus file: '
                            .$this->get_simple_error_message(self::_t('Unknown error.'))
                            .($msg ? ' '.$msg : ''), $this->_admin_plugin::LOG_PAGINATOR);

        return false;
    }

    private function _update_export_payload(array $payload, bool $clean_update = false) : ?array
    {
        $this->reset_error();

        if (!($status_file = $this->_get_export_status_file())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Cannot obtain export status file name.'));

            return null;
        }

        $new_payload = $clean_update
            ? $payload
            : self::merge_array_assoc($this->read_export_details() ?: [], $payload);

        if (false === @file_put_contents($status_file, @json_encode($new_payload) ?: '')
           && false === @file_put_contents($status_file, @json_encode($new_payload) ?: '')) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating export status file.'));

            return null;
        }

        return $new_payload;
    }

    private function _create_export_context(string $action_class, string $bulk_action, array $export_params, PHS_Paginator $paginator) : array
    {
        return [
            'action'            => $action_class,
            'bulk_action'       => $bulk_action,
            'export_params'     => $export_params,
            'scope'             => $paginator->get_scope(),
            'pagination_params' => $paginator->pagination_params(),
        ];
    }

    private function _set_paginator_action(PHS_Action_Generic_list $paginator_action) : void
    {
        $this->_paginator_action = $paginator_action;
    }

    private function _set_account_data(int | array | PHS_Record_data $account_data) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (!($account_arr = $this->_accounts_model->data_to_record_data($account_data))
           || $this->_accounts_model->is_deleted($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found.'));

            return false;
        }

        $this->_account_data = $account_arr;

        return true;
    }

    private function _get_export_path(bool $slash_ended = true) : string
    {
        return rtrim(PHS_UPLOADS_DIR, '/').'/'.self::EXPORT_DIR.(!empty($slash_ended) ? '/' : '');
    }

    private function _get_export_www(bool $slash_ended = true) : string
    {
        return rtrim(PHS_UPLOADS_WWW, '/').'/'.self::EXPORT_DIR.(!empty($slash_ended) ? '/' : '');
    }

    private function _create_export_folder() : bool
    {
        $this->reset_error();

        if (!($export_dir = $this->_get_export_path(false))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining temporary upload directory path.'));

            return false;
        }

        @file_put_contents($export_dir.'/.htaccess', 'Require all denied');

        if (@file_exists($export_dir)) {
            if (!@is_dir($export_dir)
                || !@is_writable($export_dir)) {
                $this->set_error(self::ERR_RIGHTS,
                    self::_t('QR code directory is not a directory or is not writeable.'));

                return false;
            }

            return true;
        }

        if (!@mkdir($export_dir, 0775)
            && !@is_dir($export_dir)
        ) {
            $this->set_error(self::ERR_RIGHTS, self::_t('Error creating QR code directory.'));

            return false;
        }

        return true;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            (!$this->_accounts_model
             && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
            || (!$this->_admin_plugin
                && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }

    private function _not_used_only_for_translation() : void
    {
        self::_t('Launched');
        self::_t('Started');
        self::_t('Error');
        self::_t('Finished');
        self::_t('Cancelled');
    }
}
