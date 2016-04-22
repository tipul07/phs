<?php

namespace phs\system\core\models;

use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_params;

class PHS_Model_Bg_jobs extends PHS_Model
{
    const ERR_DB_JOB = 10000;

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return array( 'bg_jobs' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'bg_jobs';
    }

    /**
     * Performs any necessary actions when updating model from $old_version to $new_version
     *
     * @param string $old_version Old version of model
     * @param string $new_version New version of model
     *
     * @return bool true on success, false on failure
     */
    protected function update( $old_version, $new_version )
    {
        return true;
    }

    public function get_settings_structure()
    {
        return array(
            'minutes_to_stall' => array(
                'display_name' => 'Minutes to stall',
                'display_hint' => 'After how many minutes should we consider a job as stalling',
                'type' => PHS_params::T_INT,
                'default' => 15,
            ),
        );
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

        $edit_arr = array();
        $edit_arr['last_action'] = date( self::DATETIME_DB );

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function job_error_stop( $job_data, $params )
    {
        $this->reset_error();

        if( empty( $job_data )
         or empty( $params ) or !is_array( $params )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Job not found in database.' ) );
            return false;
        }

        if( empty( $params['last_error'] ) )
            $params['last_error'] = self::_t( 'Unknown error.' );

        $edit_arr = array();
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = $params['last_error'];
        $edit_arr['last_action'] = date( self::DATETIME_DB );

        return $this->edit( $job_arr, array( 'fields' => $edit_arr ) );
    }

    public function job_is_stalling( $job_data )
    {
        $this->reset_error();

        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get background jobs details.' ) );
            return null;
        }

        if( !($settings_arr = $this->get_db_settings()) )
        {
            $this->set_error( self::ERR_DB_JOB, self::_t( 'Couldn\'t get background jobs model settings.' ) );
            return null;
        }

        if( empty( $job_arr['pid'] )
         or (!empty( $settings_arr['minutes_to_stall'] ) and floor( parse_db_date( $job_arr['last_action'] ) / 60 ) < $settings_arr['minutes_to_stall']) )
            return false;

        return true;
    }

    public function job_is_running( $job_data )
    {
        if( empty( $job_data )
         or !($job_arr = $this->data_to_array( $job_data ))
         or empty( $job_arr['pid'] ) )
            return false;

        return true;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_insert_prepare_params( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['route'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a route.' ) );
            return false;
        }

        if( empty( $params['fields']['timed_action'] )
         or empty_db_date( $params['fields']['timed_action'] ) )
            $params['fields']['timed_action'] = self::DATETIME_EMPTY;

        $params['fields']['last_action'] = date( self::DATETIME_DB );

        if( empty( $params['fields']['cdate'] )
         or empty_db_date( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = $params['fields']['last_action'];

        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        // Update last_action field on any edit's we do...
        if( empty( $params['fields']['last_action'] )
         or empty_db_date( $params['fields']['last_action'] ) )
            $params['fields']['last_action'] = date( self::DATETIME_DB );

        return $params;
    }

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return array Returns an array with table fields
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
            case 'bg_jobs':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
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
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'last_action' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'timed_action' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
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
