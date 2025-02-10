<?php
namespace phs\plugins\remote_phs\actions\domains;

use phs\PHS;
use phs\PHS_Ajax;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_params;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Api_keys;
use phs\plugins\remote_phs\PHS_Plugin_Remote_phs;
use phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains;

/** @property PHS_Model_Phs_remote_domains $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    private ?PHS_Model_Api_keys $_apikeys_model = null;

    private ?PHS_Plugin_Remote_phs $_remote_plugin = null;

    /**
     * @inheritdoc
     */
    public function should_stop_execution() : ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_remote_plugin->can_admin_list_domains()) {
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
        PHS::page_settings('page_title', $this->_pt('Remote PHS Domains List'));

        $list_arr = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $this->_paginator_model::STATUS_DELETED];
        $list_arr['flags'] = ['include_api_keys_details'];

        $flow_params = [
            'listing_title'          => $this->_pt('Remote PHS Domains List'),
            'term_singular'          => $this->_pt('remote PHS domain'),
            'term_plural'            => $this->_pt('remote PHS domains'),
            'initial_list_arr'       => $list_arr,
            'after_table_callback'   => [$this, 'after_table_callback'],
            'after_filters_callback' => [$this, 'after_filters_callback'],
        ];

        if (PHS_params::_g('unknown_domain', PHS_params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid remote PHS domain or remote PHS domain was not found in database.'));
        }
        if (PHS_params::_g('domain_added', PHS_params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Remote PHS domain saved in database.'));
        }

        if (!($statuses_arr = $this->_paginator_model->get_statuses_as_key_val())) {
            $statuses_arr = [];
        }
        if (!($api_keys_arr = $this->_apikeys_model->get_all_api_keys_as_key_val())) {
            $api_keys_arr = [];
        }

        $filter_api_keys_arr = $api_keys_arr;

        if (!empty($statuses_arr)) {
            $statuses_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $statuses_arr);
        }
        if (!empty($filter_api_keys_arr)) {
            $filter_api_keys_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - ')], $filter_api_keys_arr);
        }

        if (isset($statuses_arr[$this->_paginator_model::STATUS_DELETED])) {
            unset($statuses_arr[$this->_paginator_model::STATUS_DELETED]);
        }

        $can_manage = $this->_remote_plugin->can_admin_manage_domains();

        if (!$can_manage) {
            $bulk_actions = null;
        } else {
            $bulk_actions = [
                [
                    'display_name'    => $this->_pt('Connect'),
                    'action'          => 'bulk_connect',
                    'js_callback'     => 'phs_remote_domains_list_bulk_connect',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Suspend'),
                    'action'          => 'bulk_suspend',
                    'js_callback'     => 'phs_remote_domains_list_bulk_suspend',
                    'checkbox_column' => 'id',
                ],
                [
                    'display_name'    => $this->_pt('Delete'),
                    'action'          => 'bulk_delete',
                    'js_callback'     => 'phs_remote_domains_list_bulk_delete',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt('Title'),
                'display_hint' => $this->_pt('All records containing this value at title field'),
                'var_name'     => 'ftitle',
                'record_field' => 'title',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Handle'),
                'display_hint' => $this->_pt('All records containing this value at handle field'),
                'var_name'     => 'fhandle',
                'record_field' => 'handle',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Domain URL'),
                'display_hint' => $this->_pt('All records containing this value at domain field'),
                'var_name'     => 'fremote_www',
                'record_field' => 'remote_www',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Outgoing API Key'),
                'display_hint' => $this->_pt('All records containing this value at outgoing API key'),
                'var_name'     => 'fout_apikey',
                'record_field' => 'out_apikey',
                'record_check' => ['check' => 'LIKE', 'value' => '%%%s%%'],
                'type'         => PHS_params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Incoming API Key'),
                'var_name'     => 'fapikey_id',
                'record_field' => 'apikey_id',
                'type'         => PHS_params::T_INT,
                'default'      => 0,
                'values_arr'   => $filter_api_keys_arr,
            ],
            [
                'display_name' => $this->_pt('Status'),
                'var_name'     => 'fstatus',
                'record_field' => 'status',
                'type'         => PHS_params::T_INT,
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
                'display_callback'    => [$this, 'display_hide_id'],
            ],
            [
                'column_title'        => $this->_pt('Title'),
                'record_field'        => 'title',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Domain URL'),
                'record_field'        => 'remote_www',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Outgoing API Key'),
                'record_field'        => 'out_apikey',
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'          => $this->_pt('Incoming API Key'),
                'record_field'          => 'apikey_id',
                'display_key_value'     => $api_keys_arr,
                'invalid_value'         => '-',
                'extra_style'           => 'text-align:center;',
                'extra_records_style'   => 'text-align:center;',
                'display_callback'      => [$this, 'display_incoming_api_key'],
                'extra_callback_params' => ['api_keys_arr' => $api_keys_arr],
            ],
            [
                'column_title'        => $this->_pt('Status'),
                'record_field'        => 'status',
                'display_key_value'   => $statuses_arr,
                'invalid_value'       => $this->_pt('Undefined'),
                'extra_style'         => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title'        => $this->_pt('Created'),
                'default_sort'        => 1,
                'record_db_field'     => 'cdate',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('Invalid'),
                'extra_style'         => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title'        => $this->_pt('Actions'),
                'display_callback'    => [$this, 'display_actions'],
                'extra_style'         => 'width:150px;',
                'extra_records_style' => 'text-align:right;',
                'sortable'            => false,
            ],
        ];

        if ($can_manage) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'id',
                'type' => PHS_params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'remote_phs', 'c' => 'admin', 'a' => 'list', 'ad' => 'domains']);
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
            case 'bulk_suspend':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required remote PHS domains suspended with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Suspending selected remote PHS domains failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed suspending all selected remote PHS domains. Remote PHS domains which failed suspention are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_remote_plugin->can_admin_manage_domains()) {
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
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->act_suspend($record_id)) {
                        $remaining_ids_arr[] = $record_id;
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

            case 'bulk_connect':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Started connection procedure for required remote PHS domains.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Starting connection procedure for selected remote PHS domains failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed starting connection procedure for all selected remote PHS domains. Remote PHS domains for which starting connection failed are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_remote_plugin->can_admin_manage_domains()) {
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
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->act_connect($record_id)) {
                        $remaining_ids_arr[] = $record_id;
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
                        PHS_Notifications::add_success_notice($this->_pt('Required remote PHS domains deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting selected remote PHS domains failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed deleting all selected remote PHS domains. Remote PHS domains which failed deletion are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_remote_plugin->can_admin_manage_domains()) {
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
                foreach ($scope_arr[$scope_key] as $record_id) {
                    if (!$this->_paginator_model->act_delete($record_id)) {
                        $remaining_ids_arr[] = $record_id;
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

            case 'do_connect':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Connecting procedure started for remote PHS domain.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Starting connect procedure for remote PHS domain failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_remote_plugin->can_admin_manage_domains()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (empty($action['action_params'])
                 || !($payment_category_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot connect to remote PHS domain. Remote PHS domain not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_connect($payment_category_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'do_suspend':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Remote PHS domain suspended with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Suspending remote PHS domain failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_remote_plugin->can_admin_manage_domains()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (empty($action['action_params'])
                 || !($payment_category_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate remote PHS domain. Remote PHS domain not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_suspend($payment_category_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'do_delete':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Remote PHS domain deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting remote PHS domain failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_remote_plugin->can_admin_manage_domains()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (empty($action['action_params'])
                 || !($payment_category_arr = $this->_paginator_model->get_details($action['action_params']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete remote PHS domain. Remote PHS domain not found in database.'));

                    return false;
                }

                if (!$this->_paginator_model->act_delete($payment_category_arr)) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;
        }

        return $action_result_params;
    }

    public function display_hide_id(array $params) : string
    {
        return '';
    }

    public function display_incoming_api_key(array $params) : ?string
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return null;
        }

        if (empty($params['preset_content'])
            || !PHS::user_logged_in()) {
            return '-';
        }

        if (!$this->_paginator->is_cell_rendering_for_html($params)) {
            return $params['record']['apikey_id'];
        }

        if (!empty($params['record']['apikey_id'])
         && can(PHS_Roles::ROLEU_MANAGE_API_KEYS)
         && ($edit_apikey_url = PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'apikeys'],
             ['aid' => $params['record']['apikey_id'], 'back_page' => $this->_paginator->get_full_url()]))) {
            return '<a href="'.$edit_apikey_url.'">'.$params['preset_content'].'</a>'
                   .(!empty($params['record']['api_keys_api_key']) ? '<br/>'.$params['record']['api_keys_api_key'] : '');
        }

        return $params['preset_content']
               .(!empty($params['record']['api_keys_api_key']) ? '<br/>'.$params['record']['api_keys_api_key'] : '');
    }

    public function display_actions(array $params) : ?string
    {
        if (!$this->_paginator->is_cell_rendering_for_html($params)
            || !$this->_remote_plugin->can_admin_manage_domains()) {
            return '-';
        }

        if (empty($params['record']) || !is_array($params['record'])
         || !($domain_arr = $this->_paginator_model->data_to_array($params['record']))) {
            return null;
        }

        ob_start();
        ?>
        <a href="javascript:void(0)" onclick="phs_remote_domains_list_info( '<?php echo $domain_arr['id']; ?>' )">
            <i class="fa fa-info action-icons" title="<?php echo $this->_pt('Remote PHS domain details'); ?>"></i></a>
        <a href="<?php echo PHS::url(['p' => 'remote_phs', 'c' => 'admin', 'a' => 'edit', 'ad' => 'domains'],
            ['did' => $domain_arr['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>">
            <i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt('Edit remote PHS domain'); ?>"></i></a>
        <?php
        if ($this->_paginator_model->is_not_connected($domain_arr)
         || $this->_paginator_model->is_connection_error($domain_arr)
         || $this->_paginator_model->is_suspended($domain_arr)) {
            ?>
            <a href="javascript:void(0)" onclick="phs_remote_domains_list_connect( '<?php echo $domain_arr['id']; ?>' )">
                <i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt('Connect remote PHS domain'); ?>"></i></a>
            <?php
        }
        if ($this->_paginator_model->is_connected($domain_arr)) {
            ?>
            <a href="javascript:void(0)" onclick="phs_remote_domains_list_suspend( '<?php echo $domain_arr['id']; ?>' )">
                <i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt('Suspend remote PHS domain'); ?>"></i></a>
            <?php
        }

        if (!$this->_paginator_model->is_waiting_connection($domain_arr)) {
            ?>
            <a href="javascript:void(0)" onclick="phs_remote_domains_list_delete( '<?php echo $domain_arr['id']; ?>' )">
                <i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt('Delete remote PHS domain'); ?>"></i></a>
            <?php
        }

        return ob_get_clean() ?: '';
    }

    public function after_filters_callback(array $params) : string
    {
        if (!$this->_remote_plugin->can_admin_manage_domains()) {
            return '';
        }

        ob_start();
        ?>
        <div style="width:97%;min-width:97%;margin: 15px auto 0;">
          <a class="btn btn-small btn-success" style="color:white;"
             href="<?php echo PHS::url(['p' => 'remote_phs', 'c' => 'admin', 'a' => 'add', 'ad' => 'domains']); ?>">
              <i class="fa fa-plus"></i> <?php echo $this->_pt('Add Remote PHS Domain'); ?></a>
        </div>
        <div class="clearfix"></div>
        <?php

        return ob_get_clean() ?: '';
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
        function phs_remote_domains_list_connect( id )
        {
            if( !confirm( "<?php echo self::_e('Are you sure you want to connect to this remote PHS domain?', '"'); ?>" ) ) {
                return;
            }

            <?php
            $url_params = [];
        $url_params['action'] = [
            'action'        => 'do_connect',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
        }
        function phs_remote_domains_list_suspend( id )
        {
            if( !confirm( "<?php echo self::_e('Are you sure you want to suspend this remote PHS domain?', '"'); ?>" ) ) {
                return;
            }

            <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'do_suspend',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
        }
        function phs_remote_domains_list_info( id )
        {
            PHS_JSEN.createAjaxDialog( {
                width: 800,
                height: 600,
                suffix: "phs_info_remote_domain",
                resizable: true,
                close_outside_click: false,

                title: "<?php echo self::_e($this->_pt('Remote Domain Details')); ?>",
                method: "GET",
                url: "<?php echo PHS_Ajax::url(['p' => 'remote_phs', 'a' => 'info_ajax', 'ad' => 'domains']); ?>",
                url_data: { domain_id: id }
            });
        }
        function phs_remote_domains_list_delete( id )
        {
            if( !confirm( "<?php echo self::_e('Are you sure you want to DELETE this remote PHS domain?', '"'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) ) {
                return;
            }

            <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'do_delete',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
        }

        function phs_remote_domains_list_get_checked_ids_count()
        {
            const checkboxes_list = phs_paginator_get_checkboxes_checked('id');
            if( !checkboxes_list || !checkboxes_list.length ) {
                return 0;
            }

            return checkboxes_list.length;
        }

        function phs_remote_domains_list_bulk_suspend()
        {
            const total_checked = phs_remote_domains_list_get_checked_ids_count();

            if( !total_checked ) {
                alert( "<?php echo self::_e('Please select remote PHS domains you want to suspend first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to suspend %s remote PHS domains?', '"'), '" + total_checked + "'); ?>" ) ) {
                return false;
            }

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj ) {
                form_obj.submit();
            }
        }

        function phs_remote_domains_list_bulk_connect()
        {
            const total_checked = phs_remote_domains_list_get_checked_ids_count();

            if( !total_checked ) {
                alert( "<?php echo self::_e('Please select remote PHS domains you want to connect to first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to connect to %s remote PHS domains?', '"'), '" + total_checked + "'); ?>" ) ) {
                return false;
            }

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj ) {
                form_obj.submit();
            }
        }

        function phs_remote_domains_list_bulk_delete()
        {
            const total_checked = phs_remote_domains_list_get_checked_ids_count();

            if( !total_checked ) {
                alert( "<?php echo self::_e('Please select remote PHS domains you want to delete first.', '"'); ?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf(self::_e('Are you sure you want to DELETE %s remote PHS domains?', '"'), '" + total_checked + "'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) ) {
                return false;
            }

            const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
            if( form_obj ) {
                form_obj.submit();
            }
        }
        </script>
        <?php

            return ob_get_clean() ?: '';
    }

    protected function _load_dependencies() : bool
    {
        if ((!$this->_paginator_model && !($this->_paginator_model = PHS_Model_Phs_remote_domains::get_instance()))
            || (!$this->_remote_plugin && !($this->_remote_plugin = PHS_Plugin_Remote_phs::get_instance()))
            || (!$this->_apikeys_model && !($this->_apikeys_model = PHS_Model_Api_keys::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
