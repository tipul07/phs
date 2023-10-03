<?php
namespace phs\plugins\admin\actions\plugins;

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

class PHS_Action_Settings extends PHS_Action
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

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $pid = PHS_Params::_gp('pid', PHS_Params::T_NOHTML);
        $model_id = PHS_Params::_gp('model_id', PHS_Params::T_NOHTML);
        $tenant_id = PHS_Params::_gp('tenant_id', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML);

        $do_submit = PHS_Params::_gp('do_submit', PHS_Params::T_NOHTML);
        $do_cancel = PHS_Params::_p('do_cancel');

        if (empty($back_page)) {
            $back_page = PHS::url(['p' => 'admin', 'a' => 'plugins_list']);
        } else {
            $back_page = from_safe_url($back_page);
        }

        if ($do_cancel) {
            return action_redirect($back_page);
        }

        if( !$is_multi_tenant ) {
            $tenant_id = 0;
            $tenants_arr = [];
        } elseif(!($tenants_arr = $tenants_model->get_all_tenants())) {
            $tenants_arr = [];
        }

        if( !empty( $tenant_id )
            && empty( $tenants_arr[$tenant_id] ) ) {
            return action_redirect(['p' => 'admin', 'a' => 'plugins_list'], ['unknown_tenant' => 1]);
        }

        $context_arr = [];
        $context_arr['tenant_id'] = $tenant_id;
        $context_arr['plugin'] = $pid;
        $context_arr['model_id'] = $model_id;
        $context_arr['extract_submit'] = (bool)$do_submit;

        $init_has_error = false;
        if( !($context_arr = PHS_Has_db_settings::init_settings_context($context_arr))
            || !empty($context_arr['stop_executon'])
            || !empty($context_arr['redirect_to'])
            || ($init_has_error = self::arr_has_error($context_arr['errors'] ?? []))) {

            if( $init_has_error ) {
                if (!($error_msg = self::arr_get_error_message($context_arr['errors'] ?? []))) {
                    $error_msg = self::_t('Error initializing plugin settings page.');
                }
                PHS_Notifications::add_error_notice($error_msg);
            }

            if( !empty( $context_arr['redirect_to'] ) ) {
                return action_redirect($context_arr['redirect_to']);
            }

            if(!empty( $context_arr['stop_executon'] ) ) {
                return self::default_action_result();
            }
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Settings saved in database.'));
        }

        $instance_obj = $context_arr['plugin_instance'] ?? $context_arr['model_instance'] ?? null;

        $data = [
            'back_page'             => $back_page,
            'tenant_id'            => $tenant_id,
            'tenants_arr'             => $tenants_arr,
            'form_data'             => $context_arr['form_data'],
            'models_arr' => $context_arr['models_arr'],
            'plugin_obj'            => $context_arr['plugin_instance'],
            'tenants_model'            => $tenants_model,
        ];

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
