<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS_Scope;
use phs\PHS_bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\s2p_companies\PHS_Plugin_S2p_companies;
use phs\plugins\s2p_companies\models\PHS_Model_Companies;

class PHS_Action_Run_retention_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute() : ?array
    {
        if (!($params = PHS_bg_jobs::get_current_job_parameters())
            || empty($params['retention_ids'])
            || !($retention_ids = self::extract_integers_from_array($params['retention_ids']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Data retention details not provided.'));

            return null;
        }

        /** @var PHS_Plugin_Admin $admin_plugin */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($retention_lib = $admin_plugin->get_data_retention_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        PHS_Logger::notice('[START] Running '.count($retention_ids).' data retention policies.',
            $admin_plugin::LOG_DATA_RETENTION);

        if ( !($result = $retention_lib->run_data_retention_for_list_bg($retention_ids)) ) {
            PHS_Logger::error('[ERROR] Error running data retention policies: '
                              .$retention_lib->get_simple_error_message($this->_pt('Unknown error.')),
                $admin_plugin::LOG_DATA_RETENTION);

            return PHS_Action::default_action_result();
        }

        PHS_Logger::notice('[END] Finished running '.count($retention_ids).' data retention policies: '
                           .'policies with errors: '.$result['error_policies'].', '
                           .'affected rows: '.$result['affected_rows'].'/'.$result['total_rows'],
            $admin_plugin::LOG_DATA_RETENTION);

        return PHS_Action::default_action_result();
    }
}
