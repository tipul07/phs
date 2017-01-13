<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_bg_jobs;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Plugins_integrity_bg extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        if( !($params = PHS_bg_jobs::get_current_job_parameters()) )
            $params = false;

        $action_result = self::default_action_result();

        $action_result['buffer'] = 'Asta e din background...';

        ob_start();
        var_dump( $params );
        $action_result['buffer'] .= ob_get_clean();

        return $action_result;
    }
}
