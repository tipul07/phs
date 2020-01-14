<?php

namespace phs\plugins\hubspot\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Library;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_utils;

class PHS_Hubspot extends PHS_Library
{
    const ERR_SETTINGS = 1;

    /** @var \phs\plugins\hubspot\PHS_Plugin_Hubspot $_hubspot_plugin */
    private $_hubspot_plugin = false;

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

        if( empty( $this->_hubspot_plugin )
        and !($this->_hubspot_plugin = PHS::load_plugin( 'hubspot' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading HubSpot plugin.' ) );
            return false;
        }

        return true;
    }

    public function valid_api_settings( $settings_arr )
    {
        if( empty( $settings_arr ) or !is_array( $settings_arr )
         or empty( $settings_arr['base_url'] ) or empty( $settings_arr['api_key'] ) )
            return false;

        return true;
    }

    public function get_default_api_settings()
    {
        return array(
            'source' => 'default',
            'base_url' => 'https://api.hubapi.com/',
            'api_key' => '',
            'timeout' => 30,
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

    protected function _extract_api_info()
    {
        if( !$this->_load_dependencies() )
            return false;

        if( !($settings_arr = $this->_hubspot_plugin->get_plugin_settings()) )
        {
            $this->set_error( self::ERR_SETTINGS, $this->_pt( 'Couldn\'t obtain HubSpot plugin settings.' ) );
            return false;
        }

        if( empty( $settings_arr['hubspot_api_url'] ) )
            $settings_arr['hubspot_api_url'] = '';
        if( empty( $settings_arr['hubspot_api_key'] ) )
            $settings_arr['hubspot_api_key'] = '';

        if( $settings_arr['hubspot_api_url'] !== '' )
            $settings_arr['hubspot_api_url'] = rtrim( $settings_arr['hubspot_api_url'], '/' ).'/';

        $this->_api_settings['source'] = 'plugin';
        $this->_api_settings['base_url'] = $settings_arr['hubspot_api_url'];
        $this->_api_settings['api_key'] = $settings_arr['hubspot_api_key'];
        $this->_api_settings['timeout'] = (!empty( $settings_arr['hubspot_api_timeout'] )?$settings_arr['hubspot_api_timeout']:30);

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

    protected function _can_connect()
    {
        if( empty( $this->_api_settings['base_url'] ) or empty( $this->_api_settings['api_key'] ) )
            return false;

        return true;
    }

    /**
     * @param bool|array $payload_arr
     * @param bool|array $params
     *
     * @return array|bool
     */
    private function _do_call( $payload_arr = false, $params = false )
    {
        if( !$this->_load_dependencies() )
            return false;

        $this->reset_error();

        if( !$this->_can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide HubSpot API parameters.' ) );
            return false;
        }

        $hubspot_plugin = $this->_hubspot_plugin;

        $payload_str = '';
        if( !empty( $payload_arr )
        and !($payload_str = @json_encode( $payload_arr )) )
        {
            ob_start();
            var_dump( $payload_arr );
            $buf = ob_get_clean();

            PHS_Logger::logf( 'Couldn\'t obtain JSON from payload: ['.$buf.']', $hubspot_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain JSON from payload.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['log_not_found_response'] ) )
            $params['log_not_found_response'] = false;
        else
            $params['log_not_found_response'] = (!empty( $params['log_not_found_response'] ));

        if( empty( $params['log_payload'] ) )
            $params['log_payload'] = false;
        else
            $params['log_payload'] = (!empty( $params['log_payload'] ));
        if( empty( $params['ok_http_code'] ) or !is_array( $params['ok_http_code'] ) )
            $params['ok_http_code'] = array( 200, 204 );
        else
            $params['ok_http_code'] = self::extract_integers_from_array( $params['ok_http_code'] );

        if( empty( $params['extra_get_params'] ) or !is_array( $params['extra_get_params'] ) )
            $params['extra_get_params'] = array();

        $base_url = rtrim( trim( $this->_api_settings['base_url'] ), '/' );
        $rest_url = trim( trim( $this->_api_params['rest_url'] ), '/' );

        $api_url = $base_url.'/'.$rest_url;

        $extra_get_params = $params['extra_get_params'];
        $extra_get_params['hapikey'] = $this->_api_settings['api_key'];

        $api_params = array();
        $api_params['http_method'] = $this->_api_params['http_method'];
        $api_params['extra_get_params'] = $extra_get_params;
        $api_params['header_keys_arr'] = array(
            'Content-Type' => 'application/json',
        );

        if( !empty( $payload_str ) )
        {
            $api_params['raw_post_str'] = $payload_str;
            $this->_api_params( 'payload', $payload_str );
        }

        if( !($response = PHS_utils::quick_curl( $api_url, $api_params ))
         or empty( $response['http_code'] ) )
        {
            PHS_Logger::logf( 'Error sending request to ['.$api_url.']', $hubspot_plugin::LOG_CHANNEL );

            if( !empty( $response['request_error_msg'] ) )
                PHS_Logger::logf( 'cURL said: '.$response['request_error_msg'].' (#'.(!empty( $response['request_error_no'] )?$response['request_error_no']:'0').')', $hubspot_plugin::LOG_CHANNEL );

            if( !empty( $params['log_payload'] ) )
                PHS_Logger::logf( 'Payload: '.$payload_str, $hubspot_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error sending request to HubSpot server.' ) );
            return false;
        }

        $response['json_response_arr'] = array();
        
        if( isset( $response['http_code'] ) )
            $response['http_code'] = (int)$response['http_code'];
        else
            $response['http_code'] = 0;

        if( empty( $response['response'] ) )
            $response_arr = array();
        else
            $response_arr = @json_decode( $response['response'], true );

        $response['json_response_arr'] = $response_arr;

        if( !empty( $response_arr )
        and !empty( $response_arr['status'] )
        and $response_arr['status'] === 'error' )
        {
            $short_error = '';
            if( !empty( $response_arr['message'] ) )
                $short_error = (!empty( $response_arr['category'] )?$response_arr['category'].': ':'').$response_arr['message'];

            if( !empty( $params['log_not_found_response'] )
            and $this->api_response_is_not_found( $response ) )
            {
                $long_error = '';
                if( !empty( $response_arr['errors'] ) and is_array( $response_arr['errors'] ) )
                {
                    $knti = 1;
                    foreach( $response_arr['errors'] as $error_arr )
                    {
                        if( empty( $error_arr ) or !is_array( $error_arr )
                         or empty( $error_arr['message'] ) )
                            continue;

                        $long_error .= $knti.'. '.$error_arr['message']."\n";
                        $knti++;
                    }
                }


                PHS_Logger::logf( 'Error in response from ['.$api_url.'], http code: '.$response['http_code'].', error: '.(!empty( $short_error )?$short_error:'N/A'), $hubspot_plugin::LOG_CHANNEL );
                if( !empty( $long_error ) )
                    PHS_Logger::logf( 'Errors: '.$long_error, $hubspot_plugin::LOG_CHANNEL );
                else
                    PHS_Logger::logf( 'Response: '.(!empty( $response['response'] )?$response['response']:'N/A'), $hubspot_plugin::LOG_CHANNEL );

                if( !empty( $params['log_payload'] ) )
                    PHS_Logger::logf( 'Payload: '.$payload_str, $hubspot_plugin::LOG_CHANNEL );
            }

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'HubSpot server responded with an error.%s', (!empty( $short_error )?' '.$short_error:'') ) );
            return false;
        }

        if( !$this->api_response_is_ok( $response, $params['ok_http_code'] ) )
        {
            if( $this->api_response_is_not_found( $response ) )
            {
                // Special case for not found...
                return $response;
            }

            PHS_Logger::logf( 'Error in response from ['.$api_url.'], http code: '.$response['http_code'], $hubspot_plugin::LOG_CHANNEL );
            PHS_Logger::logf( 'Response: '.(!empty( $response['response'] )?$response['response']:'N/A'), $hubspot_plugin::LOG_CHANNEL );

            if( !empty( $params['log_payload'] ) )
                PHS_Logger::logf( 'Payload: '.$payload_str, $hubspot_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'HubSpot server responded with an error.' ) );
            return false;
        }

        return $response;
    }

    /**
     * @param array $api_response
     * @return bool
     */
    public function api_response_is_not_found( $api_response )
    {
        if( empty( $api_response ) or !is_array( $api_response )
         or empty( $api_response['http_code'] ) or (int)$api_response['http_code'] === 404 )
            return true;

        return false;
    }

    /**
     * @param array $api_response
     * @param array|bool $http_ok_codes
     * @return bool
     */
    public function api_response_is_ok( $api_response, $http_ok_codes = false )
    {
        if( empty( $http_ok_codes ) or !is_array( $http_ok_codes ) )
            $http_ok_codes = array( 200, 204 );

        if( empty( $api_response ) or !is_array( $api_response )
         or empty( $api_response['http_code'] )
         or !in_array( (int)$api_response['http_code'], $http_ok_codes, true ) )
            return false;

        return true;
    }

    /**
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function manage_api_request_params( $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['call_parameters'] ) or !is_array( $params['call_parameters'] ) )
            $params['call_parameters'] = false;

        if( empty( $params['hubspot_settings'] ) or !is_array( $params['hubspot_settings'] ) )
            $params['hubspot_settings'] = false;

        elseif( !$this->valid_api_settings( $params['hubspot_settings'] ) )
        {
            $this->set_error( self::ERR_SETTINGS, $this->_pt( 'Invalid HubSpot settings passed to API call request.' ) );
            return false;
        }

        $this->reset_api_settings();
        if( !empty( $params['hubspot_settings'] ) )
        {
            $params['hubspot_settings']['source'] = 'call';
            $this->api_settings( $params['hubspot_settings'] );
        } else
            $this->_extract_api_info();

        return $params;
    }

    //
    ///region Contacts methods
    //
    /**
     * @param int $contact_id
     * @param bool|array $params
     *
     * @return bool|array
     */
    public function get_contact_by_id( $contact_id, $params = false )
    {
        $this->reset_error();
        $this->reset_api_params();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !($params = $this->manage_api_request_params( $params )) )
            return false;

        $this->_api_params( array(
            'rest_url' => 'contacts/v1/contact/vid/'.$contact_id.'/profile',
            'http_method' => 'GET',
        ) );

        if( !($response = $this->_do_call( false, $params['call_parameters'] )) )
            return false;

        if( empty( $response['json_response_arr'] ) or !is_array( $response['json_response_arr'] ) )
            return array();

        return $response['json_response_arr'];
    }

    /**
     * @param string $email
     * @param bool|array $params
     *
     * @return bool|array Returns false on error, empty array if account is not found and a populated array if account exists
     */
    public function get_contact_by_email( $email, $params = false )
    {
        $this->reset_error();
        $this->reset_api_params();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !($params = $this->manage_api_request_params( $params )) )
            return false;

        $this->_api_params( array(
            'rest_url' => 'contacts/v1/contact/email/'.trim( $email ).'/profile',
            'http_method' => 'GET',
        ) );

        if( !($response = $this->_do_call( false, $params['call_parameters'] )) )
            return false;

        if( empty( $response['json_response_arr'] ) or !is_array( $response['json_response_arr'] ) )
            return array();

        return $response['json_response_arr'];
    }

    /**
     * @param bool|array $list_params
     * @param bool|array $params
     *
     * @return bool|array
     */
    public function get_contacts_list( $list_params = false, $params = false )
    {
        $this->reset_error();
        $this->reset_api_params();

        if( !($params = $this->manage_api_request_params( $params )) )
            return false;

        if( empty( $list_params ) or !is_array( $list_params ) )
            $list_params = array();

        if( !empty( $list_params['count'] ) )
        {
            $list_params['count'] = (int)$list_params['count'];

            if( $list_params['count'] > 100 )
                $list_params['count'] = 100;
        }

        if( !empty( $list_params['vidOffset'] ) )
            $list_params['vidOffset'] = (int)$list_params['vidOffset'];


        $api_params = array(
            'rest_url' => 'contacts/v1/lists/all/contacts/all',
            'http_method' => 'GET',
        );

        $this->_api_params( $api_params );

        $params['extra_get_params'] = $list_params;

        if( !($response = $this->_do_call( false, $params['call_parameters'] )) )
            return false;

        if( empty( $response['json_response_arr'] ) or !is_array( $response['json_response_arr'] ) )
            return array();

        return $response['json_response_arr'];
    }
    //
    ///endregion Contacts methods
    //

    //
    ///region Companies methods
    //
    /**
     * @param int $company_id
     * @param bool|array $params
     *
     * @return bool|array
     */
    public function get_company_by_id( $company_id, $params = false )
    {
        $this->reset_error();
        $this->reset_api_params();

        $company_id = (int)$company_id;
        if( empty( $company_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide company ID to get details.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !($params = $this->manage_api_request_params( $params )) )
            return false;

        $this->_api_params( array(
            'rest_url' => 'companies/v2/companies/'.$company_id,
            'http_method' => 'GET',
        ) );

        if( !($response = $this->_do_call( false, $params['call_parameters'] )) )
            return false;

        if( empty( $response['json_response_arr'] ) or !is_array( $response['json_response_arr'] ) )
            return array();

        return $response['json_response_arr'];
    }

    /**
     * @param int $company_id
     * @param bool|array $params
     *
     * @return bool|array
     */
    public function get_company_contacts_by_id( $company_id, $params = false )
    {
        $this->reset_error();
        $this->reset_api_params();

        $company_id = (int)$company_id;
        if( empty( $company_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide company ID to get contacts.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !($params = $this->manage_api_request_params( $params )) )
            return false;

        $this->_api_params( array(
            'rest_url' => '/companies/v2/companies/'.$company_id.'/contacts',
            'http_method' => 'GET',
        ) );

        if( !($response = $this->_do_call( false, $params['call_parameters'] )) )
            return false;

        if( empty( $response['json_response_arr'] ) or !is_array( $response['json_response_arr'] ) )
            return array();

        return $response['json_response_arr'];
    }
    //
    ///endregion Companies methods
    //

}
