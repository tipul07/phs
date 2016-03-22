<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;
use \phs\system\core\views\PHS_View;

class PHS_Action_Login extends PHS_Action
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
        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $nick = PHS_params::_pg( 'nick', PHS_params::T_NOHTML );
        $pass = PHS_params::_pg( 'pass', PHS_params::T_NOHTML );
        $do_remember = PHS_params::_pg( 'do_remember', PHS_params::T_INT );
        $submit = PHS_params::_p( 'submit' );

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
            'nick' => $nick,
            'pass' => $pass,
            'do_remember' => (!empty( $do_remember )?'checked="checked"':''),
        );

        return $this->quick_render_template( 'login', $data );
    }
}
