<?php

namespace phs\plugins\backup\models;

use \phs\PHS;
use \phs\PHS_Db;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Db_class;
use \phs\libraries\PHS_Utils;

class PHS_Model_Results extends PHS_Model
{
    const STATUS_PENDING = 1, STATUS_RUNNING = 2, STATUS_FINISHED = 3, STATUS_ERROR = 4;
    protected static $STATUSES_ARR = array(
        self::STATUS_PENDING => array( 'title' => 'Pending' ),
        self::STATUS_RUNNING => array( 'title' => 'Running' ),
        self::STATUS_FINISHED => array( 'title' => 'Finished' ),
        self::STATUS_ERROR => array( 'title' => 'Error' ),
    );

    const FILE_TYPE_LOG = 1, FILE_TYPE_RESULT = 2;
    protected static $FILE_TYPES_ARR = array(
        self::FILE_TYPE_LOG => array( 'title' => 'Log file' ),
        self::FILE_TYPE_RESULT => array( 'title' => 'Result file' ),
    );

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
        return array( 'backup_results', 'backup_results_files' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'backup_results';
    }

    final public function get_statuses( $lang = false )
    {
        static $statuses_arr = array();

        if( $lang === false
        and !empty( $statuses_arr ) )
            return $statuses_arr;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Pending' );
        $this->_pt( 'Running' );
        $this->_pt( 'Finished' );
        $this->_pt( 'Error' );

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
         or !isset( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    final public function get_file_types( $lang = false )
    {
        static $file_types_arr = array();

        if( $lang === false
        and !empty( $file_types_arr ) )
            return $file_types_arr;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Log file' );
        $this->_pt( 'Result file' );

        $result_arr = $this->translate_array_keys( self::$FILE_TYPES_ARR, array( 'title' ), $lang );

        if( $lang === false )
            $file_types_arr = $result_arr;

        return $result_arr;
    }

    final public function get_file_types_as_key_val( $lang = false )
    {
        static $file_types_key_val_arr = false;

        if( $lang === false
        and $file_types_key_val_arr !== false )
            return $file_types_key_val_arr;

        $key_val_arr = array();
        if( ($types = $this->get_file_types( $lang )) )
        {
            foreach( $types as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $file_types_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function valid_file_type( $type, $lang = false )
    {
        $all_types = $this->get_file_types( $lang );
        if( empty( $type )
         or !isset( $all_types[$type] ) )
            return false;

        return $all_types[$type];
    }

    public function is_pending( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or (int)$record_arr['status'] !== self::STATUS_PENDING )
            return false;

        return $record_arr;
    }

    public function is_running( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or (int)$record_arr['status'] !== self::STATUS_RUNNING )
            return false;

        return $record_arr;
    }

    public function is_finished( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or (int)$record_arr['status'] !== self::STATUS_FINISHED )
            return false;

        return $record_arr;
    }

    public function is_error( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or (int)$record_arr['status'] !== self::STATUS_FINISHED )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     * @param bool|array $params
     *
     * @return bool
     */
    public function act_delete( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         or !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'Backup result details not found in database.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !$this->unlink_all_result_files_for_result( $record_arr, array( 'update_result' => false ) ) )
            return false;

        PHS_Utils::rmdir_tree( $record_arr['run_dir'], array( 'recursive' => true ) );

        return $this->hard_delete( $record_arr );
    }

    /**
     * @param int|array $result_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function launch_result_shell_script_bg( $result_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['force'] ) )
            $params['force'] = false;
        else
            $params['force'] = (!empty( $params['force'] ));

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( empty( $result_data )
         or !($br_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) ))
         or !($result_arr = $this->data_to_array( $result_data, $br_flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule details not found in database.' ) );
            return false;
        }

        if( empty( $result_arr['run_dir'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup result run directory not set.' ) );
            return false;
        }

        if( empty( $params['force'] )
        and !$this->is_pending( $result_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup result is not pending execution.' ) );
            return false;
        }

        $edit_arr = $br_flow_params;
        $edit_arr['fields'] = array(
            'status' => self::STATUS_RUNNING,
        );

        $shell_file = $result_arr['run_dir'].'/run.sh &';

        ob_start();
        $script_launched = (@system( $shell_file ) !== false );
        ob_clean();

        if( !$script_launched )
            $edit_arr['fields']['status'] = self::STATUS_ERROR;

        if( !($new_result = $this->edit( $result_arr, $edit_arr )) )
        {
            $status_title = '(???)';
            if( ($status_arr = $this->valid_status( $edit_arr['fields']['status'] )) )
                $status_title = $status_arr['title'];

            PHS_Logger::logf( 'Failed setting result data to '.$status_title.' ('.$edit_arr['fields']['status'].')', $backup_plugin::LOG_CHANNEL );
        }

        return array(
            'script_launched' => $script_launched,
            'result_data' => $new_result,
        );
    }

    /**
     * @param array|int $result_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function finish_result_shell_script_bg( $result_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['force'] ) )
            $params['force'] = false;
        else
            $params['force'] = (!empty( $params['force'] ));

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load backup rules model.' ) );
            return false;
        }

        if( empty( $result_data )
         or !($br_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) ))
         or !($result_arr = $this->data_to_array( $result_data, $br_flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup rule details not found in database.' ) );
            return false;
        }

        if( empty( $params['force'] )
        and !$this->is_running( $result_arr ) )
        {
            PHS_Logger::logf( 'Cannot finish rule (R#'.$result_arr['rule_id'].', Result#'.$result_arr['id'].'). Rule is not running.', $backup_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Backup result is not running.' ) );
            return false;
        }

        if( !$this->update_result_files_size( $result_arr['id'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating backup result files sizes.' ) );
            return false;
        }

        $edit_arr = $br_flow_params;
        $edit_arr['fields'] = array(
            'status' => self::STATUS_FINISHED,
        );

        if( !($new_result = $this->edit( $result_arr, $edit_arr )) )
        {
            $status_title = '(???)';
            if( ($status_arr = $this->valid_status( $edit_arr['fields']['status'] )) )
                $status_title = $status_arr['title'];

            PHS_Logger::logf( 'Failed setting result data to '.$status_title.' ('.$edit_arr['fields']['status'].')', $backup_plugin::LOG_CHANNEL );
        }

        PHS_Logger::logf( 'FINISHED rule (R#'.$result_arr['rule_id'].', Result#'.$result_arr['id'].'). Output ['.$result_arr['run_dir'].']', $backup_plugin::LOG_CHANNEL );

        return array(
            'result_data' => $new_result,
        );
    }

    public function update_result_size( $result_id )
    {
        $this->reset_error();

        $result_id = intval( $result_id );
        if( empty( $result_id )
         or !($br_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) ))
         or !($brf_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results_files' ) ))
         or !($br_table_name = $this->get_flow_table_name( $br_flow_params ))
         or !($brf_table_name = $this->get_flow_table_name( $brf_flow_params ))
         or !($qid = db_query( 'SELECT SUM(`size`) AS total_size FROM `'.$brf_table_name.'` WHERE result_id = \''.$result_id.'\'', $brf_flow_params['db_connection'] ))
         or !($total_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Cannot obtain backup result files from database.' ) );
            return false;
        }

        if( empty( $total_arr['total_size'] ) )
            $total_arr['total_size'] = 0;

        if( !db_query( 'UPDATE `'.$br_table_name.'` SET size = \''.$total_arr['total_size'].'\' WHERE id = \''.$result_id.'\'', $br_flow_params['db_connection'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error updating backup result size.' ) );
            return false;
        }

        return $total_arr['total_size'];
    }

    public function update_result_files_size( $result_id )
    {
        $this->reset_error();

        $result_id = (int)$result_id;
        if( empty( $result_id )
         or !($br_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) ))
         or !($brf_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results_files' ) ))
         or !($br_table_name = $this->get_flow_table_name( $br_flow_params ))
         or !($brf_table_name = $this->get_flow_table_name( $brf_flow_params ))
         or !($qid = db_query( 'SELECT * FROM `'.$brf_table_name.'` WHERE result_id = \''.$result_id.'\'', $brf_flow_params['db_connection'] )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Cannot obtain backup result files from database.' ) );
            return false;
        }

        // As we don't have any records, be sure result size is 0
        if( !@mysqli_num_rows( $qid ) )
            return $this->update_result_size( $result_id );

        $we_have_error = false;
        $sizes_changed = false;
        $total_size = 0;
        while( ($result_file_arr = @mysqli_fetch_assoc( $qid )) )
        {
            if( empty( $result_file_arr['file'] )
             or !@file_exists( $result_file_arr['file'] ) )
            {
                $we_have_error = true;
                continue;
            }

            if( !($file_size = @filesize( $result_file_arr['file'] )) )
                $file_size = 0;

            if( (int)$file_size !== (int)$result_file_arr['size'] )
            {
                $sizes_changed = true;
                $result_file_arr['size'] = $file_size;

                if( !db_query( 'UPDATE `'.$brf_table_name.'` SET size = \''.$file_size.'\' WHERE id = \''.$result_file_arr['id'].'\'', $brf_flow_params['db_connection'] ) )
                    $we_have_error = true;
            }

            $total_size += $result_file_arr['size'];
        }

        if( !empty( $we_have_error ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating backup result files size.' ) );
            return false;
        }

        if( empty( $sizes_changed ) )
            return $total_size;

        return $this->update_result_size( $result_id );
    }

    public function get_result_files( $result_id )
    {
        $this->reset_error();

        $result_id = intval( $result_id );
        if( empty( $result_id )
         or !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results_files' ) ))
         or !($qid = db_query( 'SELECT * FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE result_id = \''.$result_id.'\' ORDER BY `id` ASC', $flow_params['db_connection'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Cannot obtain backup result files from database.' ) );
            return false;
        }

        if( !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[$link_arr['id']] = $link_arr;
        }

        return $return_arr;
    }

    public function unlink_all_result_files_for_result( $result_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['update_result'] ) )
            $params['update_result'] = true;
        else
            $params['update_result'] = (!empty( $params['update_result'] )?true:false);

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( !($result_arr = $this->data_to_array( $result_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Backup result not found in database.' ) );
            return false;
        }

        // Error obtaining backup result files from database
        if( ($result_files_arr = $this->get_result_files( $result_arr['id'] )) === false )
            return false;

        // No result files in database...
        if( empty( $result_files_arr ) or !is_array( $result_files_arr ) )
            return $result_arr;

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results_files' ) ))
         or !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE result_id = \''.$result_arr['id'].'\'', $this->get_db_connection( $flow_params ) ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink result files from result.' ) );
            return false;
        }

        foreach( $result_files_arr as $file_id => $file_arr )
        {
            if( empty( $file_arr['file'] )
             or !@file_exists( $file_arr['file'] ) )
                continue;

            @unlink( $file_arr['file'] );
        }

        if( !empty( $params['update_result'] ) )
        {
            $edit_arr = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) );
            $edit_arr['fields'] = array(
                'size' => 0,
            );

            if( !($new_result_arr = $this->edit( $result_arr, $edit_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating result details from database.' ) );

                PHS_Logger::logf( '!!! Error updating backup result when deleting all backup result files. Backup result #'.$result_arr['id'].' size 0.', $backup_plugin::LOG_CHANNEL );

                return false;
            }

            $result_arr = $new_result_arr;
        }

        return $result_arr;
    }

    public function unlink_result_file( $result_file_data, $params = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results_files' ) ))
         or !($result_file_arr = $this->data_to_array( $result_file_data, $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Backup result file not found in database.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['update_result'] ) )
            $params['update_result'] = true;
        else
            $params['update_result'] = (!empty( $params['update_result'] )?true:false);

        $result_file_arr['{result_data}'] = false;
        if( !empty( $params['update_result'] ) )
        {
            $edit_arr = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) );
            $edit_arr['fields'] = array(
                'size' => array( 'raw_field' => true, 'value' => 'size - '.$result_file_arr['size'] ),
            );

            if( !($result_arr = $this->edit( $result_file_arr['result_id'], $edit_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating result details from database.' ) );

                return false;
            }

            $result_file_arr['{result_data}'] = $result_arr;
        }

        if( !$this->hard_delete( $result_file_arr, $flow_params ) )
        {
            if( !empty( $params['update_result'] ) )
            {
                $edit_arr = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results' ) );
                $edit_arr['fields'] = array(
                    'size' => array( 'raw_field' => true, 'value' => 'size + '.$result_file_arr['size'] ),
                );

                if( !($result_arr = $this->edit( $result_file_arr['result_id'], $edit_arr )) )
                    PHS_Logger::logf( '!!! Error updating backup result when deleting backup result file. Size missing from backup result #'.$result_file_arr['result_id'].' ['.$result_file_arr['size'].']', $backup_plugin::LOG_CHANNEL );
            }

            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error deleting result file.' ) );
            return false;
        }

        if( @file_exists( $result_file_arr['file'] ) )
            @unlink( $result_file_arr['file'] );

        return $result_file_arr;
    }

    public function link_result_files_to_result( $result_data, $files_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !is_array( $files_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No files provided to link to backup result.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load backup rules model.' ) );
            return false;
        }

        if( empty( $result_data )
         or !($result_arr = $this->data_to_array( $result_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Backup result not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'backup_results_files' ) ))
         or !($result_files_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Invalid flow parameters.' ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        $return_arr = array(
            'files' => array(),
            'total_size' => 0,
        );

        if( empty( $files_arr ) )
        {
            // Unlink all roles...
            if( !$this->unlink_all_result_files_for_result( $result_arr ) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking result files from backup result.' ) );
                return false;
            }

            return $return_arr;
        } else
        {
            if( !($existing_files = $this->get_result_files( $result_arr['id'] )) )
                $existing_files = array();

            $total_file_size = 0;
            foreach( $files_arr as $file_arr )
            {
                if( empty( $file_arr ) or !@is_array( $file_arr )
                 or empty( $file_arr['file'] ) )
                    continue;

                if( !@file_exists( $file_arr['file'] )
                 or !@is_file( $file_arr['file'] )
                 or !($file_size = @filesize( $file_arr['file'] )) )
                    $file_size = 0;

                if( empty( $file_arr['id'] )
                 or empty( $existing_files[$file_arr['id']] ) )
                {
                    if( isset( $file_arr['id'] ) )
                        unset( $file_arr['id'] );

                    if( empty( $file_arr['type'] )
                     or !$this->valid_file_type( $file_arr['type'] ) )
                        $file_arr['type'] = self::FILE_TYPE_RESULT;

                    if( empty( $file_arr['target_id'] )
                     or !$rules_model->valid_target( $file_arr['target_id'] ) )
                        $file_arr['target_id'] = $rules_model::BACKUP_TARGET_UPLOADS;

                    // insert
                    $insert_arr = $flow_params;
                    $insert_arr['fields'] = $file_arr;
                    $insert_arr['fields']['result_id'] = $result_arr['id'];
                    $insert_arr['fields']['size'] = $file_size;

                    $total_file_size += $file_size;

                    if( !($file_record = $this->insert( $insert_arr )) )
                    {
                        // undo file inserts
                        if( !empty( $return_arr['files'] ) and is_array( $return_arr['files'] ) )
                        {
                            foreach( $return_arr['files'] as $file_id => $new_file_arr )
                            {
                                if( $this->record_is_new( $new_file_arr ) )
                                    $this->hard_delete( $new_file_arr );
                            }
                        }

                        if( !$this->has_error() )
                            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error inserting result file in database.' ) );
                        return false;
                    }

                    $return_arr['files'][$file_record['id']] = $file_record;
                } else
                {
                    // edit
                    unset( $file_arr['id'] );

                    if( isset( $file_arr['type'] )
                    and !$this->valid_file_type( $file_arr['type'] ) )
                        $file_arr['type'] = self::FILE_TYPE_RESULT;

                    if( isset( $file_arr['target_id'] )
                    and !$rules_model->valid_target( $file_arr['target_id'] ) )
                        $file_arr['target_id'] = $rules_model::BACKUP_TARGET_UPLOADS;

                    $edit_arr = $flow_params;
                    $edit_arr['fields'] = $file_arr;
                    $edit_arr['fields']['result_id'] = $result_arr['id'];
                    $edit_arr['fields']['size'] = $file_size;

                    $total_file_size += $file_size;

                    if( !($file_record = $this->edit( $existing_files[$file_arr['id']], $edit_arr )) )
                    {
                        // undo file inserts
                        if( !empty( $return_arr['files'] ) and is_array( $return_arr['files'] ) )
                        {
                            foreach( $return_arr['files'] as $file_id => $new_file_arr )
                            {
                                if( $this->record_is_new( $new_file_arr ) )
                                    $this->hard_delete( $new_file_arr );
                            }
                        }

                        if( !$this->has_error() )
                            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error editing result file in database.' ) );
                        return false;
                    }

                    $return_arr['files'][$file_record['id']] = $file_record;

                    unset( $existing_files[$file_arr['id']] );
                }
            }

            if( !empty( $existing_files ) and is_array( $existing_files ) )
            {
                $unlink_params = array();
                $unlink_params['update_result'] = false;

                $got_error_on_file_delete = false;

                foreach( $existing_files as $file_id => $file_arr )
                {
                    // Try to delete as much as we can...
                    if( !$this->unlink_result_file( $file_arr, $unlink_params ) )
                        $got_error_on_file_delete = true;
                }

                if( $got_error_on_file_delete )
                    return false;
            }
        }

        $return_arr['total_size'] = $total_file_size;

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_backup_results( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['run_dir'] )
         or !@is_dir( $params['fields']['run_dir'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide running backup rule directory.' ) );
            return false;
        }

        if( empty( $params['fields']['rule_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide backup result rule.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        if( empty( $params['fields']['status'] )
         or !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_PENDING;

        $params['fields']['cdate'] = $cdate;

        if( empty( $params['fields']['status_date'] )
         or empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];
        else
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        if( empty( $params['{result_files}'] ) or !is_array( $params['{result_files}'] ) )
            $params['{result_files}'] = false;

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
    protected function insert_after_backup_results( $insert_arr, $params )
    {
        $insert_arr['{result_files}'] = array();

        if( !empty( $params['{result_files}'] ) and is_array( $params['{result_files}'] ) )
        {
            if( !($result_files_arr = $this->link_result_files_to_result( $insert_arr, $params['{result_files}'] )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_INSERT, $this->_pt( 'Error linking result files to backup result.' ) );

                return false;
            }

            if( !empty( $result_files_arr['files'] ) and is_array( $result_files_arr['files'] ) )
            {
                $insert_arr['{result_files}'] = $result_files_arr['files'];

                if( empty( $result_files_arr['total_size'] ) )
                    $result_files_arr['total_size'] = 0;

                if( $result_files_arr['total_size'] != $insert_arr['size'] )
                {
                    if( !($flow_params = $this->fetch_default_flow_params( $params ))
                     or !($table_name = $this->get_flow_table_name( $flow_params ))
                     or !db_query( 'UPDATE `'.$table_name.'` SET size = \''.$result_files_arr['total_size'].'\' WHERE id = \''.$insert_arr['id'].'\'', $flow_params['db_connection'] ) )
                    {
                        if( !$this->has_error() )
                            $this->set_error( self::ERR_INSERT, $this->_pt( 'Error linking result files to backup result.' ) );

                        return false;
                    }

                    $insert_arr['size'] = $result_files_arr['total_size'];
                }
            }
        }

        return $insert_arr;
    }

    protected function get_edit_prepare_params_backup_results( $existing_arr, $params )
    {

        if( isset( $params['fields']['run_dir'] )
        and (empty( $params['fields']['run_dir'] )
                or !@is_dir( $params['fields']['run_dir'] )) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide running backup rule directory.' ) );
            return false;
        }

        if( isset( $params['fields']['rule_id'] )
        and empty( $params['fields']['rule_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide backup result rule.' ) );
            return false;
        }

        if( isset( $params['fields']['status'] ) and !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide valid status for backup rule.' ) );
            return false;
        }

        if( !empty( $params['fields']['status'] )
        and (empty( $params['fields']['status_date'] ) or empty_db_date( $params['fields']['status_date'] ))
        and $params['fields']['status'] != $existing_arr['status'] )
            $params['fields']['status_date'] = date( self::DATETIME_DB );

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        if( empty( $params['{result_files}'] ) or !is_array( $params['{result_files}'] ) )
            $params['{result_files}'] = false;

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
    protected function edit_after_backup_results( $existing_data, $edit_arr, $params )
    {
        if( !empty( $params['{result_files}'] ) and is_array( $params['{result_files}'] ) )
        {
            if( !($result_files_arr = $this->link_result_files_to_result( $existing_data, $params['{result_files}'] )) )
            {
                // update result size based on what we have left as result files in database
                $this->update_result_size( $existing_data['id'] );

                if( !$this->has_error() )
                    $this->set_error( self::ERR_EDIT, $this->_pt( 'Error linking result files to backup result.' ) );

                return false;
            }

            if( !empty( $result_files_arr['files'] ) and is_array( $result_files_arr['files'] ) )
            {
                $existing_data['{result_files}'] = $result_files_arr['files'];

                if( empty( $result_files_arr['total_size'] ) )
                    $result_files_arr['total_size'] = 0;

                if( $result_files_arr['total_size'] != $existing_data['size'] )
                {
                    if( !($flow_params = $this->fetch_default_flow_params( $params ))
                     or !($table_name = $this->get_flow_table_name( $flow_params ))
                     or !db_query( 'UPDATE `'.$table_name.'` SET size = \''.$result_files_arr['total_size'].'\' WHERE id = \''.$existing_data['id'].'\'', $flow_params['db_connection'] ) )
                    {
                        if( !$this->has_error() )
                            $this->set_error( self::ERR_EDIT, $this->_pt( 'Error linking result files to backup result.' ) );

                        return false;
                    }

                    $existing_data['size'] = $result_files_arr['total_size'];
                }
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
    protected function get_insert_prepare_params_backup_results_files( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' )) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Couldn\'t load backup rules model.' ) );
            return false;
        }

        if( empty( $params['fields']['result_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result id for result file.' ) );
            return false;
        }

        if( empty( $params['fields']['file'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result file.' ) );
            return false;
        }

        if( empty( $params['fields']['target_id'] )
         or !$rules_model->valid_target( $params['fields']['target_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result file target id.' ) );
            return false;
        }

        if( empty( $params['fields']['type'] )
         or !$this->valid_file_type( $params['fields']['type'] ) )
            $params['fields']['type'] = self::FILE_TYPE_RESULT;

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
    protected function get_edit_prepare_params_backup_results_files( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;


        if( isset( $params['fields']['result_id'] ) and empty( $params['fields']['result_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result id for result file.' ) );
            return false;
        }

        if( isset( $params['fields']['file'] ) and empty( $params['fields']['file'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result file.' ) );
            return false;
        }

        if( isset( $params['fields']['target_id'] ) )
        {
            /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
            if( !($rules_model = PHS::load_model( 'rules', 'backup' )) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Couldn\'t load backup rules model.' ) );
                return false;
            }

            if( empty( $params['fields']['target_id'] )
             or !$rules_model->valid_target( $params['fields']['target_id'] ) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result file target id.' ) );
                return false;
            }
        }

        if( empty( $params['fields']['type'] )
         or !$this->valid_file_type( $params['fields']['type'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a backup result file type.' ) );
            return false;
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_count_list_common_params( $params = false )
    {
        if( !empty( $params['flags'] ) and is_array( $params['flags'] ) )
        {
            $model_table = $this->get_flow_table_name( $params );
            foreach( $params['flags'] as $flag )
            {
                switch( $flag )
                {
                    // for results listing
                    case 'include_rule_details':

                        if( $params['table_name'] == 'backup_results' )
                        {
                            if( !($rules_model = PHS::load_model( 'rules', 'backup' ))
                             or !($rules_table = $rules_model->get_flow_table_name( array( 'table_name' => 'backup_rules' ))) )
                                continue 2;

                            $params['db_fields'] .= ', `'.$rules_table.'`.title AS backup_rules_title, '.
                                ' `'.$rules_table.'`.hour AS backup_rules_hour, '.
                                ' `'.$rules_table.'`.target AS backup_rules_target, '.
                                ' `'.$rules_table.'`.location AS backup_rules_location, '.
                                ' `'.$rules_table.'`.status AS backup_rules_status, '.
                                ' `'.$rules_table.'`.status_date AS backup_rules_status_date, '.
                                ' `'.$rules_table.'`.last_run AS backup_rules_last_run, '.
                                ' `'.$rules_table.'`.cdate AS backup_rules_cdate ';
                            $params['join_sql'] .= ' LEFT JOIN `'.$rules_table.'` ON `'.$rules_table.'`.id = `'.$model_table.'`.rule_id ';
                        }
                    break;

                    // for result files listing
                    case 'include_result_details':

                        if( $params['table_name'] == 'backup_results_files' )
                        {
                            if( !($results_table = $this->get_flow_table_name( array( 'table_name' => 'backup_results' ))) )
                                continue 2;

                            $params['db_fields'] .= ', `'.$results_table.'`.rule_id AS backup_results_rule_id, '.
                                ' `'.$results_table.'`.run_dir AS backup_results_run_dir, '.
                                ' `'.$results_table.'`.size AS backup_results_size, '.
                                ' `'.$results_table.'`.status AS backup_results_status, '.
                                ' `'.$results_table.'`.status_date AS backup_results_status_date, '.
                                ' `'.$results_table.'`.cdate AS backup_results_cdate ';
                            $params['join_sql'] .= ' LEFT JOIN `'.$results_table.'` ON `'.$results_table.'`.id = `'.$model_table.'`.result_id ';
                        }
                    break;
                }
            }
        }

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
            case 'backup_results':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'rule_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'run_dir' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Directory where backup rule runs',
                    ),
                    'size' => array(
                        'type' => self::FTYPE_BIGINT,
                    ),
                    'copied' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                        'comment' => 'Date when results were copied',
                    ),
                    'copy_error' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;

            case 'backup_results_files':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'result_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'file' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Full path to resulting file',
                    ),
                    'size' => array(
                        'type' => self::FTYPE_BIGINT,
                    ),
                    'target_id' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'What was the target for this file',
                    ),
                    'type' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'Log file, result file',
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
