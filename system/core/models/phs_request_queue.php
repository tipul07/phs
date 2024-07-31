<?php

namespace phs\system\core\models;

use phs\PHS_Crypt;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Instantiable;
use phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Request_queue extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const FORCE_RUNNING_SECONDS = 900; // cand be forced after 15 mins

    public const STATUS_PENDING = 1, STATUS_RUNNING = 2, STATUS_FAILED = 3, STATUS_PAUSED = 4, STATUS_SUCCESS = 5;

    protected static array $STATUSES_ARR = [
        self::STATUS_PENDING => ['title' => 'Pending'],
        self::STATUS_RUNNING => ['title' => 'Running'],
        self::STATUS_FAILED  => ['title' => 'Failed'],
        self::STATUS_PAUSED  => ['title' => 'Paused'],
        self::STATUS_SUCCESS => ['title' => 'Success'],
    ];

    public function get_model_version() : string
    {
        return '1.0.6';
    }

    public function get_table_names() : array
    {
        return ['phs_request_queue', 'phs_request_queue_runs'];
    }

    public function get_main_table_name() : string
    {
        return 'phs_request_queue';
    }

    public function is_pending(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_PENDING;
    }

    public function is_running(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_RUNNING;
    }

    public function is_failed(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_FAILED;
    }

    public function is_paused(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_PAUSED;
    }

    public function is_success(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_SUCCESS;
    }

    public function is_final(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && !empty($record_arr['is_final']);
    }

    public function is_timed(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && !empty($record_arr['run_after']);
    }

    public function should_delete_on_completion(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && ($settings_arr = $this->get_request_full_settings($record_arr))
               && !empty($settings_arr['delete_on_completion']);
    }

    public function can_run_request(int | array $requet_data, bool $forced = false) : bool
    {
        return !empty($requet_data)
               && ($request_arr = $this->data_to_array($requet_data))
               && ($this->is_pending($request_arr) || $this->is_failed($request_arr))
               && ($forced || $request_arr['max_retries'] > $request_arr['fails']);
    }

    public function can_be_forced(int | array $requet_data) : bool
    {
        return !empty($requet_data)
               && ($request_arr = $this->data_to_array($requet_data))
               && !$this->is_paused($request_arr)
               && (!$this->is_running($request_arr)
                   || seconds_passed($request_arr['status_date']) > self::FORCE_RUNNING_SECONDS);
    }

    public function act_pause(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('HTTP call details not found in database.'));

            return null;
        }

        if ($this->is_paused($record_arr)) {
            return $record_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']);
        $edit_params['fields'] = [
            'status'     => self::STATUS_PAUSED,
            'old_status' => $record_arr['status'],
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Error saving HTTP call details in database.'));

            return null;
        }

        return $new_record;
    }

    public function act_unpause(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('HTTP call details not found in database.'));

            return null;
        }

        if (!$this->is_paused($record_arr)) {
            return $record_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']);
        $edit_params['fields'] = [
            'status'     => $record_arr['old_status'],
            'old_status' => 0,
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Error saving HTTP call details in database.'));

            return null;
        }

        return $new_record;
    }

    public function create_request(
        string $url,
        string $method = 'get',
        null | array | string $payload = null,
        ?array $settings = null,
        int $max_retries = 1,
        ?string $handle = null,
        ?string $run_after = null,
    ) : ?array {
        $this->reset_error();

        if (!PHS_Params::check_type($url, PHS_Params::T_URL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid URL for the HTTP call.'));

            return null;
        }

        if (!($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error loading required resources.'));

            return null;
        }

        $request_params = $flow_arr;
        $request_params['fields'] = [];
        $request_params['fields']['url'] = $url;
        $request_params['fields']['payload'] = $this->_encode_payload($payload);
        $request_params['fields']['method'] = $method ?: null;
        $request_params['fields']['max_retries'] = $max_retries;
        $request_params['fields']['fails'] = 0;
        $request_params['fields']['last_error'] = null;
        $request_params['fields']['handle'] = $handle ?: null;
        $request_params['fields']['settings'] = $this->validate_settings_arr($settings) ?: null;
        $request_params['fields']['status'] = self::STATUS_PENDING;
        $request_params['fields']['run_after'] = $run_after ?: null;

        if (!($new_record = $this->insert($request_params))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error saving HTTP call details in database.'));

            return null;
        }

        return $new_record;
    }

    public function start_request(
        int | array $request_data,
    ) : ?array {
        $this->reset_error();

        if (empty($request_data)
            || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))
            || !($request_arr = $this->data_to_array($request_data, $flow_arr))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('HTTP call not found in database.'));

            return null;
        }

        $request_params = $flow_arr;
        $request_params['fields'] = [];
        $request_params['fields']['last_error'] = null;
        $request_params['fields']['last_run'] = date(self::DATETIME_DB);
        $request_params['fields']['status'] = self::STATUS_RUNNING;

        if (!($new_record = $this->edit($request_arr, $request_params))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error saving HTTP call details in database.'));

            return null;
        }

        return $new_record;
    }

    public function update_request_for_success(
        int | array $request_data,
        ?string $method = null,
        ?int $http_code = null,
        null | bool | string $response = false,
        null | bool | string $error = false,
    ) : ?array {
        return $this->_update_request($request_data, $method, $http_code, self::STATUS_SUCCESS, $response, $error);
    }

    public function update_request_for_failure(
        int | array $request_data,
        ?string $method = null,
        ?int $http_code = null,
        null | bool | string $response = false,
        null | bool | string $error = false,
    ) : ?array {
        return $this->_update_request($request_data, $method, $http_code, self::STATUS_FAILED, $response, $error);
    }

    public function hard_delete_http_call(int | array $record_data) : ?array
    {
        $this->reset_error();

        if ( !($queue_flow = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))
            || !($runs_flow = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue_runs']))
            || !($queue_run_table = $this->get_flow_table_name($runs_flow))) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($record_data)
            || !($record_arr = $this->data_to_array($record_data, $queue_flow))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Request details not found in database.'));

            return null;
        }

        if (!db_query('DELETE FROM `'.$queue_run_table.'` WHERE request_id = \''.$record_arr['id'].'\'', $runs_flow['db_connection'])) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error deleting HTTP call runs records.'));

            return null;
        }

        if (!$this->hard_delete($record_arr, $queue_flow)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error deleting HTTP call record.'));

            return null;
        }

        return $record_arr;
    }

    public function update_payload(int | array $record_data, ?string $payload) : ?array
    {
        $this->reset_error();

        if (empty($record_data)
            || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Request details not found in database.'));

            return null;
        }

        if ( !empty($payload)
            && ($json_arr = @json_decode($payload, true)) ) {
            $payload = @json_encode($json_arr);
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']);
        $edit_params['fields'] = [
            'payload' => $payload,
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Error saving HTTP call details in database.'));

            return null;
        }

        return $new_record;
    }

    public function empty_request_settings_arr() : array
    {
        return [
            'timeout'              => 30,
            'log_file'             => null,
            'delete_on_completion' => true,
            'expect_json_response' => false,
            'success_codes'        => [],
            'curl_params'          => [],
            'headers'              => [],
            'auth_basic'           => [],
            'auth_bearer'          => [],
            'success_callback'     => null,
            'one_fail_callback'    => null,
            'fail_callback'        => null,
        ];
    }

    public function obfuscate_minimum_settings(int | array $record_data) : ?array
    {
        if ( null === ($settings_arr = $this->get_request_minimum_settings($record_data)) ) {
            return null;
        }

        if (!empty($settings_arr['auth_basic']['pass'])
            && is_string($settings_arr['auth_basic']['pass'])) {
            $settings_arr['auth_basic']['pass'] = '(undisclosed_password)';
        }

        if (!empty($settings_arr['auth_bearer']['token'])
            && is_string($settings_arr['auth_bearer']['token'])) {
            $settings_arr['auth_bearer']['token'] = '(undisclosed_token)';
        }

        return $settings_arr;
    }

    public function validate_settings_arr(?array $settings_arr) : array
    {
        $settings_arr ??= [];

        $new_settings_arr = [];
        $settings_fields = $this->empty_request_settings_arr();
        foreach ($settings_fields as $field => $default_val) {
            if (array_key_exists($field, $settings_arr)
                && $settings_arr[$field] !== $default_val) {
                $new_settings_arr[$field] = $settings_arr[$field];
            }
        }

        if ( !empty($new_settings_arr['auth_basic']) ) {
            if ( !is_array($new_settings_arr['auth_basic']) ) {
                $new_settings_arr['auth_basic'] = [];
            }

            $new_settings_arr['auth_basic']['pass'] = ($new_settings_arr['auth_basic']['pass'] ?? '');
            if ( !isset($new_settings_arr['auth_basic']['user'])
                 || !is_string($new_settings_arr['auth_basic']['user'])
                 || !is_string($new_settings_arr['auth_basic']['pass'])) {
                unset($new_settings_arr['auth_basic']);
            }
        }

        if ( isset($settings_arr['auth_bearer'])
             && (empty($settings_arr['auth_bearer']['token']) || !is_string($settings_arr['auth_bearer']['token'])) ) {
            unset($settings_arr['auth_bearer']);
        }

        if ( empty($new_settings_arr['success_codes'])
             || !is_array($new_settings_arr['success_codes']) ) {
            $new_settings_arr['success_codes'] = [200];
        }

        $new_settings_arr['success_codes'] = self::extract_integers_from_array($new_settings_arr['success_codes']);

        return $new_settings_arr;
    }

    public function check_settings_for_errors(array $settings_arr) : ?array
    {
        if (empty($settings_arr)) {
            return [];
        }

        if (!empty($settings_arr['log_file'])
            && !PHS_Logger::define_channel($settings_arr['log_file'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid log file provided for HTTP call settings.'));

            return null;
        }

        if ( isset($settings_arr['delete_on_completion']) ) {
            $settings_arr['delete_on_completion'] = !empty($settings_arr['delete_on_completion']);
        }
        if ( isset($settings_arr['expect_json_response']) ) {
            $settings_arr['expect_json_response'] = !empty($settings_arr['expect_json_response']);
        }

        if ( !empty($settings_arr['success_codes'])
             && !is_array($settings_arr['success_codes']) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid success codes provided for HTTP call settings.'));

            return null;
        }

        if ( !empty($settings_arr['curl_params'])
             && !is_array($settings_arr['curl_params']) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid cURL parameters provided for HTTP call settings.'));

            return null;
        }

        if ( !empty($settings_arr['headers'])
             && !is_array($settings_arr['headers']) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid headers provided for HTTP call settings.'));

            return null;
        }

        if ( !empty($settings_arr['auth_basic'])
             && (!is_array($settings_arr['auth_basic'])
                 || !isset($settings_arr['auth_basic']['user']) || !is_string($settings_arr['auth_basic']['user'])
             ) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid basic authentication provided for HTTP call settings.'));

            return null;
        }

        if ( !empty($settings_arr['auth_bearer'])
             && (!is_array($settings_arr['auth_bearer'])
                 || !isset($settings_arr['auth_bearer']['token']) || !is_string($settings_arr['auth_bearer']['token'])
             ) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid bearer authentication provided for HTTP call settings.'));

            return null;
        }

        if ( !empty($settings_arr['success_callback']) ) {
            if ( !($callback = $this->validate_request_callback($settings_arr['success_callback'])) ) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid success callback provided for HTTP call settings.'));

                return null;
            }

            $settings_arr['success_callback'] = $callback;
        }

        if ( !empty($settings_arr['one_fail_callback']) ) {
            if ( !($callback = $this->validate_request_callback($settings_arr['one_fail_callback'])) ) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid one failure callback provided for HTTP call settings.'));

                return null;
            }

            $settings_arr['one_fail_callback'] = $callback;
        }

        if ( !empty($settings_arr['fail_callback']) ) {
            if ( !($callback = $this->validate_request_callback($settings_arr['fail_callback'])) ) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid final failure callback provided for HTTP call settings.'));

                return null;
            }

            $settings_arr['fail_callback'] = $callback;
        }

        return $settings_arr;
    }

    public function validate_request_callback(array | string $callback) : null | string | array
    {
        if (is_string($callback)) {
            if (!is_callable($callback)) {
                return null;
            }

            return $callback;
        }

        $class = $callback[0] ?? null;
        $method = $callback[1] ?? null;

        if (empty($class) || empty($method)
            || (!is_string($class) && !is_object($class))
            || !is_string($method) ) {
            return null;
        }

        $classname = null;
        if (is_string($class)) {
            $classname = $class;
        } elseif (@is_object($class)) {
            $classname = $class::class;
        }

        if (empty($classname) || !is_string($classname)) {
            return null;
        }

        if (!($callback_obj = $classname::get_instance())
            || !($callback_obj instanceof PHS_Instantiable)
            || !@method_exists($callback_obj, $method)) {
            return null;
        }

        if (!($plugin_obj = $callback_obj->get_plugin_instance())
            || !$plugin_obj->plugin_active()) {
            return null;
        }

        return [$classname, $method];
    }

    public function get_request_success_callback($request_data) : null | string | array
    {
        return ($settings_arr = $this->get_request_minimum_settings($request_data))
               && !empty($settings_arr['success_callback'])
            ? $this->_instantiate_callback($settings_arr['success_callback'])
            : null;
    }

    public function get_request_one_fail_callback($request_data) : null | string | array
    {
        return ($settings_arr = $this->get_request_minimum_settings($request_data))
               && !empty($settings_arr['one_fail_callback'])
            ? $this->_instantiate_callback($settings_arr['one_fail_callback'])
            : null;
    }

    public function get_request_fail_callback($request_data) : null | string | array
    {
        return ($settings_arr = $this->get_request_minimum_settings($request_data))
               && !empty($settings_arr['fail_callback'])
            ? $this->_instantiate_callback($settings_arr['fail_callback'])
            : null;
    }

    public function get_request_minimum_settings(int | array $request_data) : ?array
    {
        $this->reset_error();

        if (empty($request_data)
            || !($request_arr = $this->data_to_array($request_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid request.'));

            return null;
        }

        if (empty($request_arr['settings'])
            || !($settings_arr = $this->_decode_settings_field($request_arr['settings']))
            || !is_array($settings_arr)) {
            $settings_arr = [];
        }

        return $this->validate_settings_arr($settings_arr);
    }

    public function get_request_full_settings(int | array $request_data) : ?array
    {
        $this->reset_error();

        if (empty($request_data) || !($request_arr = $this->data_to_array($request_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid request.'));

            return null;
        }

        $default_settings = $this->empty_request_settings_arr();
        if ( !($settings_arr = $this->get_request_minimum_settings($request_arr)) ) {
            return $default_settings;
        }

        foreach ($default_settings as $field => $def_value) {
            if ( array_key_exists($field, $settings_arr) ) {
                $default_settings[$field] = $settings_arr[$field];
            }
        }

        return $default_settings;
    }

    public function get_request_runs(int | array $request_data) : ?array
    {
        $this->reset_error();

        if (empty($request_data)
           || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))
           || !($run_flow = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue_runs']))
           || !($request_arr = $this->data_to_array($request_data, $flow_arr))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('HTTP call not found in database.'));

            return null;
        }

        $list_arr = $run_flow;
        $list_arr['fields'] = [];
        $list_arr['fields']['request_id'] = $request_arr['id'];

        if ( !($result_arr = $this->get_list($list_arr)) ) {
            return [];
        }

        return $result_arr;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'phs_request_queue':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'url' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                    ],
                    'payload' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'method' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 50,
                    ],
                    'max_retries' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'fails' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'last_error' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'handle' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                    ],
                    'settings' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'is_final' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'old_status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_edit' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_run' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'run_after' => [
                        'type'    => self::FTYPE_DATETIME,
                        'index'   => true,
                        'comment' => 'Run this request after this date',
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;

            case 'phs_request_queue_runs':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'request_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'http_code' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'response' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'error' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_phs_request_queue($params) : ?array
    {
        if (empty($params) || !is_array($params)) {
            return null;
        }

        if (empty($params['fields']['url'])
            || !PHS_Params::check_type($params['fields']['url'], PHS_Params::T_URL)) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid URL for the request.'));

            return null;
        }

        if (isset($params['fields']['status'])
            && !$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid status for the request.'));

            return null;
        }

        $params['fields']['handle'] = ($params['fields']['handle'] ?? null);

        if (!empty($params['fields']['handle'])
            && $this->get_details_fields(['handle' => $params['fields']['handle'], ]) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('There is already a request in database with provided handle.'));

            return null;
        }

        $params['fields']['settings'] = ($params['fields']['settings'] ?? null) ?: null;
        if (!empty($params['fields']['settings'])) {
            if ((is_string($params['fields']['settings'])
                && null === ($params['fields']['settings'] = $this->_decode_settings_field($params['fields']['settings'])))
               || !is_array($params['fields']['settings'])) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid settings provided for the request.'));

                return null;
            }

            if ( null === ($validated_settings = $this->check_settings_for_errors($params['fields']['settings'])) ) {
                return null;
            }

            $params['fields']['settings'] = $this->_encode_settings_field($this->validate_settings_arr($validated_settings) ?: null) ?: null;
        }

        $now_date = date(self::DATETIME_DB);

        if (!empty($params['fields']['run_after'])
           && ($run_after_time = parse_db_date($params['fields']['run_after'])) ) {
            $params['fields']['run_after'] = date(self::DATETIME_DB, $run_after_time);
        } else {
            $params['fields']['run_after'] = null;
        }

        $params['fields']['payload'] = ($params['fields']['payload'] ?? null) ?: null;
        $params['fields']['method'] = ($params['fields']['method'] ?? null) ?: 'get';
        $params['fields']['max_retries'] = (int)($params['fields']['max_retries'] ?? 1);
        $params['fields']['fails'] = 0;
        $params['fields']['last_error'] = ($params['fields']['last_error'] ?? null) ?: null;
        $params['fields']['status'] ??= self::STATUS_PENDING;
        $params['fields']['cdate'] ??= $now_date;
        $params['fields']['last_edit'] ??= $now_date;
        $params['fields']['status_date'] ??= $now_date;

        return $params;
    }

    protected function get_edit_prepare_params_phs_request_queue($existing_data, $params) : ?array
    {
        if (empty($params) || !is_array($params)) {
            return null;
        }

        if (array_key_exists('url', $params['fields'])
            && !empty($params['fields']['url'])
            && !PHS_Params::check_type($params['fields']['url'], PHS_Params::T_URL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid URL for the request.'));

            return null;
        }

        $now_date = date(self::DATETIME_DB);

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a valid status for data retention policy.'));

                return null;
            }

            $params['fields']['status_date'] = $now_date;
        }

        if (!empty($params['fields']['settings'])) {
            if ((is_string($params['fields']['settings'])
                && null === ($params['fields']['settings'] = $this->_decode_settings_field($params['fields']['settings'])))
               || !is_array($params['fields']['settings'])) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid settings provided for the request.'));

                return null;
            }

            if ( null === ($validated_settings = $this->check_settings_for_errors($params['fields']['settings'])) ) {
                return null;
            }

            $params['fields']['settings'] = $this->_encode_settings_field($this->validate_settings_arr($validated_settings) ?: null) ?: null;
        }

        $handle = $params['fields']['handle'] ?? $existing_data['handle'] ?? null;
        if (!empty($handle)
            && $this->get_details_fields([
                'handle' => $handle,
                'id'     => ['check' => '!=', 'value' => $existing_data['id']],
            ]) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('There is already a request in database with provided handle.'));

            return null;
        }

        $params['fields']['last_edit'] ??= $now_date;

        return $params;
    }

    /**
     * Call this method when we have a response from the 3rd party (success or fail)
     *
     * @param int|array $request_data
     * @param null|int $http_code
     * @param null|int $status
     * @param null|bool|string $response
     * @param null|bool|string $error
     * @param ?string $method
     *
     * @return null|array
     */
    private function _update_request(
        int | array $request_data,
        ?string $method = null,
        ?int $http_code = null,
        ?int $status = null,
        null | bool | string $response = false,
        null | bool | string $error = false,
    ) : ?array {
        $this->reset_error();

        if (empty($request_data)
            || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))
            || !($request_arr = $this->data_to_array($request_data, $flow_arr))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request record not found in database.'));

            return null;
        }

        $now_date = date(self::DATETIME_DB);

        $edit_params = $flow_arr;
        $edit_params['fields'] = [];
        if ( $method !== null) {
            $edit_params['fields']['method'] = $method;
        }
        if ( $status !== null
            && $this->valid_status($status)) {
            $edit_params['fields']['status'] = $status;
        }

        if ($error !== false) {
            $edit_params['fields']['last_error'] = $error;
            if (empty($edit_params['fields']['status'])) {
                $edit_params['fields']['status'] = self::STATUS_FAILED;
            }
        }

        if (!empty($edit_params['fields']['status'])) {
            if ($edit_params['fields']['status'] === self::STATUS_SUCCESS) {
                $http_code = $http_code ?: 200;
                $error = null;
                $edit_params['fields']['is_final'] = 1;
            } elseif ($edit_params['fields']['status'] === self::STATUS_FAILED) {
                $http_code ??= 0;
                $edit_params['fields']['fails'] = ['raw_field' => true, 'value' => 'fails + 1'];
                if ( $request_arr['max_retries'] <= $request_arr['fails'] + 1 ) {
                    $edit_params['fields']['is_final'] = 1;
                }
            }
        }

        $edit_params['fields']['last_edit'] = $now_date;

        if ( !($request_run_arr = $this->_request_run_result(
            $request_arr,
            $http_code ?: 0,
            $response ?: null,
            $error ?: null,
            $edit_params['fields']['status'] ?? null,
        )) ) {
            return null;
        }

        if (!($new_record = $this->edit($request_arr, $edit_params))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error saving data rentention run details in database.'));

            return null;
        }

        return [
            'request_data'     => $new_record,
            'request_run_data' => $request_run_arr,
        ];
    }

    private function _request_run_result(
        int | array $request_data,
        int $http_code = -1,
        ?string $response = null,
        ?string $error = null,
        ?int $status = null,
    ) : ?array {
        $this->reset_error();

        if (empty($request_data)
            || !($request_arr = $this->data_to_array($request_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid request.'));

            return null;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue_runs']);
        $edit_params['fields'] = [
            'request_id' => $request_arr['id'],
            'http_code'  => $http_code,
            'response'   => $response,
            'error'      => $error,
            'cdate'      => date(self::DATETIME_DB),
        ];

        if ($status !== null) {
            $edit_params['fields']['status'] = $status;
        }

        if (!($new_record = $this->insert($edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error saving request run details in database.'));

            return null;
        }

        return $new_record;
    }

    private function _instantiate_callback(null | string | array $callback) : null | string | array
    {
        $this->reset_error();

        if (empty($callback)) {
            return null;
        }

        if (is_string($callback)) {
            if ( !@function_exists($callback) ) {
                $this->set_error(self::ERR_PARAMETERS,
                    self::_t('Function for provided callback doesn\'t exist: %s.', $callback.'()'));

                return null;
            }

            return $callback;
        }

        $class = $callback[0] ?? null;
        $method = $callback[1] ?? null;

        if (empty($class) || empty($method)
            || !is_string($class)
            || !is_string($method)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid callback structure.'));

            return null;
        }

        if (!($callback_obj = $class::get_instance())
            || !($callback_obj instanceof PHS_Instantiable)
            || !@method_exists($callback_obj, $method)) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Method for provided callback doesn\'t exist %s.', $class.'::'.$method.'()'));

            return null;
        }

        if (!($plugin_obj = $callback_obj->get_plugin_instance())
            || !$plugin_obj->plugin_active()) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Plugin for provided callback is not active %s.', $class.'::'.$method.'()'));

            return null;
        }

        return [$callback_obj, $method];
    }

    private function _decode_settings_field(?string $settings) : ?array
    {
        if (empty($settings)) {
            return [];
        }

        if (!($decoded_settings = @json_decode($settings, true))) {
            return null;
        }

        if (!empty($decoded_settings['auth_basic']['pass'])
           && is_string($decoded_settings['auth_basic']['pass'])) {
            $decoded_settings['auth_basic']['pass'] = PHS_Crypt::quick_decode($decoded_settings['auth_basic']['pass']);
        }

        if (!empty($decoded_settings['auth_bearer']['token'])
           && is_string($decoded_settings['auth_bearer']['token'])) {
            $decoded_settings['auth_bearer']['token'] = PHS_Crypt::quick_decode($decoded_settings['auth_bearer']['token']);
        }

        return $decoded_settings;
    }

    private function _encode_settings_field(null | string | array $settings) : ?string
    {
        if (empty($settings)) {
            return '';
        }

        if (is_string($settings)) {
            if (!($decoded_settings = @json_decode($settings, true))) {
                return null;
            }

            $settings = $decoded_settings;
        }

        if (!empty($settings['auth_basic']['pass'])
           && is_string($settings['auth_basic']['pass'])) {
            $settings['auth_basic']['pass'] = PHS_Crypt::quick_encode($settings['auth_basic']['pass']);
        }

        if (!empty($settings['auth_bearer']['token'])
           && is_string($settings['auth_bearer']['token'])) {
            $settings['auth_bearer']['token'] = PHS_Crypt::quick_encode($settings['auth_bearer']['token']);
        }

        return @json_encode($settings) ?: null;
    }

    private function _encode_payload(null | string | array $payload) : ?string
    {
        if (empty($payload)) {
            return null;
        }

        if (is_string($payload)) {
            if (!($decoded_payload = @json_decode($payload, true))) {
                return null;
            }

            $payload = $decoded_payload;
        }

        return @json_encode($payload) ?: null;
    }

    private function _not_used_only_for_translation() : void
    {
        $this->_pt('Pending');
        $this->_pt('Running');
        $this->_pt('Failed');
        $this->_pt('Paused');
        $this->_pt('Success');
    }
}
