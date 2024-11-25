<?php
namespace phs\plugins\mobileapi\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\mobileapi\PHS_Plugin_Mobileapi;
use phs\plugins\mobileapi\models\PHS_Model_Api_online;

class PHS_Action_Check_api_sessions_ag extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AGENT];
    }

    public function execute()
    {
        /** @var PHS_Plugin_Mobileapi $mobileapi_plugin */
        /** @var PHS_Model_Api_online $apionline_model */
        if (!($mobileapi_plugin = PHS_Plugin_Mobileapi::get_instance())
            || !($apionline_model = PHS_Model_Api_online::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        if (!($plugin_settings = $mobileapi_plugin->get_plugin_settings())
         || empty($plugin_settings['api_session_lifetime'])) {
            return self::default_action_result();
        }

        PHS_Logger::notice(' ----- Started API sessions check...', $mobileapi_plugin::LOG_CHANNEL);

        if (!($check_result = $apionline_model->check_api_sessions_ag())) {
            if ($apionline_model->has_error()) {
                $this->copy_error($apionline_model);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error checking API sessions.'));
            }

            return false;
        }

        PHS_Logger::notice('Sessions check result: '.$check_result['expired'].' expired, '.$check_result['deleted'].' deleted, '.$check_result['errors'].' could not be expired.', $mobileapi_plugin::LOG_CHANNEL);

        PHS_Logger::notice(' ----- END API sessions check...', $mobileapi_plugin::LOG_CHANNEL);

        return self::default_action_result();
    }
}
