<?php

namespace phs\plugins\accounts_3rd\actions;

use \phs\PHS;
use phs\PHS_Session;
use phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;

class PHS_Action_Google extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_REGISTER, self::ACT_ROLE_LOGIN ];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_WEB ];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if( !($action = PHS_Params::_gp( 'action', PHS_Params::T_NOHTML ))
         || !in_array( $action, [ 'login', 'register' ], true ) )
            $action = 'login';

        if( $action === 'login' )
            PHS::page_settings( 'page_title', $this->_pt( 'Login with Google' ) );
        else
            PHS::page_settings( 'page_title', $this->_pt( 'Register with Google' ) );

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $accounts_trd_plugin */
        if( !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         || !($accounts_trd_plugin = PHS::load_plugin( 'accounts_3rd' ))
         || !($google_lib = $accounts_trd_plugin->get_google_instance())
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Error loading required resources.' ) );
            return self::default_action_result();
        }

        if( !($google_code = PHS_Params::_gp( 'code', PHS_Params::T_NOHTML )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Invalid Google token. Please try again.' ) );
            return self::default_action_result();
        }

        if( !($account_info = $google_lib->get_web_account_details_by_code( $google_code )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Error obtaining Google account details. Please try again.' ) );
            return self::default_action_result();
        }

        $register_required = false;
        $retry_login = false;
        if( $action === 'login' )
        {
            if( empty( $account_info['email'] ) )
            {
                $retry_login = true;
                PHS_Notifications::add_error_notice( $this->_pt( 'Error obtaining Google account email address. Please try again.' ) );
            } elseif( !($account_arr = $accounts_model->get_details_fields( [ 'email' => $account_info['email'] ] )) )
            {
                $register_required = true;
                PHS_Notifications::add_error_notice( $this->_pt( 'Email address %s is not registered on this platform.', $account_info['email'] ) );
            } elseif( !$accounts_model->is_active( $account_arr ) )
            {
                $retry_login = true;
                PHS_Notifications::add_error_notice( $this->_pt( 'Account linked with this email address is not active.' ).
                                                     $this->_pt( 'Please try logging in using a different email address.' ) );
            } else
            {
                if( !($plugin_settings = $accounts_plugin->get_plugin_settings()) )
                    $plugin_settings = [];

                if( empty( $plugin_settings['session_expire_minutes_normal'] ) )
                    $plugin_settings['session_expire_minutes_normal'] = 0; // till browser closes
                if( empty( $plugin_settings['block_after_expiration'] ) )
                    $plugin_settings['block_after_expiration'] = 0; // hardcoded block

                $login_params = [];
                $login_params['expire_mins'] = $plugin_settings['session_expire_minutes_normal'];

                if( $accounts_plugin->do_login( $account_arr, $login_params ) )
                {
                    if( ($account_language = $accounts_model->get_account_language( $account_arr )) )
                    {
                        if( !($current_language = self::get_current_language())
                         || $current_language !== $account_language )
                        {
                            self::set_current_language( $account_language );
                            PHS_Session::_s( self::LANG_SESSION_KEY, $account_language );
                        }
                    }

                    $action_result = self::default_action_result();

                    $hook_args = PHS_Hooks::default_page_location_hook_args();
                    $hook_args['action_result'] = $action_result;

                    if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_AFTER_LOGIN, $hook_args ))
                    && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
                        return $new_hook_args['action_result'];

                    $action_result['redirect_to_url'] = PHS::url();

                    return $action_result;
                }

                if( $accounts_plugin->has_error() )
                    PHS_Notifications::add_error_notice( $accounts_plugin->get_simple_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error logging in... Please try again.' ) );
            }
        } elseif( $action === 'register' )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Register flow not implemented yet.' ) );
        }

        $action_result = self::default_action_result();

        ob_start();
        var_dump( $action );

        $action_result['buffer'] = ob_get_clean();

        return $action_result;
    }
}
