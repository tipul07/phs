<?php
namespace phs;

use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Record_data;
use phs\system\core\models\PHS_Model_Bg_jobs;

// ! @version 1.00

class PHS_Bg_jobs extends PHS_Registry
{
    public const ERR_DB_INSERT = 30000, ERR_COMMAND = 30001, ERR_RUN_JOB = 30002, ERR_JOB_DB = 30003, ERR_JOB_STALLING = 30004;

    public const DATA_JOB_KEY = 'bg_jobs_job_data';

    public static function current_job_data(null | array | PHS_Record_data $job_data = null) : null | array | PHS_Record_data
    {
        if ($job_data === null) {
            return self::get_data(self::DATA_JOB_KEY);
        }

        self::set_data(self::DATA_JOB_KEY, $job_data);

        return $job_data;
    }

    public static function get_current_job_parameters() : array
    {
        if (!($job_arr = self::current_job_data())
            || empty($job_arr['params'])
            || !($job_params_arr = @json_decode($job_arr['params'], true))) {
            return [];
        }

        return $job_params_arr;
    }

    public static function get_stalling_minutes() : int
    {
        static $stalling_minutes = null;

        if ($stalling_minutes !== null) {
            return $stalling_minutes;
        }

        /** @var PHS_Model_Bg_jobs $bg_jobs_model */
        if (!($bg_jobs_model = PHS_Model_Bg_jobs::get_instance())
            || !($stalling_minutes = $bg_jobs_model->get_stalling_minutes())) {
            $stalling_minutes = 0;
        }

        return $stalling_minutes;
    }

    public static function refresh_current_job() : ?array
    {
        self::st_reset_error();

        /** @var PHS_Model_Bg_jobs $bg_jobs_model */
        if (!($bg_jobs_model = PHS_Model_Bg_jobs::get_instance())
            || !($job_data = self::current_job_data())
            || !($job_arr = $bg_jobs_model->data_to_array($job_data))) {
            self::st_copy_or_set_error($bg_jobs_model,
                self::ERR_JOB_DB, self::_t('Couldn\'t get background job details.'));

            return null;
        }

        if (!($new_job = $bg_jobs_model->refresh_job($job_arr))) {
            self::st_copy_or_set_error($bg_jobs_model,
                self::ERR_JOB_DB, self::_t('Couldn\'t refresh background job details.'));

            return null;
        }

        return $new_job;
    }

    /**
     * @param string|array $route Route to be executed in background
     * @param array $params Parameters that will be passed to background job
     * @param array $extra Parameters used in this method
     *
     * @return bool|string|array
     */
    public static function run(array | string $route, array $params = [], array $extra = []) : bool | string | array
    {
        // We don't use here PHS::route_exists() because route_exists() will instantiate plugin, controller and action and if they have errors
        // launching script will die...
        self::st_reset_error();

        /** @var PHS_Model_Bg_jobs $bg_jobs_model */
        if (!($bg_jobs_model = PHS_Model_Bg_jobs::get_instance())) {
            self::st_set_error_if_not_set(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        if (!($route_parts = PHS::parse_route($route, false))) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Route is invalid.'));

            return false;
        }

        if (empty($route_parts) || !is_array($route_parts)) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Route is invalid.'));

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

        if (!($cleaned_route = PHS::route_from_parts([
            'p'  => $route_parts['plugin'],
            'c'  => $route_parts['controller'],
            'a'  => $route_parts['action'],
            'ad' => $route_parts['action_dir'],
        ]))) {
            self::st_set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Route is invalid.'));

            return false;
        }

        // Background job will consider foreground user as logged in in the background route
        $extra['with_foreground_user'] = !empty($extra['with_foreground_user']);

        // Tells if we need to pass the result of action to caller script
        // In case we need result of action, job should be called in synchronous and task should run right away, not timed (async_task=false)
        $extra['return_buffer'] = !empty($extra['return_buffer']);

        if (!empty($extra['return_buffer'])) {
            $extra['async_task'] = false;
            $extra['timed_action'] = false;
        }

        $extra['return_command'] = !empty($extra['return_command']);
        $extra['async_task'] = !isset($extra['async_task']) || !empty($extra['async_task']);
        $extra['same_thread_if_bg'] = !empty($extra['same_thread_if_bg']);

        if (empty($extra['timed_action'])) {
            $extra['timed_action'] = null;
        } else {
            $extra['timed_action'] = validate_db_date($extra['timed_action']);
        }

        $insert_arr = [];
        $insert_arr['uid'] = ($cuser = PHS::user_logged_in()) ? $cuser['id'] : 0;
        $insert_arr['session_id'] = ($extra['with_foreground_user'] && ($onuser = PHS::current_user_session())) ? $onuser['id'] : 0;
        $insert_arr['pid'] = 0;
        $insert_arr['route'] = $cleaned_route;
        $insert_arr['params'] = (!empty($params) ? @json_encode($params) : null);
        $insert_arr['timed_action'] = $extra['timed_action'];
        $insert_arr['return_buffer'] = ($extra['return_buffer'] ? 1 : 0);

        if (!($job_arr = $bg_jobs_model->insert(['fields' => $insert_arr]))
            || empty($job_arr['id'])) {
            self::st_copy_or_set_error($bg_jobs_model,
                self::ERR_DB_INSERT, self::_t('Couldn\'t save database details. Please try again.'));

            return false;
        }

        $cmd_extra = [];
        $cmd_extra['async_task'] = $extra['async_task'];
        $cmd_extra['return_buffer'] = $extra['return_buffer'];

        if (!($cmd_parts = self::get_job_command($job_arr, $cmd_extra))
            || empty($cmd_parts['cmd'])) {
            self::st_set_error_if_not_set(self::ERR_COMMAND, self::_t('Couldn\'t get background job command.'));

            $bg_jobs_model->hard_delete($job_arr);

            return false;
        }

        if (!empty($extra['return_command'])) {
            // !!! Be sure to hard_delete job record if you don't use it
            return [
                'cmd'      => $cmd_parts['cmd'],
                'job_data' => $job_arr,
            ];
        }

        if (!empty_db_date($extra['timed_action'])
            && parse_db_date($extra['timed_action']) > time()) {
            return true;
        }

        PHS_Logger::notice('Launching job: [#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_BACKGROUND);

        if (PHS::st_debugging_mode()) {
            PHS_Logger::debug('Command ['.$cmd_parts['cmd'].']', PHS_Logger::TYPE_BACKGROUND);
        }

        if (!empty($extra['same_thread_if_bg'])
            && PHS_Scope::current_scope() === PHS_Scope::SCOPE_BACKGROUND) {
            $original_debug_data = PHS::platform_debug_data();

            // We are in background scope... just execute the route
            $job_start_time = microtime(true);

            if (!($action_result = self::bg_run_job($job_arr))) {
                PHS_Logger::error('Error running job [#'.$job_arr['id'].'] ('.$job_arr['route'].')', PHS_Logger::TYPE_BACKGROUND);
                PHS_Logger::error('Job error: '.self::st_get_error_message(self::_t('Unknown error.')), PHS_Logger::TYPE_BACKGROUND);
            } elseif (($debug_data = PHS::platform_debug_data())) {
                PHS_Logger::notice('Job #'.$job_arr['id'].' ('.$job_arr['route'].') run with success: '.($original_debug_data['db_queries_count'] - $debug_data['db_queries_count']).' queries, '
                                  .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                                  .' running: '.number_format(($job_start_time - microtime(true)), 6, '.', '').'s', PHS_Logger::TYPE_BACKGROUND);
            }

            $action_result = PHS::validate_array($action_result, PHS_Action::default_action_result());

            if (!empty($job_arr['return_buffer'])) {
                return $action_result;
            }

            return true;
        }

        if (!empty($job_arr['return_buffer'])) {
            if (null === ($action_result = @shell_exec($cmd_parts['cmd']))
                || false === $action_result) {
                PHS_Logger::notice('Job #'.$job_arr['id'].' ('.$job_arr['route'].') error launching job or job returned empty buffer when we expected a response.', PHS_Logger::TYPE_BACKGROUND);

                $edit_arr = [];
                $edit_arr['fields'] = [];
                $edit_arr['fields']['last_error'] = 'Error launching job or job returned empty buffer when we expected a response.';
                $edit_arr['fields']['last_action'] = date($bg_jobs_model::DATETIME_DB);

                $bg_jobs_model->edit($job_arr, $edit_arr);

                self::st_set_error(self::ERR_RUN_JOB, self::_t('Error launching job or job returned empty buffer when we expected a response.'));

                return false;
            }

            return @json_decode($action_result, true) ?: [];
        }

        ob_start();
        $result = (@system($cmd_parts['cmd']) !== false);
        ob_clean();

        return $result;
    }

    public static function get_job_command(int | array | PHS_Record_data $job_data, array $extra = []) : ?array
    {
        self::st_reset_error();

        $extra['return_buffer'] = !empty($extra['return_buffer']);
        $extra['async_task'] = !isset($extra['async_task']) || !empty($extra['async_task']);

        /** @var PHS_Model_Bg_jobs $bg_jobs_model */
        if (empty($job_data)
         || !($bg_jobs_model = PHS_Model_Bg_jobs::get_instance())
         || !($job_arr = $bg_jobs_model->data_to_array($job_data))) {
            self::st_set_error_if_not_set(self::ERR_COMMAND, self::_t('Couldn\'t get background jobs details.'));

            return null;
        }

        $pub_key = microtime(true);

        if (false === ($crypted_parms = PHS_Crypt::quick_encode($job_arr['id'].'::'.md5($job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'])))) {
            self::st_set_error(self::ERR_COMMAND, self::_t('Error obtaining crypted background jobs arguments.'));

            return null;
        }

        $clean_cmd = PHP_EXEC.' '.PHS::get_background_path().' '.$crypted_parms.'::'.$pub_key;

        if (stripos(PHP_OS, 'win') === 0) {
            // launching background task under windows
            $cmd = 'start '.($extra['async_task'] ? ' /B ' : '').$clean_cmd;
        } else {
            $cmd = $clean_cmd.' 2>/dev/null <&-';
            if (!$extra['return_buffer']) {
                $cmd .= ' >&- >/dev/null';
            }
            if (!$extra['async_task']) {
                $cmd .= ' &';
            }
        }

        return [
            'cmd'     => $cmd,
            'pub_key' => $pub_key,
        ];
    }

    public static function bg_validate_input(string $input_str) : ?array
    {
        if (empty($input_str)
         || !str_contains($input_str, '::')
         || !($parts_arr = explode('::', $input_str, 2))
         || empty($parts_arr[0]) || empty($parts_arr[1])) {
            PHS_Logger::error('Invalid input', PHS_Logger::TYPE_BACKGROUND);

            return null;
        }

        $crypted_data = $parts_arr[0];
        $pub_key = $parts_arr[1];

        /** @var PHS_Model_Bg_jobs $bg_jobs_model */
        if (!($decrypted_data = PHS_Crypt::quick_decode($crypted_data))
         || !($decrypted_parts = explode('::', $decrypted_data, 2))
         || empty($decrypted_parts[0]) || empty($decrypted_parts[1])
         || !($job_id = (int)$decrypted_parts[0])
         || !($bg_jobs_model = PHS_Model_Bg_jobs::get_instance())
         || !($job_arr = $bg_jobs_model->get_details($job_id))
         || $decrypted_parts[1] !== md5($job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'])) {
            PHS_Logger::error('Input validation failed', PHS_Logger::TYPE_BACKGROUND);

            return null;
        }

        return [
            'job_data'      => $job_arr,
            'pub_key'       => $pub_key,
            'bg_jobs_model' => $bg_jobs_model,
        ];
    }

    public static function bg_run_job(int | array | PHS_Record_data $job_data) : ?array
    {
        self::st_reset_error();

        /** @var PHS_Model_Bg_jobs $bg_jobs_model */
        if (!($bg_jobs_model = PHS_Model_Bg_jobs::get_instance())) {
            self::st_set_error(self::ERR_RESOURCES, self::_t('Error loading required resources.'));

            return null;
        }

        if (empty($job_data)
            || !($job_arr = $bg_jobs_model->data_to_array($job_data))) {
            self::st_copy_or_set_error($bg_jobs_model,
                self::ERR_RUN_JOB, self::_t('Couldn\'t get background jobs details.'));

            return null;
        }

        self::current_job_data($job_arr);

        if (!($new_job_arr = $bg_jobs_model->edit($job_arr, ['fields' => ['pid' => @getmypid() ?: -1]]))) {
            self::st_copy_or_set_error($bg_jobs_model,
                self::ERR_RUN_JOB, self::_t('Couldn\'t save background jobs details in database.'));

            return null;
        }

        $job_arr = $new_job_arr;

        self::current_job_data($job_arr);

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_BACKGROUND)
            || !PHS::set_route($job_arr['route'])) {
            self::st_set_error_if_not_set(self::ERR_RUN_JOB, self::_t('Error preparing environment.'));

            $error_arr = self::st_get_error();

            $bg_jobs_model->job_error_stop($job_arr, ['last_error' => self::st_get_simple_error_message()]);

            self::st_copy_error_from_array($error_arr);

            return null;
        }

        if (!empty($job_arr['session_id'])) {
            PHS::current_user_force_session_id_for_bg((int)$job_arr['session_id']);
            PHS::current_user(true);
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

            $bg_jobs_model->job_error_stop($job_arr,
                ['last_error' => self::arr_get_simple_error_message($error_arr)]
            );

            self::st_copy_error_from_array($error_arr);

            return null;
        }

        $bg_jobs_model->hard_delete($job_arr);

        return $action_result;
    }
}
