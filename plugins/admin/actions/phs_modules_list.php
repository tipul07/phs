<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Paginator;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;

/** @property \phs\system\core\models\PHS_Model_Plugins $_paginator_model */
class PHS_Action_Modules_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    public function load_depencies()
    {
        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, self::_t( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, self::_t( 'Couldn\'t load plugins model.' ) );
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
            PHS_Notifications::add_warning_notice( self::_t( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                    'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        return false;
    }

    /**
     * @return array|bool
     */
    public function load_paginator_params()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            $this->set_error( self::ERR_ACTION, self::_t( 'You should login first...' ) );
            return false;
        }

        if( !$this->_accounts_model->can_list_modules( $current_user ) )
        {
            $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to list modules.' ) );
            return false;
        }

        $flow_params = array(
            'term_singular' => self::_t( 'module' ),
            'term_plural' => self::_t( 'modules' ),
            'after_table_callback' => array( $this, 'after_table_callback' ),
        );

        $bulk_actions = array(
            array(
                'display_name' => self::_t( 'Inactivate' ),
                'action' => 'bulk_inactivate',
                'js_callback' => 'phs_modules_list_bulk_inactivate',
                'checkbox_column' => 'id',
            ),
            array(
                'display_name' => self::_t( 'Activate' ),
                'action' => 'bulk_activate',
                'js_callback' => 'phs_modules_list_bulk_activate',
                'checkbox_column' => 'id',
            ),
            array(
                'display_name' => self::_t( 'Delete' ),
                'action' => 'bulk_delete',
                'js_callback' => 'phs_modules_list_bulk_delete',
                'checkbox_column' => 'id',
            ),
        );

        if( !($modules_statuses = $this->_paginator_model->get_statuses_as_key_val()) )
            $modules_statuses = array();
        if( !empty( $modules_statuses ) )
            $modules_statuses = self::merge_array_assoc( array( 0 => self::_t( ' - Choose - ' ) ), $modules_statuses );

        $filters_arr = array(
            array(
                'display_name' => self::_t( 'Plugin' ),
                'display_hint' => self::_t( 'All records containing this value' ),
                'var_name' => 'fplugin',
                'record_field' => 'plugin',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => self::_t( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $modules_statuses,
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => self::_t( '#' ),
                'record_field' => 'id',
                'checkbox_record_index_key' => array(
                    'key' => 'id',
                    'type' => PHS_params::T_INT,
                ),
                'invalid_value' => self::_t( 'N/A' ),
                'extra_style' => 'min-width:50px;max-width:80px;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => self::_t( 'ID' ),
                'record_field' => 'instance_id',
            ),
            array(
                'column_title' => self::_t( 'Plugin' ),
                'record_field' => 'plugin',
            ),
            array(
                'column_title' => self::_t( 'Type' ),
                'record_field' => 'type',
            ),
            array(
                'column_title' => self::_t( 'Version' ),
                'record_field' => 'version',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => self::_t( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $modules_statuses,
                'invalid_value' => self::_t( 'Undefined' ),
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => self::_t( 'Status Date' ),
                'record_field' => 'status_date',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd M y H:i',
                'invalid_value' => self::_t( 'N/A' ),
                'extra_style' => 'width:100px;',
                'extra_records_style' => 'text-align:right;',
            ),
            array(
                'column_title' => self::_t( 'Installed' ),
                'default_sort' => 1,
                'record_field' => 'cdate',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd M y H:i',
                'invalid_value' => self::_t( 'Invalid' ),
                'extra_style' => 'width:100px;',
                'extra_records_style' => 'text-align:right;',
            ),
            array(
                'column_title' => self::_t( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'extra_style' => 'width:100px;',
                'extra_records_style' => 'text-align:right;',
            ),
        );

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'modules_list' ) );
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
                PHS_Notifications::add_error_notice( self::_t( 'Unknown action.' ) );
                return true;
            break;

            case 'bulk_activate':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Required accounts activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Activating selected accounts failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( self::_t( 'Failed activating all selected accounts. Accounts which failed activation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
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
                    if( !$this->_accounts_model->activate_account( $account_id ) )
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
                        PHS_Notifications::add_success_notice( self::_t( 'Required accounts inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Inactivating selected accounts failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( self::_t( 'Failed inactivating all selected accounts. Accounts which failed inactivation are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
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
                    if( !$this->_accounts_model->inactivate_account( $account_id ) )
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
                        PHS_Notifications::add_success_notice( self::_t( 'Required accounts deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Deleting selected accounts failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( self::_t( 'Failed deleting all selected accounts. Accounts which failed deletion are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
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
                    if( !$this->_accounts_model->delete_account( $account_id ) )
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

            case 'activate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Activating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );
                 
                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'Cannot activate account. Account not found.' ) );
                    return false;
                }

                if( !$this->_accounts_model->activate_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'inactivate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Inactivating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'Cannot inactivate account. Account not found.' ) );
                    return false;
                }

                if( !$this->_accounts_model->inactivate_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
           break;

            case 'delete_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Deleting account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'Cannot delete account. Account not found.' ) );
                    return false;
                }

                if( !$this->_accounts_model->delete_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;
        }

        return $action_result_params;
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($module_arr = $this->_paginator_model->data_to_array( $params['record'] )) )
            return false;

        /**
        ob_start();
        if( $this->_paginator_model->is_inactive( $module_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_activate( '<?php echo $module_arr['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo self::_t( 'Activate module' )?>"></i></a>
            <?php
        }
        if( $this->_paginator_model->is_active( $module_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_inactivate( '<?php echo $module_arr['id']?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo self::_t( 'Inactivate module' )?>"></i></a>
            <?php
        }

        if( !$this->_paginator_model->is_deleted( $module_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_delete( '<?php echo $module_arr['id']?>' )"><i class="fa fa-times-circle-o action-icons" title="<?php echo self::_t( 'Delete module' )?>"></i></a>
            <?php
        }

        return ob_get_clean();
         **/
        return '';
    }

    public function after_table_callback( $params )
    {
        static $js_functionality = false;

        if( !empty( $js_functionality ) )
            return '';

        $js_functionality = true;
        
        if( !($flow_params_arr = $this->_paginator->flow_params()) )
            $flow_params_arr = array();

        ob_start();
        ?>
        <script type="text/javascript">
        function phs_modules_list_activate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to activate this module?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'activate_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_modules_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to inactivate this module?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'inactivate_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_modules_list_delete( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this module?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'delete_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }

        function phs_modules_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_modules_list_bulk_activate()
        {
            var total_checked = phs_modules_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select modules you want to activate first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to activate %s modules?', '"' ), '" + total_checked + "' )?>" ) )

            {
                var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
                if( form_obj )
                    form_obj.submit();
            }
        }

        function phs_modules_list_bulk_inactivate()
        {
            var total_checked = phs_modules_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select modules you want to inactivate first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to inactivate %s modules?', '"' ), '" + total_checked + "' )?>" ) )

            {
                var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
                if( form_obj )
                    form_obj.submit();
            }
        }

        function phs_modules_list_bulk_delete()
        {
            var total_checked = phs_modules_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select modules you want to delete first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to DELETE %s modules?', '"' ), '" + total_checked + "' )?>" + "\n" +
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
