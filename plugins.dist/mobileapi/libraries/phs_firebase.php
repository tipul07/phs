<?php

namespace phs\plugins\mobileapi\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Library;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Utils;

class PHS_Firebase extends PHS_Library
{
    // Maximum of tokens to send notification to (allowed by Firebase)
    const MAX_TOKENS_IN_NOTIFICATION = 1000;

    const ERR_DEPENDENCIES = 1;

    /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $_mobileapi_plugin */
    private $_mobileapi_plugin = false;

    private $_api_settings = array();
    private $_api_params = array();

    public function __construct( $error_no = self::ERR_OK, $error_msg = '', $error_debug_msg = '', $static_instance = false )
    {
        parent::__construct( $error_no, $error_msg, $error_debug_msg, $static_instance );

        $this->reset_api_settings();
        $this->reset_api_params();
    }

    private function _load_dependencies()
    {
        $this->reset_error();

        if( empty( $this->_mobileapi_plugin )
        and !($this->_mobileapi_plugin = PHS::load_plugin( 'mobileapi' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading MobileAPI plugin.' ) );
            return false;
        }

        return true;
    }

    protected function _extract_api_settings()
    {
        if( !$this->_load_dependencies() )
            return false;

        if( !($settings_arr = $this->_mobileapi_plugin->get_plugin_settings()) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t obtain Firebase API settings from plugin.' ) );
            return false;
        }

        if( empty( $settings_arr['fcm_base_url'] )
         or empty( $settings_arr['fcm_auth_key'] ) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Invalid Firebase plugin settings.' ) );
            return false;
        }

        $this->_api_settings['fcm_base_url'] = rtrim( $settings_arr['fcm_base_url'], '/' ).'/';
        $this->_api_settings['fcm_auth_key'] = $settings_arr['fcm_auth_key'];
        $this->_api_settings['fcm_api_timeout'] = (!empty( $settings_arr['fcm_api_timeout'] )?$settings_arr['fcm_api_timeout']:30);

        return true;
    }

    public function get_default_api_settings()
    {
        return array(
            'fcm_base_url' => 'https://fcm.googleapis.com',
            'fcm_auth_key' => '',
            'fcm_api_timeout' => 30,
        );
    }

    public function get_default_api_params()
    {
        return array(
            'rest_url' => '',
            // GET, POST, DELETE, etc...
            'http_method' => 'GET',
            'payload' => false,
        );
    }

    public function reset_api_settings()
    {
        $this->_api_settings = $this->get_default_api_settings();
    }

    public function reset_api_params()
    {
        $this->_api_params = $this->get_default_api_params();
    }

    public function api_settings( $key = null, $val = null )
    {
        if( $key === null and $val === null )
            return $this->_api_settings;

        if( $val === null )
        {
            if( !is_array( $key ) )
            {
                if( !is_scalar( $key )
                 or !isset( $this->_api_settings[$key] ) )
                    return null;

                return $this->_api_settings[$key];
            }

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey )
                 or !isset( $this->_api_settings[$kkey] ) )
                    continue;

                $this->_api_settings[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key )
         or !isset( $this->_api_settings[$key] ) )
            return null;

        $this->_api_settings[$key] = $val;

        return true;
    }

    public function get_api_params()
    {
        return $this->_api_params;
    }

    private function _api_params( $key = null, $val = null )
    {
        if( $key === null and $val === null )
            return $this->_api_params;

        if( $val === null )
        {
            if( !is_array( $key ) )
            {
                if( !is_scalar( $key )
                 or !isset( $this->_api_params[$key] ) )
                    return null;

                return $this->_api_params[$key];
            }

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey )
                 or !isset( $this->_api_params[$kkey] ) )
                    continue;

                $this->_api_params[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key )
         or !isset( $this->_api_params[$key] ) )
            return null;

        $this->_api_params[$key] = $val;

        return true;
    }

    public function can_connect()
    {
        if( empty( $this->_api_settings['fcm_base_url'] ) or empty( $this->_api_settings['fcm_auth_key'] ) )
            return false;

        return true;
    }

    /**
     * @param string|array $token
     * @param array $payload_arr
     * @param bool|array $envelope_arr
     *
     * @return bool|mixed
     */
    public function send_notification( $token, $payload_arr, $envelope_arr = false )
    {
        $this->reset_error();

        if( empty( $payload_arr ) or !is_array( $payload_arr )
         or (
            (empty( $payload_arr['data'] ) or !is_array( $payload_arr['data'] ))
            and
            (empty( $payload_arr['notification'] ) or !is_array( $payload_arr['notification'] ))
        ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide data and/or notification in payload.' ) );
            return false;
        }

        if( is_string( $token ) )
            $token = trim( $token );

        elseif( !is_array( $token ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a device token or an array of tokens.' ) );
            return false;
        } else
        {
            // Array of tokens
            $token = self::extract_strings_from_array( $token, array( 'trim_parts' => true, 'dump_empty_parts' => true ) );
            if( count( $token ) > self::MAX_TOKENS_IN_NOTIFICATION )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'You can provide up to %s tokens for a notification.', self::MAX_TOKENS_IN_NOTIFICATION ) );
                return false;
            }
        }

        if( empty( $token ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a device token or an array of device tokens.' ) );
            return false;
        }

        if( empty( $envelope_arr ) or !is_array( $envelope_arr ) )
            $envelope_arr = array();

        $this->_api_params( 'rest_url', 'fcm/send/' );
        $this->_api_params( 'http_method', 'POST' );

        $full_payload_arr = $envelope_arr;
        if( is_string( $token ) )
            $full_payload_arr['to'] = $token;
        else
            $full_payload_arr['registration_ids'] = $token;

        if( !empty( $payload_arr['data'] ) )
            $full_payload_arr['data'] = $payload_arr['data'];
        if( !empty( $payload_arr['notification'] ) )
            $full_payload_arr['notification'] = $payload_arr['notification'];

        if( !($api_response = $this->_do_call( $full_payload_arr ))
         or empty( $api_response['json_response_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error sending notification using Firebase.' ) );

            return false;
        }

        return $api_response['json_response_arr'];
    }

    /**
     * @param bool|array $payload
     * @param bool|array $params
     *
     * @return array|bool
     */
    private function _do_call( $payload = false, $params = false )
    {
        if( !$this->_extract_api_settings() )
            return false;

        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide Firebase API parameters.' ) );
            return false;
        }

        $mobileapi_plugin = $this->_mobileapi_plugin;

        $payload_str = '';
        if( !empty( $payload )
        and !($payload_str = @json_encode( $payload )) )
        {
            ob_start();
            var_dump( $payload );
            $buf = ob_get_clean();

            PHS_Logger::logf( 'Couldn\'t obtain JSON from payload: ['.$buf.']', $mobileapi_plugin::LOG_FIREBASE );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain JSON from payload.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['log_payload'] ) )
            $params['log_payload'] = true;
        if( empty( $params['ok_http_codes'] ) or !is_array( $params['ok_http_codes'] ) )
            $params['ok_http_codes'] = array( 200 );

        $base_url = trim( trim( $this->_api_settings['fcm_base_url'] ), './' );
        $rest_url = trim( trim( $this->_api_params['rest_url'] ), './' );

        $api_url = $base_url.'/'.$rest_url;

        $api_params = array();
        $api_params['http_method'] = $this->_api_params['http_method'];
        $api_params['header_keys_arr'] = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'key='.$this->_api_settings['fcm_auth_key'],
        );

        if( !empty( $payload_str ) )
        {
            $api_params['raw_post_str'] = $payload_str;
            $this->_api_params( 'payload', $payload_str );
        }

        if( !($response = PHS_Utils::quick_curl( $api_url, $api_params ))
         or empty( $response['http_code'] ) )
        {
            PHS_Logger::logf( 'Error sending request to ['.$api_url.']', $mobileapi_plugin::LOG_FIREBASE );

            if( !empty( $params['request_error_msg'] ) )
                PHS_Logger::logf( 'cURL said: '.$params['request_error_msg'].' (#'.(!empty( $params['request_error_no'] )?$params['request_error_no']:'0').')', $mobileapi_plugin::LOG_FIREBASE );

            if( !empty( $params['log_payload'] ) )
                PHS_Logger::logf( 'Payload: '.$payload_str, $mobileapi_plugin::LOG_FIREBASE );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error sending request to Firebase server.' ) );
            return false;
        }

        if( !in_array( (int)$response['http_code'], $params['ok_http_codes'], true ) )
        {
            PHS_Logger::logf( 'Error in response from ['.$api_url.'], http code: '.$response['http_code'], $mobileapi_plugin::LOG_FIREBASE );
            PHS_Logger::logf( 'Response: '.(!empty( $response['response'] )?$response['response']:'N/A'), $mobileapi_plugin::LOG_FIREBASE );

            if( !empty( $params['log_payload'] ) )
                PHS_Logger::logf( 'Payload: '.$payload_str, $mobileapi_plugin::LOG_FIREBASE );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Firebase server responded with an error.' ) );
            return false;
        }

        if( empty( $response['response'] ) )
            $response_arr = array();
        else
            $response_arr = @json_decode( $response['response'], true );

        $response['json_response_arr'] = $response_arr;

        return $response;
    }

}
