<?php

namespace phs\libraries;

use phs\PHS;
use \phs\system\core\models\PHS_Model_Plugins;

abstract class PHS_Plugin extends PHS_Has_db_settings
{
    const ERR_MODEL = 50000, ERR_INSTALL = 50001, ERR_UNINSTALL = 50002, ERR_CHANGES = 50003, ERR_LIBRARY = 50004;

    const SIGNAL_INSTALL = 'phs_plugin_install', SIGNAL_UNINSTALL = 'phs_plugin_uninstall',
          SIGNAL_UPDATE = 'phs_plugin_update', SIGNAL_FORCE_INSTALL = 'phs_plugin_force_install';

    const LIBRARIES_DIR = 'libraries';

    private $_libraries_instances = array();
    // Plugin details as defined in default_plugin_details_fields() method
    private $_plugin_details = array();

    /**
     * @return array An array of strings which are the models used by this plugin
     */
    abstract public function get_models();

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    abstract public function get_plugin_details();

    /**
     * @return string Returns version of plugin
     */
    abstract public function get_plugin_version();

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_PLUGIN;
    }

    function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        if( !$this->signal_defined( self::SIGNAL_INSTALL ) )
        {
            $signal_defaults            = array();
            $signal_defaults['version'] = '';

            $this->define_signal( self::SIGNAL_INSTALL, $signal_defaults );
        }

        if( !$this->signal_defined( self::SIGNAL_UNINSTALL ) )
        {
            $this->define_signal( self::SIGNAL_UNINSTALL );
        }

        if( !$this->signal_defined( self::SIGNAL_UPDATE ) )
        {
            $signal_defaults                = array();
            $signal_defaults['old_version'] = '';
            $signal_defaults['new_version'] = '';

            $this->define_signal( self::SIGNAL_UPDATE, $signal_defaults );
        }

        if( !$this->signal_defined( self::SIGNAL_FORCE_INSTALL ) )
        {
            $signal_defaults = array();

            $this->define_signal( self::SIGNAL_FORCE_INSTALL, $signal_defaults );
        }
    }

    public static function safe_escape_library_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return strtolower( $name );
    }

    public function get_library_full_path( $library )
    {
        $library = self::safe_escape_library_name( $library );
        if( empty( $library )
         or !($dir_path = $this->instance_plugin_path())
         or !@is_dir( $dir_path.self::LIBRARIES_DIR )
         or !@file_exists( $dir_path.self::LIBRARIES_DIR.'/'.$library.'.php' ) )
            return false;

        return $dir_path.self::LIBRARIES_DIR.'/'.$library.'.php';
    }

    public function load_library( $library, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['full_class_name'] ) )
            $params['full_class_name'] = $library;
        if( empty( $params['init_params'] ) )
            $params['init_params'] = false;
        if( empty( $params['as_singleton'] ) )
            $params['as_singleton'] = true;

        if( !($library = self::safe_escape_library_name( $library )) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load library from plugin [%s]', $this->get_plugin_instance() ) );
            return false;
        }

        if( !empty( $params['as_singleton'] )
        and !empty( $this->_libraries_instances[$library] ) )
            return $this->_libraries_instances[$library];

        if( !($file_path = $this->get_library_full_path( $library )) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load library [%s] from plugin [%s]', $library, $this->get_plugin_instance() ) );
            return false;
        }

        ob_start();
        include_once( $file_path );
        ob_get_clean();

        if( !@class_exists( $params['full_class_name'], false ) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t instantiate library class for library [%s] from plugin [%s]', $library, $this->get_plugin_instance() ) );
            return false;
        }

        if( empty( $params['init_params'] ) )
            $library_instance = new $params['full_class_name']();
        else
            $library_instance = new $params['full_class_name']( $params['init_params'] );

        if( !empty( $params['as_singleton'] ) )
            $this->_libraries_instances[$library] = $library_instance;

        return $library_instance;
    }

    public function email_template_resource_from_file( $file )
    {
        return array(
            'file' => $file,
            'extra_paths' => array(
                PHS::relative_path( $this->instance_plugin_email_templates_path() ) => PHS::relative_url( $this->instance_plugin_email_templates_www() ),
            ),
        );
    }

    public function template_resource_from_file( $file )
    {
        return array(
            'file' => $file,
            'extra_paths' => array(
                PHS::relative_path( $this->instance_plugin_templates_path() ) => PHS::relative_url( $this->instance_plugin_templates_www() ),
            ),
        );
    }

    public function plugin_active()
    {
        return ($this->db_record_active()?true:false);
    }

    public function check_installation()
    {
        if( !($db_details = $this->get_db_details()) )
        {
            $this->reset_error();

            return $this->install();
        }

        if( version_compare( $db_details['version'], $this->get_plugin_version(), '!=' ) )
            return $this->update( $db_details['version'], $this->get_plugin_version() );

        return true;
    }

    final public function activate_plugin()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_CHANGES, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Logger::logf( 'Activating plugin [' . $this->instance_id() . ']', PHS_Logger::TYPE_MAINTENANCE );

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            PHS_Logger::logf( '!!! Error instantiating plugins model. [' . $this->instance_id() . ']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_CHANGES, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $plugin_details = array();
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_ACTIVE;

        if( !($db_details = $plugins_model->update_db_details( $plugin_details ))
         or empty( $db_details['new_data'] ) )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_CHANGES, self::_t( 'Error activating plugin.' ) );

            return false;
        }

        return $db_details['new_data'];
    }

    final public function inactivate_plugin()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_CHANGES, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Logger::logf( 'Activating plugin [' . $this->instance_id() . ']', PHS_Logger::TYPE_MAINTENANCE );

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            PHS_Logger::logf( '!!! Error instantiating plugins model. [' . $this->instance_id() . ']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_CHANGES, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $plugin_details = array();
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_INACTIVE;

        if( !($db_details = $plugins_model->update_db_details( $plugin_details ))
         or empty( $db_details['new_data'] ) )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_CHANGES, self::_t( 'Error inactivating plugin.' ) );

            return false;
        }

        return $db_details['new_data'];
    }

    final public function install()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Logger::logf( 'Installing plugin ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            PHS_Logger::logf( '!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( !$plugins_model->check_install_plugins_db() )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing plugins model.' ) );

            PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        $plugin_details = array();
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['plugin'] = $this->instance_plugin_name();
        $plugin_details['type'] = $this->instance_type();
        $plugin_details['is_core'] = ($this->instance_is_core() ? 1 : 0);
        $plugin_details['settings'] = PHS_line_params::to_string( $this->get_default_settings() );
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_INSTALLED;
        $plugin_details['version'] = $this->get_plugin_version();

        if( !($db_details = $plugins_model->update_db_details( $plugin_details ))
         or empty( $db_details['new_data'] ) )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_INSTALL, self::_t( 'Error saving plugin details to database.' ) );

            PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        $plugin_arr = $db_details['new_data'];
        $old_plugin_arr = (!empty( $db_details['old_data'] )?$db_details['old_data']:false);

        if( empty( $old_plugin_arr ) )
        {
            PHS_Logger::logf( 'Triggering install signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            // No details in database before... it should be an install
            $signal_params = array();
            $signal_params['version'] = $plugin_arr['version'];

            $this->signal_trigger( self::SIGNAL_INSTALL, $signal_params );

            PHS_Logger::logf( 'DONE triggering install signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );
        } else
        {
            $trigger_update_signal = false;
            // Performs any necessary actions when updating model from old version to new version
            if( version_compare( $old_plugin_arr['version'], $plugin_arr['version'], '<' ) )
            {
                PHS_Logger::logf( 'Calling update method from version ['.$old_plugin_arr['version'].'] to version ['.$plugin_arr['version'].'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                // Installed version is bigger than what we already had in database... update...
                if( !$this->update( $old_plugin_arr['version'], $plugin_arr['version'] ) )
                {
                    PHS_Logger::logf( '!!! Update failed ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE );

                    return false;
                }

                PHS_Logger::logf( 'Update with success ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $trigger_update_signal = true;
            }

            if( $trigger_update_signal )
            {
                PHS_Logger::logf( 'Triggering update signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $signal_params = array();
                $signal_params['old_version'] = $old_plugin_arr['version'];
                $signal_params['new_version'] = $plugin_arr['version'];

                $this->signal_trigger( self::SIGNAL_UPDATE, $signal_params );
            } else
            {
                PHS_Logger::logf( 'Triggering install signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $signal_params = array();
                $signal_params['version'] = $plugin_arr['version'];

                $this->signal_trigger( self::SIGNAL_INSTALL, $signal_params );
            }

            PHS_Logger::logf( 'DONE triggering signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );
        }

        PHS_Logger::logf( 'Installing plugin models ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        if( ($models_arr = $this->get_models())
        and is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing model %s.', $model_name ) );

                    return false;
                }

                $model_obj->install();
            }
        }

        PHS_Logger::logf( 'DONE installing plugin ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return $plugin_arr;
    }

    final public function uninstall()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Logger::logf( 'Uninstalling plugin ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            PHS_Logger::logf( '!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $check_arr = array();
        $check_arr['instance_id'] = $this_instance_id;

        db_supress_errors( $plugins_model->get_db_connection() );
        if( !($db_details = $plugins_model->get_details_fields( $check_arr ))
         or empty( $db_details['type'] )
         or $db_details['type'] != self::INSTANCE_TYPE_PLUGIN )
        {
            db_restore_errors_state( $plugins_model->get_db_connection() );

            if( !$this->has_error() )
                $this->set_error( self::ERR_UNINSTALL, self::_t( 'Plugin doesn\'t seem to be installed.' ) );

            return false;
        }

        db_restore_errors_state( $plugins_model->get_db_connection() );

        if( $plugins_model->active_status( $db_details['status'] ) )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Plugin is still active. Please inactivate it first.' ) );
            return false;
        }

        PHS_Logger::logf( 'Triggering uninstall signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        $this->signal_trigger( self::SIGNAL_UNINSTALL );

        PHS_Logger::logf( 'DONE triggering uninstall signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        PHS_Logger::logf( 'Uninstalling plugin models ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        if( ($models_arr = $this->get_models())
        and is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing model %s.', $model_name ) );

                    return false;
                }

                $model_obj->uninstall();
            }
        }

        if( !$plugins_model->hard_delete( $db_details ) )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error hard-deleting plugin from database.' ) );

            PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        PHS_Logger::logf( 'DONE uninstalling plugin ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return $db_details;
    }

    /**
     * Performs any necessary custom actions when updating plugin from $old_version to $new_version
     * Overwrite this method to do particular updates.
     * If this function returns false whole update will stop and error set in this method will be used.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    protected function custom_update( $old_version, $new_version )
    {
        return true;
    }

    /**
     * Performs any necessary actions when updating plugin from $old_version to $new_version
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    final protected function update( $old_version, $new_version )
    {
        if( !$this->custom_update( $old_version, $new_version ) )
            return false;

        if( ($models_arr = $this->get_models())
        and is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing model %s.', $model_name ) );

                    return false;
                }
                
                if( !($model_details = $model_obj->get_db_details( true ))
                 or empty( $model_details['version'] ) )
                    $old_version = '0.0.0';
                else
                    $old_version = $model_details['version'];

                $model_obj->update( $old_version, $model_obj->get_model_version() );
            }
        }

        return true;
    }

    final public function default_plugin_details_fields()
    {
        return array(
            'id' => '',
            'name' => '',
            'description' => '',
            'script_version' => '0.0.0',
            'db_version' => '0.0.0',
            'update_url' => '',
            'status' => 0,
            'is_installed' => false,
            'is_core' => false,
            'db_details' => false,
            'models' => array(),
            'settings_arr' => array(),
        );
    }

    final public function get_plugin_info()
    {
        $default_info = $this->default_plugin_details_fields();
        
        if( !empty( $this->_plugin_details ) )
            return $this->_plugin_details;

        $plugin_details = self::validate_array( $this->get_plugin_details(), $default_info );

        $plugin_details['id'] = $this->instance_id();

        if( empty( $plugin_details['name'] ) )
            $plugin_details['name'] = $this->instance_name();

        $plugin_details['script_version'] = $this->get_plugin_version();
        $plugin_details['models'] = $this->get_models();

        if( ($db_details = $this->get_db_details()) )
        {
            $plugin_details['db_details'] = $db_details;
            $plugin_details['is_installed'] = true;
            $plugin_details['db_version'] = (!empty( $db_details['version'] )?$db_details['version']:'0.0.0');
            $plugin_details['is_core'] = (!empty( $db_details['is_core'] )?true:false);
        }

        $plugin_details['settings_arr'] = $this->get_plugin_settings();

        $this->_plugin_details = $plugin_details;

        return $this->_plugin_details;
    }

}
