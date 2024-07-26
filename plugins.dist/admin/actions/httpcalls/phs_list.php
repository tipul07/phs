<?php

namespace phs\plugins\admin\actions\httpcalls;

use phs\PHS;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Request_queue;

/** @property PHS_Model_Request_queue $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    public function load_depencies() : bool
    {
        if (!($this->_admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($this->_paginator_model = PHS_Model_Request_queue::get_instance())) {
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

        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$this->_admin_plugin->can_admin_list_http_calls()
            && !$this->_admin_plugin->can_admin_manage_http_calls()) {
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
        PHS::page_settings('page_title', $this->_pt('HTTP Calls'));

        $list_arr = [];
        $list_arr['flags'] = ['include_account_details'];

        $flow_params = [
            'term_singular'            => $this->_pt('HTTP call'),
            'term_plural'              => $this->_pt('HTTP calls'),
            'initial_list_arr'         => $list_arr,
            'after_table_callback'     => [$this, 'after_table_callback'],
            'after_full_list_callback' => [$this, 'after_full_list_callback'],
            'listing_title'            => $this->_pt('HTTP Calls'),
        ];

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }

        $filter_statuses_arr = $statuses_arr
            ? self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr)
            : [];

        $filters_arr = [
            [
                'display_name' => $this->_pt('URL'),
                'display_hint' => $this->_pt('All HTTP calls to provided URL'),
                'var_name'     => 'furl',
                'record_field' => 'url',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Method'),
                'display_hint' => $this->_pt('All HTTP calls done using this method'),
                'var_name'     => 'fmethod',
                'record_field' => 'method',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Handle'),
                'display_hint' => $this->_pt('All HTTP calls created using specified handle'),
                'var_name'     => 'fhandle',
                'record_field' => 'handle',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name'  => $this->_pt('Is final?'),
                'display_hint'  => $this->_pt('Select only final HTTP calls'),
                'var_name'      => 'fis_final',
                'switch_filter' => [
                    1 => [
                        'raw_query' => 'is_final = 1',
                    ],
                    2 => [
                        'raw_query' => 'is_final = 0',
                    ],
                ],
                'type'       => PHS_Params::T_INT,
                'default'    => -1,
                'values_arr' => [
                    -1 => $this->_pt('All'),
                    1  => $this->_pt('Final calls'),
                    2  => $this->_pt('NOT final calls'),
                ],
            ],
            [
                'display_name' => $this->_pt('Status'),
                'var_name'     => 'fstatus',
                'record_field' => 'status',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $filter_statuses_arr,
            ],
            [
                'display_name'        => $this->_pt('Run From'),
                'display_hint'        => $this->_pt('All HTTP calls run after this date'),
                'display_placeholder' => $this->_pt('Calls run after this date'),
                'var_name'            => 'fstart_date',
                'record_field'        => 'last_run',
                'record_check'        => ['check' => '>=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 00:00:00'],
                'default'             => '',
                'linkage_func'        => 'AND',
            ],
            [
                'display_name'        => $this->_pt('Run Up To'),
                'display_hint'        => $this->_pt('All HTTP calls run before this date'),
                'display_placeholder' => $this->_pt('Calls run before this date'),
                'var_name'            => 'fend_date',
                'record_field'        => 'last_run',
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
                'column_title'        => $this->_pt('Method'),
                'record_field'        => 'method',
                'extra_style'         => 'width:100px;text-align:center;',
                'extra_records_style' => 'text-align:right;',
                'display_callback'    => function($params) {
                    return strtoupper($params['record']['method'] ?? 'N/A');
                },
            ],
            [
                'column_title'     => $this->_pt('URL'),
                'record_field'     => 'url',
                'display_callback' => [$this, 'display_url'],
            ],
            [
                'column_title'        => $this->_pt('Handle'),
                'record_field'        => 'handle',
                'extra_style'         => 'width: 100px;text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Payload'),
                'record_field'        => 'payload',
                'extra_style'         => 'width:100px;text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
                'display_callback'    => [$this, 'display_payload_details'],
            ],
            [
                'column_title'          => $this->_pt('Runs'),
                'record_field'          => 'last_run',
                'date_format'           => 'd-m-Y H:i:s',
                'extra_classes'         => 'date_th',
                'extra_records_classes' => 'date',
                'extra_style'           => 'text-align:center;',
                'extra_records_style'   => 'text-align:center;',
                'display_callback'      => [$this, 'display_progress'],
            ],
            [
                'column_title'        => $this->_pt('Is Final?'),
                'record_field'        => 'is_final',
                'extra_style'         => 'width:11em;text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
                'display_key_value'   => [
                    0 => $this->_pt('No'),
                    1 => $this->_pt('Yes'),
                ],
                'display_callback' => [$this, 'display_is_final'],
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
                'default_sort'          => true,
                'record_field'          => 'cdate',
                'display_callback'      => [&$this->_paginator, 'pretty_date'],
                'date_format'           => 'd-m-Y H:i:s',
                'invalid_value'         => $this->_pt('Invalid'),
                'extra_style'           => 'width: 10em;',
                'extra_classes'         => 'date_th',
                'extra_records_classes' => 'date',
            ],
            [
                'column_title'        => $this->_pt('Actions'),
                'display_callback'    => [$this, 'display_actions'],
                'extra_style'         => 'width:120px;',
                'extra_records_style' => 'text-align:right;',
                'sortable'            => false,
            ],
        ];

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'httpcalls']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    public function display_hide_id($params)
    {
        if (empty($params)
            || !is_array($params)
            || empty($params['record']) || !is_array($params['record'])
            || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return false;
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                    $params['preset_content'] = (int)$record_arr['id'];
                    break;

                case $this->_paginator::CELL_RENDER_HTML:
                    $params['preset_content'] = '';
                    break;
            }
        }

        return $params['preset_content'];
    }

    public function display_url($params)
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        if (empty($params['preset_content'])) {
            return '-';
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                    return $params['preset_content'];
            }
        }

        return '<a href="'.$params['preset_content'].'" target="_blank">'.$params['preset_content'].'</a>';
    }

    public function display_payload_details($params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
         || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        if (empty($record_arr['payload'])) {
            return '-';
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:
                    return $record_arr['payload'];
            }
        }

        ob_start();
        ?><br/>
        <a href="javascript:void(0)" onclick="phs_httpcalls_list_open_payload( '<?php echo $record_arr['id']; ?>' )"
           onfocus="this.blur()"><i title="<?php echo $this->_pt('View Payload'); ?>" class="fa fa-sign-in action-icons"></i></a>
        <div id="phs_httpcalls_list_open_payload_pretty_<?php echo $record_arr['id']; ?>" style="display:none;"><?php
            if (($json_arr = @json_decode($record_arr['payload'], true))) {
                echo @json_encode($json_arr, JSON_PRETTY_PRINT);
            } else {
                echo htmlentities($record_arr['payload']);
            }
        ?></div>
        <div id="phs_httpcalls_list_open_payload_raw_<?php echo $record_arr['id']; ?>" style="display: none"><?php
            echo $record_arr['payload'];
        ?></div>
        <?php
        return ob_get_clean();
    }

    public function display_progress($params) : ?string
    {
        if (empty($params['record']['id'])) {
            return null;
        }

        $record_arr = $params['record'];

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case $this->_paginator::CELL_RENDER_JSON:
                case $this->_paginator::CELL_RENDER_TEXT:

                    return $record_arr['fails'].'/'.$record_arr['max_retries'];
            }
        }

        $progress_perc = $this->_paginator_model->is_final($record_arr)
            ? 100
            : min(100, (!(float)$record_arr['max_retries'] ? 0 : ceil(($record_arr['fails'] * 100) / $record_arr['max_retries'])));

        ob_start();
        ?>
        <div>
            <span title="<?php echo $this->_pte('Fails'); ?>">F: <?php echo $record_arr['fails']; ?></span>
            /
            <span title="<?php echo $this->_pte('Max retries'); ?>">MR: <?php echo $record_arr['max_retries']; ?></span>
            <br/>
            <div style="width:100%;height:10px;background-color:red;"><div style="width:<?php echo $progress_perc; ?>%;height:10px;background-color:green;"></div></div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function display_is_final($params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])
            || !($record_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        if (!empty($params['request_render_type'])
            && in_array((int)$params['request_render_type'],
                [$this->_paginator::CELL_RENDER_JSON, $this->_paginator::CELL_RENDER_TEXT], true)) {
            return $params['preset_content'];
        }

        if (!empty($record_arr['last_run'])) {
            $pretty_params = [];
            $pretty_params['date_format'] = $params['column']['date_format'] ?? null;
            $pretty_params['request_render_type'] = $this->_paginator::CELL_RENDER_HTML;

            $last_run = $this->_paginator->pretty_date_independent($record_arr['last_run'], $pretty_params);
        } else {
            $last_run = $this->_pt('N/A');
        }

        ob_start();
        ?><br/>
        <span title="<?php echo $this->_pte('Last Run'); ?>"><?php echo $this->_pt('LR'); ?></span>: <?php echo $last_run; ?>
        <?php

        if (!empty($record_arr['last_error'])) {
            ?><br/>
            <a href="javascript:void(0)" onclick="phs_httpcalls_record_error_message( '<?php echo $record_arr['id']; ?>' )"
               title="<?php echo $this->_pte('Last HTTP Call Error'); ?>"
               onfocus="this.blur()"><i class="fa fa-exclamation action-icons"></i></a>
            <div id="phs_httpcalls_record_error_message_<?php echo $record_arr['id']; ?>" style="display:none;">
                <?php echo str_replace('  ', ' &nbsp;', nl2br($record_arr['last_error'])); ?>
            </div>
            <?php
        }

        return $params['preset_content'].@ob_get_clean();
    }

    public function display_actions($params) : ?string
    {
        if (!$this->_admin_plugin->can_admin_manage_http_calls()) {
            return '-';
        }

        if (empty($params['record']['id'])) {
            return null;
        }

        $record_arr = $params['record'];

        $is_success = $this->_paginator_model->is_success($record_arr);
        $is_failed = $this->_paginator_model->is_failed($record_arr);
        $is_pending = $this->_paginator_model->is_pending($record_arr);

        ob_start();
        ?>
        <a href="javascript:void(0)" onclick="phs_httpcalls_record_settings( '<?php echo $record_arr['id']; ?>' )"
        ><i class="fa fa-wrench action-icons" title="<?php echo form_str($this->_pt('View HTTP call settings')); ?>"></i></a>
        <div id="phs_httpcalls_record_settings_<?php echo $record_arr["id"]; ?>" style="display:none;"><pre
                style="min-height: 95px; height: 90%; resize: vertical;"><?php
            if (null !== ($json_arr = $this->_paginator_model->obfuscate_settings($record_arr))) {
                echo @json_encode($json_arr, JSON_PRETTY_PRINT);
            } else {
                echo $this->_pt('N/A');
            }
        ?></div>
        <?php

        if ($is_success || $is_failed || $is_pending) {
            ?>
            <a href="javascript:void(0)" onclick="phs_migrations_list_rerun_migration( '<?php echo $record_arr['id']; ?>' )"
            ><i class="fa fa-refresh action-icons" title="<?php echo form_str($this->_pt('Resend HTTP call')); ?>"></i></a>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * @inheritdoc
     */
    public function manage_action($action) : null | bool | array
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
            case 'edit_json_payload':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Payload updated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Error updating payload. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_http_calls()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to take this action.'));

                    return false;
                }

                if (!($json_payload = PHS_Params::_gp('phs_httpcall_payload', PHS_Params::T_NOHTML))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Please provide a payload content.'));

                    return false;
                }

                if (!($phs_httpcall_id = PHS_Params::_gp('phs_httpcall_id', PHS_Params::T_INT))
                    || !($request_arr = $this->_paginator_model->get_details($phs_httpcall_id))
                    || $this->_paginator_model->is_deleted($request_arr) ) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('HTTP call not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->update_payload($request_arr, $json_payload)) {
                    PHS_Logger::error('Error updating payload for request #'.$request_arr['id'].'.', PHS_Logger::TYPE_HTTP_CALLS);

                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'do_retry':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Request retried with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Retrying request failed. Please try again.'));
                    }

                    return true;
                }

                if (empty($can_retry)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to retry NCP requests.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($request_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('NCP request not found in database.'));

                    return false;
                }

                if (!$this->_ncp_flows->retry_sending_data_to_ncp($request_arr, ['skip_failed_event' => true])) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function after_table_callback($params) : ?string
    {
        static $js_functionality = false;

        if (!empty($js_functionality)) {
            return '';
        }

        $js_functionality = true;

        ob_start();
        ?>
        <script type="text/javascript">
            function phs_httpcalls_list_open_payload( id )
            {
                const container_pretty_text = $("#phs_httpcalls_list_open_payload_pretty_" + id).text();
                const container_raw_text = $("#phs_httpcalls_list_open_payload_raw_" + id).text();
                const phs_httpcall_id = $('#phs_httpcall_id');
                const text_area_payload = $('#phs_httpcall_payload');

                $("#phs_httpcall_payload_area").text(container_pretty_text);
                text_area_payload.text(container_raw_text);
                phs_httpcall_id.val(id);

                PHS_JSEN.createAjaxDialog( {
                    suffix: 'phs_httpcalls_record_payload_',
                    width: 800,
                    height: 800,
                    title: "<?php echo $this->_pte('HTTP Call Payload'); ?>",
                    resizable: true,
                    close_outside_click: false,
                    source_obj: "phs_httpcalls_view_payload",
                    source_not_cloned: true,
                    onsuccess: () => {
                        $('#phs_httpcalls_view_payload').show();
                    },
                    onclose: () => {
                        $('#phs_httpcalls_view_payload').hide();
                    }
                });

                return false;
            }
            function closing_phs_httpcalls_list_open_payload(id)
            {
                const container_obj = $("#phs_httpcalls_list_open_payload_pretty_" + id);
                if( !container_obj )
                    return;

                container_obj.hide();
            }
            function phs_httpcalls_cancel_edit_payload()
            {
                const uneditable_wrapper = $('#phs_httpcalls_payload_uneditable_wrapper');
                const editable_wrapper = $('#phs_httpcalls_payload_editable_wrapper');

                if (uneditable_wrapper.length) {
                    uneditable_wrapper.show();
                }

                if (editable_wrapper.length) {
                    editable_wrapper.hide();
                }
            }

            function phs_httpcalls_record_error_message( id )
            {
                const container_obj = $("#phs_httpcalls_record_error_message_" + id);
                if( !container_obj )
                    return;

                container_obj.show();

                PHS_JSEN.createAjaxDialog( {
                    suffix: 'phs_httpcall_error_message_',
                    width: 700,
                    height: 650,
                    title: "<?php echo $this->_pte('Last HTTP Call Error'); ?>",
                    resizable: true,
                    close_outside_click: false,
                    source_obj: container_obj,
                    source_not_cloned: true,
                    onbeforeclose: () => phs_httpcalls_record_error_message_close(id)
                });

                return false;
            }
            function phs_httpcalls_record_error_message_close(id)
            {
                const container_obj = $("#phs_httpcalls_record_error_message_" + id);
                if( !container_obj )
                    return;

                container_obj.hide();
            }

            function phs_httpcalls_record_settings( id )
            {
                const container_obj = $("#phs_httpcalls_record_settings_" + id);
                if( !container_obj )
                    return;

                container_obj.show();

                PHS_JSEN.createAjaxDialog( {
                    suffix: 'phs_httpcall_settings_',
                    width: 700,
                    height: 650,
                    title: "<?php echo $this->_pte('HTTP Call Settings'); ?>",
                    resizable: true,
                    close_outside_click: false,
                    source_obj: container_obj,
                    source_not_cloned: true,
                    onbeforeclose: () => phs_httpcalls_record_settings_close(id)
                });

                return false;
            }
            function phs_httpcalls_record_settings_close(id)
            {
                const container_obj = $("#phs_httpcalls_record_settings_" + id);
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

        return ob_get_clean();
    }

    public function after_full_list_callback($params) : ?string
    {
        ob_start();
        ?>
        <script type="text/javascript">
            function phs_httpcalls_payload_make_editable()
            {
                const uneditable_wrapper = $('#phs_httpcalls_payload_uneditable_wrapper');
                const editable_wrapper = $('#phs_httpcalls_payload_editable_wrapper');

                if (uneditable_wrapper.length) {
                    uneditable_wrapper.hide();
                }

                if (editable_wrapper.length) {
                    editable_wrapper.show();
                }
            }
        </script>
        <div style="display: none;" id="phs_httpcalls_view_payload">
            <?php
            $url_params = [];
        $url_params['action'] = [
            'action' => 'edit_json_payload',
        ];

        if (!($edit_url = $this->_paginator->get_full_url($url_params))) {
            $edit_url = '';
        }
        ?>
            <div id="phs_httpcalls_payload_uneditable_wrapper" style="display: block;">
                <pre style="min-height: 95px; height: 90%; resize: vertical;" id="phs_httpcall_payload_area"></pre>
                <?php if ($this->_admin_plugin->can_admin_manage_http_calls()) { ?>
                    <button class="btn btn-primary" id="make_payload_editable" name="make_payload_editable" type="button"
                            onclick="phs_httpcalls_payload_make_editable()"><?php echo $this->_pt('Edit payload'); ?></button>
                <?php } ?>
            </div>
            <?php
        if ($this->_admin_plugin->can_admin_manage_http_calls()) { ?>
                <div id="phs_httpcalls_payload_editable_wrapper" style="display: none;">
                    <form method="post" name="phs_httpcalls_payload_form" id="phs_httpcalls_payload_form"
                          action="<?php echo $edit_url; ?>">
                        <input type="hidden" name="phs_httpcall_id" id="phs_httpcall_id" value="0" />
                        <textarea style="min-height: 95px; height: 400px; width: 100%; resize: vertical"
                                  id="phs_httpcall_payload" name="phs_httpcall_payload"></textarea>
                        <div style="text-align: right;">
                            <button type="button" id="phs_httpcalls_edit_json_cancel" name="phs_httpcalls_edit_json_cancel"
                                    class="btn btn-default btn-medium mr-0_5"
                                    onclick="phs_httpcalls_cancel_edit_payload()">
                                <?php echo $this->_pt('Cancel'); ?>
                            </button>
                            <button type="submit" id="phs_httpcalls_edit_json_submit" name="phs_httpcalls_edit_json_submit"
                                    class="btn btn-primary btn-medium submit-protection ignore_hidden_required">
                                <?php echo $this->_pt('Save Changes'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </div>
        <?php

        return ob_get_clean();
    }
}
