<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use phs\libraries\PHS_params;
use phs\libraries\PHS_Notifications;
use \phs\system\core\views\PHS_View;

class PHS_Action_Register extends PHS_Action
{
    /**
     * @return array|bool
     */
    public function execute()
    {
        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $nick = PHS_params::_pg( 'nick', PHS_params::T_NOHTML );
        $email = PHS_params::_pg( 'email', PHS_params::T_EMAIL );
        $pass1 = PHS_params::_p( 'pass1', PHS_params::T_ASIS );
        $pass2 = PHS_params::_p( 'pass2', PHS_params::T_ASIS );
        $vcode = PHS_params::_p( 'vcode', PHS_params::T_NOHTML );
        $submit = PHS_params::_p( 'submit' );

        if( !empty( $submit ) )
        {
            /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
            /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $captcha_plugin */
            if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
                PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts model.' ) );

            elseif( !($captcha_plugin = PHS::load_plugin( 'captcha' )) )
                PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load captcha plugin.' ) );

            elseif( $captcha_plugin->plugin_active()
                and (empty( $vcode )
                        or !$captcha_plugin->check_captcha_code( $vcode )) )
                PHS_Notifications::add_error_notice( self::_t( 'Invalid validation code.' ) );

            elseif( $pass1 != $pass2 )
                PHS_Notifications::add_error_notice( self::_t( 'Passwords mistmatch.' ) );

            else
            {
                $insert_arr = array();
                $insert_arr['nick'] = $nick;
                $insert_arr['pass'] = $pass1;
                $insert_arr['email'] = $email;
                $insert_arr['level'] = $accounts_model::LVL_MEMBER;

                if( !($account_arr = $accounts_model->insert( array( 'fields' => $insert_arr ) )) )
                {
                    if( $accounts_model->has_error() )
                        PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t register user. Please try again.' ) );
                }
            }

            if( !PHS_Notifications::have_notifications_errors() )
            {
                PHS_Notifications::add_success_notice( self::_t( 'User registered...' ) );
            }
        }

        $data = array(
            'nick' => $nick,
            'email' => $email,
            'pass1' => $pass1,
            'pass2' => $pass2,
            'vcode' => $vcode,
        );

        return $this->quick_render_template( 'register', $data );
    }
}
