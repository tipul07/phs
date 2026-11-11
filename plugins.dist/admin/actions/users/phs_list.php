<?php
namespace phs\plugins\admin\actions\users;

use phs\PHS;
use phs\PHS_Ajax;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Record_data;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\admin\actions\users\api\PHS_Action_Info;
use phs\plugins\accounts\models\PHS_Model_Accounts_tenants;

/**
 * @property PHS_Model_Accounts $_paginator_model
 */
class PHS_Action_List extends PHS_Action_Generic_list
{
    protected null | string | PHS_Instantiable $_paginator_model_class = PHS_Model_Accounts::class;

    #[PHS_Dependency]
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Plugin_Accounts $_accounts_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Model_Accounts_tenants $_account_tenants_model = null;

    #[PHS_Dependency]
    private ?PHS_Model_Tenants $_tenants_model = null;

    private array $_tenants_list_arr = [];

    /**
     * @inheritdoc
     */
    public function should_stop_execution(): ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_admin_plugin->can_admin_list_accounts()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params(): ?array
    {
        PHS::page_settings('page_title', $this->_pt('List Users'));

        $current_user = PHS::user_logged_in();

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
            $account_tenant_ids = $this->_account_tenants_model->get_account_tenants_as_ids_array($current_user['id']) ?: [];
            $all_tenants_arr = $this->_tenants_model->get_tenants_as_key_val() ?: [];

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
        $can_manage_accounts = $this->_admin_plugin->can_admin_manage_accounts();
        $account_lockout_enabled = $this->_accounts_plugin->lockout_is_enabled();

        if (!($u_flow = $this->_paginator_model->fetch_default_flow_params(['table_name' => 'users']))
            || !($u_table_name = $this->_paginator_model->get_flow_table_name($u_flow))) {
            $u_table_name = 'users';
        }

        $list_arr = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $this->_paginator_model::STATUS_DELETED];
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

        $users_levels = $this->_paginator_model->get_levels_as_key_val() ?: [];
        $users_statuses = $this->_paginator_model->get_statuses_as_key_val() ?: [];

        $users_levels_filters = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $users_levels);
        $users_statuses_filters = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $users_statuses);
        $all_tenants_filter = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $all_tenants_arr);

        if (isset($users_statuses[$this->_paginator_model::STATUS_DELETED])) {
            unset($users_statuses[$this->_paginator_model::STATUS_DELETED], $users_statuses_filters[$this->_paginator_model::STATUS_DELETED]);
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
                'values_arr'          => $users_levels_filters,
                'extra_records_style' => 'vertical-align:middle;',
            ],
            [
                'display_name'        => $this->_pt('Status'),
                'var_name'            => 'fstatus',
                'record_field'        => 'status',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'values_arr'          => $users_statuses_filters,
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
                'display_callback' => function(array $params) {
                    $return_str = '<strong>'.($params['preset_content'] ?? '-').'</strong>';

                    $name_str
                        = trim(($params['record']['users_details_title'] ?? '').' '
                        .($params['record']['users_details_fname'] ?? '').' '
                        .($params['record']['users_details_lname'] ?? '').' ');

                    return $return_str.($name_str !== '' ? '<br/>'.$name_str : '');
                },
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
            $this->_default_actions_column_definition(),
        ]);

        $actions_arr = [
            'bulk_export_selected' => [
                'enabled'                => $can_export_accounts,
                'is_bulk'                => true,
                'with_submit_protection' => false,
                'display_name'           => $this->_pt('Export selected'),
                'display_icon'           => 'fa-reply',
                'texts'                  => [
                    'action_success'     => $this->_pt('Required accounts exported with success.'),
                    'action_failed'      => $this->_pt('Exporting selected accounts failed. Please try again.'),
                    'action_failed_some' => $this->_pt('Failed exporting all selected accounts. Accounts for which action failed are still selected. Please try again.'),
                    'nothing_selected'   => $this->_pt('Please select accounts you want to export first.'),
                    'bulk_confirmation'  => $this->_pt('Are you sure you want to export %s accounts?'),
                ],
                'callbacks' => [
                    'bulk_single_action' => true,
                    'can_run'            => function(int | array | PHS_Record_data $record_arr) use ($can_export_accounts): bool {
                        return $can_export_accounts;
                    },
                    'action' => function(array $export_ids) use ($can_export_accounts): bool {
                        $export_params = [];
                        $export_params['export_file_name'] = 'accounts_export_'.date('YmdHi').'.json';

                        return $can_export_accounts
                               && $this->_accounts_plugin->export_account_ids($export_ids, $export_params);
                    },
                ],
            ],
            'bulk_export_all' => [
                'enabled'                => $can_export_accounts,
                'is_bulk'                => true,
                'with_submit_protection' => false,
                'checkbox_column'        => null,
                'display_name'           => $this->_pt('Export ALL accounts'),
                'display_icon'           => 'fa-reply-all',
                'texts'                  => [
                    'action_success'     => $this->_pt('ALL accounts exported with success.'),
                    'action_failed'      => $this->_pt('Exporting ALL accounts failed. Please try again.'),
                    'action_failed_some' => $this->_pt('Failed exporting ALL accounts. Accounts for which action failed are still selected. Please try again.'),
                    'nothing_selected'   => $this->_pt('No accounts to export.'),
                    'bulk_confirmation'  => $this->_pt('Are you sure you want to export ALL accounts?'),
                ],
                'callbacks' => [
                    'require_selection'  => false,
                    'bulk_single_action' => true,
                    'can_run'            => function(int | array | PHS_Record_data $record_arr) use ($can_export_accounts): bool {
                        return $can_export_accounts;
                    },
                    'action' => function() use ($can_export_accounts): bool {
                        $export_params = [];
                        $export_params['export_file_name'] = 'accounts_export_all_'.date('YmdHi').'.json';

                        return $can_export_accounts
                               && $this->_accounts_plugin->export_account_ids([], $export_params);
                    },
                ],
            ],
            'bulk_reset_account_locking' => [
                'enabled'      => $can_manage_accounts && $account_lockout_enabled,
                'is_bulk'      => true,
                'display_name' => $this->_pt('Reset account locking'),
                'display_icon' => 'fa-unlock',
                'texts'        => [
                    'action_success'     => $this->_pt('Reseted account locking for required accounts with success.'),
                    'action_failed'      => $this->_pt('Resetting account locking for selected accounts failed. Please try again.'),
                    'action_failed_some' => $this->_pt('Failed resetting account locking for all selected accounts. Accounts for which action failed are still selected. Please try again.'),
                    'nothing_selected'   => $this->_pt('Please select accounts you want to reset account locking first.'),
                    'bulk_confirmation'  => $this->_pt('Are you sure you want to reset account locking for %s accounts?'),
                ],
                'callbacks' => [
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts, $account_lockout_enabled): bool {
                        return $can_manage_accounts && $account_lockout_enabled;
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts, $account_lockout_enabled): bool {
                        return $can_manage_accounts
                               && $account_lockout_enabled
                               && $this->_paginator_model->reset_account_locking($record_arr);
                    },
                ],
            ],
            'bulk_inactivate' => [
                'enabled'      => $can_manage_accounts,
                'is_bulk'      => true,
                'display_name' => $this->_pt('Inactivate'),
                'display_icon' => 'fa-pause-circle-o',
                'texts'        => [
                    'action_success'     => $this->_pt('Required accounts inactivated with success.'),
                    'action_failed'      => $this->_pt('Inactivating selected accounts failed. Please try again.'),
                    'action_failed_some' => $this->_pt('Failed inactivating all selected accounts. Accounts which failed inactivation are still selected. Please try again.'),
                    'nothing_selected'   => $this->_pt('Please select accounts you want to inactivate first.'),
                    'bulk_confirmation'  => $this->_pt('Are you sure you want to inactivate %s accounts?'),
                ],
                'callbacks' => [
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts;
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts && $this->_paginator_model->inactivate_account($record_arr);
                    },
                ],
            ],
            'bulk_activate' => [
                'enabled'      => $can_manage_accounts,
                'is_bulk'      => true,
                'display_name' => $this->_pt('Activate'),
                'display_icon' => 'fa-play-circle-o',
                'texts'        => [
                    'action_success'     => $this->_pt('Required accounts activated with success.'),
                    'action_failed'      => $this->_pt('Activating selected accounts failed. Please try again.'),
                    'action_failed_some' => $this->_pt('Failed activating all selected accounts. Accounts which failed activating are still selected. Please try again.'),
                    'nothing_selected'   => $this->_pt('Please select accounts you want to activate first.'),
                    'bulk_confirmation'  => $this->_pt('Are you sure you want to activate %s accounts?'),
                ],
                'callbacks' => [
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts;
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts && $this->_paginator_model->activate_account($record_arr);
                    },
                ],
            ],
            'bulk_delete' => [
                'enabled'            => $can_manage_accounts,
                'is_bulk'            => true,
                'display_name'       => $this->_pt('Delete'),
                'display_icon'       => 'fa-times-circle-o',
                'display_icon_style' => 'color: red',
                'texts'              => [
                    'action_success'     => $this->_pt('Required accounts deleted with success.'),
                    'action_failed'      => $this->_pt('Deleting selected accounts failed. Please try again.'),
                    'action_failed_some' => $this->_pt('Failed deleting all selected accounts. Accounts which failed deletion are still selected. Please try again.'),
                    'nothing_selected'   => $this->_pt('Please select accounts you want to delete first.'),
                    'bulk_confirmation'  => $this->_pt('Are you sure you want to delete %s accounts?')."\n"
                                            .$this->_pt('NOTE: You cannot undo this action!'),
                ],
                'callbacks' => [
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts;
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts && $this->_paginator_model->delete_account($record_arr);
                    },
                ],
            ],
            'sublogin_account' => [
                'display_icon'    => 'fa-sign-in',
                'display_tooltip' => $this->_pt('Change login to this account'),
                'callbacks'       => [
                    'should_display' => function(array $render_params) use ($can_manage_accounts): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && $this->_admin_plugin->can_admin_login_subaccounts()
                               && $this->_paginator_model->is_active($render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && $this->_admin_plugin->can_admin_login_subaccounts()
                               && $this->_paginator_model->is_active($record_arr);
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        if (!$can_manage_accounts
                           || !$this->_admin_plugin->can_admin_login_subaccounts()
                           || !$this->_paginator_model->is_active($record_arr)
                           || !$this->_paginator_model->login($record_arr)) {
                            return false;
                        }

                        header('Location: '.PHS::url());
                        exit;
                    },
                ],
                'texts' => [
                    'confirmation'   => $this->_pt('Are you sure you want to change login as this account?'),
                    'action_success' => $this->_pt('Logged in with success.'),
                    'action_failed'  => $this->_pt('Logging in as this account failed. Please try again.'),
                ],
            ],
            'do_edit' => [
                'enabled'         => $can_manage_accounts,
                'display_icon'    => 'fa-pencil-square-o',
                'display_tooltip' => $this->_pt('Edit account'),
                'callbacks'       => [
                    'should_display' => function(array $render_params) use ($can_manage_accounts): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && !$this->_paginator_model->is_deleted($render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && !$this->_paginator_model->is_deleted($record_arr);
                    },
                    'redirect_url' => function(int | array | PHS_Record_data $record_arr): string {
                        return PHS::url(
                            PHS_Action_Edit::class,
                            ['uid' => $record_arr['id'], 'back_page' => $this->_paginator->get_full_url()],
                        );
                    },
                ],
            ],
            'reset_account_locking' => [
                'display_icon'    => 'fa-unlock',
                'display_tooltip' => $this->_pt('Reset account locking'),
                'callbacks'       => [
                    'should_display' => function(array $render_params) use ($current_user, $can_manage_accounts, $account_lockout_enabled): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && $account_lockout_enabled
                               && $current_user
                               && !$this->_paginator_model->is_deleted($render_params['record'])
                               && $this->_paginator_model->can_manage_account($current_user, $render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($current_user, $can_manage_accounts, $account_lockout_enabled): bool {
                        return $can_manage_accounts
                               && $account_lockout_enabled
                               && $current_user
                               && !$this->_paginator_model->is_deleted($record_arr)
                               && $this->_paginator_model->can_manage_account($current_user, $record_arr);
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($current_user, $can_manage_accounts, $account_lockout_enabled): bool {
                        return $can_manage_accounts
                               && $account_lockout_enabled
                               && $current_user
                               && $this->_paginator_model->can_manage_account($current_user, $record_arr)
                               && $this->_paginator_model->reset_account_locking($record_arr);
                    },
                ],
                'texts' => [
                    'confirmation'   => $this->_pt('Are you sure you want to reset account locking for this account?'),
                    'action_success' => $this->_pt('Account locking reset with success.'),
                    'action_failed'  => $this->_pt('Resetting account locking failed. Please try again.'),
                ],
            ],
            'resend_registration_email' => [
                'display_icon'    => 'fa-share-square-o',
                'display_tooltip' => $this->_pt('Re-send registration email'),
                'callbacks'       => [
                    'should_display' => function(array $render_params) use ($can_manage_accounts): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && $this->_paginator_model->needs_after_registration_email($render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && $this->_paginator_model->needs_after_registration_email($record_arr);
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && $this->_paginator_model->needs_after_registration_email($record_arr)
                               && ($send_result = $this->_paginator_model->send_after_registration_email($record_arr, ['send_confirmation_email' => true]))
                               && empty($send_result['has_error']);
                    },
                ],
                'texts' => [
                    'confirmation'   => $this->_pt('Are you sure you want to re-send registration email for this account?'),
                    'action_success' => $this->_pt('Re-sent registration email with success.'),
                    'action_failed'  => $this->_pt('Re-sending registration email failed. Please try again.'),
                ],
            ],
            'do_activate' => [
                'enabled'         => $can_manage_accounts,
                'display_icon'    => 'fa-play-circle-o',
                'display_tooltip' => $this->_pt('Activate account'),
                'callbacks'       => [
                    'should_display' => function(array $render_params) use ($can_manage_accounts): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && $this->_paginator_model->is_inactive($render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && $this->_paginator_model->is_inactive($record_arr);
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts && $this->_paginator_model->activate_account($record_arr);
                    },
                ],
                'texts' => [
                    'confirmation'   => $this->_pt('Are you sure you want to activate this account?'),
                    'action_success' => $this->_pt('Account activated with success.'),
                    'action_failed'  => $this->_pt('Activating account failed. Please try again.'),
                ],
            ],
            'do_inactivate' => [
                'enabled'         => $can_manage_accounts,
                'display_icon'    => 'fa-pause-circle-o',
                'display_tooltip' => $this->_pt('Inactivate account'),
                'callbacks'       => [
                    'should_display' => function(array $render_params) use ($can_manage_accounts): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && $this->_paginator_model->is_active($render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && $this->_paginator_model->is_active($record_arr);
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts && $this->_paginator_model->inactivate_account($record_arr);
                    },
                ],
                'texts' => [
                    'confirmation'   => $this->_pt('Are you sure you want to inactivate this account?'),
                    'action_success' => $this->_pt('Account inactivated with success.'),
                    'action_failed'  => $this->_pt('Inactivating account failed. Please try again.'),
                ],
            ],
            'info' => [
                'br_before_icon'  => true,
                'display_icon'    => 'fa-info-circle',
                'display_tooltip' => $this->_pt('Account details'),
                'callbacks'       => [
                    'javascript_callback' => function(int | array | PHS_Record_data $record_arr): string {
                        return 'phs_users_list_view_info(\''.$record_arr['id'].'\')';
                    },
                ],
            ],
            'do_delete' => [
                'enabled'            => $can_manage_accounts,
                'display_icon'       => 'fa-times-circle-o',
                'display_icon_style' => 'color: red',
                'display_tooltip'    => $this->_pt('Delete account'),
                'callbacks'          => [
                    'should_display' => function(array $render_params) use ($can_manage_accounts): bool {
                        return !empty($render_params['record'])
                               && $can_manage_accounts
                               && !$this->_paginator_model->is_deleted($render_params['record']);
                    },
                    'can_run' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts
                               && !$this->_paginator_model->is_deleted($record_arr);
                    },
                    'action' => function(int | array | PHS_Record_data $record_arr) use ($can_manage_accounts): bool {
                        return $can_manage_accounts && $this->_paginator_model->delete_account($record_arr);
                    },
                ],
                'texts' => [
                    'confirmation' => $this->_pt('Are you sure you want to DELETE this account?')."\n"
                                      .$this->_pt('NOTE: You cannot undo this action!'),
                    'action_success' => $this->_pt('Account deleted with success.'),
                    'action_failed'  => $this->_pt('Deleting account failed. Please try again.'),
                ],
            ],
        ];

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(self::class);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['actions'] = $actions_arr;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    public function display_tenants(array $params): ?string
    {
        if (empty($params['record']['id'])) {
            return null;
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

    public function display_locked(array $params): ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
            || !($account_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        $pretty_params = [];
        $pretty_params['date_format'] = $params['column']['date_format'] ?? null;
        $pretty_params['request_render_type'] = $params['request_render_type'] ?? null;

        $cell_str = (empty($account_arr['locked_date']) ? $this->_pt('No')
            : ($this->_paginator_model->is_locked($account_arr)
                ? '<span class="text-danger font-weight-bold">'.$this->_pt('YES').'</span>' : $this->_pt('No')).'<br/> '
            .$this->_paginator->pretty_date_independent($account_arr['locked_date'], $pretty_params));

        if (empty($params['record']['failed_logins'])) {
            $params['record']['failed_logins'] = 0;
        }

        if ($this->_paginator->is_cell_rendering_for_html($params)) {
            $cell_str .= '<br/>'.$this->_pt('%s failures', $params['record']['failed_logins']);
        } else {
            $cell_str = strip_tags($cell_str).' ('.$params['record']['failed_logins'].')';
        }

        return $cell_str;
    }

    public function after_table_callback(array $params): string
    {
        static $js_functionality = false;

        if ($js_functionality) {
            return '';
        }

        $js_functionality = true;

        ob_start();
        ?>
        <script type="text/javascript">
        function phs_users_list_view_info( id )
        {
            PHS_JSEN.js_messages_hide_all();
            show_submit_protection("<?php echo $this->_pte('Reading account details...'); ?>");

            PHS_JSEN.createAjaxDialog({
                width: 800,
                height: 800,
                suffix: "phs_user_details",
                resizable: true,
                close_outside_click: false,

                title: "<?php echo self::_e($this->_pt('Account details')); ?>",
                method: "GET",
                url: "<?php echo PHS_Ajax::url(PHS_Action_Info::class); ?>",
                url_data: { account_id: id },
                onsuccess: () => {
                    hide_submit_protection();
                },
                onfailed: () => {
                    PHS_JSEN.js_message_error("<?php echo $this->_pte('Error reading account details. Please try again.'); ?>")
                    hide_submit_protection();
                },
            });
        }
        </script>
        <?php

            return ob_get_clean();
    }
}
