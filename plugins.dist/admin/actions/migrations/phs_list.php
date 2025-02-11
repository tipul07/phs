<?php
namespace phs\plugins\admin\actions\migrations;

use phs\PHS;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Migrations;
use phs\plugins\accounts\models\PHS_Model_Accounts;

/** @property PHS_Model_Migrations $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Model_Accounts $_accounts_model = null;

    public function should_stop_execution() : ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_admin_plugin->can_admin_list_migrations()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        return null;
    }

    public function load_paginator_params() : ?array
    {
        PHS::page_settings('page_title', $this->_pt('Migrations List'));

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
        $return_arr['base_url'] = PHS::url(['a' => 'list', 'ad' => 'migrations', 'p' => 'admin']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['bulk_actions'] = $bulk_actions;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    public function manage_action(array $action) : null | bool | array
    {
        $action_result_params = $this->_paginator->default_action_params();

        if (empty($action['action'])) {
            return $action_result_params;
        }

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;
            case 'rerun_migration':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Migration launched in a background script with success. Check maintenance.log to see the output.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed launching migration in a background script. Check maintenance.log to see any errors reported.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_migrations()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (empty($action['action_params'])
                 || !($migration_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Migration script details not found in database.'));

                    return false;
                }

                if (!($migrations_manager = migrations_manager())
                    || !$migrations_manager->launch_rerun_migration_job($migration_arr)) {
                    self::st_reset_error();
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_progress(array $params) : ?string
    {
        if (empty($params['record']['id'])) {
            return null;
        }

        $migration_arr = $params['record'];

        if (!$this->_paginator->is_cell_rendering_for_html($params)) {
            return $migration_arr['current_count'].'/'.$migration_arr['total_count'];
        }

        $progress_perc = min(100, (!(float)$migration_arr['total_count'] ? 0 : ceil(($migration_arr['current_count'] * 100) / $migration_arr['total_count'])));

        ob_start();
        ?>
        <div>
            <?php echo $migration_arr['current_count'].' / '.$migration_arr['total_count'].' ('.$progress_perc.'%)'; ?><br/>
            <div style="width:100%;height:10px;background-color:red;"><div style="width:<?php echo $progress_perc; ?>%;height:10px;background-color:green;"></div></div>
        </div>
        <?php

        return ob_get_clean() ?: '';
    }

    public function display_actions(array $params) : ?string
    {
        if (!$this->_paginator->is_cell_rendering_for_html($params)
            || !$this->_admin_plugin->can_admin_manage_roles()) {
            return '-';
        }

        if (empty($params['record']['id'])) {
            return null;
        }

        $migration_arr = $params['record'];

        $is_finished = $this->_paginator_model->is_finished($migration_arr);
        $is_stalling = $this->_paginator_model->is_stalling($migration_arr);

        ob_start();
        if ($is_finished || $is_stalling) {
            ?>
            <a href="javascript:void(0)" onclick="phs_migrations_list_rerun_migration( '<?php echo $migration_arr['id']; ?>' )"
            ><i class="fa fa-refresh action-icons" title="<?php echo form_str($this->_pt('Run migration again')); ?>"></i></a>
            <?php
        }

        return ob_get_clean();
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
        </script>
        <?php

        return ob_get_clean() ?: '';
    }

    protected function _load_dependencies() : bool
    {
        $this->reset_error();

        if ((empty($this->_admin_plugin)
             && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
         || (empty($this->_accounts_model)
             && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
         || (empty($this->_paginator_model)
             && !($this->_paginator_model = PHS_Model_Migrations::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
