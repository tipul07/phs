<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\admin\libraries\Phs_Data_retention;
use phs\system\core\models\PHS_Model_Data_retention;

/** @property PHS_Model_Data_retention $_paginator_model */
class PHS_Action_List_runs extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?Phs_Data_retention $_data_retention_lib = null;

    private ?PHS_Model_Accounts $_accounts_model = null;

    private array $policies_cache = [];

    public function load_depencies() : bool
    {
        if ((empty($this->_admin_plugin)
             && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
            || (empty($this->_data_retention_lib)
                && !($this->_data_retention_lib = $this->_admin_plugin->get_data_retention_instance()))
            || (empty($this->_accounts_model)
                && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
            || (empty($this->_paginator_model)
                && !($this->_paginator_model = PHS_Model_Data_retention::get_instance()))
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

        if (!$this->_admin_plugin->can_admin_list_data_retention()) {
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
        PHS::page_settings('page_title', $this->_pt('Data Retention Policies Runs'));

        $can_manage = $this->_admin_plugin->can_admin_manage_data_retention();

        $list_arr = $this->_paginator_model->fetch_default_flow_params(['table_name' => 'phs_data_retention_runs']);

        $flow_params = [
            'listing_title'        => $this->_pt('Data Retention Policies Runs'),
            'term_singular'        => $this->_pt('policy'),
            'term_plural'          => $this->_pt('policies'),
            'initial_list_arr'     => $list_arr,
            'after_table_callback' => [$this, 'after_table_callback'],
        ];

        if (PHS_Params::_g('unknown_policy', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid data retention policy or data retention policy was not found in database.'));
        }

        $statuses_arr = $this->_paginator_model->get_statuses_as_key_val() ?: [];
        $filter_statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        if (isset($filter_statuses_arr[$this->_paginator_model::STATUS_DELETED])) {
            unset($filter_statuses_arr[$this->_paginator_model::STATUS_DELETED]);
        }

        $types_arr = $this->_paginator_model->get_types_as_key_val() ?: [];
        $filter_types_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $types_arr);

        if (!$can_manage) {
            $bulk_actions = null;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Re-run'),
                    'action'          => 'bulk_run_policies',
                    'js_callback'     => 'phs_data_retention_list_runs_bulk_run_policies',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name'        => $this->_pt('From Table'),
                'display_hint'        => $this->_pt('Runs which moved records from this table'),
                'display_placeholder' => $this->_pt('Source table'),
                'var_name'            => 'ffrom_table',
                'record_field'        => 'from_table',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
            ],
            [
                'display_name'        => $this->_pt('To Table'),
                'display_hint'        => $this->_pt('Runs which moved records to this table'),
                'display_placeholder' => $this->_pt('Destination table'),
                'var_name'            => 'fto_table',
                'record_field'        => 'to_table',
                'record_check'        => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
            ],
            [
                'display_name' => $this->_pt('Action Type'),
                'var_name'     => 'ftype',
                'record_field' => 'type',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $filter_types_arr,
            ],
            [
                'display_name'        => $this->_pt('Run From'),
                'display_hint'        => $this->_pt('All data policies which run after this date'),
                'display_placeholder' => $this->_pt('Start date from'),
                'var_name'            => 'fstart_date',
                'record_field'        => 'start_date',
                'record_check'        => ['check' => '>=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 00:00:00'],
                'default'             => null,
                'linkage_func'        => 'AND',
            ],
            [
                'display_name'        => $this->_pt('Run Up To'),
                'display_hint'        => $this->_pt('All data policies which run before this date'),
                'display_placeholder' => $this->_pt('Start date to'),
                'var_name'            => 'fend_date',
                'record_field'        => 'start_date',
                'record_check'        => ['check' => '<=', 'value' => '%s'],
                'type'                => PHS_Params::T_DATE,
                'extra_type'          => ['format' => $this->_paginator_model::DATE_DB.' 23:59:59'],
                'default'             => null,
                'linkage_func'        => 'AND',
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
                'column_title'        => $this->_pt('Retention Policy'),
                'record_field'        => 'retention_policy_id',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => [$this, 'display_retention_policy'],
            ],
            [
                'column_title'        => $this->_pt('From table'),
                'record_field'        => 'from_table',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('To table'),
                'record_field'        => 'to_table',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Field'),
                'record_field'        => 'date_field',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Max date'),
                'record_field'        => 'last_date',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('Invalid'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('Action Type'),
                'record_field'        => 'type',
                'display_key_value'   => $types_arr,
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'invalid_value'       => $this->_pt('N/A'),
            ],
            [
                'column_title'        => $this->_pt('Rows'),
                'record_field'        => 'total_records',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback'    => function($params) {
                    return ($params['record']['current_records'] ?? '0')
                         .' / '
                         .($params['record']['total_records'] ?? '0');
                },
            ],
            [
                'column_title'        => $this->_pt('Started'),
                'default_sort'        => 1,
                'record_field'        => 'start_date',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('Invalid'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('Finished'),
                'record_field'        => 'end_date',
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

        if ($this->_admin_plugin->can_admin_manage_data_retention()) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_Params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['a' => 'list_runs', 'ad' => 'retention', 'p' => 'admin']);
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
            case 'run_record':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Data retention policy launched with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Launching data retention policy failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = (int)$action['action_params'];
                }

                if (empty($action['action_params'])
                    || !($record_arr = $this->_paginator_model->get_details($action['action_params'], ['table_name' => 'phs_data_retention_runs']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot launch data retention policy. Data retention policy not found.'));

                    return false;
                }

                if (empty($record_arr['retention_policy_id'])
                    || !$this->_data_retention_lib->run_data_retention($record_arr['retention_policy_id'])) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_retention_policy($params) : ?string
    {
        if (empty($params['record']['retention_policy_id'])
            || (!($retention_arr = $this->policies_cache[(int)$params['record']['retention_policy_id']] ?? null)
                && !($retention_arr = $this->_paginator_model->get_details($params['record']['retention_policy_id'], ['table_name' => 'phs_data_retention'])))
        ) {
            return null;
        }

        if ( !isset($this->policies_cache[(int)$params['record']['retention_policy_id']])) {
            $this->policies_cache[(int)$params['record']['retention_policy_id']] = $retention_arr;
        }

        return '<span title="'.$this->_pt('Plugin').'">'.($retention_arr['plugin'] ?? $this::_t('Core')).'</span> '
               .' - '
               .'<span title="'.$this->_pt('Model').'">'.($retention_arr['model'] ?? $this->_pt('N/A')).'</span>'
               .' - '
               .($retention_arr['retention'] ?? $this->_pt('N/A'));
    }

    public function display_actions($params) : ?string
    {
        if (empty($this->_paginator_model) && !$this->load_depencies()) {
            return null;
        }

        if (!$this->_admin_plugin->can_admin_manage_data_retention()) {
            return '-';
        }

        if (empty($params['record']) || !is_array($params['record'])
            || !($retention_run_arr = $this->_paginator_model->data_to_array($params['record'], ['table_name' => 'phs_data_retention_runs']))) {
            return null;
        }

        ob_start();
        if (!empty($retention_run_arr['retention_policy_id'])) {
            ?>
            <a href='javascript:void(0)' onclick="phs_data_retention_list_runs_run_record( '<?php echo $retention_run_arr['id']; ?>' )"
            ><i class='fa fa-fast-forward action-icons' title="<?php
                echo $this->_pt('Re-run data retention policy'); ?>"></i></a>
            <?php
        }

        return ob_get_clean() ?: '';
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
            function phs_data_retention_list_runs_run_record( id )
            {
                if( !confirm( "<?php echo $this->_pte('Are you sure you want to run this data retention policy again?'); ?>" ) ) {
                    return
                }

                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'run_record',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        </script>
        <?php

        return ob_get_clean();
    }
}
