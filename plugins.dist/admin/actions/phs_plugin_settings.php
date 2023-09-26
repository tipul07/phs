<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Has_db_settings;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Plugin_settings extends PHS_Action
{
    public const ERR_PLUGIN = 1;

    /** @var null|\phs\libraries\PHS_Plugin */
    private ?PHS_Plugin $_plugin_obj = null;

    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Plugin Settings'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\system\core\models\PHS_Model_Tenants $tenants_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($tenants_model = PHS_Model_Tenants::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_plugins()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $is_multi_tenant = PHS::is_multi_tenant();

        $pid = PHS_Params::_gp('pid', PHS_Params::T_NOHTML);
        $tenant_id = PHS_Params::_gp('tenant_id', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML);

        $do_cancel = PHS_Params::_p('do_cancel');

        if( !$is_multi_tenant ) {
            $tenant_id = 0;
            $tenants_arr = [];
        } elseif(!($tenants_arr = $tenants_model->get_all_tenants())) {
            $tenants_arr = [];
        }

        if (empty($back_page)) {
            $back_page = PHS::url(['p' => 'admin', 'a' => 'plugins_list']);
        } else {
            $back_page = from_safe_url($back_page);
        }

        if ($do_cancel) {
            return action_redirect($back_page);
        }

        if ($pid !== PHS_Instantiable::CORE_PLUGIN
            && (!($instance_details = PHS_Instantiable::valid_instance_id($pid))
                || empty($instance_details['instance_type'])
                || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                || !($this->_plugin_obj = PHS::load_plugin($instance_details['plugin_name']))
            )
        ) {
            return action_redirect(['p' => 'admin', 'a' => 'plugins_list'], ['unknown_plugin' => 1]);
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Settings saved in database.'));
        }

        if ($pid === PHS_Instantiable::CORE_PLUGIN) {
            $plugin_models_arr = PHS::get_core_models();
        } else {
            $plugin_models_arr = $this->_plugin_obj->get_models();
        }

        $modules_with_settings = [];
        if (!empty($plugin_models_arr)) {
            foreach ($plugin_models_arr as $model_name) {
                if (!($model_instance = PHS::load_model($model_name, ($this->_plugin_obj ? $this->_plugin_obj->instance_plugin_name() : null)))
                 || !($settings_arr = $model_instance->validate_settings_structure())) {
                    continue;
                }

                $model_id = $model_instance->instance_id();

                if (!($model_db_details = $model_instance->get_db_main_details())) {
                    $model_db_details = [];
                }

                $modules_with_settings[$model_id]['instance'] = $model_instance;
                $modules_with_settings[$model_id]['settings'] = $settings_arr;
                $modules_with_settings[$model_id]['default_settings'] = $model_instance->get_default_settings();
                $modules_with_settings[$model_id]['db_settings'] = $model_instance->get_db_settings($tenant_id);
                $modules_with_settings[$model_id]['db_version'] = $model_db_details['version'] ?? '0.0.0';
                $modules_with_settings[$model_id]['script_version'] = $model_instance->get_model_version();
            }
        }

        $data = [
            'back_page'             => $back_page,
            'tenant_id'            => $tenant_id,
            'tenants_arr'             => $tenants_arr,
            'form_data'             => [],
            'modules_with_settings' => $modules_with_settings,
            'settings_fields'       => [],
            'db_settings'           => [],
            'plugin_obj'            => $this->_plugin_obj,
            'tenants_model'            => $tenants_model,
        ];

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $selected_module = PHS_Params::_gp('selected_module', PHS_Params::T_NOHTML);
        $do_submit = PHS_Params::_gp('do_submit', PHS_Params::T_NOHTML);

        $form_data = [];
        $form_data['selected_module'] = $selected_module;
        $form_data['do_submit'] = $do_submit;

        /** @var \phs\libraries\PHS_Has_db_settings $module_instance */
        $model_instance = null;
        $module_instance = null;
        $settings_fields = [];
        $default_settings = [];
        $db_settings = [];
        $db_version = '0.0.0';
        $script_version = '0.0.0';
        if (!empty($form_data['selected_module'])
         && !empty($modules_with_settings[$form_data['selected_module']])) {
            if (!empty($modules_with_settings[$form_data['selected_module']]['instance'])) {
                $model_instance = $module_instance = $modules_with_settings[$form_data['selected_module']]['instance'];
            }
            if (!empty($modules_with_settings[$form_data['selected_module']]['settings'])) {
                $settings_fields = $modules_with_settings[$form_data['selected_module']]['settings'];
            }
            if (!empty($modules_with_settings[$form_data['selected_module']]['default_settings'])) {
                $default_settings = $modules_with_settings[$form_data['selected_module']]['default_settings'];
            }
            if (!empty($modules_with_settings[$form_data['selected_module']]['db_settings'])) {
                $db_settings = $modules_with_settings[$form_data['selected_module']]['db_settings'];
            }
            if (!empty($modules_with_settings[$form_data['selected_module']]['db_version'])) {
                $db_version = $modules_with_settings[$form_data['selected_module']]['db_version'];
            }
            if (!empty($modules_with_settings[$form_data['selected_module']]['script_version'])) {
                $script_version = $modules_with_settings[$form_data['selected_module']]['script_version'];
            }
        } else {
            $form_data['selected_module'] = '';
            $module_instance = $this->_plugin_obj;

            if ($this->_plugin_obj) {
                if (!($plugin_db_details = $this->_plugin_obj->get_db_main_details())) {
                    $plugin_db_details = [];
                }

                $settings_fields = $this->_plugin_obj->validate_settings_structure();
                $default_settings = $this->_plugin_obj->get_default_settings();
                $db_settings = $this->_plugin_obj->get_db_settings($tenant_id);
                $db_version = (!empty($plugin_db_details['version']) ? $plugin_db_details['version'] : '0.0.0');
                $script_version = $this->_plugin_obj->get_plugin_version();

                // var_dump( $this->_plugin_obj->get_db_settings(1) );
                // var_dump( $this->_plugin_obj->get_db_settings(0) );
                // exit;
            }
        }

        //$new_settings_arr = $this->_extract_settings_fields_from_submit($settings_fields, $default_settings, $db_settings, $foobar, $form_data);

        $new_settings_arr = [];
        if( ($form_extraction = PHS_Has_db_settings::extract_settings_fields_from_submit(
            $this->_plugin_obj, $model_instance, $tenant_id, $form_data, (bool)$foobar )) ) {
            if( !empty( $form_extraction['form_settings'] ) ) {
                $new_settings_arr = $form_extraction['form_settings'];
            }
            if( !empty( $form_extraction['form_data'] ) ) {
                $form_data = $form_extraction['form_data'];
            }
        }

        // echo self::var_dump( PHS_Has_db_settings::render_settings_form_for_instance( $this->_plugin_obj, $model_instance, $tenant_id, $form_data ), [ 'max_level' => 4 ] );
        // var_dump(PHS_Has_db_settings::st_get_error());
        // exit;

        if (!empty($do_submit)) {
            //$new_settings_arr = self::validate_array($new_settings_arr, $db_settings);

            if( ($save_extraction = PHS_Has_db_settings::extract_custom_save_settings_fields_for_save(
                $this->_plugin_obj, $model_instance, $tenant_id, $new_settings_arr, $form_data )) ) {
                if( !empty( $save_extraction['new_settings'] ) ) {
                    $new_settings_arr = $save_extraction['new_settings'];
                }

                if( !empty( $save_extraction['errors_arr'] ) ) {
                    foreach( $save_extraction['errors_arr'] as $error_msg ) {
                        PHS_Notifications::add_error_notice($error_msg);
                    }
                }

                if( !empty( $save_extraction['warnings_arr'] ) ) {
                    foreach( $save_extraction['warnings_arr'] as $warning_msg ) {
                        PHS_Notifications::add_warning_notice($warning_msg);
                    }
                }
            }

            // $callback_params = PHS_Plugin::st_default_custom_save_params();
            // $callback_params['plugin_obj'] = $this->_plugin_obj;
            // $callback_params['model_obj'] = $module_instance;
            // $callback_params['form_data'] = $form_data;
            //
            // $new_settings_arr = $this->_extract_custom_save_settings_fields_from_submit($settings_fields, $callback_params, $new_settings_arr, $db_settings);

            if( empty( $new_settings_arr ) ) {
                PHS_Notifications::add_error_notice($this->_pt('No settings to be saved.'));
            }

            elseif (!PHS_Notifications::have_notifications_errors()) {
                if ($module_instance->save_db_settings($new_settings_arr, $tenant_id)) {
                    $args = [
                        'changes_saved'   => 1,
                        'pid'             => $pid,
                        'selected_module' => $selected_module,
                    ];

                    if($is_multi_tenant) {
                        $args['tenant_id'] = $tenant_id;
                    }

                    $args['back_page'] = $back_page;

                    return action_redirect(['p' => 'admin', 'a' => 'plugin_settings'], $args);
                }

                if ($module_instance->has_error()) {
                    PHS_Notifications::add_error_notice($module_instance->get_error_message());
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Error saving settings in database. Please try again.'));
                }
            }
        }

        $form_data['pid'] = $pid;

        $data['form_data'] = $form_data;
        $data['settings_fields'] = $settings_fields;
        $data['db_settings'] = $db_settings;
        $data['db_version'] = $db_version;
        $data['script_version'] = $script_version;

        return $this->quick_render_template('plugin_settings', $data);
    }

    private function _extract_custom_save_settings_fields_from_submit($settings_fields, array $init_callback_params, array $new_settings_arr, $db_settings) : array
    {
        $default_custom_save_callback_result = PHS_Plugin::st_default_custom_save_callback_result();
        foreach ($settings_fields as $field_name => $field_details) {
            if (!empty($field_details['ignore_field_value'])) {
                continue;
            }

            if (PHS_Plugin::settings_field_is_group($field_details)) {
                if (null !== ($group_settings = $this->_extract_custom_save_settings_fields_from_submit(
                    $field_details['group_fields'],
                    $init_callback_params,
                    $new_settings_arr,
                    $db_settings))) {
                    $new_settings_arr = self::merge_array_assoc($new_settings_arr, $group_settings);
                }

                continue;
            }

            if (empty($field_details['custom_save'])
             || !@is_callable($field_details['custom_save'])) {
                continue;
            }

            $callback_params = $init_callback_params;
            $callback_params['field_name'] = $field_name;
            $callback_params['field_details'] = $field_details;
            $callback_params['field_value'] = ($new_settings_arr[$field_name] ?? null);

            // make sure static error is reset
            self::st_reset_error();
            // make sure static warnings are reset
            self::st_reset_warnings();

            /**
             * When there is a field in instance settings which has a custom callback for saving data, it will return
             * either a scalar or an array to be merged with existing settings. Only keys which already exists as settings
             * can be provided
             */
            if (($save_result = @call_user_func($field_details['custom_save'], $callback_params)) !== null) {
                if (!is_array($save_result)) {
                    $new_settings_arr[$field_name] = $save_result;
                } else {
                    $save_result = self::merge_array_assoc($save_result, $default_custom_save_callback_result);
                    if (!empty($save_result['{new_settings_fields}']) && is_array($save_result['{new_settings_fields}'])) {
                        foreach ($db_settings as $s_key => $s_val) {
                            if (array_key_exists($s_key, $save_result['{new_settings_fields}'])) {
                                $new_settings_arr[$s_key] = $save_result['{new_settings_fields}'][$s_key];
                            }
                        }
                    } else {
                        if (isset($save_result['{new_settings_fields}'])) {
                            unset($save_result['{new_settings_fields}']);
                        }

                        $new_settings_arr[$field_name] = $save_result;
                    }
                }
            } elseif (self::st_has_error()) {
                PHS_Notifications::add_error_notice(self::st_get_error_message());
            }

            if (self::st_has_warnings()) {
                PHS_Notifications::add_warning_notice(self::st_get_warnings());
            }
        }

        return $new_settings_arr;
    }

    private function _extract_settings_fields_from_submit(array $settings_fields, array $default_settings, array $db_settings, $is_post, array &$form_data) : array
    {
        $new_settings_arr = [];
        foreach ($settings_fields as $field_name => $field_details) {
            if (!empty($field_details['ignore_field_value'])) {
                continue;
            }

            if (PHS_Has_db_settings::settings_field_is_group($field_details)) {
                if (null !== ($group_settings = $this->_extract_settings_fields_from_submit($field_details['group_fields'], $default_settings, $db_settings, $is_post, $form_data))) {
                    $new_settings_arr = self::merge_array_assoc($new_settings_arr, $group_settings);
                }

                continue;
            }

            if (null === ($field_value = $this->_extract_field_value_from_submit($field_name, $field_details, $default_settings, $db_settings, $is_post, $form_data))) {
                continue;
            }

            $new_settings_arr[$field_name] = $field_value;
        }

        return $new_settings_arr;
    }

    private function _extract_field_value_from_submit(string $field_name, array $field_details, array $default_settings, array $db_settings, $is_post, array &$form_data)
    {
        $field_value = null;

        if (empty($field_details['editable'])) {
            // Check if default values have changed (upgrading plugin might change default value)
            if (isset($default_settings[$field_name]) && isset($db_settings[$field_name])
             && $default_settings[$field_name] !== $db_settings[$field_name]) {
                $field_value = $default_settings[$field_name];
            }

            // if we have something in database use that value
            elseif (isset($db_settings[$field_name])) {
                $field_value = $db_settings[$field_name];
            }

            // This is a new non-editable value, save default value to db
            elseif (isset($default_settings[$field_name])) {
                $field_value = $default_settings[$field_name];
            }

            return $field_value;
        }

        $form_data[$field_name] = PHS_Params::_gp($field_name, $field_details['type'], $field_details['extra_type']);

        if (!empty($is_post)
         && (int)$field_details['type'] === PHS_Params::T_BOOL) {
            $form_data[$field_name] = (!empty($form_data[$field_name]));
        }

        if (!empty($field_details['custom_save'])) {
            return null;
        }

        switch ($field_details['input_type']) {
            default:
            case PHS_Has_db_settings::INPUT_TYPE_ONE_OR_MORE:
            case PHS_Has_db_settings::INPUT_TYPE_ONE_OR_MORE_MULTISELECT:
                if (isset($form_data[$field_name])) {
                    $field_value = $form_data[$field_name];
                } elseif (isset($default_settings[$field_name])) {
                    $field_value = $default_settings[$field_name];
                }
                break;

            case PHS_Has_db_settings::INPUT_TYPE_TEMPLATE:
                break;

            case PHS_Has_db_settings::INPUT_TYPE_KEY_VAL_ARRAY:
                if (empty($default_settings[$field_name])) {
                    $field_value = $form_data[$field_name];
                } else {
                    $field_value = self::validate_array_to_new_array($form_data[$field_name], $default_settings[$field_name]);
                }
                break;
        }

        return $field_value;
    }
}
