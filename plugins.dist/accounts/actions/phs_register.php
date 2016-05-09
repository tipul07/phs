<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Error;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Register extends PHS_Action
{

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
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $nick = PHS_params::_pg( 'nick', PHS_params::T_NOHTML );
        $email = PHS_params::_pg( 'email', PHS_params::T_EMAIL );
        $pass1 = PHS_params::_p( 'pass1', PHS_params::T_ASIS );
        $pass2 = PHS_params::_p( 'pass2', PHS_params::T_ASIS );
        $vcode = PHS_params::_p( 'vcode', PHS_params::T_NOHTML );
        $submit = PHS_params::_p( 'submit' );

        $registered = PHS_params::_g( 'registered', PHS_params::T_INT );

        if( empty( $foobar )
        and PHS::user_logged_in() )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'Already logged in...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url();

            return $action_result;
        }

        if( !empty( $registered ) )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'User account registered with success...' ) );

            $data = array(
                'nick' => $nick,
                'email' => $email,
            );

            return $this->quick_render_template( 'register_thankyou', $data );
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

        if( !empty( $submit ) )
        {
            /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
            if( !($captcha_plugin = PHS::load_plugin( 'captcha' )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load captcha plugin.' ) );

            elseif( ($hook_result = PHS_Hooks::trigger_captcha_check( $vcode )) !== null
                and empty( $hook_result['check_valid'] ) )
            {
                if( PHS_Error::arr_has_error( $hook_result['hook_errors'] ) )
                    PHS_Notifications::add_error_notice( PHS_Error::arr_get_error_message( $hook_result['hook_errors'] ) );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Invalid validation code.' ) );
            }

            elseif( $pass1 != $pass2 )
                PHS_Notifications::add_error_notice( $this->_pt( 'Passwords mistmatch.' ) );

            else
            {
                $insert_arr = array();
                $insert_arr['nick'] = $nick;
                $insert_arr['pass'] = $pass1;
                $insert_arr['email'] = $email;
                $insert_arr['level'] = $accounts_model::LVL_MEMBER;
                $insert_arr['lastip'] = request_ip();

                if( !($account_arr = $accounts_model->insert( array( 'fields' => $insert_arr ) )) )
                {
                    if( $accounts_model->has_error() )
                        PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t register user. Please try again.' ) );
                } else
                    PHS_Hooks::trigger_captcha_regeneration();
            }

            if( !empty( $account_arr )
            and !PHS_Notifications::have_notifications_errors() )
            {
                $action_result = self::default_action_result();

                if( !$accounts_model->is_active( $account_arr ) )
                    $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'register' ), array( 'registered' => 1, 'nick' => $nick, 'email' => $email ) );
                else
                    $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'registered' => 1, 'nick' => $nick ) );

                return $action_result;
            }
        }

        $data = array(
            'nick' => $nick,
            'email' => $email,
            'pass1' => $pass1,
            'pass2' => $pass2,
            'vcode' => $vcode,
            'min_password_length' => $accounts_settings['min_password_length'],
            'password_regexp' => $accounts_settings['password_regexp'],
        );

        return $this->quick_render_template( 'register', $data );
    }
}
