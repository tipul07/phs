<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Session;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Login extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_LOGIN ];
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
        $action_result = self::default_action_result();

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

        PHS::page_settings( 'page_title', $this->_pt( 'Login' ) );

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $nick = PHS_Params::_pg( 'nick', PHS_Params::T_NOHTML );
        $pass = PHS_Params::_p( 'pass', PHS_Params::T_NOHTML );
        $do_remember = PHS_Params::_pg( 'do_remember', PHS_Params::T_INT );
        $do_submit = PHS_Params::_p( 'do_submit' );

        $back_page = PHS_Params::_gp( 'back_page', PHS_Params::T_NOHTML );

        $reason = PHS_Params::_g( 'reason', PHS_Params::T_NOHTML );

        if( ($expired_secs = PHS_Params::_g( 'expired_secs', PHS_Params::T_INT )) )
            PHS_Notifications::add_warning_notice( $this->_pt( 'Your session expired. Please login again into your account.' ) );

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts plugin.' ) );

        if( !empty( $accounts_plugin )
         && !empty( $reason )
         && ($reason_success_text = $accounts_plugin->valid_confirmation_reason( $reason )) )
            PHS_Notifications::add_success_notice( $reason_success_text );

        if( PHS_Params::_g( 'registered', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Account registered and active. You can login now.' ) );
        if( PHS_Params::_g( 'password_changed', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Password changed with success. You can login now.' ) );
        if( PHS_Params::_g( 'confirmation_email', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'An email with your password was sent to email provided in your account details.' ) );

        if( empty( $foobar )
         && PHS::user_logged_in()
         && !PHS_Notifications::have_notifications_errors() )
        {
            $hook_args = PHS_Hooks::default_page_location_hook_args();
            $hook_args['action_result'] = $action_result;

            if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_AFTER_LOGIN, $hook_args ))
            && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
                return $new_hook_args['action_result'];

            PHS_Notifications::add_success_notice( $this->_pt( 'Already logged in...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = (!empty( $back_page )?$back_page:PHS::url());

            return $action_result;
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
        if( !empty( $do_submit )
         && !PHS_Notifications::have_notifications_errors() )
        {
            if( empty( $nick ) || empty( $pass ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Please provide complete mandatory fields.' ) );

            elseif( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );

            elseif( !($account_arr = $accounts_model->get_details_fields( [ 'nick' => $nick ] ))
                 || !$accounts_model->check_pass( $account_arr, $pass )
                 || !$accounts_model->is_active( $account_arr ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Bad username or password.' ) );

            else
            {
                $login_params = [];
                $login_params['expire_mins'] = (!empty( $do_remember )?$plugin_settings['session_expire_minutes_remember']:$plugin_settings['session_expire_minutes_normal']);

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

                    if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_AFTER_LOGIN, PHS_Hooks::default_page_location_hook_args() ))
                     && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
                        return $new_hook_args['action_result'];

                    PHS_Notifications::add_success_notice( $this->_pt( 'Successfully logged in...' ) );

                    $action_result = self::default_action_result();

                    $action_result['redirect_to_url'] = (!empty( $back_page )?from_safe_url( $back_page ):PHS::url());

                    return $action_result;
                }

                if( $accounts_plugin->has_error() )
                    PHS_Notifications::add_error_notice( $accounts_plugin->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error logging in... Please try again.' ) );
            }
        }

        $data = [
            'back_page' => $back_page,
            'nick' => $nick,
            'pass' => $pass,
            'remember_me_session_minutes' => $plugin_settings['session_expire_minutes_remember'],
            'normal_session_minutes' => $plugin_settings['session_expire_minutes_normal'],
            'no_nickname_only_email' => $plugin_settings['no_nickname_only_email'],
            'do_remember' => (!empty( $do_remember )?'checked="checked"':''),
        ];

        return $this->quick_render_template( 'login', $data );
    }
}
