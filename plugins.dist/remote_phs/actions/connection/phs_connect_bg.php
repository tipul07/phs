<?php

namespace phs\plugins\remote_phs\actions\connection;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_bg_jobs;
use phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Action;

class PHS_Action_Connect_bg extends PHS_Action
{
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_BACKGROUND ];
    }

    public function execute()
    {
        /** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $domains_model */
        if( !($params = PHS_bg_jobs::get_current_job_parameters())
         || !is_array( $params )
         || empty( $params['rdid'] )
         || !($domains_model = PHS::load_model( 'phs_remote_domains', 'remote_phs' ))
         || !($domain_arr = $domains_model->get_details( $params['rdid'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'PHS remote domain not found in database.' ) );
            return false;
        }

        if( !($check_result = $domains_model->act_connect_bg( $domain_arr )) )
        {
            if( $domains_model->has_error() )
                $error_msg = $domains_model->get_simple_error_message();
            else
                $error_msg = 'Unknown error connecting to PHS remote domain ['.$domain_arr['title'].'] #'.$domain_arr['id'];

            PHS_Logger::logf( '[ERROR] Error connecting to PHS remote domain: '.$error_msg, PHS_Logger::TYPE_REMOTE );
        }

        return PHS_Action::default_action_result();
    }
}
