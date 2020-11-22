<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;
use \phs\PHS_Scope;
use \phs\PHS_Bg_jobs;

class PHS_Action_Pass_changed_email_bg extends PHS_Action
{
    const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_CHANGE_PASSWORD );
    }

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($params = PHS_Bg_jobs::get_current_job_parameters())
         or !is_array( $params )
         or empty( $params['uid'] )
         or !($accounts_plugin = $this->get_plugin_instance())
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or !($accounts_settings = $this->get_plugin_settings())
         or !is_array( $accounts_settings )
         or empty( $accounts_settings['announce_pass_change'] )
         or !($account_arr = $accounts_model->get_details( $params['uid'] ))
         or !$accounts_model->is_active( $account_arr )
         or empty( $account_arr['email'] ) )
        {
            $this->set_error( self::ERR_UNKNOWN_ACCOUNT, $this->_pt( 'Account doesn\'t need password change notification.' ) );
            return false;
        }

        $hook_args = array();
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file( 'password_changed' );
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt( 'Password changed' );
        $hook_args['email_vars'] = array(
            'nick' => $account_arr['nick'],
            'obfuscated_pass' => $accounts_model->obfuscate_password( $account_arr ),
            'contact_us_link' => PHS::url( array( 'a' => 'contact_us' ) ),
        );

        if( ($hook_results = PHS_Hooks::trigger_email( $hook_args )) === null )
            return self::default_action_result();

        if( empty( $hook_results ) or !is_array( $hook_results )
         or empty( $hook_results['send_result'] ) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_SEND_EMAIL );
            else
                $this->set_error( self::ERR_SEND_EMAIL, $this->_pt( 'Error sending password changed message to %s.', $account_arr['email'] ) );

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
