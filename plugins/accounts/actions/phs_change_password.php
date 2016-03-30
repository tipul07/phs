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
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( self::_t( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
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

        $password_changed = PHS_params::_g( 'password_changed', PHS_params::T_NOHTML );

        if( !empty( $password_changed ) )
            PHS_Notifications::add_success_notice( self::_t( 'Password changed with success.' ) );

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $pass = PHS_params::_p( 'pass', PHS_params::T_ASIS );
        $pass1 = PHS_params::_p( 'pass1', PHS_params::T_ASIS );
        $pass2 = PHS_params::_p( 'pass2', PHS_params::T_ASIS );

        $submit = PHS_params::_p( 'submit' );

        if( !empty( $submit ) )
        {
            if( empty( $pass ) or empty( $pass1 ) or empty( $pass2 ) )
                PHS_Notifications::add_error_notice( self::_t( 'Please provide mandatory fields.' ) );

            elseif( !$accounts_model->check_pass( $current_user, $pass ) )
                PHS_Notifications::add_error_notice( self::_t( 'Wrong current password.' ) );

            elseif( $pass1 != $pass2 )
                PHS_Notifications::add_error_notice( self::_t( 'Passwords mismatch.' ) );

            else
            {
                $edit_arr = array();
                $edit_arr['pass'] = $pass1;

                $edit_params_arr = array();
                $edit_params_arr['fields'] = $edit_arr;

                if( ($new_account = $accounts_model->edit( $current_user, $edit_params_arr )) )
                {
                    PHS_Notifications::add_success_notice( self::_t( 'Changes saved...' ) );

                    $action_result = self::default_action_result();

                    $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'change_password' ), array( 'password_changed' => 1 ) );

                    return $action_result;
                } else
                {
                    if( $accounts_model->has_error() )
                        PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( self::_t( 'Error changing password. Please try again.' ) );
                }
            }
        }

        $data = array(
            'nick' => $current_user['nick'],
            'pass' => $pass,
            'min_password_length' => $accounts_settings['min_password_length'],
            'password_regexp' => $accounts_settings['password_regexp'],
        );

        return $this->quick_render_template( 'change_password', $data );
    }
}
