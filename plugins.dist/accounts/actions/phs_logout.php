<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;

class PHS_Action_Logout extends PHS_Action
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
        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'You logged out from your account...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url();

            return $action_result;
        }

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        if( $accounts_plugin->do_logout() )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'Successfully logged out...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url();

            return $action_result;
        }

        if( $accounts_plugin->has_error() )
            PHS_Notifications::add_error_notice( $accounts_plugin->get_error_message() );
        else
            PHS_Notifications::add_error_notice( $this->_pt( 'Error logging out... Please try again.' ) );

        return $this->quick_render_template( 'logout' );
    }
}
