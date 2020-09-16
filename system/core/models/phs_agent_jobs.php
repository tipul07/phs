<?php

namespace phs\system\core\models;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_params;

class PHS_Model_Agent_jobs extends PHS_Model
{
    const ERR_DB_JOB = 10000;

    const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_SUSPENDED = 3;
    protected static $STATUSES_ARR = array(
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
        self::STATUS_SUSPENDED => array( 'title' => 'Suspended' ),
    );

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.5';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return array( 'bg_agent' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'bg_agent';
    }

    public function get_settings_structure()
    {
        return array(
            'minutes_to_stall' => array(
                'display_name' => 'Minutes to stall',
                'display_hint' => 'After how many minutes should we consider agent as stalling',
                'type' => PHS_params::T_INT,
                'default' => 15,
            ),
        );
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $statuses_arr = array();
        // Translate and validate statuses...
        foreach( self::$STATUSES_ARR as $status_id => $status_arr )
        {
            $status_id = intval( $status_id );
            if( empty( $status_id ) )
                continue;

            if( empty( $status_arr['title'] ) )
                $status_arr['title'] = $this->_pt( 'Status %s', $status_id );
            else
                $status_arr['title'] = $this->_pt( $status_arr['title'] );

            $statuses_arr[$status_id] = $status_arr;
        }

        return $statuses_arr;
    }

    final public function get_statuses_as_key_val()
    {
        static $user_statuses_key_val_arr = false;

        if( $user_statuses_key_val_arr !== false )
            return $user_statuses_key_val_arr;

        $user_statuses_key_val_arr = array();
        if( ($user_statuses = $this->get_statuses()) )
        {
            foreach( $user_statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $user_statuses_key_val_arr[$key] = $val['title'];
            }
        }

        return $user_statuses_key_val_arr;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
            or empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    public function act_activate( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        if( $this->job_is_suspended( $job_arr ) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job is suspended. You must activate plugin to activate it again.' ) );
            return false;
        }

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_ACTIVE;

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function act_inactivate( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        if( $this->job_is_suspended( $job_arr ) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job is suspended. You must activate plugin to activate it again.' ) );
            return false;
        }

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_INACTIVE;

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function act_suspend( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Agent job details not found in database.' ) );
            return false;
        }

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_SUSPENDED;

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function act_delete( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         or !($flow_params = $this->fetch_default_flow_params())
         or !($table_name = $this->get_flow_table_name( $flow_params ))
         or !($job_arr = $this->data_to_array( $job_data )) )
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
         or !($job_arr = $this->data_to_array( $job_data )) )
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

        $edit_arr = array();
        $edit_arr['is_running'] = date( self::DATETIME_DB );
        $edit_arr['pid'] = $pid;
        if( !empty( $job_params_arr ) )
            $edit_arr['params'] = @json_encode( $job_params_arr );

        PHS_Logger::logf( 'Starting agent job (#'.$job_arr['id'].'), route ['.$job_arr['route'].'] with pid ['.$pid.']' );

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
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
            $params = array();

        if( empty( $params['last_error'] ) )
            $params['last_error'] = '';

        if( empty( $job_arr['is_running'] ) )
            $next_time = time();
        else
            $next_time = parse_db_date( $job_arr['is_running'] );

        // Remove one minute to be sure we'r not at the limit with few seconds (time which took to bootstrap agent job)
        // One minute doesn't affect time unit at which scripts can run as linux crontab can run at minimum every minute
        if( !empty( $job_arr['timed_seconds'] ) )
            $next_time += (int)$job_arr['timed_seconds'] - 60;

        // Remove force job parameter (if set)
        $new_params = false;
        if( !empty( $job_arr['params'] )
         && ($job_params_arr = @json_decode( $job_arr['params'], true ))
         && isset( $job_params_arr['force_job'] ) )
        {
            unset( $job_params_arr['force_job'] );
            if( !empty( $job_params_arr ) )
                $new_params = @json_encode( $job_params_arr );
            else
                $new_params = null;
        }

        $edit_arr = array();
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = $params['last_error'];
        $edit_arr['is_running'] = null;
        $edit_arr['last_action'] = date( self::DATETIME_DB );
        $edit_arr['timed_action'] = date( self::DATETIME_DB, $next_time );
        if( $new_params !== false )
            $edit_arr['params'] = $new_params;

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function refresh_job( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Job not found in database.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        $edit_arr = array();
        if( empty( $job_arr['pid'] ) )
        {
            if( !($pid = @getmypid()) )
                $pid = -1;

            $edit_arr['pid'] = $pid;
        }

        if( empty( $job_arr['is_running'] )
         or empty_db_date( $job_arr['is_running'] ) )
            $edit_arr['is_running'] = $cdate;

        $edit_arr['last_action'] = $cdate;

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function get_stalling_minutes()
    {
        static $stalling_minutes = false;

        if( $stalling_minutes !== false )
            return $stalling_minutes;

        if( !($settings_arr = $this->get_db_settings())
         or empty( $settings_arr['minutes_to_stall'] ) )
            $stalling_minutes = 0;
        else
            $stalling_minutes = intval( $settings_arr['minutes_to_stall'] );

        return $stalling_minutes;
    }

    public function job_is_stalling( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get agent jobs details.' ) );
            return null;
        }

        if( !($settings_arr = $this->get_db_settings()) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get agent jobs model settings.' ) );
            return null;
        }

        if( !$this->job_is_running( $job_arr )
         or (!empty( $settings_arr['minutes_to_stall'] ) and !empty( $job_arr['last_action'] )
                and floor( seconds_passed( $job_arr['last_action'] ) / 60 ) > $settings_arr['minutes_to_stall']) )
            return false;

        return true;
    }

    public function job_runs_async( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data ))
         or empty( $job_arr['run_async'] ) )
            return false;

        return true;
    }

    public function job_is_running( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data ))
         or empty_db_date( $job_arr['is_running'] ) )
            return false;

        return true;
    }

    public function job_is_active( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data ))
         or $job_arr['status'] != self::STATUS_ACTIVE )
            return false;

        return true;
    }

    public function job_is_inactive( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data ))
         or $job_arr['status'] != self::STATUS_INACTIVE )
            return false;

        return true;
    }

    public function job_is_suspended( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data ))
         or $job_arr['status'] != self::STATUS_SUSPENDED )
            return false;

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_bg_agent( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        self::st_reset_error();

        if( empty( $params['fields']['route'] )
         or !PHS::route_exists( $params['fields']['route'], array( 'action_accepts_scopes' => PHS_Scope::SCOPE_AGENT ) ) )
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

        if( $this->get_details_fields( array( 'handler' => $params['fields']['handler'] ) ) )
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
         or !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_ACTIVE;

        if( empty( $params['fields']['timed_seconds'] ) )
            $params['fields']['timed_seconds'] = 0;
        else
            $params['fields']['timed_seconds'] = intval( $params['fields']['timed_seconds'] );

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        $params['fields']['timed_action'] = date( self::DATETIME_DB, time() + $params['fields']['timed_seconds'] );

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_edit_prepare_params_bg_agent( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        $cdate = date( self::DATETIME_DB );

        if( !empty( $params['fields']['handler'] ) )
        {
            $check_arr = array();
            $check_arr['handler'] = $params['fields']['handler'];
            $check_arr['id'] = array( 'check' => '!=', 'value' => $existing_data['id'] );

            if( $this->get_details_fields( $check_arr ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Handler already defined in database.' ) );
                return false;
            }
        }

        if( !empty( $params['fields']['status'] )
        and !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Agent job status is not defined.' ) );
            return false;
        }

        if( isset( $params['fields']['run_async'] ) )
            $params['fields']['run_async'] = (!empty( $params['fields']['run_async'] )?1:0);

        if( isset( $params['fields']['is_running'] ) )
        {
            if( empty( $params['fields']['is_running'] )
             or empty_db_date( $params['fields']['is_running'] ) )
                $params['fields']['is_running'] = null;
            else
                $params['fields']['is_running'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['is_running'] ) );
        }

        // Update last_action field on any edit's we do...
        if( empty( $params['fields']['last_action'] )
         or empty_db_date( $params['fields']['last_action'] ) )
            $params['fields']['last_action'] = $cdate;

        return $params;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'bg_agent':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'title' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Descriptive title',
                    ),
                    'handler' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'comment' => 'String which will help identify task',
                    ),
                    'plugin' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'comment' => 'Tells if job was installed by a plugin',
                    ),
                    'pid' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'route' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'params' => array(
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ),
                    'last_error' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'default' => null,
                    ),
                    'is_running' => array(
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Is route currently running',
                    ),
                    'run_async' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'default' => 1,
                        'comment' => 'Run this job asynchronous',
                    ),
                    'last_action' => array(
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Last time action said is alive',
                    ),
                    'timed_seconds' => array(
                        'type' => self::FTYPE_INT,
                        'comment' => 'Once how many seconds should route run',
                    ),
                    'timed_action' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                        'comment' => 'Next time action should run',
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'default' => self::STATUS_ACTIVE,
                        'comment' => 'Status of current job',
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
