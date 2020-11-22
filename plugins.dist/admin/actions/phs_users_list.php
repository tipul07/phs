<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Paginator;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;
use \phs\libraries\PHS_Roles;

/** @property \phs\plugins\accounts\models\PHS_Model_Accounts $_paginator_model */
class PHS_Action_Users_list extends PHS_Action_Generic_list
{
    public function load_depencies()
    {
        if( !($this->_paginator_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'List Users' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You should login first...' ) );
            return false;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_LIST_ACCOUNTS ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to create accounts.' ) );
            return false;
        }

        if( PHS_Params::_g( 'changes_saved', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Changes saved to database.' ) );
        if( PHS_Params::_g( 'account_created', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'User account created.' ) );
        if( PHS_Params::_g( 'unknown_account', PHS_Params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Account not found in database.' ) );

        $accounts_model = $this->_paginator_model;

        $list_arr = array();
        $list_arr['fields']['status'] = array( 'check' => '!=' , 'value' => $accounts_model::STATUS_DELETED );
        $list_arr['flags'] = array( 'include_account_details' );

        $flow_params = array(
            'term_singular' => $this->_pt( 'user' ),
            'term_plural' => $this->_pt( 'users' ),
            'initial_list_arr' => $list_arr,
            'after_table_callback' => array( $this, 'after_table_callback' ),
        );

        if( !($users_levels = $this->_paginator_model->get_levels_as_key_val()) )
            $users_levels = array();
        if( !($users_statuses = $this->_paginator_model->get_statuses_as_key_val()) )
            $users_statuses = array();

        if( !empty( $users_levels ) )
            $users_levels = self::merge_array_assoc( array( 0 => $this->_pt( ' - Choose - ' ) ), $users_levels );
        if( !empty( $users_statuses ) )
            $users_statuses = self::merge_array_assoc( array( 0 => $this->_pt( ' - Choose - ' ) ), $users_statuses );

        if( isset( $users_statuses[$accounts_model::STATUS_DELETED] ) )
            unset( $users_statuses[$accounts_model::STATUS_DELETED] );

        $bulk_actions = array(
            array(
                'display_name' => $this->_pt( 'Inactivate' ),
                'action' => 'bulk_inactivate',
                'js_callback' => 'phs_users_list_bulk_inactivate',
                'checkbox_column' => 'id',
            ),
            array(
                'display_name' => $this->_pt( 'Activate' ),
                'action' => 'bulk_activate',
                'js_callback' => 'phs_users_list_bulk_activate',
                'checkbox_column' => 'id',
            ),
            array(
                'display_name' => $this->_pt( 'Delete' ),
                'action' => 'bulk_delete',
                'js_callback' => 'phs_users_list_bulk_delete',
                'checkbox_column' => 'id',
            ),
        );

        $filters_arr = array(
            array(
                'display_name' => $this->_pt( 'IDs' ),
                'display_hint' => $this->_pt( 'Comma separated ids' ),
                'display_placeholder' => $this->_pt( 'eg. 1,2,3' ),
                'var_name' => 'fids',
                'record_field' => 'id',
                'record_check' => array( 'check' => 'IN', 'value' => '(%s)' ),
                'type' => PHS_Params::T_ARRAY,
                'extra_type' => array( 'type' => PHS_Params::T_INT ),
                'default' => array(),
                'extra_records_style' => 'vertical-align:middle;',
            ),
            array(
                'display_name' => $this->_pt( 'Nickname' ),
                'display_hint' => $this->_pt( 'All records containing this value' ),
                'var_name' => 'fnick',
                'record_field' => 'nick',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
                'extra_records_style' => 'vertical-align:middle;',
            ),
            array(
                'display_name' => $this->_pt( 'Email' ),
                'display_hint' => $this->_pt( 'All records containing this value' ),
                'var_name' => 'femail',
                'record_field' => 'email',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
                'extra_records_style' => 'vertical-align:middle;',
            ),
            array(
                'display_name' => $this->_pt( 'Level' ),
                'var_name' => 'flevel',
                'record_field' => 'level',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'values_arr' => $users_levels,
                'extra_records_style' => 'vertical-align:middle;',
            ),
            array(
                'display_name' => $this->_pt( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'values_arr' => $users_statuses,
                'extra_records_style' => 'vertical-align:middle;',
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => '#',
                'record_field' => 'id',
                'checkbox_record_index_key' => array(
                    'key' => 'id',
                    'type' => PHS_Params::T_INT,
                ),
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_style' => 'min-width:50px;max-width:80px;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Nickname' ),
                'record_field' => 'nick',
                'display_callback' => array( $this, 'display_nickname' ),
            ),
            array(
                'column_title' => $this->_pt( 'Email' ),
                'record_field' => 'email',
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $users_statuses,
                'invalid_value' => $this->_pt( 'Undefined' ),
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Level' ),
                'record_field' => 'level',
                'display_key_value' => $users_levels,
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Last Login' ),
                'record_field' => 'lastlog',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Never' ),
                'extra_style' => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ),
            array(
                'column_title' => $this->_pt( 'Created' ),
                'default_sort' => 1,
                'record_field' => 'cdate',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Invalid' ),
                'extra_style' => 'width:130px;',
                'extra_records_style' => 'text-align:right;',
            ),
            array(
                'column_title' => $this->_pt( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'extra_style' => 'width:150px;',
                'extra_records_style' => 'text-align:right;',
                'sortable' => false,
            ),
        );

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'users_list' ) );
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
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required accounts activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating selected accounts failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed activating all selected accounts. Accounts which failed activation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
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
                foreach( $scope_arr[$scope_key] as $account_id )
                {
                    if( !$this->_paginator_model->activate_account( $account_id ) )
                    {
                        $remaining_ids_arr[] = $account_id;
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
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required accounts inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating selected accounts failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed inactivating all selected accounts. Accounts which failed inactivation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
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
                foreach( $scope_arr[$scope_key] as $account_id )
                {
                    if( !$this->_paginator_model->inactivate_account( $account_id ) )
                    {
                        $remaining_ids_arr[] = $account_id;
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
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required accounts deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting selected accounts failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed deleting all selected accounts. Accounts which failed deletion are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
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
                foreach( $scope_arr[$scope_key] as $account_id )
                {
                    if( !$this->_paginator_model->delete_account( $account_id ) )
                    {
                        $remaining_ids_arr[] = $account_id;
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

            case 'sublogin_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Logged in with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Logging in as this account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_LOGIN_SUBACCOUNT ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to login as this accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot login as this account. Account not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->is_active( $account_arr ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Account not active. Activate account first.' ) );
                    return false;
                }

                if( !$this->_paginator_model->login( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                {
                    // If we logged in with success redirect at main page as we don't know if new logged in user has rights to view this page...
                    header( 'Location: '.PHS::url() );
                    exit;
                }
            break;

            case 'resend_registration_email':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Re-sent registration email with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Re-sending registration email failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot re-send registration email. Account not found.' ) );
                    return false;
                }

                if( !($send_result = $this->_paginator_model->send_after_registration_email( $account_arr, array( 'send_confirmation_email' => true ) ))
                 or !is_array( $send_result )
                 or !empty( $send_result['has_error'] ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'activate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Account activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot activate account. Account not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->activate_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'inactivate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Account inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot inactivate account. Account not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->inactivate_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
           break;

            case 'delete_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Account deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete account. Account not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->delete_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;
        }

        return $action_result_params;
    }

    public function display_nickname( $params )
    {
        if( empty( $params )
            or !is_array( $params )
            or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $return_str = '<strong>'.$params['preset_content'].'</strong>';

        $name_str = '';
        if( !empty( $params['record']['users_details_title'] )
         or !empty( $params['record']['users_details_fname'] )
         or !empty( $params['record']['users_details_lname'] ) )
        {
            if( !empty( $params['record']['users_details_title'] ) )
                $name_str .= $params['record']['users_details_title'].' ';
            if( !empty( $params['record']['users_details_fname'] ) )
                $name_str .= $params['record']['users_details_fname'].' ';
            if( !empty( $params['record']['users_details_lname'] ) )
                $name_str .= $params['record']['users_details_lname'].' ';

            if( $name_str != '' )
                $name_str = '<br/>'.$name_str;
        }

        return $return_str.$name_str;
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        $current_user = PHS::user_logged_in();

        if( empty( $current_user )
         or empty( $params ) or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($account_arr = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
            return '-';

        $is_inactive = $this->_paginator_model->is_inactive( $account_arr );
        $is_active = $this->_paginator_model->is_active( $account_arr );

        ob_start();

        if( PHS_Roles::user_has_role_units( PHS::current_user(), PHS_Roles::ROLEU_LOGIN_SUBACCOUNT )
        and $this->_paginator_model->is_active( $account_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_sublogin_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-sign-in action-icons" title="<?php echo $this->_pt( 'Change login to this account' )?>"></i></a>
            <?php
        }

        if( ($is_inactive or $is_active)
        and $this->_paginator_model->can_manage_account( $current_user, $account_arr ) )
        {
            ?>
            <a href="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'user_edit' ), array( 'uid' => $account_arr['id'], 'back_page' => $this->_paginator->get_full_url() ) )?>"><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt( 'Edit account' )?>"></i></a>
            <?php
        }

        if( $this->_paginator_model->needs_after_registration_email( $account_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_resend_registration_email( '<?php echo $account_arr['id']?>' )"><i class="fa fa-share-square-o action-icons" title="<?php echo $this->_pt( 'Re-send registration email' )?>"></i></a>
            <?php
        }

        if( $is_inactive )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_activate_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt( 'Activate account' )?>"></i></a>
            <?php
        }
        if( $is_active )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_inactivate_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt( 'Inactivate account' )?>"></i></a>
            <?php
        }

        if( !$this->_paginator_model->is_deleted( $account_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_delete_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete account' )?>"></i></a>
            <?php
        }

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
        function phs_users_list_sublogin_account( id )
        {
            if( confirm( "<?php echo $this->_pte( 'Are you sure you want to change login as this account?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'sublogin_account',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_users_list_resend_registration_email( id )
        {
            if( confirm( "<?php echo $this->_pte( 'Are you sure you want to re-send registration email for this account?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'resend_registration_email',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_users_list_activate_account( id )
        {
            if( confirm( "<?php echo $this->_pte( 'Are you sure you want to activate this account?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'activate_account',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_users_list_inactivate_account( id )
        {
            if( confirm( "<?php echo $this->_pte( 'Are you sure you want to inactivate this account?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'inactivate_account',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_users_list_delete_account( id )
        {
            if( confirm( "<?php echo $this->_pte( 'Are you sure you want to DELETE this account?', '"' )?>" + "\n" +
                         "<?php echo $this->_pte( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'delete_account',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }

        function phs_users_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_users_list_bulk_activate()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte( 'Please select accounts you want to activate first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( $this->_pte( 'Are you sure you want to activate %s accounts?', '"' ), '" + total_checked + "' )?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_users_list_bulk_inactivate()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte( 'Please select accounts you want to inactivate first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( $this->_pte( 'Are you sure you want to inactivate %s accounts?', '"' ), '" + total_checked + "' )?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_users_list_bulk_delete()
        {
            var total_checked = phs_users_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo $this->_pte( 'Please select accounts you want to delete first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( $this->_pte( 'Are you sure you want to DELETE %s accounts?', '"' ), '" + total_checked + "' )?>" + "\n" +
                         "<?php echo $this->_pte( 'NOTE: You cannot undo this action!', '"' )?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
            if( form_obj )
                form_obj.submit();
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
