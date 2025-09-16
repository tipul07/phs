<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Tenants;

abstract class PHS_Has_db_registry extends PHS_Has_db_settings
{
    public function get_db_registry_details(?int $tenant_id = null, bool $force = false) : ?array
    {
        if (!$this->_load_plugins_instance()) {
            return null;
        }

        if (!PHS::is_multi_tenant()
            || !$this->is_plugin_multi_tenant()
            || ($tenant_id === null
                && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        return $this->_plugins_instance->get_db_registry($this->instance_id(), $tenant_id, $force);
    }

    public function get_db_registry(?int $tenant_id = null, bool $force = false) : ?array
    {
        if (!$this->_load_plugins_instance()) {
            return null;
        }

        if (!PHS::is_multi_tenant()
            || !$this->is_plugin_multi_tenant()
            || ($tenant_id === null
                && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        return $this->_plugins_instance->get_plugins_db_registry($this->instance_id(), $tenant_id, $force);
    }

    public function save_db_registry(array $registry_arr, ?int $tenant_id = null) : ?array
    {
        if (!$this->_load_plugins_instance()) {
            return null;
        }

        if (!PHS::is_multi_tenant()
            || !$this->is_plugin_multi_tenant()
            || ($tenant_id === null
                && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        return $this->_plugins_instance->save_plugins_db_registry($registry_arr, $this->instance_id(), $tenant_id);
    }

    public function update_db_registry(array $registry_part_arr, ?int $tenant_id = null) : ?array
    {
        if (empty($registry_part_arr)) {
            return null;
        }

        return $this->save_db_registry(self::merge_array_assoc($this->get_db_registry($tenant_id), $registry_part_arr), $tenant_id);
    }

    public function clean_db_registry(?int $tenant_id = null) : ?array
    {
        return $this->save_db_registry([], $tenant_id);
    }

    public function delete_db_registry(?int $tenant_id = null) : bool
    {
        if (!$this->_load_plugins_instance()) {
            return false;
        }

        if (!PHS::is_multi_tenant()
            || !$this->is_plugin_multi_tenant()
            || ($tenant_id === null
                && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        if (!$this->_plugins_instance->delete_db_registry($this->instance_id(), $tenant_id)) {
            $this->copy_or_set_error($this->_plugins_instance,
                self::ERR_PLUGINS_MODEL, self::_t('Couldn\'t delete registry database record.'));

            return false;
        }

        return true;
    }

    public function delete_all_db_registry() : bool
    {
        if (!$this->_load_plugins_instance()
            || !$this->_plugins_instance->delete_all_db_registry($this->instance_id())) {
            $this->copy_or_set_error($this->_plugins_instance,
                self::ERR_PLUGINS_MODEL, self::_t('Couldn\'t delete all registry database record.'));

            return false;
        }

        return true;
    }

    public function get_db_registry_fields_settings() : array
    {
        if (!($registry_fields = $this->_db_registry_fields_settings())) {
            return [];
        }

        $return_arr = [];
        $registry_fields_keys = self::_default_db_registry_fields_settings();
        foreach ($registry_fields as $field_key => $field_settings) {
            $return_arr[$field_key] = self::validate_array($field_settings, $registry_fields_keys);
        }

        return $return_arr;
    }

    /**
     * Overwrite this method if you want specific registry fields settings.
     * This will be applied only to the interface in admin plugin!
     *
     * @return array
     */
    protected function _db_registry_fields_settings() : array
    {
        return [];
    }

    private static function _default_db_registry_fields_settings() : array
    {
        return [
            'readonly'       => false,
            'can_be_deleted' => true,
        ];
    }
}
