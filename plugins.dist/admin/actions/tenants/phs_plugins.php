<?php
namespace phs\plugins\admin\actions\tenants;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\models\PHS_Model_Plugins;

class PHS_Action_Plugins extends PHS_Action
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

        $data = [
            'tenant_id' => $tenant_id,
            'is_multi_tenant' => $is_multi_tenant,
            'plugins_statuses' => $plugins_statuses,
            'tenants_key_val_arr' => $tenants_key_val_arr,

            'tenants_filter_arr' => $tenants_filter_arr,
            'statuses_filter_arr' => $statuses_filter_arr,

            'plugins_arr' => $plugins_arr,
            'plugins_model' => $plugins_model,
        ];

        return $this->quick_render_template('tenants/plugins', $data);
    }
}
