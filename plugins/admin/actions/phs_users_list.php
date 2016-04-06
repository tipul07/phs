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
    /** @var bool|PHS_Paginator */
    private $_paginator = false;

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
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

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         or !($accounts_plugin_settings = $accounts_plugin->get_plugin_settings()) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        if( !$accounts_model->can_list_accounts( $current_user ) )
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
        );

        if( !($this->_paginator = new PHS_Paginator( PHS::url( array( 'p' => 'admin', 'a' => 'users_list' ) ), $flow_params )) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t instantiate paginator class.' ) );
            return self::default_action_result();
        }

        if( !($users_levels = $accounts_model->get_levels_as_key_val()) )
            $users_levels = array();
        if( !($users_statuses = $accounts_model->get_statuses_as_key_val()) )
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
                'display_default_as_filter' => false,
            ),
            array(
                'display_name' => self::_t( 'Nickname' ),
                'display_hint' => self::_t( 'All records containing this value' ),
                'var_name' => 'fnick',
                'record_field' => 'nick',
                'record_check' => array( 'check' => 'LIKE', 'value' => '%%%s%%' ),
                'type' => PHS_params::T_NOHTML,
                'default' => '',
                'display_default_as_filter' => false,
            ),
            array(
                'display_name' => self::_t( 'Level' ),
                'var_name' => 'flevel',
                'record_field' => 'level',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'display_default_as_filter' => false,
                'values_arr' => $users_levels,
            ),
            array(
                'display_name' => self::_t( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'display_default_as_filter' => false,
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
        );

        if( !$this->_paginator->set_columns( $columns_arr )
         or !$this->_paginator->set_filters( $filters_arr )
         or !$this->_paginator->set_model( $accounts_model ) )
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
            
            $data = array(
                'filters' => $this->_paginator->get_filters_buffer(),
                'listing' => $this->_paginator->get_listing_buffer(),
            );
        }

        return $this->quick_render_template( 'users_list', $data );
    }
}
