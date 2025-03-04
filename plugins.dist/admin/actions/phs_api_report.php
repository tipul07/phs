<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Api_monitor;

/** @property PHS_Model_Api_monitor $_paginator_model */
class PHS_Action_Api_report extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    /**
     * @inheritdoc
     */
    public function should_stop_execution() : ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_admin_plugin->can_admin_view_api_monitoring_report()) {
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
        PHS::page_settings('page_title', $this->_pt('API Monitor Report'));

        $list_arr = [];
        $list_arr['flags'] = ['include_account_details'];

        $flow_params = [
            'term_singular'          => $this->_pt('API call'),
            'term_plural'            => $this->_pt('API calls'),
            'initial_list_arr'       => $list_arr,
            'after_table_callback'   => [$this, 'after_table_callback'],
            'listing_title'          => $this->_pt('API Monitor Report'),
            'simulate_records_count' => true,
        ];

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }
        if (!($types_arr = $this->_paginator_model->get_types_as_key_val())) {
            $types_arr = [];
        }

        if (!empty($statuses_arr)) {
            $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        }
        if (!empty($types_arr)) {
            $types_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $types_arr);
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt('External route'),
                'display_hint' => $this->_pt('All records containing this value at title field'),
                'var_name'     => 'ftitle',
                'record_field' => 'external_route',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Internal route'),
                'display_hint' => $this->_pt('All records containing this value at handler field'),
                'var_name'     => 'fhandler',
                'record_field' => 'internal_route',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Method'),
                'display_hint' => $this->_pt('All requests done with this method'),
                'var_name'     => 'fmethod',
                'record_field' => 'method',
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
                'display_name' => $this->_pt('Response code'),
                'var_name'     => 'fresponse_code',
                'record_field' => 'response_code',
                'type'         => PHS_Params::T_INT,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Type'),
                'var_name'     => 'ftype',
                'record_field' => 'type',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $types_arr,
            ],
            [
                'display_name' => $this->_pt('Status'),
                'var_name'     => 'fstatus',
                'record_field' => 'status',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $statuses_arr,
            ],
            [
                'display_name'        => $this->_pt('Requests From'),
                'display_hint'        => $this->_pt('All HTTP requests run after this date'),
                'display_placeholder' => $this->_pt('Requests after this date'),
                'var_name'            => 'frequest_time_start',
                'record_field'        => 'request_time',
                'record_check'        => ['check' => '>=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 00:00:00'],
                'default'             => '',
                'linkage_func'        => 'AND',
            ],
            [
                'display_name'        => $this->_pt('Requests Up To'),
                'display_hint'        => $this->_pt('All HTTP requests run before this date'),
                'display_placeholder' => $this->_pt('Requests before this date'),
                'var_name'            => 'frequest_time_end',
                'record_field'        => 'request_time',
                'record_check'        => ['check' => '<=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 23:59:59'],
                'default'             => '',
                'linkage_func'        => 'AND',
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
                'column_title'        => $this->_pt('Account'),
                'record_field'        => 'account_nick',
                'record_api_field'    => 'account_id',
                'display_callback'    => [$this, 'display_account_name'],
                'extra_style'         => 'width:120px;text-align:center;',
                'extra_records_style' => 'width:120px;text-align:center;word-break:break-word;',
            ],
            [
                'column_title'        => $this->_pt('Method'),
                'record_field'        => 'method',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('External Route'),
                'record_field'        => 'external_route',
                'extra_records_style' => 'text-align:left;',
            ],
            [
                'column_title'        => $this->_pt('Internal Route'),
                'record_field'        => 'internal_route',
                'extra_records_style' => 'text-align:left;',
            ],
            [
                'column_title'        => $this->_pt('Plugin'),
                'record_field'        => 'plugin',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'          => $this->_pt('Request'),
                'default_sort'          => 1,
                'record_field'          => 'request_time',
                'date_format'           => 'd-m-Y H:i:s',
                'extra_classes'         => 'date_th',
                'extra_records_classes' => 'date',
                'extra_style'           => 'text-align:center;',
                'extra_records_style'   => 'text-align:center;',
                'display_callback'      => [$this, 'display_request_details'],
            ],
            [
                'column_title'          => $this->_pt('Response'),
                'record_field'          => 'response_time',
                'date_format'           => 'd-m-Y H:i:s',
                'extra_classes'         => 'date_th',
                'extra_records_classes' => 'date',
                'extra_style'           => 'text-align:center;',
                'extra_records_style'   => 'text-align:center;',
                'display_callback'      => [$this, 'display_response_details'],
            ],
            [
                'column_title'        => $this->_pt('HTTP Code'),
                'record_field'        => 'response_code',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_error_message'],
            ],
            [
                'column_title'          => $this->_pt('Type'),
                'record_field'          => 'type',
                'display_key_value'     => $types_arr,
                'invalid_value'         => $this->_pt('Undefined'),
                'extra_classes'         => 'status_th',
                'extra_records_classes' => 'status',
            ],
            [
                'column_title'          => $this->_pt('Status'),
                'record_field'          => 'status',
                'display_key_value'     => $statuses_arr,
                'invalid_value'         => $this->_pt('Undefined'),
                'extra_classes'         => 'status_th',
                'extra_records_classes' => 'status',
            ],
        ];

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'api_report']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    public function manage_action(array $action) : null | bool | array
    {
        return $this->_paginator->default_action_params();
    }

    public function display_hide_id(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
            || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                case $this->_paginator::CELL_RENDER_CSV:
                case $this->_paginator::CELL_RENDER_EXCEL:
                    $params['preset_content'] = (int)$record_arr['id'];
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    $params['preset_content'] = '';
                    break;
            }
        }

        return $params['preset_content'];
    }

    public function display_account_name(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return null;
        }

        if (empty($params['preset_content'])) {
            return '-';
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                case $this->_paginator::CELL_RENDER_CSV:
                case $this->_paginator::CELL_RENDER_EXCEL:
                    return $params['preset_content'];
            }
        }

        if (($users_list_url = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'users'],
            ['fids' => [$params['record']['account_id']]]))) {
            return '<a href="'.$users_list_url.'">'.$params['preset_content'].'</a>';
        }

        return $params['preset_content'];
    }

    public function display_request_details(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
         || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        $cell_str = '-';
        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                case $this->_paginator::CELL_RENDER_CSV:
                case $this->_paginator::CELL_RENDER_EXCEL:
                    $cell_str = $record_arr['request_time'];
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    $pretty_params = [];
                    $pretty_params['date_format'] = $params['column']['date_format'] ?? null;
                    $pretty_params['request_render_type'] = $this->_paginator::CELL_RENDER_HTML;

                    if (!empty($record_arr['request_time'])) {
                        $cell_str = $this->_paginator->pretty_date_independent($record_arr['request_time'], $pretty_params);
                    }

                    if (!empty($record_arr['request_body'])) {
                        ob_start();
                        ?><br/>
                        <a href="javascript:void(0)" onclick="phs_open_api_monitor_record_request_body( '<?php echo $record_arr['id']; ?>' )"
                           onfocus="this.blur()"><i class="fa fa-sign-in action-icons"></i></a>
                        <div id="phs_open_api_monitor_record_request_body_<?php echo $record_arr['id']; ?>" style="display:none;">
                            <pre><?php
                                if (($json_arr = @json_decode($record_arr['request_body'], true))) {
                                    echo @json_encode($json_arr, JSON_PRETTY_PRINT);
                                } else {
                                    echo htmlentities($record_arr['request_body']);
                                }
                        ?></pre>
                        </div>
                        <?php
                        $cell_str .= ob_get_clean() ?: '';
                    }
                    break;
            }
        }

        return $cell_str;
    }

    public function display_response_details(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
         || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        $cell_str = '-';
        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                case $this->_paginator::CELL_RENDER_CSV:
                case $this->_paginator::CELL_RENDER_EXCEL:
                    $cell_str = $record_arr['response_time'];
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    $pretty_params = [];
                    $pretty_params['date_format'] = $params['column']['date_format'] ?? null;
                    $pretty_params['request_render_type'] = $this->_paginator::CELL_RENDER_HTML;

                    if (!empty($record_arr['response_time'])) {
                        $cell_str = $this->_paginator->pretty_date_independent($record_arr['response_time'], $pretty_params);
                    }

                    if (!empty($record_arr['response_body'])) {
                        ob_start();
                        ?><br/>
                        <a href="javascript:void(0)" onclick="phs_open_api_monitor_record_response_body( '<?php echo $record_arr['id']; ?>' )"
                           onfocus="this.blur()"><i class="fa fa-sign-out action-icons"></i></a>
                        <div id="phs_open_api_monitor_record_response_body_<?php echo $record_arr['id']; ?>" style="display:none;">
                            <pre><?php
                                if (($json_arr = @json_decode($record_arr['response_body'], true))) {
                                    echo @json_encode($json_arr, JSON_PRETTY_PRINT);
                                } else {
                                    echo htmlentities($record_arr['response_body']);
                                }
                        ?></pre>
                        </div>
                        <?php
                        $cell_str .= ob_get_clean() ?: '';
                    }
                    break;
            }
        }

        return $cell_str;
    }

    public function display_error_message(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
         || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        $cell_str = $record_arr['response_code'] ?? '0';

        if (!empty($params['request_render_type'])
            && (int)$params['request_render_type'] === $this->_paginator::CELL_RENDER_HTML) {
            if (!empty($record_arr['error_message'])) {
                ob_start();
                ?>
                <a href="javascript:void(0)" onclick="phs_open_api_monitor_record_error_message( '<?php echo $record_arr['id']; ?>' )"
                   onfocus="this.blur()"><i class="fa fa-exclamation action-icons"></i></a>
                <div id="phs_open_api_monitor_record_error_message_<?php echo $record_arr['id']; ?>" style="display:none;">
                    <?php echo str_replace('  ', ' &nbsp;', nl2br($record_arr['error_message'])); ?>
                </div>
                <?php
                $cell_str .= ob_get_clean() ?: '';
            }

            if (empty($record_arr['request_time'])
                || empty($record_arr['response_time'])) {
                $response_time = 0;
            } else {
                $response_time = abs(seconds_passed($record_arr['response_time']) - seconds_passed($record_arr['request_time']));
            }

            $cell_str .= '<br/>Time: '.$response_time.'s';
        }

        return $cell_str;
    }

    public function after_table_callback(array $params) : string
    {
        static $js_functionality = false;

        if ($js_functionality) {
            return '';
        }

        $js_functionality = true;

        ob_start();
        ?>
        <script type="text/javascript">
            function phs_open_api_monitor_record_request_body( id )
            {
                const container_obj = $("#phs_open_api_monitor_record_request_body_" + id);
                if( !container_obj )
                    return;

                container_obj.show();

                PHS_JSEN.createAjaxDialog( {
                    suffix: 'phs_api_monitor_record_request_body_',
                    width: 700,
                    height: 650,
                    title: "<?php echo $this->_pte('API Monitoring Record Request'); ?>",
                    resizable: true,
                    close_outside_click: false,
                    source_obj: container_obj,
                    source_not_cloned: true,
                    onbeforeclose: () => closing_phs_open_api_monitor_record_request_body(id)
                });

                return false;
            }
            function closing_phs_open_api_monitor_record_request_body(id)
            {
                const container_obj = $("#phs_open_api_monitor_record_request_body_" + id);
                if( !container_obj )
                    return;

                container_obj.hide();
            }
            function phs_open_api_monitor_record_response_body( id )
            {
                const container_obj = $("#phs_open_api_monitor_record_response_body_" + id);
                if( !container_obj )
                    return;

                container_obj.show();

                PHS_JSEN.createAjaxDialog( {
                    suffix: 'phs_api_monitor_record_response_body_',
                    width: 700,
                    height: 650,
                    title: "<?php echo $this->_pte('API Monitoring Record Response'); ?>",
                    resizable: true,
                    close_outside_click: false,
                    source_obj: container_obj,
                    source_not_cloned: true,
                    onbeforeclose: () => closing_phs_open_api_monitor_record_response_body(id)
                });

                return false;
            }
            function closing_phs_open_api_monitor_record_response_body(id)
            {
                const container_obj = $("#phs_open_api_monitor_record_response_body_" + id);
                if( !container_obj )
                    return;

                container_obj.hide();
            }

            function phs_open_api_monitor_record_error_message( id )
            {
                const container_obj = $("#phs_open_api_monitor_record_error_message_" + id);
                if( !container_obj )
                    return;

                container_obj.show();

                PHS_JSEN.createAjaxDialog( {
                    suffix: 'phs_api_monitor_record_error_message_',
                    width: 700,
                    height: 650,
                    title: "<?php echo $this->_pte('API Monitoring Error Message'); ?>",
                    resizable: true,
                    close_outside_click: false,
                    source_obj: container_obj,
                    source_not_cloned: true,
                    onbeforeclose: () => closing_phs_open_api_monitor_record_error_message(id)
                });

                return false;
            }
            function closing_phs_open_api_monitor_record_error_message(id)
            {
                const container_obj = $("#phs_open_api_monitor_record_error_message_" + id);
                if( !container_obj )
                    return;

                container_obj.hide();
            }

            function phs_agent_jobs_report_get_checked_ids_count()
            {
                const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
                if( !checkboxes_list || !checkboxes_list.length )
                    return 0;

                return checkboxes_list.length;
            }
        </script>
        <?php

        return ob_get_clean() ?: '';
    }

    protected function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            (!$this->_admin_plugin && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
            || (!$this->_paginator_model && !($this->_paginator_model = PHS_Model_Api_monitor::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
