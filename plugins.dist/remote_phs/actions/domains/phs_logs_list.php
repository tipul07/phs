<?php

namespace phs\plugins\remote_phs\actions\domains;

use \phs\PHS;
use phs\PHS_Ajax;
use phs\libraries\PHS_Roles;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;

/** @property \phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $_paginator_model */
class PHS_Action_Logs_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $_remote_plugin */
    private $_remote_plugin;

    /** @var array $_cuser_details_arr */
    private $_cuser_details_arr = [];

    public function load_depencies()
    {
        if( !($this->_remote_plugin = PHS::load_plugin( 'remote_phs' )) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t load PHS remote plugin.' ) );
            return false;
        }

        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'phs_remote_domains', 'remote_phs' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load remote PHS domains model.' ) );
            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( !$this->_remote_plugin->can_admin_list_logs( $current_user )
         && !$this->_remote_plugin->can_admin_manage_logs( $current_user ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to list remote PHS domains logs.' ) );
            return false;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Remote PHS Domains Logs List' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You should login first...' ) );
            return false;
        }

        if( !$this->_remote_plugin->can_admin_list_logs( $current_user )
         && !$this->_remote_plugin->can_admin_manage_logs( $current_user ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to list remote PHS domains logs.' ) );
            return false;
        }

        $domains_model = $this->_paginator_model;

        $list_arr = $domains_model->fetch_default_flow_params( [ 'table_name' => 'phs_remote_logs' ] );
        $list_arr['flags'] = [ 'include_domain_details' ];

        $flow_params = [
            'listing_title' => $this->_pt( 'Remote PHS Domains Logs List' ),
            'term_singular' => $this->_pt( 'remote PHS domain log' ),
            'term_plural' => $this->_pt( 'remote PHS domain logs' ),
            'initial_list_arr' => $list_arr,
            'after_table_callback' => [ $this, 'after_table_callback' ],
        ];

        if( !($all_domains_arr = $this->_paginator_model->get_all_remote_domains_as_key_val()) )
            $all_domains_arr = [];
        if( !($statuses_arr = $this->_paginator_model->get_log_statuses_as_key_val()) )
            $statuses_arr = [];
        if( !($log_types_arr = $this->_paginator_model->get_log_types_as_key_val()) )
            $log_types_arr = [];

        $filter_domains_arr = [];
        $filter_statuses_arr = [];
        $filter_log_types_arr = [];
        if( !empty( $all_domains_arr ) )
            $filter_domains_arr = self::merge_array_assoc( [ 0 => $this->_pt( ' - Choose - ' ) ], $all_domains_arr );
        if( !empty( $statuses_arr ) )
            $filter_statuses_arr = self::merge_array_assoc( [ 0 => $this->_pt( ' - Choose - ' ) ], $statuses_arr );
        if( !empty( $log_types_arr ) )
            $filter_log_types_arr = self::merge_array_assoc( [ 0 => $this->_pt( ' - Choose - ' ) ], $log_types_arr );

        if( !$this->_remote_plugin->can_admin_manage_logs( $current_user ) )
            $bulk_actions = false;

        else
        {
            $bulk_actions = [
                [
                    'display_name' => $this->_pt( 'Delete' ),
                    'action' => 'bulk_delete',
                    'js_callback' => 'phs_remote_domain_logs_list_bulk_delete',
                    'checkbox_column' => 'id',
                ],
            ];
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt( 'PHS Remote Domain' ),
                'var_name' => 'fdomain_id',
                'record_field' => 'domain_id',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $filter_domains_arr,
            ],
            [
                'display_name' => $this->_pt( 'Route' ),
                'display_hint' => $this->_pt( 'All records containing this value at route field' ),
                'var_name' => 'ftitle',
                'record_field' => 'title',
                'record_check' => [ 'check' => 'LIKE', 'value' => '%%%s%%' ],
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ],
            [
                'display_name' => $this->_pt( 'Log Type' ),
                'var_name' => 'flog_type',
                'record_field' => 'type',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $filter_log_types_arr,
            ],
            [
                'display_name' => $this->_pt( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $filter_statuses_arr,
            ],
        ];

        $columns_arr = [
            [
                'column_title' => '#',
                'record_field' => 'id',
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_style' => 'min-width:50px;max-width:80px;',
                'extra_records_style' => 'text-align:center;',
                'display_callback' => [ $this, 'display_hide_id' ],
            ],
            [
                'column_title' => $this->_pt( 'PHS Remote Domain' ),
                'record_field' => 'domain_id',
                'display_key_value' => $all_domains_arr,
                'invalid_value' => '-',
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title' => $this->_pt( 'Type' ),
                'record_field' => 'type',
                'display_key_value' => $log_types_arr,
                'invalid_value' => '-',
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title' => $this->_pt( 'Route' ),
                'record_field' => 'route',
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title' => $this->_pt( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $statuses_arr,
                'invalid_value' => $this->_pt( 'Undefined' ),
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ],
            [
                'column_title' => $this->_pt( 'Created' ),
                'default_sort' => 1,
                'record_db_field' => 'cdate',
                'display_callback' => [ &$this->_paginator, 'pretty_date' ],
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Invalid' ),
                'extra_style' => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ],
            [
                'column_title' => $this->_pt( 'Actions' ),
                'display_callback' => [ $this, 'display_actions' ],
                'extra_style' => 'width:150px;',
                'extra_records_style' => 'text-align:right;',
                'sortable' => false,
            ],
        ];

        if( $this->_remote_plugin->can_admin_manage_logs( $current_user ) )
        {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key' => 'id',
                'type' => PHS_params::T_INT,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( [ 'p' => 'remote_phs', 'c' => 'admin', 'a' => 'logs_list', 'ad' => 'domains' ] );
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
    public function manage_action( $action )
    {
        $this->reset_error();

        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( !($current_user = PHS::user_logged_in()) )
            $current_user = false;

        $remote_plugin = $this->_remote_plugin;

        $action_result_params = $this->_paginator->default_action_params();

        if( empty( $action ) || !is_array( $action )
         || empty( $action['action'] ) )
            return $action_result_params;

        $action_result_params['action'] = $action['action'];

        switch( $action['action'] )
        {
            default:
                PHS_Notifications::add_error_notice( $this->_pt( 'Unknown action.' ) );
                return true;
            break;

            case 'bulk_delete':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'PHS domain logs deleted with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting selected remote PHS domain logs failed. Please try again.' ) );
                    elseif( $action['action_result'] === 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed deleting all selected remote PHS domain logs. Log records which failed deletion are still selected. Please try again.' ) );

                    return true;
                }

                if( empty( $current_user )
                 || !$remote_plugin->can_admin_manage_logs( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage remote PHS domain logs.' ) );
                    return false;
                }

                if( !($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 || !($scope_key = @sprintf( $ids_checkboxes_name, 'id' ))
                 || !($scope_all_key = @sprintf( $ids_all_checkbox_name, 'id' ))
                 || empty( $scope_arr[$scope_key] )
                 || !is_array( $scope_arr[$scope_key] ) )
                    return true;

                $remaining_ids_arr = [];
                foreach( $scope_arr[$scope_key] as $record_id )
                {
                    if( !$this->_paginator_model->act_delete_log( $record_id ) )
                    {
                        $remaining_ids_arr[] = $record_id;
                    }
                }

                if( isset( $scope_arr[$scope_all_key] ) )
                    unset( $scope_arr[$scope_all_key] );

                if( empty( $remaining_ids_arr ) )
                {
                    $action_result_params['action_result'] = 'success';

                    unset( $scope_arr[$scope_key] );

                    $action_result_params['action_redirect_url_params'] = [ 'force_scope' => $scope_arr ];
                } else
                {
                    if( count( $remaining_ids_arr ) !== count( $scope_arr[$scope_key] ) )
                        $action_result_params['action_result'] = 'failed_some';
                    else
                        $action_result_params['action_result'] = 'failed';

                    $scope_arr[$scope_key] = implode( ',', $remaining_ids_arr );

                    $action_result_params['action_redirect_url_params'] = [ 'force_scope' => $scope_arr ];
                }
            break;

            case 'do_delete':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Remote PHS domain log deleted with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting remote PHS domain log failed. Please try again.' ) );

                    return true;
                }

                if( empty( $current_user )
                 || !$remote_plugin->can_admin_manage_logs( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage remote PHS domain logs.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = (int)$action['action_params'];

                if( empty( $action['action_params'] )
                 || !($record_arr = $this->_paginator_model->get_details( $action['action_params'], [ 'table_name' => 'phs_remote_logs' ] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete remote PHS domain log. Remote PHS domain log not found in database.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_delete_log( $record_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;
        }

        return $action_result_params;
    }

    public function display_hide_id( $params )
    {
        return '';
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model )
         && !$this->load_depencies() )
            return false;

        if( !($current_user = PHS::current_user()) )
            $current_user = false;

        if( !$this->_remote_plugin->can_admin_manage_domains( $current_user ) )
            return '-';

        if( empty( $params )
         || !is_array( $params )
         || empty( $params['record'] ) || !is_array( $params['record'] )
         || !($log_arr = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        ob_start();
        ?>
        <a href="javascript:void(0)" onclick="phs_remote_domain_logs_list_info( '<?php echo $log_arr['id']?>' )">
            <i class="fa fa-info action-icons" title="<?php echo $this->_pt( 'Remote PHS domain log details' )?>"></i></a>
        <a href="javascript:void(0)" onclick="phs_remote_domain_logs_list_delete( '<?php echo $log_arr['id']?>' )">
            <i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete remote PHS domain log' )?>"></i></a>
        <?php

        return ob_get_clean();
    }

    public function after_table_callback( $params )
    {
        static $js_functionality = false;

        if( !empty( $js_functionality ) )
            return '';

        $js_functionality = true;

        ob_start();
        ?>
        <script type="text/javascript">
        function phs_remote_domain_logs_list_info( id )
        {
            PHS_JSEN.createAjaxDialog( {
                width: 800,
                height: 600,
                suffix: "phs_info_remote_domain_log",
                resizable: true,
                close_outside_click: false,

                title: "<?php echo self::_e( $this->_pt( 'Remote Domain Log Details' ) )?>",
                method: "GET",
                url: "<?php echo PHS_Ajax::url( [ 'p' => 'remote_phs', 'a' => 'log_info_ajax', 'ad' => 'domains' ] )?>",
                url_data: { log_id: id }
            });
        }
        function phs_remote_domain_logs_list_delete( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this remote PHS domain?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = [];
                $url_params['action'] = [
                    'action' => 'do_delete',
                    'action_params' => '" + id + "',
                ]
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }

        function phs_remote_domain_logs_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_remote_domain_logs_list_bulk_delete()
        {
            var total_checked = phs_remote_domain_logs_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select remote PHS domain logs you want to delete first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to DELETE %s remote PHS domain logs?', '"' ), '" + total_checked + "' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
                if( form_obj )
                    form_obj.submit();
            }
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
