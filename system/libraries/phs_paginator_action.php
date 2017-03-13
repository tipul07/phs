<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;

abstract class PHS_Action_Generic_list extends PHS_Action
{
    const ERR_DEPENCIES = 50000, ERR_ACTION = 50001;

    /** @var bool|PHS_Paginator */
    protected $_paginator = false;

    /** @var \phs\libraries\PHS_Model $_paginator_model */
    protected $_paginator_model = false;

    /**
     * @return bool true if all depencies were loaded successfully, false if any error (set_error should be used to pass error message)
     */
    abstract public function load_depencies();

    /**
     * @return array|bool Returns an array with flow_parameters, bulk_actions, filters_arr and columns_arr keys containing arrays with definitions for paginator class
     */
    abstract public function load_paginator_params();

    /**
     * @param string $action Action to be managed
     *
     * @return mixed
     */
    abstract public function manage_action( $action );

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    // Do any actions required immediately after paginator was instantiated
    public function we_have_paginator()
    {
        return true;
    }

    // Do any actions required after paginator was instantiated and initialized (eg. columns, filters, model and bulk actions were set)
    public function we_initialized_paginator()
    {
        return true;
    }

    protected function default_paginator_params()
    {
        return array(
            'base_url' => '',
            'flow_parameters' => false,
            'bulk_actions' => false,
            'filters_arr' => false,
            'columns_arr' => false,
        );
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        return false;
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_body_class( 'phs_paginator_action' );

        if( ($action_result = $this->should_stop_execution()) )
        {
            $action_result = self::validate_array( $action_result, self::default_action_result() );

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

        if( !($paginator_params = $this->load_paginator_params())
         or !is_array( $paginator_params )
         or !($paginator_params = self::validate_array( $paginator_params, $this->default_paginator_params() ))
         or empty( $paginator_params['base_url'] ) )
        {
            if( $this->has_error() )
                PHS_Notifications::add_error_notice( $this->get_error_message() );
            elseif( !PHS_Notifications::have_notifications_errors() )
                PHS_Notifications::add_error_notice( self::_t( 'Error loading paginator parameters.' ) );

            return self::default_action_result();
        }

        // Generic action hooks...
        $hook_args = PHS_Hooks::default_paginator_action_parameters_hook_args();
        $hook_args['paginator_action_obj'] = $this;
        $hook_args['paginator_params'] = $paginator_params;

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_PAGINATOR_ACTION_PARAMETERS, $hook_args ))
        and !empty( $hook_args['paginator_params'] ) and is_array( $hook_args['paginator_params'] ) )
            $paginator_params = $hook_args['paginator_params'];

        // Particular action hooks...
        $hook_args = PHS_Hooks::default_paginator_action_parameters_hook_args();
        $hook_args['paginator_action_obj'] = $this;
        $hook_args['paginator_params'] = $paginator_params;

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_PAGINATOR_ACTION_PARAMETERS.$this->instance_id(), $hook_args ))
        and !empty( $hook_args['paginator_params'] ) and is_array( $hook_args['paginator_params'] ) )
            $paginator_params = $hook_args['paginator_params'];

        if( !($this->_paginator = new PHS_Paginator( $paginator_params['base_url'], $paginator_params['flow_parameters'] ))
         or !$this->we_have_paginator() )
        {
            if( $this->has_error() )
                PHS_Notifications::add_error_notice( $this->get_error_message() );
            else
                PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t instantiate paginator class.' ) );

            return self::default_action_result();
        }

        //echo self::var_dump( $paginator_params['columns_arr'], array( 'max_level' => 3 ) );

        $init_went_ok = true;
        if( !$this->_paginator->set_columns( $paginator_params['columns_arr'] )
         or (!empty( $paginator_params['filters_arr'] ) and !$this->_paginator->set_filters( $paginator_params['filters_arr'] ))
         or (!empty( $this->_paginator_model ) and !$this->_paginator->set_model( $this->_paginator_model ))
         or (!empty( $paginator_params['bulk_actions'] ) and !$this->_paginator->set_bulk_actions( $paginator_params['bulk_actions'] )) )
        {
            if( $this->_paginator->has_error() )
                $error_msg = $this->_paginator->get_error_message();
            else
                $error_msg = self::_t( 'Something went wrong while preparing paginator class.' );

            $data = array(
                'filters' => $error_msg,
                'listing' => '',
            );

            $init_went_ok = false;
        }

        if( $init_went_ok )
        {
            if( !$this->we_initialized_paginator() )
            {
                if( $this->has_error() )
                    PHS_Notifications::add_error_notice( $this->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t initialize paginator class.' ) );

                return self::default_action_result();
            }

            // check actions...
            if( ($current_action = $this->_paginator->get_current_action())
            and is_array( $current_action )
            and !empty( $current_action['action'] ) )
            {
                if( !($pagination_action_result = $this->manage_action( $current_action )) )
                {
                    if( $this->has_error() )
                        PHS_Notifications::add_error_notice( $this->get_error_message() );
                } elseif( is_array( $pagination_action_result )
                          and !empty( $pagination_action_result['action'] ) )
                {
                    $pagination_action_result = self::validate_array( $pagination_action_result, $this->_paginator->default_action_params() );

                    $url_params = array(
                        'action' => $pagination_action_result,
                    );

                    if( !empty( $pagination_action_result['action_redirect_url_params'] )
                        and is_array( $pagination_action_result['action_redirect_url_params'] ) )
                        $url_params = self::merge_array_assoc( $pagination_action_result['action_redirect_url_params'], $url_params );

                    $action_result = self::default_action_result();

                    $action_result['redirect_to_url'] = $this->_paginator->get_full_url( $url_params );

                    return $action_result;
                }
            }

            $data = array(
                'filters' => $this->_paginator->get_filters_buffer(),
                'listing' => $this->_paginator->get_listing_buffer(),
                'paginator_params' => $this->_paginator->pagination_params(),
                'flow_params' => $this->_paginator->flow_params(),
            );
        }

        if( empty( $data ) )
            $data = array(
                'paginator_params' => array(),
                'filters' => self::_t( 'Something went wrong...' ),
                'listing' => '',
            );

        return $this->quick_render_template( 'paginator_default_template', $data );
    }

}
