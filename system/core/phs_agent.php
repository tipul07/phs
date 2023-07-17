<?php
namespace phs;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Instantiable;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Agent_jobs;

// ! @version 1.00

class PHS_Agent extends PHS_Registry
{
    public const ERR_DB_INSERT = 30000, ERR_COMMAND = 30001, ERR_RUN_JOB = 30002, ERR_JOB_DB = 30003, ERR_JOB_STALLING = 30004,
    ERR_AVAILABLE_ACTIONS = 30005;

    public const DATA_AGENT_KEY = 'bg_agent_data';

    /**
     * @param int|array $job_data
     * @param false|array $extra
     *
     * @return array|bool
     */
    public function run_job($job_data, $extra = false)
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (empty($job_data)
         || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            $this->set_error(self::ERR_RUN_JOB, self::_t('Couldn\'t load agent jobs details from database.'));

            return false;
        }

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        if (empty($extra['return_command'])) {
            $extra['return_command'] = false;
        }
        if (empty($extra['force_run'])) {
            $extra['force_run'] = false;
        } else {
            $extra['force_run'] = true;
        }

        if (!$extra['force_run']
         && !$agent_jobs_model->job_is_active($job_arr)) {
            $this->set_error(self::ERR_RUN_JOB, self::_t('Agent job not active.'));

            return false;
        }

        if (!$extra['force_run']
        && $agent_jobs_model->job_is_running($job_arr)
        && !$agent_jobs_model->job_is_stalling($job_arr)) {
            $this->set_error(self::ERR_RUN_JOB, self::_t('Agent job is still running.'));

            return false;
        }

        $run_async = false;
        if ($agent_jobs_model->job_runs_async($job_arr)) {
            $run_async = true;
        }

        // Make sure we are not launching job from front-end...
        if (!in_array(PHS_Scope::current_scope(), [PHS_Scope::SCOPE_AGENT, PHS_Scope::SCOPE_BACKGROUND, ], true)) {
            $run_async = true;
        }

        $cmd_extra = [];
        $cmd_extra['async_task'] = $run_async;
        $cmd_extra['agent_jobs_model'] = $agent_jobs_model;
        $cmd_extra['force_run'] = $extra['force_run'];

        if (!($cmd_parts = $this->get_job_command($job_arr, $cmd_extra))
         || empty($cmd_parts['cmd'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_COMMAND, self::_t('Couldn\'t get agent job command.'));
            }

            return false;
        }

        // Agent job cannot be edited as it will be managed by PHS_Agent class...
        if (!empty($extra['return_command'])) {
            return ['cmd' => $cmd_parts['cmd'], ];
        }

        PHS_Logger::notice('Launching agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_AGENT);

        if (PHS::st_debugging_mode()) {
            PHS_Logger::debug('Command ['.$cmd_parts['cmd'].']', PHS_Logger::TYPE_AGENT);
        }

        return @system($cmd_parts['cmd']) !== false;
    }

    /**
     * @param int|array $job_data
     * @param false|array $extra
     *
     * @return array|false
     */
    public function get_job_command($job_data, $extra = false)
    {
        $this->reset_error();

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        if (empty($extra['force_run'])) {
            $extra['force_run'] = false;
        } else {
            $extra['force_run'] = true;
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!empty($extra['agent_jobs_model'])) {
            $agent_jobs_model = $extra['agent_jobs_model'];
        } else {
            $agent_jobs_model = PHS_Model_Agent_jobs::get_instance();
        }

        if (empty($job_data)
         || empty($agent_jobs_model)
         || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            if ($agent_jobs_model->has_error()) {
                $this->copy_error($agent_jobs_model, self::ERR_COMMAND);
            } else {
                $this->set_error(self::ERR_COMMAND, self::_t('Couldn\'t get background jobs details.'));
            }

            return false;
        }

        if (!isset($extra['async_task'])) {
            $extra['async_task'] = (!empty($job_arr['run_async']));
        } else {
            $extra['async_task'] = (!empty($extra['async_task']));
        }

        $pub_key = microtime(true);

        if (false === ($ecrypted_params = PHS_Crypt::quick_encode($job_arr['id'].'::'.(!empty($extra['force_run']) ? '1' : '0').'::'.md5($job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'])))) {
            $this->set_error(self::ERR_COMMAND, self::_t('Error obtaining background job command arguments.'));

            return false;
        }

        $clean_cmd = PHP_EXEC.' '.PHS::get_agent_path().' '.$ecrypted_params.'::'.$pub_key;

        if (stripos(PHP_OS, 'win') === 0) {
            // launching background task under windows
            $cmd = 'start '.(!empty($extra['async_task']) ? ' /B ' : '').$clean_cmd;
        } else {
            $cmd = $clean_cmd.' 2>/dev/null >&- <&- >/dev/null';
            if (!empty($extra['async_task'])) {
                $cmd .= ' &';
            }
        }

        return [
            'cmd'     => $cmd,
            'pub_key' => $pub_key,
        ];
    }

    /**
     * Any agent jobs that are not following stalling policy will be stopped
     * @return array|false
     */
    public function check_stalling_agent_jobs()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        $return_arr = [];
        $return_arr['jobs_running'] = 0;
        $return_arr['jobs_not_dead'] = 0;
        $return_arr['jobs_stopped'] = 0;
        $return_arr['jobs_stopped_error'] = 0;

        $list_arr = $agent_jobs_model->fetch_default_flow_params();
        $list_arr['fields']['is_running'] = ['check' => 'IS', 'raw_value' => 'NOT NULL'];
        $list_arr['fields']['pid'] = ['check' => '!=', 'value' => '0'];
        $list_arr['fields']['status'] = $agent_jobs_model::STATUS_ACTIVE;

        if (!($jobs_list = $agent_jobs_model->get_list($list_arr))
         || !is_array($jobs_list)) {
            return $return_arr;
        }

        $return_arr['jobs_running'] = count($jobs_list);

        foreach ($jobs_list as $job_arr) {
            if (!$agent_jobs_model->is_job_dead_as_per_stalling_policy($job_arr)) {
                $return_arr['jobs_not_dead']++;
                continue;
            }

            if (!$agent_jobs_model->stop_job($job_arr)) {
                $return_arr['jobs_stopped_error']++;
            } else {
                $return_arr['jobs_stopped']++;
            }
        }

        return $return_arr;
    }

    public function check_agent_jobs()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        $return_arr = [];
        $return_arr['jobs_count'] = 0;
        $return_arr['jobs_errors'] = 0;
        $return_arr['jobs_success'] = 0;

        $list_arr = $agent_jobs_model->fetch_default_flow_params();
        $list_arr['fields']['is_running'] = ['check' => 'IS', 'raw_value' => 'NULL'];
        $list_arr['fields']['timed_action'] = ['check' => '<=', 'value' => date($agent_jobs_model::DATETIME_DB)];
        $list_arr['fields']['status'] = $agent_jobs_model::STATUS_ACTIVE;
        $list_arr['order_by'] = 'run_async DESC';

        if (!($jobs_list = $agent_jobs_model->get_list($list_arr))
         || !is_array($jobs_list)) {
            return $return_arr;
        }

        $return_arr['jobs_count'] = count($jobs_list);

        foreach ($jobs_list as $job_arr) {
            if ($this->run_job($job_arr)) {
                $return_arr['jobs_success']++;
            } else {
                $return_arr['jobs_errors']++;

                if ($this->has_error()) {
                    $error_msg = $this->get_error_message();
                } else {
                    $error_msg = 'Error launching agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']';
                }

                PHS_Logger::error($error_msg, PHS_Logger::TYPE_AGENT);
            }
        }

        return $return_arr;
    }

    public static function get_agent_routes()
    {
        self::st_reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if (!($plugins_model = PHS_Model_Plugins::get_instance())) {
            self::st_set_error(self::ERR_AVAILABLE_ACTIONS, self::_t('Couldn\'t load plugins model.'));

            return false;
        }

        if (!($plugins_list = $plugins_model->cache_all_dir_details())) {
            $plugins_list = [];
        }

        $available_plugins_arr = [];

        if (($agent_controllers = self::get_agent_available_controllers())
        && ($agent_actions = self::get_agent_available_actions())) {
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['info'] = PHS_Plugin::core_plugin_details_fields();
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['controllers'] = $agent_controllers;
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['actions'] = $agent_actions;
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['instance'] = false;
        }

        /** @var \phs\libraries\PHS_Plugin $plugin_instance */
        foreach ($plugins_list as $plugin_name => $plugin_instance) {
            if (!($plugin_info_arr = $plugin_instance->get_plugin_info())
             || empty($plugin_info_arr['is_installed'])
             || !($agent_controllers = self::get_agent_available_controllers($plugin_name))
             || !($agent_actions = self::get_agent_available_actions($plugin_name))) {
                continue;
            }

            $available_plugins_arr[$plugin_name]['info'] = $plugin_info_arr;
            $available_plugins_arr[$plugin_name]['controllers'] = $agent_controllers;
            $available_plugins_arr[$plugin_name]['actions'] = $agent_actions;
            $available_plugins_arr[$plugin_name]['instance'] = $plugin_instance;
        }

        return $available_plugins_arr;
    }

    public static function get_agent_available_controllers(?string $plugin = null) : array
    {
        self::st_reset_error();

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        if (!($controller_names = PHS::get_plugin_scripts_from_dir($plugin, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER))
         || !is_array($controller_names)) {
            return [];
        }

        $available_controllers = [];
        foreach ($controller_names as $controller_info) {
            /** @var \phs\libraries\PHS_Controller $controller_obj */
            if (empty($controller_info['file'])
             || !($controller_obj = PHS::load_controller($controller_info['file'], $plugin))
             || !$controller_obj->scope_is_allowed(PHS_Scope::SCOPE_AGENT)) {
                continue;
            }

            $available_controllers[$controller_info['file']] = $controller_obj;
        }

        return $available_controllers;
    }

    public static function get_agent_available_actions(?string $plugin = null)
    {
        self::st_reset_error();

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        if (!($action_names = PHS::get_plugin_scripts_from_dir($plugin, PHS_Instantiable::INSTANCE_TYPE_ACTION))
         || !is_array($action_names)) {
            return [];
        }

        $available_actions = [];
        foreach ($action_names as $action_info) {
            /** @var \phs\libraries\PHS_Action $action_obj */
            if (empty($action_info['file'])
             || !($action_obj = PHS::load_action($action_info['file'], $plugin, (!empty($action_info['dir']) ? $action_info['dir'] : '')))
             || !$action_obj->scope_is_allowed(PHS_Scope::SCOPE_AGENT)) {
                continue;
            }

            $action_name = (!empty($action_info['dir']) ? $action_info['dir'].'/' : '').$action_info['file'];

            $available_actions[$action_name] = $action_obj;
        }

        return $available_actions;
    }

    public static function current_job_data($job_data = null)
    {
        if ($job_data === null) {
            return self::get_data(self::DATA_AGENT_KEY);
        }

        return self::set_data(self::DATA_AGENT_KEY, $job_data);
    }

    public static function get_current_job_parameters()
    {
        if (!($job_arr = self::current_job_data())
         || !is_array($job_arr)
         || empty($job_arr['params'])
         || !($job_params_arr = @json_decode($job_arr['params'], true))) {
            return [];
        }

        return $job_params_arr;
    }

    public static function current_job_is_forced()
    {
        return !(!($job_params = self::get_current_job_parameters())
         || empty($job_params['force_job']));
    }

    public static function remove_job_handler($handler)
    {
        self::st_reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (empty($handler)) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Please provide a job handler.'));

            return false;
        }

        if (!($existing_job = $agent_jobs_model->get_details_fields(['handler' => $handler]))) {
            return true;
        }

        $remove_params = [];
        $remove_params['agent_jobs_model'] = $agent_jobs_model;

        return self::remove_job($existing_job, $remove_params);
    }

    /**
     * @param int|array $job_data
     * @param bool|array $params
     *
     * @return array|bool|int|string
     */
    public static function remove_job($job_data, $params = false)
    {
        self::st_reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (empty($params['agent_jobs_model'])) {
            $agent_jobs_model = false;
        } else {
            $agent_jobs_model = $params['agent_jobs_model'];
        }

        if (empty($agent_jobs_model)
        && !($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (empty($job_data)
         || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Couldn\'t load agent job details from database.'));

            return false;
        }

        if (!$agent_jobs_model->hard_delete($job_arr)) {
            if ($agent_jobs_model->has_error()) {
                self::st_copy_error($agent_jobs_model, self::ERR_JOB_DB);
            } else {
                self::st_set_error(self::ERR_JOB_DB, self::_t('Couldn\'t delete agent job from database.'));
            }

            return false;
        }

        return $job_arr;
    }

    /**
     * @param string $handler
     * @param string|array $route
     * @param int $once_every_seconds
     * @param bool|array $params
     * @param bool|array $extra
     *
     * @return array|bool|int
     */
    public static function add_job($handler, $route, $once_every_seconds, $params = false, $extra = false)
    {
        // We don't use here PHS::route_exists() because route_exists() will instantiate plugin, controller and action and if they have errors
        // launching script will die...
        self::st_reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        if (!empty($extra['title'])) {
            $extra['title'] = trim($extra['title']);
        } else {
            $extra['title'] = '';
        }

        if (!isset($extra['run_async'])) {
            $extra['run_async'] = true;
        } else {
            $extra['run_async'] = (!empty($extra['run_async']));
        }

        // This tells if job was added by plugin or is an user defined job
        if (empty($extra['plugin']) || !is_string($extra['plugin'])) {
            $extra['plugin'] = '';
        } else {
            $extra['plugin'] = trim($extra['plugin']);
        }

        $extra['stalling_minutes'] = (!empty($extra['stalling_minutes']) ? (int)$extra['stalling_minutes'] : 0);

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($handler) || !is_string($handler)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide a handler for this agent job.'));

            return false;
        }

        $once_every_seconds = (int)$once_every_seconds;

        $route_parts = false;
        if (is_string($route)
        && !($route_parts = PHS::parse_route($route))) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid route for agent job.'));
            }

            return false;
        }

        if (is_array($route)) {
            $route_parts = $route;
        }

        if (empty($route_parts) || !is_array($route_parts)) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid route for agent job.'));
            }

            return false;
        }

        if (!isset($route_parts['plugin'])) {
            $route_parts['plugin'] = false;
        }
        if (!isset($route_parts['controller'])) {
            $route_parts['controller'] = false;
        }
        if (!isset($route_parts['action'])) {
            $route_parts['action'] = false;
        }
        if (!isset($route_parts['action_dir'])) {
            $route_parts['action_dir'] = false;
        }

        if (!($cleaned_route = PHS::route_from_parts(
            [
                'p'  => $route_parts['plugin'],
                'c'  => $route_parts['controller'],
                'a'  => $route_parts['action'],
                'ad' => $route_parts['action_dir'],
            ]
        ))) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid route for agent job.'));
            }

            return false;
        }

        if (empty($extra['status'])
         || !$agent_jobs_model->valid_status($extra['status'])) {
            $extra['status'] = $agent_jobs_model::STATUS_INACTIVE;
        }

        if (($existing_job = $agent_jobs_model->get_details_fields(['handler' => $handler]))) {
            // At this point it doesn't matter if job is currently running as fields edited won't affect running flow of tasks...
            $edit_arr = [];
            $edit_arr['title'] = $extra['title'];
            $edit_arr['handler'] = $handler;
            $edit_arr['route'] = $cleaned_route;
            $edit_arr['params'] = (!empty($params) ? @json_encode($params) : null);
            $edit_arr['timed_seconds'] = $once_every_seconds;
            $edit_arr['run_async'] = ($extra['run_async'] ? 1 : 0);
            $edit_arr['plugin'] = $extra['plugin'];
            $edit_arr['stalling_minutes'] = $extra['stalling_minutes'];

            if (!($job_arr = $agent_jobs_model->edit($existing_job, ['fields' => $edit_arr]))) {
                if ($agent_jobs_model->has_error()) {
                    self::st_copy_error($agent_jobs_model);
                } else {
                    self::st_set_error(self::ERR_DB_INSERT, self::_t('Couldn\'t save agent job details in database. Please try again.'));
                }

                return false;
            }
        } else {
            $insert_arr = [];
            $insert_arr['title'] = $extra['title'];
            $insert_arr['handler'] = $handler;
            $insert_arr['pid'] = 0;
            $insert_arr['route'] = $cleaned_route;
            $insert_arr['params'] = (!empty($params) ? @json_encode($params) : null);
            $insert_arr['timed_seconds'] = $once_every_seconds;
            $insert_arr['run_async'] = ($extra['run_async'] ? 1 : 0);
            $insert_arr['status'] = $extra['status'];
            $insert_arr['plugin'] = $extra['plugin'];
            $insert_arr['stalling_minutes'] = $extra['stalling_minutes'];

            if (!($job_arr = $agent_jobs_model->insert(['fields' => $insert_arr]))
             || empty($job_arr['id'])) {
                if ($agent_jobs_model->has_error()) {
                    self::st_copy_error($agent_jobs_model);
                } else {
                    self::st_set_error(self::ERR_DB_INSERT, self::_t('Couldn\'t save agent job details in database. Please try again.'));
                }

                return false;
            }
        }

        return $job_arr;
    }

    public static function suspend_agent_jobs($plugin) : bool
    {
        self::st_reset_error();

        if (!($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin name.'));

            return false;
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (!($flow_params = $agent_jobs_model->fetch_default_flow_params())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t obtain agent jobs flow parameters.'));

            return false;
        }

        if (!db_query('UPDATE `'.$agent_jobs_model->get_flow_table_name($flow_params).'`'
                       .' SET status = \''.$agent_jobs_model::STATUS_SUSPENDED.'\' '
                       .' WHERE '
                       .' plugin = \''.prepare_data($plugin_name).'\' AND status = \''.$agent_jobs_model::STATUS_ACTIVE.'\'', $flow_params['db_connection'])) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Error running query to suspend agent jobs for provided plugin.'));

            return false;
        }

        return true;
    }

    public static function unsuspend_agent_jobs($plugin) : bool
    {
        self::st_reset_error();

        if (!($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin name.'));

            return false;
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (!($flow_params = $agent_jobs_model->fetch_default_flow_params())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t obtain agent jobs flow parameters.'));

            return false;
        }

        if (!db_query('UPDATE `'.$agent_jobs_model->get_flow_table_name($flow_params).'`'
                       .' SET status = \''.$agent_jobs_model::STATUS_ACTIVE.'\' '
                       .' WHERE '
                       .' plugin = \''.prepare_data($plugin_name).'\' AND status = \''.$agent_jobs_model::STATUS_SUSPENDED.'\'', $flow_params['db_connection'])) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Error running query to re-activate agent jobs for provided plugin.'));

            return false;
        }

        return true;
    }

    public static function bg_validate_input(string $input_str) : ?array
    {
        if (empty($input_str)
         || @strstr($input_str, '::') === false
         || !($parts_arr = explode('::', $input_str, 2))
         || empty($parts_arr[0]) || empty($parts_arr[1])) {
            PHS_Logger::error('Invalid input', PHS_Logger::TYPE_AGENT);

            return null;
        }

        [$crypted_data, $pub_key] = $parts_arr;

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($decrypted_data = PHS_Crypt::quick_decode($crypted_data))
         || !($decrypted_parts = explode('::', $decrypted_data, 3))
         || empty($decrypted_parts[0]) || !isset($decrypted_parts[1]) || empty($decrypted_parts[2])
         || !($job_id = (int)$decrypted_parts[0])
         || !($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())
         || !($job_arr = $agent_jobs_model->get_details($job_id))
         || $decrypted_parts[2] !== md5($job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'])) {
            PHS_Logger::error('Input validation failed', PHS_Logger::TYPE_AGENT);

            return null;
        }

        return [
            'job_data'         => $job_arr,
            'pub_key'          => $pub_key,
            'force_run'        => (!empty($decrypted_parts[1])),
            'agent_jobs_model' => $agent_jobs_model,
        ];
    }

    public static function get_stalling_minutes() : int
    {
        static $stalling_minutes = null;

        if ($stalling_minutes !== null) {
            return $stalling_minutes;
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())
         || !($stalling_minutes = $agent_jobs_model->get_stalling_minutes())) {
            $stalling_minutes = 0;
        }

        return $stalling_minutes;
    }

    /**
     * @param false|array $extra
     *
     * @return array|bool
     */
    public static function refresh_current_job($extra = false)
    {
        self::st_reset_error();

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!empty($extra['agent_jobs_model'])) {
            $agent_jobs_model = $extra['agent_jobs_model'];
        } else {
            $agent_jobs_model = PHS_Model_Agent_jobs::get_instance();
        }

        if (empty($agent_jobs_model)
         || !($job_data = self::current_job_data())
         || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            if ($agent_jobs_model->has_error()) {
                self::st_copy_error($agent_jobs_model);
            }

            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_JOB_DB, self::_t('Couldn\'t get agent job details.'));
            }

            return false;
        }

        if (!($new_job = $agent_jobs_model->refresh_job($job_arr))) {
            if ($agent_jobs_model->has_error()) {
                self::st_copy_error($agent_jobs_model);
            }

            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_JOB_DB, self::_t('Couldn\'t refresh agent job details.'));
            }

            return false;
        }

        return $new_job;
    }

    /**
     * @param int|array $job_data
     * @param false|array $extra
     *
     * @return null|array|bool
     */
    public static function bg_run_job($job_data, $extra = false)
    {
        self::st_reset_error();

        if (empty($extra) || !is_array($extra)) {
            $extra = [];
        }

        $extra['force_run'] = !empty($extra['force_run']);

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if (!empty($extra['agent_jobs_model'])) {
            $agent_jobs_model = $extra['agent_jobs_model'];
        } else {
            $agent_jobs_model = PHS_Model_Agent_jobs::get_instance();
        }

        if (empty($job_data)
         || empty($agent_jobs_model)
         || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            if ($agent_jobs_model->has_error()) {
                self::st_copy_error($agent_jobs_model);
            }

            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Couldn\'t get agent job details.'));
            }

            return false;
        }

        self::current_job_data($job_arr);

        if ($agent_jobs_model->job_is_running($job_arr)) {
            if (($job_stalling = $agent_jobs_model->job_is_stalling($job_arr)) === null) {
                if ($agent_jobs_model->has_error()) {
                    self::st_copy_error($agent_jobs_model);
                }

                return false;
            }

            if (empty($job_stalling)) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Agent job already running.'));

                return false;
            }

            if (empty($extra['force_run'])) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Agent job seems to stall. Run not told to force execution.'));

                return false;
            }
        }

        $job_params = [];
        $job_params['force_job'] = (!empty($extra['force_run']));

        if (!($new_job_arr = $agent_jobs_model->start_job($job_arr, $job_params))) {
            if ($agent_jobs_model->has_error()) {
                self::st_copy_error($agent_jobs_model);
            }

            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Couldn\'t save background jobs details in database.'));
            }

            return false;
        }

        $job_arr = $new_job_arr;

        self::current_job_data($job_arr);

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_AGENT)
         || !PHS::set_route($job_arr['route'])) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Error preparing environment.'));
            }

            $error_arr = self::st_get_error();

            $error_params = [];
            $error_params['last_error'] = self::st_get_error_message();

            $agent_jobs_model->stop_job($job_arr, $error_params);

            self::st_copy_error_from_array($error_arr);

            return false;
        }

        $execution_params = [];
        $execution_params['die_on_error'] = false;

        if (!($action_result = PHS::execute_route($execution_params))) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Error executing route.'));
            }

            $error_arr = self::st_get_error();

            $error_params = [];
            $error_params['last_error'] = self::st_get_error_message();

            $agent_jobs_model->stop_job($job_arr, $error_params);

            self::st_copy_error_from_array($error_arr);

            return false;
        }

        $agent_jobs_model->stop_job($job_arr);

        return $action_result;
    }
}
