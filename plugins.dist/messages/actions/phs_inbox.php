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
    /** @var \phs\system\core\models\PHS_Model_Roles $_roles_model */
    private $_roles_model;

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

        if( !($this->_roles_model = PHS::load_model( 'roles' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load roles model.' ) );
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

            $action_result['request_login'] = true;

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
        PHS::page_settings( 'page_title', $this->_pt( 'Inbox' ) );

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
        // $list_fields_arr['`'.$mu_table_name.'`.is_author'] = 0;

        $list_arr = $mu_flow_params;
        $list_arr['fields'] = $list_fields_arr;
        $list_arr['join_sql'] = ' LEFT JOIN `'.$m_table_name.'` ON `'.$mu_table_name.'`.message_id = `'.$m_table_name.'`.id ';
        // !!!! m_id should be first field for $m_table_name records and mu_id should be first field for $mu_table_name table
        // !!!! these fields tells system when to create new arrays in results for each table !!!!
        $list_arr['db_fields'] = 'MAX(`'.$m_table_name.'`.id) AS m_id, `'.$m_table_name.'`.*, MAX(`'.$m_table_name.'`.cdate) AS m_cdate, '.
                                 ' MAX(`'.$mu_table_name.'`.id) AS mu_id, MAX(`'.$mu_table_name.'`.is_new) AS mu_is_new, `'.$mu_table_name.'`.*, COUNT( `'.$mu_table_name.'`.id ) AS m_thread_count ';
        $list_arr['order_by'] = '`'.$m_table_name.'`.sticky ASC, `'.$mu_table_name.'`.cdate DESC';
        $list_arr['count_field'] = '`'.$mu_table_name.'`.thread_id';

        $count_list_arr = $list_arr;

        $list_arr['group_by'] = '`'.$mu_table_name.'`.thread_id';

        $flow_params = array(
            'term_singular' => $this->_pt( 'message' ),
            'term_plural' => $this->_pt( 'messages' ),
            'initial_list_arr' => $list_arr,
            'initial_count_list_arr' => $count_list_arr,
            'after_table_callback' => array( $this, 'after_table_callback' ),
            'listing_title' => $this->_pt( 'Inbox' ),
        );

        if( PHS_params::_g( 'unknown_message', PHS_params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Message details not found in database.' ) );
        if( PHS_params::_g( 'unknown_thread', PHS_params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Message thread details not found in database.' ) );

        if( !PHS_Roles::user_has_role_units( PHS::current_user(), $messages_plugin::ROLEU_WRITE_MESSAGE ) )
            $bulk_actions = false;

        else
        {
            $bulk_actions = array(
                array(
                    'display_name' => $this->_pt( 'Delete' ),
                    'action' => 'bulk_delete',
                    'js_callback' => 'phs_messages_list_bulk_delete',
                    'checkbox_column' => 'mu_id',
                ),
                array(
                    'display_name' => $this->_pt( 'Mark as read' ),
                    'action' => 'bulk_mark_as_read',
                    'js_callback' => 'phs_messages_list_bulk_mark_as_read',
                    'checkbox_column' => 'mu_id',
                ),
            );
        }

        $filters_arr = array(
            array(
                'display_name' => $this->_pt( 'From' ),
                'display_hint' => $this->_pt( 'Author messaging handle contains this value' ),
                'var_name' => 'ffrom',
                'record_field' => '`'.$m_table_name.'`.from_handle',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'To' ),
                'display_hint' => $this->_pt( 'Destination messagin handle contains this value' ),
                'var_name' => 'fto',
                'record_field' => '`'.$m_table_name.'`.dest_str',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'Subject' ),
                'display_hint' => $this->_pt( 'Subject contains this value' ),
                'var_name' => 'fsubject',
                'record_field' => '`'.$m_table_name.'`.subject',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => ' ',
                'record_field' => 'm_id',
                'invalid_value' => $this->_pt( 'N/A' ),
                'display_callback' => array( $this, 'display_hide_id' ),
                'extra_style' => 'width:20px;',
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => $this->_pt( 'From' ),
                'record_field' => 'from_handle',
                'invalid_value' => $this->_pt( 'System' ),
                'display_callback' => array( $this, 'display_from' ),
                'extra_classes' => 'inbox_from_th',
                'extra_records_classes' => 'inbox_from',
            ),
            array(
                'column_title' => $this->_pt( 'To' ),
                'record_field' => 'from_handle',
                'invalid_value' => $this->_pt( 'System' ),
                'display_callback' => array( $this, 'display_to' ),
                'extra_classes' => 'inbox_to_th',
                'extra_records_classes' => 'inbox_to',
            ),
            array(
                'column_title' => $this->_pt( 'Subject' ),
                'record_field' => 'subject',
                'invalid_value' => $this->_pt( 'N/A' ),
                'display_callback' => array( $this, 'display_subject' ),
                'extra_classes' => 'inbox_subject_th',
                'extra_records_classes' => 'inbox_subject',
            ),
            array(
                'column_title' => $this->_pt( 'Last reply' ),
                'default_sort' => 1,
                'record_db_field' => 'm_cdate',
                'record_field' => 'MAX(`messages`.cdate)',
                'display_callback' => array( $this, 'display_last_reply' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Invalid' ),
                'extra_classes' => 'date_th',
                'extra_records_classes' => 'date',
            ),
            array(
                'column_title' => $this->_pt( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'sortable' => false,
                'extra_style' => 'width:100px;',
                'extra_classes' => 'actions_th',
                'extra_records_classes' => 'actions',
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
                 or !($scope_key = @sprintf( $ids_checkboxes_name, 'mu_id' ))
                 or !($scope_all_key = @sprintf( $ids_all_checkbox_name, 'mu_id' ))
                 or empty( $scope_arr[$scope_key] )
                 or !is_array( $scope_arr[$scope_key] ) )
                    return true;

                $remaining_ids_arr = array();
                foreach( $scope_arr[$scope_key] as $message_id )
                {
                    if( !$this->_paginator_model->act_delete_thread( $message_id ) )
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

            case 'bulk_mark_as_read':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Required messages marked as read with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Marking selected messages as read failed. Please try again.' ) );
                    elseif( $action['action_result'] == 'failed_some' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Failed marking as read all selected messages. Failed messages are still selected. Please try again.' ) );

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
                 or !($scope_key = @sprintf( $ids_checkboxes_name, 'mu_id' ))
                 or !($scope_all_key = @sprintf( $ids_all_checkbox_name, 'mu_id' ))
                 or empty( $scope_arr[$scope_key] )
                 or !is_array( $scope_arr[$scope_key] ) )
                    return true;

                $remaining_ids_arr = array();
                foreach( $scope_arr[$scope_key] as $message_id )
                {
                    if( !$this->_paginator_model->act_mark_as_read_thread( $message_id ) )
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
                 or !($mu_flow_params = $this->_paginator_model->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
                 or !($message_user_arr = $this->_paginator_model->get_details( $action['action_params'], $mu_flow_params )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete address. Address not found in database.' ) );
                    return false;
                }

                if( !$this->_paginator_model->act_delete_thread( $message_user_arr ) )
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

    public function display_subject( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $message_link = PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ), array( 'muid' => $params['record']['mu_id'] ) );

        $extra_str = '';
        if( !empty( $params['record']['mu_is_new'] ) )
        {
            $extra_str = '<i class="fa fa-asterisk" aria-hidden="true"></i> ';
        }

        return $extra_str.'<a href="'.$message_link.'">'.$params['record']['subject'].'</a> <span>['.$params['record']['m_thread_count'].']</span>';
    }

    public function display_from( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        if( ($current_user = PHS::current_user())
        and $current_user['id'] == $params['record']['from_uid'] )
            return $this->_pt( 'You (%s)', $params['record']['from_handle'] );

        return $params['record']['from_handle'];
    }

    public function display_to( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $current_user = PHS::current_user();
        $messages_model = $this->_paginator_model;
        $accounts_model = $this->_accounts_model;
        $roles_model = $this->_roles_model;

        switch( $params['record']['dest_type'] )
        {
            default:
                $destination_str = '['.$this->_pt( 'Unknown destination' ).']';
            break;

            case $messages_model::DEST_TYPE_USERS_IDS:
                $destination_str = 'IDs: '.$params['record']['dest_str'];
            break;

            case $messages_model::DEST_TYPE_USERS:
            case $messages_model::DEST_TYPE_HANDLERS:
                $destination_str = $params['record']['dest_str'];
            break;

            case $messages_model::DEST_TYPE_LEVEL:
                $user_levels = $accounts_model->get_levels_as_key_val();

                if( !empty( $user_levels[$params['record']['dest_id']] ) )
                    $destination_str = $user_levels[$params['record']['dest_id']];
                else
                    $destination_str = '['.$this->_pt( 'Unknown user level' ).']';
            break;

            case $messages_model::DEST_TYPE_ROLE:

                $roles_arr = $roles_model->get_all_roles();

                if( !empty( $roles_arr[$params['record']['dest_id']] ) )
                    $destination_str = $roles_arr[$params['record']['dest_id']]['name'];
                else
                    $destination_str = '['.$this->_pt( 'Unknown role' ).']';
            break;

            case $messages_model::DEST_TYPE_ROLE_UNIT:

                $roles_units_arr = $roles_model->get_all_role_units();

                if( !empty( $roles_units_arr[$params['record']['dest_id']] ) )
                    $destination_str = $roles_units_arr[$params['record']['dest_id']]['name'];
                else
                    $destination_str = '['.$this->_pt( 'Unknown role unit' ).']';
            break;
        }

        if( !empty( $current_user )
        and !empty( $params['record']['user_id'] )
        and $params['record']['user_id'] == $current_user['id']
        and empty( $params['record']['is_author'] ) )
        {
            $destination_str = $this->_pt( 'You (%s)', $destination_str );
        }

        return $destination_str;
    }

    public function display_last_reply( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        $params['column']['record_field'] = 'm_cdate';

        return $this->_paginator->pretty_date( $params );
    }

    public function display_actions( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        ob_start();
        ?>
        <a href="<?php echo PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ), array( 'muid' => $params['record']['mu_id'], 'back_page' => $this->_paginator->get_full_url() ) )?>"><i class="fa fa-envelope action-icons" title="<?php echo $this->_pt( 'View thread' )?>"></i></a>
        <a href="javascript:void(0)" onclick="phs_messages_list_delete( '<?php echo $params['record']['mu_id']?>' )"><i class="fa fa-times action-icons" title="<?php echo $this->_pt( 'Delete message thread' )?>"></i></a>
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
        function phs_messages_list_delete( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this message thread?', '"' )?>" + "\n" +
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

        function phs_messages_list_get_checked_ids_count()
        {
            var checkboxes_list = phs_paginator_get_checkboxes_checked( 'mu_id' );
            if( !checkboxes_list || !checkboxes_list.length )
                return 0;

            return checkboxes_list.length;
        }

        function phs_messages_list_bulk_delete()
        {
            var total_checked = phs_messages_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select message threads you want to delete first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to DELETE %s message threads?', '"' ), '" + total_checked + "' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
                return false;

            var form_obj = $("#<?php echo $this->_paginator->get_listing_form_name()?>");
            if( form_obj )
                form_obj.submit();
        }

        function phs_messages_list_bulk_mark_as_read()
        {
            var total_checked = phs_messages_list_get_checked_ids_count();

            if( !total_checked )
            {
                alert( "<?php echo self::_e( 'Please select message threads you want to mark as read first.', '"' )?>" );
                return false;
            }

            if( !confirm( "<?php echo sprintf( self::_e( 'Are you sure you want to mark as read %s message threads?', '"' ), '" + total_checked + "' )?>" ) )
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
