<?php

namespace phs\system\core\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Error;
use \phs\libraries\PHS_Logger;

class PHS_Action_Contact_us extends PHS_Action
{
    const ERR_SEND_EMAIL = 40000;

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $email = PHS_params::_pg( 'email', PHS_params::T_NOHTML );
        $subject = PHS_params::_pg( 'subject', PHS_params::T_NOHTML );
        $body = PHS_params::_pg( 'body', PHS_params::T_NOHTML );
        $vcode = PHS_params::_p( 'vcode', PHS_params::T_NOHTML );
        $submit = PHS_params::_p( 'submit' );

        $sent = PHS_params::_g( 'sent', PHS_params::T_INT );

        if( !empty( $sent ) )
            PHS_Notifications::add_success_notice( self::_t( 'Your message was succesfully sent. Thank you!' ) );

        if( !($current_user = PHS::user_logged_in()) )
            $current_user = false;

        if( !empty( $current_user )
        and empty( $foobar ) )
            $email = $current_user['email'];

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !empty( $submit ) )
        {
            $emails_arr = array();
            if( defined( 'PHS_CONTACT_EMAIL' )
            and ($emails_str = constant( 'PHS_CONTACT_EMAIL' ))
            and ($emails_parts_arr = explode( ',', $emails_str ))
            and is_array( $emails_parts_arr ) )
            {
                foreach( $emails_parts_arr as $email_addr )
                {
                    $email_addr = trim( $email_addr );
                    if( empty( $email_addr )
                        or !PHS_params::check_type( $email_addr, PHS_params::T_EMAIL ) )
                    {
                        PHS_Notifications::add_error_notice( self::_t( '[%s] doesn\'t seem to be an email address. Please change your PHS_CONTACT_EMAIL constant in main.php file.', $email_addr ) );
                        continue;
                    }

                    $emails_arr[] = $email_addr;
                }
            }

            if( empty( $emails_arr ) )
                PHS_Notifications::add_error_notice( self::_t( 'No email addresses setup in the platform.' ) );

            elseif( empty( $email ) or empty( $subject ) or empty( $body )
             or (empty( $current_user ) and empty( $vcode )) )
                PHS_Notifications::add_error_notice( self::_t( 'Please provide mandatory fields in the form.' ) );

            elseif( !PHS_params::check_type( $email, PHS_params::T_EMAIL ) )
                PHS_Notifications::add_error_notice( self::_t( 'Please provide a valid email address.' ) );

            elseif( empty( $current_user )
                and !($captcha_plugin = PHS::load_plugin( 'captcha' )) )
                PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load captcha plugin.' ) );

            elseif( empty( $current_user )
                and ($hook_result = PHS_Hooks::trigger_captcha_check( $vcode )) !== null
                and empty( $hook_result['check_valid'] ) )
            {
                if( PHS_Error::arr_has_error( $hook_result['hook_errors'] ) )
                    PHS_Notifications::add_error_notice( PHS_Error::arr_get_error_message( $hook_result['hook_errors'] ) );
                else
                    PHS_Notifications::add_error_notice( self::_t( 'Invalid validation code.' ) );
            }

            if( !PHS_Notifications::have_notifications_errors() )
            {
                PHS_Hooks::trigger_captcha_regeneration();

                $hook_args = array();
                $hook_args['template'] = array( 'file' => 'contact_us' );
                $hook_args['from'] = $email;
                $hook_args['from_name'] = self::_t( 'Site Contact' );
                $hook_args['subject'] = self::_t( 'Contact Us: %s', $subject );
                $hook_args['email_vars'] = array(
                    'current_user' => $current_user,
                    'user_agent' => (!empty( $_SERVER['HTTP_USER_AGENT'] )?$_SERVER['HTTP_USER_AGENT']:self::_t( 'N/A' )),
                    'request_ip' => request_ip(),
                    'subject' => $subject,
                    'email' => $email,
                    'body' => str_replace( '  ', '&nbsp; ', nl2br( $body ) ),
                );

                $email_failed = true;
                foreach( $emails_arr as $email_address )
                {
                    $hook_args['to'] = $email_address;
                    $hook_args['to_name'] = self::_t( 'Site Contact' );

                    if( !($hook_results = PHS_Hooks::trigger_email( $hook_args )) )
                        PHS_Logger::logf( self::_t( 'Error sending email from contact form to [%s].', $email_address, PHS_Logger::TYPE_DEBUG ) );
                    else
                        $email_failed = false;
                }

                if( $email_failed )
                    PHS_Notifications::add_error_notice( self::_t( 'Failed sending email. Please try again.' ) );

                else
                {
                    $action_result = self::default_action_result();

                    $action_result['redirect_to_url'] = PHS::url( array( 'a' => 'contact_us' ), array( 'sent' => 1 ) );

                    return $action_result;
                }
            }
        }

        $data = array(
            'email' => $email,
            'subject' => $subject,
            'body' => $body,
            'vcode' => $vcode,
        );

        return $this->quick_render_template( 'contact_us', $data );
    }
}
