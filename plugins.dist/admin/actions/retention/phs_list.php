<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\admin\libraries\Phs_Data_retention;
use phs\system\core\models\PHS_Model_Data_retention;

/** @property PHS_Model_Data_retention $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?Phs_Data_retention $_data_retention_lib = null;

    private ?PHS_Model_Accounts $_accounts_model = null;

    public function load_depencies() : bool
    {
        if ((empty($this->_admin_plugin)
             && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
            || (empty($this->_data_retention_lib)
                && !($this->_data_retention_lib = $this->_admin_plugin->get_data_retention_instance()))
            || (empty($this->_accounts_model)
                && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
            || (empty($this->_paginator_model)
                && !($this->_paginator_model = PHS_Model_Data_retention::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function should_stop_execution() : ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_admin_plugin->can_admin_list_data_retention()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have rights to access this section.'));

            return action_request_login();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params() : ?array
    {
        PHS::page_settings('page_title', $this->_pt('Data retention Policies'));

        $can_manage = $this->_admin_plugin->can_admin_manage_data_retention();

        $list_arr = $this->_paginator_model->fetch_default_flow_params(['table_name' => 'phs_data_retention']);
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $this->_paginator_model::STATUS_DELETED];

        $flow_params = [
            'listing_title'          => $this->_pt('Data Retention Policies'),
            'term_singular'          => $this->_pt('policy'),
            'term_plural'            => $this->_pt('policies'),
            'initial_list_arr'       => $list_arr,
            'after_table_callback'   => [$this, 'after_table_callback'],
            'after_filters_callback' => [$this, 'after_filters_callback'],
        ];

        if (PHS_Params::_g('unknown_policy', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid data retention policy or data retention policy was not found in database.'));
        }
        if (PHS_Params::_g('policy_added', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Data retention policy details saved in database.'));
        }

        $statuses_arr = $this->_paginator_model->get_statuses_as_key_val() ?: [];
        $filter_statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        if (isset($filter_statuses_arr[$this->_paginator_model::STATUS_DELETED])) {
            unset($filter_statuses_arr[$this->_paginator_model::STATUS_DELETED]);
        }

        $types_arr = $this->_paginator_model->get_types_as_key_val() ?: [];
        $filter_types_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $types_arr);

        if (!$can_manage) {
            $bulk_actions = null;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Run'),
                    'action'          => 'bulk_run_policies',
                    'js_callback'     => 'phs_data_retention_list_bulk_run_policies',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Inactivate'),
                    'action'          => 'bulk_inactivate',
                    'js_callback'     => 'phs_data_retention_list_bulk_inactivate',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Activate'),
                    'action'          => 'bulk_activate',
                    'js_callback'     => 'phs_data_retention_list_bulk_activate',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Delete'),
                    'action'          => 'bulk_delete',
                    'js_callback'     => 'phs_data_retention_list_bulk_delete',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name'        => $this->_pt('Plugin'),
                'display_hint'        => $this->_pt('Policies for specific plugin'),
                'display_placeholder' => $this->_pt('Policy plugin'),
                'var_name'            => 'fplugin',
                'record_field'        => 'plugin',
                'check_callback'      => function($filter_arr, $scope_val) {
                    if ($scope_val === PHS_Instantiable::CORE_PLUGIN) {
                        return ['scope' => null];
                    }

                    $filter_arr['record_check'] = ['check' => 'LIKE', 'value' => '%%%s%%'];

                    return ['filter' => $filter_arr];
                },
                'type'    => PHS_Params::T_NOHTML,
                'default' => '',
            ],
            [
                'display_name'        => $this->_pt('Model'),
                'display_hint'        => $this->_pt('Policies for specific model'),
                'display_placeholder' => $this->_pt('Policy model'),
                'var_name'            => 'fmodel',
                'record_field'        => 'model',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
            ],
            [
                'display_name'        => $this->_pt('Table'),
                'display_hint'        => $this->_pt('Policies for specific model table'),
                'display_placeholder' => $this->_pt('Policy model table'),
                'var_name'            => 'ftable',
                'record_field'        => 'table',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
            ],
            [
                'display_name' => $this->_pt('Action Type'),
                'var_name'     => 'ftype',
                'record_field' => 'type',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $filter_types_arr,
            ],
            [
                'display_name' => $this->_pt('Status'),
                'var_name'     => 'fstatus',
                'record_field' => 'status',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $filter_statuses_arr,
            ],
        ];

        $columns_arr = [
            [
                'column_title'        => '#',
                'record_field'        => 'id',
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'min-width:50px;max-width:80px;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Plugin'),
                'record_field'        => 'plugin',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_plugin'],
            ],
            [
                'column_title'        => $this->_pt('Model'),
                'record_field'        => 'model',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Table'),
                'record_field'        => 'table',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Field'),
                'record_field'        => 'date_field',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Retention'),
                'record_field'        => 'retention',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
                'display_callback'    => [$this, 'display_retention'],
            ],
            [
                'column_title'        => $this->_pt('Action Type'),
                'record_field'        => 'type',
                'display_key_value'   => $types_arr,
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Status'),
                'record_field'        => 'status',
                'display_key_value'   => $statuses_arr,
                'invalid_value'       => $this->_pt('Undefined'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Created'),
                'default_sort'        => 1,
                'record_field'        => 'cdate',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('Invalid'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('Actions'),
                'display_callback'    => [$this, 'display_actions'],
                'extra_style'         => 'width:120px;',
                'extra_records_style' => 'text-align:right;',
                'sortable'            => false,
            ],
        ];

        if ($this->_admin_plugin->can_admin_manage_data_retention()) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['a' => 'list', 'ad' => 'retention', 'p' => 'admin']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['bulk_actions'] = $bulk_actions;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    public function manage_action($action) : null | bool | array
    {
        $this->reset_error();

        if (empty($this->_paginator_model)
            && !$this->load_depencies()) {
            return false;
        }

        $action_result_params = $this->_paginator->default_action_params();

        if (empty($action['action'])) {
            return $action_result_params;
        }

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;
            case 'bulk_run_policies':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required data retention policies launched with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Launching selected data retention policies failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed launched all selected data retention policies. Data retention policies which failed launching are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                    || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                    || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                    || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                    || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))
                    || empty($scope_arr[$scope_key])
                    || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                $running_ids_arr = [];
                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->is_active($record_id)) {
                        $remaining_ids_arr[] = $record_id;
                    } else {
                        $running_ids_arr[] = $record_id;
                    }
                }

                if (isset($scope_arr[$scope_all_key])) {
                    unset($scope_arr[$scope_all_key]);
                }

                $result = null;
                if ( !empty($running_ids_arr)
                    && ($result = $this->_data_retention_lib->run_data_retention_for_list($running_ids_arr)) ) {
                }

                if (!empty($remaining_ids_arr)) {
                    $scope_arr[$scope_key] = implode(',', $remaining_ids_arr);
                }

                if (empty($remaining_ids_arr)
                    && !empty($result)) {
                    $action_result_params['action_result'] = 'success';

                    unset($scope_arr[$scope_key]);
                } else {
                    if (count($running_ids_arr) !== count($scope_arr[$scope_key])) {
                        $action_result_params['action_result'] = 'failed_some';
                    } else {
                        $action_result_params['action_result'] = 'failed';
                    }
                }

                $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                break;

            case 'bulk_activate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required data retention policies activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating selected data retention policies failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed activating all selected data retention policies. Data retention policies which failed activation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                    || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                    || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                    || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                    || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))
                    || empty($scope_arr[$scope_key])
                    || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->act_activate($record_id)) {
                        $remaining_ids_arr[] = $record_id;
                    }
                }

                if (isset($scope_arr[$scope_all_key])) {
                    unset($scope_arr[$scope_all_key]);
                }

                if (empty($remaining_ids_arr)) {
                    $action_result_params['action_result'] = 'success';

                    unset($scope_arr[$scope_key]);

                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                } else {
                    if (count($remaining_ids_arr) !== count($scope_arr[$scope_key])) {
                        $action_result_params['action_result'] = 'failed_some';
                    } else {
                        $action_result_params['action_result'] = 'failed';
                    }

                    $scope_arr[$scope_key] = implode(',', $remaining_ids_arr);

                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                }
                break;

            case 'bulk_inactivate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required data retention policies inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating selected data retention policies failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed inactivating all selected data retention policies. Data retention policies which failed inactivation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                    || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                    || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                    || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                    || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))
                    || empty($scope_arr[$scope_key])
                    || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->act_inactivate($record_id)) {
                        $remaining_ids_arr[] = $record_id;
                    }
                }

                if (isset($scope_arr[$scope_all_key])) {
                    unset($scope_arr[$scope_all_key]);
                }

                if (empty($remaining_ids_arr)) {
                    $action_result_params['action_result'] = 'success';

                    unset($scope_arr[$scope_key]);

                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                } else {
                    if (count($remaining_ids_arr) !== count($scope_arr[$scope_key])) {
                        $action_result_params['action_result'] = 'failed_some';
                    } else {
                        $action_result_params['action_result'] = 'failed';
                    }

                    $scope_arr[$scope_key] = implode(',', $remaining_ids_arr);

                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                }
                break;

            case 'bulk_delete':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required data retention policies deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected data retention policies failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected data retention policies. Data retention policies which failed deletion are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                    || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                    || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                    || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                    || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))
                    || empty($scope_arr[$scope_key])
                    || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->act_delete($record_id)) {
                        $remaining_ids_arr[] = $record_id;
                    }
                }

                if (isset($scope_arr[$scope_all_key])) {
                    unset($scope_arr[$scope_all_key]);
                }

                if (empty($remaining_ids_arr)) {
                    $action_result_params['action_result'] = 'success';

                    unset($scope_arr[$scope_key]);

                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                } else {
                    if (count($remaining_ids_arr) !== count($scope_arr[$scope_key])) {
                        $action_result_params['action_result'] = 'failed_some';
                    } else {
                        $action_result_params['action_result'] = 'failed';
                    }

                    $scope_arr[$scope_key] = implode(',', $remaining_ids_arr);

                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                }
                break;

            case 'run_record':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Data retention policy launched with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Launching data retention policy failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($record_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot launch data retention policy. Data retention policy not found.'));

                    return false;
                }

                if (!$this->_data_retention_lib->run_data_retention($record_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'activate_record':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Data retention policy activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating data retention policy failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($record_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot activate data retention policy. Data retention policy not found.'));

                    return false;
                }

                if (!$this->_paginator_model->act_activate($record_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'inactivate_record':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Data retention policy inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating data retention policy failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($record_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate data retention policy. Data retention policy not found.'));

                    return false;
                }

                if (!$this->_paginator_model->act_inactivate($record_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'delete_record':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Data retention policy deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting data retention policy failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($record_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete data retention policy. Data retention policy not found.'));

                    return false;
                }

                if (!$this->_paginator_model->act_delete($record_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_plugin($params)
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        return empty($params['record']['plugin']) ? $this::_t('Core') : $params['record']['plugin'];
    }

    public function display_retention($params)
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:

                    return $params['record']['retention'] ?? $this::_t('N/A');
            }
        }

        return (empty($params['record']['retention'])
                || !($retention_arr = $this->_paginator_model->parse_retention_interval($params['record']['retention']))
                || !($interval_arr = $this->_paginator_model->valid_interval($retention_arr['interval'])))
            ? $this::_t('N/A')
            : $retention_arr['count'].' '.$interval_arr['title'];
    }

    public function display_actions($params) : ?string
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return null;
        }

        if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
            return '-';
        }

        if (empty($params['record']) || !is_array($params['record'])
            || !($retention_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        $is_inactive = $this->_paginator_model->is_inactive($retention_arr);
        $is_active = $this->_paginator_model->is_active($retention_arr);

        ob_start();
        if ($is_inactive || $is_active) {
            ?>
            <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'retention'],
                ['drid' => $retention_arr['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
            ><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt('Edit data retention policy'); ?>"></i></a>
            <?php
        }
        if ($is_inactive) {
            ?>
            <a href="javascript:void(0)" onclick="phs_data_retention_list_activate_record( '<?php echo $retention_arr['id']; ?>' )"
            ><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt('Activate data retention policy'); ?>"></i></a>
            <?php
        }
        if ($is_active) {
            ?>
            <a href="javascript:void(0)" onclick="phs_data_retention_list_inactivate_record( '<?php echo $retention_arr['id']; ?>' )"
            ><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt('Inactivate data retention policy'); ?>"></i></a>
            <a href="javascript:void(0)" onclick="phs_data_retention_list_run_record( '<?php echo $retention_arr['id']; ?>' )"
            ><i class="fa fa-fast-forward action-icons" title="<?php echo $this->_pt('Run data retention policy'); ?>"></i></a>
            <?php
        }

        if (!$this->_paginator_model->is_deleted($retention_arr)) {
            ?>
            <br/>
            <a href="javascript:void(0)" onclick="phs_data_retention_list_delete_record( '<?php echo $retention_arr['id']; ?>' )"
            ><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt('Delete data retention policy'); ?>"></i></a>
            <?php
        }

        return ob_get_clean() ?: '';
    }

    public function after_filters_callback($params)
    {
        if ( !$this->_admin_plugin->can_admin_manage_data_retention() ) {
            return '';
        }

        ob_start();
        ?>
        <div class="p-1">
            <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'add', 'ad' => 'retention']); ?>"
               class="btn btn-small btn-success" style="color:white;"><i class="fa fa-plus"></i> <?php echo $this->_pt('Add Data Retention Policy'); ?></a>
        </div>
        <div class="clearfix"></div>
        <?php

        return ob_get_clean();
    }

    public function after_table_callback($params)
    {
        static $js_functionality = false;

        if (!empty($js_functionality)) {
            return '';
        }

        $js_functionality = true;

        ob_start();
        ?>
        <script type="text/javascript">
            function phs_data_retention_list_activate_record( id )
            {
                if( !confirm( "<?php echo $this->_pte('Are you sure you want to activate this data retention policy?'); ?>" ) ) {
                    return;
                }
                <?php
                    $url_params = [];
        $url_params['action'] = [
            'action'        => 'activate_record',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
            function phs_data_retention_list_inactivate_record( id )
            {
                if( !confirm( "<?php echo $this->_pte('Are you sure you want to inactivate this data retention policy?'); ?>" ) ) {
                    return
                }

                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'inactivate_record',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
            function phs_data_retention_list_run_record( id )
            {
                if( !confirm( "<?php echo $this->_pte('Are you sure you want to run this data retention policy?'); ?>" ) ) {
                    return
                }

                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'run_record',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
            function phs_data_retention_list_delete_record( id )
            {
                if( !confirm( "<?php echo $this->_pte('Are you sure you want to DELETE this data retention policy?'); ?>" + "\n" +
                    "<?php echo $this->_pte('NOTE: You cannot undo this action!'); ?>" ) ) {
                    return
                }

                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'delete_record',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }

            function phs_data_retention_list_get_checked_ids_count()
            {
                const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
                if( !checkboxes_list || !checkboxes_list.length ) {
                    return 0;
                }

                return checkboxes_list.length;
            }

            function phs_data_retention_list_bulk_activate()
            {
                const total_checked = phs_data_retention_list_get_checked_ids_count();

                if( !total_checked ) {
                    alert( "<?php echo $this->_pte('Please select data retention policies you want to activate first.'); ?>" );
                    return false;
                }

                if( !confirm( "<?php echo $this->_pt('Are you sure you want to activate %s data retention policies?', '" + total_checked + "'); ?>" ) ) {
                    return false;
                }

                let form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
                if( form_obj ) {
                    form_obj.submit();
                }
            }

            function phs_data_retention_list_bulk_inactivate()
            {
                const total_checked = phs_data_retention_list_get_checked_ids_count();

                if( !total_checked ) {
                    alert( "<?php echo $this->_pte('Please select data retention policies you want to inactivate first.'); ?>" );
                    return false;
                }

                if( !confirm( "<?php echo $this->_pt('Are you sure you want to inactivate %s data retention policies?', '" + total_checked + "'); ?>" ) ) {
                    return false;
                }

                let form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
                if( form_obj ) {
                    form_obj.submit();
                }
            }

            function phs_data_retention_list_bulk_run_policies()
            {
                const total_checked = phs_data_retention_list_get_checked_ids_count();

                if( !total_checked ) {
                    alert( "<?php echo $this->_pte('Please select data retention policies you want to run first.'); ?>" );
                    return false;
                }

                if( !confirm( "<?php echo $this->_pt('Are you sure you want to run %s data retention policies?', '" + total_checked + "'); ?>" ) ) {
                    return false;
                }

                let form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
                if( form_obj ) {
                    form_obj.submit();
                }
            }

            function phs_data_retention_list_bulk_delete()
            {
                const total_checked = phs_data_retention_list_get_checked_ids_count();

                if( !total_checked ) {
                    alert( "<?php echo $this->_pte('Please select data retention policies you want to delete first.'); ?>" );
                    return false;
                }

                if( !confirm( "<?php echo $this->_pt('Are you sure you want to DELETE %s data retention policies?', '" + total_checked + "'); ?>" + "\n" +
                    "<?php echo $this->_pte('NOTE: You cannot undo this action!'); ?>" ) ) {
                    return false;
                }

                let form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
                if( form_obj ) {
                    form_obj.submit();
                }
            }
        </script>
        <?php

        return ob_get_clean();
    }
}
