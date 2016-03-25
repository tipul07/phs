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
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts plugin.' ) );

        if( !($current_user = PHS::current_user())
         or empty( $current_user['id'] ) )
        {
            PHS_Notifications::add_success_notice( self::_t( 'You logged out from your account...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url();

            return $action_result;
        }

        if( !($plugin_settings = $this->get_plugin_settings()) )
            $plugin_settings = array();

        if( empty( $plugin_settings['session_expire_minutes_remember'] ) )
            $plugin_settings['session_expire_minutes_remember'] = 43200; // 30 days
        if( empty( $plugin_settings['session_expire_minutes_normal'] ) )
            $plugin_settings['session_expire_minutes_normal'] = 0; // till browser closes

        if( $accounts_plugin->do_logout() )
        {
            PHS_Notifications::add_success_notice( self::_t( 'Successfully logged out...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url();

            return $action_result;
        }

        if( $accounts_plugin->has_error() )
            PHS_Notifications::add_error_notice( $accounts_plugin->get_error_message() );
        else
            PHS_Notifications::add_error_notice( self::_t( 'Error logging out... Please try again.' ) );

        return $this->quick_render_template( 'login', $data );
    }
}
