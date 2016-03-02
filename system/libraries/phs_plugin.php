<?php

namespace phs\libraries;

use phs\PHS;
use \phs\system\core\models\PHS_Model_Plugins;

abstract class PHS_Plugin extends PHS_Signal_and_slot
{
    const SIGNAL_INSTALL = 'phs_plugin_install', SIGNAL_UPDATE = 'phs_plugin_update', SIGNAL_FORCE_INSTALL = 'phs_plugin_force_install';

    const ERR_MODEL = 30000, ERR_INSTANCE = 30001, ERR_INSTALL = 30002, ERR_LIBRARY = 30003;

    const LIBRARIES_DIR = 'libraries';

    private $_libraries_instances = array();

    /**
     * @return array An array of strings which are the models used by this plugin
     */
    abstract public function get_models();

    /**
     * @return string Returns version of plugin
     */
    abstract public function get_plugin_version();

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_PLUGIN;
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     *
     * @return array
     */
    public function get_default_settings()
    {
        return array();
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

    public function plugin_active()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !($plugin_obj = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( !($db_details = $plugin_obj->get_db_details( $this->instance_id() ))
         or !$plugin_obj->active_status( $db_details['status'] ) )
            return false;

        return true;
    }

    public function get_plugin_db_details( $force = false )
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !($plugin_obj = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        return $plugin_obj->get_db_details( $this->instance_id(), $force );
    }

    public function get_plugin_db_settings( $force = false )
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !($plugin_obj = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        return $plugin_obj->get_db_settings( $this->instance_id(), $force );
    }

    public function check_installation()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !($plugin_obj = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( !($db_details = $plugin_obj->get_db_details( $this->instance_id() )) )
        {
            $this->reset_error();

            return $this->install();
        }

        if( version_compare( $db_details['version'], $this->get_plugin_version(), '!=' ) )
            return $this->update( $db_details['version'], $this->get_plugin_version() );

        return true;
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

                $model_obj->update();
            }
        }

        return true;
    }

}
