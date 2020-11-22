<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;
use \phs\libraries\PHS_Roles;

/** @property \phs\system\core\models\PHS_Model_Api_keys $_paginator_model */
class PHS_Action_Api_keys_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    public function load_depencies()
    {
        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'api_keys' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load API keys model.' ) );
            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'API Keys List' ) );

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

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_LIST_API_KEYS ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to list API keys.' ) );
            return false;
        }

        $apikeys_model = $this->_paginator_model;

        $list_arr = array();
        $list_arr['fields']['status'] = array( 'check' => '!=' , 'value' => $apikeys_model::STATUS_DELETED );
        $list_arr['flags'] = array( 'include_account_details' );

        $flow_params = array(
            'term_singular' => $this->_pt( 'API key' ),
            'term_plural' => $this->_pt( 'API keys' ),
            'listing_title' => $this->_pt( 'API keys' ),
            'initial_list_arr' => $list_arr,
            'after_table_callback' => array( $this, 'after_table_callback' ),
            'after_filters_callback' => array( $this, 'after_filters_callback' ),
        );

        if( PHS_Params::_g( 'unknown_api_key', PHS_Params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Invalid API key or API key was not found in database.' ) );
        if( PHS_Params::_g( 'api_key_added', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'API key details saved in database.' ) );

        if( !($statuses_arr = $this->_paginator_model->get_statuses_as_key_val()) )
            $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            $statuses_arr = self::merge_array_assoc( array( 0 => $this->_pt( ' - Choose - ' ) ), $statuses_arr );

        if( isset( $statuses_arr[$apikeys_model::STATUS_DELETED] ) )
            unset( $statuses_arr[$apikeys_model::STATUS_DELETED] );

        if( !PHS_Roles::user_has_role_units( PHS::current_user(), PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
            $bulk_actions = false;

        else
        {
            $bulk_actions = array(
                array(
                    'display_name' => $this->_pt( 'Inactivate' ),
                    'action' => 'bulk_inactivate',
                    'js_callback' => 'phs_apikeys_list_bulk_inactivate',
                    'checkbox_column' => 'id',
                ),
                array(
                    'display_name' => $this->_pt( 'Activate' ),
                    'action' => 'bulk_activate',
                    'js_callback' => 'phs_apikeys_list_bulk_activate',
                    'checkbox_column' => 'id',
                ),
                array(
                    'display_name' => $this->_pt( 'Delete' ),
                    'action' => 'bulk_delete',
                    'js_callback' => 'phs_apikeys_list_bulk_delete',
                    'checkbox_column' => 'id',
                ),
            );
        }

        $filters_arr = array(
            array(
                'display_name' => $this->_pt( 'Title' ),
                'display_hint' => $this->_pt( 'All records containing this value' ),
                'var_name' => 'ftitle',
                'record_field' => 'title',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'values_arr' => $statuses_arr,
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => '#',
                'record_field' => 'id',
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_style' => 'min-width:50px;max-width:80px;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Title' ),
                'record_field' => 'title',
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_style' => 'text-align:left;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'API Key' ),
                'record_field' => 'api_key',
                'display_callback' => array( $this, 'display_apikey' ),
            ),
            array(
                'column_title' => $this->_pt( 'Account' ),
                'record_field' => 'uid',
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
                'display_callback' => array( $this, 'display_apikey_account' ),
            ),
            array(
                'column_title' => $this->_pt( 'Simulate Web' ),
                'record_field' => 'allow_sw',
                'display_key_value' => array( 0 => $this->_pt( 'No' ), 1 => $this->_pt( 'Yes' ) ),
                'invalid_value' => $this->_pt( '??' ),
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $statuses_arr,
                'invalid_value' => $this->_pt( 'Undefined' ),
                'extra_style' => 'text-align:center;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'Created' ),
                'default_sort' => 1,
                'record_field' => 'cdate',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Invalid' ),
                'extra_style' => 'text-align:center;width:130px;',
                'extra_records_style' => 'text-align:right;',
            ),
            array(
                'column_title' => $this->_pt( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'extra_style' => 'text-align:center;width:120px;',
                'extra_records_style' => 'text-align:right;',
                'sortable' => false,
            ),
        );

        if( PHS_Roles::user_has_role_units( PHS::current_user(), PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
        {
            $columns_arr[0]['checkbox_record_index_key'] = array(
                'key' => 'id',
                'type' => PHS_Params::T_INT,
            );
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'api_keys_list' ) );
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
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required API keys activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating selected API keys failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed activating all selected API keys. API keys which failed activation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage API keys.' ) );
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
                foreach( $scope_arr[$scope_key] as $role_id )
                {
                    if( !$this->_paginator_model->act_activate( $role_id ) )
                    {
                        $remaining_ids_arr[] = $role_id;
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
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required API keys inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating selected API keys failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed inactivating all selected API keys. API keys which failed inactivation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage API keys.' ) );
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
                foreach( $scope_arr[$scope_key] as $role_id )
                {
                    if( !$this->_paginator_model->act_inactivate( $role_id ) )
                    {
                        $remaining_ids_arr[] = $role_id;
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
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required API keys deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting selected API keys failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed deleting all selected API keys. API keys which failed deletion are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage API keys.' ) );
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
                foreach( $scope_arr[$scope_key] as $role_id )
                {
                    if( !$this->_paginator_model->act_delete( $role_id ) )
                    {
                        $remaining_ids_arr[] = $role_id;
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

            case 'activate_role':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'API key activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating API key failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage API keys.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($apikey_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot activate API key. API key not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_activate( $apikey_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'inactivate_role':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'API key inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating API key failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage API keys.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($apikey_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot inactivate API key. API key not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_inactivate( $apikey_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
           break;

            case 'delete_role':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'API key deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting API key failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage API keys.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($apikey_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete API key. API key not found.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_delete( $apikey_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;
        }

        return $action_result_params;
    }

    public function display_apikey( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $paginator_obj = $this->_paginator;

        if( !empty( $params['request_render_type'] ) )
        {
            switch( $params['request_render_type'] )
            {
                case $paginator_obj::CELL_RENDER_JSON:
                case $paginator_obj::CELL_RENDER_TEXT:
                    return $params['record']['api_key'].' / '.$params['record']['api_secret'];
                break;
            }
        }

        return '<strong>'.$this->_pt( 'API Key' ).'</strong>: '.$params['record']['api_key'].'<br/>'.
               '<div style="float:left;"><strong>'.$this->_pt( 'API Secret' ).'</strong>: </div>'.
               '<div id="api_secret_container_'.$params['record']['id'].'" style="margin-left:5px;float:left;display:none;cursor:pointer;" onclick="$(\'#api_secret_container_'.$params['record']['id'].'\').toggle();$(\'#api_secret_container_show_'.$params['record']['id'].'\').toggle();">'.$params['record']['api_secret'].'</div>'.
               '<div id="api_secret_container_show_'.$params['record']['id'].'" style="margin-left:5px;float:left;display:block;cursor:pointer;" onclick="$(\'#api_secret_container_'.$params['record']['id'].'\').toggle();$(\'#api_secret_container_show_'.$params['record']['id'].'\').toggle();"> - '.$this->_pt( 'Show' ).' -</div>';
    }

    public function display_apikey_account( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        if( empty( $params['record']['uid'] ) )
            return '-';

        $paginator_obj = $this->_paginator;

        if( !empty( $params['request_render_type'] ) )
        {
            switch( $params['request_render_type'] )
            {
                case $paginator_obj::CELL_RENDER_JSON:
                case $paginator_obj::CELL_RENDER_TEXT:

                    return $params['record']['uid'].' / '.$params['record']['account_nick'].' / '.$params['record']['account_email'];
                break;
            }
        }

        return $params['record']['account_nick'].' (#'.$params['record']['uid'].')<br/>'.
               $params['record']['account_email'];
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( !PHS_Roles::user_has_role_units( PHS::current_user(), PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
            return '-';

        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($apikey_arr = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        $is_inactive = $this->_paginator_model->is_inactive( $apikey_arr );
        $is_active = $this->_paginator_model->is_active( $apikey_arr );

        ob_start();
        if( $is_inactive or $is_active )
        {
            ?>
            <a href="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'api_key_edit' ), array( 'aid' => $apikey_arr['id'], 'back_page' => $this->_paginator->get_full_url() ) )?>"><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt( 'Edit API key' )?>"></i></a>
            <?php
        }
        if( $is_inactive )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_apikeys_list_activate_role( '<?php echo $apikey_arr['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt( 'Activate API key' )?>"></i></a>
            <?php
        }
        if( $is_active )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_apikeys_list_inactivate_role( '<?php echo $apikey_arr['id']?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt( 'Inactivate API key' )?>"></i></a>
            <?php
        }

        if( !$this->_paginator_model->is_deleted( $apikey_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_apikeys_list_delete_role( '<?php echo $apikey_arr['id']?>' )"><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete API key' )?>"></i></a>
            <?php
        }

        return ob_get_clean();
    }

    public function after_filters_callback( $params )
    {
        ob_start();
        ?>
        <div style="width:97%;min-width:97%;margin: 15px auto 0;">
          <a href="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'api_key_add' ) )?>" class="btn btn-small btn-success" style="color:white;"><i class="fa fa-plus"></i> <?php echo $this->_pt( 'Add API Key' )?></a>
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
        function phs_apikeys_list_activate_role( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to activate this API key?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'activate_role',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_apikeys_list_inactivate_role( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to inactivate this API key?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'inactivate_role',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_apikeys_list_delete_role( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this API key?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'delete_role',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }

        function phs_apikeys_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_apikeys_list_bulk_activate()
        {
            var total_checked = phs_apikeys_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select API keys you want to activate first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to activate %s API keys?', '"' ), '" + total_checked + "' )?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_apikeys_list_bulk_inactivate()
        {
            var total_checked = phs_apikeys_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select API keys you want to inactivate first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to inactivate %s API keys?', '"' ), '" + total_checked + "' )?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_apikeys_list_bulk_delete()
        {
            var total_checked = phs_apikeys_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select API keys you want to delete first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to DELETE %s API keys?', '"' ), '" + total_checked + "' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
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
