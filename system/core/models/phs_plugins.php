<?php
namespace phs\system\core\models;

use phs\PHS;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Line_params;
use phs\libraries\PHS_Record_data;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Model_Core_base;
use phs\traits\PHS_Model_Trait_statuses;
use phs\system\core\events\plugins\PHS_Event_Plugin_registry;

class PHS_Model_Plugins extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const ERR_FORCE_INSTALL = 100, ERR_DB_DETAILS = 101, ERR_DIR_DETAILS = 102, ERR_REGISTRY = 103;

    public const STATUS_INSTALLED = 1, STATUS_ACTIVE = 2, STATUS_INACTIVE = 3;

    protected static array $STATUSES_ARR = [
        self::STATUS_INSTALLED => ['title' => 'Installed'],
        self::STATUS_ACTIVE    => ['title' => 'Active'],
        self::STATUS_INACTIVE  => ['title' => 'Inactive'],
    ];

    // Cached database plugins rows
    private static array $db_plugins = [];

    // Cached database tenant plugins rows
    private static ?array $db_tenant_plugins = null;

    // Cached database plugin records which are ACTIVE plugins in framework
    private static array $db_plugin_active_plugins = [];

    // Cached database plugin records which are ACTIVE plugins for specific tenant
    private static array $db_plugin_active_tenant_plugins = [];

    // Cached database plugin records which are plugins in framework
    private static array $db_plugin_plugins = [];

    // Cached database plugin records which are plugins for specific tenant
    private static array $db_plugin_tenant_plugins = [];

    // Cached plugin settings
    private static array $plugin_settings = [];

    // Cached plugin tenant settings
    private static array $plugin_tenant_settings = [];

    // Cached database registry rows
    private static array $db_registry = [];

    // Cached plugin registry
    private static array $plugin_registry = [];

    // Cached directory rows
    private static array $dir_plugins = [];

    public function get_model_version() : string
    {
        return '1.3.0';
    }

    public function get_table_names() : array
    {
        return ['plugins', 'plugins_registry', 'plugins_tenants'];
    }

    public function get_main_table_name() : string
    {
        return 'plugins';
    }

    public function active_status(int $status) : bool
    {
        return $status === self::STATUS_ACTIVE;
    }

    public function inactive_status(int $status) : bool
    {
        return in_array($status, [self::STATUS_INSTALLED, self::STATUS_INACTIVE], true);
    }

    public function is_active(int | array | PHS_Record_data $plugin_data) : bool
    {
        return $plugin_data
               && ($plugin_arr = $this->data_to_array($plugin_data))
               && (int)$plugin_arr['status'] === self::STATUS_ACTIVE;
    }

    public function is_inactive(int | array | PHS_Record_data $plugin_data) : bool
    {
        return $plugin_data
               && ($plugin_arr = $this->data_to_array($plugin_data))
               && $this->inactive_status($plugin_arr['status']);
    }

    public function is_status_inactive(int | array | PHS_Record_data $plugin_data) : bool
    {
        return $plugin_data
               && ($plugin_arr = $this->data_to_array($plugin_data))
               && (int)$plugin_arr['status'] === self::STATUS_INACTIVE;
    }

    public function is_installed(int | array | PHS_Record_data $plugin_data) : bool
    {
        return $plugin_data
               && ($plugin_arr = $this->data_to_array($plugin_data))
               && (int)$plugin_arr['status'] === self::STATUS_INSTALLED;
    }

    public function is_active_on_tenant(string $instance_id, int $tenant_id) : bool
    {
        return $instance_id
               && ($record_arr = $this->get_details_fields(
                   ['instance_id' => $instance_id, 'tenant_id' => $tenant_id],
                   ['table_name' => 'plugins_tenants'])
               )
               && (int)$record_arr['status'] === self::STATUS_ACTIVE;
    }

    public function is_inactive_on_tenant(string $instance_id, int $tenant_id) : bool
    {
        return $instance_id
               && ($record_arr = $this->get_details_fields(
                   ['instance_id' => $instance_id, 'tenant_id' => $tenant_id],
                   ['table_name' => 'plugins_tenants'])
               )
               && $this->inactive_status($record_arr['status']);
    }

    public function is_status_inactive_on_tenant(string $instance_id, int $tenant_id) : bool
    {
        return $instance_id
               && ($record_arr = $this->get_details_fields(
                   ['instance_id' => $instance_id, 'tenant_id' => $tenant_id],
                   ['table_name' => 'plugins_tenants'])
               )
               && (int)$record_arr['status'] === self::STATUS_INACTIVE;
    }

    public function get_status_of_tenant(string $instance_id, int $tenant_id) : int
    {
        if ($instance_id
            && ($record_arr = $this->get_details_fields(
                ['instance_id' => $instance_id, 'tenant_id' => $tenant_id],
                ['table_name' => 'plugins_tenants']))
        ) {
            return (int)$record_arr['status'];
        }

        return 0;
    }

    public function save_plugins_db_settings(?string $instance_id, array $settings_arr, int $tenant_id = 0, array $update_params = []) : ?array
    {
        if ($tenant_id === 0) {
            return $this->_save_plugins_db_main_settings($instance_id, $settings_arr, $update_params);
        }

        return $this->_save_plugins_db_tenant_settings($instance_id, $settings_arr, $tenant_id);
    }

    public function get_plugins_db_settings(
        string $instance_id,
        int $tenant_id = 0,
        bool $force = false,
    ) : array {
        $this->reset_error();

        $main_settings = $this->_get_plugins_db_main_settings($instance_id, $force) ?: [];

        if (!$tenant_id
            || !PHS::is_multi_tenant()
            || !($tenant_settings = $this->get_plugins_db_tenant_settings($instance_id, $tenant_id, $force))) {
            return $main_settings;
        }

        return self::merge_array_assoc_existing($main_settings, $tenant_settings);
    }

    public function save_plugins_db_registry(array $registry_arr, string $instance_id, int $tenant_id = 0) : ?array
    {
        $this->reset_error();

        if (!$instance_id
            || !($instance_details = self::valid_instance_id($instance_id))) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid instance ID.'));

            return null;
        }

        $plugin_details = [];
        $plugin_details['tenant_id'] = $tenant_id;
        $plugin_details['instance_id'] = $instance_id;
        $plugin_details['plugin'] = $instance_details['plugin_name'];
        $plugin_details['registry'] = $registry_arr;

        if (!($db_details = $this->_update_db_registry($plugin_details))
            || empty($db_details['new_data'])) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Error saving registry data in database.'));

            return null;
        }

        $db_registry_arr = [];
        if (!empty($db_details['new_data']['registry'])) {
            $db_registry_arr = $this->_decode_registry_field($db_details['new_data']['registry']);
        }

        self::$plugin_registry[$tenant_id][$instance_id]
            = $this->_populate_plugins_db_registry_from_event($instance_id, $tenant_id, $db_registry_arr);

        return self::$plugin_registry[$tenant_id][$instance_id];
    }

    public function get_plugins_db_registry(string $instance_id, int $tenant_id, bool $force = false) : ?array
    {
        $this->reset_error();

        if (!$instance_id
            || !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid instance ID.'));

            return null;
        }

        if (isset(self::$plugin_registry[$tenant_id][$instance_id])) {
            if (!$force) {
                return self::$plugin_registry[$tenant_id][$instance_id];
            }

            unset(self::$plugin_registry[$tenant_id][$instance_id]);
        }

        $db_registry_arr = [];
        if (($db_details = $this->get_db_registry($instance_id, $tenant_id, $force))
            && !empty($db_details['registry'])) {
            $db_registry_arr = $this->_decode_registry_field($db_details['registry']);
        }

        self::$plugin_registry[$tenant_id][$instance_id]
            = $this->_populate_plugins_db_registry_from_event($instance_id, $tenant_id, $db_registry_arr);

        return self::$plugin_registry[$tenant_id][$instance_id];
    }

    public function get_all_plugin_names_from_dir() : ?array
    {
        $this->reset_error();

        @clearstatcache();

        if (false === ($dirs_list = @glob(PHS_PLUGINS_DIR.'*', GLOB_ONLYDIR))
            || !is_array($dirs_list)) {
            $this->set_error(self::ERR_DIR_DETAILS, self::_t('Couldn\'t get a list of plugin directories.'));

            return null;
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

    public function get_all_records_for_paginator() : array
    {
        $records_arr = [];

        $records_arr[] = $this->_get_core_record_for_paginator();

        if (!($dir_entries = $this->cache_all_dir_details())) {
            return $records_arr;
        }

        foreach ($dir_entries as $plugin_instance) {
            if (!($record_arr = $this->get_record_details_from_instance_for_paginator($plugin_instance))) {
                continue;
            }

            $records_arr[] = $record_arr;
        }

        return $records_arr;
    }

    public function get_record_details_from_name_for_paginator(string $plugin_name) : ?array
    {
        if (!($plugin_instance = PHS::load_plugin($plugin_name))) {
            return null;
        }

        return $this->get_record_details_from_instance_for_paginator($plugin_instance);
    }

    public function get_record_details_from_instance_for_paginator(PHS_Plugin $plugin_instance) : ?array
    {
        if (!($plugin_info_arr = $plugin_instance->get_plugin_info())) {
            return null;
        }

        $record_arr = [];
        $record_arr['id'] = $plugin_info_arr['id'];
        $record_arr['plugin_name'] = $plugin_info_arr['plugin_name'];
        $record_arr['vendor_id'] = $plugin_info_arr['vendor_id'];
        $record_arr['vendor_name'] = $plugin_info_arr['vendor_name'];
        $record_arr['name'] = $plugin_info_arr['name'];
        $record_arr['description'] = $plugin_info_arr['description'];
        $record_arr['version'] = $plugin_info_arr['db_version'].' / '.$plugin_info_arr['script_version'];
        $record_arr['status'] = (int)($plugin_info_arr['db_details']['status'] ?? -1);
        $record_arr['status_date'] = (!empty($plugin_info_arr['db_details']) ? $plugin_info_arr['db_details']['status_date'] : null);
        $record_arr['cdate'] = (!empty($plugin_info_arr['db_details']) ? $plugin_info_arr['db_details']['cdate'] : null);
        $record_arr['models'] = ((!empty($plugin_info_arr['models']) && is_array($plugin_info_arr['models'])) ? $plugin_info_arr['models'] : []);
        $record_arr['is_installed'] = $plugin_info_arr['is_installed'];
        $record_arr['is_upgradable'] = $plugin_info_arr['is_upgradable'];
        $record_arr['is_core'] = $plugin_info_arr['is_core'];
        $record_arr['is_always_active'] = $plugin_info_arr['is_always_active'];
        $record_arr['is_distribution'] = $plugin_info_arr['is_distribution'];
        $record_arr['is_multi_tenant'] = $plugin_info_arr['is_multi_tenant'];
        $record_arr['tenants'] = $this->get_tenants_ids_for_plugin_name($plugin_info_arr['plugin_name']);

        return $record_arr;
    }

    /**
     * Get plugin names and instances as key value pairs
     *
     * @param bool $force Force plugins recheck
     *
     * @return null|array<string, PHS_Plugin> False on error or array with plugin name as key and plugin instance as value
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

        if (false === ($dirs_list = @glob(PHS_PLUGINS_DIR.'*', GLOB_ONLYDIR))
            || !is_array($dirs_list)) {
            $this->set_error(self::ERR_DIR_DETAILS, self::_t('Couldn\'t get a list of plugin directories.'));

            return null;
        }

        foreach ($dirs_list as $dir_name) {
            if (!($dir_name = basename($dir_name))
                || !($plugin_instance = PHS::load_plugin($dir_name))) {
                continue;
            }

            self::$dir_plugins[$dir_name] = $plugin_instance;
        }

        return self::$dir_plugins;
    }

    public function plugin_name_is_instantiable(string $plugin_name) : ?PHS_Plugin
    {
        $this->cache_all_dir_details();

        return self::$dir_plugins[$plugin_name] ?? null;
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
            || !self::$db_plugins) {
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

    public function get_all_active_plugins(bool $force = false) : array
    {
        $this->_cache_all_db_details($force);

        return self::$db_plugin_active_plugins;
    }

    public function get_all_plugins(bool $force = false) : array
    {
        $this->_cache_all_db_details($force);

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

        if (!empty(self::$db_registry)) {
            if (!$force) {
                return true;
            }

            self::$db_registry = [];
        }

        db_supress_errors($this->get_db_connection());
        if (!($all_db_registry = $this->get_list($this->fetch_default_flow_params(['table_name' => 'plugins_registry'])))) {
            db_restore_errors_state($this->get_db_connection());
            self::$db_registry = [];

            return true;
        }
        db_restore_errors_state($this->get_db_connection());

        foreach ($all_db_registry as $db_arr) {
            if (empty($db_arr['instance_id'])) {
                continue;
            }

            self::$db_registry[(int)($db_arr['tenant_id'] ?? 0)][$db_arr['instance_id']] = $db_arr;
        }

        return true;
    }

    public function get_plugins_db_main_details(?string $instance_id = null, bool $force = false) : ?array
    {
        $this->reset_error();

        $instance_id ??= $this->instance_id();
        if (!self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_INSTANCE, self::_t('Invalid instance ID.'));

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

            $this->set_error_if_not_set(self::ERR_DB_DETAILS,
                self::_t('Couldn\'t find plugin settings in database. Try re-installing plugin.'));

            return null;
        }

        db_restore_errors_state($this->get_db_connection());

        self::$db_plugins[$instance_id] = $db_details;

        return $db_details;
    }

    public function get_plugins_db_tenant_details(?string $instance_id = null, int $tenant_id = 0, bool $force = false) : ?array
    {
        $this->reset_error();

        if (!PHS::is_multi_tenant()) {
            return null;
        }

        $instance_id ??= $this->instance_id();
        if (!self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_INSTANCE, self::_t('Invalid instance ID.'));

            return null;
        }

        // Cache all plugin details at once instead of caching one at a time...
        $this->_cache_all_db_details($force);

        if (!empty(self::$db_tenant_plugins[$tenant_id][$instance_id])) {
            return self::$db_tenant_plugins[$tenant_id][$instance_id];
        }

        return null;
    }

    public function act_activate(string $instance_id) : ?array
    {
        return $this->_update_db_details($instance_id, ['status' => self::STATUS_ACTIVE]);
    }

    public function act_inactivate(string $instance_id) : ?array
    {
        return $this->_update_db_details($instance_id, ['status' => self::STATUS_INACTIVE]);
    }

    public function act_activate_on_tenant(string $instance_id, int $tenant_id) : ?array
    {
        return $this->_update_db_tenant_details($instance_id, ['status' => self::STATUS_ACTIVE], $tenant_id);
    }

    public function act_inactivate_on_tenant(string $instance_id, int $tenant_id) : ?array
    {
        return $this->_update_db_tenant_details($instance_id, ['status' => self::STATUS_INACTIVE], $tenant_id);
    }

    public function install_record($instance_id, $plugin, $plugin_name, $type, $is_core, $def_settings, $version) : ?array
    {
        $plugin_details = [];
        $plugin_details['plugin'] = $plugin;
        $plugin_details['plugin_name'] = $plugin_name;
        $plugin_details['type'] = $type;
        $plugin_details['is_core'] = ($is_core ? 1 : 0);
        $plugin_details['settings'] = $this->_encode_settings_field($def_settings);
        $plugin_details['version'] = $version;
        $plugin_details['status'] = self::STATUS_INSTALLED;

        return $this->_update_db_details($instance_id, $plugin_details);
    }

    public function update_record(string $instance_id, string $plugin_name, bool $is_core, string $version) : ?array
    {
        $plugin_details = [];
        $plugin_details['plugin_name'] = $plugin_name;
        $plugin_details['is_core'] = ($is_core ? 1 : 0);
        $plugin_details['version'] = $version;

        return $this->_update_db_details($instance_id, $plugin_details);
    }

    public function delete_db_registry(string $instance_id, int $tenant_id) : bool
    {
        $this->reset_error();

        if (!$instance_id
            || !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Invalid instance ID.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']))
         || !($table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Error obtaining flow details.'));

            return false;
        }

        if (!db_query('DELETE FROM `'.$table_name.'` WHERE instance_id = \''.$instance_id.'\' '
                      .' AND tenant_id = \''.$tenant_id.'\' LIMIT 1', $flow_params['db_connection'])) {
            PHS_Logger::error('Error deleting registry entry for tenant ['.$tenant_id.'] '
                              .'instance ['.$instance_id.']',
                PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_REGISTRY,
                self::_t('Error deleting registry database entry for instance %s.', $instance_id));

            return false;
        }

        if (isset(self::$plugin_registry[$tenant_id][$instance_id])) {
            unset(self::$plugin_registry[$tenant_id][$instance_id]);
        }
        if (isset(self::$db_registry[$tenant_id][$instance_id])) {
            unset(self::$db_registry[$tenant_id][$instance_id]);
        }

        return true;
    }

    public function delete_all_db_registry(string $instance_id) : bool
    {
        $this->reset_error();

        if (!$instance_id
            || !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Invalid instance ID.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']))
            || !($table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Error obtaining flow details.'));

            return false;
        }

        if (!db_query('DELETE FROM `'.$table_name.'` WHERE instance_id = \''.$instance_id.'\'', $flow_params['db_connection'])) {
            PHS_Logger::error('Error deleting ALL registry entries for instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_REGISTRY, self::_t('Error deleting registry database entries for instance %s.', $instance_id));

            return false;
        }

        $this->_reset_all_plugin_registry_cache();

        return true;
    }

    public function get_db_registry(string $instance_id, int $tenant_id = 0, bool $force = false) : ?array
    {
        $this->reset_error();

        if (!$instance_id
            || !self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_REGISTRY, self::_t('Invalid instance ID.'));

            return null;
        }

        // Cache all plugin registry at once instead of caching one at a time...
        $this->cache_all_db_registry_details($force);

        if (!empty(self::$db_registry[$tenant_id][$instance_id])) {
            return self::$db_registry[$tenant_id][$instance_id];
        }

        // This record might be invalidated in other methods, altough it exists in database
        $check_arr = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']);
        $check_arr['instance_id'] = $instance_id;
        $check_arr['tenant_id'] = $tenant_id;

        db_supress_errors($this->get_db_connection());
        if (!($db_details = $this->get_details_fields($check_arr))) {
            db_restore_errors_state($this->get_db_connection());

            return null;
        }

        db_restore_errors_state($this->get_db_connection());

        self::$db_registry[$tenant_id][$instance_id] = $db_details;

        return $db_details;
    }

    final public function check_install_plugins_db() : bool
    {
        static $check_result = null;

        if ($check_result !== null) {
            return $check_result;
        }

        PHS_Maintenance::lock_db_structure_read();

        if ($this->check_table_exists(['table_name' => 'plugins'])
            && $this->check_table_exists(['table_name' => 'plugins_registry'])
            && $this->check_table_exists(['table_name' => 'plugins_tenants'])) {
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
    final public function fields_definition($params = false) : ?array
    {
        // $params should be flow parameters...
        if (empty($params['table_name'])) {
            return null;
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
                    'last_update' => [
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

    public function get_tenants_ids_for_plugin_name(string $plugin_name) : array
    {
        return $this->get_tenants_ids_by_plugin_name()[$plugin_name] ?? [];
    }

    public function get_tenants_ids_by_plugin_name() : ?array
    {
        static $tenant_ids_by_plugin_name = null;

        if ($tenant_ids_by_plugin_name !== null) {
            return $tenant_ids_by_plugin_name;
        }

        if (!$this->_cache_tenants_db_details()) {
            return null;
        }

        if (empty(self::$db_tenant_plugins)) {
            return [];
        }

        $tenant_ids_by_plugin_name = [];
        foreach (self::$db_tenant_plugins as $tenant_id => $tenant_plugins_arr) {
            foreach ($tenant_plugins_arr as $instance_id => $db_arr) {
                if (empty($db_arr['plugin'])) {
                    continue;
                }

                if (empty($tenant_ids_by_plugin_name[$db_arr['plugin']])) {
                    $tenant_ids_by_plugin_name[$db_arr['plugin']] = [];
                }

                $tenant_ids_by_plugin_name[$db_arr['plugin']][] = (int)$tenant_id;
            }
        }

        return $tenant_ids_by_plugin_name;
    }

    public function get_plugins_db_tenant_settings(
        string $instance_id,
        int $tenant_id,
        bool $force = false,
    ) : ?array {
        $this->reset_error();

        if (!PHS::is_multi_tenant()) {
            return null;
        }

        if (!empty($force)
         && isset(self::$plugin_tenant_settings[$tenant_id][$instance_id])) {
            unset(self::$plugin_tenant_settings[$tenant_id][$instance_id]);
        }

        if (isset(self::$plugin_tenant_settings[$tenant_id][$instance_id])) {
            return self::$plugin_tenant_settings[$tenant_id][$instance_id];
        }

        if (!($db_details = $this->get_plugins_db_tenant_details($instance_id, $tenant_id, $force))) {
            return null;
        }

        if (empty($db_details['settings'])) {
            self::$plugin_tenant_settings[$tenant_id][$instance_id] = [];
        } else {
            // parse settings in database...
            self::$plugin_tenant_settings[$tenant_id][$instance_id] = $this->_decode_settings_field($db_details['settings']);
        }

        return self::$plugin_tenant_settings[$tenant_id][$instance_id];
    }

    protected function _do_construct(array $instance_details = []) : void
    {
        parent::_do_construct($instance_details);

        $this->_reset_all_plugin_cache();
        $this->_reset_plugin_settings_cache();
        $this->_reset_db_registry_cache();
        $this->_reset_plugin_registry_cache();
    }

    protected function _update_db_details(string $instance_id, array $fields_arr, int $tenant_id = 0) : ?array
    {
        if ($tenant_id === 0) {
            return $this->_update_db_main_details($instance_id, $fields_arr);
        }

        return $this->_update_db_tenant_details($instance_id, $fields_arr, $tenant_id);
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

        if ($this->get_details_fields(['instance_id' => $params['fields']['instance_id']])) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a plugin with this id in database.'));

            return false;
        }

        $now_date = date(self::DATETIME_DB);

        $params['fields']['last_update'] = $params['fields']['status_date'] = $now_date;

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
            $check_arr = [];
            $check_arr['instance_id'] = $params['fields']['instance_id'];
            $check_arr['id'] = ['check' => '!=', 'value' => $existing_arr['id']];

            if ($this->get_details_fields($check_arr, $params)) {
                $this->set_error(self::ERR_INSERT, self::_t('There is already a plugin with this id in database.'));

                return false;
            }
        }

        $now_date = date(self::DATETIME_DB);

        $params['fields']['last_update'] = $now_date;

        if (isset($params['fields']['plugin_name'])
            && empty($params['fields']['plugin_name'])) {
            $params['fields']['plugin_name'] = null;
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

    protected function get_insert_prepare_params_plugins_tenants($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_INSTALLED;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid tenant plugin status.'));

            return false;
        }

        if (empty($params['fields']['instance_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant plugin id.'));

            return false;
        }

        if ($this->get_details_fields(['instance_id' => $params['fields']['instance_id']])) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a tenant plugin with this id in database.'));

            return false;
        }

        $now_date = date(self::DATETIME_DB);

        $params['fields']['last_update'] = $params['fields']['status_date'] = $now_date;

        if (empty($params['fields']['cdate']) || empty_db_date($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $now_date;
        } else {
            $params['fields']['cdate'] = date(self::DATETIME_DB, parse_db_date($params['fields']['cdate']));
        }

        return $params;
    }

    protected function get_edit_prepare_params_plugins_tenants($existing_arr, $params)
    {
        if (empty($existing_arr) || !is_array($existing_arr)
         || empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['status'])
            && !$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid tenant plugin status.'));

            return false;
        }

        if (!empty($params['fields']['instance_id'])) {
            $check_arr = [];
            $check_arr['instance_id'] = $params['fields']['instance_id'];
            $check_arr['id'] = ['check' => '!=', 'value' => $existing_arr['id']];

            if ($this->get_details_fields($check_arr, $params)) {
                $this->set_error(self::ERR_INSERT, self::_t('There is already a tenant plugin with this id in database.'));

                return false;
            }
        }

        $now_date = date(self::DATETIME_DB);

        $params['fields']['last_update'] = $now_date;

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

    private function _populate_plugins_db_registry_from_event(string $instance_id, int $tenant_id, array $registry_arr) : array
    {
        if (($event_obj = PHS_Event_Plugin_registry::trigger([
            'instance_id'  => $instance_id,
            'tenant_id'    => $tenant_id,
            'registry_arr' => $registry_arr,
        ]))
            && ($new_registry_arr = $event_obj->get_output('registry_arr'))
            && is_array($new_registry_arr)
        ) {
            $registry_arr = self::validate_array_recursive($new_registry_arr, $registry_arr);
        }

        return $registry_arr;
    }

    private function _update_db_registry(array $fields_arr)
    {
        $this->reset_error();

        if (!$fields_arr
         || !isset($fields_arr['tenant_id'])
         || empty($fields_arr['instance_id'])
         || !self::valid_instance_id($fields_arr['instance_id'])
         || !($params = $this->fetch_default_flow_params(['table_name' => 'plugins_registry']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unknown instance database details.'));

            return false;
        }

        $tenant_id = (int)$fields_arr['tenant_id'];

        $check_arr = [];
        $check_arr['tenant_id'] = $tenant_id;
        $check_arr['instance_id'] = $fields_arr['instance_id'];

        $params['fields'] = $fields_arr;

        if (!($existing_arr = $this->get_details_fields($check_arr, $params))) {
            $existing_arr = null;
            $params['action'] = 'insert';
        } else {
            $params['action'] = 'edit';
        }

        PHS_Logger::notice('Plugins model registry action ['.$params['action'].'] on tenant ['.$tenant_id.'] instance ['
                           .$fields_arr['instance_id'].']', PHS_Logger::TYPE_MAINTENANCE);

        if (!($validate_fields = $this->validate_data_for_fields($params))
            || empty($validate_fields['data_arr'])) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Error validating plugin registry database fields.'));

            return false;
        }

        $cdate = date(self::DATETIME_DB);

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating registry...
        if (!empty($new_fields_arr['registry'])) {
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
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Couldn\'t save plugin registry to database.'));

            PHS_Logger::error('!!! Error in plugins registry model action ['.$params['action'].'] on tenant ['.$tenant_id.'] instance ['
                              .$fields_arr['instance_id'].'] ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE);

            return false;
        }

        PHS_Logger::notice('DONE Plugins registry model action ['.$params['action'].'] on tenant ['.$tenant_id.'] instance ['
                           .$fields_arr['instance_id'].']', PHS_Logger::TYPE_MAINTENANCE);

        self::$db_registry[$tenant_id][$fields_arr['instance_id']] = $plugin_registry_arr;

        return [
            'old_data' => $existing_arr,
            'new_data' => $plugin_registry_arr,
        ];
    }

    private function _save_plugins_db_main_settings(?string $instance_id, array $settings_arr, array $update_params = []) : ?array
    {
        $this->reset_error();

        $update_params['skip_merging_old_settings'] = !empty($update_params['skip_merging_old_settings']);

        $instance_id ??= $this->instance_id();
        if (!self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid instance ID.'));

            return null;
        }

        if (!$this->get_plugins_db_main_details($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Plugin is not yet installed.'));

            return null;
        }

        $old_settings = $this->_get_plugins_db_main_settings($instance_id, true) ?: [];

        // Nothing to save...
        if (!($settings_arr = $this->_decode_settings_field($settings_arr))) {
            return $old_settings;
        }

        if (empty($update_params['skip_merging_old_settings'])
            && !empty($old_settings)) {
            $settings_arr = self::merge_array_assoc($old_settings, $settings_arr);
        }

        if (!($db_details = $this->_update_db_details($instance_id, ['settings' => $settings_arr]))
            || empty($db_details['new_data'])) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Error saving settings in database.'));

            return null;
        }

        return $this->_get_plugins_db_main_settings($instance_id, true) ?: [];
    }

    private function _save_plugins_db_tenant_settings(?string $instance_id, array $settings_arr, int $tenant_id) : ?array
    {
        $this->reset_error();

        if (!PHS::is_multi_tenant()) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Multi-tenant is not active.'));

            return null;
        }

        $instance_id ??= $this->instance_id();
        if (!self::valid_instance_id($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid instance ID.'));

            return null;
        }

        if (!$this->get_plugins_db_main_details($instance_id)) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Plugin is not yet installed.'));

            return null;
        }

        // Nothing to save...
        if (!$settings_arr
            || !($new_settings_arr = $this->_decode_settings_field($settings_arr))) {
            $new_settings_arr = null;
        }

        if (!($db_details = $this->_update_db_tenant_details($instance_id, ['settings' => $new_settings_arr], $tenant_id))
            || empty($db_details['new_data'])) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Error saving tenant settings in database.'));

            return null;
        }

        return $this->get_plugins_db_tenant_settings($instance_id, $tenant_id, true) ?: [];
    }

    private function _get_plugins_db_main_settings(
        string $instance_id,
        bool $force = false,
    ) : ?array {
        $this->reset_error();

        if (!empty($force)
         && isset(self::$plugin_settings[$instance_id])) {
            unset(self::$plugin_settings[$instance_id]);
        }

        if (isset(self::$plugin_settings[$instance_id])) {
            return self::$plugin_settings[$instance_id];
        }

        if (!($db_details = $this->get_plugins_db_main_details($instance_id, $force))) {
            return null;
        }

        self::$plugin_settings[$instance_id] = empty($db_details['settings'])
            ? []
            : $this->_decode_settings_field($db_details['settings']);

        return self::$plugin_settings[$instance_id];
    }

    private function _get_core_record_for_paginator() : array
    {
        $core_details = PHS_Plugin::core_plugin_details_fields();

        $record_arr = [];
        $record_arr['id'] = $core_details['id'];
        $record_arr['plugin_name'] = $core_details['plugin_name'];
        $record_arr['vendor_id'] = $core_details['vendor_id'];
        $record_arr['vendor_name'] = $core_details['vendor_name'];
        $record_arr['name'] = $core_details['name'];
        $record_arr['description'] = $core_details['description'];
        $record_arr['version'] = $core_details['db_version'].' / '.$core_details['script_version'];
        $record_arr['status'] = $core_details['status'];
        $record_arr['status_date'] = date(PHS_Model_Core_base::DATETIME_DB, @filemtime(PHS_PATH.'bootstrap.php'));
        $record_arr['cdate'] = $record_arr['status_date'];
        $record_arr['models'] = ((!empty($core_details['models']) && is_array($core_details['models'])) ? $core_details['models'] : []);
        $record_arr['is_installed'] = true;
        $record_arr['is_core'] = true;
        $record_arr['is_always_active'] = $core_details['is_always_active'];
        $record_arr['is_distribution'] = $core_details['is_distribution'];
        // Considered multi tenant for core models...
        $record_arr['is_multi_tenant'] = true;
        $record_arr['tenants'] = [];

        return $record_arr;
    }

    private function _update_db_main_details(string $instance_id, array $fields_arr) : ?array
    {
        $this->reset_error();

        if (!$fields_arr
            || !$instance_id
            || !($instance_details = self::valid_instance_id($instance_id))
            || !($params = $this->fetch_default_flow_params(['table_name' => 'plugins']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unknown instance database details.'));

            return null;
        }

        if (!($existing_arr = $this->get_plugins_db_main_details($instance_id))) {
            $existing_arr = null;
            $params['action'] = 'insert';
            $fields_arr['instance_id'] = $instance_id;

            if (empty($fields_arr['type'])) {
                $fields_arr['type'] = $instance_details['instance_type'] ?? null;
            }
            if (empty($fields_arr['plugin'])) {
                $fields_arr['plugin'] = $instance_details['plugin_name'] ?? null;
            }
        } else {
            $params['action'] = 'edit';

            if (empty($existing_arr['type'])) {
                $fields_arr['type'] = $instance_details['instance_type'] ?? null;
            }
            if (empty($existing_arr['plugin'])) {
                $fields_arr['plugin'] = $instance_details['plugin_name'] ?? null;
            }
        }

        $params['fields'] = $fields_arr;

        PHS_Logger::notice('Plugins model action ['.$params['action'].'] on instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

        if (!($validate_fields = $this->validate_data_for_fields($params))
            || empty($validate_fields['data_arr'])) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Error validating plugin database fields.'));

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

        if (!$existing_arr) {
            $plugin_arr = $this->insert($details_arr);
        } else {
            $plugin_arr = $this->edit($existing_arr, $details_arr);
        }

        if (!$plugin_arr) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Couldn\'t save plugin details to database.'));

            PHS_Logger::error('!!! Error in plugins model action ['.$params['action'].'] on instance ['.$instance_id.'] ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE);

            return null;
        }

        PHS_Logger::notice('DONE Plugins model action ['.$params['action'].'] on instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

        self::$db_plugins[$instance_id] = $plugin_arr;

        return [
            'old_data' => $existing_arr,
            'new_data' => $plugin_arr,
        ];
    }

    private function _update_db_tenant_details(string $instance_id, array $fields_arr, int $tenant_id) : ?array
    {
        $this->reset_error();

        if (!PHS::is_multi_tenant()) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Cannot save tenant plugin details.'));

            return null;
        }

        if (!$fields_arr
            || !$instance_id
            || !$tenant_id
            || !($instance_details = self::valid_instance_id($instance_id))
            || !($params = $this->fetch_default_flow_params(['table_name' => 'plugins_tenants']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Unknown instance database details.'));

            return null;
        }

        if (!($db_main_details = $this->get_plugins_db_main_details($instance_id))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Plugin instance not installed yet.'));

            return null;
        }

        if (!($existing_arr = $this->get_plugins_db_tenant_details($instance_id, $tenant_id))) {
            $existing_arr = null;
            $params['action'] = 'insert';
            $fields_arr['tenant_id'] = $tenant_id;
            $fields_arr['instance_id'] = $instance_id;
            $fields_arr['type'] = $instance_details['instance_type'];
            $fields_arr['plugin'] = $instance_details['plugin_name'];
            if (empty($fields_arr['status'])
                && !empty($db_main_details['status'])) {
                $fields_arr['status'] = $db_main_details['status'];
            }
        } else {
            $params['action'] = 'edit';

            if (empty($existing_arr['type'])) {
                $fields_arr['type'] = $instance_details['instance_type'] ?? null;
            }
            if (empty($existing_arr['plugin'])) {
                $fields_arr['plugin'] = $instance_details['plugin_name'] ?? null;
            }
        }

        $params['fields'] = $fields_arr;

        PHS_Logger::notice('Tenant plugins model action ['.$params['action'].'] on instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

        if (!($validate_fields = $this->validate_data_for_fields($params))
            || empty($validate_fields['data_arr'])) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Error validating plugin database fields.'));

            return null;
        }

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating settings...
        if (array_key_exists('settings', $new_fields_arr)) {
            $new_fields_arr['settings'] = empty($new_fields_arr['settings'])
                ? null
                : $this->_encode_settings_field($new_fields_arr['settings']);

            PHS_Logger::notice('New tenant settings for tenant ['.$tenant_id.'] ['.($new_fields_arr['settings'] ?? '(empty settings)').']', PHS_Logger::TYPE_MAINTENANCE);
        }

        $details_arr = $this->fetch_default_flow_params(['table_name' => 'plugins_tenants']);
        $details_arr['fields'] = $new_fields_arr;

        if (!$existing_arr) {
            $plugin_arr = $this->insert($details_arr);
        } else {
            $plugin_arr = $this->edit($existing_arr, $details_arr);
        }

        if (!$plugin_arr) {
            $this->set_error_if_not_set(self::ERR_DB_DETAILS, self::_t('Couldn\'t save tenant plugin details to database.'));

            PHS_Logger::error('!!! Error in tenant plugins model action ['.$params['action'].'] on tenant ['.$tenant_id.'] instance ['.$instance_id.'] ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE);

            return null;
        }

        PHS_Logger::notice('DONE Plugins model action ['.$params['action'].'] on tenant ['.$tenant_id.'] instance ['.$instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

        if (empty(self::$db_tenant_plugins)) {
            self::$db_tenant_plugins = [];
        }

        self::$db_tenant_plugins[$tenant_id][$instance_id] = $plugin_arr;

        return [
            'old_data' => $existing_arr,
            'new_data' => $plugin_arr,
        ];
    }

    private function _cache_all_db_details(bool $force = false) : bool
    {
        if (!$this->_cache_default_db_main_details($force)
                || (PHS::is_multi_tenant() && !$this->_cache_tenants_db_details($force))) {
            $this->_reset_all_plugin_cache();

            return false;
        }

        return true;
    }

    private function _cache_tenants_db_details(bool $force = false) : bool
    {
        $this->reset_error();

        if (empty($force)
            && self::$db_tenant_plugins !== null) {
            return true;
        }

        $this->_reset_tenants_db_plugin_cache();

        self::$db_tenant_plugins = [];

        if (!PHS::is_multi_tenant()) {
            return true;
        }

        if (!($list_arr = $this->fetch_default_flow_params(['table_name' => 'plugins_tenants']))) {
            $this->set_error(self::ERR_DB_DETAILS, $this->_pt('Error preparing query to obtain tenants plugins records.'));

            return false;
        }

        db_supress_errors($list_arr['db_connection']);
        if (!($all_db_plugins = $this->get_list($list_arr))) {
            db_restore_errors_state($list_arr['db_connection']);

            return true;
        }
        db_restore_errors_state($list_arr['db_connection']);

        foreach ($all_db_plugins as $db_arr) {
            if (empty($db_arr['instance_id'])
                || empty($db_arr['tenant_id'])) {
                continue;
            }

            self::$db_tenant_plugins[(int)$db_arr['tenant_id']][$db_arr['instance_id']] = $db_arr;
        }

        return true;
    }

    private function _cache_default_db_details(bool $force = false) : bool
    {
        $this->reset_error();

        if (empty($force)
            && !empty(self::$db_plugins)) {
            return true;
        }

        $this->_reset_db_plugin_cache();

        if (!($list_arr = $this->fetch_default_flow_params(['table_name' => 'plugins']))) {
            $this->set_error(self::ERR_DB_DETAILS, $this->_pt('Error preparing query to obtain plugins records.'));

            return false;
        }

        $list_arr['order_by'] = 'is_core DESC';

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

            if ($db_arr['type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                || empty($db_arr['plugin'])) {
                continue;
            }

            self::$db_plugin_plugins[$db_arr['plugin']] = $db_arr;

            if ($this->is_active($db_arr)) {
                self::$db_plugin_active_plugins[$db_arr['plugin']] = $db_arr;
            }
        }

        return true;
    }

    private function _cache_default_db_main_details(bool $force = false) : bool
    {
        $this->reset_error();

        if (empty($force)
            && !empty(self::$db_plugins)) {
            return true;
        }

        $this->_reset_db_plugin_cache();

        if (!($list_arr = $this->fetch_default_flow_params(['table_name' => 'plugins']))) {
            $this->set_error(self::ERR_DB_DETAILS, $this->_pt('Error preparing query to obtain plugins records.'));

            return false;
        }

        $list_arr['order_by'] = 'is_core DESC';

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

            if ($db_arr['type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                || empty($db_arr['plugin'])) {
                continue;
            }

            self::$db_plugin_plugins[$db_arr['plugin']] = $db_arr;

            if ($this->is_active($db_arr)) {
                self::$db_plugin_active_plugins[$db_arr['plugin']] = $db_arr;
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

    private function _decode_settings_field(string | array $settings) : array
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

    private function _reset_tenants_db_plugin_cache() : void
    {
        self::$db_tenant_plugins = null;
        self::$db_plugin_tenant_plugins = [];
        self::$db_plugin_active_tenant_plugins = [];
    }

    private function _reset_all_plugin_cache() : void
    {
        $this->_reset_db_plugin_cache();
        $this->_reset_tenants_db_plugin_cache();
    }

    private function _reset_all_plugin_registry_cache() : void
    {
        $this->_reset_plugin_registry_cache();
        $this->_reset_db_registry_cache();
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
