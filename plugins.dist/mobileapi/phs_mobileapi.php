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

    const LOG_CHANNEL = 'mobileapi.log', LOG_FIREBASE = 'firebase.log';

    const H_EXPORT_ACCOUNT_DATA = 'phs_mobileapi_export_account_data', H_EXPORT_SESSION_DATA = 'phs_mobileapi_export_session_data',
          // export account and session details to 3rd party apps
          H_EXPORT_ACCOUNT_SESSION = 'phs_mobileapi_export_account_session';

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
            // default template
            'fcm_base_url' => array(
                'display_name' => 'Firebase API URL',
                'display_hint' => 'URL where plugin will make the call to send push notifications using Firebase library.',
                'type' => PHS_params::T_ASIS,
                'default' => 'https://fcm.googleapis.com',
            ),
            'fcm_auth_key' => array(
                'display_name' => $this->_pt( 'Firebase Authentication Key' ),
                'display_hint' => $this->_pt( 'Key used for authentication when sending push notification using Firebase library.' ),
                'type' => PHS_params::T_ASIS,
                'default' => '',
            ),
            'fcm_api_timeout' => array(
                'display_name' => $this->_pt( 'Firebase API Timeout' ),
                'display_hint' => $this->_pt( 'After how many seconds should request to Firebase server timeout.' ),
                'type' => PHS_params::T_INT,
                'default' => 30,
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

    public static function default_export_account_and_session_hook_args()
    {
        return PHS_Hooks::hook_args_definition( array(
            'account_data' => false,
            'session_data' => false,
            'extra_export_fields' => false,
        ) );
    }

    public static function default_export_account_data_hook_args()
    {
        return PHS_Hooks::hook_args_definition( array(
            'account_data' => false,
            'export_fields' => false,
            'extra_export_fields' => false,
        ) );
    }

    /**
     * Standard definition of a data node to be exported as response to a 3rd party request
     * @return array
     */
    public static function get_default_export_node_details()
    {
        return array(
            // Key/Index of the node when exporting to outside reuqests
            'key' => '',
            // Type of data to be exported (useful when exporting to type-oriented languages)
            'type' => PHS_params::T_ASIS,
            // Extra parameters used in PHS_params::set_type()
            'type_extra' => false,
            // Default value when exporting
            'default' => null,
            // Don't export missing keys/indexes or export them with default value
            'export_if_not_found' => false,
        );
    }

    /**
     * Normalize an array with definitions of data nodes to be exported to a 3rd party request
     * @param array $definition_arr Array definition to be normalized
     * @return array Normalized array
     */
    public static function normalize_definition_of_export_nodes( $definition_arr )
    {
        if( empty( $definition_arr ) or !is_array( $definition_arr ) )
            return array();

        $node_definition = self::get_default_export_node_details();
        $return_arr = array();
        foreach( $definition_arr as $int_key => $node_arr )
        {
            if( !isset( $node_arr['key'] )
             or (string)$node_arr['key'] === ''
             or !is_scalar( $node_arr['key'] ) )
                $node_arr['key'] = $int_key;

            $return_arr[$int_key] = self::validate_array( $node_arr, $node_definition );
        }

        return $return_arr;
    }

    public static function export_array_data_with_definition_as_array( $data_arr, $definition_arr )
    {
        if( empty( $data_arr ) or !is_array( $data_arr ) )
            return array();

        $definition_arr = self::normalize_definition_of_export_nodes( $definition_arr );
        $return_arr = array();
        foreach( $definition_arr as $int_key => $node_arr )
        {
            if( !isset( $node_arr['key'] )
             or (string)$node_arr['key'] === '' )
                continue;

            if( array_key_exists( $int_key, $data_arr ) )
                $return_arr[$node_arr['key']] = PHS_params::set_type( $data_arr[$int_key], $node_arr['type'],
                    (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) );

            elseif( !empty( $node_arr['export_if_not_found'] ) )
                $return_arr[$node_arr['key']] = $return_arr['default'];
        }

        return $return_arr;
    }

    public static function export_data_account_fields()
    {
        return array(
            'id' => array(
                'key' => 'id',
                'type' => PHS_params::T_INT,
                'default' => 0,
            ),
            'email' => array(
                'key' => 'email',
                'type' => PHS_params::T_EMAIL,
                'default' => '',
            ),
            'email_verified' => array(
                'key' => 'email_verified',
                'type' => PHS_params::T_INT,
                'default' => 0,
            ),
            'status' => array(
                'key' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
            ),
            'status_date' => array(
                'key' => 'status_date',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => PHS_Model::DATETIME_DB ),
                'default' => null,
            ),
            'level' => array(
                'key' => 'level',
                'type' => PHS_params::T_INT,
                'default' => 0,
            ),
            'lastlog' => array(
                'key' => 'lastlog',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => PHS_Model::DATETIME_DB ),
                'default' => null,
            ),
            'lastip' => array(
                'key' => 'lastip',
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            'cdate' => array(
                'key' => 'cdate',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => PHS_Model::DATETIME_DB ),
                'default' => null,
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
                $fields_arr = self::merge_array_assoc( $fields_arr, $hook_args['extra_export_fields'] );
            }
        }

        return self::export_array_data_with_definition_as_array( $account_arr, $fields_arr );
    }

    public function export_data_account_and_session( $account_data, $session_data )
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or !($online_model = PHS::load_model( 'api_online', 'mobileapi' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load required models.' ) );
            return false;
        }

        if( empty( $account_data ) )
            $account_arr = null;

        elseif( !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Account details not found in database.' ) );
            return false;
        }

        if( empty( $session_data ) )
            $session_arr = null;

        elseif( !($session_arr = $online_model->data_to_array( $session_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Session details not found in database.' ) );
            return false;
        }

        if( !empty( $account_arr ) )
            $account_arr = $this->export_data_from_account_data( $account_arr );
        if( !empty( $session_arr ) )
            $session_arr = $online_model->export_data_from_session_data( $session_arr );

        $export_arr = array(
            'session_data' => $session_arr,
            'account_data' => $account_arr,
        );

        $hook_args = self::default_export_account_and_session_hook_args();
        $hook_args['account_data'] = $account_arr;
        $hook_args['session_data'] = $session_arr;

        if( ($hook_result = PHS::trigger_hooks( self::H_EXPORT_ACCOUNT_SESSION, $hook_args ))
        and is_array( $hook_result ) )
        {
            if( !empty( $hook_result['account_data'] )
            and is_array( $hook_result['account_data'] ) and !empty( $hook_result['account_data']['id'] ) )
            {
                $account_arr = $hook_result['account_data'];
                $export_arr['account_data'] = $account_arr;
            }

            if( !empty( $hook_result['session_data'] )
            and is_array( $hook_result['session_data'] ) and !empty( $hook_result['session_data']['id'] ) )
            {
                $session_arr = $hook_result['session_data'];
                $export_arr['session_data'] = $session_arr;
            }

            // old keys might also be overwritten
            if( !empty( $hook_result['extra_export_fields'] ) and is_array( $hook_result['extra_export_fields'] ) )
            {
                foreach( $hook_result['extra_export_fields'] as $key => $val )
                {
                    if( in_array( $key, array( 'account_data', 'session_data' ) ) )
                        continue;

                    $export_arr[$key] = $val;
                }
            }
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

    /**
     * @param int|array $account_data
     * @param array $payload_arr
     * @param bool|array $params
     *
     * @return bool|array Returns false or error or a list of devices to which notification was sent and errors (if any)
     */
    public function push_notification_to_user( $account_data, $payload_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading accounts model.' ) );
            return false;
        }

        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $apionline_model */
        if( !($apionline_model = PHS::load_model( 'api_online', 'mobileapi' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading API online model.' ) );
            return false;
        }

        if( empty( $account_data )
         or !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Account not found in database.' ) );
            return false;
        }

        $params['apionline_model'] = $apionline_model;

        if( empty( $params['devices_params'] )
         or !is_array( $params['devices_params'] ) )
            $params['devices_params'] = array();

        $return_arr = array();
        $return_arr['errors'] = array();
        $return_arr['devices'] = array();

        if( !($devices_arr = $apionline_model->get_account_devices( $account_arr, $params['devices_params'] )) )
        {
            if( $apionline_model->has_error() )
            {
                $this->copy_error( $apionline_model );
                return false;
            }

            return $return_arr;
        }

        $return_arr['devices'] = $devices_arr;
        foreach( $devices_arr as $device_id => $device_arr )
        {
            if( !($send_result = $this->push_notification_to_device( $device_arr, $payload_arr, $params )) )
            {
                $error_msg = $this->_pt( 'Error sending notification to device.' );
                if( $this->has_error() )
                    $error_msg = $this->get_simple_error_message();

                $return_arr['errors'][$device_id] = $error_msg;
            }
        }

        return $return_arr;
    }

    /**
     * @param int|array $device_data
     * @param array $payload_arr
     * @param bool|array $params
     *
     * @return bool|void
     */
    public function push_notification_to_device( $device_data, $payload_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['apionline_model'] )
        and !($params['apionline_model'] = PHS::load_model( 'api_online', 'mobileapi' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading API online model.' ) );
            return false;
        }

        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $apionline_model */
        $apionline_model = $params['apionline_model'];

        if( !($devices_flow = $apionline_model->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t initiate device module flow.' ) );
            return false;
        }

        if( !($device_arr = $apionline_model->data_to_array( $device_data, $devices_flow )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Device not found in database.' ) );
            return false;
        }

        if( empty( $device_arr['device_token'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Device doesn\'t have a token set.' ) );
            return false;
        }

        return $this->push_notification_to_token( $device_arr['device_token'], $payload_arr, $params );
    }

    /**
     * @param string $token_str
     * @param array $payload_arr
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function push_notification_to_token( $token_str, $payload_arr, $params = false )
    {
        $this->reset_error();

        if( !($firebase_obj = $this->get_firebase_instance()) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading Firebase library.' ) );

            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        // Envelope ar keys which will be added in root of payload sent to Firebase
        // (eg. collapse_key, priority, time_to_live, etc)
        // @see https://firebase.google.com/docs/cloud-messaging/http-server-ref
        if( empty( $params['envelope'] ) or !is_array( $params['envelope'] ) )
            $params['envelope'] = false;

        if( !($result = $firebase_obj->send_notification( $token_str, $payload_arr, $params['envelope'] )) )
        {
            if( $firebase_obj->has_error() )
                $this->copy_error( $firebase_obj );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error sending push notification using Firebase library.' ) );

            return false;
        }

        return $result;
    }

    /**
     * Returns an instance of PHS_Firebase class
     *
     * @return bool|\phs\plugins\mobileapi\libraries\PHS_Firebase
     */
    public function get_firebase_instance()
    {
        static $library_obj = null;

        if( $library_obj !== null )
            return $library_obj;

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\mobileapi\\libraries\\PHS_Firebase';
        $library_params['as_singleton'] = true;

        /** @var \phs\plugins\mobileapi\libraries\PHS_Firebase $loaded_library */
        if( !($loaded_library = $this->load_library( 'phs_firebase', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading Firebase library.' ) );

            return false;
        }

        $library_obj = $loaded_library;

        return $library_obj;
    }
}
