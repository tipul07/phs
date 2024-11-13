<?php
namespace phs\plugins\admin\actions\agent;

use phs\PHS;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\libraries\PHS_Paginator_exporter_library;
use phs\system\core\models\PHS_Model_Agent_jobs_monitor;

/** @property PHS_Model_Agent_jobs_monitor $_paginator_model */
class PHS_Action_Report extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    public function load_depencies() : bool
    {
        if (!($this->_admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($this->_paginator_model = PHS_Model_Agent_jobs_monitor::get_instance())) {
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

        if (!$this->_admin_plugin->can_admin_list_agent_jobs()) {
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
        PHS::page_settings('page_title', $this->_pt('Agent Jobs Report'));

        $flow_params = [
            'term_singular'        => $this->_pt('agent job'),
            'term_plural'          => $this->_pt('agent jobs'),
            'initial_list_arr'     => [],
            'after_table_callback' => [$this, 'after_table_callback'],
            'listing_title'        => $this->_pt('Agent Jobs Report'),
        ];

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }

        if (!empty($statuses_arr)) {
            $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        }

        $bulk_actions = [
            [
                'display_name'    => $this->_pt('Export selected'),
                'action'          => 'bulk_export_selected',
                'js_callback'     => 'phs_agent_jobs_report_bulk_export_selected',
                'checkbox_column' => 'id',
            ],
            [
                'display_name'    => $this->_pt('Export ALL records'),
                'action'          => 'bulk_export_all',
                'js_callback'     => 'phs_agent_jobs_report_bulk_export_all',
                'checkbox_column' => 'id',
            ],
        ];

        $filters_arr = [
            [
                'display_name' => $this->_pt('Job title'),
                'display_hint' => $this->_pt('All records containing this value at title field'),
                'var_name'     => 'ftitle',
                'record_field' => 'job_title',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Job handler'),
                'display_hint' => $this->_pt('All records containing this value at handler field'),
                'var_name'     => 'fhandler',
                'record_field' => 'job_handler',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Plugin'),
                'display_hint' => $this->_pt('All records containing this value at plugin field'),
                'var_name'     => 'fplugin',
                'record_field' => 'plugin',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Error code'),
                'var_name'     => 'ferror_code',
                'record_field' => 'error_code',
                'type'         => PHS_Params::T_INT,
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
                'column_title'              => '#',
                'record_field'              => 'id',
                'checkbox_record_index_key' => [
                    'key'  => 'id',
                    'type' => PHS_Params::T_INT,
                ],
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'width:80px;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_hide_id'],
            ],
            [
                'column_title'        => $this->_pt('Title'),
                'record_field'        => 'job_title',
                'extra_records_style' => 'text-align:left;',
                'display_callback'    => [$this, 'display_job_title'],
            ],
            [
                'column_title'        => $this->_pt('Plugin'),
                'record_field'        => 'plugin',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
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
                'column_title'        => $this->_pt('Error'),
                'record_field'        => 'error_message',
                'extra_style'         => 'width:80px;text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_error_message'],
            ],
            [
                'column_title'          => $this->_pt('Created'),
                'default_sort'          => 1,
                'record_field'          => 'cdate',
                'display_callback'      => [&$this->_paginator, 'pretty_date'],
                'date_format'           => 'd-m-Y H:i',
                'invalid_value'         => $this->_pt('Invalid'),
                'extra_classes'         => 'date_th',
                'extra_records_classes' => 'date',
            ],
        ];

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'report', 'ad' => 'agent']);
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

            case 'bulk_export_selected':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Creating export file...'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Exporting selected records failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed exporting all selected records. Please try again.'));
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

                if (isset($scope_arr[$scope_all_key])) {
                    unset($scope_arr[$scope_all_key]);
                }

                if (empty($scope_arr[$scope_key])
                    || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                $export_params = [];
                $export_params['exporter_library_params'] = [
                    'export_encoding'     => 'UTF-8',
                    'export_to'           => PHS_Paginator_exporter_library::EXPORT_TO_BROWSER,
                    'request_render_type' => $this->_paginator::CELL_RENDER_TEXT,
                    'export_file_name'    => 'phs_agent_jobs_report_'.date('Y_m_d_H_i').'.csv',
                    'export_mime_type'    => 'text/csv',
                    'csv_format'          => [
                        'line_delimiter'   => "\n",
                        'column_delimiter' => ',',
                        'field_enclosure'  => '"',
                        'enclosure_escape' => '"',
                    ],
                ];
                $export_params['filter_records_fields'] = ['id' => $scope_arr[$scope_key], ];

                if (($export_result = $this->_paginator->do_export_records($export_params))) {
                    if (empty($export_result['exports_failed'])) {
                        $action_result_params['action_result'] = 'success';

                        unset($scope_arr[$scope_key]);
                    } else {
                        $action_result_params['action_result'] = 'failed_some';
                    }
                } else {
                    $action_result_params['action_result'] = 'failed';
                }

                $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                break;

            case 'bulk_export_all':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Creating export file...'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Exporting all records failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed exporting all records. Please try again.'));
                    }

                    return true;
                }

                $export_params = [];
                $export_params['exporter_library_params'] = [
                    'export_encoding'     => 'UTF-8',
                    'export_to'           => PHS_Paginator_exporter_library::EXPORT_TO_BROWSER,
                    'request_render_type' => $this->_paginator::CELL_RENDER_TEXT,
                    'export_file_name'    => 'phs_agent_jobs_report_all_'.date('Y_m_d_H_i').'.csv',
                    'export_mime_type'    => 'text/csv',
                    'csv_format'          => [
                        'line_delimiter'   => "\n",
                        'column_delimiter' => ',',
                        'field_enclosure'  => '"',
                        'enclosure_escape' => '"',
                    ],
                ];
                $export_params['model_query_params']['export_all_records'] = true;

                if (($export_result = $this->_paginator->do_export_records($export_params))) {
                    if (empty($export_result['exports_failed'])) {
                        $action_result_params['action_result'] = 'success';
                    } else {
                        $action_result_params['action_result'] = 'failed_some';
                    }
                } else {
                    $action_result_params['action_result'] = 'failed';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_hide_id($params)
    {
        if (empty($params)
            || !is_array($params)
            || empty($params['record']) || !is_array($params['record'])
            || !($agent_job = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                    $params['preset_content'] = (int)$agent_job['id'];
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    $params['preset_content'] = '';
                    break;
            }
        }

        return $params['preset_content'];
    }

    public function display_job_title($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || !($agent_job = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        if (empty($params['preset_content'])) {
            $params['preset_content'] = '';
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                    $params['preset_content'] .= (!empty($params['preset_content']) ? ' - ' : '').$agent_job['job_handle'];
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    if (!empty($params['preset_content'])) {
                        $params['preset_content'] .= '<br/><small>'.$agent_job['job_handle'].'</small>';
                    } else {
                        $params['preset_content'] = $agent_job['job_handle'];
                    }
                    break;
            }
        }

        return $params['preset_content'];
    }

    public function display_error_message($params)
    {
        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || !($agent_job = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        if (empty($params['preset_content'])) {
            $params['preset_content'] = '';
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                    $params['preset_content'] = str_replace(["\r", "\n"], ' ', $agent_job['error_message']);
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    if (empty($agent_job['error_message'])) {
                        $params['preset_content'] = '-';
                    } else {
                        ob_start();
                        ?>
                        <a href="javascript:void(0)" onclick="phs_open_agent_job_error( '<?php echo $agent_job['id']; ?>' )"
                           onfocus="this.blur()"><i class="fa fa-exclamation action-icons"></i></a>
                        <div id="phs_agent_jobs_report_error_<?php echo $agent_job['id']; ?>" style="display:none;">
                            <?php echo str_replace('  ', ' &nbsp;', nl2br($agent_job['error_message'])); ?>
                        </div>
                        <?php
                        $params['preset_content'] = ob_get_clean();
                    }
                    break;
            }
        }

        return $params['preset_content'];
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
            function phs_open_agent_job_error( id )
            {
                $("#phs_agent_jobs_report_error_" + id).dialog({
                    title: "<?php echo form_str($this->_pt('Agent job error')); ?>",
                    width: "500px",
                    buttons: [
                        {
                            text: "<?php echo form_str($this->_pt('Ok')); ?>",
                            click: function() {
                                $( this ).dialog( "close" );
                            }
                        }
                    ]
                }).show()
            }
        function phs_agent_jobs_report_get_checked_ids_count()
        {
            const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_agent_jobs_report_bulk_export_selected()
        {
            const total_checked = phs_agent_jobs_report_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e('Please select records you want to export first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to export %s records?', '"'), '" + total_checked + "'); ?>" ) )
                return false;

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj )
                form_obj.submit();
        }
        function phs_agent_jobs_report_bulk_export_all()
        {
            if( !confirm( "<?php echo self::_e($this->_pt('Are you sure you want to export ALL records?'), '"'); ?>" ) )
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
