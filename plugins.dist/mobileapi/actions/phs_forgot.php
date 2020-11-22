<?php

namespace phs\plugins\mobileapi\actions;

use \phs\PHS;
use phs\PHS_Bg_jobs;
use \phs\PHS_Scope;
use \phs\PHS_Api;
use \phs\libraries\PHS_Action;

class PHS_Action_Forgot extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_FORGOT_PASSWORD );
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

        if( !($request_arr = PHS_Api::get_request_body_as_json_array())
         or empty( $request_arr['email'] ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_BAD_REQUEST, $this->_pt( 'Please provide credentials.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Please provide credentials.' ) );
                return false;
            }

            exit;
        }

        if( !($account_arr = $accounts_model->get_details_fields( array( 'email' => $request_arr['email'], 'status' => $accounts_model::STATUS_ACTIVE ) )) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_NOT_FOUND, $this->_pt( 'Invalid account.' ) ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $this->_pt( 'Invalid account.' ) );
                return false;
            }

            exit;
        }

        if( !PHS_Bg_jobs::run( array( 'plugin' => 'accounts', 'action' => 'forgot_password_bg' ), array( 'uid' => $account_arr['id'] ) ) )
        {
            if( self::st_has_error() )
                $error_msg = self::st_get_error_message();
            else
                $error_msg = $this->_pt( 'Error sending forgot password email. Please try again.' );

            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $error_msg ) )
            {
                $this->set_error( $api_obj::ERR_AUTHENTICATION, $error_msg );
                return false;
            }

            exit;
        }

        $action_result = self::default_action_result();

        $response_arr = array(
            'email_queued' => true,
        );

        $action_result['api_json_result_array'] = $response_arr;

        return $action_result;
    }
}
