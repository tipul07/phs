<?php

namespace phs\plugins\mobileapi;

use \phs\PHS;
use \phs\PHS_api;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Hooks;

class PHS_Plugin_Mobileapi extends PHS_Plugin
{
    const ERR_DEPENDENCIES = 60000;

    const AGENT_JOB_HANDLE = 'mobileapi_index_bg_check_api_sessions_ag',
          AGENT_CHECK_SESSIONS_SECS = 3600; // check expired 3rd party session once every hour

    const LOG_CHANNEL = 'mobileapi.log';

    const H_EXPORT_ACCOUNT_DATA = 'phs_mobileapi_export_account_data', H_EXPORT_SESSION_DATA = 'phs_mobileapi_export_session_data';

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.0';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'vendor_id' => 'phs',
            'vendor_name' => 'PHS',
            'name' => 'Basic Mobile API',
            'description' => 'Handles basic functionality related to 3rd party (mobile) API calls.',
        );
    }

    public function get_models()
    {
        return array( 'api_online' );
    }

    /**
     * @inheritdoc
     */
    public function get_agent_jobs_definition()
    {
        return array(
            self::AGENT_JOB_HANDLE => array(
                'title' => 'Check mobile 3rd party sessions',
                'route' => array(
                    'plugin' => 'mobileapi',
                    'controller' => 'index_bg',
                    'action' => 'check_api_sessions_ag'
                ),
                'timed_seconds' => self::AGENT_CHECK_SESSIONS_SECS,
            ),
        );
    }

    public static function api_session( $session_params = null )
    {
        static $session_data = false;

        if( $session_params === null )
            return $session_data;

        if( empty( $session_params ) )
        {
            $session_data = false;
            return true;
        }

        if( !is_array( $session_params ) )
            return false;

        if( empty( $session_data ) or !is_array( $session_data ) )
            $session_data = array();

        if( isset( $session_params['session_arr'] ) )
            $session_data['session_arr'] = ((!empty( $session_params['session_arr'] ) and is_array( $session_params['session_arr'] ))?$session_params['session_arr']:false);
        if( isset( $session_params['account_arr'] ) )
            $session_data['account_arr'] = ((!empty( $session_params['account_arr'] ) and is_array( $session_params['account_arr'] ))?$session_params['account_arr']:false);

        if( empty( $session_data ) )
            $session_data = false;

        return $session_data;
    }

    public static function default_export_account_data_hook_args()
    {
        return self::validate_array_recursive( array(
            'account_data' => false,
            'export_fields' => false,
            'extra_export_fields' => false,
        ), PHS_Hooks::default_common_hook_args() );
    }

    public static function export_data_account_fields()
    {
        return array(
            'id' => array(
                'key' => 'id',
                'type' => PHS_params::T_INT,
            ),
            'email' => array(
                'key' => 'email',
                'type' => PHS_params::T_EMAIL,
            ),
            'email_verified' => array(
                'key' => 'email_verified',
                'type' => PHS_params::T_INT,
            ),
            'status' => array(
                'key' => 'status',
                'type' => PHS_params::T_INT,
            ),
            'status_date' => array(
                'key' => 'status_date',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => PHS_Model::DATETIME_DB ),
            ),
            'level' => array(
                'key' => 'level',
                'type' => PHS_params::T_INT,
            ),
            'lastlog' => array(
                'key' => 'lastlog',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => PHS_Model::DATETIME_DB ),
            ),
            'lastip' => array(
                'key' => 'lastip',
                'type' => PHS_params::T_NOHTML,
            ),
            'cdate' => array(
                'key' => 'cdate',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => PHS_Model::DATETIME_DB ),
            ),
        );
    }

    public function export_data_from_account_data( $account_data )
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load required models.' ) );
            return false;
        }

        if( empty( $account_data )
         or !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Account details not found in database.' ) );
            return false;
        }

        $fields_arr = self::export_data_account_fields();

        $hook_args = self::default_export_account_data_hook_args();
        $hook_args['account_data'] = $account_arr;
        $hook_args['export_fields'] = $fields_arr;

        if( ($hook_result = PHS::trigger_hooks( self::H_EXPORT_ACCOUNT_DATA, $hook_args ))
        and is_array( $hook_result ) )
        {
            if( !empty( $hook_args['account_data'] )
            and is_array( $hook_args['account_data'] ) and !empty( $hook_args['account_data']['id'] ) )
                $account_arr = $hook_args['account_data'];

            // old keys might also be overwritten
            if( !empty( $hook_args['extra_export_fields'] ) and is_array( $hook_args['extra_export_fields'] ) )
            {
                foreach( $hook_args['extra_export_fields'] as $field => $field_arr )
                {
                    if( !is_array( $field_arr ) )
                        $field_arr = array();

                    if( empty( $field_arr['key'] ) )
                        $field_arr['key'] = $field;

                    if( empty( $field_arr['type'] ) )
                        $field_arr['type'] = PHS_params::T_ASIS;

                    $fields_arr[$field] = $field_arr;
                }
            }
        }

        $export_arr = array();
        foreach( $fields_arr as $field => $field_arr )
        {
            if( !array_key_exists( $field, $account_arr ) )
                continue;

            $export_arr[$field_arr['key']] = PHS_params::set_type( $account_arr[$field], $field_arr['type'],
                (!empty( $field_arr['type_extra'] )?$field_arr['type_extra']:false) );
        }

        return $export_arr;
    }

    public function do_api_authentication( $params )
    {
        $this->reset_error();

        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($online_model = PHS::load_model( 'api_online', 'mobileapi' ))
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t load required models.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Authentication parameters not provided.' ) );
            return false;
        }

        $params = self::validate_array( $params, PHS_api::default_api_authentication_callback_params() );

        if( empty( $params['api_obj'] ) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'API object not provided to authentication method.' ) );
            return false;
        }

        /** @var \phs\PHS_api $api_obj */
        $api_obj = $params['api_obj'];

        if( !($api_key = $api_obj->api_flow_value( 'api_user' ))
         or !($api_secret = $api_obj->api_flow_value( 'api_pass' ))
         or !($session_arr = $online_model->get_session_by_apikey( $api_key, array( 'include_device_data' => true ) ))
         // If we cannot obtain session device might be unlinked from account... ask user to login again...
         or !$online_model->get_session_device( $session_arr )
         or !$online_model->check_session_authentication( $session_arr, $api_secret ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Not authorized.' ) );
                return false;
            }

            exit;
        }

        if( empty( $session_arr['uid'] )
         or !($account_arr = $accounts_model->get_details( $session_arr['uid'], array( 'table_name' => 'users' ) ))
         or !$accounts_model->is_active( $accounts_model ) )
            $account_arr = null;

        $session_params = array();
        $session_params['session_arr'] = $session_arr;
        $session_params['account_arr'] = $account_arr;

        self::api_session( $session_params );

        return true;
    }
}
