<?php

namespace phs\plugins\admin\actions\httpcalls;

use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;

class PHS_Action_Check_ag extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AGENT];
    }

    public function execute()
    {
        PHS_Logger::notice(' ----- Checking HTTP Calls', PHS_Logger::TYPE_HTTP_CALLS);

        if (!($rq_manager = requests_queue_manager())) {
            PHS_Logger::error('Error instantiating requests queue manager.', PHS_Logger::TYPE_HTTP_CALLS);

            return self::default_action_result();
        }

        if (!($check_result = $rq_manager->check_http_calls_queue())) {
            PHS_Logger::error('ERROR checking HTTP Calls queue: '
                              .$rq_manager->get_simple_error_message($this->_pt('Unknown error.')),
                PHS_Logger::TYPE_HTTP_CALLS
            );
        } else {
            PHS_Logger::notice('Total HTTP calls '.$check_result['total']
                               .', success '.($check_result['success'] ?? 0).', failed '.($check_result['failed'] ?? 0)
                               .', retries '.($check_result['retries'] ?? 0).', timed '.($check_result['timed'] ?? 0).'.',
                PHS_Logger::TYPE_HTTP_CALLS
            );
        }

        PHS_Logger::notice(' ----- END Checking HTTP Calls', PHS_Logger::TYPE_HTTP_CALLS);

        return self::default_action_result();
    }
}
