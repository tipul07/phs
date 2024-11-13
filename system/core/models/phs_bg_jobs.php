<?php
namespace phs\system\core\models;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;

class PHS_Model_Bg_jobs extends PHS_Model
{
    public const ERR_DB_JOB = 10000;

    public function get_model_version() : string
    {
        return '1.0.1';
    }

    public function get_table_names() : array
    {
        return ['bg_jobs'];
    }

    public function get_main_table_name() : string
    {
        return 'bg_jobs';
    }

    public function get_settings_structure() : array
    {
        return [
            'minutes_to_stall' => [
                'display_name' => 'Minutes to stall',
                'display_hint' => 'After how many minutes should we consider a job as stalling',
                'type'         => PHS_Params::T_INT,
                'default'      => 15,
            ],
        ];
    }

    public function refresh_job(int | array $job_data) : ?array
    {
        $this->reset_error();

        if (empty($job_data)
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Job not found in database.'));

            return null;
        }

        $edit_arr = [];
        if (empty($job_arr['pid'])) {
            if (!($pid = @getmypid())) {
                $pid = -1;
            }

            $edit_arr['pid'] = $pid;
        }

        $edit_arr['last_action'] = date(self::DATETIME_DB);

        if (!($new_job_arr = $this->edit($job_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Error updating background job.'));

            return null;
        }

        return $new_job_arr;
    }

    public function job_error_stop(int | array $job_data, $params) : ?array
    {
        $this->reset_error();

        if (empty($job_data)
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Job not found in database.'));

            return null;
        }

        $params['last_error'] ??= self::_t('Unknown error.');

        $edit_arr = [];
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = $params['last_error'];
        $edit_arr['last_action'] = date(self::DATETIME_DB);

        if (!($new_job_arr = $this->edit($job_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_DB_JOB, self::_t('Job not found in database.'));

            return null;
        }

        return $new_job_arr;
    }

    public function get_stalling_minutes() : int
    {
        static $stalling_minutes = null;

        if ($stalling_minutes !== null) {
            return $stalling_minutes;
        }

        $settings_arr = $this->get_db_settings();

        $stalling_minutes = (int)($settings_arr['minutes_to_stall'] ?? 0);

        return $stalling_minutes;
    }

    public function get_job_seconds_since_last_action(int | array $job_data) : ?int
    {
        $this->reset_error();

        if (empty($job_data)
            || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t get agent jobs details.'));

            return null;
        }

        return !empty($job_arr['last_action']) ? seconds_passed($job_arr['last_action']) : 0;
    }

    public function job_is_stalling(int | array $job_data) : ?bool
    {
        $this->reset_error();

        if (empty($job_data)
         || !($job_arr = $this->data_to_array($job_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t get background jobs details.'));

            return null;
        }

        return $this->job_is_running($job_arr)
               && ($minutes_to_stall = $this->get_stalling_minutes())
               && floor($this->get_job_seconds_since_last_action($job_arr) / 60) >= $minutes_to_stall;
    }

    public function job_is_running(int | array $job_data) : bool
    {
        return !empty($job_data)
               && ($job_arr = $this->data_to_array($job_data))
               && !empty($job_arr['pid']);
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
            case 'bg_jobs':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
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
                    'return_buffer' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 0,
                        'comment' => 'Should job return something to caller',
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
                    'last_action' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'timed_action' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_bg_jobs(array $params) : ?array
    {
        if (empty($params['fields']['route'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a route.'));

            return null;
        }

        if (empty($params['fields']['return_buffer'])) {
            $params['fields']['return_buffer'] = 0;
        } else {
            $params['fields']['return_buffer'] = 1;
        }

        if (empty($params['fields']['timed_action'])
         || empty_db_date($params['fields']['timed_action'])) {
            $params['fields']['timed_action'] = null;
        }

        $params['fields']['last_action'] = date(self::DATETIME_DB);

        if (empty($params['fields']['cdate'])
         || empty_db_date($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $params['fields']['last_action'];
        }

        return $params;
    }

    protected function get_edit_prepare_params_bg_jobs(array $existing_data, array $params) : ?array
    {
        // Update last_action field on any edit's we do...
        if (empty($params['fields']['last_action'])) {
            $params['fields']['last_action'] = date(self::DATETIME_DB);
        }

        return $params;
    }
}
