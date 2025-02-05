<?php
namespace phs\plugins\admin\actions\plugins;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\plugins\admin\libraries\Phs_Plugin_settings;

class PHS_Action_Import extends PHS_Action
{
    private string $result_buffer = '';

    /**
     * @inheritdoc
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Import Plugin Settings'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        $is_multi_tenant = PHS::is_multi_tenant();

        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Tenants $tenants_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($plugin_settings_lib = Phs_Plugin_settings::get_instance())
            || ($is_multi_tenant
                && !($tenants_model = PHS_Model_Tenants::get_instance()))
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_import_plugins_settings()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $all_tenants_arr = [];
        if ($is_multi_tenant
            && !($all_tenants_arr = $tenants_model->get_all_tenants())) {
            $all_tenants_arr = [];
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $tenant_id = PHS_Params::_p('tenant_id', PHS_Params::T_INT) ?? 0;
        $settings_json = PHS_Params::_p('settings_json', PHS_Params::T_NOHTML);
        $crypt_key = PHS_Params::_p('crypt_key', PHS_Params::T_NOHTML);
        $selected_plugins = PHS_Params::_p('selected_plugins', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_NOHTML]) ?: [];

        $do_validate = PHS_Params::_p('do_validate');
        $do_import = PHS_Params::_p('do_import');

        $decoded_settings_arr = [];
        if( $settings_json && $crypt_key
            && !($decoded_settings_arr = $plugin_settings_lib->do_platform_import_settings_for_plugins_from_json_buffer($settings_json, $crypt_key)) ) {
            $decoded_settings_arr = [];
            PHS_Notifications::add_error_notice(
                $plugin_settings_lib->get_simple_error_message($this->_pt('Error decoding settings JSON string. Please try again.'))
            );
        }

        if (!empty($do_validate)) {
            if(empty($decoded_settings_arr)
             && !PHS_Notifications::have_errors_or_warnings_notifications()) {
                PHS_Notifications::add_error_notice($this->_pt('Error decoding settings JSON string. Please try again.'));
            }
        }

        $import_with_success = false;
        if (!empty($do_import)
            && !PHS_Notifications::have_errors_or_warnings_notifications()) {

            $this->result_buffer = '';
            PHS_Maintenance::output_callback([$this, 'maintenance_injection']);

            if( !($import_with_success = $plugin_settings_lib->do_platform_import_settings_for_plugins_from_interface(
                $decoded_settings_arr, $selected_plugins, $is_multi_tenant ? $tenant_id : null))
            ) {
                PHS_Notifications::add_error_notice(
                    $plugin_settings_lib->get_simple_error_message($this->_pt('Error while importing the settings. Please try again.'))
                );
            }
        }

        $data = [
            'foobar'        => $foobar,
            'tenant_id'        => $tenant_id,
            'settings_json'            => $settings_json,
            'crypt_key'          => $crypt_key,

            'decoded_settings_arr'          => $decoded_settings_arr,
            'selected_plugins'          => $selected_plugins,

            'result_buffer'          => $this->result_buffer,
            'import_with_success'          => $import_with_success,

            'all_tenants_arr' => $all_tenants_arr,

            'do_import' => $do_import,
        ];

        return $this->quick_render_template('plugins/import', $data);
    }

    public function maintenance_injection(string $msg): void
    {
        $this->result_buffer .= $msg."\n";
    }
}
