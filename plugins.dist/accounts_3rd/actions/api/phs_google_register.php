<?php

namespace phs\plugins\accounts_3rd\actions\api;

use \phs\PHS;
use phs\PHS_Api;
use phs\PHS_Session;
use phs\PHS_Api_base;
use phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use phs\libraries\PHS_Api_action;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;

class PHS_Action_Google_register extends PHS_Api_action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_REGISTER ];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_API ];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $accounts_trd_plugin */
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobile_plugin */
        /** @var \phs\plugins\mobileapi\models\PHS_Model_Api_online $online_model */
        if( !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         || !($mobile_plugin = PHS::load_plugin( 'mobileapi' ))
         || !($online_model = PHS::load_model( 'api_online', 'mobileapi' ))
         || !($accounts_trd_plugin = PHS::load_plugin( 'accounts_3rd' ))
         || !($google_lib = $accounts_trd_plugin->get_google_instance())
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $this->_pt( 'Error loading required resources.' ) );
        }

        if( !($platform = $this->request_var( 'platform', PHS_Params::T_NOHTML, '' ))
         || !($platform = $google_lib->valid_auth_platform( $platform )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                                          $this->_pt( 'Invalid authentication platform.' ) );
        }

        if( !($google_code = $this->request_var( 'code', PHS_Params::T_NOHTML, '' )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                                          $this->_pt( 'Invalid Google token.' ) );
        }

        if( !($request_arr = PHS_Api::get_request_body_as_json_array())
         || empty( $request_arr['device_info'] ) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_AUTHENTICATION,
                                          $this->_pt( 'Please provide device info.' ) );
        }

        if( !($session_data = $mobile_plugin::api_session())
         || empty( $session_data['session_arr'] ) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_UNAUTHORIZED, self::ERR_API_INIT,
                                          $this->_pt( 'No session.' ) );
        }
        $session_arr = $session_data['session_arr'];

        if( !($account_info = $google_lib->get_mobile_account_details_by_code( $google_code, $platform ))
         || !is_array( $account_info ) || empty( $account_info['email'] ) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $this->_pt( 'Error obtaining Google account details.' ) );
        }

        if( !($account_arr = $accounts_model->get_details_fields( [ 'email' => $account_info['email'] ] )) )
        {
            $fields_arr = [];
            $fields_arr['nick'] = $account_info['email'];
            $fields_arr['email'] = $account_info['email'];
            $fields_arr['pass'] = '';
            $fields_arr['level'] = $accounts_model::LVL_MEMBER;
            $fields_arr['status'] = $accounts_model::STATUS_ACTIVE;
            $fields_arr['lastip'] = request_ip();

            $insert_arr = $accounts_model->fetch_default_flow_params( [ 'table_name' => 'users' ] );
            $insert_arr['fields'] = $fields_arr;

            if( !($account_arr = $accounts_model->insert( $insert_arr )) )
            {
                if( $accounts_model->has_error() )
                    $error_msg = $accounts_model->get_simple_error_message();
                else
                    $error_msg = $this->_pt( 'Couldn\'t register user. Please try again.' );

                return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                              $error_msg );
            }
        } elseif( !$accounts_model->is_active( $account_arr ) )
        {
            // Account not found, and also we should not create new account...
            return $this->send_api_error( PHS_Api_base::H_CODE_NOT_FOUND, self::ERR_PARAMETERS,
                                          $this->_pt( 'Registration failed.' ) );
        }

        $device_data = $mobile_plugin::import_api_data_with_definition_as_array( $request_arr['device_info'], $online_model::get_api_data_device_fields() );
        if( !($new_session_arr = $online_model->update_session( $session_arr, $device_data, $account_arr['id'], [ 'regenerate_keys' => true ] )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $this->_pt( 'Error updating session.' ) );
        }
        $session_arr = $new_session_arr;

        return $this->send_api_success(
            $mobile_plugin->export_data_account_and_session( $account_arr, $session_arr )
        );
    }
}
