<?php

namespace phs\plugins\mailchimp\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Library;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_utils;

class Mailchimp extends PHS_Library
{
    const ERR_BB_PARSE = 1;

    /** @var \phs\plugins\mailchimp\PHS_Plugin_Mailchimp $_mailchimp_plugin */
    private $_mailchimp_plugin = false;

    private $_settings = array();

    public function __construct( $error_no = self::ERR_OK, $error_msg = '', $error_debug_msg = '', $static_instance = false )
    {
        parent::__construct( $error_no, $error_msg, $error_debug_msg, $static_instance );

        $this->reset_api_settings();
    }

    private function _load_dependencies()
    {
        $this->reset_error();

        if( empty( $this->_mailchimp_plugin )
         or !($this->_mailchimp_plugin = PHS::load_plugin( 'mailchimp' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading MailChimp plugin.' ) );
            return false;
        }

        return true;
    }

    public function get_default_api_settings()
    {
        return array(
            'base_url' => 'api.mailchimp.com/3.0/',
            'dc_server' => '',
            'api_key' => '',

            'rest_url' => '',
            // GET, POST, DELETE, etc...
            'http_method' => 'GET',
            'payload' => false,
        );
    }

    public function reset_api_settings()
    {
        $this->_settings = $this->get_default_api_settings();
    }


    public function api_settings( $key = null, $val = null )
    {
        if( $key === null and $val === null )
            return $this->_settings;

        if( $val === null )
        {
            if( !is_array( $key ) )
            {
                if( !is_scalar( $key )
                 or !isset( $this->_settings[$key] ) )
                    return null;

                return $this->_settings[$key];
            }

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey )
                 or !isset( $this->_settings[$kkey] ) )
                    continue;

                $this->_settings[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key )
         or !isset( $this->_settings[$key] ) )
            return null;

        $this->_settings[$key] = $val;

        return true;
    }

    public function can_connect()
    {
        if( empty( $this->_settings['dc_server'] ) or empty( $this->_settings['api_key'] ) )
            return false;

        return true;
    }

    public function create_list( $list_arr )
    {
        $this->reset_error();

        if( empty( $list_arr ) or !is_array( $list_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide list parameters.' ) );
            return false;
        }

        if( empty( $list_arr['name'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide list name.' ) );
            return false;
        }

        if( empty( $list_arr['contact'] ) or !is_array( $list_arr['contact'] )
         or empty( $list_arr['contact']['company'] )
         or empty( $list_arr['contact']['address1'] )
         or empty( $list_arr['contact']['city'] )
         or empty( $list_arr['contact']['state'] )
         or empty( $list_arr['contact']['zip'] )
         // country ISO 2 chars...
         or empty( $list_arr['contact']['country'] )
        )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide list contact details.' ) );
            return false;
        }

        if( empty( $list_arr['permission_reminder'] ) )
            $list_arr['permission_reminder'] = $this->_pt( 'You\'re receiving this email because you signed up for updates from our site %s.', PHS_SITE_NAME );

        if( empty( $list_arr['campaign_defaults'] ) or !is_array( $list_arr['campaign_defaults'] ) )
            $list_arr['campaign_defaults'] = array();

        if( empty( $list_arr['campaign_defaults']['from_name'] ) )
            $list_arr['campaign_defaults']['from_name'] = PHS_SITE_NAME;
        if( empty( $list_arr['campaign_defaults']['from_email'] )
        and constant( 'PHS_CONTACT_EMAIL' ) )
        {
            $from_email = '';
            if( strpos( PHS_CONTACT_EMAIL, ',' ) === false )
                $from_email = PHS_CONTACT_EMAIL;

            elseif( ($emails_arr = explode( ',', PHS_CONTACT_EMAIL, 2 ))
                and !empty( $emails_arr[0] ) )
                $from_email = $emails_arr[0];

            $list_arr['campaign_defaults']['from_email'] = $from_email;
        }

        if( empty( $list_arr['campaign_defaults']['subject'] ) )
            $list_arr['campaign_defaults']['subject'] = $this->_pt( 'News from %s site', PHS_SITE_NAME );

        if( empty( $list_arr['campaign_defaults']['language'] ) )
            $list_arr['campaign_defaults']['language'] = self::get_current_language();

        if( !isset( $list_arr['email_type_option'] ) )
            $list_arr['email_type_option'] = true;
        else
            $list_arr['email_type_option'] = (!empty( $list_arr['email_type_option'] )?true:false);

        $this->api_settings( 'rest_url', 'lists' );
        $this->api_settings( 'http_method', 'POST' );

        if( !($api_response = $this->_do_call( $list_arr ))
         or empty( $api_response['json_response_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error creating MailChimp list.' ) );

            return false;
        }

        return $api_response['json_response_arr'];
    }

    public function get_list_status( $list_id )
    {
        $this->reset_error();

        $list_id = trim( $list_id );
        if( empty( $list_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a MailChimp list ID.' ) );
            return false;
        }

        $this->api_settings( 'rest_url', 'lists/'.$list_id );
        $this->api_settings( 'http_method', 'GET' );

        if( !($api_response = $this->_do_call())
         or empty( $api_response['json_response_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining details about MailChimp list.' ) );

            return false;
        }

        return $api_response['json_response_arr'];
    }

    private function _do_call( $payload = false, $params = false )
    {
        if( !$this->_load_dependencies() )
            return false;

        $this->reset_error();

        if( !$this->can_connect() )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide MailChimp API parameters.' ) );
            return false;
        }

        $mailchimp_plugin = $this->_mailchimp_plugin;

        $payload_str = '';
        if( !empty( $payload )
        and !($payload_str = @json_encode( $payload )) )
        {
            ob_start();
            var_dump( $payload );
            $buf = ob_get_clean();

            PHS_Logger::logf( 'Couldn\'t obtain JSON from payload: ['.$buf.']', $mailchimp_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain JSON from payload.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['log_payload'] ) )
            $params['log_payload'] = true;
        if( empty( $params['ok_http_code'] ) )
            $params['ok_http_code'] = 200;

        $dc_server = trim( trim( $this->_settings['dc_server'] ), './' );
        $base_url = trim( trim( $this->_settings['base_url'] ), './' );
        $rest_url = trim( trim( $this->_settings['rest_url'] ), './' );

        $api_url = 'https://'.$dc_server.'.'.$base_url.'/'.$rest_url;

        $api_params = array();
        $api_params['userpass'] = array( 'user' => 'something', 'pass' => $this->_settings['api_key'] );
        $api_params['header_keys_arr'] = array(
            'Content-Type' => 'application/json',
        );

        if( !empty( $payload_str ) )
            $api_params['raw_post_str'] = $payload_str;

        if( !($response = PHS_utils::quick_curl( $api_url, $api_params ))
         or empty( $response['http_code'] ) )
        {
            PHS_Logger::logf( 'Error sending request to ['.$api_url.']', $mailchimp_plugin::LOG_CHANNEL );

            if( !empty( $params['request_error_msg'] ) )
                PHS_Logger::logf( 'cURL said: '.$params['request_error_msg'].' (#'.(!empty( $params['request_error_no'] )?$params['request_error_no']:'0').')', $mailchimp_plugin::LOG_CHANNEL );

            if( !empty( $params['log_payload'] ) )
                PHS_Logger::logf( 'Payload: '.$payload_str, $mailchimp_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error sending request to MailChimp server.' ) );
            return false;
        }

        if( $response['http_code'] != $params['ok_http_code'] )
        {
            PHS_Logger::logf( 'Error in response from ['.$api_url.'], http code: '.$response['http_code'], $mailchimp_plugin::LOG_CHANNEL );
            PHS_Logger::logf( 'Response: '.(!empty( $response['response'] )?$response['response']:'N/A'), $mailchimp_plugin::LOG_CHANNEL );

            if( !empty( $params['log_payload'] ) )
                PHS_Logger::logf( 'Payload: '.$payload, $mailchimp_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'MailChimp server responded with an error.' ) );
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
