<?php

namespace phs\plugins\backup\actions;

use phs\PHS;
use phs\PHS_Ajax;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\backup\models\PHS_Model_Rules;
use phs\plugins\backup\models\PHS_Model_Results;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Result_files extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Backup Result Files'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Model_Results $results_model */
        /** @var PHS_Model_Rules $rules_model */
        if (!($backup_plugin = $this->get_plugin_instance())
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !($results_model = PHS_Model_Results::get_instance())
            || !($rules_model = PHS_Model_Rules::get_instance())
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!can($backup_plugin::ROLEU_LIST_BACKUPS)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $current_scope = PHS_Scope::current_scope();

        if (PHS_Params::_gp('file_deleted', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Backup file deleted with success.'));
        }

        $result_id = PHS_Params::_gp('result_id', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_ASIS);
        $action = PHS_Params::_gp('action', PHS_Params::T_NOHTML);
        $brfid = PHS_Params::_gp('brfid', PHS_Params::T_INT);

        if (empty($result_id)
         || !($result_arr = $results_model->get_details($result_id))
         || empty($result_arr['rule_id'])
         || !($rule_arr = $rules_model->get_details($result_arr['rule_id']))) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid backup result...'));

            $args = [
                'unknown_backup_result' => 1,
            ];

            if (empty($back_page)) {
                $back_page = PHS::url(['p' => 'backup', 'a' => 'backups_list']);
            } else {
                $back_page = from_safe_url($back_page);
            }

            return action_redirect(add_url_params($back_page, $args));
        }

        if (!($result_files_arr = $results_model->get_result_files($result_arr['id']))) {
            $result_files_arr = [];
        }

        if (!empty($action)
        && in_array($action, ['delete'], true)) {
            $action_result = self::default_action_result();

            switch ($action) {
                case 'delete':
                    if (!can($backup_plugin::ROLEU_DELETE_BACKUPS)) {
                        PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));
                    } elseif (empty($brfid)
                         || !($brf_flow_params = $results_model->fetch_default_flow_params(['table_name' => 'backup_results_files']))
                         || !($backup_file_arr = $results_model->get_details($brfid, $brf_flow_params))) {
                        PHS_Notifications::add_error_notice($this->_pt('Backup result file not found in database.'));
                    } else {
                        if ($results_model->unlink_result_file($backup_file_arr, ['update_result' => true])) {
                            PHS_Notifications::add_success_notice($this->_pt('Backup file deleted with success.'));

                            if (empty($back_page)) {
                                $back_page = '';
                            }

                            $url_params = ['result_id' => $result_arr['id'], 'back_page' => $back_page, 'file_deleted' => 1];

                            if ($current_scope === PHS_Scope::SCOPE_AJAX) {
                                $action_result['redirect_to_url'] = PHS_Ajax::url(['p' => 'backup', 'a' => 'result_files'], $url_params);
                            } else {
                                $action_result['redirect_to_url'] = PHS::url(['p' => 'backup', 'a' => 'result_files'], $url_params);
                            }
                        } else {
                            if ($results_model->has_error()) {
                                PHS_Notifications::add_error_notice($results_model->get_error_message());
                            } else {
                                PHS_Notifications::add_error_notice($this->_pt('Error deleting backup file. Please try again.'));
                            }
                        }
                    }
                    break;

                default:
                    PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));
                    break;
            }

            if ($current_scope === PHS_Scope::SCOPE_AJAX) {
                return $action_result;
            }
        }

        $data = [
            'back_page'        => $back_page,
            'result_files_arr' => $result_files_arr,

            'result_id'   => $result_id,
            'result_data' => $result_arr,
            'rule_data'   => $rule_arr,

            'backup_plugin'  => $backup_plugin,
            'results_model'  => $results_model,
            'rules_model'    => $rules_model,
            'accounts_model' => $accounts_model,
        ];

        return $this->quick_render_template('result_files', $data);
    }
}
