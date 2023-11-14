<?php
namespace phs\plugins\admin\actions\tenants;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Api_action;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Tenants;

class PHS_Action_Plugins extends PHS_Api_action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Manage Tenant\'s Plugins'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        /** @var \phs\system\core\models\PHS_Model_Tenants $tenants_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($plugins_model = PHS_Model_Plugins::get_instance())
            || !($tenants_model = PHS_Model_Tenants::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_list_tenants()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $tenant_id = PHS_Params::_pg('tenant_id', PHS_Params::T_INT);

        $is_multi_tenant = PHS::is_multi_tenant();
        $is_ajax_call = PHS_Scope::current_scope() === PHS_Scope::SCOPE_AJAX;

        if (!($plugins_arr = $plugins_model->get_all_records_for_paginator())) {
            $plugins_arr = [];
        }

        if (!($plugins_statuses = $plugins_model->get_statuses_as_key_val())) {
            $plugins_statuses = [];
        }
        if (!$is_multi_tenant
            || !($tenants_key_val_arr = $tenants_model->get_tenants_as_key_val())) {
            $tenants_key_val_arr = [];
        }

        $tenants_filter_arr = [];
        $statuses_filter_arr = [];
        if (!empty($tenants_key_val_arr)) {
            $tenants_filter_arr = self::merge_array_assoc([0 => $this->_pt('Default')], $tenants_key_val_arr);
        }
        if (!empty($plugins_statuses)) {
            $statuses_filter_arr = self::merge_array_assoc([0 => $this->_pt(' - Choose - '), -1 => $this->_pt('Not Installed')], $plugins_statuses);
        }

        $tenant_reset = false;
        if ($tenant_id
           && (!$is_multi_tenant
               || empty($tenants_key_val_arr[$tenant_id]))) {
            $tenant_id = 0;
            $tenant_reset = true;

            PHS_Notifications::add_warning_notice($this->_pt('Provided tenant is invalid.'));
        }

        if ($is_ajax_call) {
            if ($tenant_reset) {
                return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                    $this->_pt('Provided tenant is invalid.'));
            }

            if (!$admin_plugin->can_admin_manage_plugins()) {
                return $this->send_api_error(PHS_Api_base::H_CODE_FORBIDDEN, self::ERR_RIGHTS,
                    $this->_pt('You don\'t have rights to access this section.'));
            }

            $do_activate_selected = PHS_Params::_p('do_activate_selected');
            $do_inactivate_selected = PHS_Params::_p('do_inactivate_selected');
            $do_get_registry = PHS_Params::_p('do_get_registry');
            $do_get_settings = PHS_Params::_p('do_get_settings');

            if (!empty($do_inactivate_selected) || !empty($do_activate_selected)) {
                $selected_plugins = PHS_Params::_p('selected_plugins', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_NOHTML]) ?: [];
                $action_success = false;

                if (empty($selected_plugins)) {
                    PHS_Notifications::add_error_notice($this->_pt('Please provide a list of plugins.'));
                } elseif (!($dir_entries = $plugins_model->cache_all_dir_details())) {
                    PHS_Notifications::add_error_notice($this->_pt('Error obtaining a list of framework plugins.'));
                } else {
                    $instances_arr = [];
                    foreach ($selected_plugins as $plugin) {
                        if ($plugin === PHS_Instantiable::CORE_PLUGIN
                            || empty($dir_entries[$plugin])) {
                            continue;
                        }

                        $instances_arr[$plugin] = $dir_entries[$plugin];
                    }

                    $action_success = true;
                    /**
                     * @var string $plugin_name
                     * @var \phs\libraries\PHS_Plugin $plugin_obj
                     */
                    foreach ($instances_arr as $plugin_name => $plugin_obj) {
                        $plugin_display_name = $plugin_obj->get_plugin_display_name() ?: $plugin_name;
                        if ((!empty($tenant_id) && !$plugin_obj->is_multi_tenant())
                            || $plugin_obj->is_always_active()) {
                            PHS_Notifications::add_warning_notice($this->_pt('Cannot change status for plugin %s.',
                                $plugin_display_name));
                            continue;
                        }

                        if (!empty($do_inactivate_selected)) {
                            if ((empty($tenant_id) && !$plugin_obj->inactivate_plugin())
                                || (!empty($tenant_id) && !$plugin_obj->inactivate_plugin_on_tenant($tenant_id))) {
                                $action_success = false;
                                PHS_Notifications::add_error_notice($this->_pt('Error inactivating plugin %s on tenant %s.',
                                    $plugin_display_name, $tenants_filter_arr[$tenant_id] ?? $this->_pt('N/A')));
                            }
                        } elseif (!empty($do_activate_selected)) {
                            if ((empty($tenant_id) && !$plugin_obj->activate_plugin())
                                || (!empty($tenant_id) && !$plugin_obj->activate_plugin_on_tenant($tenant_id))) {
                                $action_success = false;
                                PHS_Notifications::add_error_notice($this->_pt('Error activating plugin %s on tenant %s.',
                                    $plugin_display_name, $tenants_filter_arr[$tenant_id] ?? $this->_pt('N/A')));
                            }
                        }
                    }
                }

                return $this->send_api_success(['action_success' => $action_success]);
            }

            if (!empty($do_get_registry) || !empty($do_get_settings)) {
                /** @var \phs\libraries\PHS_Plugin $plugin_obj */
                if (!($plugin_id = PHS_Params::_p('plugin_id', PHS_Params::T_NOHTML))
                 || !($id_details = PHS_Instantiable::valid_instance_id($plugin_id))
                 || $id_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                 || $id_details['plugin_name'] === PHS_Instantiable::CORE_PLUGIN
                 || !($plugin_obj = PHS::load_plugin($id_details['plugin_name']))) {
                    PHS_Notifications::add_error_notice($this->_pt('Please provide a valid plugin id.'));
                } else {
                    if (!empty($do_get_registry) ) {
                        return $this->send_api_success(['registry' => $plugin_obj->get_plugin_registry($tenant_id)]);
                    }

                    return $this->send_api_success(['settings' => $plugin_obj->get_plugin_settings_as_strings_array($tenant_id)]);
                }
            }

            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Unknown action in request.'));
        }

        $data = [
            'tenant_id'           => $tenant_id,
            'is_multi_tenant'     => $is_multi_tenant,
            'plugins_statuses'    => $plugins_statuses,
            'tenants_key_val_arr' => $tenants_key_val_arr,

            'tenants_filter_arr'  => $tenants_filter_arr,
            'statuses_filter_arr' => $statuses_filter_arr,

            'plugins_arr'   => $plugins_arr,
            'plugins_model' => $plugins_model,
        ];

        return $this->quick_render_template('tenants/plugins', $data);
    }
}
