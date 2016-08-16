<?php

namespace phs\plugins\messages\actions;

use \phs\PHS;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;
use \phs\libraries\PHS_Roles;

/** @property \phs\plugins\messages\models\PHS_Model_Messages $_paginator_model */
class PHS_Action_Inbox extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    /** @var \phs\plugins\messages\PHS_Plugin_Messages $_messages_plugin */
    private $_messages_plugin;

    public function load_depencies()
    {
        if( !($this->_messages_plugin = $this->get_plugin_instance()) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t load messages plugin.' ) );
            return false;
        }

        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'messages', 'messages' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load messages model.' ) );
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

            $args = array(
                    'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        $messages_plugin = $this->_messages_plugin;

        if( !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_READ_MESSAGE ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to read messages.' ) );
            return self::default_action_result();
        }
        
        return false;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Company Addresses List' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You should login first...' ) );
            return false;
        }

        $messages_plugin = $this->_messages_plugin;
        $messages_model = $this->_paginator_model;
        $accounts_model = $this->_accounts_model;

        if( !($m_flow_params = $messages_model->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         or !($mu_flow_params = $messages_model->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         or !($mb_flow_params = $messages_model->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         or !($users_flow_params = $accounts_model->fetch_default_flow_params( array( 'table_name' => 'users' ) ))
         or !($users_table_name = $accounts_model->get_flow_table_name( $users_flow_params ))
         or !($mu_table_name = $messages_model->get_flow_table_name( $mu_flow_params ))
         or !($m_table_name = $messages_model->get_flow_table_name( $m_flow_params )) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t load required functionality.' ) );
            return false;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_READ_MESSAGE ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to read messages.' ) );
            return false;
        }

        $list_fields_arr = array();
        $list_fields_arr['`'.$mu_table_name.'`.user_id'] = $current_user['id'];
        $list_fields_arr['`'.$mu_table_name.'`.is_author'] = 0;

        $list_arr = $mu_flow_params;
        $list_arr['fields'] = $list_fields_arr;
        $list_arr['join_sql'] = ' LEFT JOIN `'.$m_table_name.'` ON `'.$mu_table_name.'`.message_id = `'.$m_table_name.'`.id ';
        $list_arr['db_fields'] = 'MAX(`'.$m_table_name.'`.id) AS m_id, `'.$m_table_name.'`.*, MAX(`'.$m_table_name.'`.cdate) AS m_cdate, '.
                                 ' MAX(`'.$mu_table_name.'`.id) AS mu_id, `'.$mu_table_name.'`.* ';
        $list_arr['order_by'] = '`'.$m_table_name.'`.sticky ASC, `'.$mu_table_name.'`.cdate DESC';
        $list_arr['group_by'] = '`'.$mu_table_name.'`.thread_id';

        $flow_params = array(
            'term_singular' => $this->_pt( 'message' ),
            'term_plural' => $this->_pt( 'messages' ),
            'initial_list_arr' => $list_arr,
            'after_table_callback' => array( $this, 'after_table_callback' ),
        );

        if( PHS_params::_g( 'unknown_message', PHS_params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Message details not found in database.' ) );
        if( PHS_params::_g( 'address_added', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Address details saved in database.' ) );

        if( !PHS_Roles::user_has_role_units( PHS::current_user(), $messages_plugin::ROLEU_WRITE_MESSAGE ) )
            $bulk_actions = false;

        else
        {
            $bulk_actions = array(
                array(
                    'display_name' => $this->_pt( 'Delete' ),
                    'action' => 'bulk_delete',
                    'js_callback' => 'phs_messages_list_bulk_delete',
                    'checkbox_column' => 'id',
                ),
            );
        }

        $filters_arr = array(
            array(
                'display_name' => $this->_pt( 'From' ),
                'display_hint' => $this->_pt( 'Destination nickname contains this value' ),
                'var_name' => 'ffrom',
                'record_field' => 'from_handle',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => $this->_pt( 'From' ),
                'record_field' => 'from_handle',
                'invalid_value' => $this->_pt( 'System' ),
            ),
            array(
                'column_title' => $this->_pt( 'Subject' ),
                'record_field' => 'subject',
                'invalid_value' => $this->_pt( 'N/A' ),
            ),
            array(
                'column_title' => $this->_pt( 'Last reply' ),
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
            )
        );

        if( PHS_Roles::user_has_role_units( PHS::current_user(), $messages_plugin::ROLEU_WRITE_MESSAGE ) )
        {
            $columns_arr[0]['checkbox_record_index_key'] = array(
                'key' => 'mu_id',
                'type' => PHS_params::T_INT,
            );
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'messages', 'a' => 'inbox' ) );
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

        $messages_plugin = $this->_messages_plugin;

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

            case 'bulk_delete':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required messages deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting selected messages failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed deleting all selected messages. Messages which failed deletion are still selected. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_WRITE_MESSAGE ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage messages.' ) );
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
                foreach( $scope_arr[$scope_key] as $message_id )
                {
                    if( !$this->_paginator_model->act_delete( $message_id ) )
                    {
                        $remaining_ids_arr[] = $message_id;
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

            case 'do_delete':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Message deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting message failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_WRITE_MESSAGE ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage messages.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($message_arr = $this->_paginator_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete address. Address not found in database.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_delete( $message_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;
        }

        return $action_result_params;
    }

    public function display_address_title( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        return '<strong>'.$params['preset_content'].'</strong>';
    }

    public function display_address_address( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $address_str = '';
        if( !empty( $params['preset_content'] ) )
            $address_str .= (!empty( $address_str )?', ':'').$params['preset_content'];
        if( !empty( $params['record']['postcode'] ) )
            $address_str .= (!empty( $address_str )?', ':'').$params['record']['postcode'];

        return $address_str;
    }

    public function display_address_city_state( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $address_str = '';
        if( !empty( $params['preset_content'] ) )
            $address_str .= (!empty( $address_str )?', ':'').$params['preset_content'];
        if( !empty( $params['record']['state'] ) )
            $address_str .= (!empty( $address_str )?', ':'').$params['record']['state'];

        return $address_str;
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        $messages_plugin = $this->_messages_plugin;

        if( !($current_user = PHS::current_user()) )
            $current_user = false;

        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $list_message = $params['record'];

        ob_start();
        ?>
        <a href="<?php echo PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ), array( 'muid' => $list_message['mu_id'], 'back_page' => $this->_paginator->get_full_url() ) )?>"><i class="fa fa-pencil-square-o action-icons" title="<?php echo $this->_pt( 'View thread' )?>"></i></a>
        <?php

        if( $this->_paginator_model->can_reply( $list_message, array( 'account_data' => $current_user ) ) )
        {
            ?>
            <a href="<?php echo PHS::url( array( 'p' => 'messages', 'a' => 'compose' ), array( 'reply_to_muid' => $list_message['mu_id'], 'back_page' => $this->_paginator->get_full_url() ) )?>"><i class="fa fa-reply action-icons" title="<?php echo $this->_pt( 'Reply' )?>"></i></a>
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
        function phs_addresses_list_activate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to activate this address?', '"' )?>" ) )
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
        function phs_addresses_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to inactivate this address?', '"' )?>" ) )
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
        function phs_addresses_list_delete( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this address?', '"' )?>" + "\n" +
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

        function phs_addresses_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_messages_list_bulk_delete()
        {
            var total_checked = phs_addresses_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select addresses you want to delete first.', '"' )?>" );
                return false;
            }

            if( confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to DELETE %s addresses?', '"' ), '" + total_checked + "' )?>" + "\n" +
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
