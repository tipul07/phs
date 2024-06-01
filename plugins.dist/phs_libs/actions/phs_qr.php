<?php

namespace phs\plugins\phs_libs\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_params;
use phs\libraries\PHS_Notifications;
use phs\plugins\phs_libs\PHS_Plugin_Phs_libs;

class PHS_Action_Qr extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        $download = PHS_params::_g('download', PHS_params::T_INT);

        /** @var PHS_Plugin_Phs_libs $libs_plugin */
        if (!($libs_plugin = PHS_Plugin_Phs_libs::get_instance())
         || !($libs_obj = $libs_plugin->get_qr_code_instance())) {
            echo $this->_pt('Error loading required resources.');
            exit;
        }

        if (!($details_arr = $libs_plugin->extract_qr_code_url_details())) {
            echo $libs_plugin->has_error()
                ? $libs_plugin->get_simple_error_message()
                : $this->_pt('Error extracting QR code token.');
            exit;
        }

        if (!empty($details_arr['for_account_id'])
            && (!($current_user = PHS::current_user())
                || (int)$current_user['id'] !== $details_arr['for_account_id'])
        ) {
            echo 'Invalid QR code details.';
            exit;
        }

        @header('Content-Description: QR Code');

        @header('Expires: 0');
        @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        @header('Pragma: public');

        if (!($result = $libs_obj->render_url_to_output($details_arr['url'], $details_arr['qr_options']))) {
            echo $libs_plugin->has_error()
                ? $libs_plugin->get_simple_error_message()
                : $this->_pt('Error rendering QR code.');
            exit;
        }

        if (!empty($result['options']['qr_options']['output_type'])
            && ($mimetype = $libs_obj->get_mimetype_by_output_type($result['options']['qr_options']['output_type']))) {
            @header('Content-Type: '.$mimetype);
        }

        if (!empty($download)) {
            @header('Content-Transfer-Encoding: binary');
            @header('Content-Disposition: attachment; filename="QRCode"');
        }

        @header('Content-Length: '.strlen($result['result']));

        echo $result['result'];

        exit;
    }
}
