<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Paginator;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Users_list extends PHS_Action
{
    const ERR_DEPENCIES = 1, ERR_ACTION = 2;

    /** @var bool|PHS_Paginator */
    private $_paginator = false;

    /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $_accounts_plugin */
    private $_accounts_plugin = false;

    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model = false;

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function load_depencies()
    {
        if( !($this->_accounts_plugin = PHS::load_plugin( 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, self::_t( 'Couldn\'t load accounts plugin.' ) );
            return false;
        }

        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, self::_t( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        return true;
    }

    /**
     * @return array|bool
     */
    public function execute()
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

        if( !$this->load_depencies() )
        {
            if( $this->has_error() )
                PHS_Notifications::add_error_notice( $this->get_error_message() );
            else
                PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load action depencies.' ) );

            return self::default_action_result();
        }

        if( !($accounts_plugin_settings = $this->_accounts_plugin->get_plugin_settings()) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts plugin settings.' ) );
            return self::default_action_result();
        }

        if( !$this->_accounts_model->can_list_accounts( $current_user ) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'You don\'t have rights to create accounts.' ) );
            return self::default_action_result();
        }

        $account_created = PHS_params::_g( 'account_created', PHS_params::T_NOHTML );

        if( !empty( $account_created ) )
            PHS_Notifications::add_success_notice( self::_t( 'User account created.' ) );

        $flow_params = array(
            'term_singular' => self::_t( 'user' ),
            'term_plural' => self::_t( 'users' ),
            'after_table_callback' => array( $this, 'after_table_callback' ),
        );

        if( !($this->_paginator = new PHS_Paginator( PHS::url( array( 'p' => 'admin', 'a' => 'users_list' ) ), $flow_params )) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t instantiate paginator class.' ) );
            return self::default_action_result();
        }

        if( !($users_levels = $this->_accounts_model->get_levels_as_key_val()) )
            $users_levels = array();
        if( !($users_statuses = $this->_accounts_model->get_statuses_as_key_val()) )
            $users_statuses = array();

        if( !empty( $users_levels ) )
            $users_levels = self::merge_array_assoc( array( 0 => self::_t( ' - Choose - ' ) ), $users_levels );
        if( !empty( $users_statuses ) )
            $users_statuses = self::merge_array_assoc( array( 0 => self::_t( ' - Choose - ' ) ), $users_statuses );

        $filters_arr = array(
            array(
                'display_name' => self::_t( 'IDs' ),
                'display_hint' => self::_t( 'Comma separated ids' ),
                'display_placeholder' => self::_t( 'eg. 1,2,3' ),
                'var_name' => 'fids',
                'record_field' => 'id',
                'record_check' => array( 'check' => 'IN', 'value' => '(%s)' ),
                'type' => PHS_params::T_ARRAY,
                'extra_type' => array( 'type' => PHS_params::T_INT ),
                'default' => '',
            ),
            array(
                'display_name' => self::_t( 'Nickname' ),
                'display_hint' => self::_t( 'All records containing this value' ),
                'var_name' => 'fnick',
                'record_field' => 'nick',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => self::_t( 'Level' ),
                'var_name' => 'flevel',
                'record_field' => 'level',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $users_levels,
            ),
            array(
                'display_name' => self::_t( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $users_statuses,
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
                'column_title' => self::_t( 'Nickname' ),
                'record_field' => 'nick',
            ),
            array(
                'column_title' => self::_t( 'Email' ),
                'record_field' => 'email',
                'invalid_value' => self::_t( 'N/A' ),
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => self::_t( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $users_statuses,
                'invalid_value' => self::_t( 'Undefined' ),
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => self::_t( 'Level' ),
                'record_field' => 'level',
                'display_key_value' => $users_levels,
                'extra_records_style' => 'text-align:center;',
            ),
            array(
                'column_title' => self::_t( 'Last Login' ),
                'record_field' => 'lastlog',
                'display_callback' => array( $this->_paginator, 'pretty_date' ),
                'date_format' => 'd M y H:i',
                'invalid_value' => self::_t( 'Never' ),
                'extra_style' => 'width:100px;',
                'extra_records_style' => 'text-align:right;',
            ),
            array(
                'column_title' => self::_t( 'Created' ),
                'default_sort' => 1,
                'record_field' => 'cdate',
                'display_callback' => array( $this->_paginator, 'pretty_date' ),
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

        if( !$this->_paginator->set_columns( $columns_arr )
         or !$this->_paginator->set_filters( $filters_arr )
         or !$this->_paginator->set_model( $this->_accounts_model ) )
        {
            if( $this->_paginator->has_error() )
                $error_msg = $this->_paginator->get_error_message();
            else
                $error_msg = self::_t( 'Something went wrong while preparing paginator class.' );

            $data = array(
                'filters' => $error_msg,
                'listing' => '',
            );
        } else
        {
            // check actions...
            if( ($current_action = $this->_paginator->get_current_action())
            and is_array( $current_action )
            and !empty( $current_action['action'] ) )
            {
                if( !$this->manage_action( $current_action ) )
                {
                    if( $this->has_error() )
                        PHS_Notifications::add_error_notice( $this->get_error_message() );
                }
            }
            
            $data = array(
                'filters' => $this->_paginator->get_filters_buffer(),
                'listing' => $this->_paginator->get_listing_buffer(),
            );
        }

        return $this->quick_render_template( 'users_list', $data );
    }
    
    public function manage_action( $action )
    {
        $this->reset_error();

        if( empty( $this->_accounts_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( empty( $action ) or !is_array( $action )
         or empty( $action['action'] ) )
            return true;

        switch( $action['action'] )
        {
            case 'activate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_success_notice( self::_t( 'Activating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    PHS_Notifications::add_error_notice( self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );
                 
                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    PHS_Notifications::add_error_notice( self::_t( 'Cannot activate account. Account not found.' ) );
                    return false;
                }

                PHS_Notifications::add_success_notice( 'Activating ['.$account_arr['nick'].'] account' );

            break;

            case 'inactivate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_success_notice( self::_t( 'Inactivating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    PHS_Notifications::add_error_notice( self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    PHS_Notifications::add_error_notice( self::_t( 'Cannot inactivate account. Account not found.' ) );
                    return false;
                }

                PHS_Notifications::add_success_notice( 'Inactivating ['.$account_arr['nick'].'] account' );

           break;

            case 'delete_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_success_notice( self::_t( 'Deleting account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    PHS_Notifications::add_error_notice( self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    PHS_Notifications::add_error_notice( self::_t( 'Cannot delete account. Account not found.' ) );
                    return false;
                }

                PHS_Notifications::add_success_notice( 'Deleting ['.$account_arr['nick'].'] account' );

            break;
        }

        return true;
    }

    public function display_actions( $params )
    {
        if( empty( $this->_accounts_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or !($account_arr = $this->_accounts_model->data_to_array( $params['record'] )) )
            return false;

        ob_start();
        if( $this->_accounts_model->is_inactive( $account_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_activate_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo self::_t( 'Activate account' )?>"></i></a>
            <?php
        }
        if( $this->_accounts_model->is_active( $account_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_inactivate_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo self::_t( 'Inactivate account' )?>"></i></a>
            <?php
        }

        if( !$this->_accounts_model->is_deleted( $account_arr ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_users_list_delete_account( '<?php echo $account_arr['id']?>' )"><i class="fa fa-times-circle-o action-icons" title="<?php echo self::_t( 'Delete account' )?>"></i></a>
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
        function phs_users_list_activate_account( id )
        {
            if( confirm( '<?php echo self::_e( 'Are you sure you want to activate this account?', '\'' )?>' ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'activate_account',
                    'action_params' => '\' + id + \'',
                )
                ?>document.location = '<?php echo $this->_paginator->get_full_url( $url_params )?>';
            }
        }
        function phs_users_list_inactivate_account( id )
        {
            if( confirm( '<?php echo self::_e( 'Are you sure you want to inactivate this account?', '\'' )?>' ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'inactivate_account',
                    'action_params' => '\' + id + \'',
                )
                ?>document.location = '<?php echo $this->_paginator->get_full_url( $url_params )?>';
            }
        }
        function phs_users_list_delete_account( id )
        {
            if( confirm( '<?php echo self::_e( 'Are you sure you want to delete this account?', '\'' )?>' ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'delete_account',
                    'action_params' => '\' + id + \'',
                )
                ?>document.location = '<?php echo $this->_paginator->get_full_url( $url_params )?>';
            }
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
