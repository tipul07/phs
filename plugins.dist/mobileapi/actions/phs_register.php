<?php

namespace phs\plugins\mobileapi\actions;

use phs\libraries\PHS_Hooks;
use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_api;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Register extends PHS_Action
{
    const ERR_DEPENDENCIES = 1, ERR_MODEL_DATA = 2, ERR_REGISTRATION = 3;

    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_REGISTER );
    }

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_API );
    }

    public function execute()
    {
        /** @var \phs\PHS_api $api_obj */
        if( !($api_obj = PHS_api::global_api_instance()) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining API instance.' ) );
            return false;
        }

        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($online_model = PHS::load_model( 'api_online', 'mobileapi' ))
         or !($mobile_plugin = PHS::load_plugin( 'mobileapi' ))
         or !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $this->_pt( 'Couldn\'t load required models.' ) ) )
            {
                $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t load required models.' ) );
                return false;
            }

            exit;
        }

        $cuser_arr = PHS::account_structure( PHS::user_logged_in() );

        if( !PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_REGISTER ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_NOT_IMPLEMENTED, $this->_pt( 'Registration is closed for this site.' ) ) )
            {
                $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Registration is closed for this site.' ) );
                return false;
            }

            exit;
        }

        if( !($request_arr = PHS_api::get_request_body_as_json_array())
         or empty( $request_arr['nick'] )
         or empty( $request_arr['email'] )
         or !isset( $request_arr['pass'] ) )
        {
            if( !$api_obj->send_header_response( $api_obj::H_CODE_BAD_REQUEST, $this->_pt( 'Please provide required fields.' ) ) )
            {
                $this->set_error( self::ERR_MODEL_DATA, $this->_pt( 'Please provide required fields.' ) );
                return false;
            }

            exit;
        }

        if( empty( $request_arr['pass'] ) )
            $request_arr['pass'] = '';

        $insert_arr = array();
        $insert_arr['nick'] = $request_arr['nick'];
        $insert_arr['pass'] = $request_arr['pass'];
        $insert_arr['email'] = $request_arr['email'];
        $insert_arr['level'] = $accounts_model::LVL_MEMBER;
        $insert_arr['lastip'] = request_ip();

        if( !($account_arr = $accounts_model->insert( array( 'fields' => $insert_arr ) )) )
        {
            if( $accounts_model->has_error() )
                $error_msg = $accounts_model->get_error_message();
            else
                $error_msg = $this->_pt( 'Couldn\'t register user. Please try again.' );

            if( !$api_obj->send_header_response( $api_obj::H_CODE_INTERNAL_SERVER_ERROR, $error_msg ) )
            {
                $this->set_error( self::ERR_REGISTRATION, $error_msg );
                return false;
            }

            exit;
        }

        $action_result = self::default_action_result();

        $response_arr = array(
            'registered' => true,
            'account_data' => $mobile_plugin->export_data_from_account_data( $account_arr ),
        );

        // trigger hook to populate with other details if required

        $action_result['api_json_result_array'] = $response_arr;

        return $action_result;
    }
}
