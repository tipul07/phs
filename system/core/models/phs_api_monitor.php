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

    protected static array $STATUSES_ARR = [
        self::STATUS_STARTED => ['title' => 'Started'],
        self::STATUS_SUCCESS => ['title' => 'Success'],
        self::STATUS_ERROR   => ['title' => 'Error'],
    ];

    private static ?PHS_Plugin_Admin $_admin_plugin = null;

    private static ?PHS_Model_Api_monitor $_the_model = null;

    private static ?array $_current_record = null;

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.1';
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

    public static function api_incoming_request_started() : bool
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return false;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return true;
        }

        $cuser = PHS::user_logged_in();

        $fields_arr = [];
        $fields_arr['account_id'] = $cuser['id'] ?? 0;
        $fields_arr['method'] = $_SERVER['REQUEST_METHOD'] ?? null;
        if( self::$_admin_plugin->monitor_api_full_request_body() ) {
            $fields_arr['request_body'] = PHS_Api_base::get_php_input();
        }
        $fields_arr['status'] = self::STATUS_STARTED;

        return (bool)self::_update_api_monitor_record($fields_arr);
    }

    public static function api_request_success(int $http_code = null, ?string $response_body = null) : bool
    {
        return self::_api_finish_request(self::STATUS_SUCCESS, null, $http_code, $response_body);
    }

    public static function api_request_error(
        int $http_code = null, ?string $error_message = null, ?string $response_body = null
    ) : bool
    {
        return self::_api_finish_request(self::STATUS_ERROR, $error_message, $http_code, $response_body);
    }

    public static function api_request_direct_error(
        int $http_code = null, ?string $error_message = null, ?string $response_body = null
    ) : bool
    {
        return self::api_incoming_request_started()
               && self::_api_finish_request(self::STATUS_ERROR, $error_message, $http_code, $response_body);
    }

    public static function update_api_request( int $http_code = null, ?string $response_body = null ): bool
    {
        return self::_api_finish_request(null, null, $http_code, $response_body);
    }

    private static function _api_finish_request(
        ?int $status = null, ?string $error_message = null,
        int $http_code = null, ?string $response_body = null
    ): bool
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error laoding required resources.' ) );
            return false;
        }

        if( !self::$_admin_plugin->monitor_api_incoming_calls() ) {
            return true;
        }

        if (!($api_obj = PHS_Api::global_api_instance())) {
            $api_obj = null;
        }

        $cuser = PHS::user_logged_in();
        $route_str = PHS::get_route_as_string();
        if( !($route_arr = PHS::get_route_details_for_url()) ) {
            $route_arr = [];
        }

        $fields_arr = [];
        $fields_arr['account_id'] = $cuser['id'] ?? 0;
        $fields_arr['plugin'] = $route_arr['p'] ?? null;
        $fields_arr['route'] = $route_str;
        if( $api_obj ) {
            $fields_arr['api_route'] = $api_obj->get_api_route();
        }
        $fields_arr['response_time'] = date(self::DATETIME_DB);
        if( $response_body !== null
            && self::$_admin_plugin->monitor_api_full_response_body() ) {
            $fields_arr['response_body'] = $response_body;
        }
        if( $http_code !== null ) {
            $fields_arr['response_code'] = $http_code;
        }
        if( $error_message !== null ) {
            $fields_arr['error_message'] = $error_message;
        }
        if( $status !== null ) {
            $fields_arr['status'] = $status;
        }

        return (bool)self::_update_api_monitor_record($fields_arr);
    }

    public static function get_current_record(): ?array
    {
        return self::$_current_record;
    }

    /**
     * @param int|array|null $record_data
     *
     * @return bool
     */
    public static function force_current_record($record_data): bool
    {
        if( null === $record_data ) {
            self::$_current_record = null;
            return true;
        }

        if( !self::_load_dependencies()
         || !($record_arr = self::$_the_model->data_to_array( $record_data )) ) {
            return false;
        }

        self::$_current_record = $record_arr;
        return true;
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
                    'route' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 255,
                        'default' => null,
                    ],
                    'api_route' => [
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

        $str_fields = ['method' => 50, 'route' => 255, 'api_route' => 255, 'plugin' => 255];
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

        $str_fields = ['method' => 50, 'route' => 255, 'api_route' => 255, 'plugin' => 255];
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

    private static function _update_api_monitor_record(array $fields_arr, ?array $force_record = null) : ?array
    {
        self::st_reset_error();

        if( !self::_load_dependencies() ) {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error loading required resources.' ) );
            return null;
        }

        $existing_record = $force_record ?? self::$_current_record ?? null;
        if( $existing_record && empty($existing_record['id']) ) {
            if( !empty($force_record) ) {
                self::st_set_error(self::ERR_INSERT, self::_t('Provided API monitor record is invalid.'));
                return null;
            }
            $existing_record = null;
        }

        $copy_keys = ['account_id', 'method', 'route', 'api_route', 'plugin',
                      'request_time', 'request_body', 'response_time', 'response_body',
                      'response_code', 'error_message', 'status', ];
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

        if( $record_arr
            && $force_record === null ) {
            self::$_current_record = $record_arr;
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
