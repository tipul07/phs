<?php

namespace phs\system\core\actions;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Event;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\system\core\models\PHS_Model_Request_queue;

class PHS_Action_Run_request_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute() : ?array
    {
        /** @var PHS_Model_Request_queue $requests_model */
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
            || empty($params['request_id'])
            || !($requests_model = PHS_Model_Request_queue::get_instance())
            || !($rq_manager = requests_queue_manager())
            || !($request_arr = $requests_model->get_details($params['request_id'], ['table_name' => 'phs_request_queue']))
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid request sent to background job.') );

            return null;
        }

        $params['force_run'] = !empty($params['force_run']);

        PHS_Logger::info('[QUEUE] Starting request #'.$request_arr['id']
                         .($params['force_run'] ? ' (forced)' : '').'.', PHS_Logger::TYPE_HTTP_CALLS);

        if ( !$rq_manager->run_request_bg($request_arr, $params['force_run']) ) {
            PHS_Logger::error('[QUEUE] Error running request #'.$request_arr['id'].': '
                              .$rq_manager->get_simple_error_message(self::_t('Unknown error.')),
                PHS_Logger::TYPE_HTTP_CALLS);

            return self::default_action_result();
        }

        PHS_Logger::info('[QUEUE] Finished request #'.$request_arr['id']
                         .($params['force_run'] ? ' (forced)' : '').'.', PHS_Logger::TYPE_HTTP_CALLS);

        return self::default_action_result();
    }
}
