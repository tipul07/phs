<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;
use \phs\PHS_Scope;
use \phs\PHS_Bg_jobs;

class PHS_Action_Verify_email_bg extends PHS_Action
{
    const ERR_UNKNOWN_ACCOUNT = 40000, ERR_SEND_EMAIL = 40001;

    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_ACTIVATION );
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
         or !($accounts_plugin = PHS::load_plugin( $this->instance_plugin_name() ))
         or !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() ))
         or !($account_arr = $accounts_model->get_details( $params['uid'] ))
         or !$accounts_model->needs_email_verification( $account_arr ) )
        {
            $this->set_error( self::ERR_UNKNOWN_ACCOUNT, $this->_pt( 'Account doesn\'t need email verification.' ) );
            return false;
        }

        $hook_args = array();
        $hook_args['template'] = $accounts_plugin->email_template_resource_from_file( 'verify_email' );
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt( 'Verify Email' );
        $hook_args['email_vars'] = array(
            'nick' => $account_arr['nick'],
            'activation_link' => $accounts_plugin->get_confirmation_link( $account_arr, $accounts_plugin::CONF_REASON_EMAIL ),
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
                $this->set_error( self::ERR_SEND_EMAIL, $this->_pt( 'Error sending verify email message to %s.', $account_arr['email'] ) );

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
