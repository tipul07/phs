<?php

namespace phs\system\core\libraries;

use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;
use phs\system\core\models\PHS_Model_Api_monitor;
use phs\system\core\models\PHS_Model_Request_queue;

class PHS_Requests_queue_manager extends PHS_Library
{
    private ?PHS_Model_Request_queue $_requests_model = null;

    public function http_call(
        string $url,
        string $method = 'get',
        ?string $payload = null,
        ?array $settings = null,
        array $params = [],
    ) : ?array {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        $params['max_retries'] = (int)($params['max_retries'] ?? 1);
        $params['handle'] ??= null;
        $params['sync_run'] = !isset($params['sync_run']) || !empty($params['sync_run']);
        $params['same_thread_if_bg'] = !isset($params['same_thread_if_bg']) || !empty($params['same_thread_if_bg']);
        $params['run_after'] ??= $params['run_after'];

        if ( !empty($params['run_after'])
            && ($run_after = parse_db_date($params['run_after']))) {
            $params['run_after'] = date($this->_requests_model::DATETIME_DB, $run_after);
        } else {
            $params['run_after'] = null;
        }

        if ( !($request_arr = $this->_requests_model->create_request($url, $method, $payload, $settings, $params['max_retries'], $params['handle'], $params['run_after'])) ) {
            $this->copy_or_set_error($this->_requests_model,
                self::ERR_FUNCTIONALITY, self::_t('Error adding the request to the queue.'));

            return null;
        }

        if (empty($params['run_after'])) {
            $request_arr = $params['sync_run']
                ? $this->run_request($request_arr)
                : $this->run_request_bg($request_arr);

            if ( !$request_arr ) {
                return null;
            }
        }

        return $request_arr;
    }

    public function run_request_bg(int | array $request_data, bool $forced_run = false) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        if ( !($request_arr = $this->_requests_model->data_to_array($request_data, ['table_name' => 'phs_request_queue'])) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request details not found in database.'));

            return null;
        }

        if (!$forced_run
           && !$this->_requests_model->can_run_request($request_arr, $forced_run)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided request cannot be run.'));

            return null;
        }

        if (!PHS_Bg_jobs::run(
            ['c' => 'index_bg', 'a' => 'run_request_bg'],
            ['request_id' => $request_arr['id'], 'force_run' => $forced_run])
        ) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error launching background job for the provided request.'));

            return null;
        }

        return $request_arr;
    }

    public function run_request(int | array $request_data, bool $forced_run = false) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        if ( !($request_arr = $this->_requests_model->data_to_array($request_data, ['table_name' => 'phs_request_queue'])) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request details not found in database.'));

            return null;
        }

        if (!$forced_run
           && $this->_requests_model->can_run_request($request_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided request cannot be run.'));

            return null;
        }

        if ( ($new_request = $this->_requests_model->start_request($request_arr)) ) {
            $request_arr = $new_request;
        }

        if ( !($result = $this->_do_api_call($request_arr)) ) {
            $this->_requests_model->update_request($request_arr);

            return null;
        }

        return $request_arr;
    }

    protected function _do_api_call(array $request_arr, array $params = []) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        if ( !($settings_arr = $this->_requests_model->get_request_settings($request_arr)) ) {
            $settings_arr = [];
        }

        $curl_params = [];
        if ( !empty($settings_arr['auth_basic']) ) {
            $curl_params['userpass'] = [
                'user' => $settings_arr['auth_basic']['user'] ?? '',
                'pass' => $settings_arr['auth_basic']['pass'] ?? '',
            ];
        }
        if ( !empty($settings_arr['auth_bearer']['token']) ) {
            $curl_params['header_keys_arr'] ??= [];
            $curl_params['header_keys_arr']['Authorization'] = 'Bearer '.$settings_arr['auth_bearer']['token'];
        }

        $call_params = [];
        $call_params['curl_params'] = $curl_params;

        if ( !($result = $this->_do_api_call_to_url($request_arr['url'], $request_arr['payload'], $request_arr['method'], $call_params)) ) {
            return null;
        }

        return $result;
    }

    protected function _do_api_call_to_url(string $url, ?string $payload = null, ?string $method = null, array $params = []) : ?array
    {
        if ( !$this->_load_dependencies()) {
            return null;
        }

        $params['skip_api_monitoring'] = !empty($params['skip_api_monitoring']);
        $params['log_file'] ??= $params['log_file'];

        $params['expect_json'] = !empty($params['expect_json']);
        $params['timeout'] = (int)($params['timeout'] ?? 30);

        if (empty($params['headers']) || !is_array($params['headers'])) {
            $params['headers'] = [];
        }
        if (empty($params['curl_params']) || !is_array($params['curl_params'])) {
            $params['curl_params'] = [];
        }

        if (empty($params['log_file'])
           || !PHS_Logger::define_channel($params['log_file'])) {
            $params['log_file'] = null;
        }

        $curl_params = $params['curl_params'];
        $curl_params['timeout'] = $params['timeout'];
        if (!empty($params['headers'])) {
            $curl_params['header_keys_arr'] = $params['headers'];
        }

        if ($payload !== null) {
            $curl_params['raw_post_str'] = $payload;
            $method ??= 'post';
        }

        if (!empty($method)) {
            $curl_params['http_method'] = $method;
        }

        if ($params['skip_api_monitoring']) {
            $outgoing_request = null;
        } else {
            $outgoing_request = PHS_Model_Api_monitor::api_outgoing_request_started($url, $payload ?: null, $method ?? 'GET');
        }

        if ($params['log_file']) {
            PHS_Logger::info('Sending '.strtoupper($method ?? 'get').' request to '.$url.'.', $params['log_file'] );
        }

        $obfuscated_params = $curl_params;
        if (!empty($obfuscated_params['userpass'])) {
            $obfuscated_params['userpass'] = '(Obfuscated_credentials)';
        }
        // TODO: Obfuscate authentication headers

        if (!($api_response = PHS_Utils::quick_curl($url, $curl_params))
            || !is_array($api_response)
        ) {
            if ($params['log_file']) {
                PHS_Logger::error('Error initiating API call.', $params['log_file']);
            }

            ob_start();
            var_dump($curl_params);
            $request_params = @ob_get_clean();

            PHS_Logger::info('API URL: '.$url."\n"
                             .'Params: '.$request_params, $this->_vies_vat_plugin::LOG_CHANNEL);

            $error_msg = $this->_pt('Error initiating call to VIES VAT API.');
            $this->set_error(self::ERR_API_INIT, $error_msg);

            PHS_Model_Api_monitor::api_outgoing_request_error($outgoing_request, null, $error_msg);

            return null;
        }

        return null;
    }

    protected function _empty_request_response() : array
    {
        return [
            'http_code'        => 0,
            'response_headers' => [],
            'response_buf'     => null,
            'response_json'    => null,
            'response_curl'    => null,
        ];
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            ($this->_requests_model === null && !($this->_requests_model = PHS_Model_Request_queue::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
