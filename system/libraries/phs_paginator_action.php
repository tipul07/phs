<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Scope;

abstract class PHS_Action_Generic_list extends PHS_Action
{
    public const ERR_DEPENCIES = 50000, ERR_ACTION = 50001;

    protected ?PHS_Paginator $_paginator = null;

    protected ?PHS_Model $_paginator_model = null;

    /**
     * @return bool true if all depencies were loaded successfully, false if any error (set_error should be used to pass error message)
     */
    abstract public function load_depencies(); // : bool;

    /**
     * @return null|array Returns an array with flow_parameters, bulk_actions, filters_arr and columns_arr keys containing arrays with definitions for paginator class
     */
    abstract public function load_paginator_params(); // : ?array;

    /**
     * @param array $action Action to be managed
     *
     * @return null|bool|array
     */
    abstract public function manage_action($action);

    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    // Do any actions required immediately after paginator was instantiated
    public function we_have_paginator()// : bool
    {
        return true;
    }

    // Do any actions required after paginator was instantiated and initialized (eg. columns, filters, model and bulk actions were set)
    public function we_initialized_paginator()// : bool
    {
        return true;
    }

    /**
     * @return null|array Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()// : ?array
    {
        return null;
    }

    /**
     * @param array $current_columns_arr
     * @param string|array $where After which column should $new_columns_arr be inserted. If string we assume column key 'record_field' will be provided value/
     *                            If array, eg $where = array( '{array_key}', '{value_to_be_checked}' ) = array( 'record_field', 'nick' );
     * @param array $new_columns_arr
     *
     * @return array
     */
    public function insert_columns_arr(array $current_columns_arr, $where, array $new_columns_arr) : array
    {
        if (empty($new_columns_arr)) {
            if (empty($current_columns_arr)) {
                $current_columns_arr = [];
            }

            return $current_columns_arr;
        }

        if (empty($current_columns_arr)) {
            return $new_columns_arr;
        }

        if (empty($where)
         || (!is_string($where) && !is_array($where))
         || (is_array($where)
                && (empty($where[0]) || empty($where[1]) || !is_string($where[0]) || !is_string($where[1])
                ))
        ) {
            return $current_columns_arr;
        }

        if (is_string($where)) {
            $where = ['record_field', $where];
        }

        $where_column_key = $where[0];
        $where_column_val = $where[1];

        $columns_arr = [];
        $new_columns_added = false;
        foreach ($current_columns_arr as $column_key => $column_arr) {
            if (empty($column_arr) || !is_array($column_arr)
             || empty($column_arr[$where_column_key])
             || $column_arr[$where_column_key] != $where_column_val) {
                if (!is_numeric($column_key)) {
                    $columns_arr[$column_key] = $column_arr;
                } else {
                    $columns_arr[] = $column_arr;
                }

                continue;
            }

            $columns_arr[] = $column_arr;

            $new_columns_added = true;
            foreach ($new_columns_arr as $new_column_key => $new_column_arr) {
                if (!is_numeric($new_column_key)) {
                    $columns_arr[$new_column_key] = $new_column_arr;
                } else {
                    $columns_arr[] = $new_column_arr;
                }
            }
        }

        if (empty($new_columns_added)) {
            foreach ($new_columns_arr as $new_column_key => $new_column_arr) {
                if (!is_numeric($new_column_key)) {
                    $columns_arr[$new_column_key] = $new_column_arr;
                } else {
                    $columns_arr[] = $new_column_arr;
                }
            }
        }

        return $columns_arr;
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_body_class('phs_paginator_action');

        if (!$this->load_depencies()) {
            PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Error loading required resources.')));

            return self::default_action_result();
        }

        if (($action_result = $this->should_stop_execution())) {
            return self::validate_action_result($action_result);
        }

        if (!($paginator_params = $this->load_paginator_params())
         || !is_array($paginator_params)
         || !($paginator_params = self::validate_array($paginator_params, $this->default_paginator_params()))
         // Complain about base_url not set only if we are not forced to return an action result already
         || (empty($paginator_params['base_url']) && empty($paginator_params['force_action_result']))) {
            if ($this->has_error()) {
                PHS_Notifications::add_error_notice($this->get_simple_error_message());
            } elseif (!PHS_Notifications::have_notifications_errors()) {
                PHS_Notifications::add_error_notice(self::_t('Error loading paginator parameters.'));
            }

            return self::default_action_result();
        }

        if (!empty($paginator_params['force_action_result'])) {
            return self::validate_action_result($paginator_params['force_action_result']);
        }

        // Generic action hooks...
        $hook_args = PHS_Hooks::default_paginator_action_parameters_hook_args();
        $hook_args['paginator_action_obj'] = $this;
        $hook_args['paginator_params'] = $paginator_params;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_PAGINATOR_ACTION_PARAMETERS, $hook_args))
         && !empty($hook_args['paginator_params']) && is_array($hook_args['paginator_params'])) {
            $paginator_params = self::validate_array($hook_args['paginator_params'], $this->default_paginator_params());
        }

        // Particular action hooks...
        $hook_args = PHS_Hooks::default_paginator_action_parameters_hook_args();
        $hook_args['paginator_action_obj'] = $this;
        $hook_args['paginator_params'] = $paginator_params;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_PAGINATOR_ACTION_PARAMETERS.$this->instance_id(), $hook_args))
         && !empty($hook_args['paginator_params']) && is_array($hook_args['paginator_params'])) {
            $paginator_params = self::validate_array($hook_args['paginator_params'], $this->default_paginator_params());
        }

        if (empty($paginator_params['flow_parameters']) || !is_array($paginator_params['flow_parameters'])) {
            $paginator_params['flow_parameters'] = [];
        }

        if (!($this->_paginator = new PHS_Paginator($paginator_params['base_url'], $paginator_params['flow_parameters']))
            || !$this->we_have_paginator()) {
            PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Couldn\'t instantiate paginator class.')));

            return self::default_action_result();
        }

        $init_went_ok = true;
        if (!$this->_paginator->set_columns($paginator_params['columns_arr'])
         || (!empty($paginator_params['filters_arr'])
             && is_array($paginator_params['filters_arr'])
             && !$this->_paginator->set_filters($paginator_params['filters_arr']))
         || (!empty($this->_paginator_model)
             && !$this->_paginator->set_model($this->_paginator_model))
         || (!empty($paginator_params['bulk_actions'])
             && is_array($paginator_params['bulk_actions'])
             && !$this->_paginator->set_bulk_actions($paginator_params['bulk_actions']))
        ) {
            $data = [
                'filters' => $this->_paginator->get_simple_error_message(self::_t('Something went wrong while preparing paginator class.')),
                'listing' => '',
            ];

            $init_went_ok = false;
        }

        if ($init_went_ok) {
            if (!$this->we_initialized_paginator()) {
                PHS_Notifications::add_error_notice($this->get_simple_error_message(self::_t('Couldn\'t initialize paginator class.')));

                return self::default_action_result();
            }

            // check actions...
            if (($current_action = $this->_paginator->get_current_action())
             && !empty($current_action['action'])) {
                if (!($pagination_action_result = $this->manage_action($current_action))) {
                    if ($this->has_error()) {
                        PHS_Notifications::add_error_notice($this->get_simple_error_message());
                    }
                } elseif (is_array($pagination_action_result)
                       && !empty($pagination_action_result['action'])) {
                    $pagination_action_result = self::validate_array($pagination_action_result, $this->_paginator->default_action_params());

                    $url_params = [
                        'action' => $pagination_action_result,
                    ];

                    if (!empty($pagination_action_result['action_redirect_url_params'])
                        && is_array($pagination_action_result['action_redirect_url_params'])) {
                        $url_params = self::merge_array_assoc($pagination_action_result['action_redirect_url_params'],
                            $url_params);
                    }

                    return action_redirect($this->_paginator->get_full_url($url_params));
                }
            }

            if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
                // Prepare API response
                $action_result = PHS_Action::default_action_result();

                if (!($json_result = $this->_paginator->get_listing_result())
                    || !is_array($json_result)) {
                    $json_result = [];
                }

                $action_result['api_json_result_array'] = $json_result;

                return $action_result;
            }

            $data = [
                'filters'          => $this->_paginator->get_filters_result(),
                'listing'          => $this->_paginator->get_listing_result(),
                'paginator_params' => $this->_paginator->pagination_params(),
                'flow_params'      => $this->_paginator->flow_params(),
            ];
        }

        if (empty($data)) {
            PHS_Notifications::add_error_notice(self::_t('Error rendering paginator details.'));

            $data = [
                'paginator_params' => [],
                'filters'          => self::_t('Something went wrong...'),
                'listing'          => '',
            ];
        }

        return $this->quick_render_template('paginator_default_template', $data);
    }

    protected function default_paginator_params() : array
    {
        return [
            'base_url'        => '',
            'flow_parameters' => [],
            'bulk_actions'    => [],
            'filters_arr'     => [],
            'columns_arr'     => [],
            // an action result array or null
            'force_action_result' => null,
        ];
    }
}
