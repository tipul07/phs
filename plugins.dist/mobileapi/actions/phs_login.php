<?php

namespace phs\plugins\mobileapi\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Api;
use \phs\libraries\PHS_Action;

class PHS_Action_Login extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_LOGIN ];
    }

    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_API ];
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
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'No session.' ) ) )
            {
                $this->set_error( $api_obj::ERR_API_INIT, $this->_pt( 'No session.' ) );
                return false;
            }

            exit;
        }

        $session_arr = $session_data['session_arr'];

        if( !($request_arr = PHS_Api::get_request_body_as_json_array())
         or empty( $request_arr['nick'] )
         or empty( $request_arr['pass'] )
         or empty( $request_arr['device_info'] ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'Please provide credentials.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Please provide credentials.' ) );
                return false;
            }

            exit;
        }

        if( !($account_arr = $accounts_model->get_details_fields( [ 'nick' => $request_arr['nick'] ] ))
         or !$accounts_model->check_pass( $account_arr, $request_arr['pass'] )
         or !$accounts_model->is_active( $account_arr ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'Authentication failed.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Authentication failed.' ) );
                return false;
            }

            exit;
        }

        //        $device_data = array();
        //        if( !empty( $request_arr['device_info'] ) )
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
        //                if( array_key_exists( $field, $request_arr['device_info'] ) )
        //                    $device_data[$field] = $request_arr['device_info'][$field];
        //            }
        //        }

        $device_data = $mobile_plugin::import_api_data_with_definition_as_array( $request_arr['device_info'], $online_model::get_api_data_device_fields() );

        if( !($new_session_arr = $online_model->update_session( $session_arr, $device_data, $account_arr['id'], [ 'regenerate_keys' => true ] )) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $this->_pt( 'Error updating session.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Error updating session.' ) );
                return false;
            }

            exit;
        }

        $session_arr = $new_session_arr;

        $action_result = self::default_action_result();

        $action_result['api_json_result_array'] = $mobile_plugin->export_data_account_and_session( $account_arr, $session_arr );

        return $action_result;
    }
}
