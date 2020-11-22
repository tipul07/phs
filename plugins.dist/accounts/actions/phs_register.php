<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Error;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Register extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_REGISTER );
    }

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        $action_result = self::default_action_result();

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_REGISTER_ACTION_START, $hook_args ))
        and is_array( $new_hook_args ) and !empty( $new_hook_args['action_result'] ) )
        {
            $action_result = self::validate_array( $new_hook_args['action_result'], self::default_action_result() );

            if( !empty( $new_hook_args['stop_execution'] ) )
            {
                $this->set_action_result( $action_result );

                return $action_result;
            }
        }

        PHS::page_settings( 'page_title', $this->_pt( 'Register an Account' ) );

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

        $cuser_arr = PHS::account_structure( PHS::user_logged_in() );

        if( !PHS_Roles::user_has_role_units( $cuser_arr, PHS_Roles::ROLEU_REGISTER ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Registration is closed for this site.' ) );
            return self::default_action_result();
        }

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $nick = PHS_Params::_pg( 'nick', PHS_Params::T_NOHTML );
        $email = PHS_Params::_pg( 'email', PHS_Params::T_EMAIL );
        $pass1 = PHS_Params::_p( 'pass1', PHS_Params::T_ASIS );
        $pass2 = PHS_Params::_p( 'pass2', PHS_Params::T_ASIS );
        $vcode = PHS_Params::_p( 'vcode', PHS_Params::T_NOHTML );
        $do_submit = PHS_Params::_p( 'do_submit' );

        $registered = PHS_Params::_g( 'registered', PHS_Params::T_INT );

        if( empty( $foobar )
        and PHS::user_logged_in() )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'Already logged in...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url();

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

        if( !empty( $registered ) )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'User account registered with success...' ) );

            $data = array(
                'nick' => $nick,
                'email' => $email,
                'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
            );

            return $this->quick_render_template( 'register_thankyou', $data );
        }

        $template = 'register';
        $template_data = array(
            'nick' => $nick,
            'email' => $email,
            'pass1' => $pass1,
            'pass2' => $pass2,
            'vcode' => $vcode,

            // We pass it here so hook result can stop execution
            'do_submit' => $do_submit,

            'min_password_length' => $accounts_settings['min_password_length'],
            'password_regexp' => $accounts_settings['password_regexp'],
            'no_nickname_only_email' => $accounts_settings['no_nickname_only_email'],
        );

        $hook_args = PHS_Hooks::default_page_location_hook_args();
        $hook_args['page_template'] = $template;
        $hook_args['page_template_args'] = $template_data;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_PAGE_REGISTER, $hook_args ))
        and is_array( $new_hook_args ) )
        {
            if( !empty( $new_hook_args['action_result'] ) and is_array( $new_hook_args['action_result'] ) )
                return self::validate_array( $new_hook_args['action_result'], PHS_Action::default_action_result() );

            if( !empty( $new_hook_args['new_page_template'] ) )
                $template = $new_hook_args['new_page_template'];
            if( isset( $new_hook_args['new_page_template_args'] ) and $new_hook_args['new_page_template_args'] !== false )
                $template_data = $new_hook_args['new_page_template_args'];
        }

        if( !empty( $template_data['do_submit'] ) )
        {
            /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
            /*
            if( !($captcha_plugin = PHS::load_plugin( 'captcha' )) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load captcha plugin.' ) );

            else
            */

            if( ($hook_result = PHS_Hooks::trigger_captcha_check( $template_data['vcode'] )) !== null
                and empty( $hook_result['check_valid'] ) )
            {
                if( PHS_Error::arr_has_error( $hook_result['hook_errors'] ) )
                    PHS_Notifications::add_error_notice( self::arr_get_error_message( $hook_result['hook_errors'] ) );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Invalid validation code.' ) );
            }

            elseif( $template_data['pass1'] != $template_data['pass2'] )
                PHS_Notifications::add_error_notice( $this->_pt( 'Passwords mistmatch.' ) );

            else
            {
                $insert_arr = array();
                $insert_arr['nick'] = $template_data['nick'];
                $insert_arr['pass'] = $template_data['pass1'];
                $insert_arr['email'] = $template_data['email'];
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

        return $this->quick_render_template( $template, $template_data );
    }
}
