<?php
namespace phs\plugins\backup\actions;

use phs\PHS;
use phs\PHS_Ajax;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Action_Generic_list;

/** @property \phs\plugins\backup\models\PHS_Model_Results $_paginator_model */
class PHS_Action_Backups_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts */
    private $_accounts_model;

    /** @var \phs\plugins\backup\PHS_Plugin_Backup */
    private $_backup_plugin;

    /** @var \phs\plugins\backup\models\PHS_Model_Rules */
    private $_rules_model;

    /** @var array */
    private $_cuser_details_arr = [];

    public function load_depencies()
    {
        if (empty($this->_backup_plugin)
        && !($this->_backup_plugin = $this->get_plugin_instance())) {
            $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t load backup plugin.'));

            return false;
        }

        if (empty($this->_accounts_model)
        && !($this->_accounts_model = PHS::load_model('accounts', 'accounts'))) {
            $this->set_error(self::ERR_DEPENCIES, $this->_pt('Couldn\'t load accounts model.'));

            return false;
        }

        if (empty($this->_paginator_model)
        && !($this->_paginator_model = PHS::load_model('results', 'backup'))) {
            $this->set_error(self::ERR_DEPENCIES, $this->_pt('Couldn\'t load backup results model.'));

            return false;
        }

        if (empty($this->_rules_model)
        && !($this->_rules_model = PHS::load_model('rules', 'backup'))) {
            $this->set_error(self::ERR_DEPENCIES, $this->_pt('Couldn\'t load backup rules model.'));

            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        PHS::page_settings('page_title', $this->_pt('Backup Results List'));

        if (!($current_user = PHS::user_logged_in())) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You should login first...'));

            return false;
        }

        $backup_plugin = $this->_backup_plugin;

        $can_delete_backups = can($backup_plugin::ROLEU_DELETE_BACKUPS);

        if (!can($backup_plugin::ROLEU_LIST_BACKUPS)
        && !$can_delete_backups) {
            $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to list backup results.'));

            return false;
        }

        $results_model = $this->_paginator_model;

        if (!($rules_flow = $results_model->fetch_default_flow_params(['table_name' => 'backup_rules']))
         || !($rules_table_name = $results_model->get_flow_table_name($rules_flow))) {
            $rules_table_name = 'backup_rules';
        }

        $list_arr = $results_model->fetch_default_flow_params(['table_name' => 'backup_results']);
        $list_arr['flags'] = ['include_rule_details'];

        $flow_params = [
            'term_singular'        => $this->_pt('backup result'),
            'term_plural'          => $this->_pt('backup results'),
            'initial_list_arr'     => $list_arr,
            'after_table_callback' => [$this, 'after_table_callback'],
            'listing_title'        => $this->_pt('Backup Results'),
        ];

        if (PHS_Params::_g('unknown_backup_result', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid backup result or backup result not found in database.'));
        }

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }

        if (!empty($statuses_arr)) {
            $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        }

        if (!$can_delete_backups) {
            $bulk_actions = false;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Delete'),
                    'action'          => 'bulk_delete',
                    'js_callback'     => 'phs_backup_results_list_bulk_delete',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt('Title'),
                'display_hint' => $this->_pt('Results of rules containing this in title'),
                'var_name'     => 'ftitle',
                'record_field' => '`'.$rules_table_name.'`.title',
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
                'display_callback'    => [$this, 'display_hide_id'],
            ],
            [
                'column_title'     => $this->_pt('Rule'),
                'record_field'     => 'backup_rules_title',
                'display_callback' => [$this, 'display_backup_rule_title'],
            ],
            [
                'column_title'        => $this->_pt('Where'),
                'record_field'        => 'location',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_backup_rule_where'],
            ],
            [
                'column_title'        => $this->_pt('What'),
                'record_field'        => 'target',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_backup_rule_what'],
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
                'column_title'          => $this->_pt('Created'),
                'default_sort'          => 1,
                'record_db_field'       => 'cdate',
                'record_field'          => 'cdate',
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

        if ($can_delete_backups) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $url_params = ['p' => 'backup', 'a' => 'backups_list'];

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url($url_params);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['bulk_actions'] = $bulk_actions;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    public function manage_action($action)
    {
        $this->reset_error();

        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        $backup_plugin = $this->_backup_plugin;

        $action_result_params = $this->_paginator->default_action_params();

        if (empty($action) || !is_array($action)
         || empty($action['action'])) {
            return $action_result_params;
        }

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;
                break;

            case 'bulk_delete':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required backup results deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected backup results failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected backup results. Backup results which failed deletion are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!($current_user = PHS::user_logged_in())
                 || !can($backup_plugin::ROLEU_DELETE_BACKUPS)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage backup results.'));

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
                foreach ($scope_arr[$scope_key] as $result_id) {
                    if (!$this->_paginator_model->act_delete($result_id)) {
                        $remaining_ids_arr[] = $result_id;
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

            case 'do_delete':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Backup result deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting backup result failed. Please try again.'));
                    }

                    return true;
                }

                if (!can($backup_plugin::ROLEU_DELETE_BACKUPS)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to manage backup results.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                 || !($result_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete backup result. Backup result not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_delete($result_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_hide_id($params)
    {
        return '';
    }

    public function display_backup_rule_title($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        $rules_model = $this->_rules_model;
        if (!($days_arr = $rules_model->get_rule_days())) {
            $days_arr = [];
        }

        if (!($rule_days_arr = $rules_model->get_rule_days_as_array($params['record']['rule_id']))) {
            $rule_days_arr = [];
        }

        $days_str_arr = [];
        foreach ($rule_days_arr as $day) {
            if (empty($days_arr[$day])) {
                continue;
            }

            $days_str_arr[] = $days_arr[$day];
        }

        if (empty($days_str_arr)) {
            $days_str_arr = '';
        } else {
            $days_str_arr = implode(', ', $days_str_arr);
        }

        $hour_str = '';
        if (isset($params['record']['backup_rules_hour'])) {
            $hour_str = ($days_str_arr != '' ? ' @' : '').$params['record']['backup_rules_hour'].($params['record']['backup_rules_hour'] < 12 ? 'am' : 'pm');
        }

        return (!empty($params['record']['backup_rules_title']) ? $params['record']['backup_rules_title'] : '(???)').'<br/>'
               .'<small>'.$days_str_arr.$hour_str.'</small>';
    }

    public function display_backup_rule_where($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        $backup_plugin = $this->_backup_plugin;
        if (empty($params['record']['run_dir'])
         || !($location_stats_arr = $backup_plugin->get_directory_stats($params['record']['run_dir']))) {
            $location_stats_arr = false;
        }

        $display_dir = '(???)';
        if (!empty($params['record']['run_dir'])) {
            $dir1 = @basename($params['record']['run_dir']);
            $dir2 = @basename(@dirname($params['record']['run_dir']));
            $dir3 = @basename(@dirname(@dirname($params['record']['run_dir'])));

            $display_dir = $dir3.'/'.$dir2.'/'.$dir1;
        }

        return '<span title="'.self::_e($params['record']['run_dir'] ?? '').'" class="no-title-skinning">'.$display_dir.'</span>'
               .' - '
               .'<span title="'.self::_e($this->_pt('%s bytes', number_format($params['record']['size']))).'">'.format_filesize($params['record']['size']).'</span>'
            .(empty($location_stats_arr) ? ''
                : '<br/>'.$this->_pt('Total: %s, Free: %s', format_filesize($location_stats_arr['total_space']), format_filesize($location_stats_arr['free_space']))
            );
    }

    public function display_backup_rule_what($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        $rules_model = $this->_rules_model;
        if (!($targets_arr = $rules_model->get_targets_as_key_val())) {
            $targets_arr = [];
        }

        if (!($rule_targets_arr = $rules_model->bits_to_targets_arr($params['record']['backup_rules_target']))) {
            $rule_targets_arr = [];
        }

        $targets_str_arr = [];
        foreach ($rule_targets_arr as $target_id) {
            if (empty($targets_arr[$target_id])) {
                continue;
            }

            $targets_str_arr[] = $targets_arr[$target_id];
        }

        if (empty($targets_str_arr)) {
            $targets_str_arr = $this->_pt('N/A');
        } else {
            $targets_str_arr = implode(', ', $targets_str_arr);
        }

        return '<div class="clearfix">'.$targets_str_arr.'</div>'
               .'<div style="text-align: center;"><a href="javascript:void(0)" onclick="phs_backup_results_list_view_files('.$params['record']['id'].')">'.$this->_pt('View files').'</a></div>';

        // return $targets_str_arr;
    }

    public function display_actions($params)
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return false;
        }

        $backup_plugin = $this->_backup_plugin;

        if (!can($backup_plugin::ROLEU_DELETE_BACKUPS)) {
            return '-';
        }

        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || !($result_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        ob_start();
        if ($this->_paginator_model->is_finished($result_arr)
         || $this->_paginator_model->is_error($result_arr)) {
            ?>
            <a href="javascript:void(0)" onclick="phs_backup_results_list_delete( '<?php echo $result_arr['id']; ?>' )"><i class="fa fa-times action-icons" title="<?php echo $this->_pt('Delete backup result'); ?>"></i></a>
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
        function phs_backup_results_list_delete( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to DELETE this backup result?', '"'); ?>" + "\n" +
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

        function phs_backup_results_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_backup_results_list_bulk_delete()
        {
            var total_checked = phs_backup_results_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select backup results you want to delete first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to DELETE %s backup results?', '"'), '" + total_checked + "'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }
        function phs_backup_results_list_view_files( id )
        {
            PHS_JSEN.createAjaxDialog( {
                width: 900,
                height: 600,
                suffix: "backup_result_files_",
                resizable: true,
                close_outside_click: false,

                title: "<?php echo self::_e($this->_pt('Backup Result Files')); ?>",
                method: "get",
                url: "<?php echo PHS_Ajax::url(['p' => 'backup', 'a' => 'result_files']); ?>",
                url_data: { result_id: id }
           });
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
