<?php
namespace phs\system\core\models;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Api_monitor extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const STATUS_STARTED = 1, STATUS_SUCCESS = 2, STATUS_ERROR = 3;
    public const TYPE_INCOMING = 1, TYPE_OUTGOING = 2;

    protected static array $STATUSES_ARR = [
        self::STATUS_STARTED => ['title' => 'Started'],
        self::STATUS_SUCCESS => ['title' => 'Success'],
        self::STATUS_ERROR   => ['title' => 'Error'],
    ];

    protected static array $TYPES_ARR = [
        self::TYPE_INCOMING => ['title' => 'Incoming'],
        self::TYPE_OUTGOING => ['title' => 'Outgoing'],
    ];

    private static ?PHS_Plugin_Admin $_admin_plugin = null;

    private static ?PHS_Model_Api_monitor $_the_model = null;

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.2';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return ['api_monitor'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'api_monitor';
    }

    /**
     * @param false|string $lang
     *
     * @return array
     */
    public function get_types($lang = false) : array
    {
        static $types_arr = [];

        if (empty(self::$TYPES_ARR)) {
            return [];
        }

        if ($lang === false
            && !empty($types_arr)) {
            return $types_arr;
        }

        $result_arr = $this->translate_array_keys(self::$TYPES_ARR, ['title'], $lang);

        if ($lang === false) {
            $types_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param false|string $lang
     *
     * @return array
     */
    public function get_types_as_key_val($lang = false) : array
    {
        static $types_key_val_arr = null;

        if ($lang === false
            && $types_key_val_arr !== null) {
            return $types_key_val_arr;
        }

        $key_val_arr = [];
        if (($statuses = $this->get_types($lang))) {
            foreach ($statuses as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if ($lang === false) {
            $types_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param int|string $type
     * @param false|string $lang
     *
     * @return null|array
     */
    public function valid_type($type, $lang = false): ?array
    {
        $all_types = $this->get_types($lang);
        if (empty($type)
            || !isset($all_types[$type])) {
            return null;
        }

        return $all_types[$type];
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false)
    {
        // $params should be flow parameters...
        if (empty($params) || !is_array($params)
         || empty($params['table_name'])) {
            return false;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'api_monitor':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'account_id' => [
                        'type'    => self::FTYPE_INT,
                        'default' => 0,
                        'comment' => 'Request for an account',
                    ],
                    'method' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 50,
                        'default' => null,
                    ],
                    'internal_route' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'external_route' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'plugin' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'request_time' => [
                        'type'    => self::FTYPE_DATETIME,
                    ],
                    'request_body' => [
                        'type'    => self::FTYPE_MEDIUMTEXT,
                    ],
                    'response_time' => [
                        'type'    => self::FTYPE_DATETIME,
                    ],
                    'response_body' => [
                        'type'    => self::FTYPE_MEDIUMTEXT,
                    ],
                    'response_code' => [
                        'type'    => self::FTYPE_INT,
                        'default' => 0,
                        'comment' => 'HTTP response code',
                    ],
                    'error_message' => [
                        'type'    => self::FTYPE_TEXT,
                        'comment' => 'Error message (if available)',
                    ],
                    'type' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 0,
                        'index'   => true,
                        'comment' => 'Incoming/Outgoing',
                    ],
                    'status' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 0,
                        'index'   => true,
                    ],
                    'last_update' => [
                        'type'    => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    //region Incoming monitoring
    public static function api_incoming_request_started() : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return null;
        }

        $fields_arr = [];
        $fields_arr['method'] = $_SERVER['REQUEST_METHOD'] ?? null;
        $fields_arr['request_body'] = PHS_Api_base::get_php_input();
        $fields_arr['status'] = self::STATUS_STARTED;
        $fields_arr['type'] = self::TYPE_INCOMING;

        return self::_update_api_monitor_record($fields_arr);
    }

    public static function api_incoming_request_success(int $http_code = null, ?string $response_body = null, $force_record = null) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return null;
        }

        $fields_arr = [];
        $fields_arr['status'] = self::STATUS_SUCCESS;
        if($http_code !== null) {
            $fields_arr['response_code'] = $http_code;
        }
        if($response_body !== null) {
            $fields_arr['response_body'] = $response_body;
        }

        $record = $force_record ?? PHS_Api::incoming_monitoring_record() ?? null;

        return self::_update_api_monitor_record($fields_arr, $record);
    }

    public static function api_incoming_request_error(
        int $http_code = null, ?string $error_message = null, ?string $response_body = null, $force_record = null
    ) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return null;
        }

        $fields_arr = [];
        $fields_arr['status'] = self::STATUS_ERROR;
        if($http_code !== null) {
            $fields_arr['response_code'] = $http_code;
        }
        if( $error_message !== null ) {
            $fields_arr['error_message'] = $error_message;
        }
        if($response_body !== null) {
            $fields_arr['response_body'] = $response_body;
        }

        $record = $force_record ?? PHS_Api::incoming_monitoring_record() ?? null;

        return self::_update_api_monitor_record($fields_arr, $record);
    }

    public static function api_incoming_request_direct_error(
        int $http_code, ?string $error_message = null, ?string $response_body = null
    ) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return null;
        }

        if( !($monitor_record = self::api_incoming_request_started())
            || !($monitor_record = self::_update_api_monitor_record([
                'status' => self::STATUS_ERROR,
                'response_code' => $http_code,
                'error_message' => $error_message,
                'response_body' => $response_body
            ], $monitor_record)) ) {
            return null;
        }

        return $monitor_record;
    }

    /**
     * @param  array  $fields_arr
     * @param int|array|null $force_record
     *
     * @return null|array
     */
    public static function update_incoming_request_record( array $fields_arr, $force_record = null ): ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return null;
        }

        $copy_keys = ['account_id', 'method', 'internal_route', 'external_route', 'plugin',
                      'request_time', 'request_body', 'response_time', 'response_body',
                      'response_code', 'error_message', ];
        $new_fields_arr = [];
        foreach( $copy_keys as $key ) {
            if( !array_key_exists( $key, $fields_arr ) ) {
                continue;
            }

            $new_fields_arr[$key] = $fields_arr[$key];
        }

        if( empty($new_fields_arr) ) {
            return null;
        }

        $record = $force_record ?? PHS_Api::incoming_monitoring_record() ?? null;

        return self::_update_api_monitor_record($new_fields_arr, $record);
    }
    //endregion Incoming monitoring

    //region Outgoing monitoring
    public static function api_outgoing_request_started(string $url, ?string $request_body = null, string $method = 'GET') : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_outgoing_calls() ) {
            return null;
        }

        $fields_arr = [];
        $fields_arr['external_route'] = $url;
        $fields_arr['method'] = $method;
        $fields_arr['request_body'] = $request_body;
        $fields_arr['status'] = self::STATUS_STARTED;
        $fields_arr['type'] = self::TYPE_OUTGOING;

        return self::_update_api_monitor_record($fields_arr);
    }

    /**
     * @param null|int|array $outgoing_request
     * @param  int|null  $http_code
     * @param  null|string  $response_body
     *
     * @return null|array
     */
    public static function api_outgoing_request_success($outgoing_request, int $http_code = null, ?string $response_body = null) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_outgoing_calls() ) {
            return null;
        }

        $fields_arr = [];
        $fields_arr['status'] = self::STATUS_SUCCESS;
        if($http_code !== null) {
            $fields_arr['response_code'] = $http_code;
        }
        if($response_body !== null) {
            $fields_arr['response_body'] = $response_body;
        }

        return self::_update_api_monitor_record($fields_arr, $outgoing_request);
    }

    /**
     * @param null|int|array $outgoing_request
     * @param  int|null  $http_code
     * @param  null|string  $error_message
     * @param  null|string  $response_body
     *
     * @return null|array
     */
    public static function api_outgoing_request_error(
        $outgoing_request, int $http_code = null, ?string $error_message = null, ?string $response_body = null
    ) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_outgoing_calls() ) {
            return null;
        }

        $fields_arr = [];
        $fields_arr['status'] = self::STATUS_ERROR;
        if($http_code !== null) {
            $fields_arr['response_code'] = $http_code;
        }
        if( $error_message !== null ) {
            $fields_arr['error_message'] = $error_message;
        }
        if($response_body !== null) {
            $fields_arr['response_body'] = $response_body;
        }

        return self::_update_api_monitor_record($fields_arr, $outgoing_request);
    }

    /**
     * @param  string  $url
     * @param  null|string  $request_body
     * @param  null|string  $method
     * @param  int|null  $http_code
     * @param  null|string  $error_message
     * @param  null|string  $response_body
     *
     * @return null|array
     */
    public static function api_outgoing_request_direct_error(
        string $url, ?string $request_body = null, string $method = null,
        int $http_code = null, ?string $error_message = null, ?string $response_body = null
    ) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_outgoing_calls() ) {
            return null;
        }

        if($method === null) {
            if( empty( $response_body ) ) {
                $method = 'GET';
            } else {
                $method = 'POST';
            }
        }

        if( !($monitor_record = self::api_outgoing_request_started($url, $request_body, $method))
            || !($monitor_record = self::_update_api_monitor_record([
                'status' => self::STATUS_ERROR,
                'response_code' => $http_code,
                'error_message' => $error_message,
                'response_body' => $response_body
            ], $monitor_record)) ) {
            return null;
        }

        return $monitor_record;
    }

    /**
     * @param  array  $fields_arr
     * @param int|array $force_record
     *
     * @return null|array
     */
    public static function update_outgoing_request_record( array $fields_arr, $force_record ): ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return null;
        }

        if( !self::$_admin_plugin->monitor_api_outgoing_calls() ) {
            return null;
        }

        $copy_keys = ['account_id', 'method', 'internal_route', 'external_route', 'plugin',
                      'request_time', 'request_body', 'response_time', 'response_body',
                      'response_code', 'error_message', ];
        $new_fields_arr = [];
        foreach( $copy_keys as $key ) {
            if( !array_key_exists( $key, $fields_arr ) ) {
                continue;
            }

            $new_fields_arr[$key] = $fields_arr[$key];
        }

        if( empty($new_fields_arr) ) {
            return null;
        }

        return self::_update_api_monitor_record($new_fields_arr, $force_record);
    }
    //endregion Outgoing monitoring

    protected function get_insert_prepare_params_api_monitor($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['status'])
            || !$this->valid_status($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_STARTED;
        }

        $params['fields']['request_body'] ??= null;
        $params['fields']['response_body'] ??= null;
        $params['fields']['error_message'] ??= null;

        $str_fields = ['method' => 50, 'internal_route' => 255, 'external_route' => 255, 'plugin' => 255];
        foreach( $str_fields as $field => $max_len ) {
            if( empty( $params['fields'][$field] ) ) {
                $params['fields'][$field] = null;
            } else {
                $params['fields'][$field] = substr( $params['fields'][$field], 0, $max_len);
            }
        }

        $params['fields']['last_update'] = date(self::DATETIME_DB);

        if (empty($params['fields']['request_time'])) {
            $params['fields']['request_time'] = $params['fields']['last_update'];
        }

        return $params;
    }

    protected function get_edit_prepare_params_api_monitor($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['status'])
            && (empty($params['fields']['status'])
                || !$this->valid_status($params['fields']['status']))
        ) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a status for this API monitor record.'));

            return false;
        }

        $str_fields = ['method' => 50, 'internal_route' => 255, 'external_route' => 255, 'plugin' => 255];
        foreach( $str_fields as $field => $max_len ) {
            if( !array_key_exists( $field, $params['fields'] ) ) {
                continue;
            }

            if( empty( $params['fields'][$field] ) ) {
                $params['fields'][$field] = null;
            } else {
                $params['fields'][$field] = substr( $params['fields'][$field], 0, $max_len);
            }
        }

        $nullable_fields = ['request_body', 'response_body', 'error_message', ];
        foreach( $nullable_fields as $field ) {
            if( array_key_exists( $field, $params['fields'] )
                && $params['fields'][$field] === '' ) {
                $params['fields'][$field] = null;
            }
        }

        $params['fields']['last_update'] = date(self::DATETIME_DB);

        return $params;
    }

    /**
     * @param  array  $fields_arr
     * @param null|array $record_data
     *
     * @return null|array
     */
    private static function _update_api_monitor_record(array $fields_arr, ?array $record_data = null) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error loading required resources.' ) );
            return null;
        }

        $existing_record = null;
        if( !empty($record_data)
            && !($existing_record = self::$_the_model->data_to_array($record_data)) ) {
            self::st_set_error(self::ERR_INSERT, self::_t('Provided API monitor record is invalid.'));
            return null;
        }

        if( empty($existing_record['account_id'] )
            && empty( $fields_arr['account_id'] )
            && ($cuser = PHS::user_logged_in()) ) {
            $fields_arr['account_id'] = $cuser['id'] ?? 0;
        }

        if( empty($existing_record['plugin'] )
            && empty( $fields_arr['plugin'] )
            && ($route_arr = PHS::get_route_details_for_url()) ) {
            $fields_arr['plugin'] = $route_arr['p'] ?? null;
        }

        if( empty($existing_record['internal_route'] )
            && empty( $fields_arr['internal_route'] ) ) {
            $fields_arr['internal_route'] = PHS::get_route_as_string();
        }

        if( empty($existing_record['external_route'] )
            && empty( $fields_arr['external_route'] )
            && ($type = $fields_arr['type'] ?? $existing_record['type'] ?? 0)
            && (int)$type === self::TYPE_INCOMING
            && ($api_obj = PHS_Api::global_api_instance()) ) {
            $fields_arr['external_route'] = $api_obj->get_api_route();
        }

        if( !empty( $fields_arr['request_body'] )
            && !self::$_admin_plugin->monitor_api_full_request_body() ) {
            unset($fields_arr['request_body']);
        }

        if( !empty( $fields_arr['response_body'] )
            && !self::$_admin_plugin->monitor_api_full_response_body() ) {
            unset($fields_arr['response_body']);
        }

        if( !empty( $fields_arr['status'] ) ) {
            $fields_arr['status'] = (int)$fields_arr['status'];
            if( $fields_arr['status'] === self::STATUS_STARTED ) {
                $fields_arr['request_time'] = date(self::DATETIME_DB);
            } elseif( in_array( $fields_arr['status'], [self::STATUS_SUCCESS, self::STATUS_ERROR], true) ) {
                $fields_arr['response_time'] = date(self::DATETIME_DB);
            }
        }

        $copy_keys = ['account_id', 'method', 'internal_route', 'external_route', 'plugin',
                      'request_time', 'request_body', 'response_time', 'response_body',
                      'response_code', 'error_message', 'type', 'status', ];
        $new_fields_arr = [];
        foreach( $copy_keys as $key ) {
            if( !array_key_exists( $key, $fields_arr ) ) {
                continue;
            }

            $new_fields_arr[$key] = $fields_arr[$key];
        }

        $action_arr = self::$_the_model->fetch_default_flow_params(['table_name' => 'api_monitor']);
        $action_arr['fields'] = $new_fields_arr;

        $record_arr = null;
        if( empty($new_fields_arr)
            || (empty( $existing_record ) && !($record_arr = self::$_the_model->insert($action_arr)))
            || (!empty( $existing_record ) && !($record_arr = self::$_the_model->edit($existing_record, $action_arr))) ) {
            PHS_Logger::error('Error saving API monitor record details: '.
                              "\n".print_r($new_fields_arr, true),
                self::$_admin_plugin::LOG_API_MONITOR);

            self::st_set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error saving API monitor record.'));

            return null;
        }

        return $record_arr;
    }

    private static function _load_dependencies(): bool
    {
        return (self::$_admin_plugin
                || (self::$_admin_plugin = PHS_Plugin_Admin::get_instance()))
               && (self::$_the_model
                   || (self::$_the_model = self::get_instance()));
    }
}
