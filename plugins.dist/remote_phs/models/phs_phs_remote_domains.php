<?php

namespace phs\plugins\remote_phs\models;

use \phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Crypt;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Utils;
use \phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use \phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Phs_remote_domains extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const STATUS_NOT_CONNECTED = 1, STATUS_WAITING_CONNECTION = 2, STATUS_CONNECTED = 3,
        STATUS_CONNECTION_ERROR = 4, STATUS_SUSPENDED = 5, STATUS_DELETED = 6;
    protected static array $STATUSES_ARR = [
        self::STATUS_NOT_CONNECTED => [ 'title' => 'Not Connected' ],
        self::STATUS_WAITING_CONNECTION => [ 'title' => 'Waiting Connection' ],
        self::STATUS_CONNECTED => [ 'title' => 'Connected' ],
        self::STATUS_CONNECTION_ERROR => [ 'title' => 'Connection Error' ],
        self::STATUS_SUSPENDED => [ 'title' => 'Suspended' ],
        self::STATUS_DELETED => [ 'title' => 'Deleted' ],
    ];

    public const LOG_STATUS_SENDING = 1, LOG_STATUS_SENT = 2, LOG_STATUS_ERROR = 3, LOG_STATUS_RECEIVED = 4;
    protected static array $LOG_STATUSES_ARR = [
        self::LOG_STATUS_SENDING => [ 'title' => 'Sending' ],
        self::LOG_STATUS_SENT => [ 'title' => 'Sent' ],
        self::LOG_STATUS_ERROR => [ 'title' => 'Error' ],
        self::LOG_STATUS_RECEIVED => [ 'title' => 'Received' ],
    ];

    public const LOG_TYPE_INCOMING = 1, LOG_TYPE_OUTGOING = 2;
    protected static array $LOG_TYPES_ARR = [
        self::LOG_TYPE_INCOMING => [ 'title' => 'Incoming' ],
        self::LOG_TYPE_OUTGOING => [ 'title' => 'Outgoing' ],
    ];

    public const SOURCE_MANUALLY = 1, SOURCE_PROGRAMMATICALLY = 2;
    protected static array $SOURCES_ARR = [
        self::SOURCE_MANUALLY => [ 'title' => 'Manually' ],
        self::SOURCE_PROGRAMMATICALLY => [ 'title' => 'Programmatically' ],
    ];

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.6';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return [ 'phs_remote_domains', 'phs_remote_logs' ];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'phs_remote_domains';
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_log_statuses( $lang = false )
    {
        static $log_statuses_arr = [];

        if( empty( self::$LOG_STATUSES_ARR ) ) {
            return [];
        }

        if( $lang === false
         && !empty( $log_statuses_arr ) ) {
            return $log_statuses_arr;
        }

        $result_arr = $this->translate_array_keys( self::$LOG_STATUSES_ARR, [ 'title' ], $lang );

        if( $lang === false ) {
            $log_statuses_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_log_statuses_as_key_val( $lang = false )
    {
        static $log_statuses_key_val_arr = false;

        if( $lang === false
         && $log_statuses_key_val_arr !== false ) {
            return $log_statuses_key_val_arr;
        }

        $key_val_arr = [];
        if( ($log_statuses_arr = $this->get_log_statuses( $lang )) )
        {
            foreach( $log_statuses_arr as $key => $val )
            {
                if( !is_array( $val ) ) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false ) {
            $log_statuses_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param int $status
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_log_status( $status, $lang = false )
    {
        $all_statuses = $this->get_log_statuses( $lang );
        if( empty( $status )
         || !isset( $all_statuses[$status] ) ) {
            return false;
        }

        return $all_statuses[$status];
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_log_types( $lang = false )
    {
        static $types_arr = [];

        if( empty( self::$LOG_TYPES_ARR ) ) {
            return [];
        }

        if( $lang === false
         && !empty( $types_arr ) ) {
            return $types_arr;
        }

        $result_arr = $this->translate_array_keys( self::$LOG_TYPES_ARR, [ 'title' ], $lang );

        if( $lang === false ) {
            $types_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_log_types_as_key_val( $lang = false )
    {
        static $types_key_val_arr = false;

        if( $lang === false
         && $types_key_val_arr !== false ) {
            return $types_key_val_arr;
        }

        $key_val_arr = [];
        if( ($types_arr = $this->get_log_types( $lang )) )
        {
            foreach( $types_arr as $key => $val )
            {
                if( !is_array( $val ) ) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false ) {
            $types_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param int $type
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_log_type( $type, $lang = false )
    {
        $all_types = $this->get_log_types( $lang );
        if( empty( $type )
         || !isset( $all_types[$type] ) ) {
            return false;
        }

        return $all_types[$type];
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_remote_domain_sources( $lang = false )
    {
        static $sources_arr = [];

        if( empty( self::$SOURCES_ARR ) ) {
            return [];
        }

        if( $lang === false
         && !empty( $sources_arr ) ) {
            return $sources_arr;
        }

        $result_arr = $this->translate_array_keys( self::$SOURCES_ARR, [ 'title' ], $lang );

        if( $lang === false ) {
            $sources_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_remote_domain_sources_as_key_val( $lang = false )
    {
        static $sources_key_val_arr = false;

        if( $lang === false
         && $sources_key_val_arr !== false ) {
            return $sources_key_val_arr;
        }

        $key_val_arr = [];
        if( ($sources_arr = $this->get_remote_domain_sources( $lang )) )
        {
            foreach( $sources_arr as $key => $val )
            {
                if( !is_array( $val ) ) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false ) {
            $sources_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param int $source
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_remote_domain_sources( $source, $lang = false )
    {
        $all_sources = $this->get_remote_domain_sources( $lang );
        if( empty( $source )
         || !isset( $all_sources[$source] ) ) {
            return false;
        }

        return $all_sources[$source];
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_not_connected( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_NOT_CONNECTED ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_waiting_connection( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_WAITING_CONNECTION ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_connected( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_CONNECTED ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_connection_error( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_CONNECTION_ERROR ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_suspended( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_SUSPENDED ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_deleted( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_DELETED ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_source_manually( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['source'] !== self::SOURCE_MANUALLY ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_source_programmatically( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['source'] !== self::SOURCE_PROGRAMMATICALLY ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function should_log_requests( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || empty( $record_arr['log_requests'] ) ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function should_log_request_body( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || empty( $record_arr['log_body'] ) ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function should_allow_incoming_requests( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || empty( $record_arr['allow_incoming'] ) ) {
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_connect( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( $this->is_connected( $record_arr ) ) {
            return $record_arr;
        }

        if( !PHS_Bg_jobs::run( [ 'p' => 'remote_phs', 'c' => 'index_bg', 'a' => 'connect_bg', 'ad' => 'connection' ],
                               [ 'rdid' => $record_arr['id'] ] ) )
        {
            if( self::st_has_error() ) {
                $error_msg = self::st_get_error_message();
            } else {
                $error_msg = $this->_pt('Error starting connection process. Please try again.');
            }

            $this->set_error( self::ERR_FUNCTIONALITY, $error_msg );
            return false;
        }

        return $record_arr;
    }

    /**
     * @param int|array $domain_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_connect_bg( $domain_data, $params = false )
    {
        $this->reset_error();

        if( empty( $domain_data )
         || !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( $this->is_connected( $domain_arr ) ) {
            return $domain_arr;
        }

        $crypt_key = PHS_Crypt::generate_crypt_key();
        $crypt_internal_keys = PHS_Crypt::generate_crypt_internal_keys();

        if( !($settings_arr = $this->decode_connection_settings( $domain_arr )) ) {
            $settings_arr = $this->get_default_connection_settings_arr();
        }

        $settings_arr['crypt_key'] = $crypt_key;
        $settings_arr['crypt_internal_keys'] = $crypt_internal_keys;

        if( !($connection_settings = $this->encode_connection_settings( $settings_arr )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining connection settings.' ) );
            return false;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_WAITING_CONNECTION;
        $edit_arr['connection_settings'] = $connection_settings;
        $edit_arr['error_log'] = null;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if( !($new_domain_arr = $this->edit( $domain_arr, $edit_params )) )
        {
            PHS_Logger::error( '[CONNECTION_ERROR] Error updating remote domain '.$domain_arr['title'].' #'.$domain_arr['id'].'.', PHS_Logger::TYPE_REMOTE );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating remote domain details in database.' ) );
            return false;
        }
        $domain_arr = $new_domain_arr;

        $payload_arr = [];
        $payload_arr['remote_id'] = (int)$domain_arr['id'];
        $payload_arr['remote_www'] = PHS::get_base_domain_and_path();
        $payload_arr['crypt_key'] = $crypt_key;
        $payload_arr['crypt_internal_keys'] = $crypt_internal_keys;

        if( !($api_response = $this->_send_api_request_to_domain( $domain_arr, 'phs_remote/connect', $payload_arr ))
         || empty( $api_response['response_json'] ) || !is_array( $api_response['response_json'] )
         || empty( $api_response['response_json']['response'] ) || !is_array( $api_response['response_json']['response'] )
         || empty( $api_response['response_json']['response']['remote_id'] ) )
        {
            if( $this->has_error() ) {
                $error_log = $this->get_simple_error_message();
            } else {
                $error_log = 'Error sending initial connect request.';
            }

            if( !empty( $api_response['response_json'] ) && is_array( $api_response['response_json'] )
             && empty( $api_response['response_json']['error'] ) && is_array( $api_response['response_json']['error'] ) )
            {
                if( !empty( $api_response['response_json']['error']['code'] ) ) {
                    $error_log .= ' #'.$api_response['response_json']['error']['code'];
                }
                if( !empty( $api_response['response_json']['error']['message'] ) ) {
                    $error_log .= ' '.$api_response['response_json']['error']['message'];
                }
            }

            PHS_Logger::error( '[CONNECTION_ERROR] Error connecting with remote domain '.$domain_arr['title'].' #'.$domain_arr['id'].': '.$error_log, PHS_Logger::TYPE_REMOTE );

            // Error sending request to remote domain
            $edit_arr = [];
            $edit_arr['status'] = self::STATUS_CONNECTION_ERROR;
            $edit_arr['error_log'] = $error_log;

            $edit_params = [];
            $edit_params['fields'] = $edit_arr;

            if( !($new_domain_arr = $this->edit( $domain_arr, $edit_params )) )
            {
                PHS_Logger::error( '[CONNECTION_ERROR] Error updating remote domain when connection error.', PHS_Logger::TYPE_REMOTE );

                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating remote domain details in database.' ) );
                return false;
            }

            return $new_domain_arr;
        }

        $edit_arr = [];
        $edit_arr['remote_id'] = (int)$api_response['response_json']['response']['remote_id'];
        $edit_arr['status'] = self::STATUS_CONNECTED;
        $edit_arr['error_log'] = null;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if( !($new_domain_arr = $this->edit( $domain_arr, $edit_params )) )
        {
            PHS_Logger::error( '[CONNECTION_ERROR] Error updating remote domain when waiting connection '.$domain_arr['title'].' #'.$domain_arr['id'].'.', PHS_Logger::TYPE_REMOTE );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating remote domain details in database.' ) );
            return false;
        }
        $domain_arr = $new_domain_arr;

        $payload_arr = [];
        $payload_arr['remote_id'] = (int)$domain_arr['id'];
        $payload_arr['remote_www'] = PHS::get_base_domain_and_path();

        if( !($api_response = $this->_send_api_request_to_domain( $domain_arr, 'phs_remote/connect_confirm', $payload_arr ))
         || empty( $api_response['response_json']['response']['remote_id'] ) )
        {
            if( $this->has_error() ) {
                $error_log = $this->get_simple_error_message();
            } else {
                $error_log = 'Error sending connection confirmation request.';
            }

            if( !empty( $api_response['response_json'] ) && is_array( $api_response['response_json'] )
             && empty( $api_response['response_json']['error'] ) && is_array( $api_response['response_json']['error'] ) )
            {
                if( !empty( $api_response['response_json']['error']['code'] ) ) {
                    $error_log .= ' #'.$api_response['response_json']['error']['code'];
                }
                if( !empty( $api_response['response_json']['error']['message'] ) ) {
                    $error_log .= ' '.$api_response['response_json']['error']['message'];
                }
            }

            PHS_Logger::error( '[CONNECTION_ERROR] Error sending connection confirmation with remote domain '.$domain_arr['title'].' #'.$domain_arr['id'].': '.$error_log, PHS_Logger::TYPE_REMOTE );

            // Error sending request to remote domain
            $edit_arr = [];
            $edit_arr['status'] = self::STATUS_CONNECTION_ERROR;
            $edit_arr['error_log'] = $error_log;

            $edit_params = [];
            $edit_params['fields'] = $edit_arr;

            if( !($new_domain_arr = $this->edit( $domain_arr, $edit_params )) )
            {
                PHS_Logger::error( '[CONNECTION_ERROR] Error updating remote domain when connection error.', PHS_Logger::TYPE_REMOTE );

                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating remote domain details in database.' ) );
                return false;
            }

            return $new_domain_arr;
        }

        return $domain_arr;
    }

    /**
     * @param int|array $domain_data
     * @param string $api_route
     * @param false|array $payload_arr
     * @param false|array $request_params
     *
     * @return array|false
     */
    private function _send_api_request_to_domain( $domain_data, $api_route, $payload_arr = false, $request_params = false )
    {
        return $this->_send_request_to_domain( $domain_data, $api_route, PHS_Scope::SCOPE_API, $payload_arr, $request_params );
    }

    /**
     * @param int|array $domain_data
     * @param false|array $payload_arr
     * @param false|array $request_params
     *
     * @return array|false
     */
    private function _send_remote_request_to_domain( $domain_data, $payload_arr = false, $request_params = false )
    {
        return $this->_send_request_to_domain( $domain_data, '', PHS_Scope::SCOPE_REMOTE, $payload_arr, $request_params );
    }

    /**
     * @param int|array $domain_data
     * @param string $api_route
     * @param int $for_scope
     * @param false|array $payload_arr
     * @param false|array $request_params
     *
     * @return array|false
     */
    private function _send_request_to_domain( $domain_data, $api_route, $for_scope, $payload_arr = false, $request_params = false )
    {
        $this->reset_error();

        if( empty( $request_params ) || !is_array( $request_params ) ) {
            $request_params = [];
        }

        if( empty( $domain_data )
         || !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( $for_scope === PHS_Scope::SCOPE_API
         && (empty( $api_route ) || !is_string( $api_route )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a remote domain API route.' ) );
            return false;
        }

        if( empty( $domain_arr['remote_www'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain settings are invalid.' ) );
            return false;
        }

        $url_extra = [];
        $url_extra['for_scope'] = $for_scope;
        $url_extra['for_domain'] = $domain_arr['remote_www'];
        $url_extra['use_rewrite_url'] = true;
        $url_extra['raw_route'] = $api_route;

        if( !($full_url = PHS::url( [ 'force_https' => true ], false, $url_extra )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error obtaining full API URL for remote domain.' ) );
            return false;
        }

        $request_params['payload_arr'] = $payload_arr;
        $request_params['timeout'] = (int)$domain_arr['out_timeout'];
        $request_params['auth'] = [
            'user' => (!empty( $domain_arr['out_apikey'] )?$domain_arr['out_apikey']:''),
            'pass' => (!empty( $domain_arr['out_apisecret'] )?$domain_arr['out_apisecret']:''),
        ];

        return $this->_send_request_to_url( $full_url, $request_params );
    }

    /**
     * @param string $full_url
     * @param false|array $params
     *
     * @return array|false
     */
    private function _send_request_to_url( $full_url, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) ) {
            $params = [];
        }

        if( !isset( $params['timeout'] ) ) {
            $params['timeout'] = 30;
        } else {
            $params['timeout'] = (int) $params['timeout'];
        }

        if( !isset( $params['extra_get_params'] ) || !is_array( $params['extra_get_params'] ) ) {
            $params['extra_get_params'] = [];
        }

        if( empty( $params['auth'] ) || !is_array( $params['auth'] ) ) {
            $params['auth'] = false;
        } else
        {
            if( empty( $params['auth']['user'] ) ) {
                $params['auth']['user'] = '';
            }
            if( empty( $params['auth']['pass'] ) ) {
                $params['auth']['pass'] = '';
            }
        }

        if( !isset( $params['expect_json'] ) ) {
            $params['expect_json'] = true;
        }
        if( empty( $params['payload_arr'] ) ) {
            $params['payload_arr'] = false;
        }
        if( empty( $params['log_channel'] )
         || !PHS_Logger::defined_channel( $params['log_channel'] ) ) {
            $params['log_channel'] = PHS_Logger::TYPE_REMOTE;
        }

        $log_channel = $params['log_channel'];

        $curl_params = [];
        $curl_params['timeout'] = $params['timeout'];
        $curl_params['extra_get_params'] = $params['extra_get_params'];
        if( !empty( $params['auth'] )
         && (!empty( $params['auth']['user'] ) || !empty( $params['auth']['pass'] )) )
        {
            $curl_params['userpass'] = [
                'user' => $params['auth']['user'],
                'pass' => $params['auth']['pass'],
            ];
        }
        $curl_params['header_keys_arr'] = [
            'Accept' => 'application/json',
        ];

        $payload_str = false;
        if( !empty( $params['payload_arr'] ) && is_array( $params['payload_arr'] ) )
        {
            if( !($payload_str = @json_encode( $params['payload_arr'] )) )
            {
                PHS_Logger::error( 'Error encoding payload in JSON for 3rd party API call.', $log_channel );

                ob_start();
                var_dump( $curl_params );
                $request_params = @ob_get_clean();

                PHS_Logger::error( 'REMOTE API URL: '.$full_url."\n".
                                  'Params: '.$request_params, $log_channel );

                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error encoding payload in JSON for remote domain API call.' ) );
                return false;
            }

            $curl_params['header_keys_arr']['Content-Type'] = 'application/json';
            $curl_params['raw_post_str'] = $payload_str;
        }

        if( !($api_response = PHS_Utils::quick_curl( $full_url, $curl_params ))
         || !is_array( $api_response ) )
        {
            PHS_Logger::error( 'Error initiating call to remote domain API.', $log_channel );

            ob_start();
            var_dump( $curl_params );
            $request_params = @ob_get_clean();

            PHS_Logger::error( 'REMOTE API URL: '.$full_url."\n".
                              'Params: '.$request_params."\n".
                              'Payload: '.(!empty( $payload_str )?$payload_str:'N/A'), $log_channel );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating call to remote domain API.' ) );
            return false;
        }

        if( empty( $api_response['request_details'] ) || !is_array( $api_response['request_details'] ) )
        {
            PHS_Logger::error( 'Error retrieving remote domain API request details.', $log_channel );

            ob_start();
            var_dump( $curl_params );
            $request_params = @ob_get_clean();

            PHS_Logger::error( 'REMOTE API URL: '.$full_url."\n".
                              'Params: '.$request_params."\n".
                              'Payload: '.(!empty( $payload_str )?$payload_str:'N/A'), $log_channel );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error retrieving remote domain API request details.' ) );
            return false;
        }

        if( empty( $api_response['request_details']['http_code'] )
         || !in_array( (int)$api_response['request_details']['http_code'], [ 200, 201, 204 ], true ) )
        {
            PHS_Logger::error( 'Remote domain API responded with HTTP code: '.$api_response['request_details']['http_code'], $log_channel );

            $request_headers = (!empty( $api_response['request_details']['request_header'] )?$api_response['request_details']['request_header']:'N/A');
            if( !empty( $api_response['request_details']['request_params'] ) )
            {
                ob_start();
                var_dump( $api_response['request_details']['request_params'] );
                $request_params = @ob_get_clean();
            } else {
                $request_params = 'N/A';
            }

            PHS_Logger::error( 'REMOTE API URL: '.$full_url."\n".
                              'Request headers: '.$request_headers."\n".
                              'Params: '.$request_params."\n".
                              'Payload: '.(!empty( $payload_str )?$payload_str:'N/A')."\n".
                              'Response: '.(!empty( $api_response['response'] )?$api_response['response']:'N/A'), $log_channel );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Remote domain API responded with HTTP code: %s.', $api_response['request_details']['http_code'] ) );
            return false;
        }

        $http_code = (int)$api_response['request_details']['http_code'];

        if( !empty( $http_code ) )
        {
            PHS_Logger::notice( 'REMOTE API URL: '.$full_url."\n".
                              'Payload: '.(!empty( $payload_str )?$payload_str:'N/A')."\n".
                              'REMOTE API responded with HTTP code: ' . $api_response['request_details']['http_code'], $log_channel );
        }

        $api_response['response_json'] = false;

        // If we received something different from 204
        if( $http_code !== 204
         && !empty( $params['expect_json'] ) )
        {
            if( empty( $api_response['response'] ) ) {
                $api_response['response_json'] = [];
            }

            elseif( !($api_response['response_json'] = @json_decode( $api_response['response'], true )) )
            {
                PHS_Logger::error( 'Couldn\'t decode API response.', $log_channel );

                $request_headers = (!empty( $api_response['request_details']['request_header'] )?$api_response['request_details']['request_header']:'N/A');
                if( !empty( $api_response['request_details']['request_params'] ) )
                {
                    ob_start();
                    var_dump( $api_response['request_details']['request_params'] );
                    $request_params = @ob_get_clean();
                } else {
                    $request_params = 'N/A';
                }

                PHS_Logger::error( 'GP API URL: '.$full_url."\n".
                                  'Request headers: '.$request_headers."\n".
                                  'Params: '.$request_params."\n".
                                  'Payload: '.(!empty( $payload_str )?$payload_str:'N/A'), $log_channel );
            }
        }

        /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $remote_plugin */
        if( ($remote_plugin = PHS::load_plugin( 'remote_phs' ))
         && $remote_plugin->log_all_outgoing_calls() )
        {
            ob_start();
            var_dump( $api_response );
            $buf = @ob_get_clean();

            PHS_Logger::info('REMOTE API URL: '.$full_url."\n".
                             'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A')."\n".
                             'Response: '.$buf, $log_channel);
        }

        return $api_response;
    }

    public function get_default_communication_message_arr(): array
    {
        return [
            // What route should run
            'route' => false,
            // What should go in _POST (simulating post variables)
            'post_arr' => [],
            // What should go in _GET (simulating get variables)
            'get_arr' => [],
            // Other variables which will be available in request, but will not go to post or get
            'request_arr' => [],
            // Timezone
            // date( 'Z' ) - Timezone offset in seconds.
            // The offset for timezones west of UTC is always negative, and for those east of UTC is always positive.
            // -43200 through 50400
            'timezone' => 0,
            // In case action would start a background script, where should the system send the response
            'async_response_url' => '',
        ];
    }

    public function validate_communication_message( $msg )
    {
        $this->reset_error();

        if( empty( $msg ) || !is_array( $msg ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid communication message.' ) );
            return false;
        }

        if( !($msg = self::validate_array( $msg, $this->get_default_communication_message_arr() ))
         || empty( $msg['route'] ) || !is_array( $msg['route'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No action provided in communication message.' ) );
            return false;
        }

        return $msg;
    }

    public function get_default_connection_settings_arr(): array
    {
        return [
            'crypt_key' => '',
            'crypt_internal_keys' => [],
        ];
    }

    public function encode_connection_settings( $connection_settings_arr )
    {
        if( empty( $connection_settings_arr ) || !is_array( $connection_settings_arr ) ) {
            $connection_settings_arr = [];
        }

        $settings_arr = [];
        $defaults_arr = $this->get_default_connection_settings_arr();
        foreach( $defaults_arr as $key => $def )
        {
            if( !array_key_exists( $key, $connection_settings_arr ) ) {
                $connection_settings_arr[$key] = $def;
            }

            $settings_arr[$key] = $connection_settings_arr[$key];
        }

        return PHS_Crypt::quick_encode( @json_encode( $settings_arr ) );
    }

    public function decode_connection_settings( $domain_data )
    {
        $this->reset_error();

        if( empty( $domain_data )
         || !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        $defaults_arr = $this->get_default_connection_settings_arr();

        if( empty( $domain_arr['connection_settings'] ) ) {
            return $defaults_arr;
        }

        if( !($settings_str = PHS_Crypt::quick_decode( $domain_arr['connection_settings'] ))
         || null === ($settings_arr = @json_decode( $settings_str, true ))
         || !is_array( $settings_arr ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error decoding remote domain settings.' ) );
            return false;
        }

        $new_settings_arr = [];
        foreach( $defaults_arr as $key => $def )
        {
            if( !array_key_exists( $key, $settings_arr ) ) {
                $settings_arr[$key] = $def;
            }

            $new_settings_arr[$key] = $settings_arr[$key];
        }

        return $new_settings_arr;
    }

    /**
     * @param int|array $domain_data
     * @param string $str
     *
     * @return false|string
     */
    public function quick_encode( $domain_data, $str )
    {
        $this->reset_error();

        if( empty( $domain_data )
         || !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( !($settings_arr = $this->decode_connection_settings( $domain_arr ))
         || !is_array( $settings_arr )
         || empty( $settings_arr['crypt_key'] )
         || empty( $settings_arr['crypt_internal_keys'] ) || !is_array( $settings_arr['crypt_internal_keys'] ) )
        {
            $this->set_error( self::ERR_RESOURCES, $this->_pt( 'Invalid remote domain connection settings.' ) );
            return false;
        }

        $crypt_params = [];
        $crypt_params['use_base64'] = true;
        $crypt_params['crypting_key'] = $settings_arr['crypt_key'];
        $crypt_params['internal_keys'] = $settings_arr['crypt_internal_keys'];

        if( false === ($encoded_data = PHS_Crypt::quick_encode( $str, $crypt_params )) )
        {
            $this->set_error( self::ERR_RESOURCES, $this->_pt( 'Error encoding remote domain data.' ) );
            return false;
        }

        return $encoded_data;
    }

    /**
     * @param int|array $domain_data
     * @param string $str
     *
     * @return false|string
     */
    public function quick_decode( $domain_data, $str )
    {
        $this->reset_error();

        if( empty( $domain_data )
         || !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( !($settings_arr = $this->decode_connection_settings( $domain_arr ))
         || !is_array( $settings_arr )
         || empty( $settings_arr['crypt_key'] )
         || empty( $settings_arr['crypt_internal_keys'] ) || !is_array( $settings_arr['crypt_internal_keys'] ) )
        {
            $this->set_error( self::ERR_RESOURCES, $this->_pt( 'Invalid remote domain connection settings.' ) );
            return false;
        }

        $crypt_params = [];
        $crypt_params['use_base64'] = true;
        $crypt_params['crypting_key'] = $settings_arr['crypt_key'];
        $crypt_params['internal_keys'] = $settings_arr['crypt_internal_keys'];

        if( false === ($decoded_data = PHS_Crypt::quick_decode( $str, $crypt_params )) )
        {
            $this->set_error( self::ERR_RESOURCES, $this->_pt( 'Error decoding remote domain data.' ) );
            return false;
        }

        return $decoded_data;
    }

    /**
     * @param string $domain_handler
     *
     * @return array|false
     */
    public function get_domain_by_handler( $domain_handler )
    {
        $this->reset_error();

        if( empty( $domain_handler )
         || !($domain_arr = $this->get_details_fields( [ 'handle' => $domain_handler ], [ 'table_name' => 'phs_remote_domains' ] )) ) {
            return false;
        }

        return $domain_arr;
    }

    /**
     * @param string $domain_handler
     * @param array $message_arr
     *
     * @return array|false
     */
    public function send_request_to_domain_handler( $domain_handler, $message_arr )
    {
        $this->reset_error();

        if( empty( $domain_handler )
         || !($domain_arr = $this->get_domain_by_handler( $domain_handler )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        return $this->send_request_to_domain( $domain_arr, $message_arr );
    }

    /**
     * @param int|array $domain_data
     * @param array $message_arr
     *
     * @return array|false
     */
    public function send_request_to_domain( $domain_data, $message_arr )
    {
        $this->reset_error();

        if( empty( $domain_data )
         || !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( !$this->is_connected( $domain_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details is not connected yet.' ) );
            return false;
        }

        if( !($message_arr = $this->validate_communication_message( $message_arr )) )
        {
            PHS_Logger::error( 'Error validating message to (#'.$domain_arr['id'].').'.
                              ($this->has_error()?' Error: '.$this->get_simple_error_message():''), PHS_Logger::TYPE_REMOTE );

            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error validating message.' ) );
            return false;
        }

        if( empty( $message_arr['timezone'] ) ) {
            $message_arr['timezone'] = (int) date('Z');
        }

        if( !($message_json = @json_encode( $message_arr ))
         || !($message_str = $this->quick_encode( $domain_arr, $message_json )) )
        {
            PHS_Logger::error( 'Error encoding message to (#'.$domain_arr['id'].').'.
                              ($this->has_error()?' Error: '.$this->get_simple_error_message():''), PHS_Logger::TYPE_REMOTE );

            if( !$this->has_error() ) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encoding message.'));
            }
            return false;
        }

        // Update last_incoming for the domain...
        $edit_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_remote_domains' ] );
        $edit_arr['fields'] = [];
        $edit_arr['fields']['last_outgoing'] = date( self::DATETIME_DB );

        if( ($new_domain_arr = $this->edit( $domain_arr, $edit_arr )) ) {
            $domain_arr = $new_domain_arr;
        }

        // Log request right before running the actual action...
        $remote_log_arr = false;
        if( $this->should_log_requests( $domain_arr ) )
        {
            $log_fields = [];
            $log_fields['route'] = (!empty( $message_arr['route'] )?PHS::route_from_parts( $message_arr['route'] ):'-');
            if( $this->should_log_request_body( $domain_arr ) ) {
                $log_fields['body'] = $message_json;
            }

            if( !($remote_log_arr = $this->domain_outgoing_log( $domain_arr, $log_fields )) ) {
                $remote_log_arr = false;
            }
        }

        // Reset any edit errors as we don't care about them...
        $this->reset_error();

        $payload_arr = [
            'remote_id' => (int)$domain_arr['remote_id'],
            'msg' => $message_str,
        ];

        if( !($response_arr = $this->_send_remote_request_to_domain( $domain_arr, $payload_arr )) )
        {
            if( !$this->has_error() ) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error sending request to remote domain.'));
            }

            if( !empty( $remote_log_arr ) )
            {
                $log_fields = [];
                $log_fields['status'] = self::LOG_STATUS_ERROR;
                $log_fields['error_log'] = $this->get_simple_error_message();

                $error_stack = $this->stack_error();

                // We don't care about errors...
                $this->domain_outgoing_log( $domain_arr, $log_fields, $remote_log_arr );

                $this->restore_errors( $error_stack );
            }

            return false;
        }

        $error_code = 0;
        $error_msg = '';
        if( !empty( $response_arr['error'] ) && is_array( $response_arr['error'] ) )
        {
            if( !empty( $response_arr['error']['code'] ) ) {
                $error_code = (int) $response_arr['error']['code'];
            }
            if( !empty( $response_arr['error']['message'] ) ) {
                $error_msg = trim($response_arr['error']['message']);
            }
        }
        $has_error = ($error_code!==0);

        if( !empty( $remote_log_arr ) )
        {
            $log_fields = [];
            if( $has_error )
            {
                $log_fields['status'] = self::LOG_STATUS_ERROR;
                $log_fields['error_log'] = $this->_pt( 'Error in response: [%s] %s', $error_code, $error_msg );
            } else
            {
                $log_fields['status'] = self::LOG_STATUS_SENT;
                $log_fields['error_log'] = null;
            }

            // We don't care about errors...
            if( !$this->domain_outgoing_log( $domain_arr, $log_fields, $remote_log_arr ) ) {
                $this->reset_error();
            }
        }

        return [
            'has_error' => $has_error,
            'error_code' => $error_code,
            'error_msg' => $error_msg,
            'full_response' => $response_arr,
            'json_arr' => (!empty( $response_arr['response_json'] )?$response_arr['response_json']:[]),
        ];
    }

        /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_suspend( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( $this->is_suspended( $record_arr ) ) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_SUSPENDED;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_delete( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) ) {
            $params = [];
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params_arr = [];
        $edit_params_arr['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params_arr );
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_delete_log( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($flow_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_remote_logs' ] ))
         || !($record_arr = $this->data_to_array( $record_data, $flow_arr )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'Remote domain log details not found in database.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) ) {
            $params = [];
        }

        return $this->hard_delete( $record_arr, $flow_arr );
    }

    public function can_user_edit( $record_data, $account_data )
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $remote_plugin */
        if( empty( $record_data ) || empty( $account_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || $this->is_deleted( $record_arr )
         || !($remote_plugin = PHS::load_plugin( 'remote_phs' ))
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         || !($account_arr = $accounts_model->data_to_array( $account_data, [ 'table_name' => 'users' ] ))
         || !$remote_plugin->can_admin_manage_domains( $account_arr ) ) {
            return false;
        }

        $return_arr = [];
        $return_arr['remote_domain_data'] = $record_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    /**
     * @param bool $only_connected
     *
     * @return array
     */
    public function get_all_remote_domains( $only_connected = false )
    {
        static $cached_domains = false, $cached_connected_domains = false;

        $this->reset_error();

        if( !$only_connected )
        {
            if( $cached_domains !== false ) {
                return $cached_domains;
            }
        } else
        {
            if( $cached_connected_domains !== false ) {
                return $cached_connected_domains;
            }
        }

        $list_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_remote_domains' ] );
        $list_arr['fields']['status'] = [ 'check' => '!=', 'value' => self::STATUS_DELETED ];
        $list_arr['order_by'] = 'title ASC';

        $cached_connected_domains = [];
        if( !($cached_domains = $this->get_list( $list_arr )) ) {
            $cached_domains = [];
        }

        else
        {
            foreach( $cached_domains as $d_id => $d_arr )
            {
                if( !$this->is_connected( $d_arr ) ) {
                    continue;
                }

                $cached_connected_domains[$d_id] = $d_arr;
            }
        }

        if( $only_connected ) {
            return $cached_connected_domains;
        }

        return $cached_domains;
    }

    /**
     * @param bool $only_connected
     *
     * @return array
     */
    public function get_all_remote_domains_as_key_val( $only_connected = false )
    {
        $this->reset_error();

        if( !($results_arr = $this->get_all_remote_domains( $only_connected )) ) {
            return [];
        }

        $return_arr = [];
        foreach( $results_arr as $record_id => $record_arr )
        {
            $return_arr[$record_id] = $record_arr['title'];
        }

        return $return_arr;
    }

    public function domain_incoming_log( $domain_data, $fields_arr, $existing_log = false )
    {
        return $this->_domain_log( $domain_data, self::LOG_TYPE_INCOMING, $fields_arr, $existing_log );
    }

    public function domain_outgoing_log( $domain_data, $fields_arr, $existing_log = false )
    {
        return $this->_domain_log( $domain_data, self::LOG_TYPE_OUTGOING, $fields_arr, $existing_log );
    }

    private function _domain_log( $domain_data, $log_type, $fields_arr, $existing_log = false )
    {
        $this->reset_error();

        if( empty( $fields_arr ) || !is_array( $fields_arr ) ) {
            $fields_arr = [];
        }

        if( !($domain_arr = $this->data_to_array( $domain_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || $this->is_deleted( $domain_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error finding remote domain in database.' ) );
            return false;
        }

        if( empty( $existing_log ) && empty( $fields_arr['route'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a route for remote domain log.' ) );
            return false;
        }

        $fields_arr['domain_id'] = $domain_arr['id'];
        $fields_arr['type'] = $log_type;

        return $this->_domain_log_db_action( $fields_arr, $existing_log );
    }

    private function _domain_log_db_action( $fields_arr, $log_arr = false )
    {
        $this->reset_error();

        if( empty( $fields_arr ) || !is_array( $fields_arr )
         || empty( $fields_arr['domain_id'] )
         || (empty( $fields_arr['type'] ) || !$this->valid_log_type( $fields_arr['type'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid remote domain log parameters.' ) );
            return false;
        }

        if( empty( $log_arr )
         && (empty( $fields_arr['status'] ) || !$this->valid_log_status( $fields_arr['status'] )) )
        {
            if( (int)$fields_arr['type'] === self::LOG_TYPE_INCOMING ) {
                $fields_arr['status'] = self::LOG_STATUS_RECEIVED;
            }
            // Future-proof (in case we add new types)
            elseif( (int)$fields_arr['type'] === self::LOG_TYPE_OUTGOING ) {
                $fields_arr['status'] = self::LOG_STATUS_SENDING;
            }
        }

        if( !empty( $fields_arr['status'] )
         && (int)$fields_arr['status'] !== self::LOG_STATUS_ERROR ) {
            $fields_arr['error_log'] = null;
        }

        $action_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_remote_logs' ] );
        $action_arr['fields'] = $fields_arr;

        if( !empty( $log_arr ) )
        {
            // Edit...
            if( !($db_log_arr = $this->edit( $log_arr, $action_arr )) )
            {
                $this->set_error( self::ERR_EDIT, $this->_pt( 'Error updating remote domain log.' ) );
                return false;
            }
        } else
        {
            // Insert...
            if( !($db_log_arr = $this->insert( $action_arr )) )
            {
                $this->set_error( self::ERR_INSERT, $this->_pt( 'Error inserting remote domain log.' ) );
                return false;
            }
        }

        return $db_log_arr;
    }

    protected function get_insert_prepare_params_phs_remote_domains( $params )
    {
        if( empty( $params ) || !is_array( $params ) ) {
            return false;
        }

        if( empty( $params['fields']['title'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide remote domain title.' ) );
            return false;
        }

        if( empty( $params['fields']['source'] )
         || !$this->valid_status( $params['fields']['source'] ) ) {
            $params['fields']['source'] = self::SOURCE_MANUALLY;
        }

        if( !empty( $params['fields']['remote_www'] ) )
        {
            if( stripos( $params['fields']['remote_www'], 'https://' ) === 0 ) {
                $params['fields']['remote_www'] = substr($params['fields']['remote_www'], 8);
            } elseif( stripos( $params['fields']['remote_www'], 'http://' ) === 0 ) {
                $params['fields']['remote_www'] = substr($params['fields']['remote_www'], 7);
            }

            if( $params['fields']['remote_www'] !== ''
             && substr( $params['fields']['remote_www'], -1 ) !== '/' ) {
                $params['fields']['remote_www'] .= '/';
            }
        }

        // For programmatically added remote domains don't impose restrictions on fields

        if( empty( $params['fields']['remote_www'] )
         && $params['fields']['source'] !== self::SOURCE_PROGRAMMATICALLY )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide remote domain address.' ) );
            return false;
        }

        // Handle is mandatory all times
        if( empty( $params['fields']['handle'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a handle for this remote domain.' ) );
            return false;
        }

        if( empty( $params['fields']['out_apikey'] ) && empty( $params['fields']['out_apisecret'] )
         && $params['fields']['source'] !== self::SOURCE_PROGRAMMATICALLY )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide outgoing API key details for this remote domain.' ) );
            return false;
        }

        if( empty( $params['fields']['apikey_id'] )
         && $params['fields']['source'] !== self::SOURCE_PROGRAMMATICALLY )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an incoming API key for this remote domain.' ) );
            return false;
        }

        if( !isset( $params['fields']['allow_incoming'] ) ) {
            $params['fields']['allow_incoming'] = 1;
        } else {
            $params['fields']['allow_incoming'] = (!empty($params['fields']['allow_incoming']) ? 1 : 0);
        }

        $params['fields']['log_requests'] = (!empty( $params['fields']['log_requests'] )?1:0);
        $params['fields']['log_body'] = (!empty( $params['fields']['log_body'] )?1:0);

        $check_arr = [];
        $check_arr['handle'] = $params['fields']['handle'];

        if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a remote domain using provided handle.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        if( empty( $params['fields']['status'] )
         || !$this->valid_status( $params['fields']['status'] ) ) {
            $params['fields']['status'] = self::STATUS_NOT_CONNECTED;
        }

        $params['fields']['cdate'] = $cdate;

        if( empty( $params['fields']['status_date'] )
         || empty_db_date( $params['fields']['status_date'] ) ) {
            $params['fields']['status_date'] = $params['fields']['cdate'];
        } else {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        return $params;
    }

    protected function get_edit_prepare_params_phs_remote_domains( $existing_arr, $params )
    {
        if( isset( $params['fields']['title'] ) && empty( $params['fields']['title'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain title.' ) );
            return false;
        }

        if( isset( $params['fields']['remote_www'] ) && !empty( $params['fields']['remote_www'] ) )
        {
            $params['fields']['remote_www'] = trim( $params['fields']['remote_www'] );
            if( stripos( $params['fields']['remote_www'], 'https://' ) === 0 ) {
                $params['fields']['remote_www'] = substr($params['fields']['remote_www'], 8);
            } elseif( stripos( $params['fields']['remote_www'], 'http://' ) === 0 ) {
                $params['fields']['remote_www'] = substr($params['fields']['remote_www'], 7);
            }

            if( $params['fields']['remote_www'] !== ''
             && substr( $params['fields']['remote_www'], -1 ) !== '/' ) {
                $params['fields']['remote_www'] .= '/';
            }
        }

        if( isset( $params['fields']['remote_www'] ) && empty( $params['fields']['remote_www'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain URL.' ) );
            return false;
        }

        if( !empty( $params['fields']['out_apikey'] ) ) {
            $params['fields']['out_apikey'] = trim($params['fields']['out_apikey']);
        }

        if( isset( $params['fields']['out_apikey'] ) && empty( $params['fields']['out_apikey'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain API key.' ) );
            return false;
        }

        if( isset( $params['fields']['handle'] ) && empty( $params['fields']['handle'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a handle for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['source'] ) && !$this->valid_remote_domain_sources( $params['fields']['source'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid source for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['status'] ) && !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid status for this remote domain.' ) );
            return false;
        }

        if( (isset( $params['fields']['remote_www'] ) && $params['fields']['remote_www'] !== $existing_arr['remote_www'])
         || (isset( $params['fields']['out_apikey'] ) && $params['fields']['out_apikey'] !== $existing_arr['out_apikey'])
         || (isset( $params['fields']['out_apisecret'] ) && $params['fields']['out_apisecret'] !== $existing_arr['out_apisecret'])
         || (isset( $params['fields']['apikey_id'] ) && (int)$params['fields']['apikey_id'] !== (int)$existing_arr['apikey_id']) ) {
            $params['fields']['status'] = self::STATUS_NOT_CONNECTED;
        }

        if( isset( $params['fields']['out_apikey'] ) ) {
            $out_apikey = trim($params['fields']['out_apikey']);
        } else {
            $out_apikey = $existing_arr['out_apikey'];
        }

        if( isset( $params['fields']['out_apisecret'] ) ) {
            $out_apisecret = trim($params['fields']['out_apisecret']);
        } else {
            $out_apisecret = $existing_arr['out_apisecret'];
        }

        if( empty( $out_apikey ) && empty( $out_apisecret ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide outgoing API key details for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['apikey_id'] ) && empty( $params['fields']['apikey_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide an incoming API key for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['out_timeout'] ) ) {
            $params['fields']['out_timeout'] = (int) $params['fields']['out_timeout'];
        }

        if( isset( $params['fields']['allow_incoming'] ) ) {
            $params['fields']['allow_incoming'] = (!empty($params['fields']['allow_incoming']) ? 1 : 0);
        }

        if( isset( $params['fields']['log_requests'] ) ) {
            $params['fields']['log_requests'] = (!empty($params['fields']['log_requests']) ? 1 : 0);
        }
        if( isset( $params['fields']['log_body'] ) ) {
            $params['fields']['log_body'] = (!empty($params['fields']['log_body']) ? 1 : 0);
        }

        if( !empty( $params['fields']['handle'] ) )
        {
            $check_arr = [];
            $check_arr['handle'] = $params['fields']['handle'];
            $check_arr['id'] = [ 'check' => '!=', 'value' => $existing_arr['id'] ];

            if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a remote domain using provided handle.' ) );
                return false;
            }
        }

        if( !empty( $params['fields']['status'] )
         && (int)$params['fields']['status'] !== (int)$existing_arr['status']
         && (empty( $params['fields']['status_date'] ) || empty_db_date( $params['fields']['status_date'] )) ) {
            $params['fields']['status_date'] = date(self::DATETIME_DB);
        } elseif( !empty( $params['fields']['status_date'] ) ) {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        return $params;
    }

    protected function get_insert_prepare_params_phs_remote_logs( $params )
    {
        if( empty( $params ) || !is_array( $params ) ) {
            return false;
        }

        if( empty( $params['fields']['domain_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide remote domain for this remote domain log.' ) );
            return false;
        }

        if( empty( $params['fields']['route'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a route for this remote domain log.' ) );
            return false;
        }

        if( isset( $params['fields']['out_timeout'] ) ) {
            $params['fields']['out_timeout'] = (int) $params['fields']['out_timeout'];
        }

        if( empty( $params['fields']['type'] )
         || !$this->valid_log_type( $params['fields']['type'] ) ) {
            $params['fields']['type'] = self::LOG_TYPE_OUTGOING;
        }

        if( empty( $params['fields']['status'] )
         || !$this->valid_log_status( $params['fields']['status'] ) ) {
            $params['fields']['status'] = self::LOG_STATUS_SENDING;
        }

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        if( empty( $params['fields']['status_date'] )
         || empty_db_date( $params['fields']['status_date'] ) ) {
            $params['fields']['status_date'] = $params['fields']['cdate'];
        } else {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        return $params;
    }

    protected function get_edit_prepare_params_phs_remote_logs( $existing_arr, $params )
    {
        if( isset( $params['fields']['domain_id'] ) && empty( $params['fields']['domain_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain for this remote domain log.' ) );
            return false;
        }

        if( isset( $params['fields']['route'] ) && empty( $params['fields']['route'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a route for this remote domain log.' ) );
            return false;
        }

        if( isset( $params['fields']['type'] ) && !$this->valid_log_type( $params['fields']['type'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid type for this remote domain log.' ) );
            return false;
        }

        if( isset( $params['fields']['status'] ) && !$this->valid_log_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid status for this remote domain log.' ) );
            return false;
        }

        if( !empty( $params['fields']['status'] )
         && (int)$params['fields']['status'] !== (int)$existing_arr['status']
         && (empty( $params['fields']['status_date'] ) || empty_db_date( $params['fields']['status_date'] )) ) {
            $params['fields']['status_date'] = date(self::DATETIME_DB);
        } elseif( !empty( $params['fields']['status_date'] ) ) {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_count_list_common_params( $params = false )
    {
        if( !empty( $params['flags'] ) && is_array( $params['flags'] ) )
        {
            if( empty( $params['db_fields'] ) ) {
                $params['db_fields'] = '';
            }

            $model_table = $this->get_flow_table_name( $params );
            foreach( $params['flags'] as $flag )
            {
                switch( $flag )
                {
                    case 'include_api_keys_details':

                        /** @var \phs\system\core\models\PHS_Model_Api_keys $api_keys_model */
                        if( !($api_keys_model = PHS::load_model( 'api_keys' ))
                         || !($api_keys_table = $api_keys_model->get_flow_table_name( [ 'table_name' => 'api_keys' ] )) ) {
                            continue 2;
                        }

                        $params['db_fields'] .= ', `'.$api_keys_table.'`.uid AS api_keys_uid, '.
                                                ' `'.$api_keys_table.'`.title AS api_keys_title, '.
                                                ' `'.$api_keys_table.'`.api_key AS api_keys_api_key, '.
                                                ' `'.$api_keys_table.'`.api_secret AS api_keys_api_secret, '.
                                                ' `'.$api_keys_table.'`.allowed_methods AS api_keys_allowed_methods, '.
                                                ' `'.$api_keys_table.'`.denied_methods AS api_keys_denied_methods, '.
                                                ' `'.$api_keys_table.'`.allow_sw AS api_keys_allow_sw, '.
                                                ' `'.$api_keys_table.'`.allowed_ips AS api_keys_allowed_ips, '.
                                                ' `'.$api_keys_table.'`.status AS api_keys_status, '.
                                                ' `'.$api_keys_table.'`.status_date AS api_keys_status_date, '.
                                                ' `'.$api_keys_table.'`.cdate AS api_keys_cdate ';
                        $params['join_sql'] .= ' LEFT JOIN `'.$api_keys_table.'` ON `'.$api_keys_table.'`.id = `'.$model_table.'`.apikey_id ';
                    break;

                    case 'include_domain_details':

                        /** @var \phs\system\core\models\PHS_Model_Api_keys $api_keys_model */
                        if( $params['table_name'] !== 'phs_remote_logs'
                         || !($domains_table = $this->get_flow_table_name( [ 'table_name' => 'phs_remote_domains' ] )) ) {
                            continue 2;
                        }

                        $params['db_fields'] .= ', `'.$domains_table.'`.title AS phs_remote_domains_title, '.
                                                ' `'.$domains_table.'`.handle AS phs_remote_domains_handle, '.
                                                ' `'.$domains_table.'`.remote_www AS phs_remote_domains_remote_www, '.
                                                ' `'.$domains_table.'`.remote_id AS phs_remote_domains_remote_id, '.
                                                ' `'.$domains_table.'`.apikey_id AS phs_remote_domains_apikey_id, '.
                                                ' `'.$domains_table.'`.out_apikey AS phs_remote_domains_out_apikey, '.
                                                ' `'.$domains_table.'`.out_apisecret AS phs_remote_domains_out_apisecret, '.
                                                ' `'.$domains_table.'`.out_timeout AS phs_remote_domains_out_timeout, '.
                                                ' `'.$domains_table.'`.ips_whihtelist AS phs_remote_domains_ips_whihtelist, '.
                                                ' `'.$domains_table.'`.allow_incoming AS phs_remote_domains_allow_incoming, '.
                                                ' `'.$domains_table.'`.log_requests AS phs_remote_domains_log_requests, '.
                                                ' `'.$domains_table.'`.log_body AS phs_remote_domains_log_body, '.
                                                ' `'.$domains_table.'`.source AS phs_remote_domains_source, '.
                                                ' `'.$domains_table.'`.status AS phs_remote_domains_status, '.
                                                ' `'.$domains_table.'`.status_date AS phs_remote_domains_status_date, '.
                                                ' `'.$domains_table.'`.last_incoming AS phs_remote_domains_last_incoming, '.
                                                ' `'.$domains_table.'`.last_outgoing AS phs_remote_domains_last_outgoing, '.
                                                ' `'.$domains_table.'`.error_log AS phs_remote_domains_error_log, '.
                                                ' `'.$domains_table.'`.cdate AS phs_remote_domains_cdate ';
                        $params['join_sql'] .= ' LEFT JOIN `'.$domains_table.'` ON `'.$domains_table.'`.id = `'.$model_table.'`.domain_id ';
                    break;
                }
            }
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) || !is_array( $params )
         || empty( $params['table_name'] ) ) {
            return false;
        }

        $return_arr = [];
        switch( $params['table_name'] )
        {
            case 'phs_remote_domains':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'added_by_uid' => [
                        'type' => self::FTYPE_INT,
                        'comment' => 'Who added this domain',
                    ],
                    'title' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'Short explanatory title',
                    ],
                    'handle' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'Unique programable identifier',
                        'index' => true,
                    ],
                    'remote_www' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index' => true,
                    ],
                    'remote_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'ID of PHS remote domain on remote platform',
                    ],
                    'apikey_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'API key used when receiving requests',
                    ],
                    'out_apikey' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'API key used when sending requests',
                    ],
                    'out_apisecret' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'API secret used when sending requests',
                    ],
                    'out_timeout' => [
                        'type' => self::FTYPE_INT,
                        'comment' => 'Outgoing requests timeout',
                    ],
                    'ips_whihtelist' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Comma separated IPs empty=all',
                    ],
                    'allow_incoming' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Allow incoming requests',
                    ],
                    'log_requests' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Create log records in phs_remote_logs',
                    ],
                    'log_body' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Log request body',
                    ],
                    'source' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                        'comment' => 'How was this domain added',
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_incoming' => [
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Last incoming request',
                    ],
                    'last_outgoing' => [
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Last outgoing request',
                    ],
                    'error_log' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Connection error (if any)',
                    ],
                    'connection_settings' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Connection settings (if any)',
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
            break;

            case 'phs_remote_logs':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'domain_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'Request remote domain',
                    ],
                    'type' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Request type',
                    ],
                    'route' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'Executed route',
                    ],
                    'body' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Request body',
                    ],
                    'error_log' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Request error (if any)',
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
            break;
       }

        return $return_arr;
    }

    /**
     * This function is never used. Its purpose is to have translations for strings which are not translated in used methods
     */
    private function _never_used_only_for_translation(): void
    {
        $this->_pt( 'Not Connected' );
        $this->_pt( 'Waiting Connection' );
        $this->_pt( 'Connected' );
        $this->_pt( 'Connection Error' );
        $this->_pt( 'Suspended' );
        $this->_pt( 'Deleted' );

        $this->_pt( 'Manually' );
        $this->_pt( 'Programmatically' );

        $this->_pt( 'Sending' );
        $this->_pt( 'Sent' );
        $this->_pt( 'Error' );
        $this->_pt( 'Received' );

        $this->_pt( 'Incoming' );
        $this->_pt( 'Outgoing' );
    }
}
