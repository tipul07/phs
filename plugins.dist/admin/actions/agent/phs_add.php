<?php
namespace phs\plugins\admin\actions\agent;

use phs\PHS;
use phs\PHS_Agent;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Agent_jobs;

class PHS_Action_Add extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Add Agent Job'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_agent_jobs()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $agent_routes = PHS_Agent::get_agent_routes() ?: [];

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $title = PHS_Params::_p('title', PHS_Params::T_NOHTML);
        $plugin = PHS_Params::_p('plugin', PHS_Params::T_NOHTML);
        $controller = PHS_Params::_p('controller', PHS_Params::T_NOHTML);
        $action = PHS_Params::_p('action', PHS_Params::T_NOHTML);
        $handler = PHS_Params::_p('handler', PHS_Params::T_NOHTML);
        $params = PHS_Params::_p('params', PHS_Params::T_ASIS);
        $timed_seconds = PHS_Params::_p('timed_seconds', PHS_Params::T_INT);
        $run_async = PHS_Params::_p('run_async', PHS_Params::T_BOOL);
        $stalling_minutes = PHS_Params::_p('stalling_minutes', PHS_Params::T_INT);

        $do_submit = PHS_Params::_p('do_submit');

        if (!empty($do_submit)) {
            $action_dir = '';
            $action = trim($action, '/\\');
            if (strpos($action, '/') !== false) {
                $action_parts = explode('/', $action);
                $action = array_pop($action_parts);
                if (!empty($action_parts)) {
                    $action_dir = implode('/', $action_parts);
                }
            }

            if (empty($handler)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide a handler for this agent job.'));
            } elseif ($agent_jobs_model->get_details_fields(['handler' => $handler])) {
                PHS_Notifications::add_error_notice($this->_pt('Agent job handler already exists. Pick another one.'));
            } elseif (!empty($params)
                && !($params_arr = @json_decode($params, true))) {
                PHS_Notifications::add_error_notice($this->_pt('Job parameters doesn\'t look like a valid JSON string or JSON is empty.'));
            } elseif (empty($plugin) || empty($controller) || empty($action)
             || empty($agent_routes) || !is_array($agent_routes)
             || empty($agent_routes[$plugin])
             || empty($agent_routes[$plugin]['controllers'])
             || empty($agent_routes[$plugin]['actions'])
             || empty($agent_routes[$plugin]['controllers'][$controller])
             || empty($agent_routes[$plugin]['actions'][$action])) {
                PHS_Notifications::add_error_notice($this->_pt('Invalid plugin, controller or action selected. Please select valid values from drop down list.'));
            } elseif (!($job_route = PHS::route_from_parts(['p' => $plugin, 'c' => $controller, 'a' => $action, 'ad' => $action_dir]))) {
                PHS_Notifications::add_error_notice($this->_pt('Couldn\'t compose a valid route using provided plugin, controller or action. Please select valid values from drop down list.'));
            } elseif (!($timed_seconds = (int)$timed_seconds) || $timed_seconds < 0) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide a valid running interval in seconds.'));
            } else {
                if (empty($params_arr) || !is_array($params_arr)) {
                    $params_arr = [];
                }

                $job_extra_arr = [];
                $job_extra_arr['title'] = $title;
                // Plugin must be empty string to tell system this is an user-defined agent job...
                $job_extra_arr['plugin'] = $plugin;
                $job_extra_arr['status'] = $agent_jobs_model::STATUS_ACTIVE;
                $job_extra_arr['run_async'] = (!empty($run_async) ? 1 : 0);
                $job_extra_arr['stalling_minutes'] = $stalling_minutes;

                if (PHS_Agent::add_job($handler, $job_route, $timed_seconds, $params_arr, $job_extra_arr)) {
                    PHS_Notifications::add_success_notice($this->_pt('Agent job details saved...'));

                    return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'agent'], ['agent_job_added' => 1]);
                }

                PHS_Notifications::add_error_notice(PHS::st_get_error_message($this->_pt('Error saving details to database. Please try again.')));
            }
        }

        $data = [
            'foobar'           => $foobar,
            'title'            => $title,
            'plugin'           => $plugin,
            'controller'       => $controller,
            'action'           => $action,
            'handler'          => $handler,
            'params'           => $params,
            'timed_seconds'    => $timed_seconds,
            'run_async'        => (!empty($run_async) ? 'checked="checked"' : ''),
            'stalling_minutes' => $stalling_minutes,

            'agent_routes' => $agent_routes,
        ];

        return $this->quick_render_template('agent/add', $data);
    }
}
