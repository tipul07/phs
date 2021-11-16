<?php

namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Bg_jobs;
use phs\PHS_Scope;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\libraries\PHS_Api_action;

class PHS_Action_Forgot extends PHS_Api_action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_FORGOT_PASSWORD ];
    }

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

        if( !($request_arr = PHS_Api::get_request_body_as_json_array()) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                                          $this->_pt( 'Invalid account.' ) );
        }

        if( empty( $request_arr['email'] )
         || !($account_arr = $accounts_model->get_details_fields( [ 'email' => $request_arr['email'], 'status' => $accounts_model::STATUS_ACTIVE ] )) )
        {
            // Because of security reasons, let them think it's ok
            return $this->send_api_success(
                [ 'email_queued' => true ],
                PHS_Api_base::H_CODE_OK,
                false,
                [ 'only_response_data_node' => true ]
            );
        }

        if( !PHS_Bg_jobs::run( [ 'p' => 'accounts', 'c' => 'index_bg', 'a' => 'forgot_password_bg' ], [ 'uid' => $account_arr['id'] ] ) )
        {
            if( self::st_has_error() )
                $error_msg = self::st_get_simple_error_message();
            else
                $error_msg = $this->_pt( 'Error sending forgot password email. Please try again.' );

            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $error_msg );
        }

        return $this->send_api_success( [ 'email_queued' => true ] );
    }
}
