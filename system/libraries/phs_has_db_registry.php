<?php

namespace phs\libraries;

use phs\PHS;
use phs\PHS_Tenants;

abstract class PHS_Has_db_registry extends PHS_Has_db_settings
{
    /**
     * @param null|int $tenant_id
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return null|array
     */
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

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     * @param ?int $tenant_id
     *
     * @return null|array Registry saved in database for current instance
     */
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
            if ($this->_plugins_instance->has_error()) {
                $this->copy_error($this->_plugins_instance);
            } else {
                $this->set_error(self::ERR_PLUGINS_MODEL, self::_t('Couldn\'t delete registry database record.'));
            }

            return false;
        }

        return true;
    }

    public function delete_all_db_registry() : bool
    {
        if (!$this->_load_plugins_instance()
            || !$this->_plugins_instance->delete_all_db_registry($this->instance_id())) {
            if ($this->_plugins_instance->has_error()) {
                $this->copy_error($this->_plugins_instance);
            } else {
                $this->set_error(self::ERR_PLUGINS_MODEL, self::_t('Couldn\'t delete all registry database record.'));
            }

            return false;
        }

        return true;
    }
}
