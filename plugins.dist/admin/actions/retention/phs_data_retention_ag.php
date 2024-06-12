<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS_Agent;
use phs\PHS_Scope;
use phs\PHS_Session;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;

class PHS_Action_Data_retention_ag extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AGENT];
    }

    public function execute() : ?array
    {
        /** @var PHS_Plugin_Admin $admin_plugin */
        if ( !($admin_plugin = PHS_Plugin_Admin::get_instance())) {
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

        return self::default_action_result();
    }
}
