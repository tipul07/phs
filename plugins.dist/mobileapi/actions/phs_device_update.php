<?php

namespace phs\plugins\mobileapi\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Api;
use \phs\libraries\PHS_Action;

class PHS_Action_Device_update extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_API );
    }

    public function execute()
    {
        /** @var \phs\PHS_Api $api_obj */
        if( !($api_obj = PHS_Api::global_api_instance()) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining API instance.' ) );
            return false;
        }

        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($online_model = PHS::load_model( 'api_online', 'mobileapi' ))
         or !($mobile_plugin = PHS::load_plugin( 'mobileapi' ))
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $this->_pt( 'Couldn\'t load required models.' ) ) )
            {
                $this->set_error( $api_obj::ERR_API_INIT, $this->_pt( 'Couldn\'t load required models.' ) );
                return false;
            }

            exit;
        }

        if( !($session_data = $mobile_plugin::api_session())
         or empty( $session_data['session_arr'] ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'Not authenticated.' ) ) )
            {
                $this->set_error( $api_obj::ERR_API_INIT, $this->_pt( 'Not authenticated.' ) );
                return false;
            }

            exit;
        }

        $session_arr = $session_data['session_arr'];

        if( !($device_arr = $online_model->get_session_device( $session_arr )) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_NOT_FOUND, $this->_pt( 'Device not found in database.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Device not found in database.' ) );
                return false;
            }

            exit;
        }

        if( !($request_arr = PHS_Api::get_request_body_as_json_array())
         or empty( $request_arr['device_type'] )
         or empty( $request_arr['device_token'] )
         or !$online_model->valid_device_type( $request_arr['device_type'] )
         or empty( $device_arr['device_type'] ) or (int)$device_arr['device_type'] !== (int)$request_arr['device_type']
         or empty( $device_arr['device_token'] ) or (string)$device_arr['device_token'] !== (string)$request_arr['device_token'] )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'Please provide device details.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Please provide device details.' ) );
                return false;
            }

            exit;
        }

        //        $device_data = array();
        //        if( !empty( $request_arr ) )
        //        {
        //            $device_info_keys = array(
        //                'device_type' => $online_model::DEV_TYPE_UNDEFINED,
        //                'device_name' => '',
        //                'device_version' => '',
        //                'device_token' => '',
        //                'lat' => 0,
        //                'long' => 0,
        //            );
        //            foreach( $device_info_keys as $field => $def_value )
        //            {
        //                if( array_key_exists( $field, $request_arr ) )
        //                    $device_data[$field] = $request_arr[$field];
        //            }
        //        }

        $device_data = $mobile_plugin::import_api_data_with_definition_as_array( $request_arr, $online_model::get_api_data_device_fields() );

        $new_device_arr = false;
        if( !empty( $device_data )
        and !($new_device_arr = $online_model->update_device( $device_data, $device_arr )) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $this->_pt( 'Error updating device.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Error updating device.' ) );
                return false;
            }

            exit;
        }

        if( !empty( $new_device_arr ) )
            $device_arr = $new_device_arr;

        $session_arr[$online_model::DEVICE_KEY] = $device_arr;

        // In case user already logged out and we have a "late" request or user was inactivated between requests...
        if( empty( $device_arr['uid'] )
         or !($account_arr = $accounts_model->get_details( $device_arr['uid'], array( 'table_name' => 'users' ) ))
         or !$accounts_model->is_active( $account_arr ) )
            $account_arr = false;

        $action_result = self::default_action_result();

        $action_result['api_json_result_array'] = $mobile_plugin->export_data_account_and_session( $account_arr, $session_arr );

        return $action_result;
    }
}
