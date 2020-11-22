<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\PHS_Bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Edit_profile extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_EDIT_PROFILE );
    }

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
        $action_result = self::default_action_result();

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_EDIT_PROFILE_ACTION_START, $hook_args ))
        and is_array( $new_hook_args ) and !empty( $new_hook_args['action_result'] ) )
        {
            $action_result = self::validate_array( $new_hook_args['action_result'], self::default_action_result() );

            if( !empty( $new_hook_args['stop_execution'] ) )
            {
                $this->set_action_result( $action_result );

                return $action_result;
            }
        }

        PHS::page_settings( 'page_title', $this->_pt( 'Edit Profile' ) );

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $accounts_details_model */
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

        if( !($accounts_details_model = PHS::load_model( 'accounts_details', $this->instance_plugin_name() )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load account details model.' ) );
            return self::default_action_result();
        }

        $reason = PHS_Params::_g( 'reason', PHS_Params::T_NOHTML );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'back_page' => PHS::current_url()
            );

            if( !empty( $reason ) )
                $args['reason'] = $reason;

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        if( !($plugin_settings = $this->get_plugin_settings()) )
            $plugin_settings = array();

        if( PHS_Params::_g( 'changes_saved', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Changes saved to database.' ) );
        if( PHS_Params::_g( 'confirmation_email', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'An email with your password was sent to email provided in your account details.' ) );

        if( empty( $current_user['details_id'] )
         or !($user_details = $accounts_details_model->get_details( $current_user['details_id'] )) )
            $user_details = false;

        if( !empty( $reason )
        and ($reason_success_text = $accounts_plugin->valid_confirmation_reason( $reason )) )
            PHS_Notifications::add_success_notice( $reason_success_text );

        if( !($email_needs_verification = $accounts_model->needs_email_verification( $current_user )) )
            $verify_email_link = '#';
        else
            $verify_email_link = PHS::url( array( 'p' => 'accounts', 'a' => 'edit_profile' ), array( 'verify_email' => 1 ) );

        $verify_email = PHS_Params::_g( 'verify_email', PHS_Params::T_INT );
        $verification_email_sent = PHS_Params::_g( 'verification_email_sent', PHS_Params::T_INT );

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $email = PHS_Params::_p( 'email', PHS_Params::T_EMAIL );
        $title = PHS_Params::_p( 'title', PHS_Params::T_NOHTML );
        $fname = PHS_Params::_p( 'fname', PHS_Params::T_NOHTML );
        $lname = PHS_Params::_p( 'lname', PHS_Params::T_NOHTML );
        $phone = PHS_Params::_p( 'phone', PHS_Params::T_NOHTML );
        $company = PHS_Params::_p( 'company', PHS_Params::T_NOHTML );

        $do_submit = PHS_Params::_p( 'do_submit' );

        if( !empty( $verification_email_sent ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Verification email sent... Please follow the steps in email to acknowledge your email address.' ) );

        if( !empty( $verify_email )
        and $accounts_model->needs_email_verification( $current_user ) )
        {
            if( !PHS_Bg_jobs::run( array( 'p' => 'accounts', 'a' => 'verify_email_bg', 'c' => 'index_bg' ), array( 'uid' => $current_user['id'] ) ) )
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t send verification email. Please try again.' ) );

            else
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Verification email sent... Please follow the steps in email to acknowledge your email address.' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'edit_profile' ), array( 'verification_email_sent' => 1 ) );

                return $action_result;
            }
        }

        if( empty( $foobar ) )
        {
            $email = $current_user['email'];

            if( !empty( $user_details ) )
            {
                $title = $user_details['title'];
                $fname = $user_details['fname'];
                $lname = $user_details['lname'];
                $phone = $user_details['phone'];
                $company = $user_details['company'];
            }
        }

        if( !empty( $do_submit ) )
        {
            $edit_arr = array();
            if( empty( $plugin_settings['no_nickname_only_email'] ) )
                $edit_arr['email'] = $email;

            $edit_details_arr = array();
            $edit_details_arr['title'] = $title;
            $edit_details_arr['fname'] = $fname;
            $edit_details_arr['lname'] = $lname;
            $edit_details_arr['phone'] = $phone;
            $edit_details_arr['company'] = $company;

            $edit_params_arr = array();
            $edit_params_arr['fields'] = $edit_arr;
            $edit_params_arr['{users_details}'] = $edit_details_arr;

            if( ($new_account = $accounts_model->edit( $current_user, $edit_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Changes saved...' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'edit_profile' ), array( 'changes_saved' => 1 ) );

                return $action_result;
            } else
            {
                if( $accounts_model->has_error() )
                    PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
            }
        }

        $data = array(
            'nick' => $current_user['nick'],
            'email' => $email,
            'title' => $title,
            'fname' => $fname,
            'lname' => $lname,
            'phone' => $phone,
            'company' => $company,
            'email_verified' => (!empty( $email_needs_verification )?false:true),
            'verify_email_link' => $verify_email_link,
            'no_nickname_only_email' => $plugin_settings['no_nickname_only_email'],
        );

        return $this->quick_render_template( 'edit_profile', $data );
    }
}
