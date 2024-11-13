<?php
namespace phs\plugins\backup\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\backup\PHS_Plugin_Backup;
use phs\plugins\backup\models\PHS_Model_Results;

class PHS_Action_D extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Backup $backup_plugin */
        if (!($backup_plugin = PHS_Plugin_Backup::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load backup plugin.'));

            return self::default_action_result();
        }

        /** @var PHS_Model_Results $results_model */
        if (!($results_model = PHS_Model_Results::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load results model.'));

            return self::default_action_result();
        }

        if (!can($backup_plugin::ROLEU_LIST_BACKUPS)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $inline = PHS_Params::_g('inline', PHS_Params::T_INT);

        if (!($brfid = PHS_Params::_g('brfid', PHS_Params::T_INT))
         || !($brf_flow_params = $results_model->fetch_default_flow_params(['table_name' => 'backup_results_files']))
         || !($backup_file_arr = $results_model->get_details($brfid, $brf_flow_params))) {
            PHS_Notifications::add_error_notice($this->_pt('Backup result file not found in database.'));

            return self::default_action_result();
        }

        if (empty($backup_file_arr['file'])
         || !@file_exists($backup_file_arr['file'])
         || !@is_readable($backup_file_arr['file'])) {
            PHS_Notifications::add_error_notice($this->_pt('File not found in backup directory structure.'));

            return self::default_action_result();
        }

        $file_name = @basename($backup_file_arr['file']);

        @header('Content-Description: '.$file_name);

        if (empty($inline)) {
            @header('Content-Transfer-Encoding: binary');
            @header('Content-Disposition: attachment; filename="'.$file_name.'"');
        }

        @header('Expires: 0');
        @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        @header('Pragma: public');
        @header('Content-Length: '.$backup_file_arr['size']);

        if (($mime_type = PHS_Utils::mimetype($backup_file_arr['file']))) {
            @header('Content-Type: '.$mime_type);
        }

        @readfile($backup_file_arr['file']);

        exit;
    }
}
