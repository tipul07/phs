<?php

namespace phs\system\core\models;

use \phs\PHS;
use \phs\PHS_Scope;
use phs\libraries\PHS_Utils;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Params;
use phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Agent_jobs extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    const ERR_DB_JOB = 10000;

    const STALLING_PID = 1, STALLING_TIME = 2, STALLING_BOTH = 3;
    protected static $STALLING_ARR = [
        self::STALLING_PID => ['title' => 'Check process is alive'],
        self::STALLING_TIME => ['title' => 'Check only time passed'],
        self::STALLING_BOTH => ['title' => 'Use both policies'],
    ];

    const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_SUSPENDED = 3;
    protected static $STATUSES_ARR = [
        self::STATUS_ACTIVE => ['title' => 'Active'],
        self::STATUS_INACTIVE => ['title' => 'Inactive'],
        self::STATUS_SUSPENDED => ['title' => 'Suspended'],
    ];

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.1.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return [ 'bg_agent' ];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'bg_agent';
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_stalling_policies( $lang = false )
    {
        static $policies_arr = [];

        if( empty( self::$STALLING_ARR ) )
            return [];

        if( $lang === false
         && !empty( $policies_arr ) )
            return $policies_arr;

        $result_arr = $this->translate_array_keys( self::$STALLING_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $policies_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_stalling_policies_as_key_val( $lang = false )
    {
        static $policies_key_val_arr = false;

        if( $lang === false
         && $policies_key_val_arr !== false )
            return $policies_key_val_arr;

        $key_val_arr = [];
        if( ($policies = $this->get_stalling_policies( $lang )) )
        {
            foreach( $policies as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $policies_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $policy
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_stalling_policy( $policy, $lang = false )
    {
        $all_policies = $this->get_stalling_policies( $lang );
        if( empty( $policy )
         || !isset( $all_policies[$policy] ) )
            return false;

        return $all_policies[$policy];
    }

    public function get_settings_structure()
    {
        if( !($policies_arr = $this->get_stalling_policies_as_key_val()) )
            $policies_arr = [];

        return [
            'minutes_to_stall' => [
                'display_name' => 'Minutes to stall (generic)',
                'display_hint' => 'After how many minutes should we consider agent jobs which don\'t have specific stalling time as stalling',
                'type' => PHS_Params::T_INT,
                'default' => 60,
            ],
            'stalling_policy' => [
                'display_name' => 'Stalling policy',
                'display_hint' => 'When a job is stalling, how system should consider job as dead? If one condition of policy is true, we will consider job as dead and a new agent job will start.',
                'type' => PHS_Params::T_INT,
                'default' => self::STALLING_PID,
                'values_arr' => $policies_arr,
            ],
        ];
    }

    public function act_activate( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        if( $this->job_is_suspended( $job_arr ) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job is suspended. You must activate plugin to activate it again.' ) );
            return false;
        }

        return $this->edit( $job_arr, ['fields' => [ 'status' => self::STATUS_ACTIVE ] ] );
    }

    public function act_inactivate( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        if( $this->job_is_suspended( $job_arr ) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job is suspended. You must activate plugin to activate it again.' ) );
            return false;
        }

        return $this->edit( $job_arr, ['fields' => [ 'status' => self::STATUS_INACTIVE ] ] );
    }

    public function act_suspend( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        return $this->edit( $job_arr, [ 'fields' => [ 'status' => self::STATUS_SUSPENDED ] ] );
    }

    public function act_delete( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($flow_params = $this->fetch_default_flow_params())
         || !($table_name = $this->get_flow_table_name( $flow_params ))
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        if( !db_query( 'DELETE FROM `'.$table_name.'` WHERE id = \''.$job_arr['id'].'\'', $flow_params['db_connection'] ) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Error deleting agent job from database.' ) );
            return false;
        }

        return true;
    }

    /**
     * @param string|null $job_params_str
     *
     * @return null|string
     */
    private function _reset_job_parameters_on_stop( $job_params_str )
    {
        if( empty( $job_params_str )
         || !($job_params_arr = @json_decode( $job_params_str, true )) )
            return null;

        // Remove force job parameter (if set)
        $new_params = null;
        if( isset( $job_params_arr['force_job'] ) )
        {
            unset( $job_params_arr['force_job'] );
            if( !empty( $job_params_arr ) )
                $new_params = @json_encode( $job_params_arr );
        }

        return $new_params;
    }

    /**
     * @param int|array $job_data
     * @param false|array $params
     *
     * @return array|bool
     */
    public function start_job( $job_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['force_job'] ) )
            $params['force_job'] = false;
        else
            $params['force_job'] = true;

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        if( empty( $job_arr['params'] )
         || !($job_params_arr = @json_decode( $job_arr['params'], true )) )
            $job_params_arr = [];

        if( !empty( $params['force_job'] ) )
            $job_params_arr['force_job'] = true;

        if( !($pid = @getmypid()) )
            $pid = -1;

        $edit_arr = [];
        $edit_arr['is_running'] = date( self::DATETIME_DB );
        $edit_arr['pid'] = $pid;
        if( !empty( $job_params_arr ) )
            $edit_arr['params'] = @json_encode( $job_params_arr );

        PHS_Logger::logf( 'Starting agent job (#'.$job_arr['id'].'), route ['.$job_arr['route'].'] with pid ['.$pid.']' );

        return $this->edit( $job_arr, [ 'fields' => $edit_arr ] );
    }

    /**
     * @param int|array $job_data
     * @param false|array $params
     *
     * @return array|bool
     */
    public function stop_job( $job_data, $params = false )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Job not found in database.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['last_error'] ) )
            $params['last_error'] = '';

        if( empty( $job_arr['is_running'] ) )
            $next_time = time();
        else
            $next_time = parse_db_date( $job_arr['is_running'] );

        // Remove one minute to be sure we're not at the limit with few seconds (time which took to bootstrap agent job)
        // One minute doesn't affect time unit at which scripts can run as linux crontab can run at minimum every minute
        if( !empty( $job_arr['timed_seconds'] ) )
            $next_time += (int)$job_arr['timed_seconds'] - 60;

        if( !($new_params = $this->_reset_job_parameters_on_stop( $job_arr['params'] )) )
            $new_params = null;

        $edit_arr = [];
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = $params['last_error'];
        $edit_arr['is_running'] = null;
        $edit_arr['last_action'] = date( self::DATETIME_DB );
        $edit_arr['timed_action'] = date( self::DATETIME_DB, $next_time );
        if( $new_params !== false )
            $edit_arr['params'] = $new_params;

        return $this->edit( $job_arr, [ 'fields' => $edit_arr ] );
    }

    public function refresh_job( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Job not found in database.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        $edit_arr = [];
        if( empty( $job_arr['pid'] ) )
        {
            if( !($pid = @getmypid()) )
                $pid = -1;

            $edit_arr['pid'] = $pid;
        }

        if( empty( $job_arr['is_running'] )
         || empty_db_date( $job_arr['is_running'] ) )
            $edit_arr['is_running'] = $cdate;

        $edit_arr['last_action'] = $cdate;

        return $this->edit( $job_arr, [ 'fields' => $edit_arr ] );
    }

    public function get_stalling_minutes()
    {
        static $stalling_minutes = false;

        if( $stalling_minutes !== false )
            return $stalling_minutes;

        if( !($settings_arr = $this->get_db_settings())
         || empty( $settings_arr['minutes_to_stall'] ) )
            $stalling_minutes = 0;
        else
            $stalling_minutes = (int)$settings_arr['minutes_to_stall'];

        return $stalling_minutes;
    }

    public function get_stalling_policy()
    {
        static $stalling_policy = false;

        if( $stalling_policy !== false )
            return $stalling_policy;

        if( !($settings_arr = $this->get_db_settings())
         || empty( $settings_arr['stalling_policy'] ) )
            $stalling_policy = self::STALLING_PID;
        else
            $stalling_policy = (int)$settings_arr['stalling_policy'];

        return $stalling_policy;
    }

    /**
     * @param int|array $job_data
     *
     * @return null|bool
     */
    public function is_job_dead_as_per_stalling_policy( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get agent jobs details.' ) );
            return null;
        }

        $stalling_policy = $this->get_stalling_policy();

        return
        (
            $this->job_is_running( $job_arr )
            &&
            (
                (($stalling_policy === self::STALLING_PID || $stalling_policy === self::STALLING_BOTH)
                 && (empty( $job_arr['pid'] )
                     || !($process_details = PHS_Utils::get_process_details( $job_arr['pid'] ))
                     || empty( $process_details['is_running'] )
                ) )

                ||

                (($stalling_policy === self::STALLING_TIME || $stalling_policy === self::STALLING_BOTH)
                 && $this->job_is_stalling( $job_arr )
                )
            )
        );
    }

    public function get_job_stalling_minutes( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get agent jobs details.' ) );
            return null;
        }

        if( !empty( $job_arr['stalling_minutes'] ) )
            return (int)$job_arr['stalling_minutes'];

        return $this->get_stalling_minutes();
    }

    public function get_job_seconds_since_last_action( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get agent jobs details.' ) );
            return null;
        }

        if( empty( $job_arr['last_action'] )
         || empty_db_date( $job_arr['last_action'] ) )
            return 0;

        return seconds_passed( $job_arr['last_action'] );
    }

    public function job_is_stalling( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get agent jobs details.' ) );
            return null;
        }

        if( !$this->job_is_running( $job_arr )
         || !($minutes_to_stall = $this->get_job_stalling_minutes( $job_arr ))
         || floor( $this->get_job_seconds_since_last_action( $job_arr ) / 60 ) < $minutes_to_stall )
            return false;

        return true;
    }

    public function job_runs_async( $job_data )
    {
        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data ))
         || empty( $job_arr['run_async'] ) )
            return false;

        return true;
    }

    public function job_is_running( $job_data )
    {
        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data ))
         || empty( $job_arr['pid'] )
         || empty_db_date( $job_arr['is_running'] ) )
            return false;

        return true;
    }

    public function job_is_active( $job_data )
    {
        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data ))
         || (int)$job_arr['status'] !== self::STATUS_ACTIVE )
            return false;

        return true;
    }

    public function job_is_inactive( $job_data )
    {
        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data ))
         || (int)$job_arr['status'] !== self::STATUS_INACTIVE )
            return false;

        return true;
    }

    public function job_is_suspended( $job_data )
    {
        if( empty( $job_data )
         || !($job_arr = $this->data_to_array( $job_data ))
         || (int)$job_arr['status'] !== self::STATUS_SUSPENDED )
            return false;

        return true;
    }

    protected function get_insert_prepare_params_bg_agent( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        self::st_reset_error();

        if( empty( $params['fields']['route'] )
         || !PHS::route_exists( $params['fields']['route'], [ 'action_accepts_scopes' => PHS_Scope::SCOPE_AGENT ] ) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_INSERT );
            else
                $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a route.' ) );
            return false;
        }

        if( empty( $params['fields']['handler'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a handler for this task.' ) );
            return false;
        }

        if( $this->get_details_fields( [ 'handler' => $params['fields']['handler'] ] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Handler already defined in database.' ) );
            return false;
        }

        if( empty( $params['fields']['title'] ) )
            $params['fields']['title'] = '';

        if( empty( $params['fields']['plugin'] ) )
            $params['fields']['plugin'] = '';

        if( !isset( $params['fields']['run_async'] ) )
            $params['fields']['run_async'] = 1;
        else
            $params['fields']['run_async'] = (!empty( $params['fields']['run_async'] )?1:0);

        if( empty( $params['fields']['status'] )
         || !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_ACTIVE;

        if( empty( $params['fields']['timed_seconds'] ) )
            $params['fields']['timed_seconds'] = 0;
        else
            $params['fields']['timed_seconds'] = (int) $params['fields']['timed_seconds'];

        if( empty( $params['fields']['stalling_minutes'] ) )
            $params['fields']['stalling_minutes'] = 0;
        else
            $params['fields']['stalling_minutes'] = (int) $params['fields']['stalling_minutes'];

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        $params['fields']['timed_action'] = date( self::DATETIME_DB, time() + $params['fields']['timed_seconds'] );

        return $params;
    }

    protected function get_edit_prepare_params_bg_agent( $existing_data, $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        $cdate = date( self::DATETIME_DB );

        if( !empty( $params['fields']['handler'] ) )
        {
            $check_arr = [];
            $check_arr['handler'] = $params['fields']['handler'];
            $check_arr['id'] = [ 'check' => '!=', 'value' => $existing_data['id'] ];

            if( $this->get_details_fields( $check_arr ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Handler already defined in database.' ) );
                return false;
            }
        }

        if( !empty( $params['fields']['status'] )
         && !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Agent job status is not defined.' ) );
            return false;
        }

        if( isset( $params['fields']['run_async'] ) )
            $params['fields']['run_async'] = (!empty( $params['fields']['run_async'] )?1:0);

        if( isset( $params['fields']['stalling_minutes'] ) )
            $params['fields']['stalling_minutes'] = (!empty( $params['fields']['stalling_minutes'] )?(int)$params['fields']['stalling_minutes']:0);

        if( isset( $params['fields']['is_running'] ) )
        {
            if( empty( $params['fields']['is_running'] )
             || empty_db_date( $params['fields']['is_running'] ) )
                $params['fields']['is_running'] = null;
            else
                $params['fields']['is_running'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['is_running'] ) );
        }

        // Update last_action field on any edit we do...
        if( empty( $params['fields']['last_action'] )
         || empty_db_date( $params['fields']['last_action'] ) )
            $params['fields']['last_action'] = $cdate;

        return $params;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) || !is_array( $params )
         || empty( $params['table_name'] ) )
            return false;

        $return_arr = [];
        switch( $params['table_name'] )
        {
            case 'bg_agent':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'title' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Descriptive title',
                    ],
                    'handler' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'comment' => 'String which will help identify task',
                    ],
                    'plugin' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'comment' => 'Tells if job was installed by a plugin',
                    ],
                    'pid' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'route' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                    ],
                    'params' => [
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ],
                    'last_error' => [
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'default' => null,
                    ],
                    'is_running' => [
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Is route currently running',
                    ],
                    'run_async' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'default' => 1,
                        'comment' => 'Run this job asynchronous',
                    ],
                    'last_action' => [
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Last time action said is alive',
                    ],
                    'timed_seconds' => [
                        'type' => self::FTYPE_INT,
                        'comment' => 'Once how many seconds should route run',
                    ],
                    'stalling_minutes' => [
                        'type' => self::FTYPE_INT,
                        'comment' => 'Minutes after we should consider job stalling',
                    ],
                    'timed_action' => [
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                        'comment' => 'Next time action should run',
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
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
}
