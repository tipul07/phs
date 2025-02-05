<?php
namespace phs\system\core\actions\paginator;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\libraries\PHS_Paginator_exporter_manager;

class PHS_Action_Paginator_exporter_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute() : ?array
    {
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || empty($params['export_context'])
            || !($export_manager = PHS_Paginator_exporter_manager::get_instance())
            || !($admin_plugin = PHS_Plugin_Admin::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid action details sent to background job.'));

            return null;
        }

        if (!$export_manager->start_export_from_background_action($params['export_context'])) {
            PHS_Logger::error('[EXPORT] Error while running action class '.($params['export_context']['action'] ?? 'N/A').': '
                              .$export_manager->get_simple_error_message('Unknown error.').'.', $admin_plugin::LOG_PAGINATOR);

            return self::default_action_result();
        }

        PHS_Logger::info('[EXPORT] Finished action '.($params['export_context']['action'] ?? 'N/A').'.', $admin_plugin::LOG_PAGINATOR);

        return null;
    }
}
