<?php

namespace phs\plugins\admin\actions\migrations;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\system\core\models\PHS_Model_Migrations;

class PHS_Action_Rerun_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        /** @var PHS_Model_Migrations $migrations_model */
        if (!($migrations_model = PHS_Model_Migrations::get_instance())
            || !($migrations_manager = migrations_manager())) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || empty($params['migration_id'])
            || !($migration_arr = $migrations_model->get_details($params['migration_id']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Migration not found in database.'));

            PHS_Logger::error('!!! Error: migration id #'.($params['migration_id'] ?? '-').' not found in database.',
                PHS_Logger::TYPE_MAINTENANCE);

            return null;
        }

        if (!$migrations_manager->rerun_migration_data($migration_arr)) {
            // Failure of the migration script is not failure of background action...
            PHS_Logger::error('!!! Error re-running migration script id #'.$migration_arr['id'].': '
                              .$migrations_manager->get_simple_error_message($this->_pt('Unknown error.')),
                PHS_Logger::TYPE_MAINTENANCE);
        }

        return PHS_Action::default_action_result();
    }
}
