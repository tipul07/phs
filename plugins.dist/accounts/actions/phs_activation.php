<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\PHS_Scope;

class PHS_Action_Activation extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_ACTIVATION );
    }

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
        PHS::page_settings( 'page_title', $this->_pt( 'Account Activation' ) );

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        $confirmation_param = PHS_Params::_gp( $accounts_plugin::PARAM_CONFIRMATION, PHS_Params::T_NOHTML );

        if( !($confirmation_parts = $accounts_plugin->decode_confirmation_param( $confirmation_param )) )
        {
            if( $accounts_plugin->has_error() )
                PHS_Notifications::add_error_notice( $accounts_plugin->get_error_message() );
            else
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t interpret confirmation parameter. Please try again.' ) );
        }

        // Reset error for do_confirmation_reason() method call...
        $accounts_plugin->reset_error();

        $will_send_email_confirmation = false;
        if( !empty( $confirmation_parts['reason'] )
        and !empty( $confirmation_parts['account_data'] )
        and $confirmation_parts['reason'] == $accounts_plugin::CONF_REASON_ACTIVATION
        and $accounts_model->needs_activation( $confirmation_parts['account_data'] )
        and $accounts_model->needs_confirmation_email( $confirmation_parts['account_data'] ) )
            $will_send_email_confirmation = true;

        if( !PHS_Notifications::have_notifications_errors()
        and !empty( $confirmation_parts['account_data'] )
        and !empty( $confirmation_parts['reason'] )
        and ($confirmation_result = $accounts_plugin->do_confirmation_reason( $confirmation_parts['account_data'], $confirmation_parts['reason'] )) )
        {
            PHS_Notifications::add_success_notice( $this->_pt( 'Action Confirmed...' ) );

            $action_result = self::default_action_result();

            $url_params = array();
            $url_params['reason'] = $confirmation_parts['reason'];
            if( !empty( $will_send_email_confirmation ) )
                $url_params['confirmation_email'] = 1;

            if( !empty( $confirmation_result['redirect_url'] ) )
                $action_result['redirect_to_url'] = $confirmation_result['redirect_url'];

            elseif( $confirmation_parts['reason'] == $accounts_plugin::CONF_REASON_ACTIVATION
             or !PHS::user_logged_in() )
                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $url_params );

            else
                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'edit_profile' ), $url_params );

            return $action_result;
        }

        if( $accounts_plugin->has_error() )
            PHS_Notifications::add_error_notice( $accounts_plugin->get_error_message() );

        $data = array(
            'nick' => (!empty( $confirmation_parts['account_data'] )?$confirmation_parts['account_data']['nick']:''),
            'will_send_email_confirmation' => $will_send_email_confirmation,
        );

        return $this->quick_render_template( 'activation', $data );
    }
}
