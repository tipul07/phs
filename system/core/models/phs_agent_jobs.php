<?php
namespace phs\system\core\models;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Record_data;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Agent_jobs extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const ERR_DB_JOB = 10000;

    public const STALLING_PID = 1, STALLING_TIME = 2, STALLING_BOTH = 3;

    public const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_SUSPENDED = 3;

    protected static array $STALLING_ARR = [
        self::STALLING_PID  => ['title' => 'Check process is alive'],
        self::STALLING_TIME => ['title' => 'Check only time passed'],
        self::STALLING_BOTH => ['title' => 'Use both policies'],
    ];

    protected static array $STATUSES_ARR = [
        self::STATUS_ACTIVE    => ['title' => 'Active'],
        self::STATUS_INACTIVE  => ['title' => 'Inactive'],
        self::STATUS_SUSPENDED => ['title' => 'Suspended'],
    ];

    public function get_model_version() : string
    {
        return '1.1.0';
    }

    public function get_table_names() : array
    {
        return ['bg_agent'];
    }

    public function get_main_table_name() : string
    {
        return 'bg_agent';
    }

    public function get_stalling_policies(null | bool | string $lang = false) : array
    {
        static $policies_arr = [];

        if (empty(self::$STALLING_ARR)) {
            return [];
        }

        if (!$lang
         && !empty($policies_arr)) {
            return $policies_arr;
        }

        $result_arr = $this->translate_array_keys(self::$STALLING_ARR, ['title'], $lang);

        if (!$lang) {
            $policies_arr = $result_arr;
        }

        return $result_arr;
    }

    public function get_stalling_policies_as_key_val(null | bool | string $lang = false) : ?array
    {
        static $policies_key_val_arr = null;

        if (!$lang
         && $policies_key_val_arr !== null) {
            return $policies_key_val_arr;
        }

        $key_val_arr = [];
        if (($policies = $this->get_stalling_policies($lang))) {
            foreach ($policies as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if (!$lang) {
            $policies_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    public function valid_stalling_policy(int $policy, null | bool | string $lang = false) : ?array
    {
        if (!$policy) {
            return null;
        }

        return $this->get_stalling_policies($lang)[$policy] ?? null;
    }

    public function get_settings_structure() : array
    {
        if (!($policies_arr = $this->get_stalling_policies_as_key_val())) {
            $policies_arr = [];
        }

        return [
            'minutes_to_stall' => [
                'display_name' => 'Minutes to stall (generic)',
                'display_hint' => 'After how many minutes should we consider agent jobs which don\'t have specific stalling time as stalling',
                'type'         => PHS_Params::T_INT,
                'default'      => 60,
            ],
            'stalling_policy' => [
                'display_name' => 'Stalling policy',
                'display_hint' => 'When a job is stalling, how system should consider job as dead? If one condition of policy is true, we will consider job as dead and a new agent job will start.',
                'type'         => PHS_Params::T_INT,
                'default'      => self::STALLING_PID,
                'values_arr'   => $policies_arr,
            ],
        ];
    }

    public function act_activate(int | array | PHS_Record_data $job_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job details not found in database.'));

            return null;
        }

        if ($this->job_is_suspended($job_arr)) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job is suspended. You must activate plugin to activate it again.'));

            return null;
        }

        if (!($new_job = $this->edit($job_arr, ['fields' => ['status' => self::STATUS_ACTIVE]]))) {
            $this->set_error_if_not_set(self::ERR_DB_JOB, self::_t('Error updating job details.'));

            return null;
        }

        return $new_job;
    }

    public function act_inactivate(int | array | PHS_Record_data $job_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job details not found in database.'));

            return null;
        }

        if ($this->job_is_suspended($job_arr)) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job is suspended. You must activate plugin to inactivate it.'));

            return null;
        }

        if (!($new_job = $this->edit($job_arr, ['fields' => ['status' => self::STATUS_INACTIVE]]))) {
            $this->set_error_if_not_set(self::ERR_DB_JOB, self::_t('Error updating job details.'));

            return null;
        }

        return $new_job;
    }

    public function act_suspend(int | array | PHS_Record_data $job_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job details not found in database.'));

            return null;
        }

        if (!($new_job = $this->edit($job_arr, ['fields' => ['status' => self::STATUS_SUSPENDED]]))) {
            $this->set_error_if_not_set(self::ERR_DB_JOB, self::_t('Error updating job details.'));

            return null;
        }

        return $new_job;
    }

    public function act_delete(int | array | PHS_Record_data $job_data) : bool
    {
        $this->reset_error();

        if (empty($job_data)
            || !($flow_params = $this->fetch_default_flow_params())
            || !($table_name = $this->get_flow_table_name($flow_params))
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job details not found in database.'));

            return false;
        }

        if (!db_query('DELETE FROM `'.$table_name.'` WHERE id = \''.$job_arr['id'].'\'', $flow_params['db_connection'])) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Error deleting agent job from database.'));

            return false;
        }

        return true;
    }

    public function start_job(int | array | PHS_Record_data $job_data, array $params = []) : null | array | PHS_Record_data
    {
        $this->reset_error();

        $params['force_job'] = !empty($params['force_job']);

        if (empty($job_data)
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Agent job details not found in database.'));

            return null;
        }

        if (empty($job_arr['params'])
            || !($job_params_arr = @json_decode($job_arr['params'], true))) {
            $job_params_arr = [];
        }

        if (!empty($params['force_job'])) {
            $job_params_arr['force_job'] = true;
        }

        $pid = @getmypid() ?: -1;

        $edit_arr = [];
        $edit_arr['is_running'] = date(self::DATETIME_DB);
        $edit_arr['pid'] = $pid;
        $edit_arr['last_error'] = null;
        if (!empty($job_params_arr)) {
            $edit_arr['params'] = @json_encode($job_params_arr);
        }

        PHS_Logger::notice('Starting agent job (#'.$job_arr['id'].'), route ['.$job_arr['route'].'] with pid ['.$pid.']', PHS_Logger::TYPE_AGENT);

        if (!($new_job_arr = $this->edit($job_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_DB_JOB, $this->_pt('Error updating job record data.'));

            return null;
        }

        return $new_job_arr;
    }

    public function stop_job(int | array | PHS_Record_data $job_data, array $params = []) : null | array | PHS_Record_data
    {
        $this->reset_error();

        /** @var PHS_Plugin_Admin $admin_plugin */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return null;
        }

        $params ??= [];
        $params['last_error'] ??= null;
        $params['last_error_code'] ??= 0;

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Job not found in database.'));

            return null;
        }

        $next_time = empty($job_arr['is_running'])
            ? time()
            : parse_db_date($job_arr['is_running']);

        // Remove allowance interval to be sure we're not at the limit with few seconds (time which took to bootstrap agent job)
        // Allowance interval doesn't affect time unit at which scripts can run as linux crontab can run at minimum every minute
        if (!empty($job_arr['timed_seconds'])) {
            $next_time += (int)$job_arr['timed_seconds'] - $admin_plugin->agent_jobs_allowance_interval();
        }

        if (!($new_params = $this->_reset_job_parameters_on_stop($job_arr['params']))) {
            $new_params = null;
        }

        $edit_arr = [];
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = $params['last_error'];
        $edit_arr['is_running'] = null;
        $edit_arr['last_action'] = date(self::DATETIME_DB);
        $edit_arr['timed_action'] = date(self::DATETIME_DB, $next_time);
        $edit_arr['params'] = $new_params;

        if (!($new_job_arr = $this->edit($job_arr, ['fields' => $edit_arr]))) {
            return null;
        }

        /** @var PHS_Model_Agent_jobs_monitor $jobs_monitor_model */
        if ($admin_plugin->monitor_agent_jobs()
            && ($jobs_monitor_model = PHS_Model_Agent_jobs_monitor::get_instance())) {
            if (empty($params['last_error'])) {
                if (!$jobs_monitor_model->job_success($job_arr)) {
                    PHS_Logger::error('Error saving job monitor data SUCCESS for agent job: '
                                      .'[#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_AGENT);
                }
            } elseif (!$jobs_monitor_model->job_error($job_arr, $params['last_error'], $params['last_error_code'])) {
                PHS_Logger::error('Error saving job monitor data FAILED for agent job: '
                                  .'[#'.$job_arr['id'].']['.$job_arr['route'].']: (Code: '.$params['last_error_code'].') '.$params['last_error'], PHS_Logger::TYPE_AGENT);
            }
        }

        return $new_job_arr;
    }

    public function refresh_job(int | array | PHS_Record_data $job_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($job_data)
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Job not found in database.'));

            return null;
        }

        $cdate = date(self::DATETIME_DB);

        $edit_arr = [];
        if (empty($job_arr['pid'])) {
            if (!($pid = @getmypid())) {
                $pid = -1;
            }

            $edit_arr['pid'] = $pid;
        }

        if (empty($job_arr['is_running'])) {
            $edit_arr['is_running'] = $cdate;
        }

        $edit_arr['last_action'] = $cdate;

        if (!($new_record_arr = $this->edit($job_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Error updating agent job.'));

            return null;
        }

        return $new_record_arr;
    }

    public function get_stalling_minutes() : int
    {
        static $stalling_minutes = null;

        if ($stalling_minutes !== null) {
            return $stalling_minutes;
        }

        $stalling_minutes = (int)($this->get_db_settings()['minutes_to_stall'] ?? 0);

        return $stalling_minutes;
    }

    public function get_stalling_policy() : int
    {
        static $stalling_policy = null;

        if ($stalling_policy !== null) {
            return $stalling_policy;
        }

        $stalling_policy = (int)($this->get_db_settings()['stalling_policy'] ?? self::STALLING_PID);

        return $stalling_policy;
    }

    /**
     * @param int|array $job_data
     *
     * @return null|bool
     */
    public function is_job_dead_as_per_stalling_policy($job_data) : ?bool
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Couldn\'t get agent jobs details.'));

            return null;
        }

        $stalling_policy = $this->get_stalling_policy();

        return
            $this->job_is_running($job_arr)
            && (
                (($stalling_policy === self::STALLING_PID || $stalling_policy === self::STALLING_BOTH)
                 && (empty($job_arr['pid'])
                     || !($process_details = PHS_Utils::get_process_details($job_arr['pid']))
                     || empty($process_details['is_running'])
                 ))

                 || (($stalling_policy === self::STALLING_TIME || $stalling_policy === self::STALLING_BOTH)
                  && $this->job_is_stalling($job_arr)
                 )
            );
    }

    public function get_job_stalling_minutes($job_data) : int
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Couldn\'t get agent jobs details.'));

            return 0;
        }

        if (!empty($job_arr['stalling_minutes'])) {
            return (int)$job_arr['stalling_minutes'];
        }

        return $this->get_stalling_minutes();
    }

    public function get_job_seconds_since_last_action(int | array $job_data) : ?int
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Couldn\'t get agent jobs details.'));

            return null;
        }

        return !empty($job_arr['last_action']) ? seconds_passed($job_arr['last_action']) : 0;
    }

    public function job_is_stalling(int | array $job_data) : ?bool
    {
        $this->reset_error();

        if (empty($job_data)
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t get agent jobs details.'));

            return null;
        }

        return $this->job_is_running($job_arr)
               && ($minutes_to_stall = $this->get_job_stalling_minutes($job_arr))
               && floor($this->get_job_seconds_since_last_action($job_arr) / 60) >= $minutes_to_stall;
    }

    public function job_runs_async($job_data) : bool
    {
        return !(empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))
         || empty($job_arr['run_async']));
    }

    public function job_is_running($job_data) : bool
    {
        return !(empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))
         || empty($job_arr['pid'])
         || empty_db_date($job_arr['is_running']));
    }

    public function job_is_active($job_data) : bool
    {
        return !(empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))
         || (int)$job_arr['status'] !== self::STATUS_ACTIVE);
    }

    public function job_is_inactive($job_data) : bool
    {
        return !(empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))
         || (int)$job_arr['status'] !== self::STATUS_INACTIVE);
    }

    public function job_is_suspended($job_data) : bool
    {
        return !(empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))
         || (int)$job_arr['status'] !== self::STATUS_SUSPENDED);
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'bg_agent':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'title' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'comment'  => 'Descriptive title',
                    ],
                    'handler' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'index'    => true,
                        'comment'  => 'String which will help identify task',
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'index'    => true,
                        'comment'  => 'Tells if job was installed by a plugin',
                    ],
                    'pid' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'route' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'params' => [
                        'type'     => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ],
                    'last_error' => [
                        'type'     => self::FTYPE_TEXT,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'is_running' => [
                        'type'    => self::FTYPE_DATETIME,
                        'comment' => 'Is route currently running',
                    ],
                    'run_async' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 1,
                        'comment' => 'Run this job asynchronous',
                    ],
                    'last_action' => [
                        'type'    => self::FTYPE_DATETIME,
                        'comment' => 'Last time action said is alive',
                    ],
                    'timed_seconds' => [
                        'type'    => self::FTYPE_INT,
                        'comment' => 'Once how many seconds should route run',
                    ],
                    'stalling_minutes' => [
                        'type'    => self::FTYPE_INT,
                        'comment' => 'Minutes after we should consider job stalling',
                    ],
                    'timed_action' => [
                        'type'    => self::FTYPE_DATETIME,
                        'index'   => true,
                        'comment' => 'Next time action should run',
                    ],
                    'status' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => self::STATUS_ACTIVE,
                        'comment' => 'Status of current job',
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_bg_agent($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        self::st_reset_error();

        if (empty($params['fields']['route'])
            || !PHS::route_exists($params['fields']['route'], ['action_accepts_scopes' => PHS_Scope::SCOPE_AGENT])) {
            $this->copy_or_set_static_error(self::ERR_INSERT, self::_t('Please provide a route.'));

            return false;
        }

        if (empty($params['fields']['handler'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a handler for this task.'));

            return false;
        }

        if ($this->get_details_fields(['handler' => $params['fields']['handler']])) {
            $this->set_error(self::ERR_INSERT, self::_t('Handler already defined in database.'));

            return false;
        }

        if (empty($params['fields']['title'])) {
            $params['fields']['title'] = '';
        }

        if (empty($params['fields']['plugin'])) {
            $params['fields']['plugin'] = '';
        }

        if (!isset($params['fields']['run_async'])) {
            $params['fields']['run_async'] = 1;
        } else {
            $params['fields']['run_async'] = (!empty($params['fields']['run_async']) ? 1 : 0);
        }

        if (empty($params['fields']['status'])
         || !$this->valid_status($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_ACTIVE;
        }

        if (empty($params['fields']['timed_seconds'])) {
            $params['fields']['timed_seconds'] = 0;
        } else {
            $params['fields']['timed_seconds'] = (int)$params['fields']['timed_seconds'];
        }

        if (empty($params['fields']['stalling_minutes'])) {
            $params['fields']['stalling_minutes'] = 0;
        } else {
            $params['fields']['stalling_minutes'] = (int)$params['fields']['stalling_minutes'];
        }

        $params['fields']['cdate'] = date(self::DATETIME_DB);

        $params['fields']['timed_action'] = date(self::DATETIME_DB, time() + $params['fields']['timed_seconds']);

        return $params;
    }

    protected function get_edit_prepare_params_bg_agent($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        $cdate = date(self::DATETIME_DB);

        if (!empty($params['fields']['handler'])) {
            $check_arr = [];
            $check_arr['handler'] = $params['fields']['handler'];
            $check_arr['id'] = ['check' => '!=', 'value' => $existing_data['id']];

            if ($this->get_details_fields($check_arr)) {
                $this->set_error(self::ERR_EDIT, self::_t('Handler already defined in database.'));

                return false;
            }
        }

        if (!empty($params['fields']['status'])
         && !$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_EDIT, self::_t('Agent job status is not defined.'));

            return false;
        }

        if (isset($params['fields']['run_async'])) {
            $params['fields']['run_async'] = (!empty($params['fields']['run_async']) ? 1 : 0);
        }

        if (isset($params['fields']['stalling_minutes'])) {
            $params['fields']['stalling_minutes'] = (!empty($params['fields']['stalling_minutes']) ? (int)$params['fields']['stalling_minutes'] : 0);
        }

        if (isset($params['fields']['is_running'])) {
            if (empty($params['fields']['is_running'])
             || empty_db_date($params['fields']['is_running'])) {
                $params['fields']['is_running'] = null;
            } else {
                $params['fields']['is_running'] = date(self::DATETIME_DB, parse_db_date($params['fields']['is_running']));
            }
        }

        // Update last_action field on any edit we do...
        if (empty($params['fields']['last_action'])
         || empty_db_date($params['fields']['last_action'])) {
            $params['fields']['last_action'] = $cdate;
        }

        return $params;
    }

    /**
     * @param null|string $job_params_str
     *
     * @return null|string
     */
    private function _reset_job_parameters_on_stop(?string $job_params_str) : ?string
    {
        if (empty($job_params_str)
         || !($job_params_arr = @json_decode($job_params_str, true))) {
            return null;
        }

        // Remove force job parameter (if set)
        $new_params = null;
        if (isset($job_params_arr['force_job'])) {
            unset($job_params_arr['force_job']);
            if (!empty($job_params_arr)) {
                $new_params = @json_encode($job_params_arr);
            }
        }

        return $new_params;
    }
}
