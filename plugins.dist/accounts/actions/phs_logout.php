<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Logout extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return [ self::ACT_ROLE_LOGOUT ];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_WEB ];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_USERS_LOGOUT_ACTION_START, $hook_args ))
         && is_array( $new_hook_args ) && !empty( $new_hook_args['action_result'] ) )
        {
            $action_result = self::validate_array( $new_hook_args['action_result'], self::default_action_result() );

            if( !empty( $new_hook_args['stop_execution'] ) )
            {
                $this->set_action_result( $action_result );

                return $action_result;
            }
        }

        PHS::page_settings( 'page_title', $this->_pt( 'Logout' ) );

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
