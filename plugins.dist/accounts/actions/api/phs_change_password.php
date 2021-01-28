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

class PHS_Action_Change_password extends PHS_Api_action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_CHANGE_PASSWORD ];
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
                                          $this->_pt( 'API change password should use mobileapi plugin.' ) );
        }

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_CHANGE_PASSWORD_ACTION_START, $hook_args ))
        && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
        {
            $action_result = self::validate_array( $new_hook_args['action_result'], self::default_action_result() );

            if( !empty( $new_hook_args['stop_execution'] ) )
            {
                $this->set_action_result( $action_result );

                return $action_result;
            }
        }

        if( !($current_user = PHS::user_logged_in()) )
        {
            return $this->send_api_success( [ 'account' => false, 'password_changed' => false ] );
        }

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
        // Accept parameters only in POST or JSON body
        $old_pass = $this->request_var( 'old_pass', PHS_Params::T_ASIS, null, false, 'bp' );
        $pass = $this->request_var( 'pass', PHS_Params::T_ASIS, null, false, 'bp' );

        if( empty( $old_pass )
         || !$accounts_model->check_pass( $current_user, $old_pass ) )
        {
            return $this->send_api_error( PHS_Api_base::H_CODE_FORBIDDEN, $accounts_model::ERR_PASS_CHECK,
                                          $this->_pt( 'Wrong current password.' ) );
        }

        $edit_arr = [];
        $edit_arr['pass'] = $pass;

        $edit_params_arr = [];
        $edit_params_arr['fields'] = $edit_arr;

        if( !($new_account = $accounts_model->edit( $current_user, $edit_params_arr )) )
        {
            if( $accounts_plugin->has_error() )
                $error_msg = $accounts_plugin->get_error_message();
            else
                $error_msg = $this->_pt( 'Error changing account password. Please try again.' );

            return $this->send_api_error( PHS_Api_base::H_CODE_BAD_REQUEST, $accounts_model::ERR_CHANGE_PASS, $error_msg );
        }

        $current_user = $new_account;

        if( !($user_payload_arr = $accounts_model->populate_account_data_for_account_contract( $current_user ))
         || !($user_payload_arr = $account_contract->parse_data_from_inside_source( $user_payload_arr )) )
            $user_payload_arr = [];

        $payload_arr = [];
        $payload_arr['account'] = $user_payload_arr;
        $payload_arr['password_changed'] = true;

        return $this->send_api_success( $payload_arr );
    }
}
