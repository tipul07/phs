<?php
namespace phs\system\core\models;

use phs\PHS;
use phs\PHS_Crypt;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Line_params;
use phs\libraries\PHS_Instantiable;
use phs\traits\PHS_Model_Trait_statuses;
use phs\system\core\events\plugins\PHS_Event_Plugin_registry;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_saved;

class PHS_Model_Plugins extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const ERR_FORCE_INSTALL = 100, ERR_DB_DETAILS = 101, ERR_DIR_DETAILS = 102, ERR_REGISTRY = 103, ERR_SETTINGS = 104;

    public const STATUS_INSTALLED = 1, STATUS_ACTIVE = 2, STATUS_INACTIVE = 3;

    protected static array $STATUSES_ARR = [
        self::STATUS_INSTALLED => ['title' => 'Installed'],
        self::STATUS_ACTIVE    => ['title' => 'Active'],
        self::STATUS_INACTIVE  => ['title' => 'Inactive'],
    ];

    // Cached database plugins rows
    private static array $db_plugins = [];

    // Cached database tenant plugins rows
    private static array $db_tenant_plugins = [];

    // Cached database plugin records which are ACTIVE plugins in framework
    private static array $db_plugin_active_plugins = [];

    // Cached database plugin records which are plugins in framework
    private static array $db_plugin_plugins = [];

    // Cached database registry rows
    private static array $db_registry = [];

    // Cached directory rows
    private static array $dir_plugins = [];

    // Cached plugin settings
    private static array $plugin_settings = [];

    // Cached plugin registry
    private static array $plugin_registry = [];

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.2.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return ['plugins', 'plugins_registry', 'plugins_tenants'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'plugins';
    }

    public function active_status(int $status) : bool
    {
        return $this->valid_status($status) && $status === self::STATUS_ACTIVE;
    }

    public function inactive_status(int $status) : bool
    {
        return $this->valid_status($status)
         && in_array($status, [self::STATUS_INSTALLED, self::STATUS_INACTIVE], true);
    }

    public function is_active($plugin_data)
    {
        if (empty($plugin_data)
         || !($plugin_arr = $this->data_to_array($plugin_data))
         || (int)$plugin_arr['status'] !== self::STATUS_ACTIVE) {
            return false;
        }

        return $plugin_arr;
    }

    public function is_inactive($plugin_data)
    {
        if (empty($plugin_data)
         || !($plugin_arr = $this->data_to_array($plugin_data))
         || !$this->inactive_status($plugin_arr['status'])) {
            return false;
        }

        return $plugin_arr;
    }

    public function is_status_inactive($plugin_data)
    {
        if (empty($plugin_data)
         || !($plugin_arr = $this->data_to_array($plugin_data))
         || (int)$plugin_arr['status'] !== self::STATUS_INACTIVE) {
            return false;
        }

        return $plugin_arr;
    }

    public function is_installed($plugin_data)
    {
        if (empty($plugin_data)
         || !($plugin_arr = $this->data_to_array($plugin_data))
         || (int)$plugin_arr['status'] !== self::STATUS_INSTALLED) {
            return false;
        }

        return $plugin_arr;
    }

    /**
     * @param null|string $instance_id
     * @param array|string $settings_arr array or PHS_Line_params string
     * @param array $update_params
     *
     * @return null|array
     */
    public function save_plugins_db_settings(?string $instance_id, $settings_arr, array $update_params = []) : ?array
    {
        $this->reset_error();

        if (empty($update_params)) {
            $update_params = [];
        }

        $update_params['skip_merging_old_settings'] = (!empty($update_params['skip_merging_old_settings']));

        if ($instance_id !== null
         && !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid instance ID.'));

            return null;
        }

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Unknown instance ID.'));

            return null;
        }

        if (!$this->get_plugins_db_details($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Plugin is not yet installed.'));

            return null;
        }

        if (!($old_settings = $this->get_plugins_db_settings($instance_id, true))) {
            $old_settings = [];
        }

        // Nothing to save...
        if (!($settings_arr = $this->_decode_settings_field($settings_arr))) {
            return $old_settings;
        }

        if (empty($update_params['skip_merging_old_settings'])
         && !empty($old_settings)) {
            $settings_arr = self::merge_array_assoc($old_settings, $settings_arr);
        }

        $plugin_details = [];
        $plugin_details['settings'] = $settings_arr;

        if (!($db_details = $this->_update_db_details($instance_id, $plugin_details))
         || empty($db_details['new_data'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS, self::_t('Error saving settings in database.'));
            }

            return null;
        }

        // clean caches...
        if (isset(self::$plugin_settings[$instance_id])) {
            unset(self::$plugin_settings[$instance_id]);
        }
        if (isset(self::$db_plugins[$instance_id])) {
            unset(self::$db_plugins[$instance_id]);
        }

        if (!($new_settings_arr = $this->get_plugins_db_settings($instance_id, true))) {
            $new_settings_arr = [];
        }

        return $new_settings_arr;
    }

    /**
     * Get settings from database
     *
     * @param null|string $instance_id
     * @param bool $force
     *
     * @return null|array
     */
    public function get_plugins_db_settings(
        ?string $instance_id = null,
        bool $force = false) : ?array
    {
        $this->reset_error();

        if ($instance_id !== null
         && !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid instance ID.'));

            return null;
        }

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Unknown instance ID.'));

            return null;
        }

        if (!empty($force)
         && isset(self::$plugin_settings[$instance_id])) {
            unset(self::$plugin_settings[$instance_id]);
        }

        if (isset(self::$plugin_settings[$instance_id])) {
            return self::$plugin_settings[$instance_id];
        }

        if (!($db_details = $this->get_plugins_db_details($instance_id, $force))) {
            return null;
        }

        if (empty($db_details['settings'])) {
            self::$plugin_settings[$instance_id] = [];
        } else {
            // parse settings in database...
            self::$plugin_settings[$instance_id] = $this->_decode_settings_field($db_details['settings']);
        }

        return self::$plugin_settings[$instance_id];
    }

    public function save_plugins_db_registry($registry_arr, $instance_id = null) : ?array
    {
        $this->reset_error();

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Unknown instance ID.'));

            return null;
        }

        if (!($instance_details = self::valid_instance_id($instance_id))
         || empty($instance_details['plugin_name'])) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Invalid instance ID.'));

            return null;
        }

        $plugin_details = [];
        $plugin_details['instance_id'] = $instance_id;
        $plugin_details['plugin'] = $instance_details['plugin_name'];
        $plugin_details['registry'] = $registry_arr;

        if (!($db_details = $this->update_db_registry($plugin_details))
         || empty($db_details['new_data'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS, self::_t('Error saving registry data in database.'));
            }

            return null;
        }

        // clean caches...
        if (isset(self::$plugin_registry[$instance_id])) {
            unset(self::$plugin_registry[$instance_id]);
        }
        if (isset(self::$db_registry[$instance_id])) {
            unset(self::$db_registry[$instance_id]);
        }

        return $this->get_plugins_db_registry($instance_id, true);
    }

    public function get_plugins_db_registry(?string $instance_id = null, bool $force = false) : ?array
    {
        $this->reset_error();

        if ($instance_id !== null
         && !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Invalid instance ID.'));

            return null;
        }

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Unknown instance ID.'));

            return null;
        }

        if (!empty($force)
         && isset(self::$plugin_registry[$instance_id])) {
            unset(self::$plugin_registry[$instance_id]);
        }

        if (isset(self::$plugin_registry[$instance_id])) {
            return self::$plugin_registry[$instance_id];
        }

        if (!($db_details = $this->get_db_registry($instance_id, $force))) {
            return null;
        }

        if (empty($db_details['registry'])) {
            self::$plugin_registry[$instance_id] = [];
        } else {
            // parse settings in database...
            self::$plugin_registry[$instance_id] = $this->_decode_registry_field($db_details['registry']);
        }

        /** @var \phs\system\core\events\plugins\PHS_Event_Plugin_registry $event_obj */
        if (($event_obj = PHS_Event_Plugin_registry::trigger([
            'instance_id'  => $instance_id,
            'registry_arr' => self::$plugin_registry[$instance_id],
        ]))
            && ($new_registry_arr = $event_obj->get_output('registry_arr'))
            && is_array($new_registry_arr)
        ) {
            self::$plugin_registry[$instance_id]
                = self::validate_array_recursive($new_registry_arr, self::$plugin_registry[$instance_id]);
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

        if (($dirs_list = @glob(PHS_PLUGINS_DIR.'*', GLOB_ONLYDIR)) === false
         || !is_array($dirs_list)) {
            $this->set_error(self::ERR_DIR_DETAILS, self::_t('Couldn\'t get a list of plugin directories.'));

            return false;
        }

        $return_arr = [];
        foreach ($dirs_list as $dir_name) {
            if (!($dir_name = basename($dir_name))) {
                continue;
            }

            $return_arr[] = $dir_name;
        }

        return $return_arr;
    }

    /**
     * Get plugin names and instances as key value pairs
     *
     * @param bool $force Force plugins recheck
     *
     * @return null|array<string, \phs\libraries\PHS_Plugin> False on error or array with plugin name as key and plugin instance as value
     */
    public function cache_all_dir_details(bool $force = false) : ?array
    {
        $this->reset_error();

        if (!empty($force)
         && !empty(self::$dir_plugins)) {
            self::$dir_plugins = [];
        }

        if (!empty(self::$dir_plugins)) {
            return self::$dir_plugins;
        }

        @clearstatcache();

        if (($dirs_list = @glob(PHS_PLUGINS_DIR.'*', GLOB_ONLYDIR)) === false
         || !is_array($dirs_list)) {
            $this->set_error(self::ERR_DIR_DETAILS, self::_t('Couldn\'t get a list of plugin directories.'));

            return null;
        }

        /** @var \phs\libraries\PHS_Plugin $plugin_instance */
        foreach ($dirs_list as $dir_name) {
            if (!($dir_name = basename($dir_name))
                || !($plugin_instance = PHS::load_plugin($dir_name))) {
                continue;
            }

            self::$dir_plugins[$dir_name] = $plugin_instance;
        }

        return self::$dir_plugins;
    }

    public function get_all_db_details(bool $force = false) : array
    {
        $this->_cache_all_db_details($force);

        return empty(self::$db_plugins) ? [] : self::$db_plugins;
    }

    public function get_all_active_plugin_records(bool $force = false) : array
    {
        $this->reset_error();

        if (!$this->_cache_all_db_details($force)
         || empty(self::$db_plugins)) {
            return [];
        }

        $return_arr = [];
        foreach (self::$db_plugins as $instance_id => $plugin_arr) {
            if (!$this->is_active($plugin_arr)) {
                continue;
            }

            $return_arr[$instance_id] = $plugin_arr;
        }

        return $return_arr;
    }

    /**
     * Returns cached array of active plugins from plugins table
     *
     * @param bool $force
     *
     * @return array
     */
    public function get_all_active_plugins(bool $force = false) : array
    {
        $this->_redo_db_plugins_records_cache($force);

        return self::$db_plugin_active_plugins;
    }

    public function get_all_plugins(bool $force = false) : array
    {
        $this->_redo_db_plugins_records_cache($force);

        return self::$db_plugin_plugins;
    }

    /**
     * Returns plugin name as described in plugin instance using get_plugin_details() method at installation time.
     *
     * @param string $slug Plugin identifier
     *
     * @return string Plugin name or empty string if not found
     */
    public function get_plugin_name_by_slug(string $slug) : string
    {
        if (($all_plugins = $this->get_all_plugins())
         && !empty($all_plugins[$slug])
         && is_array($all_plugins[$slug])
         && !empty($all_plugins[$slug]['plugin_name'])) {
            return $all_plugins[$slug]['plugin_name'];
        }

        return '';
    }

    public function cache_all_db_registry_details(bool $force = false) : bool
    {
        $this->reset_error();

        if (!empty($force)
         && !empty(self::$db_registry)) {
            self::$db_registry = [];
        }

        if (!empty(self::$db_registry)) {
            return true;
        }

        db_supress_errors($this->get_db_connection());
        if (!($all_db_registry = $this->get_list($this->fetch_default_flow_params(['table_name' => 'plugins_registry'])))) {
            db_restore_errors_state($this->get_db_connection());
            self::$db_registry = [];

            return true;
        }
        db_restore_errors_state($this->get_db_connection());

        foreach ($all_db_registry as $db_id => $db_arr) {
            if (empty($db_arr['instance_id'])) {
                continue;
            }

            self::$db_registry[$db_arr['instance_id']] = $db_arr;
        }

        return true;
    }

    /**
     * @param null|string $instance_id Instance ID to check in database
     * @param bool $force True if we should skip caching
     *
     * @return null|array Array containing database fields of given instance_id (if available)
     */
    public function get_plugins_db_details(?string $instance_id = null, bool $force = false) : ?array
    {
        $this->reset_error();

        if ($instance_id !== null
         && !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_INSTANCE, self::_t('Invalid instance ID.'));

            return null;
        }

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_INSTANCE, self::_t('Unknown instance ID.'));

            return null;
        }

        if (!empty($force)
         && !empty(self::$db_plugins[$instance_id])) {
            unset(self::$db_plugins[$instance_id]);
        }

        // Cache all plugin details at once instead of caching one at a time...
        $this->_cache_all_db_details($force);

        if (!empty(self::$db_plugins[$instance_id])) {
            return self::$db_plugins[$instance_id];
        }

        $check_arr = [];
        $check_arr['instance_id'] = $instance_id;

        db_supress_errors($this->get_db_connection());
        if (!($flow_arr = $this->fetch_default_flow_params(['table_name' => 'plugins']))
         || !($db_details = $this->get_details_fields($check_arr, $flow_arr))) {
            db_restore_errors_state($this->get_db_connection());

            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS,
                    self::_t('Couldn\'t find plugin settings in database. Try re-installing plugin.'));
            }

            return null;
        }

        db_restore_errors_state($this->get_db_connection());

        self::$db_plugins[$instance_id] = $db_details;

        return $db_details;
    }

    /**
     * @param string $instance_id
     *
     * @return null|array
     */
    public function act_activate(string $instance_id) : ?array
    {
        $this->reset_error();

        $plugin_details = [];
        $plugin_details['status'] = self::STATUS_ACTIVE;

        return $this->_update_db_details($instance_id, $plugin_details);
    }

    /**
     * @param string $instance_id
     *
     * @return null|array
     */
    public function act_inactivate(string $instance_id) : ?array
    {
        $this->reset_error();

        $plugin_details = [];
        $plugin_details['status'] = self::STATUS_INACTIVE;

        return $this->_update_db_details($instance_id, $plugin_details);
    }

    public function install_record($instance_id, $plugin, $plugin_name, $type, $is_core, $def_settings, $version) : ?array
    {
        $this->reset_error();

        $plugin_details = [];
        $plugin_details['plugin'] = $plugin;
        $plugin_details['plugin_name'] = $plugin_name;
        $plugin_details['type'] = $type;
        $plugin_details['is_core'] = ($is_core ? 1 : 0);
        $plugin_details['settings'] = PHS_Line_params::to_string($def_settings);
        $plugin_details['version'] = $version;
        $plugin_details['status'] = self::STATUS_INSTALLED;

        return $this->_update_db_details($instance_id, $plugin_details);
    }

    public function update_record($instance_id, $plugin_name, $is_core, $version) : ?array
    {
        $this->reset_error();

        $plugin_details = [];
        $plugin_details['plugin_name'] = $plugin_name;
        $plugin_details['is_core'] = ($is_core ? 1 : 0);
        $plugin_details['version'] = $version;

        return $this->_update_db_details($instance_id, $plugin_details);
    }

    /**
     * @param null|string $instance_id Instance ID to delete from database
     *
     * @return bool True on success, false on failure
     */
    public function delete_db_registry($instance_id = null)
    {
        $this->reset_error();

        if ($instance_id !== null
         && !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Invalid instance ID.'));

            return false;
        }

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Unknown instance ID.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']))
         || !($table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Error obtaining flow details.'));

            return false;
        }

        if (!db_query('DELETE FROM `'.$table_name.'` WHERE instance_id = \''.$instance_id.'\' LIMIT 1', $flow_params['db_connection'])) {
            PHS_Logger::error('Error deleting registry entry for instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_REGISTRY, self::_t('Error deleting registry database entry for instance %s.', $instance_id));

            return false;
        }

        if (isset(self::$plugin_registry[$instance_id])) {
            unset(self::$plugin_registry[$instance_id]);
        }
        if (isset(self::$db_registry[$instance_id])) {
            unset(self::$db_registry[$instance_id]);
        }

        return true;
    }

    /**
     * @param null|string $instance_id Instance ID to check in database
     * @param bool $force True if we should skip caching
     *
     * @return null|array Array containing database registry fields of given instance_id (if available)
     */
    public function get_db_registry(?string $instance_id = null, bool $force = false) : ?array
    {
        $this->reset_error();

        if ($instance_id !== null
         && !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_INSTANCE, self::_t('Invalid instance ID.'));

            return null;
        }

        if ($instance_id === null
         && !($instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_INSTANCE, self::_t('Unknown instance ID.'));

            return null;
        }

        // Cache all plugin registry at once instead of caching one at a time...
        $this->cache_all_db_registry_details($force);

        if (!empty(self::$db_registry[$instance_id])) {
            return self::$db_registry[$instance_id];
        }

        // This record might be invalidated in other methods, altough it exists in database
        $check_arr = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']);
        $check_arr['instance_id'] = $instance_id;

        db_supress_errors($this->get_db_connection());
        if (!($db_details = $this->get_details_fields($check_arr))) {
            db_restore_errors_state($this->get_db_connection());

            return null;
        }

        db_restore_errors_state($this->get_db_connection());

        self::$db_registry[$instance_id] = $db_details;

        return $db_details;
    }

    public function update_db_registry($fields_arr)
    {
        if (empty($fields_arr) || !is_array($fields_arr)
         || empty($fields_arr['instance_id'])
         || !self::valid_instance_id($fields_arr['instance_id'])
         || !($params = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unknown instance database details.'));

            return false;
        }

        $check_arr = [];
        $check_arr['instance_id'] = $fields_arr['instance_id'];

        $params['fields'] = $fields_arr;

        if (!($existing_arr = $this->get_details_fields($check_arr, $params))) {
            $existing_arr = null;
            $params['action'] = 'insert';
        } else {
            $params['action'] = 'edit';
        }

        PHS_Logger::notice('Plugins model registry action ['.$params['action'].'] on plugin ['
                           .$fields_arr['instance_id'].']', PHS_Logger::TYPE_MAINTENANCE);

        if (!($validate_fields = $this->validate_data_for_fields($params))
         || empty($validate_fields['data_arr'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS, self::_t('Error validating plugin registry database fields.'));
            }

            return false;
        }

        $cdate = date(self::DATETIME_DB);

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating registry...
        if (!empty($new_fields_arr['registry'])) {
            if (!empty($existing_arr['registry'])) {
                $new_fields_arr['registry']
                    = self::merge_array_assoc($this->_decode_registry_field($existing_arr['registry']),
                        $this->_decode_registry_field($new_fields_arr['registry']));
            }

            $new_fields_arr['registry'] = $this->_encode_registry_field($new_fields_arr['registry']);

            $new_fields_arr['last_update'] = $cdate;

            PHS_Logger::notice('New registry ['.$new_fields_arr['registry'].']', PHS_Logger::TYPE_MAINTENANCE);
        }

        $details_arr = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']);
        $details_arr['fields'] = $new_fields_arr;

        if (empty($existing_arr)) {
            $details_arr['fields']['cdate'] = $cdate;

            $plugin_registry_arr = $this->insert($details_arr);
        } else {
            $plugin_registry_arr = $this->edit($existing_arr, $details_arr);
        }

        if (empty($plugin_registry_arr)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS, self::_t('Couldn\'t save plugin registry to database.'));
            }

            PHS_Logger::error('!!! Error in plugins registry model action ['.$params['action'].'] on plugin ['
                              .$fields_arr['instance_id'].'] ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE);

            return false;
        }

        PHS_Logger::notice('DONE Plugins registry model action ['.$params['action'].'] on plugin ['
                           .$fields_arr['instance_id'].']', PHS_Logger::TYPE_MAINTENANCE);

        self::$db_registry[$fields_arr['instance_id']] = $plugin_registry_arr;

        $return_arr = [];
        $return_arr['old_data'] = $existing_arr;
        $return_arr['new_data'] = $plugin_registry_arr;

        return $return_arr;
    }

    final public function check_install_plugins_db() : bool
    {
        static $check_result = null;

        if ($check_result !== null) {
            return $check_result;
        }

        PHS_Maintenance::lock_db_structure_read();

        if ($this->check_table_exists(['table_name' => 'plugins'])
         && $this->check_table_exists(['table_name' => 'plugins_registry'])) {
            PHS_Maintenance::unlock_db_structure_read();

            $check_result = true;

            return true;
        }

        $this->reset_error();

        $check_result = $this->install();

        PHS_Maintenance::unlock_db_structure_read();

        return $check_result;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false)
    {
        // $params should be flow parameters...
        if (empty($params) || !is_array($params)
         || empty($params['table_name'])) {
            return false;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'plugins':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'instance_id' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'type' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 100,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'plugin_name' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                    ],
                    'added_by' => [
                        'type'     => self::FTYPE_INT,
                        'editable' => false,
                    ],
                    'is_core' => [
                        'type'     => self::FTYPE_TINYINT,
                        'length'   => 2,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'settings' => [
                        'type' => self::FTYPE_LONGTEXT,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'version' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 30,
                    ],
                    'cdate' => [
                        'type'     => self::FTYPE_DATETIME,
                        'editable' => false,
                    ],
                ];
                break;

            case 'plugins_registry':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'instance_id' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'registry' => [
                        'type' => self::FTYPE_LONGTEXT,
                    ],
                    'last_update' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type'     => self::FTYPE_DATETIME,
                        'editable' => false,
                    ],
                ];
                break;

            case 'plugins_tenants':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'tenant_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'instance_id' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'type' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 100,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'editable' => false,
                        'index'    => true,
                    ],
                    'settings' => [
                        'type' => self::FTYPE_LONGTEXT,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_update' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type'     => self::FTYPE_DATETIME,
                        'editable' => false,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    /**
     * @param false|array $instance_details
     */
    protected function _do_construct($instance_details = false) : void
    {
        parent::_do_construct($instance_details);

        $this->_reset_db_plugin_cache();
        $this->_reset_plugin_settings_cache();
        $this->_reset_db_registry_cache();
        $this->_reset_plugin_registry_cache();
    }

    /**
     * @param string $instance_id
     * @param array $fields_arr
     *
     * @return null|array
     */
    protected function _update_db_details(string $instance_id, array $fields_arr) : ?array
    {
        if (empty($fields_arr)
         || empty($instance_id)
         || !($instance_details = self::valid_instance_id($instance_id))
         || !($params = $this->fetch_default_flow_params(['table_name' => 'plugins']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unknown instance database details.'));

            return null;
        }

        if (!($existing_arr = $this->get_plugins_db_details($instance_id))) {
            $existing_arr = null;
            $params['action'] = 'insert';
            $fields_arr['instance_id'] = $instance_id;
        } else {
            $params['action'] = 'edit';
        }

        $params['fields'] = $fields_arr;

        PHS_Logger::notice('Plugins model action ['.$params['action'].'] on instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

        if (!($validate_fields = $this->validate_data_for_fields($params))
         || empty($validate_fields['data_arr'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS, self::_t('Error validating plugin database fields.'));
            }

            return null;
        }

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating settings...
        if (!empty($new_fields_arr['settings'])) {
            $new_fields_arr['settings'] = $this->_encode_settings_field($new_fields_arr['settings']);

            PHS_Logger::notice('New settings ['.$new_fields_arr['settings'].']', PHS_Logger::TYPE_MAINTENANCE);
        }

        // Prevent core plugins to be inactivated...
        if (!empty($new_fields_arr['is_core']) && !empty($new_fields_arr['status'])) {
            $new_fields_arr['status'] = self::STATUS_ACTIVE;
        }

        $details_arr = [];
        $details_arr['fields'] = $new_fields_arr;

        if (empty($existing_arr)) {
            $plugin_arr = $this->insert($details_arr);
        } else {
            $plugin_arr = $this->edit($existing_arr, $details_arr);
        }

        if (empty($plugin_arr)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_DB_DETAILS, self::_t('Couldn\'t save plugin details to database.'));
            }

            PHS_Logger::error('!!! Error in plugins model action ['.$params['action'].'] on instance ['.$instance_id.'] ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE);

            return null;
        }

        PHS_Logger::notice('DONE Plugins model action ['.$params['action'].'] on instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

        self::$db_plugins[$instance_id] = $plugin_arr;

        $return_arr = [];
        $return_arr['old_data'] = $existing_arr;
        $return_arr['new_data'] = $plugin_arr;

        return $return_arr;
    }

    protected function get_insert_prepare_params_plugins($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_INSTALLED;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid plugin status.'));

            return false;
        }

        if (empty($params['fields']['instance_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a plugin id.'));

            return false;
        }

        $check_params = $params;
        $check_params['result_type'] = 'single';

        $check_arr = [];
        $check_arr['instance_id'] = $params['fields']['instance_id'];

        if ($this->get_details_fields($check_arr, $check_params)) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a plugin with this id in database.'));

            return false;
        }

        if (empty($params['fields']['plugin_name'])) {
            $params['fields']['plugin_name'] = '';
        }

        $now_date = date(self::DATETIME_DB);

        $params['fields']['status_date'] = $now_date;

        if (empty($params['fields']['cdate']) || empty_db_date($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $now_date;
        } else {
            $params['fields']['cdate'] = date(self::DATETIME_DB, parse_db_date($params['fields']['cdate']));
        }

        return $params;
    }

    protected function get_edit_prepare_params_plugins($existing_arr, $params)
    {
        if (empty($existing_arr) || !is_array($existing_arr)
         || empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['status'])
         && !$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid plugin status.'));

            return false;
        }

        if (!empty($params['fields']['instance_id'])) {
            $check_params = $params;
            $check_params['result_type'] = 'single';

            $check_arr = [];
            $check_arr['instance_id'] = $params['fields']['instance_id'];
            $check_arr['id'] = ['check' => '!=', 'value' => $existing_arr['id']];

            if ($this->get_details_fields($check_arr, $check_params)) {
                $this->set_error(self::ERR_INSERT, self::_t('There is already a plugin with this id in database.'));

                return false;
            }
        }

        $now_date = date(self::DATETIME_DB);

        if (isset($params['fields']['plugin_name'])
         && empty($params['fields']['plugin_name'])) {
            $params['fields']['plugin_name'] = '';
        }

        if (isset($params['fields']['status'])
         && empty($params['fields']['status_date'])) {
            $params['fields']['status_date'] = $now_date;
        } elseif (!empty($params['fields']['status_date'])) {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        if (!empty($params['fields']['cdate'])) {
            $params['fields']['cdate'] = date(self::DATETIME_DB, parse_db_date($params['fields']['cdate']));
        }

        return $params;
    }

    /**
     * Cache all plugin records from plugins table...
     * @param bool $force
     *
     * @return bool
     */
    private function _cache_all_db_details(bool $force = false) : bool
    {
        $this->reset_error();

        if (!empty($force)
         && !empty(self::$db_plugins)) {
            $this->_reset_db_plugin_cache();
        }

        if (!empty(self::$db_plugins)) {
            return true;
        }

        if (!($list_arr = $this->fetch_default_flow_params(['table_name' => 'plugins']))) {
            $this->set_error(self::ERR_DB_DETAILS, $this->_pt('Error preparing query to obtain plugins records.'));

            return false;
        }

        $list_arr['order_by'] = 'is_core DESC';

        $this->_reset_db_plugin_cache();

        db_supress_errors($list_arr['db_connection']);
        if (!($all_db_plugins = $this->get_list($list_arr))) {
            db_restore_errors_state($list_arr['db_connection']);

            return true;
        }
        db_restore_errors_state($list_arr['db_connection']);

        foreach ($all_db_plugins as $db_arr) {
            if (empty($db_arr['instance_id'])) {
                continue;
            }

            self::$db_plugins[$db_arr['instance_id']] = $db_arr;
        }

        if (!empty(self::$db_plugins)) {
            $this->_redo_db_plugins_records_cache(false);
        }

        return true;
    }

    private function _redo_db_plugins_records_cache(bool $force = false) : bool
    {
        if (!empty(self::$db_plugin_plugins)
            && empty($force)) {
            return true;
        }

        self::$db_plugin_plugins = [];
        self::$db_plugin_active_plugins = [];
        if ((!empty($force) || empty(self::$db_plugins))
            && !$this->_cache_all_db_details($force)) {
            return true;
        }

        foreach (self::$db_plugins as $plugin_arr) {
            if ($plugin_arr['type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
             || empty($plugin_arr['plugin'])) {
                continue;
            }

            self::$db_plugin_plugins[$plugin_arr['plugin']] = $plugin_arr;

            if ($this->is_active($plugin_arr)) {
                self::$db_plugin_active_plugins[$plugin_arr['plugin']] = $plugin_arr;
            }
        }

        return true;
    }

    /**
     * @param array|string $settings
     *
     * @return null|string
     */
    private function _encode_settings_field($settings) : ?string
    {
        if (is_array($settings)) {
            $settings = PHS_Line_params::to_string($settings);
        } elseif (is_string($settings)) {
            $settings = PHS_Line_params::to_string(PHS_Line_params::parse_string($settings));
        } else {
            return null;
        }

        return $settings;
    }

    /**
     * @param array|string $settings
     *
     * @return array
     */
    private function _decode_settings_field($settings) : array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (is_string($settings)) {
            $settings = PHS_Line_params::parse_string($settings);
        } else {
            return [];
        }

        return $settings;
    }

    /**
     * @param array|string $registry
     *
     * @return null|string
     */
    private function _encode_registry_field($registry) : ?string
    {
        if (is_array($registry)) {
            $registry = PHS_Line_params::to_string($registry);
        } elseif (is_string($registry)) {
            $registry = PHS_Line_params::to_string(PHS_Line_params::parse_string($registry));
        } else {
            return null;
        }

        return $registry;
    }

    /**
     * @param array|string $registry
     *
     * @return array
     */
    private function _decode_registry_field($registry) : array
    {
        if (is_array($registry)) {
            return $registry;
        }

        if (is_string($registry)) {
            $registry = PHS_Line_params::parse_string($registry);
        } else {
            return [];
        }

        return $registry;
    }

    private function _reset_plugin_settings_cache() : void
    {
        self::$plugin_settings = [];
    }

    private function _reset_db_plugin_cache() : void
    {
        self::$db_plugins = [];
        self::$db_plugin_plugins = [];
        self::$db_plugin_active_plugins = [];
    }

    private function _reset_plugin_registry_cache() : void
    {
        self::$plugin_registry = [];
    }

    private function _reset_db_registry_cache() : void
    {
        self::$db_registry = [];
    }
}
