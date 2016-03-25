<?php

namespace phs\system\core\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;

class PHS_Action_Contact_us extends PHS_Action
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

    public function execute()
    {
        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $email = PHS_params::_pg( 'email', PHS_params::T_NOHTML );
        $subject = PHS_params::_pg( 'subject', PHS_params::T_NOHTML );
        $body = PHS_params::_pg( 'body', PHS_params::T_NOHTML );
        $vcode = PHS_params::_p( 'vcode', PHS_params::T_NOHTML );
        $submit = PHS_params::_p( 'submit' );

        $sent = PHS_params::_g( 'sent', PHS_params::T_INT );
        
        if( !($current_user = PHS::current_user()))

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !empty( $submit ) )
        {
            if( empty( $nick ) or empty( $pass ) )
                PHS_Notifications::add_error_notice( self::_t( 'Please provide complete mandatory fields.' ) );

            elseif( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
                PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts model.' ) );

            elseif( !($account_arr = $accounts_model->get_details_fields( array( 'nick' => $nick ) ))
                    or !$accounts_model->check_pass( $account_arr, $pass )
                    or !$accounts_model->is_active( $account_arr ) )
                PHS_Notifications::add_error_notice( self::_t( 'Wrong username or password.' ) );

            else
            {
                PHS_Notifications::add_success_notice( self::_t( 'User should login now...' ) );
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
