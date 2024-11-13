<?php
namespace phs\plugins\admin\actions\httpcalls;

use phs\PHS;
use phs\PHS_Ajax;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\backup\models\PHS_Model_Rules;
use phs\plugins\backup\models\PHS_Model_Results;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\models\PHS_Model_Request_queue;

class PHS_Action_Runs_ajax extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('HTTP Calls Requests'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Request_queue $requests_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($requests_model = PHS_Model_Request_queue::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_list_http_calls()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $http_id = PHS_Params::_gp('http_id', PHS_Params::T_INT);

        if (empty($http_id)
         || !($request_arr = $requests_model->get_details($http_id))) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid HTTP call...'));

            return action_redirect(add_url_params(['p' => 'admin', 'a' => 'list', 'ad' => 'httpcalls'], ['unknown_http_call' => 1, ]));
        }

        $runs_arr = $requests_model->get_request_runs($request_arr) ?: [];

        $data = [
            'request_data' => $request_arr,
            'runs_arr'     => $runs_arr,

            'requests_model' => $requests_model,
        ];

        return $this->quick_render_template('httpcalls/runs_ajax', $data);
    }
}
