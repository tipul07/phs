<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Bg_jobs;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Logger;

class PHS_Action_Finish_backup_script_bg extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($params = PHS_Bg_jobs::get_current_job_parameters())
         or !is_array( $params )
         or empty( $params['result_id'] )
         or !($results_model = PHS::load_model( 'results', 'backup' ))
         or !($result_arr = $results_model->get_details( $params['result_id'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup result not found in database.' ) );

            PHS_Logger::logf( '!!! Error: result id #'.(!empty( $params['result_id'] )?$params['result_id']:'???').' not found in database.', $backup_plugin::LOG_CHANNEL );

            return false;
        }

        if( !$results_model->finish_result_shell_script_bg( $result_arr ) )
        {
            if( $results_model->has_error() )
                $this->copy_error( $results_model );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error finishing backup rule for result #%s.', $result_arr['id'] ) );

            PHS_Logger::logf( '!!! Error finishing result id #'.$result_arr['id'].': '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL );

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
