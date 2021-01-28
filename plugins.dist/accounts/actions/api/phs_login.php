<?php

namespace phs\plugins\accounts\actions\api;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Session;
use \phs\PHS_Api_base;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Api_action;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Login extends PHS_Api_action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_LOGIN ];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        $current_scope = PHS_Scope::current_scope();

        if( $current_scope === PHS_Scope::SCOPE_API )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_FORBIDDEN, self::ERR_PARAMETERS,
                                          $this->_pt( 'API login should use mobileapi plugin.' ) );
        }

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_LOGIN_ACTION_START, $hook_args ))
        && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
        {
            $action_result = self::validate_array( $new_hook_args['action_result'], self::default_action_result() );

            if( !empty( $new_hook_args['stop_execution'] ) )
            {
                $this->set_action_result( $action_result );

                return $action_result;
            }
        }

        // Accept parameters only in POST or JSON body
        $nick = $this->request_var( 'nick', PHS_Params::T_NOHTML, null, false, 'bp' );
        $pass = $this->request_var( 'pass', PHS_Params::T_ASIS, null, false, 'bp' );
        $do_remember = $this->request_var( 'do_remember', PHS_Params::T_INT, null, false, 'bp' );

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts\contracts\PHS_Contract_Account_basic $account_contract */
        if( !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         || !($account_contract = PHS::load_contract( 'account_basic', 'accounts' )) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                                          $this->_pt( 'Error loading required resources.' ) );
        }

        if( ($current_user = PHS::user_logged_in()) )
        {
            // We just trigger functionality for after login, but we ignore action result
            PHS::trigger_hooks( PHS_Hooks::H_USERS_AFTER_LOGIN, PHS_Hooks::default_page_location_hook_args() );

            if( !($account_arr = $accounts_model->populate_account_data_for_account_contract( $current_user ))
             || !($account_data = $account_contract->parse_data_from_inside_source( $account_arr )) )
                $account_data = null;

            return $this->send_api_success( $account_data );
        }

        if( !($plugin_settings = $this->get_plugin_settings()) )
            $plugin_settings = [];

        if( empty( $plugin_settings['session_expire_minutes_remember'] ) )
            $plugin_settings['session_expire_minutes_remember'] = 43200; // 30 days
        if( empty( $plugin_settings['session_expire_minutes_normal'] ) )
            $plugin_settings['session_expire_minutes_normal'] = 0; // till browser closes
        if( empty( $plugin_settings['block_after_expiration'] ) )
            $plugin_settings['block_after_expiration'] = 0; // hardcoded block

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($account_arr = $accounts_model->get_details_fields( [ 'nick' => $nick ] ))
         || !$accounts_model->check_pass( $account_arr, $pass )
         || !$accounts_model->is_active( $account_arr ) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, $accounts_model::ERR_LOGIN,
                                          $this->_pt( 'Bad user or password.' ) );
        }

        $login_params = [];
        $login_params['expire_mins'] = (!empty( $do_remember )?$plugin_settings['session_expire_minutes_remember']:$plugin_settings['session_expire_minutes_normal']);

        if( !$accounts_plugin->do_login( $account_arr, $login_params ) )
        {
            if( $accounts_plugin->has_error() )
                $error_msg = $accounts_plugin->get_error_message();
            else
                $error_msg = $this->_pt( 'Error logging in. Please try again.' );

            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, $accounts_model::ERR_LOGIN, $error_msg );
        }

        if( ($account_language = $accounts_model->get_account_language( $account_arr )) )
        {
            if( !($current_language = self::get_current_language())
             || $current_language !== $account_language )
            {
                self::set_current_language( $account_language );
                PHS_Session::_s( self::LANG_SESSION_KEY, $account_language );
            }
        }

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_AFTER_LOGIN, PHS_Hooks::default_page_location_hook_args() ))
         && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
            return $new_hook_args['action_result'];

        if( !($user_payload_arr = $accounts_model->populate_account_data_for_account_contract( $account_arr ))
         || !($user_payload_arr = $account_contract->parse_data_from_inside_source( $user_payload_arr )) )
            $user_payload_arr = [];

        $payload_arr = [];
        $payload_arr['account'] = $user_payload_arr;
        $payload_arr['account_logged_in'] = true;

        return $this->send_api_success( $payload_arr );
    }
}
