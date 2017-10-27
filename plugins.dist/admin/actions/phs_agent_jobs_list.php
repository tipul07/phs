<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_utils;

/** @property \phs\system\core\models\PHS_Model_Agent_jobs $_paginator_model */
class PHS_Action_Agent_jobs_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    /** @var array $_cuser_details_arr */
    private $_cuser_details_arr = array();

    public function load_depencies()
    {
        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'agent_jobs' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load agent jobs model.' ) );
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

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_LIST_AGENT_JOBS ) )
        {
            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 's2p_companies', 'a' => 'company_details' ), array( 'need_company_details' => 1 ) );

            return $action_result;
        }
        
        return false;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Manage Agent Jobs' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You should login first...' ) );
            return false;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_LIST_AGENT_JOBS ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to list agent jobs.' ) );
            return false;
        }

        $agent_jobs_model = $this->_paginator_model;

        $list_arr = array();

        $flow_params = array(
            'term_singular' => $this->_pt( 'agent job' ),
            'term_plural' => $this->_pt( 'agent jobs' ),
            'initial_list_arr' => $list_arr,
            'after_table_callback' => array( $this, 'after_table_callback' ),
            'after_filters_callback' => array( $this, 'after_filters_callback' ),
			'listing_title' => $this->_pt( 'Agent Jobs' ),
        );

        if( PHS_params::_g( 'unknown_job', PHS_params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Invalid agent job or agent job was not found in database.' ) );
        if( PHS_params::_g( 'job_added', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Agent job details saved in database.' ) );

        if( !($statuses_arr = $this->_paginator_model->get_statuses_as_key_val()) )
            $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            $statuses_arr = self::merge_array_assoc( array( 0 => $this->_pt( ' - Choose - ' ) ), $statuses_arr );

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
            $bulk_actions = false;

        else
        {
            $bulk_actions = array(
                array(
                    'display_name' => $this->_pt( 'Inactivate' ),
                    'action' => 'bulk_inactivate',
                    'js_callback' => 'phs_agent_jobs_list_bulk_inactivate',
                    'checkbox_column' => 'id',
                ),
                array(
                    'display_name' => $this->_pt( 'Activate' ),
                    'action' => 'bulk_activate',
                    'js_callback' => 'phs_agent_jobs_list_bulk_activate',
                    'checkbox_column' => 'id',
                ),
                array(
                    'display_name' => $this->_pt( 'Delete' ),
                    'action' => 'bulk_delete',
                    'js_callback' => 'phs_agent_jobs_list_bulk_delete',
                    'checkbox_column' => 'id',
                ),
            );
        }

        $filters_arr = array(
            array(
                'display_name' => $this->_pt( 'Handler' ),
                'display_hint' => $this->_pt( 'All records containing this value at handler field' ),
                'var_name' => 'fhandler',
                'record_field' => 'handler',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'Route' ),
                'display_hint' => $this->_pt( 'All records containing this value at route field' ),
                'var_name' => 'froute',
                'record_field' => 'route',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $statuses_arr,
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => '#',
                'record_field' => 'id',
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_style' => 'min-width:55px;',
                'extra_records_style' => 'text-align:center;',
                'display_callback' => array( $this, 'display_hide_id' ),
            ),
            array(
                'column_title' => $this->_pt( 'Title' ),
                'record_field' => 'title',
                'display_callback' => array( $this, 'display_job_title' ),
            ),
            array(
                'column_title' => $this->_pt( 'Route' ),
                'record_field' => 'route',
                //'display_key_value' => $countries_arr,
                'invalid_value' => $this->_pt( 'N/A' ),
            ),
            array(
                'column_title' => $this->_pt( 'Plugin' ),
                'record_field' => 'plugin',
                //'display_key_value' => $countries_arr,
                'invalid_value' => $this->_pt( 'N/A' ),
            ),
            array(
                'column_title' => $this->_pt( 'Interval' ),
                'record_field' => 'timed_seconds',
                'display_callback' => array( $this, 'display_timed_seconds' ),
            ),
            array(
                'column_title' => $this->_pt( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $statuses_arr,
                'invalid_value' => $this->_pt( 'Undefined' ),
                'extra_classes' => 'status_th',
                'extra_records_classes' => 'status',
            ),
            array(
                'column_title' => $this->_pt( 'Next run' ),
                'record_field' => 'timed_action',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Invalid' ),
                'extra_classes' => 'date_th',
                'extra_records_classes' => 'date',
            ),
            array(
                'column_title' => $this->_pt( 'Created' ),
                'default_sort' => 1,
                'record_db_field' => 'cdate',
                'record_field' => 'cdate %s, handler ASC ',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Invalid' ),
				'extra_classes' => 'date_th',
				'extra_records_classes' => 'date',
            ),
            array(
                'column_title' => $this->_pt( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'sortable' => false,
				'extra_style' => 'width:120px;',
				'extra_classes' => 'actions_th',
				'extra_records_classes' => 'actions',
            )
        );

        if( PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
        {
            $columns_arr[0]['checkbox_record_index_key'] = array(
                'key' => 'id',
                'type' => PHS_params::T_INT,
            );
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'agent_jobs_list' ) );
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

        $action_result_params = $this->_paginator->default_action_params();

        if( empty( $action ) or !is_array( $action )
         or empty( $action['action'] ) )
            return $action_result_params;

        $action_result_params['action'] = $action['action'];

        switch( $action['action'] )
        {
            default:
                PHS_Notifications::add_error_notice( $this->_pt( 'Unknown action.' ) );
                return true;
            break;

            case 'bulk_activate':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required agent jobs activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating selected agent jobs failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed activating all selected agent jobs. Agent jobs which failed activation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage agent jobs.' ) );
                    return false;
                }

                if( !($scope_arr = $this->_paginator->get_scope())
                 or !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 or !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 or !($scope_key = @sprintf( $ids_checkboxes_name, 'id' ))
                 or !($scope_all_key = @sprintf( $ids_all_checkbox_name, 'id' ))
                 or empty( $scope_arr[$scope_key] )
                 or !is_array( $scope_arr[$scope_key] ) )
                    return true;

                $remaining_ids_arr = array();
                foreach( $scope_arr[$scope_key] as $address_id )
                {
                    if( !$this->_paginator_model->act_activate( $address_id ) )
                    {
                        $remaining_ids_arr[] = $address_id;
                    }
                }

                if( isset( $scope_arr[$scope_all_key] ) )
                    unset( $scope_arr[$scope_all_key] );

                if( empty( $remaining_ids_arr ) )
                {
                    $action_result_params['action_result'] = 'success';

                    unset( $scope_arr[$scope_key] );

                    $action_result_params['action_redirect_url_params'] = array( 'force_scope' => $scope_arr );
                } else
                {
                    if( count( $remaining_ids_arr ) != count( $scope_arr[$scope_key] ) )
                        $action_result_params['action_result'] = 'failed_some';
                    else
                        $action_result_params['action_result'] = 'failed';

                    $scope_arr[$scope_key] = implode( ',', $remaining_ids_arr );

                    $action_result_params['action_redirect_url_params'] = array( 'force_scope' => $scope_arr );
                }
            break;

            case 'bulk_inactivate':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required agent jobs inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating selected agent jobs failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed inactivating all selected agent jobs. Agent jobs which failed inactivation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage agent jobs.' ) );
                    return false;
                }

                if( !($scope_arr = $this->_paginator->get_scope())
                 or !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 or !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 or !($scope_key = @sprintf( $ids_checkboxes_name, 'id' ))
                 or !($scope_all_key = @sprintf( $ids_all_checkbox_name, 'id' ))
                 or empty( $scope_arr[$scope_key] )
                 or !is_array( $scope_arr[$scope_key] ) )
                    return true;

                $remaining_ids_arr = array();
                foreach( $scope_arr[$scope_key] as $address_id )
                {
                    if( !$this->_paginator_model->act_inactivate( $address_id ) )
                    {
                        $remaining_ids_arr[] = $address_id;
                    }
                }

                if( isset( $scope_arr[$scope_all_key] ) )
                    unset( $scope_arr[$scope_all_key] );

                if( empty( $remaining_ids_arr ) )
                {
                    $action_result_params['action_result'] = 'success';

                    unset( $scope_arr[$scope_key] );

                    $action_result_params['action_redirect_url_params'] = array( 'force_scope' => $scope_arr );
                } else
                {
                    if( count( $remaining_ids_arr ) != count( $scope_arr[$scope_key] ) )
                        $action_result_params['action_result'] = 'failed_some';
                    else
                        $action_result_params['action_result'] = 'failed';

                    $scope_arr[$scope_key] = implode( ',', $remaining_ids_arr );

                    $action_result_params['action_redirect_url_params'] = array( 'force_scope' => $scope_arr );
                }
            break;

            case 'bulk_delete':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required agent jobs deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting selected agent jobs failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed deleting all selected agent jobs. Agent jobs which failed deletion are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage agent jobs.' ) );
                    return false;
                }

                if( !($scope_arr = $this->_paginator->get_scope())
                 or !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 or !($ids_all_checkbox_name = $this->_paginator->get_all_checkbox_name_format())
                 or !($scope_key = @sprintf( $ids_checkboxes_name, 'id' ))
                 or !($scope_all_key = @sprintf( $ids_all_checkbox_name, 'id' ))
                 or empty( $scope_arr[$scope_key] )
                 or !is_array( $scope_arr[$scope_key] ) )
                    return true;

                $remaining_ids_arr = array();
                foreach( $scope_arr[$scope_key] as $address_id )
                {
                    if( !$this->_paginator_model->act_delete( $address_id ) )
                    {
                        $remaining_ids_arr[] = $address_id;
                    }
                }

                if( isset( $scope_arr[$scope_all_key] ) )
                    unset( $scope_arr[$scope_all_key] );

                if( empty( $remaining_ids_arr ) )
                {
                    $action_result_params['action_result'] = 'success';

                    unset( $scope_arr[$scope_key] );

                    $action_result_params['action_redirect_url_params'] = array( 'force_scope' => $scope_arr );
                } else
                {
                    if( count( $remaining_ids_arr ) != count( $scope_arr[$scope_key] ) )
                        $action_result_params['action_result'] = 'failed_some';
                    else
                        $action_result_params['action_result'] = 'failed';

                    $scope_arr[$scope_key] = implode( ',', $remaining_ids_arr );

                    $action_result_params['action_redirect_url_params'] = array( 'force_scope' => $scope_arr );
                }
            break;

            case 'do_activate':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Agent job activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating agent job failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage agent job.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );
                 
                if( empty( $action['action_params'] )
                 or !($address_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot activate address. Agent job not found in database.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_activate( $address_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'do_inactivate':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Agent job inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating agent job failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage agent jobs.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($address_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot inactivate address. Agent job not found in database.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_inactivate( $address_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
           break;

            case 'do_delete':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Agent job deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting agent job failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage agent jobs.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($address_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete address. Agent job not found in database.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_delete( $address_arr ) )
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

    public function display_job_title( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($agent_job = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        $paginator_obj = $this->_paginator;

        if( empty( $params['preset_content'] ) )
            $params['preset_content'] = '';

        if( !empty( $params['request_render_type'] ) )
        {
            switch( $params['request_render_type'] )
            {
                case $paginator_obj::CELL_RENDER_JSON:
                case $paginator_obj::CELL_RENDER_TEXT:
                    $params['preset_content'] .= (!empty( $params['preset_content'] )?' - ':'').$params['record']['handler'];
                break;

                case $paginator_obj::CELL_RENDER_HTML:
                    if( !empty( $params['preset_content'] ) )
                        $params['preset_content'] .= '<br/><small>'.$params['record']['handler'].'</small>';
                    else
                        $params['preset_content'] = $params['record']['handler'];
                break;
            }
        }

        return $params['preset_content'];
    }

    public function display_timed_seconds( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($agent_job = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        $paginator_obj = $this->_paginator;

        if( empty( $params['record']['timed_seconds'] ) )
            $params['record']['timed_seconds'] = 0;

        $runs_every_x_str = $this->_pt( 'Runs every %s', PHS_utils::parse_period( $params['record']['timed_seconds'] ) );

        $cell_str = '';
        if( !empty( $params['request_render_type'] ) )
        {
            switch( $params['request_render_type'] )
            {
                case $paginator_obj::CELL_RENDER_JSON:
                case $paginator_obj::CELL_RENDER_TEXT:
                    $cell_str = $params['record']['timed_seconds'].'s - '.$runs_every_x_str;
                break;

                case $paginator_obj::CELL_RENDER_HTML:
                    $cell_str = '<span title="'.self::_e( $runs_every_x_str ).'">'.$params['record']['timed_seconds'].'s</span>';
                break;
            }
        }

        return $cell_str;
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( !($current_user = PHS::current_user()) )
            $current_user = false;

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
            return '-';

        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($agent_job = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        $job_is_inactive = $this->_paginator_model->job_is_inactive( $agent_job );
        $job_is_active = $this->_paginator_model->job_is_active( $agent_job );
        $job_is_suspended = $this->_paginator_model->job_is_suspended( $agent_job );

        ob_start();
        if( !$job_is_suspended )
        {
            ?>
            <a href="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'agent_job_edit' ), array( 'aid' => $agent_job['id'], 'back_page' => $this->_paginator->get_full_url() ) )?>"><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt( 'Edit agent job' )?>"></i></a>
            <?php
        }
        if( $job_is_inactive )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_agent_jobs_list_activate( '<?php echo $agent_job['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt( 'Activate agent job' )?>"></i></a>
            <?php
        }
        if( $job_is_active )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_agent_jobs_list_inactivate( '<?php echo $agent_job['id']?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt( 'Inactivate agent job' )?>"></i></a>
            <?php
        }

        ?>
        <a href="javascript:void(0)" onclick="phs_agent_jobs_list_delete( '<?php echo $agent_job['id']?>', <?php echo (!empty( $agent_job['plugin'] )?1:0)?> )"><i class="fa fa-times action-icons" title="<?php echo $this->_pt( 'Delete agent job' )?>"></i></a>
        <?php

        return ob_get_clean();
    }

    public function after_filters_callback( $params )
    {
        ob_start();
        ?>
        <div style="width:97%;min-width:97%;margin: 15px auto 0;">
          <a href="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'agent_job_add' ) )?>" class="btn btn-small btn-success" style="color:white;"><i class="fa fa-plus"></i> <?php echo $this->_pt( 'Add Agent Job' )?></a>
        </div>
        <div class="clearfix"></div>
        <?php

        $buf = ob_get_clean();

        return $buf;
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
        function phs_agent_jobs_list_activate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to activate this agent job?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'do_activate',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_agent_jobs_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to inactivate this agent job?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'do_inactivate',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_agent_jobs_list_delete( id, from_plugin )
        {
            var plugin_confirm = true;
            if( from_plugin )
                plugin_confirm = confirm( "<?php echo self::_e( 'NOTE: This agent job is part of a plugin. If you delete it plugin might not function normally.' )?>" + "\n"
                                        + "<?php echo self::_e( 'Are you sure you want to continue?' )?>" );

            if( plugin_confirm
             && confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this agent job?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'do_delete',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }

        function phs_agent_jobs_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_agent_jobs_list_bulk_activate()
        {
            var total_checked = phs_agent_jobs_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select agent jobs you want to activate first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to activate %s agent jobs?', '"' ), '" + total_checked + "' )?>" ) )

            {
                var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
                if( form_obj )
                    form_obj.submit();
            }
        }

        function phs_agent_jobs_list_bulk_inactivate()
        {
            var total_checked = phs_agent_jobs_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select agent jobs you want to inactivate first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to inactivate %s agent jobs?', '"' ), '" + total_checked + "' )?>" ) )

            {
                var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
                if( form_obj )
                    form_obj.submit();
            }
        }

        function phs_agent_jobs_list_bulk_delete()
        {
            var total_checked = phs_agent_jobs_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select agent jobs you want to delete first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to DELETE %s agent jobs?', '"' ), '" + total_checked + "' )?>" + "\n" +
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
