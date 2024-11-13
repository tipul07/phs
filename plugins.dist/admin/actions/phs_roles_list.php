<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\accounts\models\PHS_Model_Accounts;

/** @property PHS_Model_Roles $_paginator_model */
class PHS_Action_Roles_list extends PHS_Action_Generic_list
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
             && !($this->_paginator_model = PHS_Model_Roles::get_instance()))
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

        if (!$this->_admin_plugin->can_admin_list_roles()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params() : ?array
    {
        PHS::page_settings('page_title', $this->_pt('Roles List'));

        $list_arr = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $this->_paginator_model::STATUS_DELETED];

        $flow_params = [
            'listing_title'        => $this->_pt('Roles List'),
            'term_singular'        => $this->_pt('role'),
            'term_plural'          => $this->_pt('roles'),
            'initial_list_arr'     => $list_arr,
            'after_table_callback' => [$this, 'after_table_callback'],
        ];

        if (PHS_Params::_g('unknown_role', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid role or role was not found in database.'));
        }
        if (PHS_Params::_g('role_added', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Role details saved in database.'));
        }

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }

        if (!empty($statuses_arr)) {
            $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        }

        if (isset($statuses_arr[$this->_paginator_model::STATUS_DELETED])) {
            unset($statuses_arr[$this->_paginator_model::STATUS_DELETED]);
        }

        if (!can(PHS_Roles::ROLEU_MANAGE_ROLES)) {
            $bulk_actions = null;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Inactivate'),
                    'action'          => 'bulk_inactivate',
                    'js_callback'     => 'phs_roles_list_bulk_inactivate',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Activate'),
                    'action'          => 'bulk_activate',
                    'js_callback'     => 'phs_roles_list_bulk_activate',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Delete'),
                    'action'          => 'bulk_delete',
                    'js_callback'     => 'phs_roles_list_bulk_delete',
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
                'display_callback' => [$this, 'display_role_name'],
            ],
            [
                'column_title'        => $this->_pt('Slug'),
                'record_field'        => 'slug',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Status'),
                'record_field'        => 'status',
                'display_key_value'   => $statuses_arr,
                'invalid_value'       => $this->_pt('Undefined'),
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

        if (can(PHS_Roles::ROLEU_MANAGE_ROLES)) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'roles_list']);
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

    public function display_role_name($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        return '<strong>'.$params['preset_content'].'</strong>'
               .(!empty($params['record']['description']) ? '<br/><small>'.$params['record']['description'].'</small>' : '');
    }

    public function display_actions($params)
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        if (!$this->_admin_plugin->can_admin_manage_roles()) {
            return '-';
        }

        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || !($role_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        $is_inactive = $this->_paginator_model->is_inactive($role_arr);
        $is_active = $this->_paginator_model->is_active($role_arr);

        ob_start();
        if ($is_inactive || $is_active) {
            ?>
            <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'role_edit'],
                ['rid' => $role_arr['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
            ><i class="fa fa-pencil-square-o action-icons" title="<?php echo form_str($this->_pt('Edit role')); ?>"></i></a>
            <?php
        }
        if ($is_inactive) {
            ?>
            <a href="javascript:void(0)" onclick="phs_roles_list_activate_role( '<?php echo $role_arr['id']; ?>' )"
            ><i class="fa fa-play-circle-o action-icons" title="<?php echo form_str($this->_pt('Activate role')); ?>"></i></a>
            <?php
        }
        if ($is_active) {
            ?>
            <a href="javascript:void(0)" onclick="phs_roles_list_inactivate_role( '<?php echo $role_arr['id']; ?>' )"
            ><i class="fa fa-pause-circle-o action-icons" title="<?php echo form_str($this->_pt('Inactivate role')); ?>"></i></a>
            <?php
        }

        if (!$this->_paginator_model->is_deleted($role_arr)
         && !$this->_paginator_model->is_predefined($role_arr)) {
            ?>
            <a href="javascript:void(0)" onclick="phs_roles_list_delete_role( '<?php echo $role_arr['id']; ?>' )"
            ><i class="fa fa-times-circle-o action-icons" title="<?php echo form_str($this->_pt('Delete role')); ?>"></i></a>
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
        function phs_roles_list_activate_role( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to activate this role?', '"'); ?>" ) )
            {
                <?php
                $url_params = [];
        $url_params['action'] = [
            'action'        => 'activate_role',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_roles_list_inactivate_role( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to inactivate this role?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'inactivate_role',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_roles_list_delete_role( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to DELETE this role?', '"'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'delete_role',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }

        function phs_roles_list_get_checked_ids_count()
        {
            const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_roles_list_bulk_activate()
        {
            const total_checked = phs_roles_list_get_checked_ids_count();

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

        function phs_roles_list_bulk_inactivate()
        {
            const total_checked = phs_roles_list_get_checked_ids_count();

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

        function phs_roles_list_bulk_delete()
        {
            const total_checked = phs_roles_list_get_checked_ids_count();

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
