<?php
namespace phs\plugins\admin\actions\tenants;

use phs\PHS;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Tenants;
use phs\plugins\accounts\models\PHS_Model_Accounts;

/** @property \phs\system\core\models\PHS_Model_Tenants $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    /** @var null|\phs\plugins\admin\PHS_Plugin_Admin */
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    /** @var null|\phs\plugins\accounts\models\PHS_Model_Accounts */
    private ?PHS_Model_Accounts $_accounts_model = null;

    public function load_depencies()
    {
        if ((empty($this->_admin_plugin)
             && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
         || (empty($this->_accounts_model)
             && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
         || (empty($this->_paginator_model)
             && !($this->_paginator_model = PHS_Model_Tenants::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        PHS::page_settings('page_title', $this->_pt('Tenants List'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        if (!($current_user = PHS::user_logged_in())) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You should login first...'));

            return false;
        }

        if (!$this->_admin_plugin->can_admin_list_tenants($current_user)) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to list tenants.'));

            return false;
        }

        $tenants_model = $this->_paginator_model;

        $list_arr = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $tenants_model::STATUS_DELETED];

        $flow_params = [
            'listing_title'          => $this->_pt('Tenants List'),
            'term_singular'          => $this->_pt('tenant'),
            'term_plural'            => $this->_pt('tenants'),
            'initial_list_arr'       => $list_arr,
            'after_table_callback'   => [$this, 'after_table_callback'],
            'after_filters_callback' => [$this, 'after_filters_callback'],
        ];

        if (PHS_Params::_g('unknown_tenant', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid tenant or tenant was not found in database.'));
        }
        if (PHS_Params::_g('tenant_added', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Tenant details saved in database.'));
        }

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }

        if (!empty($statuses_arr)) {
            $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        }

        if (isset($statuses_arr[$tenants_model::STATUS_DELETED])) {
            unset($statuses_arr[$tenants_model::STATUS_DELETED]);
        }

        if (!can(PHS_Roles::ROLEU_MANAGE_ROLES)) {
            $bulk_actions = false;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Inactivate'),
                    'action'          => 'bulk_inactivate',
                    'js_callback'     => 'phs_tenants_list_bulk_inactivate',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Activate'),
                    'action'          => 'bulk_activate',
                    'js_callback'     => 'phs_tenants_list_bulk_activate',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Delete'),
                    'action'          => 'bulk_delete',
                    'js_callback'     => 'phs_tenants_list_bulk_delete',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt('Name'),
                'display_hint' => $this->_pt('All records containing this value'),
                'var_name'     => 'fname',
                'record_field' => 'name',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Domain'),
                'display_hint' => $this->_pt('All records containing this value'),
                'var_name'     => 'fdomain',
                'record_field' => 'domain',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Directory'),
                'display_hint' => $this->_pt('All records containing this value'),
                'var_name'     => 'fdirectory',
                'record_field' => 'directory',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Identifier'),
                'display_hint' => $this->_pt('All records containing this value'),
                'var_name'     => 'fidentifier',
                'record_field' => 'identifier',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Status'),
                'var_name'     => 'fstatus',
                'record_field' => 'status',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $statuses_arr,
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
                'column_title'     => $this->_pt('Name'),
                'record_field'     => 'name',
                'display_callback' => [$this, 'display_tenant_name'],
            ],
            [
                'column_title'        => $this->_pt('Domain'),
                'record_field'        => 'domain',
                'default'             => '',
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Directory'),
                'record_field'        => 'directory',
                'default'             => '',
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Identifier'),
                'record_field'        => 'identifier',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Default'),
                'record_field'        => 'is_default',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_key_value'   => [
                    0 => $this->_pt('No'),
                    1 => $this->_pt('Yes'),
                ],
            ],
            [
                'column_title'          => $this->_pt('Status'),
                'record_field'          => 'status',
                'display_key_value'     => $statuses_arr,
                'invalid_value'         => $this->_pt('Undefined'),
                'extra_classes'         => 'status_th',
                'extra_records_classes' => 'status',
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

        if (can(PHS_Roles::ROLEU_TENANTS_MANAGE)) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'tenants']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['bulk_actions'] = $bulk_actions;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    /**
     * Manages actions to be taken for current listing
     *
     * @param array $action Action details array
     *
     * @return array|bool Returns true if no error or no action taken, false if there was an error while taking action or an action array in case action was taken (with success or not)
     */
    public function manage_action($action)
    {
        $this->reset_error();

        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        $action_result_params = $this->_paginator->default_action_params();

        if (empty($action) || !is_array($action)
            || empty($action['action'])) {
            return $action_result_params;
        }

        if (!$this->_admin_plugin->can_admin_manage_tenants()) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

            return false;
        }

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;
                break;

            case 'bulk_activate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required tenants activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating selected tenants failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed activating all selected tenants. Tenants which failed activation are still selected. Please try again.'));
                    }

                    return true;
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
                foreach ($scope_arr[$scope_key] as $tenant_id) {
                    if (!$this->_paginator_model->act_activate($tenant_id)) {
                        $remaining_ids_arr[] = $tenant_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required tenants inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating selected tenants failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed inactivating all selected tenants. Tenants which failed inactivation are still selected. Please try again.'));
                    }

                    return true;
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
                foreach ($scope_arr[$scope_key] as $tenant_id) {
                    if (!$this->_paginator_model->act_inactivate($tenant_id)) {
                        $remaining_ids_arr[] = $tenant_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required tenants deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected tenants failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected tenants. Tenants which failed deletion are still selected. Please try again.'));
                    }

                    return true;
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
                foreach ($scope_arr[$scope_key] as $tenant_id) {
                    if (!$this->_paginator_model->act_delete($tenant_id)) {
                        $remaining_ids_arr[] = $tenant_id;
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

            case 'activate_tenant':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Tenant activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating tenant failed. Please try again.'));
                    }

                    return true;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($tenant_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot activate tenant. Tenant not found.'));

                    return false;
                }

                if (!$this->_paginator_model->act_activate($tenant_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'inactivate_tenant':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Tenant inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating tenant failed. Please try again.'));
                    }

                    return true;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($tenant_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate tenant. Tenant not found.'));

                    return false;
                }

                if (!$this->_paginator_model->act_inactivate($tenant_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'delete_tenant':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Tenant deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting tenant failed. Please try again.'));
                    }

                    return true;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($tenant_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete tenant. Tenant not found.'));

                    return false;
                }

                if (!$this->_paginator_model->act_delete($tenant_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_tenant_name($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        return '<strong>'.$params['preset_content'].'</strong>';
    }

    public function display_actions($params)
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        if (!$this->_admin_plugin->can_admin_manage_tenants()) {
            return '-';
        }

        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || !($tenant_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        $is_inactive = $this->_paginator_model->is_inactive($tenant_arr);
        $is_active = $this->_paginator_model->is_active($tenant_arr);

        ob_start();
        if ($is_inactive || $is_active) {
            ?>
            <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'tenants'],
                ['tid' => $tenant_arr['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
            ><i class="fa fa-pencil-square-o action-icons" title="<?php echo form_str($this->_pt('Edit tenant')); ?>"></i></a>
            <?php
        }
        if ($is_inactive) {
            ?>
            <a href="javascript:void(0)" onclick="phs_tenants_list_activate_tenant( '<?php echo $tenant_arr['id']; ?>' )"
            ><i class="fa fa-play-circle-o action-icons" title="<?php echo form_str($this->_pt('Activate tenant')); ?>"></i></a>
            <?php
        }
        if ($is_active) {
            ?>
            <a href="javascript:void(0)" onclick="phs_tenants_list_inactivate_tenant( '<?php echo $tenant_arr['id']; ?>' )"
            ><i class="fa fa-pause-circle-o action-icons" title="<?php echo form_str($this->_pt('Inactivate tenant')); ?>"></i></a>
            <?php
        }

        if (!$this->_paginator_model->is_deleted($tenant_arr)
         && !$this->_paginator_model->is_default_tenant($tenant_arr)) {
            ?>
            <a href="javascript:void(0)" onclick="phs_tenants_list_delete_tenant( '<?php echo $tenant_arr['id']; ?>' )"
            ><i class="fa fa-times-circle-o action-icons" title="<?php echo form_str($this->_pt('Delete tenant')); ?>"></i></a>
            <?php
        }

        return ob_get_clean();
    }

    public function after_filters_callback($params)
    {
        ob_start();
        ?>
        <div class="p-1">
            <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'add', 'ad' => 'tenants']); ?>"
               class="btn btn-small btn-success" style="color:white;"><i class="fa fa-plus"></i> <?php echo $this->_pt('New Tenant'); ?></a>
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
        function phs_tenants_list_activate_tenant( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to activate this tenant?', '"'); ?>" ) )
            {
                <?php
                $url_params = [];
        $url_params['action'] = [
            'action'        => 'activate_tenant',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_tenants_list_inactivate_tenant( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to inactivate this tenant?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'inactivate_tenant',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_tenants_list_delete_tenant( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to DELETE this tenant?', '"'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'delete_tenant',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }

        function phs_tenants_list_get_checked_ids_count()
        {
            const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_tenants_list_bulk_activate()
        {
            const total_checked = phs_tenants_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select tenants you want to activate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to activate %s tenants?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_tenants_list_bulk_inactivate()
        {
            const total_checked = phs_tenants_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select tenants you want to inactivate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to inactivate %s tenants?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_tenants_list_bulk_delete()
        {
            const total_checked = phs_tenants_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select tenants you want to delete first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to DELETE %s tenants?', '"'), '" + total_checked + "'); ?>" + "\n" +
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
