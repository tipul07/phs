<?php

namespace phs\plugins\backup\models;

use \phs\PHS;
use \phs\PHS_Crypt;
use \phs\PHS_Db;
use \phs\PHS_Bg_jobs;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Db_class;
use \phs\libraries\PHS_Utils;
use \phs\libraries\PHS_Line_params;

class PHS_Model_Rules extends PHS_Model
{
    const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_DELETED = 3, STATUS_SUSPENDED = 4;
    protected static $STATUSES_ARR = [
        self::STATUS_ACTIVE => [ 'title' => 'Active' ],
        self::STATUS_INACTIVE => [ 'title' => 'Inactive' ],
        self::STATUS_DELETED => [ 'title' => 'Deleted' ],
        self::STATUS_SUSPENDED => [ 'title' => 'Suspended' ],
    ];

    const BACKUP_TARGET_DATABASE = 1, BACKUP_TARGET_UPLOADS = 2;
    protected static $BACKUP_TARGETS_ARR = [
        self::BACKUP_TARGET_DATABASE => [ 'title' => 'Database' ],
        self::BACKUP_TARGET_UPLOADS => [ 'title' => 'Uploaded files' ],
    ];

    const COPY_FTP = 1;
    protected static $COPY_RESULTS_ARR = [
        self::COPY_FTP => [ 'title' => 'Copy to FTP' ],
    ];

    const DAY_ALL = 0, DAY_MONDAY = 1, DAY_TUESDAY = 2, DAY_WEDNESDAY = 3, DAY_THURSDAY = 4, DAY_FRIDAY = 5, DAY_SATURDAY = 6, DAY_SUNDAY = 7;

    // const BACKUP_TARGET_ALL = ((1 << self::BACKUP_TARGET_DATABASE)|(1 << self::BACKUP_TARGET_UPLOADS));

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.6';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return [ 'backup_rules', 'backup_rules_days' ];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'backup_rules';
    }

    /**
     * @return int
     */
    public function get_all_targets()
    {
        return ((1 << self::BACKUP_TARGET_DATABASE)|(1 << self::BACKUP_TARGET_UPLOADS));
    }

    final public function get_statuses( $lang = false )
    {
        static $statuses_arr = array();

        if( $lang === false
        and !empty( $statuses_arr ) )
            return $statuses_arr;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Inactive' );
        $this->_pt( 'Active' );
        $this->_pt( 'Deleted' );
        $this->_pt( 'Suspended' );

        $result_arr = $this->translate_array_keys( self::$STATUSES_ARR, array( 'title' ), $lang );

        if( $lang === false )
            $statuses_arr = $result_arr;

        return $result_arr;
    }

    final public function get_statuses_as_key_val( $lang = false )
    {
        static $statuses_key_val_arr = false;

        if( $lang === false
        and $statuses_key_val_arr !== false )
            return $statuses_key_val_arr;

        $key_val_arr = array();
        if( ($statuses = $this->get_statuses( $lang )) )
        {
            foreach( $statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $statuses_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function valid_status( $status, $lang = false )
    {
        $all_statuses = $this->get_statuses( $lang );
        if( empty( $status )
         || !isset( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    final public function get_copy_results( $lang = false )
    {
        static $copy_results_arr = [];

        if( $lang === false
         && !empty( $copy_results_arr ) )
            return $copy_results_arr;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Copy to FTP' );

        $result_arr = $this->translate_array_keys( self::$COPY_RESULTS_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $copy_results_arr = $result_arr;

        return $result_arr;
    }

    final public function get_copy_results_as_key_val( $lang = false )
    {
        static $copy_results_key_val_arr = false;

        if( $lang === false
         && $copy_results_key_val_arr !== false )
            return $copy_results_key_val_arr;

        $key_val_arr = [];
        if( ($copy_results = $this->get_copy_results( $lang )) )
        {
            foreach( $copy_results as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $copy_results_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function valid_copy_results( $copy_result, $lang = false )
    {
        $all_copy_results = $this->get_copy_results( $lang );
        if( empty( $copy_result )
         || !isset( $all_copy_results[$copy_result] ) )
            return false;

        return $all_copy_results[$copy_result];
    }

    final public function get_targets( $lang = false )
    {
        static $targets_arr = [];

        if( $lang === false
         && !empty( $targets_arr ) )
            return $targets_arr;

        $result_arr = $this->translate_array_keys( self::$BACKUP_TARGETS_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $targets_arr = $result_arr;

        return $result_arr;
    }

    final public function get_targets_as_key_val( $lang = false )
    {
        static $targets_key_val_arr = false;

        if( $lang === false
         && $targets_key_val_arr !== false )
            return $targets_key_val_arr;

        $key_val_arr = [];
        if( ($targets = $this->get_targets( $lang )) )
        {
            foreach( $targets as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $targets_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function get_rule_days( $lang = false )
    {
        static $days_arr = false;

        if( $lang === false
        and !empty( $days_arr ) )
            return $days_arr;

        $return_arr = [
            self::DAY_ALL => $this->_pt( 'Each day', $lang ),
            self::DAY_MONDAY => $this->_pt( 'Monday', $lang ),
            self::DAY_TUESDAY => $this->_pt( 'Tuesday', $lang ),
            self::DAY_WEDNESDAY => $this->_pt( 'Wednesday', $lang ),
            self::DAY_THURSDAY => $this->_pt( 'Thursday', $lang ),
            self::DAY_FRIDAY => $this->_pt( 'Friday', $lang ),
            self::DAY_SATURDAY => $this->_pt( 'Saturday', $lang ),
            self::DAY_SUNDAY => $this->_pt( 'Sunday', $lang ),
        ];

        if( $lang === false )
            $days_arr = $return_arr;

        return $return_arr;
    }

    public function valid_target( $target, $lang = false )
    {
        $all_targets = $this->get_targets( $lang );
        if( empty( $target )
         || !isset( $all_targets[$target] ) )
            return false;

        return $all_targets[$target];
    }

    public function is_active( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_ACTIVE )
            return false;

        return $record_arr;
    }

    public function is_inactive( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_INACTIVE )
            return false;

        return $record_arr;
    }

    public function is_deleted( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_DELETED )
            return false;

        return $record_arr;
    }

    public function is_suspended( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_SUSPENDED )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return array|false
     */
    public function act_activate( $record_data, $params = false )
    {
        $this->reset_error();

        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule details not found in database.' ) );
            return false;
        }

        if( !$backup_plugin->plugin_active()
         && $this->is_suspended( $record_arr ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'You will have to activate backup plugin before activating backup rule.' ) );
            return false;
        }

        if( $this->is_active( $record_arr ) )
            return $record_arr;

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return array|false
     */
    public function act_inactivate( $record_data, $params = false )
    {
        $this->reset_error();

        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule details not found in database.' ) );
            return false;
        }

        if( !$backup_plugin->plugin_active()
         && $this->is_suspended( $record_arr ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'You will have to activate backup plugin before inactivating backup rule.' ) );
            return false;
        }

        if( $this->is_inactive( $record_arr ) )
            return $record_arr;

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_INACTIVE;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return array|false
     */
    public function act_delete( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'Backup rule details not found in database.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params_arr = [];
        $edit_params_arr['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params_arr );
    }

    public function act_suspend_all_rules()
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] ))
         || !($table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain all required resources.' ) );
            return false;
        }

        if( !db_query( 'UPDATE `'.$table_name.'` SET status = \''.self::STATUS_SUSPENDED.'\' WHERE status = \''.self::STATUS_ACTIVE.'\'', $flow_params['db_connection'] ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error suspending all active backup rules.' ) );
            return false;
        }

        return true;
    }

    public function act_unsuspend_all_rules()
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] ))
         || !($table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain all required resources.' ) );
            return false;
        }

        if( !db_query( 'UPDATE `'.$table_name.'` SET status = \''.self::STATUS_ACTIVE.'\' WHERE status = \''.self::STATUS_SUSPENDED.'\'', $flow_params['db_connection'] ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error unsuspending all active backup rules.' ) );
            return false;
        }

        return true;
    }

    /**
     * @param int|array $record_data
     * @param int|array $account_data
     *
     * @return array|false
     */
    public function can_user_edit( $record_data, $account_data )
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\backup\PHS_Plugin_Backup $plugin_obj */
        if( empty( $record_data ) || empty( $account_data )
         || !($rule_arr = $this->data_to_array( $record_data ))
         || $this->is_deleted( $rule_arr )
         || !($plugin_obj = PHS::load_plugin( 'backup' ))
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         || !($account_arr = $accounts_model->data_to_array( $account_data ))
         || !PHS_Roles::user_has_role_units( $account_arr, $plugin_obj::ROLEU_MANAGE_RULES ) )
            return false;

        $return_arr = [];
        $return_arr['rule_data'] = $rule_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    /**
     * @param int|array $rule_data
     * @param false|array $params
     *
     * @return array|false
     */
    public function get_location_for_rule( $rule_data, $params = false )
    {
        $this->reset_error();

        if( empty( $rule_data )
         || !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] ))
         || !($rule_arr = $this->data_to_array( $rule_data, $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule not found in database.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( !($result_details = $backup_plugin->get_location_for_path( $rule_arr['location'], $params )) )
        {
            if( $backup_plugin->has_error() )
                $this->copy_error( $backup_plugin );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain rule location path details.' ) );
            return false;
        }

        return $result_details;
    }

    /**
     * @param int|array $rule_data
     * @param false|array $params
     *
     * @return array|false
     */
    public function get_location_stats_for_rule( $rule_data, $params = false )
    {
        $this->reset_error();

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( empty( $rule_data )
         || !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] ))
         || !($rule_arr = $this->data_to_array( $rule_data, $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule not found in database.' ) );
            return false;
        }

        if( !($location_arr = $this->get_location_for_rule( $rule_arr, $params ))
         || empty( $location_arr['full_path'] ) )
            return false;

        return $backup_plugin->get_directory_stats( $location_arr['full_path'] );
    }

    /**
     * @param int|array $rule_data
     *
     * @return array|false
     */
    protected function get_backup_directory( $rule_data )
    {
        $this->reset_error();

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( empty( $rule_data )
         || !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] ))
         || !($rule_arr = $this->data_to_array( $rule_data, $flow_params ))
         || $this->is_deleted( $rule_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule not found in database.' ) );
            return false;
        }

        $location_params = [];
        $location_params['create_location_if_not_found'] = true;

        if( !($location_details = $this->get_location_for_rule( $rule_arr, $location_params ))
         || empty( $location_details['full_path'] )
         || empty( $location_details['location_exists'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t create backup location for provided rule.' ) );

            PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): Failed creating backup location directory.', $backup_plugin::LOG_CHANNEL );
            return false;
        }

        if( empty( $location_details['location_is_dir'] ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Backup location is not a directory.' ) );

            PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): Destination is not a directory.', $backup_plugin::LOG_CHANNEL );

            return false;
        }

        if( !@is_writable( $location_details['full_path'] ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Backup location directory is not writable.' ) );

            PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): Destination directory is not writable ('.$location_details['full_path'].').', $backup_plugin::LOG_CHANNEL );

            return false;
        }

        $backup_path = rtrim( $location_details['full_path'], '/\\' );
        $rule_path = $backup_path.'/'.$rule_arr['id'];
        if( !@file_exists( $rule_path )
         || !@is_dir( $rule_path ) )
        {
            if( !@mkdir( $rule_path, 0750 ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error creating rule directory in backup location directory.' ) );

                PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): Error creating rule directory in destination directory ('.$rule_path.').', $backup_plugin::LOG_CHANNEL );

                return false;
            }
        }

        return [
            'rule_data' => $rule_arr,
            'backup_path' => $backup_path,
            'rule_path' => $rule_path,
        ];
    }

    /**
     * @param string $output_dir
     * @param bool|array $params
     *
     * @return array|false
     */
    public function get_database_backup_script_commands( $output_dir, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        // Zip database dump
        if( !isset( $params['zip_dump'] ) )
            $params['zip_dump'] = true;
        else
            $params['zip_dump'] = (!empty( $params['zip_dump'] ));

        // Option to force mysqldump or zip location. If not provided, will use plugin settings or mysqldump (if in executables path)
        if( empty( $params['zip_bin'] ) )
            $params['zip_bin'] = '';
        if( empty( $params['mysqldump_bin'] ) )
            $params['mysqldump_bin'] = '';

        if( empty( $output_dir )
         || !@is_dir( $output_dir )
         || !@is_writable( $output_dir ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide an output directory for database dump commands.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($results_model = PHS::load_model( 'results', 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup results model.' ) );
            return false;
        }

        // If no database connection is defined, return true so we don't trigger errors
        if( !($db_connections = PHS_Db::get_db_connection())
         || !is_array( $db_connections ) )
        {
            PHS_Logger::logf( 'No database connections defined.', $backup_plugin::LOG_CHANNEL );
            return true;
        }

        if( !($settings_arr = $backup_plugin->get_db_settings()) )
            $settings_arr = [];

        if( !empty( $params['mysqldump_bin'] ) )
            $mysqldump_bin = $params['mysqldump_bin'];
        elseif( !empty( $settings_arr['mysqldump_bin'] ) )
            $mysqldump_bin = $settings_arr['mysqldump_bin'];
        else
            $mysqldump_bin = 'mysqldump';

        if( !empty( $params['zip_bin'] ) )
            $zip_bin = $params['zip_bin'];
        elseif( !empty( $settings_arr['zip_bin'] ) )
            $zip_bin = $settings_arr['zip_bin'];
        else
            $zip_bin = 'zip';

        $dump_params = PHS_Db_class::default_dump_parameters();
        $dump_params['zip_dump'] = $params['zip_dump'];
        $dump_params['output_dir'] = $output_dir;
        $dump_params['binaries'] = [
            'zip_bin' => $zip_bin,
            'mysqldump_bin' => $mysqldump_bin,
        ];

        $dump_details_arr = [];
        $dump_details_arr['commands'] = [];
        $dump_details_arr['resulting_files'] = [];
        $dump_details_arr['generated_files'] = [];

        foreach( $db_connections as $connection_name => $connection_settings )
        {
            $dump_params['connection_name'] = $connection_name;

            if( ($dump_result = db_dump( $dump_params )) === false )
            {
                PHS_Logger::logf( 'Error obtaining dump command for connection ['.$connection_name.']', $backup_plugin::LOG_CHANNEL );
                PHS_Logger::logf( 'Error: '.self::st_get_error_message(), $backup_plugin::LOG_CHANNEL );
                continue;
            }

            if( empty( $dump_result ) || !is_array( $dump_result )
             || empty( $dump_result['dump_commands_for_shell'] ) || !is_array( $dump_result['dump_commands_for_shell'] ) )
            {
                // Nothing to execute for export, but there are files created for export... so we delete them
                if( !empty( $dump_result['delete_files_after_export'] ) && is_array( $dump_result['delete_files_after_export'] ) )
                {
                    foreach( $dump_result['delete_files_after_export'] as $file )
                        @unlink( $file );
                }

                continue;
            }

            if( !empty( $dump_result['generated_files'] ) && is_array( $dump_result['generated_files'] ) )
                $dump_details_arr['generated_files'] = $dump_result['generated_files'];


            foreach( $dump_result['dump_commands_for_shell'] as $command_str )
                $dump_details_arr['commands'][] = $command_str;


            if( !empty( $dump_result['delete_files_after_export'] ) && is_array( $dump_result['delete_files_after_export'] ) )
            {
                foreach( $dump_result['delete_files_after_export'] as $file )
                    $dump_details_arr['commands'][] = 'rm -f "'.$file.'"';
            }

            if( !empty( $dump_result['resulting_files'] ) && is_array( $dump_result['resulting_files'] ) )
            {
                if( !empty( $dump_result['resulting_files']['dump_files'] ) && is_array( $dump_result['resulting_files']['dump_files'] ) )
                {
                    foreach( $dump_result['resulting_files']['dump_files'] as $file )
                        $dump_details_arr['resulting_files'][] = [
                            'type' => $results_model::FILE_TYPE_RESULT,
                            'file' => $file,
                        ];
                }
                if( !empty( $dump_result['resulting_files']['log_files'] ) && is_array( $dump_result['resulting_files']['log_files'] ) )
                {
                    foreach( $dump_result['resulting_files']['log_files'] as $file )
                        $dump_details_arr['resulting_files'][] = [
                            'type' => $results_model::FILE_TYPE_LOG,
                            'file' => $file,
                        ];
                }
            }
        }

        return $dump_details_arr;
    }

    /**
     * @param string $output_dir
     * @param false|array $params
     *
     * @return array|false
     */
    public function get_uploaded_files_backup_script_commands( $output_dir, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        // Option to force mysqldump or zip location. If not provided, will use plugin settings or mysqldump (if in executables path)
        if( empty( $params['zip_bin'] ) )
            $params['zip_bin'] = '';
        // Root of backup location (to be excluded from zip)
        if( empty( $params['backup_path'] ) )
            $params['backup_path'] = '';

        if( empty( $output_dir )
         || !@is_dir( $output_dir )
         || !@is_writable( $output_dir ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide an output directory for uploaded files dump commands.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($results_model = PHS::load_model( 'results', 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup results model.' ) );
            return false;
        }

        if( !($settings_arr = $backup_plugin->get_db_settings()) )
            $settings_arr = [];

        if( !empty( $params['zip_bin'] ) )
            $zip_bin = $params['zip_bin'];
        elseif( !empty( $settings_arr['zip_bin'] ) )
            $zip_bin = $settings_arr['zip_bin'];
        else
            $zip_bin = 'zip';

        $zip_file = $output_dir.'/_uploads.zip';
        $log_file = $output_dir.'/_uploads.log';

        if( empty( $params['backup_path'] ) )
            $exclude_dir = '';
        else
            $exclude_dir = '*'.trim( str_replace( PHS_PATH, '', $params['backup_path'] ), '/\\' ).'*';

        $dump_details_arr = [];
        $dump_details_arr['commands'] = [];
        $dump_details_arr['resulting_files'] = [];
        $dump_details_arr['generated_files'] = [];

        $clean_uploads_name = rtrim( PHS_UPLOADS_DIR, '/\\' );
        $relative_uploads_dir = @dirname( $clean_uploads_name );
        $uploads_dir_name = trim( str_replace( $relative_uploads_dir, '', $clean_uploads_name ), '/\\' );

        $dump_details_arr['commands'][] = 'cd "'.$relative_uploads_dir.'"';
        $dump_details_arr['commands'][] = $zip_bin.' -r -0 -dbdc -q -lf "'.$log_file.'" -li '.
                                          (!empty( $exclude_dir )?' --exclude='.$exclude_dir.' ':'').
                                          '"'.$zip_file.'" '.$uploads_dir_name;
        $dump_details_arr['commands'][] = 'cd "'.$output_dir.'"';

        $dump_details_arr['resulting_files'][] = [
            'type' => $results_model::FILE_TYPE_RESULT,
            'file' => $zip_file,
        ];
        $dump_details_arr['resulting_files'][] = [
            'type' => $results_model::FILE_TYPE_LOG,
            'file' => $log_file,
        ];

        return $dump_details_arr;
    }

    /**
     * @param int|array $rule_data
     * @param false|array $params
     *
     * @return array|false
     */
    public function run_backup_rule_bg( $rule_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        // Run backup script now?
        if( !isset( $params['launch_shell_script'] ) )
            $params['launch_shell_script'] = true;
        else
            $params['launch_shell_script'] = (!empty( $params['launch_shell_script'] ));

        // Zip database dump
        if( !isset( $params['zip_dump'] ) )
            $params['zip_dump'] = true;
        else
            $params['zip_dump'] = (!empty( $params['zip_dump'] ));

        // Option to force mysqldump or zip location. If not provided, will use plugin settings or mysqldump (if in executables path)
        if( empty( $params['mysqldump_bin'] ) )
            $params['mysqldump_bin'] = '';
        if( empty( $params['zip_bin'] ) )
            $params['zip_bin'] = '';

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' ))
         || !($r_flow_params = $rules_model->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup rules model.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($results_model = PHS::load_model( 'results', 'backup' ))
         || !($res_flow_params = $results_model->fetch_default_flow_params( [ 'table_name' => 'backup_results' ] )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup results model.' ) );
            return false;
        }

        if( empty( $rule_data )
         || !($rule_arr = $rules_model->data_to_array( $rule_data, $r_flow_params ))
         || $rules_model->is_deleted( $rule_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule not found in database.' ) );
            return false;
        }

        if( !($rule_location = $this->get_backup_directory( $rule_arr ))
         || empty( $rule_location['rule_path'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining backup rule location details.' ) );

            PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL );

            return false;
        }

        $run_time = time();
        $run_time_str = date( 'YmdHis', $run_time );
        $run_path = $rule_location['rule_path'].'/'.$run_time_str;
        if( !@file_exists( $run_path )
         || !@is_dir( $run_path ) )
        {
            if( !@mkdir( $run_path, 0750 ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error creating backup running directory in rule location directory.' ) );

                PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): Error creating backup running directory in rule location directory ('.$run_path.').', $backup_plugin::LOG_CHANNEL );

                return false;
            }
        }

        if( !($settings_arr = $backup_plugin->get_db_settings()) )
            $settings_arr = [];

        if( !empty( $params['mysqldump_bin'] ) )
            $mysqldump_bin = $params['mysqldump_bin'];
        elseif( !empty( $settings_arr['mysqldump_bin'] ) )
            $mysqldump_bin = $settings_arr['mysqldump_bin'];
        else
            $mysqldump_bin = 'mysqldump';

        if( !empty( $params['zip_bin'] ) )
            $zip_bin = $params['zip_bin'];
        elseif( !empty( $settings_arr['zip_bin'] ) )
            $zip_bin = $settings_arr['zip_bin'];
        else
            $zip_bin = 'zip';

        $dump_params = [];
        $dump_params['backup_path'] = (!empty( $rule_location['backup_path'] )?$rule_location['backup_path']:'');
        $dump_params['zip_dump'] = $params['zip_dump'];
        $dump_params['zip_bin'] = $zip_bin;
        $dump_params['mysqldump_bin'] = $mysqldump_bin;

        $rule_arr['target'] = (int)$rule_arr['target'];
        $bg_script_commands = [];
        $resulting_files = [];
        $generated_files = [];

        PHS_Logger::logf( 'STARTING backup rule (R#'.$rule_arr['id'].'). Output ['.$run_path.']', $backup_plugin::LOG_CHANNEL );

        $bg_script_commands[] = 'cd "'.$run_path.'"';

        if( $rule_arr['target'] & (1 << self::BACKUP_TARGET_DATABASE) )
        {
            if( ($database_dump = $this->get_database_backup_script_commands( $run_path, $dump_params )) === false )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining batabase backup script commands.' ) );

                PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL );

                return false;
            }

            // if no database connections defined, get_database_backup_script_commands() will return true
            if( !empty( $database_dump ) && is_array( $database_dump ) )
            {
                if( !empty( $database_dump['generated_files'] ) && is_array( $database_dump['generated_files'] ) )
                {
                    foreach( $database_dump['generated_files'] as $file )
                        $generated_files[] = $file;
                }

                if( !empty( $database_dump['commands'] ) && is_array( $database_dump['commands'] ) )
                {
                    $bg_script_commands[] = '';
                    $bg_script_commands[] = '# Database dump commands';
                    foreach( $database_dump['commands'] as $buf )
                        $bg_script_commands[] = $buf;
                }

                if( !empty( $database_dump['resulting_files'] ) && is_array( $database_dump['resulting_files'] ) )
                {
                    foreach( $database_dump['resulting_files'] as $file )
                    {
                        if( empty( $file ) || !is_array( $file ) )
                            continue;

                        $file['target_id'] = self::BACKUP_TARGET_DATABASE;

                        $resulting_files[] = $file;
                    }
                }
            }
        }

        if( $rule_arr['target'] & (1 << self::BACKUP_TARGET_UPLOADS) )
        {
            if( ($uploaded_files_dump = $this->get_uploaded_files_backup_script_commands( $run_path, $dump_params )) === false )
            {
                if( !empty( $generated_files ) )
                {
                    foreach( $generated_files as $file )
                    {
                        if( @file_exists( $file ) )
                            @unlink( $file );
                    }
                }

                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error obtaining uploaded files backup script commands.' ) );

                PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL );

                return false;
            }

            // if no database connections $this->defined get_database_backup_script_buffer() will return true
            if( !empty( $uploaded_files_dump ) && is_array( $uploaded_files_dump ) )
            {
                if( !empty( $database_dump['generated_files'] ) && is_array( $database_dump['generated_files'] ) )
                {
                    foreach( $database_dump['generated_files'] as $file )
                        $generated_files[] = $file;
                }

                if( !empty( $uploaded_files_dump['commands'] ) && is_array( $uploaded_files_dump['commands'] ) )
                {
                    $bg_script_commands[] = '';
                    $bg_script_commands[] = '# Uploaded files commands';
                    foreach( $uploaded_files_dump['commands'] as $buf )
                        $bg_script_commands[] = $buf;
                }

                if( !empty( $uploaded_files_dump['resulting_files'] ) && is_array( $uploaded_files_dump['resulting_files'] ) )
                {
                    foreach( $uploaded_files_dump['resulting_files'] as $file )
                    {
                        if( empty( $file ) or !is_array( $file ) )
                            continue;

                        $file['target_id'] = self::BACKUP_TARGET_UPLOADS;

                        $resulting_files[] = $file;
                    }
                }
            }
        }

        if( empty( $bg_script_commands ) || !is_array( $bg_script_commands ) )
        {
            PHS_Utils::rmdir_tree( $run_path, [ 'recursive' => true ] );

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'No backup commands to pass to shell script.' ) );

            PHS_Logger::logf( 'Error (R#'.$rule_arr['id'].'): '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL );

            return false;
        }

        $insert_arr = $res_flow_params;
        $insert_arr['fields'] = [
            'rule_id' => $rule_arr['id'],
            'run_dir' => $run_path,
            'status' => $results_model::STATUS_PENDING,
            'cdate' => date( self::DATETIME_DB, $run_time ),
        ];
        $insert_arr['{result_files}'] = $resulting_files;

        if( !($result_arr = $results_model->insert( $insert_arr )) )
        {
            PHS_Utils::rmdir_tree( $run_path, [ 'recursive' => true ] );

            if( $results_model->has_error() )
                $this->copy_error( $results_model );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t save backup results details in database.' ) );

            return false;
        }

        $bg_job_params = [];
        $bg_job_params['result_id'] = $result_arr['id'];

        if( !($bg_job = PHS_Bg_jobs::run( [ 'plugin' => 'backup', 'controller' => 'index_bg', 'action' => 'finish_backup_script_bg' ],
                                          $bg_job_params,
                                          [ 'return_command' => true ] ))
         || empty( $bg_job['cmd'] ) )
        {
            PHS_Utils::rmdir_tree( $run_path, [ 'recursive' => true ] );

            if( self::st_has_error() )
                $error_msg = self::st_get_error_message();
            else
                $error_msg = $this->_pt( 'Error obtaining run rule finish background command.' );

            $this->set_error( self::ERR_FUNCTIONALITY, $error_msg );
            return false;
        }

        $bg_script_commands[] = '';
        $bg_script_commands[] = '# Announce finishing backup';
        $bg_script_commands[] = 'cd "'.PHS_PATH.'"';
        $bg_script_commands[] = $bg_job['cmd'];

        $shell_file = $run_path.'/run.sh';
        if( !$this->create_backup_script_from_commands( $result_arr['id'], $shell_file, $bg_script_commands ) )
        {
            PHS_Utils::rmdir_tree( $run_path, [ 'recursive' => true ] );
            $results_model->act_delete( $result_arr );

            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error creating backup shell script.' ) );

            return false;
        }

        $generated_files[] = $shell_file;

        $return_arr = [];
        $return_arr['result_data'] = $result_arr;
        $return_arr['resulting_files'] = $resulting_files;
        $return_arr['run_result'] = false;

        if( !empty( $params['launch_shell_script'] ) )
        {
            if( !($run_result = $this->launch_backup_rule_bg( $result_arr, [ 'rule_data' => $rule_arr ] )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error creating backup shell script.' ) );

                return false;
            }

            $return_arr['run_result'] = $run_result;
        }

        return $return_arr;
    }

    protected function create_backup_script_from_commands( $result_id, $shell_script, $commands_arr )
    {
        $this->reset_error();

        $result_id = (int)$result_id;
        if( empty( $result_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS,$this->_pt( 'Please provide a result ID.' ) );
            return false;
        }

        if( empty( $shell_script ) )
        {
            $this->set_error( self::ERR_PARAMETERS,$this->_pt( 'Please provide full script file name.' ) );
            return false;
        }

        if( empty( $commands_arr ) || !is_array( $commands_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS,$this->_pt( 'Commands array provided is empty.' ) );
            return false;
        }

        if( !($fil = @fopen( $shell_script, 'wb' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating backup shell script.' ) );
            return false;
        }

        if( !@fwrite( $fil, '#!/bin/bash' . "\n" .
                            '# Backup shell script...' . "\n" ) )
        {
            @fclose( $fil );
            @unlink( $shell_script );

            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t write to backup shell script.' ) );
            return false;
        }

        foreach( $commands_arr as $command_str )
        {
            if( !@fwrite( $fil, $command_str . "\n" ) )
            {
                @fclose( $fil );
                @unlink( $shell_script );

                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t write commands to backup shell script.' ) );
                return false;
            }
        }

        if( !@fwrite( $fil, "\n" .
                            '# Delete myself...' . "\n" .
                            'rm -f "' . $shell_script . '"' . "\n" ) )
        {
            @fclose( $fil );
            @unlink( $shell_script );

            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t write commands to backup shell script.' ) );
            return false;
        }

        @chmod( $shell_script, 0755 );

        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

    /**
     * @param int|array $result_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function launch_backup_rule_bg( $result_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['force'] ) )
            $params['force'] = false;
        else
            $params['force'] = (!empty( $params['force'] ));

        if( !($r_flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain required resources.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($results_model = PHS::load_model( 'results', 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup results model.' ) );
            return false;
        }

        if( !($launch_result = $results_model->launch_result_shell_script_bg( $result_data, $params )) )
        {
            if( $results_model->has_error() )
                $this->copy_error( $results_model );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error launching backup result shell script.' ) );
            return false;
        }

        $rule_data = false;
        if( !empty( $params['rule_data'] ) )
            $rule_data = $params['rule_data'];
        elseif( !empty( $launch_result['result_data'] ) )
            $rule_data = $launch_result['result_data']['rule_id'];

        $rule_arr = false;
        if( !empty( $rule_data )
        and ($rule_arr = $this->data_to_array( $rule_data, $r_flow_params )) )
        {
            $edit_arr = $r_flow_params;
            $edit_arr['fields'] = [
                'last_run' => date( self::DATETIME_DB ),
            ];

            if( ($new_rule = $this->edit( $rule_arr, $edit_arr )) )
                $rule_arr = $new_rule;
        }

        $launch_result['rule_data'] = $rule_arr;

        return $launch_result;
    }

    /**
     * @param int|array $result_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function finish_backup_rule_bg( $result_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['force'] ) )
            $params['force'] = false;
        else
            $params['force'] = (!empty( $params['force'] ));

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($results_model = PHS::load_model( 'results', 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup results model.' ) );
            return false;
        }

        if( !($finish_result = $results_model->launch_result_shell_script_bg( $result_data, $params )) )
        {
            if( $results_model->has_error() )
                $this->copy_error( $results_model );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error launching backup result shell script.' ) );
            return false;
        }

        return $finish_result;
    }

    /**
     * @param array $target_arr
     *
     * @return int
     */
    public function targets_arr_to_bits( $target_arr )
    {
        if( empty( $target_arr ) || !is_array( $target_arr ) )
            return 0;

        $target_bits = 0;
        foreach( $target_arr as $target )
        {
            $target = (int)$target;
            if( !$this->valid_target( $target ) )
                continue;

            $target_bits |= (1 << $target);
        }

        return $target_bits;
    }

    /**
     * @param int $bits
     *
     * @return array
     */
    public function bits_to_targets_arr( $bits )
    {
        $bits = (int)$bits;
        if( empty( $bits )
         || !($all_targets = $this->get_targets()) )
            return array();

        $return_arr = [];
        foreach( $all_targets as $target_id => $target_arr )
        {
            if( ($bits & (1 << $target_id)) )
                $return_arr[] = $target_id;
        }

        return $return_arr;
    }

    /**
     * @param $rule_id
     *
     * @return array
     */
    public function get_rule_days_as_array( $rule_id )
    {
        $this->reset_error();

        $rule_id = (int)$rule_id;
        if( empty( $rule_id )
         || !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules_days' ] ))
         || !($qid = db_query( 'SELECT * FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE rule_id = \''.$rule_id.'\' ORDER BY `day` ASC', $this->get_db_connection( $flow_params ) ))
         || !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = [];
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[] = $link_arr['day'];
        }

        return $return_arr;
    }

    /**
     * @param int|array $rule_data
     *
     * @return bool
     */
    public function unlink_all_days_for_rule( $rule_data )
    {
        $this->reset_error();

        if( !($rule_arr = $this->data_to_array( $rule_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Backup rule not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules_days' ] ))
         || !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE rule_id = \''.$rule_arr['id'].'\'', $this->get_db_connection( $flow_params ) ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink days for backup rule.' ) );
            return false;
        }

        return true;
    }

    /**
     * @param int|array $rule_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function get_rule_ftp_settings( $rule_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !($rule_arr = $this->data_to_array( $rule_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Backup rule not found in database.' ) );
            return false;
        }

        if( empty( $rule_arr['ftp_settings'] ) )
            $rule_arr['{ftp_settings}'] = [];

        else
        {
            if( !($rule_arr['{ftp_settings}'] = PHS_Line_params::parse_string( $rule_arr['ftp_settings'] )) )
                $rule_arr['{ftp_settings}'] = [];

            if( !empty( $rule_arr['{ftp_settings}']['pass'] ) )
                $rule_arr['{ftp_settings}']['pass'] = PHS_Crypt::quick_decode( $rule_arr['{ftp_settings}']['pass'] );
        }

        return $rule_arr;
    }

    /**
     * @param int|array $rule_data
     * @param array $days_arr
     * @param bool|array $params
     *
     * @return array|array[]|bool|bool[]
     */
    public function link_days_to_rule( $rule_data, $days_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !is_array( $days_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No days provided to link to backup rule.' ) );
            return false;
        }

        if( !($rule_arr = $this->data_to_array( $rule_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Backup rule not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules_days' ] ))
         || !($rules_days_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Invalid flow parameters.' ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        $return_arr = [];
        if( empty( $days_arr ) )
        {
            // Unlink all roles...
            if( !db_query( 'DELETE FROM `'.$rules_days_table_name.'` WHERE rule_id = \''.$rule_arr['id'].'\'', $db_connection ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking days for backup rule.' ) );
                return false;
            }

            return $return_arr;
        }

        if( !($existing_days = $this->get_rule_days_as_list( $rule_arr['id'] )) )
            $existing_days = [];

        $days_current_list = [];
        $insert_days = [];
        $delete_days = [];
        $delete_ids = [];
        $day_zero_record = false;
        $should_delete_rest_but_zero = false;
        foreach( $existing_days as $day_id => $day_arr )
        {
            if( empty( $day_arr ) || !is_array( $day_arr )
             || !isset( $day_arr['day'] ) )
                continue;

            $day_arr['day'] = (int)$day_arr['day'];

            $days_current_list[] = $day_arr['day'];

            if( empty( $day_arr['day'] ) )
                $day_zero_record = $day_arr;
            else
                $should_delete_rest_but_zero = true;

            if( in_array( $day_arr['day'], $days_arr ) )
                $return_arr[$day_id] = $day_arr;

            else
            {
                $delete_ids[] = $day_arr['id'];
                $delete_days[] = $day_arr['day'];
            }
        }

        $day_zero_to_be_added = false;
        foreach( $days_arr as $day_no )
        {
            if( empty( $day_no ) )
            {
                $day_zero_to_be_added = true;
                break;
            }

            if( !in_array( $day_no, $delete_days )
             && !in_array( $day_no, $days_current_list ) )
                $insert_days[] = $day_no;
        }

        if( $day_zero_to_be_added )
        {
            if( $should_delete_rest_but_zero
            and !db_query( 'DELETE FROM `'.$rules_days_table_name.'` WHERE rule_id = \''.$rule_arr['id'].'\' AND day != 0', $db_connection ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking days for backup rule.' ) );
                return false;
            }

            $day_insert_arr = $flow_params;
            $day_insert_arr['fields']['rule_id'] = $rule_arr['id'];
            $day_insert_arr['fields']['day'] = 0;

            if( empty( $day_zero_record )
             && !($day_zero_record = $this->insert( $day_insert_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error linking days to backup rule.' ) );
                return false;
            }

            return [ $day_zero_record['id'] => $day_zero_record ];
        }

        if( !empty( $insert_days ) )
        {
            $day_insert_arr = $flow_params;
            $day_insert_arr['fields']['rule_id'] = $rule_arr['id'];

            foreach( $insert_days as $day_no )
            {
                $day_insert_arr['fields']['day'] = $day_no;

                if( !($day_record = $this->insert( $day_insert_arr )) )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error linking days to backup rule.' ) );
                    return false;
                }

                $return_arr[$day_record['id']] = $day_record;
            }
        }

        if( !empty( $delete_ids )
         && !db_query( 'DELETE FROM `'.$rules_days_table_name.'` WHERE rule_id = \''.$rule_arr['id'].'\' AND id IN ('.implode( ',', $delete_ids ).')', $db_connection ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking days for backup rule.' ) );
            return false;
        }

        return $return_arr;
    }

    /**
     * @param int $rule_id
     *
     * @return array
     */
    public function get_rule_days_as_list( $rule_id )
    {
        $this->reset_error();

        $rule_id = (int)$rule_id;
        if( empty( $rule_id )
         || !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'backup_rules_days' ] ))
         || !($qid = db_query( 'SELECT * FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE rule_id = \''.$rule_id.'\'', $this->get_db_connection( $flow_params ) ))
         || !@mysqli_num_rows( $qid ) )
            return [];

        $return_arr = [];
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[(int)$link_arr['id']] = $link_arr;
        }

        return $return_arr;
    }

    /**
     * @param array $params
     *
     * @return array|false
     */
    protected function get_insert_prepare_params_backup_rules( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( empty( $params['fields']['title'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a rule title.' ) );
            return false;
        }

        if( empty( $params['fields']['copy_results'] ) )
            $params['fields']['copy_results'] = 0;

        elseif( !$this->valid_copy_results( $params['fields']['copy_results'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a copy result action.' ) );
            return false;
        }

        if( !empty( $params['fields']['copy_results'] ) )
        {
            switch( $params['fields']['copy_results'] )
            {
                case self::COPY_FTP:
                /** @var \phs\system\core\libraries\PHS_Ftp $ftp_obj */
                if( !($ftp_obj = PHS::get_core_library_instance( 'ftp' )) )
                {
                    $this->set_error( self::ERR_INSERT, $this->_pt( 'Couldn\'t load FTP core library.' ) );
                    return false;
                }

                if( !empty( $params['fields']['ftp_settings'] ) )
                {
                    if( !is_array( $params['fields']['ftp_settings'] ) )
                        $params['fields']['ftp_settings'] = PHS_Line_params::parse_string( $params['fields']['ftp_settings'] );
                }

                if( empty( $params['fields']['ftp_settings'] )
                 or !is_array( $params['fields']['ftp_settings'] )
                 or !$ftp_obj::settings_valid( $params['fields']['ftp_settings'] ) )
                {
                    $this->set_error( self::ERR_INSERT, $this->_pt( 'Invalid FTP settings.' ) );
                    return false;
                }

                if( empty( $params['fields']['ftp_settings']['pass'] ) )
                    $params['fields']['ftp_settings']['pass'] = '';

                $params['fields']['ftp_settings']['pass'] = PHS_Crypt::quick_encode( $params['fields']['ftp_settings']['pass'] );

                $params['fields']['ftp_settings'] = PHS_Line_params::to_string( $params['fields']['ftp_settings'] );
                break;
            }
        }

        if( empty( $params['fields']['location'] ) )
            $params['fields']['location'] = '';

        else
        {
            $params['fields']['location'] = rtrim( $params['fields']['location'], '/\\' );
            $location_check = $params['fields']['location'];
            if( strpos( $params['fields']['location'], '/' ) !== 0
             && substr( $params['fields']['location'], 1, 2 ) !== ':\\' )
                $location_check = PHS_PATH.$params['fields']['location'];

            // we have an absolute path
            if( !@file_exists( $location_check )
             || !@is_dir( $location_check ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided backup location is not a directory.' ) );
                return false;
            }

            if( !@is_writable( $location_check ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided backup location is not writable.' ) );
                return false;
            }
        }

        $cdate = date( self::DATETIME_DB );

        if( empty( $params['fields']['uid'] ) )
            $params['fields']['uid'] = 0;

        if( empty( $params['fields']['hour'] ) )
            $params['fields']['hour'] = 0;

        else
        {
            $params['fields']['hour'] = (int)$params['fields']['hour'];
            if( $params['fields']['hour'] < 0 || $params['fields']['hour'] > 23 )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a valid backup hour.' ) );
                return false;
            }
        }

        if( empty( $params['fields']['delete_after_days'] ) )
            $params['fields']['delete_after_days'] = 0;

        elseif( $params['fields']['delete_after_days'] < 0 )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a valid result delition interval.' ) );
            return false;
        }

        if( empty( $params['fields']['target'] ) )
            $params['fields']['target'] = $this->get_all_targets();

        elseif( is_array( $params['fields']['target'] ) )
        {
            if( !($target_bits = $this->targets_arr_to_bits( $params['fields']['target'] )) )
                $target_bits = $this->get_all_targets();

            $params['fields']['target'] = $target_bits;
        } else
        {
            $params['fields']['target'] = (int)$params['fields']['target'];
            if( ($targets_arr = $this->get_targets_as_key_val()) )
            {
                $target_bits = 0;
                foreach( $targets_arr as $target_key => $target_name )
                {
                    if( ($params['fields']['target'] & (1 << $target_key)) )
                        $target_bits |= (1 << $target_key);
                }

                $params['fields']['target'] = $target_bits;
            }
        }

        if( empty( $params['fields']['status'] )
         || !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_ACTIVE;

        $params['fields']['cdate'] = $cdate;

        if( empty( $params['fields']['status_date'] )
         || empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];
        else
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        // Default backup on all days
        if( empty( $params['{days_arr}'] ) || !is_array( $params['{days_arr}'] ) )
            $params['{days_arr}'] = [ 0 ];

        return $params;
    }

    /**
     * Called right after a successfull insert in database. Some model need more database work after successfully adding records in database or eventually chaining
     * database inserts. If one chain fails function should return false so all records added before to be hard-deleted. In case of success, function will return an array with all
     * key-values added in database.
     *
     * @param array $insert_arr Data array added with success in database
     * @param array $params Flow parameters
     *
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function insert_after_backup_rules( $insert_arr, $params )
    {
        $insert_arr['{days_arr}'] = [];

        if( empty( $params['{days_arr}'] ) || !is_array( $params['{days_arr}'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide backup rule days.' ) );
            return false;
        }

        if( !($insert_arr['{days_arr}'] = $this->link_days_to_rule( $insert_arr, $params['{days_arr}'] )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, $this->_pt( 'Error linking days to backup rule.' ) );

            return false;
        }

        return $insert_arr;
    }

    protected function get_edit_prepare_params_backup_rules( $existing_arr, $params )
    {
        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( isset( $params['fields']['title'] ) && empty( $params['fields']['title'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a rule title.' ) );
            return false;
        }

        if( isset( $params['fields']['target'] ) && empty( $params['fields']['target'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide backup targets.' ) );
            return false;
        }

        if( isset( $params['fields']['status'] ) && !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide valid status for backup rule.' ) );
            return false;
        }

        if( isset( $params['fields']['copy_results'] ) )
        {
            if( empty( $params['fields']['copy_results'] ) )
            {
                $params['fields']['copy_results'] = 0;
                $params['fields']['ftp_settings'] = '';
            } elseif( !$this->valid_copy_results( $params['fields']['copy_results'] ) )
            {
                $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a copy result action.' ) );
                return false;
            } else
            {
                switch( $params['fields']['copy_results'] )
                {
                    case self::COPY_FTP:
                        /** @var \phs\system\core\libraries\PHS_Ftp $ftp_obj */
                        if( !($ftp_obj = PHS::get_core_library_instance( 'ftp' )) )
                        {
                            $this->set_error( self::ERR_EDIT, $this->_pt( 'Couldn\'t load FTP core library.' ) );
                            return false;
                        }

                        if( !empty( $params['fields']['ftp_settings'] ) )
                        {
                            if( !is_array( $params['fields']['ftp_settings'] ) )
                                $params['fields']['ftp_settings'] = PHS_Line_params::parse_string( $params['fields']['ftp_settings'] );
                        }

                        if( empty( $params['fields']['ftp_settings'] )
                         or !is_array( $params['fields']['ftp_settings'] )
                         or !$ftp_obj::settings_valid( $params['fields']['ftp_settings'] ) )
                        {
                            $this->set_error( self::ERR_INSERT, $this->_pt( 'Invalid FTP settings.' ) );
                            return false;
                        }

                        if( empty( $params['fields']['ftp_settings']['pass'] ) )
                            $params['fields']['ftp_settings']['pass'] = '';

                        $params['fields']['ftp_settings']['pass'] = PHS_Crypt::quick_encode( $params['fields']['ftp_settings']['pass'] );

                        $params['fields']['ftp_settings'] = PHS_Line_params::to_string( $params['fields']['ftp_settings'] );
                    break;
                }
            }
        }

        if( isset( $params['fields']['location'] ) )
        {
            if( !($location_check = $backup_plugin->resolve_directory_location( $params['fields']['location'] )) )
            {
                if( $backup_plugin->has_error() )
                    $this->copy_error( $backup_plugin );
                else
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided backup location couldn\'t be checked.' ) );

                return false;
            }

            // we have an absolute path
            if( empty( $location_check['location_exists'] )
             or empty( $location_check['location_is_dir'] ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided backup location is not a directory.' ) );
                return false;
            }

            if( !@is_writable( $location_check['full_path'] ) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided backup location is not writable.' ) );
                return false;
            }

            $params['fields']['location'] = $location_check['location_path'];
        }

        if( isset( $params['fields']['hour'] )
        and !is_array( $params['fields']['hour'] ) )
        {
            $params['fields']['hour'] = intval( $params['fields']['hour'] );
            if( $params['fields']['hour'] < 0 or $params['fields']['hour'] > 23 )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a valid backup hour.' ) );
                return false;
            }
        }

        if( isset( $params['fields']['target'] ) )
        {
            if( empty( $params['fields']['target'] ) )
                $params['fields']['target'] = $this->get_all_targets();

            elseif( is_array( $params['fields']['target'] ) )
            {
                if( !($target_bits = $this->targets_arr_to_bits( $params['fields']['target'] )) )
                    $target_bits = $this->get_all_targets();

                $params['fields']['target'] = $target_bits;
            } else
            {
                $params['fields']['target'] = intval( $params['fields']['target'] );
                if( ($targets_arr = $this->get_targets_as_key_val()) )
                {
                    $target_bits = 0;
                    foreach( $targets_arr as $target_key => $target_name )
                    {
                        if( ($params['fields']['target'] & (1 << $target_key)) )
                            $target_bits |= (1 << $target_key);
                    }

                    $params['fields']['target'] = $target_bits;
                }
            }
        }

        if( !empty( $params['fields']['status'] )
        and (empty( $params['fields']['status_date'] ) or empty_db_date( $params['fields']['status_date'] ))
        and $this->valid_status( $params['fields']['status'] )
        and $params['fields']['status'] != $existing_arr['status'] )
            $params['fields']['status_date'] = date( self::DATETIME_DB );

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );


        if( !empty( $params['fields']['last_run'] ) )
            $params['fields']['last_run'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['last_run'] ) );

        if( empty( $params['{days_arr}'] ) or !is_array( $params['{days_arr}'] ) )
            $params['{days_arr}'] = false;

        return $params;
    }

    /**
     * Called right after a successfull edit action. Some model need more database work after editing records. This action is called even if model didn't save anything
     * in database.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array $edit_arr Data array saved with success in database. This can also be an empty array (nothing to save in database)
     * @param array $params Flow parameters
     *
     * @return array|bool Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function edit_after_backup_rules( $existing_data, $edit_arr, $params )
    {
        if( !empty( $params['{days_arr}'] ) and is_array( $params['{days_arr}'] ) )
        {
            if( !($existing_data['{days_arr}'] = $this->link_days_to_rule( $existing_data, $params['{days_arr}'] )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_INSERT, $this->_pt( 'Error linking days to backup rule.' ) );

                return false;
            }
        }

        return $existing_data;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
     */
    protected function get_insert_prepare_params_backup_rules_days( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( !empty( $params['fields']['rule_id'] ) )
            $params['fields']['rule_id'] = (int)$params['fields']['rule_id'];
        if( !empty( $params['fields']['day'] ) )
            $params['fields']['day'] = (int)$params['fields']['day'];

        if( empty( $params['fields']['rule_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup rule id.' ) );
            return false;
        }

        if( $params['fields']['day'] < 0 || $params['fields']['day'] > 7 )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid day for backup rule.' ) );
            return false;
        }

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
     * @return array|bool Flow parameters array
     */
    protected function get_edit_prepare_params_backup_rules_days( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['rule_id'] ) )
            $params['fields']['rule_id'] = (int)$params['fields']['rule_id'];
        if( isset( $params['fields']['day'] ) )
            $params['fields']['day'] = (int)$params['fields']['day'];

        if( isset( $params['fields']['rule_id'] ) && empty( $params['fields']['rule_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup rule id.' ) );
            return false;
        }

        if( isset( $params['fields']['day'] )
        && ($params['fields']['day'] < 0 || $params['fields']['day'] > 7) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid day for backup rule.' ) );
            return false;
        }

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
            case 'backup_rules':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'uid' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'title' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                    ],
                    'delete_after_days' => [
                        'type' => self::FTYPE_INT,
                        'comment' => 'Delete backups older than x days, 0 disabled',
                    ],
                    'hour' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'target' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'location' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                    ],
                    'copy_results' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Should results be copied',
                    ],
                    'ftp_settings' => [
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_run' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
            break;

            case 'backup_rules_days':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'rule_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'day' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                ];
            break;
        }

        return $return_arr;
    }
}
