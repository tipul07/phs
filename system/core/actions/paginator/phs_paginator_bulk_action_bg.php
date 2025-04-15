<?php
namespace phs\system\core\actions\paginator;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;

class PHS_Action_Paginator_bulk_action_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute() : ?array
    {
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || empty($params['context']['action_class'])
            || empty($params['context']['bulk_action'])
            || !is_array($params['context']['bulk_action'])
            || !($admin_plugin = PHS_Plugin_Admin::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid action details sent to background job.'));

            return null;
        }

        if (!($action_class = $params['context']['action_class'])
            || !($action_obj = $action_class::get_instance())
            || !($action_obj instanceof PHS_Action_Generic_list)) {
            PHS_Logger::error(
                'Error while instantiating paginator action class '.($params['export_context']['action'] ?? 'N/A').'.',
                $admin_plugin::LOG_PAGINATOR
            );

            return self::default_action_result();
        }

        if (!$action_obj->initialize_paginator(
            $params['context']['scope'] ?? [],
            $params['context']['pagination_params'] ?? []
        )) {
            PHS_Logger::error(
                'Error while initializing paginator for action class '.$action_obj::class.': '
                .$action_obj->get_simple_error_message('Unknown error.'),
                $admin_plugin::LOG_PAGINATOR
            );

            return self::default_action_result();
        }

        PHS_Logger::debug('Launching action '
                          .($params['context']['bulk_action']['action'] ?? 'N/A')
                          .' for class '.$action_obj::class.'.',
            $admin_plugin::LOG_PAGINATOR
        );

        if (!$action_obj->manage_action($params['context']['bulk_action'])) {
            PHS_Logger::error('Error in manage action '.($params['context']['bulk_action']['action'] ?? 'N/A')
                              .' for class '.$action_obj::class.': '
                              .$action_obj->get_simple_error_message('Unknown error.'),
                $admin_plugin::LOG_PAGINATOR
            );
        }

        PHS_Logger::debug('Finished action '
                          .($params['context']['bulk_action']['action'] ?? 'N/A')
                          .' for class '.$action_obj::class.'.',
            $admin_plugin::LOG_PAGINATOR
        );

        return self::default_action_result();
    }
}
