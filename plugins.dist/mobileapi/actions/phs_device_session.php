<?php

namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;

class PHS_Action_Device_session extends PHS_Api_action
{
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_API ];
    }

    public function execute()
    {
        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($online_model = PHS::load_model( 'api_online', 'mobileapi' ))
         || !($mobile_plugin = PHS::load_plugin( 'mobileapi' ))
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $this->_pt( 'Error loading required resources.' ) );
        }

        if( !($request_arr = PHS_Api::get_request_body_as_json_array())
         || empty( $request_arr['device_type'] )
         || !$online_model->valid_device_type( $request_arr['device_type'] )
         || empty( $request_arr['device_token'] ) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                                          $this->_pt( 'Please provide device details.' ) );
        }

        $device_data = $mobile_plugin::import_api_data_with_definition_as_array( $request_arr, $online_model::get_api_data_device_fields() );
        $device_data['uid'] = 0;

        if( !($session_arr = $online_model->generate_session( $device_data )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $this->_pt( 'Error generating session.' ) );
        }

        return $this->send_api_success(
            $mobile_plugin->export_data_account_and_session( null, $session_arr )
        );
    }
}
