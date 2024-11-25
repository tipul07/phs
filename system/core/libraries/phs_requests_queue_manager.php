<?php
namespace phs\system\core\libraries;

use phs\PHS;
use phs\PHS_Agent;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Record_data;
use phs\system\core\models\PHS_Model_Api_monitor;
use phs\system\core\models\PHS_Model_Request_queue;

class PHS_Requests_queue_manager extends PHS_Library
{
    private const _LOG_METHOD_ERROR = 'error', _LOG_METHOD_WARNING = 'warning', _LOG_METHOD_NOTICE = 'notice',
        _LOG_METHOD_INFO = 'info', _LOG_METHOD_DEBUG = 'debug';

    private ?PHS_Model_Request_queue $_requests_model = null;

    public function http_call(
        string $url,
        ?string $method = 'GET',
        null | array | string $payload = null,
        ?array $settings = null,
        array $params = [],
    ) : ?array {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!$method) {
            $method = 'GET';
        } else {
            $method = strtoupper(trim($method)) ?: 'GET';
        }

        if ($payload && $method === 'GET') {
            $method = 'POST';
        }

        $params['max_retries'] = (int)($params['max_retries'] ?? 1);
        $params['handle'] ??= null;
        $params['sync_run'] = !isset($params['sync_run']) || !empty($params['sync_run']);
        $params['same_thread_if_bg'] = !isset($params['same_thread_if_bg']) || !empty($params['same_thread_if_bg']);
        $params['run_after'] ??= null;

        if (!empty($params['run_after'])
            && ($run_after = parse_db_date($params['run_after']))) {
            $params['run_after'] = date($this->_requests_model::DATETIME_DB, $run_after);
        } else {
            $params['run_after'] = null;
        }

        if (!($request_arr = $this->_requests_model->create_request($url, $method, $payload, $settings, $params['max_retries'], $params['handle'], $params['run_after']))) {
            $this->copy_or_set_error($this->_requests_model,
                self::ERR_FUNCTIONALITY, self::_t('Error adding the request to the queue.'));

            return null;
        }

        if (empty($params['run_after'])) {
            $request_arr = $params['sync_run']
                           || ($params['same_thread_if_bg'] && PHS::are_we_in_a_background_thread())
                ? $this->run_request_bg($request_arr)
                : $this->run_request($request_arr);

            if (!$request_arr) {
                return null;
            }
        }

        return $request_arr;
    }

    public function check_http_calls_queue() : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($flow_params = $this->_requests_model->fetch_default_flow_params(['table_name' => 'phs_request_queue']))
            || !($requests_table = $this->_requests_model->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Couldn\'t obtain flow parameters.'));

            return null;
        }

        $return_arr = [];
        $return_arr['total'] = 0;
        $return_arr['success'] = 0;
        $return_arr['failed'] = 0;
        $return_arr['retries'] = 0;
        $return_arr['timed'] = 0;

        if (!($qid = db_query('SELECT * FROM `'.$requests_table.'`'
                              .' WHERE '
                              .' (status = \''.$this->_requests_model::STATUS_FAILED.'\' AND is_final = 0) '
                              .' OR '
                              .' (status = \''.$this->_requests_model::STATUS_PENDING.'\' AND run_after <= NOW()) '
                              .'ORDER BY cdate ASC',
            $flow_params['db_connection']))
            || !($requests_no = db_num_rows($qid, $flow_params['db_connection']))) {
            return $return_arr;
        }

        $return_arr['total'] = $requests_no;

        PHS_Logger::notice('[QUEUE] Trying '.$requests_no.' HTTP calls from queue', PHS_Logger::TYPE_HTTP_CALLS);

        while (($request_arr = @mysqli_fetch_assoc($qid))) {
            $call_type = '';
            if ($this->_requests_model->is_failed($request_arr)) {
                $return_arr['retries']++;
                $call_type = 'retry';
            } elseif ($this->_requests_model->is_timed($request_arr)) {
                $return_arr['timed']++;
                $call_type = 'timed';
            }

            PHS_Logger::notice('[QUEUE] Running request #'.$request_arr['id']
                .($call_type ? ' - '.$call_type : ''),
                PHS_Logger::TYPE_HTTP_CALLS
            );

            if (!($run_result = $this->run_request_bg($request_arr))
                || $run_result['has_error']) {
                PHS_Logger::error('[QUEUE] Error running request #'.$request_arr['id'].': '
                                  .$this->get_simple_error_message(self::_t('Unknown error.'))
                                  .(!empty($run_result['error_msg']) ? ' ('.$run_result['error_msg'].')' : ''),
                    PHS_Logger::TYPE_HTTP_CALLS
                );

                $return_arr['failed']++;
                continue;
            }

            $return_arr['success']++;

            if (PHS_Agent::current_job_data()) {
                PHS_Agent::refresh_current_job();
            }
        }

        // Make sure we don't propagate individual retry errors...
        $this->reset_error();

        PHS_Logger::notice('[QUEUE] Finished sending HTTP calls from queue', PHS_Logger::TYPE_HTTP_CALLS);

        return $return_arr;
    }

    public function run_request(int | array | PHS_Record_data $request_data, bool $force_run = false) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($request_arr = $this->_requests_model->data_to_array($request_data, ['table_name' => 'phs_request_queue']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request details not found in database.'));

            return null;
        }

        if (!$force_run
           && !$this->_requests_model->can_run_request($request_arr, $force_run)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided request cannot be run.'));

            return null;
        }

        if (!PHS_Bg_jobs::run(
            ['c' => 'index_bg', 'a' => 'run_request_bg'],
            ['request_id' => $request_arr['id'], 'force_run' => $force_run])
        ) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error launching background job for the provided request.'));

            return null;
        }

        $request_response = $this->_empty_request_response();
        $request_response['request_data'] = $request_arr;

        return $request_response;
    }

    public function run_request_bg(int | array | PHS_Record_data $request_data, bool $force_run = false) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($request_arr = $this->_requests_model->data_to_array($request_data, ['table_name' => 'phs_request_queue']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request details not found in database.'));

            return null;
        }

        if (!$force_run
           && !$this->_requests_model->can_run_request($request_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided request cannot be run.'));

            return null;
        }

        if (($new_request = $this->_requests_model->start_request($request_arr))) {
            $request_arr = $new_request;
        }

        $request_response = $this->_do_api_call($request_arr);

        if (!($update_result = $this->_update_request_for_result($request_arr, $request_response))) {
            self::_logf(
                self::_LOG_METHOD_ERROR,
                'Error updating request status (request #'.$request_arr['id'].') after run: '
                .$this->_requests_model->get_simple_error_message(self::_t('Unknown error.')),
                $request_response['log_file']
            );
        } else {
            $request_response['request_data'] = $update_result['request_data'];
            $request_response['request_run_data'] = $update_result['request_run_data'];

            $request_arr = $update_result['request_data'];
        }

        $this->_callbacks_on_finish($request_arr, $request_response);

        if ($this->_requests_model->is_final($request_arr)
           && $this->_requests_model->should_delete_on_completion($request_arr)
           && !$this->_requests_model->hard_delete_http_call($request_arr)) {
            self::_logf(
                self::_LOG_METHOD_WARNING,
                'Error deleting request after successful run: '
                .$this->_requests_model->get_simple_error_message(self::_t('Unknown error.')),
                $request_response['log_file']
            );
        }

        return $request_response;
    }

    private function _callbacks_on_finish(array $request_arr, array $request_response) : void
    {
        $callbacks = [];
        $errors_arr = [];
        if ($this->_requests_model->is_success($request_arr)) {
            if (($callback = $this->_requests_model->get_request_success_callback($request_arr))) {
                $callbacks['success'] = $callback;
            } elseif ($this->_requests_model->has_error()) {
                $errors_arr[] = $this->_requests_model->get_simple_error_message();
            }
        } elseif ($this->_requests_model->is_failed($request_arr)) {
            if (($callback = $this->_requests_model->get_request_one_fail_callback($request_arr))) {
                $callbacks['one_failure'] = $callback;
            } elseif ($this->_requests_model->has_error()) {
                $errors_arr[] = $this->_requests_model->get_simple_error_message();
            }

            if ($this->_requests_model->is_final($request_arr)) {
                if (($callback = $this->_requests_model->get_request_fail_callback($request_arr))) {
                    $callbacks['final_failure'] = $callback;
                } elseif ($this->_requests_model->has_error()) {
                    $errors_arr[] = $this->_requests_model->get_simple_error_message();
                }
            }
        }

        if (!empty($errors_arr)) {
            self::_logf(
                self::_LOG_METHOD_ERROR,
                'Error(s) in callbacks: '.implode(', ', $errors_arr),
                $request_response['log_file']
            );
        }

        if (empty($callbacks)) {
            return;
        }

        foreach ($callbacks as $key => $callback) {
            $callack_str = $this->_get_callback_as_string($callback);

            self::_logf(self::_LOG_METHOD_NOTICE,
                '[Callback] Request #'.$request_arr['id'].', calling: ['.$callack_str.'] for '.$key.'.',
                $request_response['log_file'] ?: null
            );

            /** @var ?\phs\libraries\PHS_Instantiable $callback_obj */
            $callback_obj = $callback[0] ?? null;
            if (!@$callback($request_response)) {
                self::_logf(
                    self::_LOG_METHOD_ERROR,
                    '[Callback] Error in callback '.$callack_str.' after request run: '
                    .($callback_obj ? $callback_obj->get_simple_error_message(self::_t('Unknown error.')) : 'N/A'),
                    $request_response['log_file'] ?: null
                );
            }
        }
    }

    private function _get_callback_as_string(string | array $callback) : string
    {
        if (empty($callback)
            || (is_array($callback)
                && (empty($callback[0]) || empty($callback[1])
                    || !is_object($callback[0]) || !is_string($callback[1])))
        ) {
            return '(invalid_callback)';
        }

        if (is_string($callback)) {
            return $callback.'()';
        }

        $obj = $callback[0];

        return $obj::class.'::'.$callback[1].'()';
    }

    private function _do_api_call(array | PHS_Record_data $request_arr, array $params = []) : array
    {
        if (!($settings_arr = $this->_requests_model->get_request_full_settings($request_arr))) {
            $settings_arr = $this->_requests_model->empty_request_settings_arr();
        }

        if (empty($settings_arr['success_codes']) || !is_array($settings_arr['success_codes'])) {
            $settings_arr['success_codes'] = [200];
        }

        $params['success_codes'] = $settings_arr['success_codes'];
        $params['timeout'] = $settings_arr['timeout'] ?? 30;
        $params['log_file'] = $settings_arr['log_file'] ?? null;
        $params['expect_json'] = $settings_arr['expect_json_response'] ?? false;

        if (!empty($settings_arr['headers']) && is_array($settings_arr['headers'])) {
            $params['headers'] = $settings_arr['headers'];
        }

        $curl_params = $params['curl_params'] ?? [];

        if (!empty($settings_arr['curl_params']) && is_array($settings_arr['curl_params'])) {
            $curl_params = self::validate_array($curl_params, $settings_arr['curl_params']);
        }

        if (!empty($settings_arr['auth_basic'])) {
            $curl_params['userpass'] = [
                'user' => $settings_arr['auth_basic']['user'] ?? '',
                'pass' => $settings_arr['auth_basic']['pass'] ?? '',
            ];
        }
        if (!empty($settings_arr['auth_bearer']['token'])) {
            $curl_params['header_keys_arr'] ??= [];
            $curl_params['header_keys_arr']['Authorization'] = 'Bearer '.$settings_arr['auth_bearer']['token'];
        }
        $params['curl_params'] = $curl_params;

        return $this->_do_api_call_to_url($request_arr['url'], $request_arr['payload'], $request_arr['method'], $params);
    }

    private function _do_api_call_to_url(string $url, ?string $payload = null, ?string $method = null, array $params = []) : array
    {
        $params['skip_api_monitoring'] = !empty($params['skip_api_monitoring']);

        $params['timeout'] = (int)($params['timeout'] ?? 30);
        $params['log_file'] ??= null;
        $params['expect_json'] = !empty($params['expect_json']);

        if (empty($params['success_codes']) || !is_array($params['success_codes'])) {
            $params['success_codes'] = [200];
        }
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

        if (empty($curl_params['header_keys_arr']) || !is_array($curl_params['header_keys_arr'])) {
            $curl_params['header_keys_arr'] = [];
        }
        if (!empty($params['headers']) && !is_array($params['headers'])) {
            $params['headers'] = [];
        }
        if (!empty($params['headers'])) {
            $curl_params['header_keys_arr'] = array_merge($curl_params['header_keys_arr'], $params['headers']);
        }

        if (!empty($curl_params['header_keys_arr'])) {
            $curl_params['header_keys_arr'] = self::unify_array_insensitive($curl_params['header_keys_arr']);
        }

        if ($payload !== null) {
            $curl_params['raw_post_str'] = $payload;
            if (!$method || strtoupper(trim($method)) === 'GET') {
                $method = 'POST';
            }
        }

        if (!empty($method)) {
            $curl_params['http_method'] = strtoupper(trim($method));
        }

        $monitoring_record = $params['skip_api_monitoring']
            ? null
            : PHS_Model_Api_monitor::api_outgoing_request_started($url, $payload ?: null, $method ?? 'GET');

        $request_response = $this->_empty_request_response();
        $request_response['method'] = $method ?? 'GET';
        $request_response['log_file'] = $params['log_file'];
        $request_response['monitoring_record'] = $monitoring_record;

        self::_logf(
            self::_LOG_METHOD_NOTICE,
            'Sending '.($method ?? 'GET').' request to '.$url.'.',
            $params['log_file']
        );

        $obfuscated_params = $curl_params;
        if (!empty($obfuscated_params['userpass'])) {
            $obfuscated_params['userpass'] = '(Obfuscated_credentials)';
        }
        if (!empty($obfuscated_params['header_keys_arr'])
            && self::array_key_exists_insensitive($obfuscated_params['header_keys_arr'], 'authorization')) {
            $obfuscated_params['header_keys_arr'] = self::array_replace_value_key_insensitive(
                $obfuscated_params['header_keys_arr'], 'authorization', '(Obfuscated_authorization)'
            );
        }

        if (!($api_response = PHS_Utils::quick_curl($url, $curl_params))
            || empty($api_response['request_details']) || !is_array($api_response['request_details'])
        ) {
            $error_msg = 'Error initiating API call: ('.($api_response['request_error_no'] ?? -1).') '
                         .($api_response['request_error_msg'] ?? 'Unknown error.');

            $request_response['has_error'] = true;
            $request_response['error_msg'] = $error_msg;
            $request_response['http_code'] = $api_response['http_code'] ?? 0;

            self::_logf(self::_LOG_METHOD_ERROR, $error_msg, $params['log_file']);

            ob_start();
            var_dump($obfuscated_params);
            $request_params = @ob_get_clean();

            self::_logf(
                self::_LOG_METHOD_INFO,
                'API URL: '.$url."\n"
                .'Params: '.$request_params,
                $params['log_file']
            );

            if ($monitoring_record
               && ($new_record = PHS_Model_Api_monitor::api_outgoing_request_error($monitoring_record, null, $error_msg))) {
                $request_response['monitoring_record'] = $new_record;
            }

            return $request_response;
        }

        $http_code = (int)($api_response['request_details']['http_code'] ?? 0);

        $request_response['http_code'] = $http_code;
        $request_response['response_buf'] = $api_response['response'] ?? '';
        $request_response['response_json'] = [];
        $request_response['response_curl'] = $api_response;

        if (empty($http_code)
            || !in_array($http_code, $params['success_codes'], true)) {
            $error_msg = 'API responded with HTTP code: '.$http_code.' (expected: '.implode(',', $params['success_codes']).')';

            $request_response['has_error'] = true;
            $request_response['error_msg'] = $error_msg;

            self::_logf(self::_LOG_METHOD_ERROR, $error_msg, $params['log_file']);

            $request_headers = $api_response['request_details']['request_header'] ?? 'N/A';
            $request_params = $api_response['request_details']['request_params'] ?? 'N/A';

            self::_logf(
                self::_LOG_METHOD_INFO,
                'API URL: '.$url."\n"
                .'Request headers:'."\n".$request_headers."\n"
                .'Params: '.$request_params,
                $params['log_file']
            );

            if ($monitoring_record
               && ($new_record = PHS_Model_Api_monitor::api_outgoing_request_error(
                   $monitoring_record, $http_code, $error_msg, $request_response['response_buf'] ?: null))
            ) {
                $request_response['monitoring_record'] = $new_record;
            }

            return $request_response;
        }

        if (!empty($params['expect_json'])) {
            $error_msg = null;
            $json_response = [];
            if (empty($request_response['response_buf'])) {
                $error_msg = 'API response body is empty.';
            } elseif (!($json_response = @json_decode($request_response['response_buf'], true))) {
                $error_msg = 'Error decoding response body as JSON.';
            }

            if (!empty($error_msg)) {
                $request_response['has_error'] = true;
                $request_response['error_msg'] = $error_msg;

                self::_logf(self::_LOG_METHOD_ERROR, $error_msg, $params['log_file']);

                $request_headers = $api_response['request_details']['request_header'] ?? 'N/A';
                $request_params = $api_response['request_details']['request_params'] ?? 'N/A';

                self::_logf(
                    self::_LOG_METHOD_INFO,
                    'API URL: '.$url."\n"
                    .'Request headers: '.$request_headers."\n"
                    .'Params: '.$request_params
                    .'API response: '.$request_response['response_buf'],
                    $params['log_file']
                );

                if ($monitoring_record
                   && ($new_record = PHS_Model_Api_monitor::api_outgoing_request_error(
                       $monitoring_record, $http_code, $error_msg, $request_response['response_buf'] ?: null))
                ) {
                    $request_response['monitoring_record'] = $new_record;
                }

                return $request_response;
            }

            $request_response['response_json'] = $json_response;
        }

        if ($monitoring_record
           && ($new_record = PHS_Model_Api_monitor::api_outgoing_request_success(
               $monitoring_record, $http_code, $request_response['response_buf'] ?: null))
        ) {
            $request_response['monitoring_record'] = $new_record;
        }

        self::_logf(
            self::_LOG_METHOD_NOTICE,
            'Success response for '.strtoupper($method ?? 'get').' request to '.$url.' with HTTP code '.$http_code.'.',
            $params['log_file']
        );

        return $request_response;
    }

    private function _update_request_for_result(array $request_arr, array $request_response) : ?array
    {
        $update_result = $request_response['has_error']
            ? $this->_requests_model->update_request_for_failure($request_arr, $request_response['method'], $request_response['http_code'], $request_response['response_buf'], $request_response['error_msg'])
            : $this->_requests_model->update_request_for_success($request_arr, $request_response['method'], $request_response['http_code'], $request_response['response_buf'], $request_response['error_msg']);

        return $update_result ?: null;
    }

    private function _empty_request_response() : array
    {
        return [
            'in_background'     => false,
            'method'            => '',
            'http_code'         => 0,
            'has_error'         => false,
            'error_msg'         => '',
            'log_file'          => null,
            'request_data'      => null,
            'request_run_data'  => null,
            'response_buf'      => null,
            'response_json'     => null,
            'response_curl'     => null,
            'monitoring_record' => null,
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

    private static function _logf(string $method, string $msg, ?string $log_channel = null) : void
    {
        if (!@method_exists(PHS_Logger::class, $method)) {
            return;
        }

        PHS_Logger::$method($msg, PHS_Logger::TYPE_HTTP_CALLS);
        if ($log_channel) {
            PHS_Logger::$method($msg, $log_channel);
        }
    }
}
