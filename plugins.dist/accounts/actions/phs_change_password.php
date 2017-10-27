<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\PHS_bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Change_password extends PHS_Action
{

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Change Password' ) );

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        $forgot_account_arr = false;
        if( !($current_user = PHS::user_logged_in()) )
        {
            if( !($confirmation_param = PHS_params::_gp( $accounts_plugin::PARAM_CONFIRMATION, PHS_params::T_NOHTML )) )
            {
                PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

                $action_result = self::default_action_result();

                $action_result['request_login'] = true;

                return $action_result;
            }

            if( !($confirmation_parts = $accounts_plugin->decode_confirmation_param( $confirmation_param ))
             or empty( $confirmation_parts['account_data'] ) or empty( $confirmation_parts['reason'] )
             or $confirmation_parts['reason'] != $accounts_plugin::CONF_REASON_FORGOT )
            {
                if( $accounts_plugin->has_error() )
                    PHS_Notifications::add_error_notice( $accounts_plugin->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t interpret confirmation parameter. Please try again.' ) );
            } else
                $forgot_account_arr = $confirmation_parts['account_data'];
        }

        if( !($accounts_settings = $accounts_plugin->get_plugin_settings()) )
            $accounts_settings = array();
        
        if( empty( $accounts_settings['min_password_length'] ) )
        {
            if( !empty( $accounts_model ) )
                $accounts_settings['min_password_length'] = $accounts_model::DEFAULT_MIN_PASSWORD_LENGTH;
            else
                $accounts_settings['min_password_length'] = 8;
        }

        if( PHS_params::_g( 'password_changed', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Password changed with success.' ) );

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $pass = PHS_params::_p( 'pass', PHS_params::T_ASIS );
        $pass1 = PHS_params::_p( 'pass1', PHS_params::T_ASIS );
        $pass2 = PHS_params::_p( 'pass2', PHS_params::T_ASIS );

        $do_submit = PHS_params::_p( 'do_submit' );

        if( !empty( $do_submit )
        and !PHS_Notifications::have_notifications_errors() )
        {
            if( (!empty( $current_user ) and empty( $pass ))
             or empty( $pass1 ) or empty( $pass2 ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Please provide mandatory fields.' ) );

            elseif( !empty( $current_user ) and !$accounts_model->check_pass( $current_user, $pass ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Wrong current password.' ) );

            elseif( $pass1 != $pass2 )
                PHS_Notifications::add_error_notice( $this->_pt( 'Passwords mismatch.' ) );

            else
            {
                $edit_arr = array();
                $edit_arr['pass'] = $pass1;

                $edit_params_arr = array();
                $edit_params_arr['fields'] = $edit_arr;

                if( ($new_account = $accounts_model->edit( (!empty( $current_user )?$current_user:$forgot_account_arr), $edit_params_arr )) )
                {
                    PHS_Notifications::add_success_notice( $this->_pt( 'Changes saved...' ) );

                    $action_result = self::default_action_result();

                    if( !empty( $current_user ) )
                        $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'change_password' ), array( 'password_changed' => 1 ) );
                    else
                    {
                        $args_arr = array();
                        $args_arr['password_changed'] = 1;
                        if( !empty( $forgot_account_arr ) )
                            $args_arr['nick'] = $forgot_account_arr['nick'];

                        $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args_arr );
                    }

                    return $action_result;
                } else
                {
                    if( $accounts_model->has_error() )
                        PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( $this->_pt( 'Error changing password. Please try again.' ) );
                }
            }
        }

        $url_extra_args = array();
        if( !empty( $forgot_account_arr ) )
        {
            if( !($confirmation_parts = $accounts_plugin->get_confirmation_params( $forgot_account_arr, $accounts_plugin::CONF_REASON_FORGOT, array( 'link_expire_seconds' => 3600 ) ))
             or empty( $confirmation_parts['confirmation_param'] ) or empty( $confirmation_parts['pub_key'] ) )
            {
                $url_extra_args = array( $accounts_plugin::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param'] );
            } elseif( !empty( $confirmation_param ) )
                $url_extra_args = array( $accounts_plugin::PARAM_CONFIRMATION => $confirmation_param );
        }

        $data = array(
            'url_extra_args' => $url_extra_args,
            'nick' => (!empty( $current_user )?$current_user['nick']:'N/A'),
            'pass' => $pass,
            'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
            'min_password_length' => $accounts_settings['min_password_length'],
            'password_regexp' => $accounts_settings['password_regexp'],
        );

        return $this->quick_render_template( 'change_password', $data );
    }
}
