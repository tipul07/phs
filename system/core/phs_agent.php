<?php

namespace phs;

use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Plugin;

//! @version 1.00

class PHS_Agent extends PHS_Registry
{
    const ERR_DB_INSERT = 30000, ERR_COMMAND = 30001, ERR_RUN_JOB = 30002, ERR_JOB_DB = 30003, ERR_JOB_STALLING = 30004,
          ERR_AVAILABLE_ACTIONS = 30005;

    const DATA_AGENT_KEY = 'bg_agent_data';

    public static function get_agent_routes()
    {
        self::st_reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            self::st_set_error( self::ERR_AVAILABLE_ACTIONS, self::_t( 'Couldn\'t load plugins model.' ) );
            return false;
        }

        if( !($plugins_list = $plugins_model->cache_all_dir_details()) )
            $plugins_list = array();

        $available_plugins_arr = array();

        if( ($agent_controllers = self::get_agent_available_controllers())
        and ($agent_actions = self::get_agent_available_actions()) )
        {
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['info'] = PHS_Plugin::core_plugin_details_fields();
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['controllers'] = $agent_controllers;
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['actions'] = $agent_actions;
            $available_plugins_arr[PHS_Instantiable::CORE_PLUGIN]['instance'] = false;
        }

        /** @var \phs\libraries\PHS_Plugin $plugin_instance */
        foreach( $plugins_list as $plugin_name => $plugin_instance )
        {
            if( !($plugin_info_arr = $plugin_instance->get_plugin_info())
             or empty( $plugin_info_arr['is_installed'] )
             or !($agent_controllers = self::get_agent_available_controllers( $plugin_name ))
             or !($agent_actions = self::get_agent_available_actions( $plugin_name )))
                continue;

            $available_plugins_arr[$plugin_name]['info'] = $plugin_info_arr;
            $available_plugins_arr[$plugin_name]['controllers'] = $agent_controllers;
            $available_plugins_arr[$plugin_name]['actions'] = $agent_actions;
            $available_plugins_arr[$plugin_name]['instance'] = $plugin_instance;
        }

        return $available_plugins_arr;
    }

    public static function get_agent_available_controllers( $plugin = false )
    {
        self::st_reset_error();

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        if( !($controller_names = PHS::get_plugin_scripts_from_dir( $plugin, PHS_Instantiable::INSTANCE_TYPE_CONTROLLER ))
         or !is_array( $controller_names ) )
            return array();

        $available_controllers = array();
        foreach( $controller_names as $controller_name )
        {
            /** @var \phs\libraries\PHS_Controller $controller_obj */
            if( !($controller_obj = PHS::load_controller( $controller_name, $plugin ))
             or !$controller_obj->scope_is_allowed( PHS_Scope::SCOPE_AGENT ) )
                continue;

            $available_controllers[$controller_name] = $controller_obj;
        }

        return $available_controllers;
    }

    public static function get_agent_available_actions( $plugin = false )
    {
        self::st_reset_error();

        if( $plugin == PHS_Instantiable::CORE_PLUGIN )
            $plugin = false;

        if( !($action_names = PHS::get_plugin_scripts_from_dir( $plugin, PHS_Instantiable::INSTANCE_TYPE_ACTION ))
         or !is_array( $action_names ) )
            return array();

        $available_actions = array();
        foreach( $action_names as $action_name )
        {
            /** @var \phs\libraries\PHS_Action $action_obj */
            if( !($action_obj = PHS::load_action( $action_name, $plugin ))
             or !$action_obj->scope_is_allowed( PHS_Scope::SCOPE_AGENT ) )
                continue;

            $available_actions[$action_name] = $action_obj;
        }

        return $available_actions;
    }

    public static function current_job_data( $job_data = null )
    {
        if( $job_data === null )
            return self::get_data( self::DATA_AGENT_KEY );

        return self::set_data( self::DATA_AGENT_KEY, $job_data );
    }

    public static function get_current_job_parameters()
    {
        if( !($job_arr = self::current_job_data())
         or !is_array( $job_arr )
         or empty( $job_arr['params'] )
         or !($job_params_arr = @json_decode( $job_arr['params'], true )) )
            return array();

        return $job_params_arr;
    }

    public static function remove_job_handler( $handler )
    {
        self::st_reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        if( empty( $handler ) )
        {
            self::st_set_error( self::ERR_JOB_DB, self::_t( 'Please provide a job handler.' ) );
            return false;
        }

        if( !($existing_job = $agent_jobs_model->get_details_fields( array( 'handler' => $handler ) )) )
            return true;

        $remove_params = array();
        $remove_params['agent_jobs_model'] = $agent_jobs_model;

        return self::remove_job( $existing_job, $remove_params );
    }

    public static function remove_job( $job_data, $params = false )
    {
        self::st_reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( empty( $params['agent_jobs_model'] ) )
            $agent_jobs_model = false;
        else
            $agent_jobs_model = $params['agent_jobs_model'];

        if( empty( $agent_jobs_model )
        and !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        if( empty( $job_data )
         or !($job_arr = $agent_jobs_model->data_to_array( $job_data )) )
        {
            self::st_set_error( self::ERR_JOB_DB, self::_t( 'Couldn\'t load agent job details from database.' ) );
            return false;
        }

        if( !$agent_jobs_model->hard_delete( $job_arr ) )
        {
            if( $agent_jobs_model->has_error() )
                self::st_copy_error( $agent_jobs_model, self::ERR_JOB_DB );
            else
                self::st_set_error( self::ERR_JOB_DB, self::_t( 'Couldn\'t delete agent job from database.' ) );

            return false;
        }

        return $job_arr;
    }

    public static function add_job( $handler, $route, $once_every_seconds, $params = false, $extra = false )
    {
        // We don't use here PHS::route_exists() because route_exists() will instantiate plugin, controller and action and if they have errors
        // launching script will die...
        self::st_reset_error();

        if( empty( $handler ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Please provide a handler for this agent job.' ) );
            return false;
        }

        $once_every_seconds = intval( $once_every_seconds );

        $route_parts = false;
        if( is_string( $route )
        and !($route_parts = PHS::parse_route( $route )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Invalid route for agent job.' ) );

            return false;
        }

        if( is_array( $route ) )
            $route_parts = $route;

        if( empty( $route_parts ) or !is_array( $route_parts ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Invalid route for agent job.' ) );

            return false;
        }

        if( !isset( $route_parts['plugin'] ) )
            $route_parts['plugin'] = false;
        if( !isset( $route_parts['controller'] ) )
            $route_parts['controller'] = false;
        if( !isset( $route_parts['action'] ) )
            $route_parts['action'] = false;

        if( !($cleaned_route = PHS::route_from_parts(
                                        array(
                                            'p' => $route_parts['plugin'],
                                            'c' => $route_parts['controller'],
                                            'a' => $route_parts['action'],
                                        )
             )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Invalid route for agent job.' ) );

            return false;
        }

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( !empty( $extra['title'] ) )
            $extra['title'] = trim( $extra['title'] );
        else
            $extra['title'] = '';

        if( !isset( $extra['run_async'] ) )
            $extra['run_async'] = true;
        else
            $extra['run_async'] = (!empty( $extra['run_async'] )?true:false);

        if( !isset( $extra['active'] ) )
            $extra['active'] = 1;
        else
            $extra['active'] = (!empty( $extra['active'] )?1:0);

        // This tells if job was added by plugin or is an user defined job
        if( empty( $extra['plugin'] ) or !is_string( $extra['plugin'] ) )
            $extra['plugin'] = '';
        else
            $extra['plugin'] = trim( $extra['plugin'] );

        if( !is_array( $params ) )
            $params = array();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        if( ($existing_job = $agent_jobs_model->get_details_fields( array( 'handler' => $handler ) )) )
        {
            // At this point it doesn't matter if job is currently running as fields edited won't affect running flow of tasks...
            $edit_arr = array();
            $edit_arr['title'] = $extra['title'];
            $edit_arr['handler'] = $handler;
            $edit_arr['route'] = $cleaned_route;
            $edit_arr['params'] = (!empty( $params )?@json_encode( $params ):null);
            $edit_arr['timed_seconds'] = $once_every_seconds;
            $edit_arr['run_async'] = ($extra['run_async']?1:0);
            $edit_arr['plugin'] = $extra['plugin'];

            if( !($job_arr = $agent_jobs_model->edit( $existing_job, array( 'fields' => $edit_arr ) )) )
            {
                if( $agent_jobs_model->has_error() )
                    self::st_copy_error( $agent_jobs_model );
                else
                    self::st_set_error( self::ERR_DB_INSERT, self::_t( 'Couldn\'t save agent job details in database. Please try again.' ) );

                return false;
            }
        } else
        {
            $insert_arr = array();
            $insert_arr['title'] = $extra['title'];
            $insert_arr['handler'] = $handler;
            $insert_arr['pid'] = 0;
            $insert_arr['route'] = $cleaned_route;
            $insert_arr['params'] = (!empty( $params )?@json_encode( $params ):null);
            $insert_arr['timed_seconds'] = $once_every_seconds;
            $insert_arr['run_async'] = ($extra['run_async']?1:0);
            $insert_arr['status'] = ($extra['active']?$agent_jobs_model::STATUS_ACTIVE:$agent_jobs_model::STATUS_INACTIVE);
            $insert_arr['plugin'] = $extra['plugin'];

            if( !($job_arr = $agent_jobs_model->insert( array( 'fields' => $insert_arr ) ))
             or empty( $job_arr['id'] ) )
            {
                if( $agent_jobs_model->has_error() )
                    self::st_copy_error( $agent_jobs_model );
                else
                    self::st_set_error( self::ERR_DB_INSERT, self::_t( 'Couldn\'t save agent job details in database. Please try again.' ) );

                return false;
            }
        }

        return $job_arr;
    }

    private function run_job( $job_data, $extra = false )
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        if( empty( $job_data )
         or !($job_arr = $agent_jobs_model->data_to_array( $job_data )) )
        {
            $this->set_error( self::ERR_RUN_JOB, self::_t( 'Couldn\'t load agent jobs details from database.' ) );
            return false;
        }

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $extra['return_command'] ) )
            $extra['return_command'] = false;
        if( empty( $extra['force_run'] ) )
            $extra['force_run'] = false;
        else
            $extra['force_run'] = true;

        if( !$agent_jobs_model->job_is_active( $job_arr ) )
        {
            $this->set_error( self::ERR_RUN_JOB, self::_t( 'Agent job not active.' ) );
            return false;
        }

        $cmd_extra = array();
        $cmd_extra['async_task'] = ($job_arr['run_async']?true:false);
        $cmd_extra['agent_jobs_model'] = $agent_jobs_model;

        if( !($cmd_parts = $this->get_job_command( $job_arr, $cmd_extra ))
         or empty( $cmd_parts['cmd'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_COMMAND, self::_t( 'Couldn\'t get agent job command.' ) );

            return false;
        }

        // Agent job cannot be edited as it will be managed by PHS_Agent class...
        if( !empty( $extra['return_command'] ) )
        {
            return array(
                'cmd' => $cmd_parts['cmd'],
            );
        }

        PHS_Logger::logf( 'Launching agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']', PHS_Logger::TYPE_AGENT );

        if( PHS::st_debugging_mode() )
            PHS_Logger::logf( 'Command ['.$cmd_parts['cmd'].']', PHS_Logger::TYPE_AGENT );

        return (@system( $cmd_parts['cmd'] ) !== false );
    }

    public function get_job_command( $job_data, $extra = false )
    {
        $this->reset_error();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !empty( $extra['agent_jobs_model'] ) )
            $agent_jobs_model = $extra['agent_jobs_model'];
        else
            $agent_jobs_model = PHS::load_model( 'agent_jobs' );

        if( empty( $job_data )
         or empty( $agent_jobs_model )
         or !($job_arr = $agent_jobs_model->data_to_array( $job_data )) )
        {
            if( $agent_jobs_model->has_error() )
                $this->copy_error( $agent_jobs_model, self::ERR_COMMAND );
            else
                $this->set_error( self::ERR_COMMAND, self::_t( 'Couldn\'t get background jobs details.' ) );

            return false;
        }

        if( !isset( $extra['async_task'] ) )
            $extra['async_task'] = (!empty( $job_arr['run_async'] )?true:false);
        else
            $extra['async_task'] = (!empty( $extra['async_task'] )?true:false);

        $pub_key = microtime( true );

        $clean_cmd = PHP_EXEC.' '.PHS::get_agent_path().' '.PHS_crypt::quick_encode( $job_arr['id'].'::'.md5( $job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'] ) ).'::'.$pub_key;

        if( strtolower( substr( PHP_OS, 0, 3 ) ) == 'win' )
        {
            // launching background task under windows
            $cmd = 'start '.(!empty($extra['async_task'])?' /B ':'').$clean_cmd;
        } else
        {
            $cmd = $clean_cmd . ' 2>/dev/null >&- <&- >/dev/null';
            if( !empty($extra['async_task']) )
                $cmd .= ' &';
        }

        return array(
            'cmd' => $cmd,
            'pub_key' => $pub_key,
        );
    }

    public static function suspend_agent_jobs( $plugin )
    {
        self::st_reset_error();

        if( !($plugin_name = PHS_Instantiable::safe_escape_plugin_name( $plugin )) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Invalid plugin name.' ) );
            return false;
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        if( !($flow_params = $agent_jobs_model->fetch_default_flow_params()) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t obtain agent jobs flow parameters.' ) );
            return false;
        }

        if( !db_query( 'UPDATE `'.$agent_jobs_model->get_flow_table_name( $flow_params ).'`'.
                       ' SET status = \''.$agent_jobs_model::STATUS_SUSPENDED.'\' '.
                       ' WHERE '.
                       ' plugin = \''.prepare_data( $plugin ).'\' AND status = \''.$agent_jobs_model::STATUS_ACTIVE.'\'', $flow_params['db_connection'] ) )
        {
            self::st_set_error( self::ERR_JOB_DB, self::_t( 'Error running query to suspend agent jobs for provided plugin.' ) );
            return false;
        }

        return true;
    }

    public static function unsuspend_agent_jobs( $plugin )
    {
        self::st_reset_error();

        if( !($plugin_name = PHS_Instantiable::safe_escape_plugin_name( $plugin )) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Invalid plugin name.' ) );
            return false;
        }

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        if( !($flow_params = $agent_jobs_model->fetch_default_flow_params()) )
        {
            self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t obtain agent jobs flow parameters.' ) );
            return false;
        }

        if( !db_query( 'UPDATE `'.$agent_jobs_model->get_flow_table_name( $flow_params ).'`'.
                       ' SET status = \''.$agent_jobs_model::STATUS_ACTIVE.'\' '.
                       ' WHERE '.
                       ' plugin = \''.prepare_data( $plugin ).'\' AND status = \''.$agent_jobs_model::STATUS_SUSPENDED.'\'', $flow_params['db_connection'] ) )
        {
            self::st_set_error( self::ERR_JOB_DB, self::_t( 'Error running query to re-activate agent jobs for provided plugin.' ) );
            return false;
        }

        return true;
    }

    public function check_agent_jobs()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        $return_arr = array();
        $return_arr['jobs_count'] = 0;
        $return_arr['jobs_errors'] = 0;
        $return_arr['jobs_success'] = 0;

        $list_arr = $agent_jobs_model->fetch_default_flow_params();
        $list_arr['fields']['is_running'] = array( 'check' => 'IS', 'raw_value' => 'NULL' );
        $list_arr['fields']['timed_action'] = array( 'check' => '<=', 'value' => date( $agent_jobs_model::DATETIME_DB ) );
        $list_arr['fields']['status'] = $agent_jobs_model::STATUS_ACTIVE;
        $list_arr['order_by'] = 'run_async DESC';

        if( ($jobs_list = $agent_jobs_model->get_list( $list_arr )) === false
         or !is_array( $jobs_list ) )
            return $return_arr;

        $return_arr['jobs_count'] = count( $jobs_list );

        foreach( $jobs_list as $job_arr )
        {
            if( $this->run_job( $job_arr ) )
                $return_arr['jobs_success']++;

            else
            {
                $return_arr['jobs_errors']++;

                if( $this->has_error() )
                    $error_msg = $this->get_error_message();
                else
                    $error_msg = 'Error launching agent job: [#'.$job_arr['id'].']['.$job_arr['route'].']';

                PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_AGENT );
            }
        }

        return $return_arr;
    }

    public static function bg_validate_input( $input_str )
    {
        if( empty( $input_str )
         or @strstr( $input_str, '::' ) === false
         or !($parts_arr = explode( '::', $input_str, 2 ))
         or empty( $parts_arr[0] ) or empty( $parts_arr[1] ) )
        {
            PHS_Logger::logf( 'Invalid input', PHS_Logger::TYPE_AGENT );
            return false;
        }

        $crypted_data = $parts_arr[0];
        $pub_key = $parts_arr[1];

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($decrypted_data = PHS_crypt::quick_decode( $crypted_data ))
         or !($decrypted_parts = explode( '::', $decrypted_data, 2 ))
         or empty( $decrypted_parts[0] ) or empty( $decrypted_parts[1] )
         or !($job_id = intval( $decrypted_parts[0] ))
         or !($agent_jobs_model = PHS::load_model( 'agent_jobs' ))
         or !($job_arr = $agent_jobs_model->get_details( $job_id ))
         or $decrypted_parts[1] != md5( $job_arr['route'].':'.$pub_key.':'.$job_arr['cdate'] ) )
        {
            PHS_Logger::logf( 'Input validation failed', PHS_Logger::TYPE_AGENT );
            return false;
        }

        return array(
            'job_data' => $job_arr,
            'pub_key' => $pub_key,
            'agent_jobs_model' => $agent_jobs_model,
        );
    }

    public static function get_stalling_minutes()
    {
        static $stalling_minutes = false;

        if( $stalling_minutes !== false )
            return $stalling_minutes;

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' ))
         or !($stalling_minutes = $agent_jobs_model->get_stalling_minutes()) )
            $stalling_minutes = 0;

        return $stalling_minutes;
    }

    public static function refresh_current_job( $extra = false )
    {
        self::st_reset_error();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !empty( $extra['agent_jobs_model'] ) )
            $agent_jobs_model = $extra['agent_jobs_model'];
        else
            $agent_jobs_model = PHS::load_model( 'agent_jobs' );

        if( empty( $agent_jobs_model )
         or !($job_data = self::current_job_data())
         or !($job_arr = $agent_jobs_model->data_to_array( $job_data )) )
        {
            if( $agent_jobs_model->has_error() )
                self::st_copy_error( $agent_jobs_model );

            if( !self::st_has_error() )
                self::st_set_error( self::ERR_JOB_DB, self::_t( 'Couldn\'t get agent job details.' ) );

            return false;
        }

        if( !($new_job = $agent_jobs_model->refresh_job( $job_arr )) )
        {
            if( $agent_jobs_model->has_error() )
                self::st_copy_error( $agent_jobs_model );

            if( !self::st_has_error() )
                self::st_set_error( self::ERR_JOB_DB, self::_t( 'Couldn\'t refresh agent job details.' ) );

            return false;
        }

        return $new_job;
   }

    public static function bg_run_job( $job_data, $extra = false )
    {
        self::st_reset_error();

        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();

        if( empty( $extra['force_run'] ) )
            $extra['force_run'] = false;
        else
            $extra['force_run'] = true;

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !empty( $extra['agent_jobs_model'] ) )
            $agent_jobs_model = $extra['agent_jobs_model'];
        else
            $agent_jobs_model = PHS::load_model( 'agent_jobs' );

        if( empty( $job_data )
         or empty( $agent_jobs_model )
         or !($job_arr = $agent_jobs_model->data_to_array( $job_data )) )
        {
            if( $agent_jobs_model->has_error() )
                self::st_copy_error( $agent_jobs_model );

            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Couldn\'t get agent job details.' ) );

            return false;
        }

        self::current_job_data( $job_arr );

        if( $agent_jobs_model->job_is_running( $job_arr ) )
        {
            if( ($job_stalling = $agent_jobs_model->job_is_stalling( $job_arr )) === null )
            {
                if( $agent_jobs_model->has_error() )
                    self::st_copy_error( $agent_jobs_model );

                return false;
            }

            if( empty( $job_stalling ) )
            {
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Agent job already running.' ) );
                return false;
            } elseif( !empty( $job_stalling ) and empty( $extra['force_run'] ) )
            {
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Agent job seems to stall. Run not told to force execution.' ) );
                return false;
            }
        }

        if( !($new_job_arr = $agent_jobs_model->start_job( $job_arr )) )
        {
            if( $agent_jobs_model->has_error() )
                self::st_copy_error( $agent_jobs_model );

            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Couldn\'t save background jobs details in database.' ) );

            return false;
        }

        $job_arr = $new_job_arr;

        self::current_job_data( $job_arr );

        if( !PHS_Scope::current_scope( PHS_Scope::SCOPE_AGENT )
         or !PHS::set_route( $job_arr['route'] ) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Error preparing environment.' ) );

            $error_arr = self::st_get_error();

            $error_params = array();
            $error_params['last_error'] = self::st_get_error_message();

            $agent_jobs_model->stop_job( $job_arr, $error_params );

            self::st_copy_error_from_array( $error_arr );

            return false;
        }

        $execution_params = array();
        $execution_params['die_on_error'] = false;

        if( !($action_result = PHS::execute_route( $execution_params )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_RUN_JOB, self::_t( 'Error executing route.' ) );

            $error_arr = self::st_get_error();

            $error_params = array();
            $error_params['last_error'] = self::st_get_error_message();

            $agent_jobs_model->stop_job( $job_arr, $error_params );

            self::st_copy_error_from_array( $error_arr );

            return false;
        }

        $agent_jobs_model->stop_job( $job_arr );

        return $action_result;
    }
}

