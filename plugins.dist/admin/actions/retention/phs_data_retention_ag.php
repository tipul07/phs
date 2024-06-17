<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS_Agent;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Data_retention;

class PHS_Action_Data_retention_ag extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AGENT];
    }

    public function execute() : ?array
    {
        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Data_retention $retention_model */
        if ( !($admin_plugin = PHS_Plugin_Admin::get_instance())
             || !($retention_lib = $admin_plugin->get_data_retention_instance())
             || !($retention_model = PHS_Model_Data_retention::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES,
                $this->_pt('Couldn\'t load required resources.'));

            return null;
        }

        $is_forced = PHS_Agent::current_job_is_forced();

        if (!$is_forced) {
            if (($check_hour = $admin_plugin->data_retention_agent_run_hour()) < 0
                || $check_hour > 23) {
                $check_hour = 3;
            }

            if ((int)date('G') !== $check_hour) {
                return PHS_Action::default_action_result();
            }
        }

        $list_arr = $retention_model->fetch_default_flow_params(['table_name' => 'phs_data_retention']);
        $list_arr['fields']['status'] = $retention_model::STATUS_ACTIVE;

        if ( !($retentions_arr = $retention_model->get_list($list_arr)) ) {
            PHS_Logger::logf('No data retention plocies to be run.',
                $admin_plugin::LOG_DATA_RETENTION);

            return self::default_action_result();
        }

        PHS_Logger::notice('[AGENT] Start running '.count($retentions_arr).' data retention policies.',
            $admin_plugin::LOG_DATA_RETENTION);

        if (($result_arr = $retention_lib->run_data_retention_for_list_bg($retentions_arr)) ) {
            PHS_Logger::notice('[AGENT] Finished running '.$result_arr['total_policies'].' data retention policies: '
                               .'policies with errors: '.$result_arr['error_policies'].', '
                               .'affected rows: '.$result_arr['affected_rows'].'/'.$result_arr['total_rows'],
                $admin_plugin::LOG_DATA_RETENTION);
        } else {
            PHS_Logger::error('[AGENT] ERROR running data retention policies: '
                               .$retention_lib->get_simple_error_message($this->_pt('Unknown error.')),
                $admin_plugin::LOG_DATA_RETENTION);
        }

        return self::default_action_result();
    }
}
