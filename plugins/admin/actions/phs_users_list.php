<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Users_list extends PHS_Action
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
            PHS_Notifications::add_warning_notice( self::_t( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         or !($accounts_plugin_settings = $accounts_plugin->get_plugin_settings()) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        if( !$accounts_model->can_list_accounts( $current_user ) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'You don\'t have rights to create accounts.' ) );
            return self::default_action_result();
        }

        $account_created = PHS_params::_g( 'account_created', PHS_params::T_NOHTML );

        if( !empty( $account_created ) )
            PHS_Notifications::add_success_notice( self::_t( 'User account created.' ) );

        return $this->quick_render_template( 'users_list' );
    }
}
