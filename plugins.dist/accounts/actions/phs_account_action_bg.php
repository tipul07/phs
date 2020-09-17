<?php

namespace phs\plugins\accounts\actions;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;
use \phs\PHS_Scope;
use \phs\PHS_bg_jobs;

class PHS_Action_Account_action_bg extends PHS_Action
{
    const ERR_UNKNOWN_ACCOUNT = 1, ERR_TRIGGER = 2;

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($params = PHS_bg_jobs::get_current_job_parameters())
         || !($hook_args = self::validate_array( $params, PHS_Hooks::default_account_action_hook_args() ))
         || empty( $hook_args['account_data'] )
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         || !($account_arr = $accounts_model->data_to_array( $hook_args['account_data'], array( 'table_name' => 'users' ) )) )
        {
            $this->set_error( self::ERR_UNKNOWN_ACCOUNT, $this->_pt( 'Cannot send forgot password email to this account.' ) );
            return false;
        }

        $hook_args['account_data'] = $account_arr;
        $hook_args['in_background'] = true;

        if( !($result_arr = PHS_Hooks::trigger_account_action( $hook_args )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_TRIGGER );
            else
                $this->set_error( self::ERR_TRIGGER, $this->_pt( 'Error triggering account action in background for account #%s (%s).', $account_arr['id'], $account_arr['nick'] ) );

            return false;
        }

        return self::default_action_result();
    }
}
