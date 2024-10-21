<?php

namespace phs;

use phs\libraries\PHS_Logger;
use phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains;

class PHS_Api_remote extends PHS_Api_base
{
    public const ERR_API_INIT = 40000, ERR_API_ROUTE = 40001, ERR_REMOTE_MESSAGE = 40002;

    private static ?PHS_Api_remote $_api_obj = null;

    private static ?PHS_Model_Phs_remote_domains $_domains_model = null;

    public function __construct(?array $init_query_params = null)
    {
        parent::__construct();

        if ($init_query_params !== null
         && !($this->_init_api_query_params($init_query_params))
         && !$this->has_error()) {
            $this->set_error(self::ERR_API_INIT, self::_t('Couldn\'t initialize remote API object.'));
        }
    }

    /**
     * @inheritdoc
     */
    final public function run_route(array $extra = [])
    {
        $this->reset_error();

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_REMOTE)) {
            $this->set_error(self::ERR_RUN_ROUTE, self::_t('Error preparing API environment.'));

            return false;
        }

        if (!($request_arr = $this->_parse_remote_message())) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Error interpreting the request.'));

            return false;
        }

        $domain_arr = $request_arr['domain_data'];
        $message_arr = $request_arr['message_data'];

        $this->api_flow_value('remote_domain', $domain_arr);
        $this->api_flow_value('remote_domain_message', $message_arr);

        $phs_route = PHS::validate_route_from_parts($message_arr['route'], true);

        // Check if we have authentication...
        if (!$this->_check_api_authentication()
         || !($apikey_arr = $this->get_request_apikey())
         || empty($apikey_arr['id'])
         || empty($domain_arr['apikey_id'])
         || (int)$domain_arr['apikey_id'] !== (int)$apikey_arr['id']) {
            $this->set_error_if_not_set(self::ERR_RUN_ROUTE, self::_t('Authentication failed.'));

            return false;
        }

        if (PHS::st_debugging_mode()) {
            if (!($route_str = PHS::route_from_parts($phs_route))) {
                $route_str = 'N/A';
            }

            PHS_Logger::debug('Remote PHS route ['.$route_str.']', PHS_Logger::TYPE_REMOTE);
        }

        $this->api_flow_value('phs_route', $phs_route);

        PHS::set_route($phs_route);

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
     * @inheritdoc
     */
    public function create_response_envelope(array $response_arr, ?array $errors_arr = null) : ?array
    {
        return $this->default_response_envelope($response_arr, $errors_arr);
    }

    protected function _before_route_run() : bool
    {
        if ($this->is_web_simulation()) {
            PHS_Scope::emulated_scope(PHS_Scope::SCOPE_WEB);
        }

        if (($message_arr = $this->api_flow_value('remote_domain_message'))
         && is_array($message_arr)) {
            // In case we run in an environment where $_POST or $_GET are not defined
            global $_POST, $_GET;

            if (!empty($message_arr['post_arr']) && is_array($message_arr['post_arr'])) {
                if (empty($_POST) || !is_array($_POST)) {
                    $_POST = [];
                }

                foreach ($message_arr['post_arr'] as $key => $val) {
                    $_POST[$key] = $val;
                }
            }

            if (!empty($message_arr['get_arr']) && is_array($message_arr['get_arr'])) {
                if (empty($_GET) || !is_array($_GET)) {
                    $_GET = [];
                }

                foreach ($message_arr['get_arr'] as $key => $val) {
                    $_GET[$key] = $val;
                }
            }
        }

        return true;
    }

    protected function _after_route_run() : bool
    {
        if ($this->is_web_simulation()) {
            PHS_Scope::emulated_scope(0);
        }

        return true;
    }

    /**
     * @return bool Returns true if custom authentication is ok or false if authentication failed
     */
    protected function _check_api_authentication() : bool
    {
        $this->reset_error();

        if (($authentication_failed = $this->_basic_api_authentication_failed())) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_AUTHENTICATION, self::_t('Authentication failed.'));
            }

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

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (empty(self::$_domains_model)
            && !(self::$_domains_model = PHS_Model_Phs_remote_domains::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    private function _parse_remote_message() : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        // Process JSON body
        if (!($root_json_arr = self::get_request_body_as_json_array())
         || empty($root_json_arr['remote_id'])
         || empty($root_json_arr['msg']) || !is_string($root_json_arr['msg'])
         || !($remote_id = (int)$root_json_arr['remote_id'])
         || !($domain_arr = self::$_domains_model->get_details($remote_id, ['table_name' => 'phs_remote_domains']))
         || !self::$_domains_model->is_connected($domain_arr)) {
            $this->set_error(self::ERR_AUTHENTICATION, $this->_pt('Invalid request.'));

            return null;
        }

        if (!self::$_domains_model->should_allow_incoming_requests($domain_arr)) {
            $this->set_error(self::ERR_RIGHTS, $this->_pt('Access denied.'));

            return null;
        }

        if (!empty($domain_arr['ips_whitelist'])
         && (
             !($request_ip = request_ip())
            || !in_array($request_ip, self::extract_strings_from_comma_separated($domain_arr['ips_whitelist'], ['to_lowercase' => true]), true)
         )) {
            PHS_Logger::error('IP denied (#'.$domain_arr['id'].', '.$request_ip.').', PHS_Logger::TYPE_REMOTE);

            $this->set_error(self::ERR_AUTHENTICATION, $this->_pt('Access denied.'));

            return null;
        }

        if (!($message_str = self::$_domains_model->quick_decode($domain_arr, $root_json_arr['msg']))
         || !($message_arr = @json_decode($message_str, true))
         || !($message_arr = self::$_domains_model->validate_communication_message($message_arr))) {
            PHS_Logger::error('Error decoding message (#'.$domain_arr['id'].').'
                              .(self::$_domains_model->has_error() ? ' Error: '.self::$_domains_model->get_simple_error_message() : ''), PHS_Logger::TYPE_REMOTE);

            $this->set_error(self::ERR_REMOTE_MESSAGE, $this->_pt('Error decoding message.'));

            return null;
        }

        return [
            'domain_data'  => $domain_arr,
            'message_data' => $message_arr,
        ];
    }

    final public static function api_factory(array $init_query_params = []) : ?self
    {
        self::st_reset_error();

        if (!empty(self::$_api_obj)) {
            return self::$_api_obj;
        }

        if (!($api_obj = new self($init_query_params))) {
            self::st_set_error(self::ERR_API_INIT, self::_t('Error obtaining remote API instance.'));

            return null;
        }

        self::$_api_obj = $api_obj;

        return self::$_api_obj;
    }
}
