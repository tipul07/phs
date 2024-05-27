<?php

namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Migrations;
use phs\plugins\accounts\models\PHS_Model_Accounts;

/** @property PHS_Model_Migrations $_paginator_model */
class PHS_Action_Migrations_list extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Model_Accounts $_accounts_model = null;

    public function load_depencies() : bool
    {
        if ((empty($this->_admin_plugin)
             && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
         || (empty($this->_accounts_model)
             && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
         || (empty($this->_paginator_model)
             && !($this->_paginator_model = PHS_Model_Migrations::get_instance()))
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
        PHS::page_settings('page_title', $this->_pt('Migrations List'));

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
        if (!PHS::user_logged_in()) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You should login first...'));

            return null;
        }

        if (!$this->_admin_plugin->can_admin_list_migrations()) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

            return null;
        }

        $can_manage = $this->_admin_plugin->can_admin_manage_migrations();

        $list_arr = $this->_paginator_model->fetch_default_flow_params(['table_name' => 'phs_migrations']);

        $flow_params = [
            'listing_title'        => $this->_pt('Migration Scripts List'),
            'term_singular'        => $this->_pt('migration'),
            'term_plural'          => $this->_pt('migrations'),
            'initial_list_arr'     => $list_arr,
            'after_table_callback' => [$this, 'after_table_callback'],
        ];

        if (!$can_manage) {
            $bulk_actions = null;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Force run'),
                    'action'          => 'bulk_inactivate',
                    'js_callback'     => 'phs_migrations_list_bulk_inactivate',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name'        => $this->_pt('Plugin'),
                'display_hint'        => $this->_pt('All migrations from specific plugin name'),
                'display_placeholder' => $this->_pt('Plugin of the migration script'),
                'var_name'            => 'fplugin',
                'record_field'        => 'plugin',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
            ],
            [
                'display_name'        => $this->_pt('Script'),
                'display_hint'        => $this->_pt('Migrations with a specific filename'),
                'display_placeholder' => $this->_pt('Migration filename contains'),
                'var_name'            => 'fscript',
                'record_field'        => 'script',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
            ],
            [
                'display_name'        => $this->_pt('Run From'),
                'display_hint'        => $this->_pt('All migration scripts run after this date'),
                'display_placeholder' => $this->_pt('Start date of the script after this date'),
                'var_name'            => 'fstart_date',
                'record_field'        => 'start_run',
                'record_check'        => ['check' => '>=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 00:00:00'],
                'default'             => '',
                'linkage_func'        => 'AND',
            ],
            [
                'display_name'        => $this->_pt('Run Up To'),
                'display_hint'        => $this->_pt('All migration scripts run before this date'),
                'display_placeholder' => $this->_pt('Start date of the script before this date'),
                'var_name'            => 'fend_date',
                'record_field'        => 'start_run',
                'record_check'        => ['check' => '<=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 23:59:59'],
                'default'             => '',
                'linkage_func'        => 'AND',
            ],
            [
                'display_name'  => $this->_pt('Specific state'),
                'display_hint'  => $this->_pt('Select only migration scripts in specific state'),
                'var_name'      => 'fm_state',
                'switch_filter' => [
                    0 => [
                        'raw_query' => 'end_run IS NULL',
                    ],
                    1 => [
                        'raw_query' => 'end_run IS NOT NULL',
                    ],
                    2 => [
                        'raw_query' => 'last_error IS NOT NULL',
                    ],
                ],
                'type'       => PHS_Params::T_INT,
                'default'    => -1,
                'values_arr' => [
                    -1 => $this->_pt(' - Choose - '),
                    0  => $this->_pt('Still running'),
                    1  => $this->_pt('Finished running'),
                    2  => $this->_pt('With errors'),
                ],
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
            ],
            [
                'column_title'        => $this->_pt('At version'),
                'record_field'        => 'run_at_version',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Script'),
                'record_field'        => 'script',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:left;',
            ],
            [
                'column_title'        => $this->_pt('Progress'),
                'record_field'        => 'total_count',
                'display_callback'    => [$this, 'display_progress'],
                'invalid_value'       => $this->_pt('Undefined'),
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Started'),
                'record_field'        => 'start_run',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('No'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('Finished'),
                'record_field'        => 'end_run',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('No'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('Last refresh'),
                'record_field'        => 'last_action',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('No'),
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
                'extra_style'         => 'width:120px;',
                'extra_records_style' => 'text-align:right;',
                'sortable'            => false,
            ],
        ];

        if (can(PHS_Roles::ROLEU_MANAGE_ROLES)) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'migrations_list']);
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

        $can_manage_roles = $this->_admin_plugin->can_admin_manage_roles();

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;
                break;

            case 'bulk_activate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required roles activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating selected roles failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed activating all selected roles. Roles which failed activation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$can_manage_roles) {
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
                foreach ($scope_arr[$scope_key] as $role_id) {
                    if (!$this->_paginator_model->activate_role($role_id)) {
                        $remaining_ids_arr[] = $role_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required roles inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating selected roles failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed inactivating all selected roles. Roles which failed inactivation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$can_manage_roles) {
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
                foreach ($scope_arr[$scope_key] as $role_id) {
                    if (!$this->_paginator_model->inactivate_role($role_id)) {
                        $remaining_ids_arr[] = $role_id;
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
                    if (count($remaining_ids_arr) != count($scope_arr[$scope_key])) {
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
                        PHS_Notifications::add_success_notice($this->_pt('Required roles deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected roles failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected roles. Roles which failed deletion are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$can_manage_roles) {
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
                foreach ($scope_arr[$scope_key] as $role_id) {
                    if (!$this->_paginator_model->delete_role($role_id)) {
                        $remaining_ids_arr[] = $role_id;
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

            case 'activate_role':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Role activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating role failed. Please try again.'));
                    }

                    return true;
                }

                if (!$can_manage_roles) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($role_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot activate role. Role not found.'));

                    return false;
                }

                if (!$this->_paginator_model->activate_role($role_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'inactivate_role':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Role inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating role failed. Please try again.'));
                    }

                    return true;
                }

                if (!$can_manage_roles) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($role_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate role. Role not found.'));

                    return false;
                }

                if (!$this->_paginator_model->inactivate_role($role_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'delete_role':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Role deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting role failed. Please try again.'));
                    }

                    return true;
                }

                if (!$can_manage_roles) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($role_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete role. Role not found.'));

                    return false;
                }

                if (!$this->_paginator_model->delete_role($role_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_progress($params)
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        $migration_arr = $params['record'];

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:

                    return $migration_arr['current_count'].'/'.$migration_arr['total_count'];
            }
        }

        $progress_perc = min(100, (!(float)$migration_arr['total_count'] ? 0 : ceil(($migration_arr['current_count'] * 100) / $migration_arr['total_count'])));

        ob_start();
        ?>
        <div>
            <?php echo $migration_arr['total_count']; ?> / <?php echo $migration_arr['current_count']; ?> (<?php echo $progress_perc; ?>%)<br/>
            <div style="width:100%;height:10px;background-color:red;"><div style="width:<?php echo $progress_perc; ?>%;height:10px;background-color:green;"></div></div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function display_actions($params)
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        if (!$this->_admin_plugin->can_admin_manage_roles()) {
            return '-';
        }

        if (empty($params['record']['id'])) {
            return false;
        }

        $migration_arr = $params['record'];

        $is_finished = $this->_paginator_model->is_finished($migration_arr);
        $is_stalling = $this->_paginator_model->is_stalling($migration_arr);
        $is_running = $this->_paginator_model->is_running($migration_arr);

        ob_start();
        if ($is_finished || $is_stalling) {
            ?>
            <a href="javascript:void(0)" onclick="phs_migrations_list_rerun_migration( '<?php echo $migration_arr['id']; ?>' )"
            ><i class="fa fa-refresh action-icons" title="<?php echo form_str($this->_pt('Run migration again')); ?>"></i></a>
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
        function phs_migrations_list_rerun_migration( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to re-run this migration script?', '"'); ?>" ) )
            {
                <?php
                $url_params = [];
        $url_params['action'] = [
            'action'        => 'rerun_migration',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }

        function phs_migrations_list_get_checked_ids_count()
        {
            const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_migrations_list_bulk_activate()
        {
            const total_checked = phs_migrations_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select roles you want to activate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to activate %s roles?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_migrations_list_bulk_inactivate()
        {
            const total_checked = phs_migrations_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select roles you want to inactivate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to inactivate %s roles?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_migrations_list_bulk_delete()
        {
            const total_checked = phs_migrations_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select roles you want to delete first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to DELETE %s roles?', '"'), '" + total_checked + "'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
                return false;

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
