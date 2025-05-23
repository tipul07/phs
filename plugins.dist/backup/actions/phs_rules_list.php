<?php
namespace phs\plugins\backup\actions;

use phs\PHS;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\backup\PHS_Plugin_Backup;
use phs\libraries\PHS_Action_Generic_list;
use phs\plugins\backup\models\PHS_Model_Rules;

/** @property PHS_Model_Rules $_paginator_model */
class PHS_Action_Rules_list extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Backup $_backup_plugin = null;

    /**
     * @inheritdoc
     */
    public function should_stop_execution() : ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)
            && !can($this->_backup_plugin::ROLEU_LIST_RULES)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params() : ?array
    {
        PHS::page_settings('page_title', $this->_pt('Backup Rules List'));

        $can_manage_rules = can($this->_backup_plugin::ROLEU_MANAGE_RULES);

        if (!($rules_flow = $this->_paginator_model->fetch_default_flow_params(['table_name' => 'backup_rules']))
         || !($rules_table_name = $this->_paginator_model->get_flow_table_name($rules_flow))) {
            $rules_table_name = '';
        }

        $list_arr = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $this->_paginator_model::STATUS_DELETED];

        $flow_params = [
            'term_singular'        => $this->_pt('backup rule'),
            'term_plural'          => $this->_pt('backup rules'),
            'initial_list_arr'     => $list_arr,
            'after_table_callback' => [$this, 'after_table_callback'],
            'listing_title'        => $this->_pt('Backup Rules'),
        ];

        if (PHS_Params::_g('unknown_rule', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid backup rule or backup rule not found in database.'));
        }
        if (PHS_Params::_g('rule_added', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Backup rule details saved in database.'));
        }

        $statuses_arr = $this->_paginator_model->get_statuses_as_key_val() ?: [];
        $rule_days_arr = $this->_paginator_model->get_rule_days() ?: [];

        $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        $rule_days_arr = self::merge_array_assoc([-1 => $this->_pt(' - Choose - ')], $rule_days_arr);

        if (isset($statuses_arr[$this->_paginator_model::STATUS_DELETED])) {
            unset($statuses_arr[$this->_paginator_model::STATUS_DELETED]);
        }

        if (!$can_manage_rules) {
            $bulk_actions = null;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Inactivate'),
                    'action'          => 'bulk_inactivate',
                    'js_callback'     => 'phs_backup_rules_list_bulk_inactivate',
                    'checkbox_column' => 'id', ],
                [
                    'display_name'    => $this->_pt('Activate'),
                    'action'          => 'bulk_activate',
                    'js_callback'     => 'phs_backup_rules_list_bulk_activate',
                    'checkbox_column' => 'id', ],
                [
                    'display_name'    => $this->_pt('Delete'),
                    'action'          => 'bulk_delete',
                    'js_callback'     => 'phs_backup_rules_list_bulk_delete',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt('Title'),
                'display_hint' => $this->_pt('All records containing this value in title field'),
                'var_name'     => 'ftitle',
                'record_field' => 'title',
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
                'extra_style'         => 'min-width:55px;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => function(array $params) : string {
                    return '';
                },
            ],
            [
                'column_title' => $this->_pt('Title'),
                'record_field' => 'title',
            ],
            [
                'column_title'        => $this->_pt('When'),
                'record_field'        => 'hour',
                'display_callback'    => [$this, 'display_backup_rule_when'],
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Where'),
                'record_field'        => 'location',
                'display_callback'    => [$this, 'display_backup_rule_where'],
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('What'),
                'record_field'        => 'target',
                'display_callback'    => [$this, 'display_backup_rule_what'],
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
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
                'column_title'    => $this->_pt('Created'),
                'default_sort'    => 1,
                'record_db_field' => 'cdate',
                'record_field'    => (!empty($rules_table_name) ? '`'.$rules_table_name.'`.' : '').'cdate %s, '
                                  .(!empty($rules_table_name) ? '`'.$rules_table_name.'`.' : '').'title ASC ',
                'display_callback'      => [&$this->_paginator, 'pretty_date'],
                'date_format'           => 'd-m-Y H:i',
                'invalid_value'         => $this->_pt('Invalid'),
                'extra_classes'         => 'date_th',
                'extra_records_classes' => 'date',
            ],
            [
                'column_title'          => $this->_pt('Actions'),
                'display_callback'      => [$this, 'display_actions'],
                'sortable'              => false,
                'extra_style'           => 'width:120px;',
                'extra_classes'         => 'actions_th',
                'extra_records_classes' => 'actions',
            ],
        ];

        if ($can_manage_rules) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'backup', 'a' => 'rules_list']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['bulk_actions'] = $bulk_actions;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    public function manage_action(array $action) : null | bool | array
    {
        $this->reset_error();

        $action_result_params = $this->_paginator->default_action_params();

        if (empty($action['action'])) {
            return $action_result_params;
        }

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;
            case 'bulk_activate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required backup rules activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating selected backup rules failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed activating all selected backup rules. Backup rules which failed activation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
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
                foreach ($scope_arr[$scope_key] as $rule_id) {
                    if (!$this->_paginator_model->act_activate($rule_id)) {
                        $remaining_ids_arr[] = $rule_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required backup rules inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating selected backup rules failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed inactivating all selected backup rules. Backup rules which failed inactivation are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
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
                foreach ($scope_arr[$scope_key] as $rule_id) {
                    if (!$this->_paginator_model->act_inactivate($rule_id)) {
                        $remaining_ids_arr[] = $rule_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required backup rules deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected backup rules failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected backup rules. Backup rules which failed deletion are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
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
                foreach ($scope_arr[$scope_key] as $rule_id) {
                    if (!$this->_paginator_model->act_delete($rule_id)) {
                        $remaining_ids_arr[] = $rule_id;
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

            case 'do_activate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Backup rule activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating backup rule failed. Please try again.'));
                    }

                    return true;
                }

                if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($rule_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot activate backup rule. Backup rule not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_activate($rule_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'do_inactivate':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Backup rule inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating backup rule failed. Please try again.'));
                    }

                    return true;
                }

                if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($rule_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate backup rule. Backup rule not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_inactivate($rule_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'do_delete':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Backup rule deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting backup rule failed. Please try again.'));
                    }

                    return true;
                }

                if (!can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($rule_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete backup rule. Backup rule not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_delete($rule_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_backup_rule_when(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return null;
        }

        $days_arr = $this->_paginator_model->get_rule_days() ?: [];

        $rule_days_arr = $this->_paginator_model->get_rule_days_as_array($params['record']['id']) ?: [];

        $days_str_arr = [];
        foreach ($rule_days_arr as $day) {
            if (empty($days_arr[$day])) {
                continue;
            }

            $days_str_arr[] = $days_arr[$day];
        }

        $days_str_arr = $days_str_arr ? implode(', ', $days_str_arr) : '';

        $hour_str = '';
        if (isset($params['record']['hour'])) {
            $hour_str = ($days_str_arr !== '' ? ' @' : '').$params['record']['hour'].($params['record']['hour'] < 12 ? 'am' : 'pm');
        }

        $delete_str = '';
        if (!empty($params['record']['delete_after_days'])) {
            $delete_str = '<br/>'.$this->_pt('Delete after %s', PHS_Utils::parse_period($params['record']['delete_after_days'] * 86400, ['show_period' => PHS_Utils::PERIOD_DAYS]));
        }

        return $days_str_arr.$hour_str.$delete_str;
    }

    public function display_backup_rule_where(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return null;
        }

        $location_arr = $this->_paginator_model->get_location_for_rule($params['record']) ?: [];
        $location_stats_arr = $this->_paginator_model->get_location_stats_for_rule($params['record']) ?: [];

        $extra_str = '';
        if (empty($location_arr['location_exists'])) {
            $extra_str = ' <i class="fa fa-exclamation-circle status_pending" title="'.$this->_pt('This location doesn\'t exist yet. System will try creating it at first run.').'"></i>';
        } elseif (empty($location_arr['location_is_dir'])) {
            $extra_str = ' <i class="fa fa-exclamation-circle status_rejected" title="'.$this->_pt('This location is not a directory. Backups will not be saved here! Please fix this by editing the rule or fixing directory.').'"></i>';
        }

        return '<span title="'.self::_e($location_arr['full_path'] ?? '').'" class="no-title-skinning">'.($location_arr['location_path'] ?? '-').'</span>'
               .$extra_str
               .(!$location_stats_arr ? ''
                : '<br/>'.$this->_pt('Total: %s, Free: %s', format_filesize($location_stats_arr['total_space'] ?? 0), format_filesize($location_stats_arr['free_space'] ?? 0))
               );
    }

    public function display_backup_rule_what(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return null;
        }

        if (!($targets_arr = $this->_paginator_model->get_targets_as_key_val())) {
            $targets_arr = [];
        }

        if (!($rule_targets_arr = $this->_paginator_model->bits_to_targets_arr($params['record']['target']))) {
            $rule_targets_arr = [];
        }

        $targets_str_arr = [];
        foreach ($rule_targets_arr as $target_id) {
            if (empty($targets_arr[$target_id])) {
                continue;
            }

            $targets_str_arr[] = $targets_arr[$target_id];
        }

        return $targets_str_arr ? implode(', ', $targets_str_arr) : $this->_pt('N/A');
    }

    public function display_actions(array $params) : ?string
    {
        if (!$this->_paginator->is_cell_rendering_for_html($params)
            || !can($this->_backup_plugin::ROLEU_MANAGE_RULES)) {
            return '-';
        }

        if (empty($params['record']) || !is_array($params['record'])
         || !($rule_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        $is_inactive = $this->_paginator_model->is_inactive($rule_arr);
        $is_active = $this->_paginator_model->is_active($rule_arr);

        ob_start();
        if ($is_inactive || $is_active) {
            ?>
            <a href="<?php echo PHS::url(['p' => 'backup', 'a' => 'rule_edit'], ['rid' => $rule_arr['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
                ><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt('Edit backup rule'); ?>"></i></a>
            <?php
        }
        if ($is_inactive) {
            ?>
            <a href="javascript:void(0)"
               onclick="phs_backup_rules_list_activate( '<?php echo $rule_arr['id']; ?>' )"><i class="fa fa-play-circle-o action-icons"
                                                                                                                       title="<?php echo $this->_pt('Activate backup rule'); ?>"></i></a>
            <?php
        }
        if ($is_active) {
            ?>
            <a href="javascript:void(0)"
               onclick="phs_backup_rules_list_inactivate( '<?php echo $rule_arr['id']; ?>' )"><i class="fa fa-pause-circle-o action-icons"
                                                                                                                         title="<?php echo $this->_pt('Inactivate backup rule'); ?>"></i></a>
            <?php
        }

        if (!$this->_paginator_model->is_deleted($rule_arr)) {
            ?>
            <a href="javascript:void(0)"
               onclick="phs_backup_rules_list_delete( '<?php echo $rule_arr['id']; ?>' )"><i class="fa fa-times action-icons"
                                                                                                                     title="<?php echo $this->_pt('Delete backup rule'); ?>"></i></a>
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
        function phs_backup_rules_list_activate( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to activate this backup rule?', '"'); ?>" ) )
            {
                <?php
                $url_params = [];
        $url_params['action'] = [
            'action'        => 'do_activate',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_backup_rules_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to inactivate this backup rule?', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'do_inactivate',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_backup_rules_list_delete( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to DELETE this backup rule?', '"'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
            {
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'do_delete',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }

        function phs_backup_rules_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_backup_rules_list_bulk_activate()
        {
            var total_checked = phs_backup_rules_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select backup rules you want to activate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to activate %s backup rules?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_backup_rules_list_bulk_inactivate()
        {
            var total_checked = phs_backup_rules_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select backup rules you want to inactivate first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to inactivate %s backup rules?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_backup_rules_list_bulk_delete()
        {
            var total_checked = phs_backup_rules_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select backup rules you want to delete first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to DELETE %s backup rules?', '"'), '" + total_checked + "'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }
        </script>
        <?php

        return ob_get_clean();
    }

    protected function _load_dependencies() : bool
    {
        if ((!$this->_paginator_model && !($this->_paginator_model = PHS_Model_Rules::get_instance()))
            || (!$this->_backup_plugin && !($this->_backup_plugin = PHS_Plugin_Backup::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
