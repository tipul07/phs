<?php

namespace phs\plugins\backup\actions;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\backup\PHS_Plugin_Backup;
use phs\plugins\backup\models\PHS_Model_Results;

class PHS_Action_Finish_backup_script_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        /** @var PHS_Plugin_Backup $backup_plugin */
        /** @var PHS_Model_Results $results_model */
        if (!($backup_plugin = PHS_Plugin_Backup::get_instance())
            || !($results_model = PHS_Model_Results::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error loading required resources.'));

            return false;
        }

        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || empty($params['result_id'])
            || !($result_arr = $results_model->get_details($params['result_id']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Backup result not found in database.'));

            PHS_Logger::error('!!! Error: result id #'.(!empty($params['result_id']) ? $params['result_id'] : '???').' not found in database.', $backup_plugin::LOG_CHANNEL);

            return false;
        }

        if (!$results_model->finish_result_shell_script_bg($result_arr)) {
            $this->copy_or_set_error($results_model, self::ERR_FUNCTIONALITY,
                $this->_pt('Error finishing backup rule for result #%s.', $result_arr['id']));

            PHS_Logger::error('!!! Error finishing result id #'.$result_arr['id'].': '.$this->get_simple_error_message(), $backup_plugin::LOG_CHANNEL);

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
