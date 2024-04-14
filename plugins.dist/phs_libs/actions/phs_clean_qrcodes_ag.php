<?php
namespace phs\plugins\phs_libs\actions;

use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\phs_libs\PHS_Plugin_Phs_libs;

class Phs_Action_Clean_qrcodes_ag extends PHS_Action
{
    public const ERR_DEPENDENCIES = 1;

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AGENT];
    }

    public function execute()
    {
        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
        if (!($libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        if (!$libs_plugin->clean_qr_code_directory_bg()) {
            $error_msg = 'Error cleaning QR code directory.';
            if ($libs_plugin->has_error()) {
                $error_msg .= ' '.$libs_plugin->get_error_message();
            }
            PHS_Logger::error($error_msg, $libs_plugin::LOG_QR_CODE);
        }

        return PHS_Action::default_action_result();
    }
}
