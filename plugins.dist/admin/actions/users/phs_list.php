<?php

namespace phs\plugins\admin\actions\users;

use phs\PHS;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Tenants;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_tenants;

/** @property PHS_Model_Accounts $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Plugin_Accounts $_accounts_plugin = null;

    private ?PHS_Model_Accounts_tenants $_account_tenants_model = null;

    private ?PHS_Model_Tenants $_tenants_model = null;

    private array $_tenants_list_arr = [];

    public function load_depencies() : bool
    {
        if ((!$this->_paginator_model && !($this->_paginator_model = PHS_Model_Accounts::get_instance()))
         || (!$this->_account_tenants_model && !($this->_account_tenants_model = PHS_Model_Accounts_tenants::get_instance()))
         || (!$this->_tenants_model && !($this->_tenants_model = PHS_Model_Tenants::get_instance()))
         || (!$this->_admin_plugin && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
         || (!$this->_accounts_plugin && !($this->_accounts_plugin = PHS_Plugin_Accounts::get_instance()))
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
        PHS::page_settings('page_title', $this->_pt('List Users'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params() : ?array
    {
        if (!($current_user = PHS::user_logged_in())) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You should login first...'));

            return null;
        }

        if (!$this->load_depencies()) {
            return null;
        }

        if (!$this->_admin_plugin->can_admin_list_accounts()) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

            return null;
        }

        $platform_is_multitenant = PHS::is_multi_tenant();

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Changes saved to database.'));
        }
        if (PHS_Params::_g('account_created', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('User account created.'));
        }
        if (PHS_Params::_g('unknown_account', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Account not found in database.'));
        }
        if (PHS_Params::_g('cannot_edit_account', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have enough rights to edit the account.'));
        }

        $account_tenant_ids = [];
        $all_tenants_arr = [];
        $ut_table_name = 'users_tenants';
        if ($platform_is_multitenant) {
            if (!($account_tenant_ids = $this->_account_tenants_model->get_account_tenants_as_ids_array($current_user['id']))) {
                $account_tenant_ids = [];
            }
            if (!($all_tenants_arr = $this->_tenants_model->get_tenants_as_key_val())) {
                $all_tenants_arr = [];
            }

            if (!empty($account_tenant_ids)) {
                $new_all_tenants_arr = [];
                foreach ($account_tenant_ids as $t_id) {
                    if (empty($all_tenants_arr[$t_id])) {
                        continue;
                    }
                    $new_all_tenants_arr[$t_id] = $all_tenants_arr[$t_id];
                }

                $all_tenants_arr = $new_all_tenants_arr;
            }

            if (!($ut_flow = $this->_account_tenants_model->fetch_default_flow_params(['table_name' => 'users_tenants']))
             || !($ut_table_name = $this->_account_tenants_model->get_flow_table_name($ut_flow))) {
                $ut_table_name = 'users_tenants';
            }
        }

        $this->_tenants_list_arr = $all_tenants_arr;

        $can_export_accounts = $this->_admin_plugin->can_admin_export_accounts();
        $account_lockout_enabled = $this->_accounts_plugin->lockout_is_enabled();

        $accounts_model = $this->_paginator_model;

        if (!($u_flow = $accounts_model->fetch_default_flow_params(['table_name' => 'users']))
            || !($u_table_name = $accounts_model->get_flow_table_name($u_flow))) {
            $u_table_name = 'users';
        }

        $list_arr = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $accounts_model::STATUS_DELETED];
        if ($platform_is_multitenant
         && !empty($account_tenant_ids)) {
            $list_arr['fields'][] = ['raw' => '(`'.$u_table_name.'`.is_multitenant = 1 OR '
                                              .'EXISTS (SELECT 1 FROM `'.$ut_table_name.'` WHERE `'.$ut_table_name.'`.account_id = `'.$u_table_name.'`.id '
                                              .' AND `'.$ut_table_name.'`.tenant_id IN ('.implode(',', $account_tenant_ids).')))', ];
        }

        $list_arr['flags'] = ['include_account_details'];

        $flow_params = [
            'listing_title'        => $this->_pt('List Users'),
            'term_singular'        => $this->_pt('user'),
            'term_plural'          => $this->_pt('users'),
            'initial_list_arr'     => $list_arr,
            'after_table_callback' => [$this, 'after_table_callback'],
        ];

        if (!($users_levels = $this->_paginator_model->get_levels_as_key_val())) {
            $users_levels = [];
        }
        if (!($users_statuses = $this->_paginator_model->get_statuses_as_key_val())) {
            $users_statuses = [];
        }

        if (!empty($users_levels)) {
            $users_levels = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $users_levels);
        }
        if (!empty($users_statuses)) {
            $users_statuses = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $users_statuses);
        }
        $all_tenants_filter = [];
        if (!empty($all_tenants_arr)) {
            $all_tenants_filter = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $all_tenants_arr);
        }

        if (isset($users_statuses[$accounts_model::STATUS_DELETED])) {
            unset($users_statuses[$accounts_model::STATUS_DELETED]);
        }

        $bulk_actions = [
            [
                'display_name'    => $this->_pt('Inactivate'),
                'action'          => 'bulk_inactivate',
                'js_callback'     => 'phs_users_list_bulk_inactivate',
                'checkbox_column' => 'id',
            ],
            [
                'display_name'    => $this->_pt('Activate'),
                'action'          => 'bulk_activate',
                'js_callback'     => 'phs_users_list_bulk_activate',
                'checkbox_column' => 'id',
            ],
            [
                'display_name'    => $this->_pt('Delete'),
                'action'          => 'bulk_delete',
                'js_callback'     => 'phs_users_list_bulk_delete',
                'checkbox_column' => 'id',
            ],
        ];

        if ($account_lockout_enabled) {
            $bulk_actions[] = [
                'display_name'    => $this->_pt('Reset account locking'),
                'action'          => 'bulk_reset_account_locking',
                'js_callback'     => 'phs_users_list_bulk_reset_account_locking',
                'checkbox_column' => 'id',
            ];
        }
        if ($can_export_accounts) {
            $bulk_actions[] = [
                'display_name'    => $this->_pt('Export selected'),
                'action'          => 'bulk_export_selected',
                'js_callback'     => 'phs_users_list_bulk_export_selected',
                'checkbox_column' => 'id',
            ];
            $bulk_actions[] = [
                'display_name'    => $this->_pt('Export ALL accounts'),
                'action'          => 'bulk_export_all',
                'js_callback'     => 'phs_users_list_bulk_export_all',
                'checkbox_column' => 'id',
            ];
        }

        $lock_options_arr = [
            -1 => $this->_pt(' - Choose - '),
            0  => $this->_pt('Has login attempts'),
            1  => $this->_pt('IS or WAS locked'),
            2  => $this->_pt('IS locked'),
        ];

        $filters_arr = [
            [
                'display_name'        => $this->_pt('IDs'),
                'display_hint'        => $this->_pt('Comma separated ids'),
                'display_placeholder' => $this->_pt('eg. 1,2,3'),
                'var_name'            => 'fids',
                'record_field'        => 'id',
                'record_check'        => ['check' => 'IN', 'value' => '(%s)'],
                'type'                => PHS_Params::T_ARRAY,
                'extra_type'          => ['type' => PHS_Params::T_INT],
                'default'             => [],
                'extra_records_style' => 'vertical-align:middle;',
            ],
            [
                'display_name'        => $this->_pt('Nickname'),
                'display_hint'        => $this->_pt('All records containing this value'),
                'var_name'            => 'fnick',
                'record_field'        => 'nick',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'extra_records_style' => 'vertical-align:middle;',
            ],
            [
                'display_name'        => $this->_pt('Email'),
                'display_hint'        => $this->_pt('All records containing this value'),
                'var_name'            => 'femail',
                'record_field'        => 'email',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'extra_records_style' => 'vertical-align:middle;',
            ],
        ];

        if ($account_lockout_enabled) {
            $filters_arr = array_merge($filters_arr, [
                [
                    'display_name'  => $this->_pt('Account lockout'),
                    'display_hint'  => $this->_pt('Select only accounts with specific account lockout conditions'),
                    'var_name'      => 'fis_paid',
                    'switch_filter' => [
                        0 => [
                            'raw_query' => '`'.$u_table_name.'`.failed_logins > 0',
                        ],
                        1 => [
                            'raw_query' => '`'.$u_table_name.'`.locked_date IS NOT NULL',
                        ],
                        2 => [
                            'raw_query' => '`'.$u_table_name.'`.locked_date IS NOT NULL AND `'.$u_table_name.'`.locked_date > NOW()',
                        ],
                    ],
                    'type'       => PHS_Params::T_INT,
                    'default'    => -1,
                    'values_arr' => $lock_options_arr,
                ],
            ]);
        }

        $filters_arr = array_merge($filters_arr, [
            [
                'display_name'        => $this->_pt('Level'),
                'var_name'            => 'flevel',
                'record_field'        => 'level',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'values_arr'          => $users_levels,
                'extra_records_style' => 'vertical-align:middle;',
            ],
            [
                'display_name'        => $this->_pt('Status'),
                'var_name'            => 'fstatus',
                'record_field'        => 'status',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'values_arr'          => $users_statuses,
                'extra_records_style' => 'vertical-align:middle;',
            ],
        ]);

        if ($platform_is_multitenant) {
            $filters_arr[] = [
                'display_name'        => $this->_pt('Tenant'),
                'var_name'            => 'ftenant',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'values_arr'          => $all_tenants_filter,
                'extra_records_style' => 'vertical-align:middle;',
                'raw_query'           => '(`'.$u_table_name.'`.is_multitenant = 1 OR '
                                         .' EXISTS (SELECT 1 FROM `'.$ut_table_name.'` '
                                         .' WHERE `'.$ut_table_name.'`.account_id = `'.$u_table_name.'`.id AND `'.$ut_table_name.'`.tenant_id = \'%s\' LIMIT 0, 1)'
                                         .')',
            ];
        }

        $columns_arr = [
            [
                'column_title'              => '#',
                'record_field'              => 'id',
                'checkbox_record_index_key' => [
                    'key'  => 'id',
                    'type' => PHS_Params::T_INT,
                ],
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'min-width:50px;max-width:80px;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'     => $this->_pt('Nickname'),
                'record_field'     => 'nick',
                'display_callback' => [$this, 'display_nickname'],
            ],
            [
                'column_title'        => $this->_pt('Email'),
                'record_field'        => 'email',
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
        ];

        if ($platform_is_multitenant) {
            $columns_arr = array_merge($columns_arr, [
                [
                    'column_title'        => $this->_pt('Tenants'),
                    'record_field'        => 'status',
                    'sortable'            => false,
                    'display_callback'    => [$this, 'display_tenants'],
                    'extra_style'         => 'text-align:center;',
                    'extra_records_style' => 'text-align:center;',
                ],
            ]);
        }

        $columns_arr = array_merge($columns_arr, [
            [
                'column_title'        => $this->_pt('Status'),
                'record_field'        => 'status',
                'display_key_value'   => $users_statuses,
                'invalid_value'       => $this->_pt('Undefined'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Level'),
                'record_field'        => 'level',
                'display_key_value'   => $users_levels,
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
        ]);

        if ($account_lockout_enabled) {
            $columns_arr = array_merge($columns_arr, [
                [
                    'column_title'        => $this->_pt('Locked?'),
                    'record_field'        => 'failed_logins',
                    'display_callback'    => [$this, 'display_locked'],
                    'invalid_value'       => $this->_pt('No'),
                    'date_format'         => 'd-m-Y H:i',
                    'extra_style'         => 'text-align:center;width:130px;',
                    'extra_records_style' => 'text-align:center;',
                ],
            ]);
        }

        $columns_arr = array_merge($columns_arr, [
            [
                'column_title'        => $this->_pt('Last Login'),
                'record_field'        => 'lastlog',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('Never'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
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
                'extra_style'         => 'width:150px;',
                'extra_records_style' => 'text-align:right;',
                'sortable'            => false,
            ],
        ]);

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'users']);
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
                break;

            case 'bulk_reset_account_locking':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Reseted account locking for required accounts with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Resetting account locking for selected accounts failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed resetting account locking for all selected accounts. Accounts for which action failed are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!PHS::user_logged_in()
                 || !$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                 || empty($scope_arr[$scope_key])
                 || !is_array($scope_arr[$scope_key])
                 || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $account_id) {
                    if (!$this->_paginator_model->reset_account_locking($account_id)) {
                        $remaining_ids_arr[] = $account_id;
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

            case 'bulk_activate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required accounts activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating selected accounts failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed activating all selected accounts. Accounts which failed activation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                 || empty($scope_arr[$scope_key])
                 || !is_array($scope_arr[$scope_key])
                 || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $account_id) {
                    if (!$this->_paginator_model->activate_account($account_id)) {
                        $remaining_ids_arr[] = $account_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required accounts inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating selected accounts failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed inactivating all selected accounts. Accounts which failed inactivation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                 || empty($scope_arr[$scope_key])
                 || !is_array($scope_arr[$scope_key])
                 || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $account_id) {
                    if (!$this->_paginator_model->inactivate_account($account_id)) {
                        $remaining_ids_arr[] = $account_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required accounts deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected accounts failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected accounts. Accounts which failed deletion are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                 || empty($scope_arr[$scope_key])
                 || !is_array($scope_arr[$scope_key])
                 || !($scope_all_key = @sprintf($ids_all_checkbox_name, 'id'))) {
                    return true;
                }

                $remaining_ids_arr = [];
                foreach ($scope_arr[$scope_key] as $account_id) {
                    if (!$this->_paginator_model->delete_account($account_id)) {
                        $remaining_ids_arr[] = $account_id;
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

            case 'bulk_export_selected':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required accounts exported with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Exporting selected accounts failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed exporting all selected accounts. Accounts which failed export are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_export_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($scope_key = @sprintf($ids_checkboxes_name, 'id'))
                 || empty($scope_arr[$scope_key])
                 || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                $export_params = [];
                $export_params['export_file_name'] = 'accounts_export_'.date('YmdHi').'.json';

                if (!$this->_accounts_plugin->export_account_ids($scope_arr[$scope_key], $export_params)) {
                    $action_result_params['action_result'] = 'failed';
                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                }
                break;

            case 'bulk_export_all':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required accounts exported with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Exporting selected accounts failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed exporting all selected accounts. Accounts which failed export are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_export_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                $export_params = [];
                $export_params['export_file_name'] = 'accounts_export_all_'.date('YmdHi').'.json';

                if (!$this->_accounts_plugin->export_account_ids([], $export_params)) {
                    $action_result_params['action_result'] = 'failed';
                }
                break;

            case 'sublogin_account':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Logged in with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Logging in as this account failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_login_subaccounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($account_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot login as this account. Account not found.'));

                    return false;
                }

                if (!$this->_paginator_model->is_active($account_arr)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Account not active. Activate account first.'));

                    return false;
                }

                if (!$this->_paginator_model->login($account_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    // If we logged in with success redirect at main page as we don't know if new logged-in user has rights to view this page...
                    header('Location: '.PHS::url());
                    exit;
                }
                break;

            case 'reset_account_locking':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Account locking reset with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Resetting account locking failed. Please try again.'));
                    }

                    return true;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($account_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot reset account locking. Account not found.'));

                    return false;
                }

                if (!($current_user = PHS::user_logged_in())
                 || !$this->_admin_plugin->can_admin_manage_accounts()
                 || !$this->_paginator_model->can_manage_account($current_user, $account_arr)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage this account.'));

                    return false;
                }

                if (!$this->_paginator_model->reset_account_locking($account_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'resend_registration_email':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Re-sent registration email with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Re-sending registration email failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage accounts.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($account_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot re-send registration email. Account not found.'));

                    return false;
                }

                if (!($send_result = $this->_paginator_model->send_after_registration_email($account_arr, ['send_confirmation_email' => true]))
                 || !is_array($send_result)
                 || !empty($send_result['has_error'])) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'activate_account':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Account activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating account failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage accounts.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($account_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot activate account. Account not found.'));

                    return false;
                }

                if (!$this->_paginator_model->activate_account($account_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'inactivate_account':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Account inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating account failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage accounts.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($account_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate account. Account not found.'));

                    return false;
                }

                if (!$this->_paginator_model->inactivate_account($account_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'delete_account':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Account deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting account failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_accounts()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage accounts.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($account_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete account. Account not found.'));

                    return false;
                }

                if (!$this->_paginator_model->delete_account($account_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_nickname($params)
    {
        if (empty($params)
            || !is_array($params)
            || empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        $return_str = '<strong>'.$params['preset_content'].'</strong>';

        $name_str = '';
        if (!empty($params['record']['users_details_title'])
         || !empty($params['record']['users_details_fname'])
         || !empty($params['record']['users_details_lname'])) {
            if (!empty($params['record']['users_details_title'])) {
                $name_str .= $params['record']['users_details_title'].' ';
            }
            if (!empty($params['record']['users_details_fname'])) {
                $name_str .= $params['record']['users_details_fname'].' ';
            }
            if (!empty($params['record']['users_details_lname'])) {
                $name_str .= $params['record']['users_details_lname'].' ';
            }

            if ($name_str !== '') {
                $name_str = '<br/>'.$name_str;
            }
        }

        return $return_str.$name_str;
    }

    public function display_tenants($params)
    {
        if (empty($params)
            || empty($params['record']['id'])) {
            return false;
        }

        if (!empty($params['record']['is_multitenant'])) {
            return $this->_pt('ALL');
        }

        if (!($tenants_ids = $this->_account_tenants_model->get_account_tenants_as_ids_array($params['record']['id']))) {
            return $this->_pt('ALL');
        }

        $result_str = '';
        foreach ($tenants_ids as $t_id) {
            if (empty($this->_tenants_list_arr[$t_id])) {
                continue;
            }

            $result_str .= ($result_str !== '' ? ', ' : '').$this->_tenants_list_arr[$t_id];
        }

        return $result_str !== '' ? $result_str : $this->_pt('ALL');
    }

    public function display_locked($params)
    {
        if (empty($params)
            || !is_array($params)
            || empty($params['record']) || !is_array($params['record'])
            || !($account_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        $paginator_obj = $this->_paginator;

        $pretty_params = [];
        $pretty_params['date_format'] = (!empty($params['column']['date_format']) ? $params['column']['date_format'] : false);
        $pretty_params['request_render_type'] = (!empty($params['request_render_type']) ? $params['request_render_type'] : false);

        $cell_str = (empty($account_arr['locked_date']) ? $this->_pt('No')
            : ($this->_paginator_model->is_locked($account_arr)
                ? '<span class="text-danger font-weight-bold">'.$this->_pt('YES').'</span>' : $this->_pt('No')).'<br/> '
            .$this->_paginator->pretty_date_independent($account_arr['locked_date'], $pretty_params));

        if (empty($params['record']['failed_logins'])) {
            $params['record']['failed_logins'] = 0;
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $paginator_obj::CELL_RENDER_JSON:
                case $paginator_obj::CELL_RENDER_TEXT:
                    $cell_str = strip_tags($cell_str).' ('.$params['record']['failed_logins'].')';
                    break;

                case $paginator_obj::CELL_RENDER_HTML:
                    $cell_str .= '<br/>'.$this->_pt('%s failures', $params['record']['failed_logins']);
                    break;
            }
        }

        return $cell_str;
    }

    public function display_actions($params)
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        if (!($current_user = PHS::user_logged_in())
         || empty($params) || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || !($account_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        if (!$this->_admin_plugin->can_admin_manage_accounts()) {
            return '-';
        }

        $is_inactive = $this->_paginator_model->is_inactive($account_arr);
        $is_active = $this->_paginator_model->is_active($account_arr);
        $can_manage_account = $this->_paginator_model->can_manage_account($current_user, $account_arr);

        ob_start();

        if ($can_manage_account) {
            if ($is_active
                && $this->_admin_plugin->can_admin_login_subaccounts()) {
                ?>
                <a href="javascript:void(0)"
                   onclick="phs_users_list_sublogin_account( '<?php echo $account_arr['id']; ?>' )"
                ><i class="fa fa-sign-in action-icons" title="<?php echo $this->_pt('Change login to this account'); ?>"></i></a>
                <?php
            }

            if ($is_inactive || $is_active) {
                ?>
                <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'users'],
                    ['uid' => $account_arr['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
                ><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt('Edit account'); ?>"></i></a>
                <?php
            }

            if ($this->_accounts_plugin->lockout_is_enabled()) {
                ?>
                <a href="javascript:void(0)"
                    onclick="phs_users_list_reset_account_locking( '<?php echo $account_arr['id']; ?>' )"
                ><i class="fa fa-unlock action-icons" title="<?php echo $this->_pt('Reset account locking'); ?>"></i></a>
                <?php
            }
        }

        if ($this->_paginator_model->needs_after_registration_email($account_arr)) {
            ?>
            <a href="javascript:void(0)"
               onclick="phs_users_list_resend_registration_email( '<?php echo $account_arr['id']; ?>' )"
            ><i class="fa fa-share-square-o action-icons" title="<?php echo $this->_pt('Re-send registration email'); ?>"></i></a>
            <?php
        }

        if ($is_inactive) {
            ?>
            <a href="javascript:void(0)"
               onclick="phs_users_list_activate_account( '<?php echo $account_arr['id']; ?>' )"
            ><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt('Activate account'); ?>"></i></a>
            <?php
        }
        if ($is_active) {
            ?>
            <a href="javascript:void(0)"
               onclick="phs_users_list_inactivate_account( '<?php echo $account_arr['id']; ?>' )"
            ><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt('Inactivate account'); ?>"></i></a>
            <?php
        }

        if (!$this->_paginator_model->is_deleted($account_arr)) {
            ?>
            <br/>
            <a href="javascript:void(0)"
               onclick="phs_users_list_delete_account( '<?php echo $account_arr['id']; ?>' )"
            ><i class="fa fa-times-circle-o action-icons" style="color:red;" title="<?php echo $this->_pt('Delete account'); ?>"></i></a>
            <?php
        }

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
        function phs_users_list_sublogin_account( id )
        {
            if( confirm( "<?php echo $this->_pte('Are you sure you want to change login as this account?', '"'); ?>" ) )
            {
                <?php
                $url_params = [];
        $url_params['action'] = [
            'action'        => 'sublogin_account',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_users_list_reset_account_locking( id )
        {
            if( confirm( "<?php echo $this->_pte('Are you sure you want to reset account locking for this account?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'reset_account_locking',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_users_list_resend_registration_email( id )
        {
            if( confirm( "<?php echo $this->_pte('Are you sure you want to re-send registration email for this account?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'resend_registration_email',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_users_list_activate_account( id )
        {
            if( confirm( "<?php echo $this->_pte('Are you sure you want to activate this account?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'activate_account',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_users_list_inactivate_account( id )
        {
            if( confirm( "<?php echo $this->_pte('Are you sure you want to inactivate this account?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'inactivate_account',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_users_list_delete_account( id )
        {
            if( confirm( "<?php echo $this->_pte('Are you sure you want to DELETE this account?', '"'); ?>" + "\n" +
                         "<?php echo $this->_pte('NOTE: You cannot undo this action!', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'delete_account',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }

        function phs_users_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_users_list_bulk_activate()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte('Please select accounts you want to activate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf($this->_pte('Are you sure you want to activate %s accounts?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_users_list_bulk_inactivate()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte('Please select accounts you want to inactivate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf($this->_pte('Are you sure you want to inactivate %s accounts?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_users_list_bulk_delete()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte('Please select accounts you want to delete first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf($this->_pte('Are you sure you want to DELETE %s accounts?', '"'), '" + total_checked + "'); ?>" + "\n" +
                         "<?php echo $this->_pte('NOTE: You cannot undo this action!', '"'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_users_list_bulk_reset_account_locking()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte('Please select accounts you want to reset locking for first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf($this->_pte('Are you sure you want to reset locking for %s accounts?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_users_list_bulk_export_selected()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select accounts you want to export first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to export %s accounts?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }
        function phs_users_list_bulk_export_all()
        {
            if( !confirm( "<?php echo self::_e($this->_pt('Are you sure you want to export ALL accounts?'), '"'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
