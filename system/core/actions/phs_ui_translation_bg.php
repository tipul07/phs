<?php
namespace phs\system\core\actions;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\libraries\PHS_Ui_translations;

class PHS_Action_Ui_translation_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute() : ?array
    {
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || !($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($translations_lib = PHS_Ui_translations::get_instance())
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid request sent to background job.'));

            return null;
        }

        $params['force_run'] = !empty($params['force_run']);

        if (empty($params['lang']) || !self::valid_language($params['lang'])) {
            PHS_Logger::info('Invalid UI language ['.($params['lang'] ?? 'N/A').']'
                             .($params['force_run'] ? ' (forced)' : '').'.', $admin_plugin::LOG_UI_TRANSLATIONS);
        }

        PHS_Logger::info('[START] Starting UI translation for '.$params['lang']
                         .($params['force_run'] ? ' (forced)' : '').'.', $admin_plugin::LOG_UI_TRANSLATIONS);

        if (!$translations_lib->start_ui_translations_bg($params['lang'], $params['force_run'])) {
            PHS_Logger::error('[ERROR] Error running UI translation for '.$params['lang'].': '
                              .$translations_lib->get_simple_error_message(self::_t('Unknown error.')),
                $admin_plugin::LOG_UI_TRANSLATIONS);

            return self::default_action_result();
        }

        PHS_Logger::info('[END] Finished UI translation for '.$params['lang']
                         .($params['force_run'] ? ' (forced)' : '').'.', $admin_plugin::LOG_UI_TRANSLATIONS);

        return self::default_action_result();
    }
}
