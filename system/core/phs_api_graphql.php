<?php

namespace phs;

use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;

class PHS_Api_graphql extends PHS_Api_base
{
    public const ERR_API_INIT = 40000, ERR_REQUEST = 40001;

    private static ?PHS_Api_graphql $_api_obj = null;

    public function __construct(?array $init_query_params = null)
    {
        parent::__construct();

        if ($init_query_params !== null
            && !($this->_init_api_query_params($init_query_params))
            && !$this->has_error()) {
            $this->set_error(self::ERR_API_INIT, self::_t('Couldn\'t initialize GraphQL API object.'));
        }
    }

    final public function run_route(array $extra = [])
    {
        $this->reset_error();

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_GRAPHQL)) {
            $this->set_error(self::ERR_RUN_ROUTE, self::_t('Error preparing API environment.'));

            return false;
        }

        // Check if we have authentication...
        if (!$this->_check_api_authentication()) {
            $this->set_error_if_not_set(self::ERR_AUTHENTICATION, self::_t('Authentication failed.'));

            return false;
        }

        if (!$this->_before_route_run()) {
            $this->set_error_if_not_set(self::ERR_RUN_ROUTE, self::_t('Running action was stopped by API instance.'));

            return false;
        }

        // Update last_incoming for the domain...
        $edit_arr = self::$_domains_model->fetch_default_flow_params(['table_name' => 'phs_remote_domains']);
        $edit_arr['fields'] = [];
        $edit_arr['fields']['last_incoming'] = date(self::$_domains_model::DATETIME_DB);

        if (($new_domain_arr = self::$_domains_model->edit($domain_arr, $edit_arr))) {
            $domain_arr = $new_domain_arr;
        }

        // Log request right before running the actual action...
        $remote_log_arr = false;
        if (self::$_domains_model->should_log_requests($domain_arr)) {
            if (!self::$_domains_model->should_log_request_body($domain_arr)
             || !($req_body_arr = $this->api_flow_value('remote_domain_message'))
             || !($req_body_str = @json_encode($req_body_arr))) {
                $req_body_str = null;
            }

            $log_fields = [];
            $log_fields['route'] = PHS::get_route_as_string();
            $log_fields['body'] = $req_body_str;

            if (!($remote_log_arr = self::$_domains_model->domain_incoming_log($domain_arr, $log_fields))) {
                $remote_log_arr = false;
            }
        }

        // Reset any edit errors as we don't care about them...
        self::$_domains_model->reset_error();

        if (!($action_result = PHS::execute_route(['die_on_error' => false]))) {
            $this->copy_or_set_static_error(self::ERR_RUN_ROUTE,
                self::_t('Error executing route [%s].', PHS::get_route_as_string()));

            if (!empty($remote_log_arr)) {
                $log_fields = [];
                $log_fields['status'] = self::$_domains_model::LOG_STATUS_ERROR;
                $log_fields['error_log'] = $this->get_simple_error_message();

                // We don't care about errors...
                if (!self::$_domains_model->domain_incoming_log($domain_arr, $log_fields, $remote_log_arr)) {
                    self::$_domains_model->reset_error();
                }
            }

            return false;
        }

        if (!$this->_after_route_run()) {
            $this->set_error_if_not_set(self::ERR_RUN_ROUTE, self::_t('Flow was stopped by API instance after action run.'));

            if (!empty($remote_log_arr)) {
                $log_fields = [];
                $log_fields['status'] = self::$_domains_model::LOG_STATUS_ERROR;
                $log_fields['error_log'] = $this->get_simple_error_message();

                // We don't care about errors...
                if (!self::$_domains_model->domain_incoming_log($domain_arr, $log_fields, $remote_log_arr)) {
                    self::$_domains_model->reset_error();
                }
            }

            return false;
        }

        if (!empty($remote_log_arr)) {
            $log_fields = [];
            $log_fields['status'] = self::$_domains_model::LOG_STATUS_RECEIVED;

            // We don't care about errors...
            if (!self::$_domains_model->domain_incoming_log($domain_arr, $log_fields, $remote_log_arr)) {
                self::$_domains_model->reset_error();
            }
        }

        return $action_result;
    }

    /**
     * @return bool Returns true if custom authentication is ok or false if authentication failed
     */
    protected function _check_api_authentication() : bool
    {
        $this->reset_error();

        if (($authentication_failed = $this->_basic_api_authentication_failed())) {
            $this->set_error_if_not_set(self::ERR_AUTHENTICATION, self::_t('Authentication failed.'));

            if (!$this->send_header_response(
                $authentication_failed['http_code'] ?? self::H_CODE_UNAUTHORIZED,
                $authentication_failed['error_msg'] ?? self::_t('Authentication failed.')
            )) {
                return false;
            }

            exit;
        }

        return true;
    }

    final public static function framework_allows_graphql_calls() : bool
    {
        static $allow_graphql_calls = null;

        if ($allow_graphql_calls !== null) {
            return $allow_graphql_calls;
        }

        $allow_graphql_calls = PHS_Plugin_Admin::get_instance()?->get_plugin_settings()['allow_graphql_calls'] ?? false;

        return $allow_graphql_calls;
    }

    final public static function api_factory(array $init_query_params = []) : ?self
    {
        self::st_reset_error();

        if (!empty(self::$_api_obj)) {
            return self::$_api_obj;
        }

        if (!($api_obj = new self($init_query_params))) {
            self::st_set_error(self::ERR_API_INIT, self::_t('Error obtaining GraphQL API instance.'));

            return null;
        }

        self::$_api_obj = $api_obj;

        return self::$_api_obj;
    }
}
