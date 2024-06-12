<?php

namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\models\PHS_Model_Data_retention;
use phs\plugins\s2p_companies\PHS_Plugin_S2p_companies;
use phs\plugins\s2p_companies\models\PHS_Model_Companies;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;

class PHS_Action_Run_retention_bg extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute() : ?array
    {
        if (!($params = PHS_bg_jobs::get_current_job_parameters())
            || empty($params['retention_id'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Data retention not found in database.'));

            return null;
        }

        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Data_retention $retention_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($retention_lib = $admin_plugin->get_data_retention_instance())
            || !($retention_model = PHS_Model_Data_retention::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!($retention_arr = $retention_model->get_details($params['retention_id']))
            || $retention_model->is_deleted($retention_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Data retention not found in database.'));

            return null;
        }

        return PHS_Action::default_action_result();
    }
}
