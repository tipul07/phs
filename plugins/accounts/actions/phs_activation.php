<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;

class PHS_Action_Activation extends PHS_Action
{

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts plugin.' ) );

        $confirmation_param = PHS_params::_gp( $accounts_plugin::PARAM_CONFIRMATION, PHS_params::T_NOHTML );

        $submit = PHS_params::_p( 'submit' );

        if( !($confirmation_parts = $accounts_plugin->decode_confirmation_param( $confirmation_param )) )
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t interpret confirmation parameter. Please try again.' ) );

        if( !PHS_Notifications::have_notifications_errors()
        and !empty( $confirmation_parts['account_data'] )
        and !empty( $confirmation_parts['reason'] )
        and ($confirmation_result = $accounts_plugin->do_confirmation_reason( $confirmation_parts['account_data'], $confirmation_parts['reason'] )) )
        {
            PHS_Notifications::add_success_notice( self::_t( 'Account activated...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'reason' => $confirmation_parts['reason'] ) );

            return $action_result;
        }

        $data = array(
            'nick' => (!empty( $confirmation_parts['account_data'] )?$confirmation_parts['account_data']['nick']:''),
        );

        return $this->quick_render_template( 'login', $data );
    }
}
