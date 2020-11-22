<?php

namespace phs\plugins\mobileapi\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Api;
use \phs\libraries\PHS_Action;

class PHS_Action_Account_edit extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_EDIT_PROFILE );
    }

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
         or empty( $session_data['session_arr'] )
         or empty( $session_data['account_arr'] ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'Not authenticated.' ) ) )
            {
                $this->set_error( $api_obj::ERR_API_INIT, $this->_pt( 'Not authenticated.' ) );
                return false;
            }

            exit;
        }

        if( !($request_arr = PHS_Api::get_request_body_as_json_array()) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_UNAUTHORIZED, $this->_pt( 'Please provide account details you want to change.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Please provide account details you want to change.' ) );
                return false;
            }

            exit;
        }

        $account_arr = $session_data['account_arr'];
        $session_arr = $session_data['session_arr'];

        if( false === ($result = $mobile_plugin->import_api_data_for_account_data( $account_arr, $request_arr ))
        and $mobile_plugin->has_error() )
        {
            if( !($error_msg = $mobile_plugin->get_simple_error_message()) )
                $error_msg = $this->_pt( 'Error saving account details.' );

            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $error_msg ) )
            {
                $this->set_error( $api_obj::ERR_FUNCTIONALITY, $error_msg );
                return false;
            }

            exit;
        }

        $response_arr = $mobile_plugin->export_data_account_and_session( $account_arr['id'], $session_arr['id'] );
        $response_arr['profile_saved'] = true;

        $action_result = self::default_action_result();

        $action_result['api_json_result_array'] = $response_arr;

        return $action_result;
    }
}
