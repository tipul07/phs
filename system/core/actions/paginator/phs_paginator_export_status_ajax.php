<?php
namespace phs\system\core\actions\paginator;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Action_Generic_list;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\libraries\PHS_Paginator_exporter_manager;

class PHS_Action_Paginator_export_status_ajax extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        if (!($current_user = PHS::current_user())) {
            PHS_Notifications::add_warning_notice(self::_t('You should login first.'));

            return action_request_login();
        }

        /** @var PHS_Action_Generic_list $action_class */
        if (!($action_class = PHS_Params::_pg('action_class', PHS_Params::T_NOHTML))
            || !($action_obj = $action_class::get_instance())
            || !($export_lib = PHS_Paginator_exporter_manager::get_instance())
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !$accounts_model->acc_is_operator($current_user)
            || !($export_status = $export_lib->read_export_details($action_obj, $current_user))) {
            return $this->send_ajax_response([]);
        }

        $export_status['max_count'] ??= 0;
        $export_status['current_count'] ??= 0;

        $export_status['status_title'] = $export_lib->get_status_title($export_status['status'] ?? '') ?: self::_t('N/A');
        $export_status['progress_perc'] = min(100, (!(float)$export_status['max_count'] ? 0 : ceil(($export_status['current_count'] * 100) / $export_status['max_count'])));
        if ($export_status['start_time'] ?? 0) {
            $export_status['time_taken'] = PHS_Utils::parse_period(max(0, (($export_status['end_time'] ?? 0) ?: time()) - $export_status['start_time']));
        }

        $export_status['is_final'] = $export_lib->is_final_status($export_status);
        $export_status['is_success'] = $export_lib->is_success_status($export_status);

        return $this->send_ajax_response($export_status);
    }
}
