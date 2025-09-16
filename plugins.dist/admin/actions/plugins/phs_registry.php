<?php
namespace phs\plugins\admin\actions\plugins;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Api_action;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\models\PHS_Model_Plugins;

class PHS_Action_Registry extends PHS_Api_action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Plugin Registry'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($tenants_model = PHS_Model_Tenants::get_instance())
            || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_plugins()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $is_multi_tenant = PHS::is_multi_tenant();
        $is_ajax_call = PHS_Scope::current_scope() === PHS_Scope::SCOPE_AJAX;

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $pname = PHS_Params::_gp('pname', PHS_Params::T_NOHTML) ?? '';
        $tenant_id = PHS_Params::_gp('tenant_id', PHS_Params::T_INT) ?? 0;
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML);

        $do_cancel = PHS_Params::_p('do_cancel');

        $back_page = !$back_page
            ? PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins'])
            : from_safe_url($back_page);

        if ($do_cancel) {
            return action_redirect($back_page);
        }

        if( empty( $pname )
            || !($plugin_obj = PHS::load_plugin($pname)) ) {
            return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins'], ['unknown_plugin' => 1]);
        }

        $plugin_is_multi_tenant = $plugin_obj->is_plugin_multi_tenant();

        if( !$is_multi_tenant ) {
            $tenant_id = 0;
            $tenants_arr = [];
        } elseif(!($tenants_arr = $tenants_model->get_all_tenants())) {
            $tenants_arr = [];
        }

        if( !empty($tenant_id)
            && !$plugin_is_multi_tenant ) {
            $tenant_id = 0;
            PHS_Notifications::add_warning_notice($this->_pt('Provided plugin is not a multi-tenant plugin. Data will be saved on default tenant.'));
        }

        if( !empty( $tenant_id )
            && empty( $tenants_arr[$tenant_id] ) ) {
            return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins'], ['unknown_tenant' => 1]);
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Registry data saved in database.'));
        }

        if ($is_ajax_call) {
            $do_save_registry_data = PHS_Params::_gp('do_save_registry_data', PHS_Params::T_NOHTML);

            if(!empty($do_save_registry_data)) {

                $save_registry_arr = PHS_Params::_p('save_registry_arr', PHS_Params::T_ARRAY) ?: [];

                if( (!empty( $save_registry_arr ) && null !== $plugin_obj->save_db_registry($save_registry_arr, $tenant_id))
                    || (empty( $save_registry_arr ) && null !== $plugin_obj->clean_db_registry($tenant_id)) ) {
                    return $this->send_api_success(['action_success' => true]);
                }

                if ($plugin_obj->has_error()) {
                    PHS_Notifications::add_error_notice($plugin_obj->get_error_message());
                }

                return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                    $this->_pt('Error saving registry data. Please try again.'));
            }

            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Unknown action in request.'));
        }

        $registry_arr = $plugin_obj->get_db_registry($tenant_id) ?: [];
        $registry_fields_settings = $plugin_obj->get_db_registry_fields_settings($tenant_id) ?: [];

        $data = [
            'back_page' => $back_page,
            'tenant_id' => $tenant_id,
            'is_multi_tenant' => $is_multi_tenant,
            'plugin_is_multi_tenant' => $plugin_is_multi_tenant,
            'pname' => $pname,
            'tenants_arr' => $tenants_arr,
            'registry_arr' => $registry_arr,
            'registry_fields_settings' => $registry_fields_settings,

            'tenants_model' => $tenants_model,
            'plugins_model' => $plugins_model,
            'plugin_obj' => $plugin_obj,
        ];

        return $this->quick_render_template('plugins/registry', $data);
    }
}
