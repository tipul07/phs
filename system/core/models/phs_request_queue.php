<?php

namespace phs\system\core\models;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Request_queue extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const STATUS_PENDING = 1, STATUS_RUNNING = 2, STATUS_FAILED = 3, STATUS_SUCCESS = 4, STATUS_DELETED = 5;

    protected static array $STATUSES_ARR = [
        self::STATUS_PENDING => ['title' => 'Pending'],
        self::STATUS_RUNNING => ['title' => 'Running'],
        self::STATUS_FAILED  => ['title' => 'Failed'],
        self::STATUS_SUCCESS => ['title' => 'Success'],
        self::STATUS_DELETED => ['title' => 'Deleted'],
    ];

    public function get_model_version() : string
    {
        return '1.0.1';
    }

    public function get_table_names() : array
    {
        return ['phs_request_queue', 'phs_request_queue_runs'];
    }

    public function get_main_table_name() : string
    {
        return 'phs_request_queue';
    }

    public function can_run_request(int | array $requet_data, bool $forced = false) : bool
    {
        return !empty($requet_data)
               && ($request_arr = $this->data_to_array($requet_data))
               && ($this->is_pending($request_arr) || $this->is_failed($request_arr))
               && ($forced || $request_arr['max_retries'] > $request_arr['fails']);
    }

    public function create_request(
        string $url,
        string $method = 'get',
        ?string $payload = null,
        ?array $settings = null,
        int $max_retries = 1,
        ?string $handle = null,
        ?string $run_after = null,
    ) : ?array {
        $this->reset_error();

        if (!PHS_Params::check_type($url, PHS_Params::T_URL)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid URL for the request.'));

            return null;
        }

        if (!($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error loading required resources.'));

            return null;
        }

        $request_params = $flow_arr;
        $request_params['fields'] = [];
        $request_params['fields']['url'] = $url;
        $request_params['fields']['payload'] = $payload;
        $request_params['fields']['method'] = $method ?: null;
        $request_params['fields']['max_retries'] = $max_retries;
        $request_params['fields']['fails'] = 0;
        $request_params['fields']['last_error'] = null;
        $request_params['fields']['handle'] = $handle ?: null;
        $request_params['fields']['settings'] = $this->validate_settings_arr($settings) ?: null;
        $request_params['fields']['status'] = self::STATUS_PENDING;
        $request_params['fields']['run_after'] = $run_after ?: null;

        if (!($new_record = $this->insert($request_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error saving request details in database.'));

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
            || !($request_arr = $this->data_to_array($request_data, $flow_arr))
            || $this->is_deleted($request_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request record not found in database.'));

            return null;
        }

        $request_params = $flow_arr;
        $request_params['fields'] = [];
        $request_params['fields']['last_error'] = null;
        $request_params['fields']['status'] = self::STATUS_RUNNING;

        if (!($new_record = $this->edit($request_arr, $request_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error saving request details in database.'));

            return null;
        }

        return $new_record;
    }

    /**
     * Call this method when we have a response from the 3rd party (success or fail)
     * @param int|array $request_data
     * @param null|int $http_code
     * @param null|bool|string $response
     * @param null|bool|string $error
     *
     * @return null|array
     */
    public function update_request(
        int | array $request_data,
        ?int $http_code = null,
        null | bool | string $response = false,
        null | bool | string $error = false,
    ) : ?array {
        $this->reset_error();

        if (empty($request_data)
            || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']))
            || !($request_arr = $this->data_to_array($request_data, $flow_arr))
            || $this->is_deleted($request_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Request record not found in database.'));

            return null;
        }

        $settings_arr = $this->get_request_settings($request_arr) ?: $this->default_settings_arr();

        $now_date = date(self::DATETIME_DB);

        $edit_params = $flow_arr;
        $edit_params['fields'] = [];
        if ( $http_code !== null
            && !empty($settings_arr['success_codes']) && is_array($settings_arr['success_codes'])) {
            if (in_array($http_code, $settings_arr['success_codes'], true)) {
                $http_code ??= array_shift($settings_arr['success_codes']) ?: 200;
                $edit_params['fields']['status'] = self::STATUS_SUCCESS;
            } else {
                $http_code ??= 500;
                $edit_params['fields']['status'] = self::STATUS_FAILED;
            }
        }

        if (!empty($edit_params['fields']['status'])) {
            if ($edit_params['fields']['status'] === self::STATUS_SUCCESS) {
                $http_code = $http_code ?: 200;
                $error = null;
            } elseif ($edit_params['fields']['status'] === self::STATUS_FAILED) {
                $http_code ??= 500;
                $edit_params['fields']['fails'] = ['raw_field' => true, 'value' => 'fails + 1'];
            }
        }

        if ($error !== false) {
            $edit_params['fields']['last_error'] = $error;
        }

        $edit_params['fields']['last_edit'] = $now_date;

        if ( !$this->_request_run_result($request_arr, $http_code ?: 0, $response ?: null, $error ?: null) ) {
            return null;
        }

        if (!($new_record = $this->edit($request_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error saving data rentention run details in database.'));

            return null;
        }

        return $new_record;
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

    public function is_success(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_SUCCESS;
    }

    public function is_deleted(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_DELETED;
    }

    public function act_delete(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Request details not found in database.'));

            return null;
        }

        if ($this->is_deleted($record_arr)) {
            return $record_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_request_queue']);
        $edit_params['fields'] = [
            'status' => self::STATUS_DELETED,
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function empty_request_settings_arr() : array
    {
        return [
            'timeout'              => 30,
            'log_file'             => null,
            'expect_json_response' => false,
            'success_codes'        => [],
            'headers'              => [],
            'auth_basic'           => [],
            'auth_bearer'          => [],
            'success_callback'     => null,
            'one_fail_callback'    => null,
            'fail_callback'        => null,
        ];
    }

    public function validate_settings_arr(array $settings_arr) : array
    {
        $new_settings_arr = [];
        $settings_fields = $this->empty_request_settings_arr();
        foreach ($settings_fields as $field => $default_val) {
            if (array_key_exists($field, $settings_arr)
                && $settings_arr[$field] !== $default_val) {
                $new_settings_arr[$field] = $settings_arr[$field];
            }
        }

        if ( !empty($new_settings_arr['auth_basic']) ) {
            if ( !isset($new_settings_arr['auth_basic']['user'])) {
                unset($new_settings_arr['auth_basic']);
            } else {
                $new_settings_arr['auth_basic']['pass'] = ($new_settings_arr['auth_basic']['pass'] ?? '');
            }
        }

        if ( isset($settings_arr['auth_bearer'])
            && empty($settings_arr['auth_bearer']['token']) ) {
            unset($settings_arr['auth_bearer']);
        }

        if ( empty($new_settings_arr['success_codes']) ) {
            $new_settings_arr['success_codes'] = [200];
        }

        $new_settings_arr['success_codes'] = self::extract_integers_from_array($new_settings_arr['success_codes']);

        if (!empty($new_settings_arr['log_file'])
           && !PHS_Logger::define_channel($new_settings_arr['log_file'])) {
            unset($new_settings_arr['log_file']);
        }

        return $new_settings_arr;
    }

    public function default_settings_arr() : array
    {
        return $this->validate_settings_arr($this->empty_request_settings_arr());
    }

    public function get_request_settings(int | array $request_data) : ?array
    {
        $this->reset_error();

        if (empty($request_data)
            || !($request_arr = $this->data_to_array($request_data))
            || $this->is_deleted($request_arr)) {
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
                    'deleted' => [
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
            && $this->get_details_fields([
                'handle' => $params['fields']['handle'],
                'status' => ['check' => '!=', 'value' => self::STATUS_DELETED],
            ]) ) {
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

            $params['fields']['settings'] = $this->_encode_settings_field($this->validate_settings_arr($params['fields']['settings']) ?: null) ?: null;
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

            if ($params['fields']['status'] === self::STATUS_DELETED) {
                $params['fields']['deleted'] = $now_date;
            }
        }

        if (!empty($params['fields']['settings'])) {
            if ((is_string($params['fields']['settings'])
                && null === ($params['fields']['settings'] = $this->_decode_settings_field($params['fields']['settings'])))
               || !is_array($params['fields']['settings'])) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid settings provided for the request.'));

                return null;
            }

            $params['fields']['settings'] = $this->_encode_settings_field($this->validate_settings_arr($params['fields']['settings']) ?: null) ?: null;
        }

        $handle = $params['fields']['handle'] ?? $existing_data['handle'] ?? null;
        if (!empty($handle)
            && $this->get_details_fields([
                'handle' => $handle,
                'id'     => ['check' => '!=', 'value' => $existing_data['id']],
                'status' => ['check' => '!=', 'value' => self::STATUS_DELETED],
            ]) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('There is already a request in database with provided handle.'));

            return null;
        }

        $params['fields']['last_edit'] ??= $now_date;

        return $params;
    }

    private function _request_run_result(
        int | array $request_data,
        int $http_code = -1,
        ?string $response = null,
        ?string $error = null,
    ) : ?array {
        $this->reset_error();

        if (empty($request_data)
            || !($request_arr = $this->data_to_array($request_data))
            || $this->is_deleted($request_arr)) {
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

        if (!($new_record = $this->insert($edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error saving request run details in database.'));

            return null;
        }

        return $new_record;
    }

    private function _decode_settings_field(?string $settings) : ?array
    {
        if (empty($settings)) {
            return [];
        }

        if (!($decoded_settings = @json_decode($settings, true))) {
            return null;
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

        return @json_encode($settings) ?: null;
    }
}
