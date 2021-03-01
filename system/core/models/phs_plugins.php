<?php

namespace phs\system\core\models;

use \phs\PHS;
use \phs\PHS_Crypt;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Line_params;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Hooks;

class PHS_Model_Plugins extends PHS_Model
{
    const ERR_FORCE_INSTALL = 100, ERR_DB_DETAILS = 101, ERR_DIR_DETAILS = 102, ERR_REGISTRY = 103, ERR_SETTINGS = 104;

    const HOOK_STATUSES = 'phs_plugins_statuses';

    const STATUS_INSTALLED = 1, STATUS_ACTIVE = 2, STATUS_INACTIVE = 3;

    // Cached database plugins rows
    private static $db_plugins = [];

    // Cached database plugin records which are ACTIVE plugins in framework
    private static $db_plugin_active_plugins = [];

    // Cached database plugin records which are plugins in framework
    private static $db_plugin_plugins = [];

    // Cached database registry rows
    private static $db_registry = [];

    // Cached directory rows
    private static $dir_plugins = [];

    // Cached plugin settings
    private static $plugin_settings = [];

    // Cached plugin registry
    private static $plugin_registry = [];

    protected static $STATUSES_ARR = [
        self::STATUS_INSTALLED => [ 'title' => 'Installed' ],
        self::STATUS_ACTIVE => [ 'title' => 'Active' ],
        self::STATUS_INACTIVE => [ 'title' => 'Inactive' ],
    ];

    public function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        $this->_reset_db_plugin_cache();
        $this->_reset_plugin_settings_cache();
        $this->_reset_db_registry_cache();
        $this->_reset_plugin_registry_cache();
    }

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.2';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return [ 'plugins', 'plugins_registry' ];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'plugins';
    }

    final public function get_statuses_as_key_val()
    {
        static $plugins_statuses_key_val_arr = false;

        if( $plugins_statuses_key_val_arr !== false )
            return $plugins_statuses_key_val_arr;

        $plugins_statuses_key_val_arr = [];
        if( ($plugins_statuses = $this->get_statuses()) )
        {
            foreach( $plugins_statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $plugins_statuses_key_val_arr[$key] = $val['title'];
            }
        }

        return $plugins_statuses_key_val_arr;
    }

    final public function get_statuses()
    {
        static $statuses_arr = [];

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $new_statuses_arr = self::$STATUSES_ARR;
        $hook_args = PHS_Hooks::default_common_hook_args();
        $hook_args['statuses_arr'] = self::$STATUSES_ARR;

        if( ($extra_statuses_arr = PHS::trigger_hooks( self::HOOK_STATUSES, $hook_args ))
         && is_array( $extra_statuses_arr ) && !empty( $extra_statuses_arr['statuses_arr'] ) )
            $new_statuses_arr = self::merge_array_assoc( $extra_statuses_arr['statuses_arr'], $new_statuses_arr );

        $statuses_arr = [];
        // Translate and validate statuses...
        if( !empty( $new_statuses_arr ) && is_array( $new_statuses_arr ) )
        {
            foreach( $new_statuses_arr as $status_id => $status_arr )
            {
                $status_id = (int)$status_id;
                if( empty( $status_id ) )
                    continue;

                if( empty( $status_arr['title'] ) )
                    $status_arr['title'] = self::_t( 'Status %s', $status_id );
                else
                    $status_arr['title'] = self::_t( $status_arr['title'] );

                $statuses_arr[$status_id] = [ 'title' => $status_arr['title'] ];
            }
        }

        return $statuses_arr;
    }

    public function active_status( $status )
    {
        $status = (int)$status;
        if( !$this->valid_status( $status )
         || !in_array( $status, [ self::STATUS_ACTIVE ], true ) )
            return false;

        return true;
    }

    public function inactive_status( $status )
    {
        $status = (int)$status;
        if( !$this->valid_status( $status )
         || !in_array( $status, [ self::STATUS_INSTALLED, self::STATUS_INACTIVE ], true ) )
            return false;

        return true;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
         || empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    private function _reset_plugin_settings_cache()
    {
        self::$plugin_settings = [];
    }

    private function _reset_db_plugin_cache()
    {
        self::$db_plugins = [];
        self::$db_plugin_plugins = [];
        self::$db_plugin_active_plugins = [];
    }

    private function _reset_plugin_registry_cache()
    {
        self::$plugin_registry = [];
    }

    private function _reset_db_registry_cache()
    {
        self::$db_registry = [];
    }

    public function is_active( $plugin_data )
    {
        if( empty( $plugin_data )
         || !($plugin_arr = $this->data_to_array( $plugin_data ))
         || (int)$plugin_arr['status'] !== self::STATUS_ACTIVE )
            return false;

        return $plugin_arr;
    }

    public function is_inactive( $plugin_data )
    {
        if( empty( $plugin_data )
         || !($plugin_arr = $this->data_to_array( $plugin_data ))
         || !$this->inactive_status( $plugin_arr['status'] ) )
            return false;

        return $plugin_arr;
    }

    public function is_status_inactive( $plugin_data )
    {
        if( empty( $plugin_data )
         || !($plugin_arr = $this->data_to_array( $plugin_data ))
         || (int)$plugin_arr['status'] !== self::STATUS_INACTIVE )
            return false;

        return $plugin_arr;
    }

    public function is_installed( $plugin_data )
    {
        if( empty( $plugin_data )
         || !($plugin_arr = $this->data_to_array( $plugin_data ))
         || (int)$plugin_arr['status'] !== self::STATUS_INSTALLED )
            return false;

        return $plugin_arr;
    }

    /**
     * @param array $settings_arr
     * @param array $obfuscating_keys
     * @param null|string $instance_id
     *
     * @return bool|mixed
     */
    public function save_plugins_db_settings( $settings_arr, $obfuscating_keys, $instance_id = null )
    {
        $this->reset_error();

        if( $instance_id !== null
         && !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_SETTINGS, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_SETTINGS, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( empty( $obfuscating_keys ) || !is_array( $obfuscating_keys ) )
            $obfuscating_keys = [];

        $plugin_details = [];
        $plugin_details['instance_id'] = $instance_id;
        $plugin_details['settings'] = $settings_arr;

        if( !($db_details = $this->update_db_details( $plugin_details, $obfuscating_keys ))
         || empty( $db_details['new_data'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Error saving settings in database.' ) );

            return false;
        }

        // clean caches...
        if( isset( self::$plugin_settings[$instance_id] ) )
            unset( self::$plugin_settings[$instance_id] );
        if( isset( self::$db_plugins[$instance_id] ) )
            unset( self::$db_plugins[$instance_id] );

        return $this->get_plugins_db_settings( $instance_id, false, $obfuscating_keys, true );
    }

    /**
     * Get settings from database
     * @param null|string $instance_id
     * @param bool|array $default_settings
     * @param bool|array $obfuscating_keys
     * @param bool $force
     *
     * @return bool|mixed
     */
    public function get_plugins_db_settings( $instance_id = null, $default_settings = false, $obfuscating_keys = false, $force = false )
    {
        $this->reset_error();

        if( $instance_id !== null
         && !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_SETTINGS, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_SETTINGS, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( empty( $obfuscating_keys ) || !is_array( $obfuscating_keys ) )
            $obfuscating_keys = [];

        if( !empty( $force )
         && isset( self::$plugin_settings[$instance_id] ) )
            unset( self::$plugin_settings[$instance_id] );

        if( isset( self::$plugin_settings[$instance_id] ) )
            return self::$plugin_settings[$instance_id];

        if( !($db_details = $this->get_plugins_db_details( $instance_id, $force )) )
            return false;

        if( empty( $db_details['settings'] ) )
            self::$plugin_settings[$instance_id] = (!empty( $default_settings )?$default_settings: []);

        else
        {
            // parse settings in database...
            self::$plugin_settings[$instance_id] = PHS_Line_params::parse_string( $db_details['settings'] );

            if( !empty( $obfuscating_keys ) )
            {
                foreach( $obfuscating_keys as $ob_key )
                {
                    if( array_key_exists( $ob_key, self::$plugin_settings[$instance_id] )
                     && is_string( self::$plugin_settings[$instance_id][$ob_key] ) )
                    {
                        // In case we are in install mode and errors will get thrown
                        try {
                            self::$plugin_settings[$instance_id][$ob_key] = PHS_Crypt::quick_decode( self::$plugin_settings[$instance_id][$ob_key] );
                        } catch( \Exception $e )
                        {
                        }
                    }
                }
            }

            // Merge database settings with default script settings
            if( !empty( $default_settings ) )
                self::$plugin_settings[$instance_id] = self::validate_array( self::$plugin_settings[$instance_id], $default_settings );
        }

        // Low level hook for plugin settings (allow only keys that are not present in default plugin settings)
        $hook_args = PHS_Hooks::default_plugin_settings_hook_args();
        $hook_args['settings_arr'] = self::$plugin_settings[$instance_id];
        $hook_args['instance_id'] = $instance_id;

        if( ($extra_settings_arr = PHS::trigger_hooks( PHS_Hooks::H_PLUGIN_SETTINGS, $hook_args ))
         && is_array( $extra_settings_arr ) && !empty( $extra_settings_arr['settings_arr'] ) )
            self::$plugin_settings[$instance_id] = self::validate_array( $extra_settings_arr['settings_arr'], self::$plugin_settings[$instance_id] );

        return self::$plugin_settings[$instance_id];
    }

    public function save_plugins_db_registry( $registry_arr, $instance_id = null )
    {
        $this->reset_error();

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !($instance_details = self::valid_instance_id( $instance_id ))
         || empty( $instance_details['plugin_name'] ) )
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        $plugin_details = [];
        $plugin_details['instance_id'] = $instance_id;
        $plugin_details['plugin'] = $instance_details['plugin_name'];
        $plugin_details['registry'] = $registry_arr;

        if( !($db_details = $this->update_db_registry( $plugin_details ))
         || empty( $db_details['new_data'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Error saving registry data in database.' ) );

            return false;
        }

        // clean caches...
        if( isset( self::$plugin_registry[$instance_id] ) )
            unset( self::$plugin_registry[$instance_id] );
        if( isset( self::$db_registry[$instance_id] ) )
            unset( self::$db_registry[$instance_id] );

        return $this->get_plugins_db_registry( $instance_id, true );
    }

    public function get_plugins_db_registry( $instance_id = null, $force = false )
    {
        $this->reset_error();

        if( $instance_id !== null
         && !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !empty( $force )
         && isset( self::$plugin_registry[$instance_id] ) )
            unset( self::$plugin_registry[$instance_id] );

        if( isset( self::$plugin_registry[$instance_id] ) )
            return self::$plugin_registry[$instance_id];

        if( !($db_details = $this->get_db_registry( $instance_id, $force )) )
            return false;

        if( empty( $db_details['registry'] ) )
            self::$plugin_registry[$instance_id] = [];

        else
        {
            // parse settings in database...
            self::$plugin_registry[$instance_id] = PHS_Line_params::parse_string( $db_details['registry'] );

            $hook_args = PHS_Hooks::default_common_hook_args();
            $hook_args['registry_arr'] = self::$plugin_registry[$instance_id];

            if( ($extra_registry_arr = PHS::trigger_hooks( PHS_Hooks::H_PLUGIN_REGISTRY, $hook_args ))
             && is_array( $extra_registry_arr ) && !empty( $extra_registry_arr['registry_arr'] ) )
                self::$plugin_registry[$instance_id] = self::validate_array_recursive( $extra_registry_arr['registry_arr'], self::$plugin_registry[$instance_id] );
        }

        return self::$plugin_registry[$instance_id];
    }

    /**
     * @return array|bool False on error or array containing plugin names (without instantiating plugins)
     */
    public function get_all_plugin_names_from_dir()
    {
        $this->reset_error();

        @clearstatcache();

        if( ($dirs_list = @glob( PHS_PLUGINS_DIR.'*', GLOB_ONLYDIR )) === false
         || !is_array( $dirs_list ) )
        {
            $this->set_error( self::ERR_DIR_DETAILS, self::_t( 'Couldn\'t get a list of plugin directories.' ) );
            return false;
        }

        $return_arr = [];
        foreach( $dirs_list as $dir_name )
        {
            $dir_name = basename( $dir_name );
            if( empty( $dir_name ) )
                continue;

            $return_arr[] = $dir_name;
        }

        return $return_arr;
    }

    /**
     * Get plugin names and instances as key value pairs
     *
     * @param bool $force Force plugins recheck
     *
     * @return array|bool False on error or array with plugin name as key and plugin instance as value
     */
    public function cache_all_dir_details( $force = false )
    {
        $this->reset_error();

        if( !empty( $force )
         && !empty( self::$dir_plugins ) )
            self::$dir_plugins = [];

        if( !empty( self::$dir_plugins ) )
            return self::$dir_plugins;

        @clearstatcache();

        if( ($dirs_list = @glob( PHS_PLUGINS_DIR.'*', GLOB_ONLYDIR )) === false
         || !is_array( $dirs_list ) )
        {
            $this->set_error( self::ERR_DIR_DETAILS, self::_t( 'Couldn\'t get a list of plugin directories.' ) );
            return false;
        }

        /** @var \phs\libraries\PHS_Plugin $plugin_instance */
        foreach( $dirs_list as $dir_name )
        {
            $dir_name = basename( $dir_name );
            if( !($plugin_instance = PHS::load_plugin( $dir_name )) )
                continue;

            self::$dir_plugins[$dir_name] = $plugin_instance;
        }

        return self::$dir_plugins;
    }

    public function get_all_db_details( $force = false )
    {
        $this->cache_all_db_details( $force );

        return (empty( self::$db_plugins )? [] :self::$db_plugins);
    }

    /**
     * Cache all plugin records from plugins table...
     * @param bool $force
     *
     * @return bool
     */
    public function cache_all_db_details( $force = false )
    {
        $this->reset_error();

        if( !empty( $force )
         && !empty( self::$db_plugins ) )
            $this->_reset_db_plugin_cache();

        if( !empty( self::$db_plugins ) )
            return true;

        if( !($list_arr = $this->fetch_default_flow_params( [ 'table_name' => 'plugins' ] )) )
        {
            $this->set_error( self::ERR_DB_DETAILS, $this->_pt( 'Error preparing query to obtain plugins records.' ) );
            return false;
        }

        $list_arr['order_by'] = 'is_core DESC';

        db_supress_errors( $list_arr['db_connection'] );
        if( !($all_db_plugins = $this->get_list( $list_arr )) )
        {
            db_restore_errors_state( $list_arr['db_connection'] );
            self::$db_plugins = [];
            self::$db_plugin_plugins = [];
            self::$db_plugin_active_plugins = [];
            return true;
        }
        db_restore_errors_state( $list_arr['db_connection'] );

        foreach( $all_db_plugins as $db_id => $db_arr )
        {
            if( empty( $db_arr['instance_id'] ) )
                continue;

            self::$db_plugins[$db_arr['instance_id']] = $db_arr;
        }

        if( !empty( self::$db_plugins ) )
        {
            self::$db_plugin_plugins = [];
            $this->get_all_plugins( false );
            self::$db_plugin_active_plugins = [];
            $this->get_all_active_plugins( false );
        }

        return true;
    }

    public function get_all_active_plugin_records( $force = false )
    {
        $this->reset_error();

        if( !$this->cache_all_db_details( $force )
         || empty( self::$db_plugins ) )
            return [];

        $return_arr = [];
        foreach( self::$db_plugins as $instance_id => $plugin_arr )
        {
            if( !$this->is_active( $plugin_arr ) )
                continue;

            $return_arr[$instance_id] = $plugin_arr;
        }

        return $return_arr;
    }

    /**
     * Returns cached array of active plugins from plugins table
     * @param bool $force
     *
     * @return array
     */
    public function get_all_active_plugins( $force = false )
    {
        $this->reset_error();

        if( (!empty( $force ) || empty( self::$db_plugins ))
         && !$this->cache_all_db_details( $force ) )
        {
            self::$db_plugin_active_plugins = [];
            return [];
        }

        if( !empty( self::$db_plugin_active_plugins ) )
        {
            if( empty( $force ) )
                return self::$db_plugin_active_plugins;

            self::$db_plugin_active_plugins = [];
        }

        if( !is_array( self::$db_plugin_active_plugins ) )
            self::$db_plugin_active_plugins = [];

        foreach( self::$db_plugins as $instance_id => $plugin_arr )
        {
            if( !$this->is_active( $plugin_arr )
             || $plugin_arr['type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
             || empty( $plugin_arr['plugin'] ) )
                continue;

            self::$db_plugin_active_plugins[$plugin_arr['plugin']] = $plugin_arr;
        }

        return self::$db_plugin_active_plugins;
    }

    public function get_all_plugins( $force = false )
    {
        $this->reset_error();

        if( (!empty( $force ) || empty( self::$db_plugins ))
         && !$this->cache_all_db_details( $force ) )
        {
            self::$db_plugin_plugins = [];
            return [];
        }

        if( !empty( self::$db_plugin_plugins ) )
        {
            if( empty( $force ) )
                return self::$db_plugin_plugins;

            self::$db_plugin_plugins = [];
        }

        if( !is_array( self::$db_plugin_plugins ) )
            self::$db_plugin_plugins = [];

        foreach( self::$db_plugins as $instance_id => $plugin_arr )
        {
            if( $plugin_arr['type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
             || empty( $plugin_arr['plugin'] ) )
                continue;

            self::$db_plugin_plugins[$plugin_arr['plugin']] = $plugin_arr;
        }

        return self::$db_plugin_plugins;
    }

    /**
     * Returns plugin name as described in plugin instance using get_plugin_details() method at installation time.
     *
     * @param string $slug Plugin identifier
     *
     * @return string Plugin name or empty string if not found
     */
    public function get_plugin_name_by_slug( $slug )
    {
        if( ($all_plugins = $this->get_all_plugins())
         && !empty( $all_plugins[$slug] )
         && is_array( $all_plugins[$slug] )
         && !empty( $all_plugins[$slug]['plugin_name'] ) )
            return $all_plugins[$slug]['plugin_name'];

        return '';
    }

    public function cache_all_db_registry_details( $force = false )
    {
        $this->reset_error();

        if( !empty( $force )
         && !empty( self::$db_registry ) )
            self::$db_registry = [];

        if( !empty( self::$db_registry ) )
            return self::$db_registry;

        $list_arr = $this->fetch_default_flow_params( [ 'table_name' => 'plugins_registry' ] );

        if( !($all_db_registry = $this->get_list( $list_arr )) )
        {
            self::$db_registry = [];
            return true;
        }

        foreach( $all_db_registry as $db_id => $db_arr )
        {
            if( empty( $db_arr['instance_id'] ) )
                continue;

            self::$db_registry[$db_arr['instance_id']] = $db_arr;
        }

        return true;
    }

    /**
     * @param string|null $instance_id Instance ID to check in database
     * @param bool $force True if we should skip caching
     *
     * @return array|bool|false Array containing database fields of given instance_id (if available)
     */
    public function get_plugins_db_details( $instance_id = null, $force = false )
    {
        $this->reset_error();

        if( $instance_id !== null
         && !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !empty( $force )
         && !empty( self::$db_plugins[$instance_id] ) )
            unset( self::$db_plugins[$instance_id] );

        // Cache all plugin details at once instead of caching one at a time...
        $this->cache_all_db_details( $force );

        if( !empty( self::$db_plugins[$instance_id] ) )
            return self::$db_plugins[$instance_id];

        $check_arr = $this->fetch_default_flow_params( [ 'table_name' => 'plugins' ] );
        $check_arr['instance_id'] = $instance_id;

        db_supress_errors( $this->get_db_connection() );
        if( !($db_details = $this->get_details_fields( $check_arr )) )
        {
            db_restore_errors_state( $this->get_db_connection() );

            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Couldn\'t find plugin settings in database. Try re-installing plugin.' ) );

            return false;
        }

        db_restore_errors_state( $this->get_db_connection() );

        self::$db_plugins[$instance_id] = $db_details;

        return $db_details;
    }

    /**
     * @param array $fields_arr
     * @param bool|array $obfuscating_keys
     * @param bool|array $update_params
     *
     * @return array|bool
     */
    public function update_db_details( $fields_arr, $obfuscating_keys = false, $update_params = false )
    {
        if( empty( $fields_arr ) || !is_array( $fields_arr )
         || empty( $fields_arr['instance_id'] )
         || !($instance_details = self::valid_instance_id( $fields_arr['instance_id'] ))
         || !($params = $this->fetch_default_flow_params( [ 'table_name' => 'plugins' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unknown instance database details.' ) );
            return false;
        }

        if( empty( $update_params ) || !is_array( $update_params ) )
            $update_params = [];

        if( empty( $update_params['skip_merging_old_settings'] ) )
            $update_params['skip_merging_old_settings'] = false;
        else
            $update_params['skip_merging_old_settings'] = true;

        $check_arr = [];
        $check_arr['instance_id'] = $fields_arr['instance_id'];

        $params['fields'] = $fields_arr;

        if( !($existing_arr = $this->get_details_fields( $check_arr, $params )) )
        {
            $existing_arr = false;
            $params['action'] = 'insert';
        } else
        {
            $params['action'] = 'edit';
        }

        PHS_Logger::logf( 'Plugins model action ['.$params['action'].'] on instance ['.$fields_arr['instance_id'].']', PHS_Logger::TYPE_MAINTENANCE );

        if( !($validate_fields = $this->validate_data_for_fields( $params ))
         || empty( $validate_fields['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Error validating plugin database fields.' ) );
            return false;
        }

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating settings...
        if( !empty( $new_fields_arr['settings'] ) )
        {
            if( empty( $update_params['skip_merging_old_settings'] )
             && !empty( $existing_arr ) && !empty( $existing_arr['settings'] ) )
                $new_fields_arr['settings'] = self::merge_array_assoc( PHS_Line_params::parse_string( $existing_arr['settings'] ), PHS_Line_params::parse_string( $new_fields_arr['settings'] ) );

            if( !empty( $obfuscating_keys ) && is_array( $obfuscating_keys ) )
            {
                foreach( $obfuscating_keys as $ob_key )
                {
                    if( array_key_exists( $ob_key, $new_fields_arr['settings'] )
                     && is_scalar( $new_fields_arr['settings'][$ob_key] ) )
                        $new_fields_arr['settings'][$ob_key] = PHS_Crypt::quick_encode( $new_fields_arr['settings'][$ob_key] );
                }
            }

            $new_fields_arr['settings'] = PHS_Line_params::to_string( $new_fields_arr['settings'] );

            PHS_Logger::logf( 'New settings ['.$new_fields_arr['settings'].']', PHS_Logger::TYPE_MAINTENANCE );
        }

        // Prevent core plugins to be inactivated...
        if( !empty( $new_fields_arr['is_core'] ) && !empty( $new_fields_arr['status'] ) )
            $new_fields_arr['status'] = self::STATUS_ACTIVE;

        $details_arr = [];
        $details_arr['fields'] = $new_fields_arr;

        if( empty( $existing_arr ) )
            $plugin_arr = $this->insert( $details_arr );
        else
            $plugin_arr = $this->edit( $existing_arr, $details_arr );

        if( empty( $plugin_arr ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Couldn\'t save plugin details to database.' ) );

            PHS_Logger::logf( '!!! Error in plugins model action ['.$params['action'].'] on instance ['.$fields_arr['instance_id'].'] ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        PHS_Logger::logf( 'DONE Plugins model action ['.$params['action'].'] on instance ['.$fields_arr['instance_id'].']', PHS_Logger::TYPE_MAINTENANCE );

        self::$db_plugins[$fields_arr['instance_id']] = $plugin_arr;

        $return_arr = [];
        $return_arr['old_data'] = $existing_arr;
        $return_arr['new_data'] = $plugin_arr;

        return $return_arr;
    }

    /**
     * @param string|null $instance_id Instance ID to delete from database
     *
     * @return bool True on success, false on failure
     */
    public function delete_db_registry( $instance_id = null )
    {
        $this->reset_error();

        if( $instance_id !== null
         && !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'plugins_registry' ] ))
         || !($table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_REGISTRY, self::_t( 'Error obtaining flow details.' ) );
            return false;
        }

        if( !db_query( 'DELETE FROM `'.$table_name.'` WHERE instance_id = \''.$instance_id.'\' LIMIT 1', $flow_params['db_connection'] ) )
        {
            PHS_Logger::logf( 'Error deleting registry entry for instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_REGISTRY, self::_t( 'Error deleting registry database entry for instance %s.', $instance_id ) );
            return false;
        }

        if( isset( self::$plugin_registry[$instance_id] ) )
            unset( self::$plugin_registry[$instance_id] );
        if( isset( self::$db_registry[$instance_id] ) )
            unset( self::$db_registry[$instance_id] );

        return true;
    }

    /**
     * @param string|null $instance_id Instance ID to check in database
     * @param bool $force True if we should skip caching
     *
     * @return array|bool|false Array containing database registry fields of given instance_id (if available)
     */
    public function get_db_registry( $instance_id = null, $force = false )
    {
        $this->reset_error();

        if( $instance_id !== null
         && !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id === null
         && !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !empty( $force )
         && !empty( self::$db_registry[$instance_id] ) )
            unset( self::$db_registry[$instance_id] );

        // Cache all plugin registry at once instead of caching one at a time...
        $this->cache_all_db_registry_details( $force );

        if( !empty( self::$db_registry[$instance_id] ) )
            return self::$db_registry[$instance_id];

        $check_arr = $this->fetch_default_flow_params( [ 'table_name' => 'plugins_registry' ] );
        $check_arr['instance_id'] = $instance_id;

        db_supress_errors( $this->get_db_connection() );
        if( !($db_details = $this->get_details_fields( $check_arr )) )
        {
            db_restore_errors_state( $this->get_db_connection() );

            return false;
        }

        db_restore_errors_state( $this->get_db_connection() );

        self::$db_registry[$instance_id] = $db_details;

        return $db_details;
    }

    public function update_db_registry( $fields_arr )
    {
        if( empty( $fields_arr ) || !is_array( $fields_arr )
         || empty( $fields_arr['instance_id'] )
         || !($instance_details = self::valid_instance_id( $fields_arr['instance_id'] ))
         || !($params = $this->fetch_default_flow_params( [ 'table_name' => 'plugins_registry' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unknown instance database details.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['instance_id'] = $fields_arr['instance_id'];

        $params['fields'] = $fields_arr;

        if( !($existing_arr = $this->get_details_fields( $check_arr, $params )) )
        {
            $existing_arr = false;
            $params['action'] = 'insert';
        } else
        {
            $params['action'] = 'edit';
        }

        PHS_Logger::logf( 'Plugins model registry action ['.$params['action'].'] on plugin ['.$fields_arr['instance_id'].']', PHS_Logger::TYPE_INFO );

        if( !($validate_fields = $this->validate_data_for_fields( $params ))
         || empty( $validate_fields['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Error validating plugin registry database fields.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating registry...
        if( !empty( $new_fields_arr['registry'] ) )
        {
            if( !empty( $existing_arr ) && !empty( $existing_arr['registry'] ) )
                $new_fields_arr['registry'] = self::merge_array_assoc( PHS_Line_params::parse_string( $existing_arr['registry'] ), PHS_Line_params::parse_string( $new_fields_arr['registry'] ) );

            $new_fields_arr['registry'] = PHS_Line_params::to_string( $new_fields_arr['registry'] );

            $new_fields_arr['last_update'] = $cdate;

            PHS_Logger::logf( 'New registry ['.$new_fields_arr['registry'].']', PHS_Logger::TYPE_INFO );
        }

        $details_arr = $this->fetch_default_flow_params( [ 'table_name' => 'plugins_registry' ] );
        $details_arr['fields'] = $new_fields_arr;

        if( empty( $existing_arr ) )
        {
            $details_arr['fields']['cdate'] = $cdate;

            $plugin_registry_arr = $this->insert( $details_arr );
        } else
            $plugin_registry_arr = $this->edit( $existing_arr, $details_arr );

        if( empty( $plugin_registry_arr ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Couldn\'t save plugin registry to database.' ) );

            PHS_Logger::logf( '!!! Error in plugins registry model action ['.$params['action'].'] on plugin ['.$fields_arr['instance_id'].'] ['.$this->get_error_message().']', PHS_Logger::TYPE_INFO );

            return false;
        }

        PHS_Logger::logf( 'DONE Plugins registry model action ['.$params['action'].'] on plugin ['.$fields_arr['instance_id'].']', PHS_Logger::TYPE_INFO );

        self::$db_registry[$fields_arr['instance_id']] = $plugin_registry_arr;

        $return_arr = [];
        $return_arr['old_data'] = $existing_arr;
        $return_arr['new_data'] = $plugin_registry_arr;

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_plugins( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( empty( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_INSTALLED;

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid plugin status.' ) );
            return false;
        }

        if( empty( $params['fields']['instance_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a plugin id.' ) );
            return false;
        }

        $check_params = $params;
        $check_params['result_type'] = 'single';

        $check_arr = [];
        $check_arr['instance_id'] = $params['fields']['instance_id'];

        if( $this->get_details_fields( $check_arr, $check_params ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'There is already a plugin with this id in database.' ) );
            return false;
        }

        if( empty( $params['fields']['plugin_name'] ) )
            $params['fields']['plugin_name'] = '';

        $now_date = date( self::DATETIME_DB );

        $params['fields']['status_date'] = $now_date;

        if( empty( $params['fields']['cdate'] ) || empty_db_date( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = $now_date;
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_edit_prepare_params_plugins( $existing_arr, $params )
    {
        if( empty( $existing_arr ) || !is_array( $existing_arr )
         || empty( $params ) || !is_array( $params ) )
            return false;

        if( isset( $params['fields']['status'] )
         && !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid plugin status.' ) );
            return false;
        }

        if( !empty( $params['fields']['instance_id'] ) )
        {
            $check_params = $params;
            $check_params['result_type'] = 'single';

            $check_arr = [];
            $check_arr['instance_id'] = $params['fields']['instance_id'];
            $check_arr['id'] = [ 'check' => '!=', 'value' => $existing_arr['id'] ];

            if( $this->get_details_fields( $check_arr, $check_params ) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'There is already a plugin with this id in database.' ) );
                return false;
            }
        }

        $now_date = date( self::DATETIME_DB );

        if( isset( $params['fields']['plugin_name'] )
         && empty( $params['fields']['plugin_name'] ) )
            $params['fields']['plugin_name'] = '';

        if( isset( $params['fields']['status'] )
         && empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $now_date;

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        if( !empty( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    final public function check_install_plugins_db()
    {
        static $check_result = null;

        if( $check_result !== null )
            return $check_result;

        if( $this->check_table_exists( [ 'table_name' => 'plugins' ] )
         && $this->check_table_exists( [ 'table_name' => 'plugins_registry' ] ) )
        {
            $check_result = true;
            return true;
        }

        $this->reset_error();

        if( $this->install() )
            $check_result = true;
        else
            $check_result = false;

        return $check_result;
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
            case 'plugins':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'instance_id' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ],
                    'type' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 100,
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ],
                    'plugin' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 100,
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ],
                    'plugin_name' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                    ],
                    'added_by' => [
                        'type' => self::FTYPE_INT,
                        'editable' => false,
                    ],
                    'is_core' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'editable' => false,
                        'index' => true,
                    ],
                    'settings' => [
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ],
                    'version' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 30,
                        'nullable' => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                        'editable' => false,
                    ],
                ];
            break;

            case 'plugins_registry':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'instance_id' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ],
                    'plugin' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 100,
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ],
                    'registry' => [
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ],
                    'last_update' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                        'editable' => false,
                    ],
                ];
            break;
        }

        return $return_arr;
    }
}
