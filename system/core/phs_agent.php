<?php
namespace phs;

use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Record_data;
use phs\libraries\PHS_Instantiable;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Agent_jobs;
use phs\system\core\models\PHS_Model_Agent_jobs_monitor;

// ! @version 1.00

class PHS_Agent extends PHS_Registry
{
    public const ERR_DB_INSERT = 30000, ERR_COMMAND = 30001, ERR_RUN_JOB = 30002, ERR_JOB_DB = 30003, ERR_JOB_STALLING = 30004,
        ERR_AVAILABLE_ACTIONS = 30005;

    public const DATA_AGENT_KEY = 'bg_agent_data';

    private ?PHS_Model_Agent_jobs $_jobs_model = null;

    public function run_job(int | array | PHS_Record_data $job_data, array $extra = []) : bool | array
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !$admin_plugin->monitor_agent_jobs()
            || !($jobs_monitor_model = PHS_Model_Agent_jobs_monitor::get_instance())) {
            $jobs_monitor_model = null;
        }

        if (!$job_data
            || !($job_arr = $this->_jobs_model->data_to_array($job_data))) {
            $this->set_error(self::ERR_RUN_JOB, self::_t('Couldn\'t load agent jobs details from database.'));

            return false;
        }

        $extra['return_command'] = !empty($extra['return_command']);
        $extra['force_run'] = !empty($extra['force_run']);

        if (!$extra['force_run']
            && !$this->_jobs_model->job_is_active($job_arr)) {
            $this->set_error(self::ERR_RUN_JOB, self::_t('Agent job not active.'));

            return false;
        }

        if (!$extra['force_run']
            && $this->_jobs_model->job_is_running($job_arr)
            && !$this->_jobs_model->job_is_stalling($job_arr)) {
            $this->set_error(self::ERR_RUN_JOB, self::_t('Agent job is still running.'));

            return false;
        }

        $run_async = $this->_jobs_model->job_runs_async($job_arr);

        // Make sure we are not launching job from front-end...
        if (!PHS::are_we_in_a_background_thread()) {
            $run_async = true;
        }

        $cmd_extra = [];
        $cmd_extra['async_task'] = $run_async;
        $cmd_extra['force_run'] = $extra['force_run'];

        if (!($cmd_parts = $this->_get_job_command($job_arr, $cmd_extra))
            || empty($cmd_parts['cmd'])) {
            $this->set_error_if_not_set(self::ERR_COMMAND, self::_t('Couldn\'t get agent job command.'));

            return false;
        }

        // Agent job cannot be edited as it will be managed by PHS_Agent class...
        if (!empty($extra['return_command'])) {
            return ['cmd' => $cmd_parts['cmd'], ];
        }

        PHS_Logger::notice('Launching agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_AGENT);

        if ($jobs_monitor_model !== null
           && !$jobs_monitor_model->job_started($job_arr)) {
            PHS_Logger::error('Error saving job monitor data START for agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_AGENT);
        }

        if (PHS::st_debugging_mode()) {
            PHS_Logger::debug('Command ['.$cmd_parts['cmd'].']', PHS_Logger::TYPE_AGENT);
        }

        return @system($cmd_parts['cmd']) !== false;
    }

    /**
     * Any agent jobs that are not following stalling policy will be stopped
     * @return null|array
     */
    public function check_stalling_agent_jobs() : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $return_arr = [];
        $return_arr['jobs_running'] = 0;
        $return_arr['jobs_not_dead'] = 0;
        $return_arr['jobs_stopped'] = 0;
        $return_arr['jobs_stopped_error'] = 0;

        $list_arr = $this->_jobs_model->fetch_default_flow_params();
        $list_arr['fields']['is_running'] = ['check' => 'IS', 'raw_value' => 'NOT NULL'];
        $list_arr['fields']['pid'] = ['check' => '!=', 'value' => '0'];
        $list_arr['fields']['status'] = $this->_jobs_model::STATUS_ACTIVE;

        if (!($jobs_list = $this->_jobs_model->get_list($list_arr))) {
            return $return_arr;
        }

        $return_arr['jobs_running'] = count($jobs_list);

        foreach ($jobs_list as $job_arr) {
            if (!$this->_jobs_model->is_job_dead_as_per_stalling_policy($job_arr)) {
                $return_arr['jobs_not_dead']++;
                continue;
            }

            if (!$this->_jobs_model->stop_job($job_arr, ['last_error' => 'Job considered dead.'])) {
                $return_arr['jobs_stopped_error']++;
            } else {
                $return_arr['jobs_stopped']++;
            }
        }

        return $return_arr;
    }

    public function check_agent_jobs() : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $return_arr = [];
        $return_arr['jobs_count'] = 0;
        $return_arr['jobs_errors'] = 0;
        $return_arr['jobs_success'] = 0;

        $list_arr = $this->_jobs_model->fetch_default_flow_params();
        $list_arr['fields']['is_running'] = ['check' => 'IS', 'raw_value' => 'NULL'];
        $list_arr['fields']['timed_action'] = ['check' => '<=', 'value' => date($this->_jobs_model::DATETIME_DB)];
        $list_arr['fields']['status'] = $this->_jobs_model::STATUS_ACTIVE;
        $list_arr['order_by'] = 'run_async DESC';

        if (!($jobs_list = $this->_jobs_model->get_list($list_arr))) {
            return $return_arr;
        }

        $return_arr['jobs_count'] = count($jobs_list);

        foreach ($jobs_list as $job_arr) {
            if ($this->run_job($job_arr)) {
                $return_arr['jobs_success']++;
            } else {
                $return_arr['jobs_errors']++;

                PHS_Logger::error(
                    $this->get_simple_error_message('Error launching agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']'),
                    PHS_Logger::TYPE_AGENT
                );
            }
        }

        return $return_arr;
    }

    private function _get_job_command(int | array | PHS_Record_data $job_data, array $extra = []) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $extra['force_run'] = !empty($extra['force_run']);

        if (!$job_data
            || !($job_arr = $this->_jobs_model->data_to_array($job_data))) {
            $this->copy_or_set_error($this->_jobs_model,
                self::ERR_COMMAND, self::_t('Couldn\'t get background jobs details.'));

            return null;
        }

        $extra['async_task'] = !isset($extra['async_task'])
            ? !empty($job_arr['run_async'])
            : !empty($extra['async_task']);

        $pub_key = microtime(true);

        if (null === ($ecrypted_params = PHS_Crypt::quick_encode($job_arr['id'].'::'.(!empty($extra['force_run']) ? '1' : '0').'::'.md5($job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'])))) {
            $this->set_error(self::ERR_COMMAND, self::_t('Error obtaining background job command arguments.'));

            return null;
        }

        $clean_cmd = PHP_EXEC.' '.PHS::get_agent_path().' '.$ecrypted_params.'::'.$pub_key;

        if (PHS::running_on_windows()) {
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

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (!$this->_jobs_model && !($this->_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }

    public static function get_agent_routes() : ?array
    {
        self::st_reset_error();

        if (!($plugins_model = PHS_Model_Plugins::get_instance())) {
            self::st_set_error(self::ERR_AVAILABLE_ACTIONS, self::_t('Couldn\'t load plugins model.'));

            return null;
        }

        $plugins_list = $plugins_model->cache_all_dir_details() ?: [];

        $available_plugins_arr = [];

        if (($agent_controllers = self::get_agent_available_controllers())
        && ($agent_actions = self::get_agent_available_actions())) {
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['info'] = PHS_Plugin::core_plugin_details_fields();
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['controllers'] = $agent_controllers;
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['actions'] = $agent_actions;
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['instance'] = false;
        }

        /** @var PHS_Plugin $plugin_instance */
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

        if (!($controller_names = PHS::get_plugin_scripts_from_dir($plugin, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER))) {
            return [];
        }

        $available_controllers = [];
        foreach ($controller_names as $controller_info) {
            /** @var libraries\PHS_Controller $controller_obj */
            if (empty($controller_info['file'])
             || !($controller_obj = PHS::load_controller($controller_info['file'], $plugin))
             || !$controller_obj->scope_is_allowed(PHS_Scope::SCOPE_AGENT)) {
                continue;
            }

            $available_controllers[$controller_info['file']] = $controller_obj;
        }

        return $available_controllers;
    }

    public static function get_agent_available_actions(?string $plugin = null) : array
    {
        self::st_reset_error();

        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $plugin = null;
        }

        if (!($action_names = PHS::get_plugin_scripts_from_dir($plugin, PHS_Instantiable::INSTANCE_TYPE_ACTION))) {
            return [];
        }

        $available_actions = [];
        foreach ($action_names as $action_info) {
            /** @var PHS_Action $action_obj */
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

    public static function current_job_data(null | array | PHS_Record_data $job_data = null) : null | array | PHS_Record_data
    {
        if ($job_data === null) {
            return self::get_data(self::DATA_AGENT_KEY);
        }

        self::set_data(self::DATA_AGENT_KEY, $job_data);

        return $job_data;
    }

    public static function get_current_job_parameters() : array
    {
        if (!($job_arr = self::current_job_data())
            || empty($job_arr['params'])) {
            return [];
        }

        return @json_decode($job_arr['params'], true) ?: [];
    }

    public static function current_job_is_forced() : bool
    {
        return ($job_params = self::get_current_job_parameters())
               && !empty($job_params['force_job']);
    }

    public static function remove_job_handler(string $handler) : bool
    {
        self::st_reset_error();

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        if (empty($handler)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide a job handler.'));

            return false;
        }

        if (!($existing_job = $agent_jobs_model->get_details_fields(['handler' => $handler]))) {
            return true;
        }

        return (bool)self::remove_job($existing_job);
    }

    public static function remove_job_handler_array(array $handlers_arr) : bool
    {
        self::st_reset_error();

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        $is_success = true;
        $error_arr = null;
        foreach (self::extract_strings_from_array($handlers_arr) as $handler) {
            if (!($existing_job = $agent_jobs_model->get_details_fields(['handler' => $handler]))) {
                continue;
            }

            if (!self::remove_job($existing_job)) {
                $is_success = false;

                $error_arr = self::st_get_error();
            }
        }

        if (!$is_success) {
            self::st_copy_error_from_array(self::arr_set_error_if_not_set($error_arr,
                self::ERR_JOB_DB,
                self::_t('Couldn\'t delete all agent jobs from database.'))
            );
        }

        return $is_success;
    }

    public static function get_db_agent_jobs(string $plugin) : ?array
    {
        self::st_reset_error();

        if (!($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin name.'));

            return null;
        }

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Couldn\'t load agent jobs model.'));

            return null;
        }

        if (!($flow_params = $agent_jobs_model->fetch_default_flow_params())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t obtain agent jobs flow parameters.'));

            return null;
        }

        if (!($qid = db_query('SELECT * FROM `'.$agent_jobs_model->get_flow_table_name($flow_params).'`'
                              .' WHERE plugin = \''.prepare_data($plugin_name).'\'', $flow_params['db_connection']))) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Error running query to obtain agent jobs for provided plugin.'));

            return null;
        }

        $jobs_arr = [];
        while (($job_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            if (empty($job_arr['handler'])) {
                continue;
            }

            $jobs_arr[$job_arr['handler']] = $job_arr;
        }

        return $jobs_arr;
    }

    public static function remove_job(int | array | PHS_Record_data $job_data) : null | array | PHS_Record_data
    {
        self::st_reset_error();

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return null;
        }

        if (!$job_data
         || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Couldn\'t load agent job details from database.'));

            return null;
        }

        if (!$agent_jobs_model->hard_delete($job_arr)) {
            self::st_copy_or_set_error($agent_jobs_model,
                self::ERR_JOB_DB, self::_t('Couldn\'t delete agent job from database.'));

            return null;
        }

        return $job_arr;
    }

    public static function add_job(
        string $handler,
        string | array $route,
        int $once_every_seconds,
        array $params = [],
        array $extra = []
    ) : ?array {
        // We don't use here PHS::route_exists() because route_exists() will instantiate plugin, controller and action and if they have errors
        // launching script will die...
        self::st_reset_error();

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return null;
        }

        $extra['title'] = !empty($extra['title'])
            ? trim($extra['title'])
            : '[Title not provided]';

        $extra['run_async'] = !isset($extra['run_async']) || !empty($extra['run_async']);

        // This tells if job was added by plugin or is a user defined job
        if (empty($extra['plugin']) || !is_string($extra['plugin'])) {
            $extra['plugin'] = '';
        } else {
            $extra['plugin'] = trim($extra['plugin']);
        }

        $extra['stalling_minutes'] = (int)($extra['stalling_minutes'] ?? 0);

        if (!$handler) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide a handler for this agent job.'));

            return null;
        }

        $route_parts = null;
        if (is_string($route)
            && !($route_parts = PHS::parse_route($route))) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Invalid route for agent job.'));

            return null;
        }

        if (is_array($route)) {
            $route_parts = $route;
        }

        if (!$route_parts || !is_array($route_parts)) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Invalid route for agent job.'));

            return null;
        }

        $route_parts['plugin'] ??= null;
        $route_parts['controller'] ??= null;
        $route_parts['action'] ??= null;
        $route_parts['action_dir'] ??= null;

        if (!($cleaned_route = PHS::route_from_parts(
            [
                'p'  => $route_parts['plugin'],
                'c'  => $route_parts['controller'],
                'a'  => $route_parts['action'],
                'ad' => $route_parts['action_dir'],
            ]
        ))) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Invalid route for agent job.'));

            return null;
        }

        if (empty($extra['status'])
            || !$agent_jobs_model->valid_status($extra['status'])) {
            $extra['status'] = $agent_jobs_model::STATUS_INACTIVE;
        }

        $action_arr = [];
        $action_arr['title'] = $extra['title'];
        $action_arr['handler'] = $handler;
        $action_arr['route'] = $cleaned_route;
        $action_arr['params'] = (!empty($params) ? @json_encode($params) : null);
        $action_arr['timed_seconds'] = $once_every_seconds;
        $action_arr['run_async'] = ($extra['run_async'] ? 1 : 0);
        $action_arr['plugin'] = $extra['plugin'];
        $action_arr['stalling_minutes'] = $extra['stalling_minutes'];

        if (($existing_job = $agent_jobs_model->get_details_fields(['handler' => $handler]))) {
            // At this point it doesn't matter if job is currently running as fields edited won't affect running flow of tasks...
            if (!($job_arr = $agent_jobs_model->edit($existing_job, ['fields' => $action_arr]))) {
                self::st_copy_or_set_error($agent_jobs_model,
                    self::ERR_DB_INSERT, self::_t('Couldn\'t save agent job details in database. Please try again.'));

                return null;
            }
        } else {
            $action_arr['pid'] = 0;
            $action_arr['status'] = $extra['status'];

            if (!($job_arr = $agent_jobs_model->insert(['fields' => $action_arr]))) {
                self::st_copy_or_set_error($agent_jobs_model,
                    self::ERR_DB_INSERT, self::_t('Couldn\'t save agent job details in database. Please try again.'));

                return null;
            }
        }

        return $job_arr;
    }

    public static function suspend_agent_jobs(string $plugin) : bool
    {
        self::st_reset_error();

        if (!($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin name.'));

            return false;
        }

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

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

    public static function unsuspend_agent_jobs(string $plugin) : bool
    {
        self::st_reset_error();

        if (!($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin name.'));

            return false;
        }

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

    public static function remove_agent_jobs(string $plugin) : bool
    {
        self::st_reset_error();

        if (!($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin))) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Invalid plugin name.'));

            return false;
        }

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t load agent jobs model.'));

            return false;
        }

        if (!($flow_params = $agent_jobs_model->fetch_default_flow_params())) {
            self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t obtain agent jobs flow parameters.'));

            return false;
        }

        if (!db_query('DELETE FROM `'.$agent_jobs_model->get_flow_table_name($flow_params).'`'
                       .' WHERE plugin = \''.prepare_data($plugin_name).'\'', $flow_params['db_connection'])) {
            self::st_set_error(self::ERR_JOB_DB, self::_t('Error running query to remove agent jobs for provided plugin.'));

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

        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())
            || !($stalling_minutes = $agent_jobs_model->get_stalling_minutes())) {
            $stalling_minutes = 0;
        }

        return $stalling_minutes;
    }

    public static function refresh_current_job() : null | array | PHS_Record_data
    {
        self::st_reset_error();

        /** @var PHS_Model_Agent_jobs $agent_jobs_model */
        if (!($agent_jobs_model = PHS_Model_Agent_jobs::get_instance())
            || !($job_data = self::current_job_data())
            || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            self::st_copy_or_set_error($agent_jobs_model,
                self::ERR_JOB_DB, self::_t('Couldn\'t get agent job details.'));

            return null;
        }

        if (!($new_job = $agent_jobs_model->refresh_job($job_arr))) {
            self::st_copy_or_set_error($agent_jobs_model,
                self::ERR_JOB_DB, self::_t('Couldn\'t refresh agent job details.'));

            return null;
        }

        return $new_job;
    }

    public static function bg_run_job(int | array | PHS_Record_data $job_data, array $extra = []) : ?array
    {
        self::st_reset_error();

        $extra['force_run'] = !empty($extra['force_run']);

        /** @var PHS_Model_Agent_jobs $agent_jobs_model */
        $agent_jobs_model = $extra['agent_jobs_model'] ?? PHS_Model_Agent_jobs::get_instance();

        if (empty($agent_jobs_model)) {
            self::st_set_error(self::ERR_RESOURCES, self::_t('Error loading required resources.'));

            return null;
        }

        if (empty($job_data)
            || !($job_arr = $agent_jobs_model->data_to_array($job_data))) {
            self::st_set_error(self::ERR_RUN_JOB, self::_t('Couldn\'t get agent job details.'));

            return null;
        }

        self::current_job_data($job_arr);

        if ($agent_jobs_model->job_is_running($job_arr)) {
            if (($job_stalling = $agent_jobs_model->job_is_stalling($job_arr)) === null) {
                if ($agent_jobs_model->has_error()) {
                    self::st_copy_error($agent_jobs_model);
                }

                return null;
            }

            if (!$job_stalling) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Agent job already running.'));

                return null;
            }

            if (empty($extra['force_run'])) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Agent job seems to stall. Run not told to force execution.'));

                return null;
            }
        }

        if (!($new_job_arr = $agent_jobs_model->start_job($job_arr, ['force_job' => !empty($extra['force_run'])]))) {
            self::st_copy_or_set_error($agent_jobs_model,
                self::ERR_RUN_JOB, self::_t('Couldn\'t save background jobs details in database.'));

            return null;
        }

        $job_arr = $new_job_arr;

        self::current_job_data($job_arr);

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_AGENT)
            || !PHS::set_route($job_arr['route'])) {
            self::st_set_error_if_not_set(self::ERR_RUN_JOB, self::_t('Error preparing environment.'));

            $error_arr = self::st_get_error();

            $error_params = [];
            $error_params['last_error'] = self::st_get_simple_error_message();
            $error_params['last_error_code'] = self::st_get_error_code();

            $agent_jobs_model->stop_job($job_arr, $error_params);

            self::st_copy_error_from_array($error_arr);

            return null;
        }

        $technical_error = null;
        if (!($action_result = PHS::execute_route(['die_on_error' => false]))
            || (($technical_error = PHS_Action::get_technical_error_from_action_result($action_result))
                && self::arr_has_error($technical_error))
        ) {
            if (!$technical_error && !self::st_has_error()) {
                self::st_set_error(self::ERR_RUN_JOB, self::_t('Error executing route.'));
            }

            $error_arr = $technical_error ?? self::st_get_error();

            $error_params = [];
            $error_params['last_error'] = self::arr_get_simple_error_message($error_arr);
            $error_params['last_error_code'] = self::arr_get_error_code($error_arr);

            $agent_jobs_model->stop_job($job_arr, $error_params);

            self::st_copy_error_from_array($error_arr);

            return null;
        }

        $agent_jobs_model->stop_job($job_arr);

        return $action_result;
    }
}
