<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Scope;
use phs\system\core\libraries\PHS_Paginator_exporter_manager;

abstract class PHS_Action_Generic_list extends PHS_Action
{
    public const ERR_ACTION = 50001;

    public const ACTION_EXPORT_SELECTED = 'phs_paginator_export_selected_action',
        ACTION_EXPORT_ALL = 'phs_paginator_export_all_action',
        ACTION_DOWNLOAD_EXPORT = 'phs_paginator_export_action_download_export',
        ACTION_RESET_EXPORT = 'phs_paginator_export_action_reset_export',
        ACTION_CANCEL_EXPORT = 'phs_paginator_export_action_cancel_export';

    protected ?PHS_Paginator $_paginator = null;

    protected ?PHS_Model $_paginator_model = null;

    /**
     * @return null|array Returns an array with flow_parameters, bulk_actions, filters_arr and columns_arr keys containing arrays with definitions for paginator class
     */
    abstract public function load_paginator_params() : ?array;

    abstract public function manage_action(array $action) : null | bool | array;

    /**
     * @return bool true if all depencies were loaded successfully, false if any error (set_error should be used to pass error message)
     */
    abstract protected function _load_dependencies() : bool;

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    // Backwards compatibility
    public function load_depencies() : bool
    {
        return $this->_load_dependencies();
    }

    public function get_internal_actions() : array
    {
        return [self::ACTION_EXPORT_SELECTED, self::ACTION_EXPORT_ALL, self::ACTION_DOWNLOAD_EXPORT,
            self::ACTION_RESET_EXPORT, self::ACTION_CANCEL_EXPORT];
    }

    // Do any actions required immediately after paginator was instantiated
    public function we_have_paginator() : bool
    {
        return true;
    }

    // Do any actions required after paginator was instantiated and initialized (eg. columns, filters, model and bulk actions were set)
    public function we_initialized_paginator() : bool
    {
        return true;
    }

    /**
     * @return null|array Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution() : ?array
    {
        return null;
    }

    /**
     * @param array $current_columns_arr
     * @param string|array $where After which column should $new_columns_arr be inserted. If string we assume column key 'record_field' will be provided value/
     *                            If array, eg $where = array( '{array_key}', '{value_to_be_checked}' ) = array( 'record_field', 'nick' );
     * @param array $new_columns_arr
     *
     * @return array
     */
    public function insert_columns_arr(array $current_columns_arr, array | string $where, array $new_columns_arr) : array
    {
        if (!$new_columns_arr) {
            return $current_columns_arr ?: [];
        }

        if (!$current_columns_arr) {
            return $new_columns_arr;
        }

        if (empty($where)
         || (is_array($where)
                && (empty($where[0]) || empty($where[1]) || !is_string($where[0]) || !is_string($where[1])
                ))
        ) {
            return $current_columns_arr;
        }

        if (is_string($where)) {
            $where = ['record_field', $where];
        }

        $where_column_key = $where[0];
        $where_column_val = $where[1];

        $columns_arr = [];
        $new_columns_added = false;
        foreach ($current_columns_arr as $column_key => $column_arr) {
            if (empty($column_arr[$where_column_key])
                || $column_arr[$where_column_key] != $where_column_val) {
                if (!is_numeric($column_key)) {
                    $columns_arr[$column_key] = $column_arr;
                } else {
                    $columns_arr[] = $column_arr;
                }

                continue;
            }

            $columns_arr[] = $column_arr;

            $new_columns_added = true;
            foreach ($new_columns_arr as $new_column_key => $new_column_arr) {
                if (!is_numeric($new_column_key)) {
                    $columns_arr[$new_column_key] = $new_column_arr;
                } else {
                    $columns_arr[] = $new_column_arr;
                }
            }
        }

        if (!$new_columns_added) {
            foreach ($new_columns_arr as $new_column_key => $new_column_arr) {
                if (!is_numeric($new_column_key)) {
                    $columns_arr[$new_column_key] = $new_column_arr;
                } else {
                    $columns_arr[] = $new_column_arr;
                }
            }
        }

        return $columns_arr;
    }

    public function get_paginator() : ?PHS_Paginator
    {
        return $this->_paginator;
    }

    public function initialize_paginator(array $scope_arr, array $pagination_params) : ?PHS_Paginator
    {
        $this->reset_error();

        if (($action_result = $this->_bootstrap_paginator())
            && is_array($action_result)) {
            $errors_arr = [];
            if ($this->has_error()) {
                $errors_arr[] = $this->get_simple_error_message();
            }
            if (PHS_Notifications::have_notifications_errors()) {
                $errors_arr = array_merge($errors_arr, PHS_Notifications::notifications_errors());
            }
            if (PHS_Notifications::have_notifications_warnings()) {
                $errors_arr = array_merge($errors_arr, PHS_Notifications::notifications_warnings());
            }

            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Errors in pagination bootstrap: ', implode('; ', $errors_arr) ?: 'N/A'));

            return null;
        }

        if ($this->_paginator === null) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('No paginator instancee after bootstrap.'));

            return null;
        }

        $this->_paginator->force_scope($scope_arr);
        $this->_paginator->pagination_params($pagination_params);

        return $this->_paginator;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        PHS::page_body_class('phs_paginator_action');

        // If we have an action result, assume we have to stop...
        if (($action_result = $this->_bootstrap_paginator())
            && is_array($action_result)) {
            if (!PHS_Notifications::have_errors_or_warnings_notifications()) {
                PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Error loading required resources.')));
            }

            return $action_result;
        }

        $data = [];
        if ($action_result === true) {
            // check actions...
            if (($current_action = $this->_paginator->get_current_action())
                && !empty($current_action['action'])) {
                if (!($pagination_action_result = $this->_manage_paginator_action($current_action))) {
                    if ($this->has_error()) {
                        PHS_Notifications::add_error_notice($this->get_simple_error_message());
                    }
                } elseif (!empty($pagination_action_result['action'])) {
                    $pagination_action_result = self::validate_array($pagination_action_result, $this->_paginator->default_action_params());

                    $url_params = [
                        'action' => $pagination_action_result,
                    ];

                    if (!empty($pagination_action_result['action_redirect_url_params'])
                        && is_array($pagination_action_result['action_redirect_url_params'])) {
                        $url_params = self::merge_array_assoc($pagination_action_result['action_redirect_url_params'],
                            $url_params);
                    }

                    return action_redirect($this->_paginator->get_full_url($url_params));
                }
            }

            if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
                // Prepare API response
                $action_result = PHS_Action::default_action_result();

                if (!($json_result = $this->_paginator->get_listing_result())
                    || !is_array($json_result)) {
                    $json_result = [];
                }

                $action_result['api_json_result_array'] = $json_result;

                return $action_result;
            }

            $data = [
                'filters'          => $this->_paginator->get_filters_result(),
                'export'           => $this->_paginator->get_export_result(),
                'listing'          => $this->_paginator->get_listing_result(),
                'paginator_params' => $this->_paginator->pagination_params(),
                'flow_params'      => $this->_paginator->flow_params(),
            ];
        }

        if (!$data) {
            PHS_Notifications::add_error_notice(self::_t('Error rendering paginator details.'));

            $data = [
                'paginator_params' => [],
                'filters'          => self::_t('Something went wrong...'),
                'export'           => '',
                'listing'          => '',
            ];
        }

        return $this->quick_render_template('paginator_default_template', $data);
    }

    protected function _bootstrap_paginator() : bool | array
    {
        $this->reset_error();

        if (!$this->_load_dependencies()) {
            PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Error loading required resources.')));

            $this->reset_error();

            return self::default_action_result();
        }

        if (($action_result = $this->should_stop_execution())) {
            return self::validate_action_result($action_result);
        }

        if (!($paginator_params = $this->load_paginator_params())
            || !is_array($paginator_params)
            || !($paginator_params = self::validate_array_recursive($paginator_params, $this->default_paginator_params()))
            // Complain about base_url not set only if we are not forced to return an action result already
            || (empty($paginator_params['base_url']) && empty($paginator_params['force_action_result']))) {
            if ($this->has_error()) {
                PHS_Notifications::add_error_notice($this->get_simple_error_message());
            } elseif (!PHS_Notifications::have_notifications_errors()) {
                PHS_Notifications::add_error_notice(self::_t('Error loading paginator parameters.'));
            }

            return self::default_action_result();
        }

        if (!empty($paginator_params['force_action_result'])) {
            return self::validate_action_result($paginator_params['force_action_result']);
        }

        // Generic action hooks...
        $hook_args = PHS_Hooks::default_paginator_action_parameters_hook_args();
        $hook_args['paginator_action_obj'] = $this;
        $hook_args['paginator_params'] = $paginator_params;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_PAGINATOR_ACTION_PARAMETERS, $hook_args))
            && !empty($hook_args['paginator_params']) && is_array($hook_args['paginator_params'])) {
            $paginator_params = self::validate_array($hook_args['paginator_params'], $this->default_paginator_params());
        }

        // Particular action hooks...
        $hook_args = PHS_Hooks::default_paginator_action_parameters_hook_args();
        $hook_args['paginator_action_obj'] = $this;
        $hook_args['paginator_params'] = $paginator_params;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_PAGINATOR_ACTION_PARAMETERS.$this->instance_id(), $hook_args))
            && !empty($hook_args['paginator_params']) && is_array($hook_args['paginator_params'])) {
            $paginator_params = self::validate_array($hook_args['paginator_params'], $this->default_paginator_params());
        }

        if (empty($paginator_params['flow_parameters']) || !is_array($paginator_params['flow_parameters'])) {
            $paginator_params['flow_parameters'] = [];
        }

        if (!($this->_paginator = new PHS_Paginator($paginator_params['base_url'], $paginator_params['flow_parameters'], $this))
            || !$this->we_have_paginator()) {
            PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Couldn\'t instantiate paginator class.')));

            return self::default_action_result();
        }

        $init_went_ok = true;
        if (!$this->_paginator->set_columns($paginator_params['columns_arr'])
            || (!empty($paginator_params['filters_arr'])
                && is_array($paginator_params['filters_arr'])
                && !$this->_paginator->set_filters($paginator_params['filters_arr']))
            || (!empty($this->_paginator_model)
                && !$this->_paginator->set_model($this->_paginator_model))
            || (((!empty($paginator_params['bulk_actions'])
                  && is_array($paginator_params['bulk_actions']))
                 || $paginator_params['export_actions'])
                && null === $this->_paginator->set_bulk_actions($paginator_params['bulk_actions'], $paginator_params['export_actions'] ?: []))
        ) {
            $init_went_ok = false;
        } elseif (!$this->we_initialized_paginator()) {
            PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Couldn\'t initialize paginator class.')));

            return self::default_action_result();
        }

        return $init_went_ok;
    }

    protected function _manage_paginator_action(array $action) : null | bool | array
    {
        $this->reset_error();

        if (empty($action['action'])) {
            return $this->_paginator->default_action_params();
        }

        if (!$this->_paginator->has_export_bulk_actions()
            || !in_array($action['action'], $this->get_internal_actions(), true)) {
            return $this->manage_action($action);
        }

        $start_export_action = $action['action'] === self::ACTION_EXPORT_SELECTED
                               || $action['action'] === self::ACTION_EXPORT_ALL;

        if (!empty($action['action_result'])) {
            $in_bg = PHS_Params::_gp('in_bg', PHS_Params::T_INT) || 0;

            if ($action['action_result'] === 'success') {
                if ($start_export_action) {
                    if ($in_bg) {
                        PHS_Notifications::add_success_notice($this->_pt('Export background action launched with success.'));
                    } else {
                        PHS_Notifications::add_success_notice($this->_pt('Export run with success.'));
                    }
                } elseif ($action['action'] === self::ACTION_RESET_EXPORT) {
                    PHS_Notifications::add_success_notice($this->_pt('Export reset with success.'));
                } elseif ($action['action'] === self::ACTION_CANCEL_EXPORT) {
                    PHS_Notifications::add_success_notice($this->_pt('Export was cancelled with success.'));
                }
            } elseif ($action['action_result'] === 'failed') {
                if ($start_export_action) {
                    if ($in_bg) {
                        PHS_Notifications::add_error_notice($this->_pt('Failed launching export background job. Please try again.'));
                    } else {
                        PHS_Notifications::add_error_notice($this->_pt('Failed running export action. Please try again.'));
                    }
                } elseif ($action['action'] === self::ACTION_RESET_EXPORT) {
                    PHS_Notifications::add_success_notice($this->_pt('Failed resetting the export.'));
                } elseif ($action['action'] === self::ACTION_CANCEL_EXPORT) {
                    PHS_Notifications::add_success_notice($this->_pt('Failed cancelling the export.'));
                }
            }

            return true;
        }

        $action_result_params = $this->_paginator->default_action_params();
        $action_result_params['action'] = $action['action'];

        $export_params = [];
        if ($start_export_action) {
            $in_bg = PHS_Params::_gp('in_bg', PHS_Params::T_INT) ?: 0;
            $export_format = PHS_Params::_gp('export_format', PHS_Params::T_NOHTML) ?: 'csv';
            $column_delimiter = PHS_Params::_gp('column_delimiter', PHS_Params::T_NOHTML) ?: ',';

            $export_params['in_bg'] = $in_bg;
            $export_params['export_format'] = $export_format;
            $export_params['column_delimiter'] = $column_delimiter;
        }

        return match ($action['action']) {
            self::ACTION_EXPORT_ALL      => $this->_manage_action_export_all($action_result_params, $export_params),
            self::ACTION_EXPORT_SELECTED => $this->_export_selected_action($action_result_params, $export_params),
            self::ACTION_DOWNLOAD_EXPORT => $this->_download_export_action($action_result_params),
            self::ACTION_RESET_EXPORT    => $this->_reset_export_action($action_result_params),
            self::ACTION_CANCEL_EXPORT   => $this->_cancel_export_action($action_result_params),
        };
    }

    protected function _download_export_action(array $action_result_params) : bool | array
    {
        if (!($export_manager = PHS_Paginator_exporter_manager::get_instance())
           || !$export_manager->download_export_file($this, PHS::current_user() ?: null)) {
            $action_result_params['action_result'] = 'failed';
        } else {
            $action_result_params['action_result'] = 'success';
        }

        return $action_result_params;
    }

    protected function _reset_export_action(array $action_result_params) : bool | array
    {
        if (!($export_manager = PHS_Paginator_exporter_manager::get_instance())
           || !$export_manager->reset_export($this, PHS::current_user() ?: null)) {
            $action_result_params['action_result'] = 'failed';
        } else {
            $action_result_params['action_result'] = 'success';
        }

        return $action_result_params;
    }

    protected function _cancel_export_action(array $action_result_params) : bool | array
    {
        if (!($export_manager = PHS_Paginator_exporter_manager::get_instance())
           || !$export_manager->cancel_export($this, PHS::current_user() ?: null)) {
            $action_result_params['action_result'] = 'failed';
        } else {
            $action_result_params['action_result'] = 'success';
        }

        return $action_result_params;
    }

    protected function _manage_action_export_all(array $action_result_params, array $export_params) : bool | array
    {
        if (!empty($export_params['in_bg'])) {
            return $this->_launch_bulk_action_in_background(self::ACTION_EXPORT_ALL, $action_result_params, $export_params);
        }

        $exporter_params = [];
        $exporter_params['export_all_records'] = true;
        $exporter_params['exporter_library_params'] = [
            'export_encoding'     => 'UTF-8',
            'export_to'           => PHS_Paginator_exporter_library::EXPORT_TO_BROWSER,
            'request_render_type' => $this->_paginator::CELL_RENDER_CSV,
            'export_file_name'    => ($this->_paginator->flow_param('term_plural') ?: 'records')
                                     .'_'.date('Y_m_d_H_i').'.csv',
            'export_mime_type' => 'text/csv',
            'csv_format'       => [
                'line_delimiter'   => "\n",
                'column_delimiter' => $export_params['column_delimiter'],
                'field_enclosure'  => '"',
                'enclosure_escape' => '"',
            ],
        ];

        if (($export_result = $this->_paginator->do_export_records($exporter_params))) {
            if (empty($export_result['exports_failed'])) {
                $action_result_params['action_result'] = 'success';
            } else {
                $action_result_params['action_result'] = 'failed_some';
            }
        } else {
            $action_result_params['action_result'] = 'failed';
        }

        return $action_result_params;
    }

    protected function _export_selected_action(array $action_result_params, array $export_params) : bool | array
    {
        if (!empty($export_params['in_bg'])) {
            return $this->_launch_bulk_action_in_background(self::ACTION_EXPORT_SELECTED, $action_result_params, $export_params);
        }

        if (!($export_action = $this->_paginator->get_export_selection_bulk_action())
           || empty($export_action['checkbox_column'])) {
            $this->set_error(self::ERR_ACTION, $this->_pt('Export action is not configured correctly.'));

            return false;
        }

        if (!($scope_arr = $this->_paginator->get_scope())
            || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
            || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
            || !($scope_key = @sprintf($ids_checkboxes_name, $export_action['checkbox_column']))
            || !($scope_all_key = @sprintf($ids_all_checkbox_name, $export_action['checkbox_column']))
            || empty($scope_arr[$scope_key])
            || !is_array($scope_arr[$scope_key])
        ) {
            return true;
        }

        if (isset($scope_arr[$scope_all_key])) {
            unset($scope_arr[$scope_all_key]);
        }

        if (empty($scope_arr[$scope_key])
            || !is_array($scope_arr[$scope_key])
        ) {
            return true;
        }

        $exporter_params = [];
        $exporter_params['export_all_records'] = false;
        $exporter_params['exporter_library_params'] = [
            'export_encoding'     => 'UTF-8',
            'export_to'           => PHS_Paginator_exporter_library::EXPORT_TO_BROWSER,
            'request_render_type' => $this->_paginator::CELL_RENDER_CSV,
            'export_file_name'    => ($this->_paginator->flow_param('term_plural') ?: 'records')
                                     .'_selected_'.date('Y_m_d_H_i').'.csv',
            'export_mime_type' => 'text/csv',
            'csv_format'       => [
                'line_delimiter'   => "\n",
                'column_delimiter' => $export_params['column_delimiter'],
                'field_enclosure'  => '"',
                'enclosure_escape' => '"',
            ],
        ];
        $exporter_params['filter_records_fields'] = [
            'id' => $scope_arr[$scope_key],
        ];

        if (($export_result = $this->_paginator->do_export_records($exporter_params))) {
            if (empty($export_result['exports_failed'])) {
                $action_result_params['action_result'] = 'success';
            } else {
                $action_result_params['action_result'] = 'failed_some';
            }
        } else {
            $action_result_params['action_result'] = 'failed';
        }

        $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];

        return $action_result_params;
    }

    protected function _launch_bulk_action_in_background(string $action, array $action_result_params, array $export_params) : bool | array
    {
        $action_result_params['action_result'] = 'success';
        $action_result_params['action_redirect_url_params']['extra_params'] = ['in_bg' => 1];

        if (!($export_manager = PHS_Paginator_exporter_manager::get_instance())
           || !$export_manager->launch_export_action_in_background($this, $action, $export_params, PHS::current_user() ?: null)) {
            $action_result_params['action_result'] = 'failed';
        }

        return $action_result_params;
    }

    protected function default_paginator_params() : array
    {
        return [
            'base_url'        => '',
            'flow_parameters' => [],
            'bulk_actions'    => [],
            'export_actions'  => [
                'enabled'          => false,
                'export_selection' => [
                    'display_name'    => self::_t('Export selected'),
                    'action'          => self::ACTION_EXPORT_SELECTED,
                    'js_callback'     => 'phs_paginator_export_selected_callback',
                    'checkbox_column' => 'id',
                ],
                'export_all' => [
                    'display_name' => self::_t('Export ALL'),
                    'action'       => self::ACTION_EXPORT_ALL,
                    'js_callback'  => 'phs_paginator_export_all_callback',
                ],
            ],
            'filters_arr' => [],
            'columns_arr' => [],
            // an action result array or null
            'force_action_result' => null,
        ];
    }
}
