<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Crypt;
use phs\PHS_Tenants;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_saved;
use phs\system\core\events\plugins\PHS_Event_Tenant_plugin_settings;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_obfuscated_keys;

abstract class PHS_Has_db_settings extends PHS_Instantiable
{
    public const ERR_PLUGINS_MODEL = 40000;

    public const INPUT_TYPE_TEMPLATE = 'template', INPUT_TYPE_ONE_OR_MORE = 'one_or_more',
        INPUT_TYPE_ONE_OR_MORE_MULTISELECT = 'one_or_more_multiselect', INPUT_TYPE_KEY_VAL_ARRAY = 'key_val_array',
        INPUT_TYPE_TEXTAREA = 'textarea';

    /** @var bool|PHS_Model_Plugins */
    protected $_plugins_instance = false;

    // Validated settings fields structure array
    private array $_settings_structure = [];

    // Array with default values for settings (key => val) array
    private array $_default_settings = [];

    // Database "main" record
    private array $_db_details = [];

    // Database tenant record
    private array $_db_tenant_details = [];

    // Database settings field parsed as array
    private array $_db_settings = [];

    // Tenant database settings field parsed as array
    private array $_db_tenant_settings = [];

    // What keys should be obfuscated
    private ?array $_obfuscating_keys = null;

    /**
     * Override this function and return an array with settings fields definition
     *
     * @return array
     */
    public function get_settings_structure()
    {
        return [];
    }

    /**
     * Override this function and return an array with keys in settings which should be obfuscated on save
     * @return array
     */
    public function get_settings_keys_to_obfuscate()
    {
        return [];
    }

    /**
     * Gathers all keys which should be obfuscated for this instance
     * @return array
     */
    final public function get_all_settings_keys_to_obfuscate() : array
    {
        if ($this->_obfuscating_keys !== null) {
            return $this->_obfuscating_keys;
        }

        $obfuscating_keys = $this->get_settings_keys_to_obfuscate();

        // Low level hook for plugin settings keys that should be obfuscated (allows only keys that are not present in plugin settings)
        /** @var PHS_Event_Plugin_settings_obfuscated_keys $event_obj */
        if (($event_obj = PHS_Event_Plugin_settings_obfuscated_keys::trigger([
            'instance_id'       => $this->instance_id(),
            'obfucate_keys_arr' => $obfuscating_keys,
        ]))
            && ($obfucated_keys_arr = $event_obj->get_output('obfucate_keys_arr'))
        ) {
            $obfuscating_keys = self::array_merge_unique_values($obfucated_keys_arr, $obfuscating_keys);
        }

        $this->_obfuscating_keys = $obfuscating_keys ?? [];

        return $obfuscating_keys;
    }

    public function default_custom_save_params() : array
    {
        return self::st_default_custom_save_params();
    }

    /**
     * Validate settings of this instance
     * @return array
     */
    public function validate_settings_structure() : array
    {
        if (!empty($this->_settings_structure)) {
            return $this->_settings_structure;
        }

        $this->_settings_structure = [];

        // Validate settings structure
        if (!($settings_structure_arr = $this->get_settings_structure())
         || !is_array($settings_structure_arr)) {
            return [];
        }

        if (!($this->_settings_structure = self::_validate_settings_structure_fields($settings_structure_arr))) {
            $this->_settings_structure = [];
        }

        return $this->_settings_structure;
    }

    /**
     * @return array
     */
    final public function get_default_settings() : array
    {
        if (!empty($this->_default_settings)) {
            return $this->_default_settings;
        }

        if (empty($this->_settings_structure)) {
            $this->validate_settings_structure();
        }

        $this->_default_settings = self::_get_default_settings_for_structure($this->_settings_structure);

        return $this->_default_settings;
    }

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return null|array
     */
    public function get_db_main_details(bool $force = false) : ?array
    {
        if (empty($force)
         && !empty($this->_db_details)) {
            return $this->_db_details;
        }

        if (!$this->_load_plugins_instance()
         || !($db_details = $this->_plugins_instance->get_plugins_db_main_details($this->instance_id(), $force))) {
            return null;
        }

        $this->_db_details = $db_details;

        return $this->_db_details;
    }

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return string
     */
    public function get_db_version(bool $force = false) : string
    {
        if (!($db_details = $this->get_db_main_details($force))
            || empty($db_details['version'])) {
            return '0.0.0';
        }

        return $db_details['version'];
    }

    /**
     * @param null|int $tenant_id
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return null|array
     */
    public function get_db_tenant_details(?int $tenant_id = null, bool $force = false) : ?array
    {
        if (!PHS::is_multi_tenant()) {
            return null;
        }

        if (!PHS::is_multi_tenant()
         || ($tenant_id === null
             && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        if (empty($force)
         && !empty($this->_db_tenant_details[$tenant_id])) {
            return $this->_db_tenant_details[$tenant_id];
        }

        if (!$this->_load_plugins_instance()
         || !($db_details = $this->_plugins_instance->get_plugins_db_tenant_details($this->instance_id(), $tenant_id, $force))) {
            return null;
        }

        $this->_db_tenant_details[$tenant_id] = $db_details;

        return $this->_db_tenant_details[$tenant_id];
    }

    /**
     * @param null|int $tenant_id
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return null|array
     */
    public function get_merged_db_details(?int $tenant_id = null, bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($db_main_details = $this->get_db_main_details($force))) {
            return null;
        }

        $db_main_details = self::_db_details_fields_prepare_for_merge($db_main_details);
        if (!PHS::is_multi_tenant()) {
            return $db_main_details;
        }

        if ($tenant_id === null
            && !($tenant_id = PHS_Tenants::get_current_tenant_id())) {
            $tenant_id = 0;
        }

        // We don't force this call (if $force is true) as cache was already rebuild when calling get_db_main_details()
        if (empty($tenant_id)
            || !($db_tenant_details = $this->get_db_tenant_details($tenant_id))) {
            return $db_main_details;
        }

        return self::merge_array_assoc($db_main_details, self::_db_details_fields_prepare_for_merge($db_tenant_details));
    }

    /**
     * @param null|int $tenant_id We can force to get settings for a specific tenant
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array Settings saved in database for current instance
     */
    public function get_db_settings_as_strings(?int $tenant_id = null, bool $force = false) : array
    {
        if (!($settings_arr = $this->get_db_settings($tenant_id, $force))
            || !($settings_structure = $this->validate_settings_structure())) {
            return [];
        }

        if (!($defaults_arr = $this->get_default_settings())) {
            $defaults_arr = [];
        }

        $strings_arr = [];
        $this->_get_db_settings_as_strings_from_fields($settings_structure, $settings_arr, $defaults_arr, $strings_arr);

        return $strings_arr;
    }

    /**
     * @param null|int $tenant_id We can force to get settings for a specific tenant
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array Settings saved in database for current instance
     */
    public function get_db_settings(?int $tenant_id = null, bool $force = false) : array
    {
        if (!PHS::is_multi_tenant()
            || ($tenant_id === null
                && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        if (empty($force)
         && !empty($this->_db_settings[$tenant_id])) {
            return $this->_db_settings[$tenant_id];
        }

        $instance_id = $this->instance_id();

        if (!$this->_load_plugins_instance()
            || !($db_settings = $this->_plugins_instance->get_plugins_db_settings($instance_id, $tenant_id, $force))
            || !($db_settings = $this->_deobfuscate_settings_array($db_settings))) {
            if ($tenant_id) {
                return [];
            }

            $db_settings = [];
        }

        // Merge with defaults only for "main" settings
        if (!$tenant_id
            && ($default_settings = $this->get_default_settings())) {
            $db_settings = self::validate_array($db_settings, $default_settings);
        }

        // Low level hook for plugin settings keys that should be obfuscated (allows only keys that are not present in plugin settings)
        /** @var PHS_Event_Plugin_settings $event_obj */
        if (($event_obj = PHS_Event_Plugin_settings::trigger([
            'tenant_id'    => $tenant_id,
            'instance_id'  => $instance_id,
            'settings_arr' => $db_settings,
        ]))
            && ($extra_settings_arr = $event_obj->get_output('settings_arr'))
        ) {
            $db_settings = self::merge_array_assoc($db_settings, $extra_settings_arr);
        }

        $this->_db_settings[$tenant_id] = $db_settings;

        return $this->_db_settings[$tenant_id];
    }

    /**
     * @param int $tenant_id For which tenant do we want settings?
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array Settings saved in database for provided tenant for current instance
     */
    public function get_tenant_db_settings(int $tenant_id, bool $force = false) : array
    {
        if (empty($tenant_id)
            || !PHS::is_multi_tenant()) {
            return [];
        }

        if (empty($force)
         && !empty($this->_db_tenant_settings[$tenant_id])) {
            return $this->_db_tenant_settings[$tenant_id];
        }

        $instance_id = $this->instance_id();

        if (!$this->_load_plugins_instance()
            || !($db_settings = $this->_plugins_instance->get_plugins_db_tenant_settings($instance_id, $tenant_id, $force))
            || !($db_settings = $this->_deobfuscate_settings_array($db_settings))) {
            return [];
        }

        // Low level hook for plugin settings keys that should be obfuscated (allows only keys that are not present in plugin settings)
        /** @var PHS_Event_Tenant_plugin_settings $event_obj */
        if (($event_obj = PHS_Event_Tenant_plugin_settings::trigger([
            'tenant_id'    => $tenant_id,
            'instance_id'  => $instance_id,
            'settings_arr' => $db_settings,
        ]))
            && ($extra_settings_arr = $event_obj->get_output('settings_arr'))
        ) {
            $db_settings = self::validate_array($extra_settings_arr, $db_settings);
        }

        $this->_db_tenant_settings[$tenant_id] = $db_settings;

        return $this->_db_tenant_settings[$tenant_id];
    }

    /**
     * @param array $settings_arr Settings to be saved
     * @param null|int $tenant_id For which tenant are we saving the settings (if any)
     *
     * @return null|array
     */
    public function save_db_settings(array $settings_arr, ?int $tenant_id = null) : ?array
    {
        $this->reset_error();

        if (!PHS::is_multi_tenant()
            || ($tenant_id === null
                && !($tenant_id = PHS_Tenants::get_current_tenant_id()))) {
            $tenant_id = 0;
        }

        $instance_id = $this->instance_id();
        $old_settings = $this->get_db_settings($tenant_id);

        if (!$this->_load_plugins_instance()
         || null === ($obfuscated_settings_arr = $this->_obfuscate_settings_array($settings_arr))
         || null === ($db_settings = $this->_plugins_instance->save_plugins_db_settings($instance_id, $obfuscated_settings_arr, $tenant_id))
        ) {
            if (!$this->has_error()
                && $this->_plugins_instance->has_error()) {
                $this->copy_error($this->_plugins_instance);
            }

            return null;
        }

        $db_settings ??= [];

        PHS_Event_Plugin_settings_saved::trigger([
            'tenant_id'         => $tenant_id,
            'instance_id'       => $instance_id,
            'instance_type'     => $this->instance_type(),
            'plugin_name'       => $this->instance_plugin_name(),
            'old_settings_arr'  => $old_settings,
            'new_settings_arr'  => $db_settings,
            'obfucate_keys_arr' => $this->get_all_settings_keys_to_obfuscate(),
        ]);

        $this->_db_settings[$tenant_id] = $db_settings;

        // invalidate cached data...
        $this->_db_details = [];

        return $this->_db_settings[$tenant_id];
    }

    public function db_record_active() : bool
    {
        /** @var PHS_Model_Plugins $plugin_obj */
        return $this->_load_plugins_instance()
                && ($db_details = $this->get_merged_db_details())
                && isset($db_details['status'])
                && $this->_plugins_instance->active_status($db_details['status']);
    }

    /**
     * @return bool true on success, false on failure
     */
    protected function _load_plugins_instance() : bool
    {
        $this->reset_error();

        if (!$this->_plugins_instance && !($this->_plugins_instance = PHS_Model_Plugins::get_instance())) {
            $this->set_error(self::ERR_PLUGINS_MODEL, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }

    private function _get_db_settings_as_strings_from_fields(
        array $settings_structure, array $settings_arr, array $defaults_arr,
        array &$strings_arr
    ) : void {
        foreach ($settings_structure as $field_name => $field_details) {
            if (self::settings_field_is_group($field_details)) {
                $this->_get_db_settings_as_strings_from_fields($field_details['group_fields'], $settings_arr, $defaults_arr, $strings_arr);
                continue;
            }

            if (array_key_exists($field_name, $settings_arr)) {
                $field_value = $settings_arr[$field_name];
            } elseif (array_key_exists($field_name, $defaults_arr)) {
                $field_value = $defaults_arr[$field_name];
            } else {
                $field_value = null;
            }

            $strings_arr[$field_name] = [
                'title'       => $field_details['display_name'] ?? '',
                'hint'        => $field_details['display_hint'] ?? '',
                'placeholder' => $field_details['display_placeholder'] ?? '',
                'value'       => $this->_get_field_value_as_string($field_name, $field_details, $field_value),
            ];
        }
    }

    private function _get_field_value_as_string(string $field_name, array $field_details, $field_value) : string
    {
        $use_custom_renderer = (!empty($field_details['custom_renderer']) && is_callable($field_details['custom_renderer']));
        $custom_renderer_get_preset_buffer = (!empty($field_details['custom_renderer_get_preset_buffer']));

        $callback_params = [];
        if ($use_custom_renderer) {
            $callback_params = self::default_custom_renderer_params();
            $callback_params['value_as_text'] = true;
            $callback_params['field_id'] = $field_name;
            $callback_params['field_name'] = $field_name;
            $callback_params['field_details'] = $field_details;
            $callback_params['field_value'] = $field_value;
            $callback_params['form_data'] = [];
            $callback_params['editable'] = (!empty($field_details['editable']));
            $callback_params['plugin_obj'] = $this->get_plugin_instance();
        }

        if ($use_custom_renderer
            && !$custom_renderer_get_preset_buffer) {
            // Default settings input rendering is not required...
            if (($string_value = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
                || $string_value === null) {
                $string_value = '['.$this->_pt('Render settings field call failed.').']';
            }

            return $string_value;
        }

        ob_start();

        if (!empty($field_details['input_type'])) {
            switch ($field_details['input_type']) {
                case self::INPUT_TYPE_KEY_VAL_ARRAY:
                    if (!is_array($field_value)) {
                        echo $this->_pt('Not a key-value array...');
                    } else {
                        $value_buf = '';
                        foreach ($field_value as $field_value_key => $field_value_val) {
                            $value_buf .= ($value_buf !== '' ? ', ' : '')
                                          .'<em>'.$field_value_key.'</em>: '.($field_value_val ?? $this->_pt('N/A'));
                        }

                        echo $value_buf ?: $this->_pt('N/A');
                    }
                    break;

                case self::INPUT_TYPE_TEMPLATE:

                    echo $this->_pt('Template file').': ';
                    if (is_string($field_value)) {
                        echo $field_value;
                    } elseif (!empty($field_value['file'])) {
                        echo $field_value['file'];
                    } else {
                        echo $this->_pt('N/A');
                    }

                    if (is_array($field_value)
                        && !empty($field_value['extra_paths']) && is_array($field_value['extra_paths'])) {
                        echo '<br/>'.$this->_pt('From paths').': ';

                        $paths_arr = [];
                        foreach ($field_value['extra_paths'] as $path_dir => $path_www) {
                            $paths_arr[] = $path_dir;
                        }

                        if (empty($paths_arr)) {
                            echo $this->_pt('N/A');
                        } else {
                            echo implode(', ', $paths_arr);
                        }
                    }
                    break;

                case self::INPUT_TYPE_ONE_OR_MORE:
                case self::INPUT_TYPE_ONE_OR_MORE_MULTISELECT:
                    if (empty($field_details['values_arr'])
                        || !is_array($field_details['values_arr'])) {
                        echo $this->_pt('Values array should be provided');
                    } else {
                        if (empty($field_value) || !is_array($field_value)) {
                            $field_value = [];
                        }

                        $values_arr = [];
                        foreach ($field_details['values_arr'] as $one_more_key => $one_more_text) {
                            if (!in_array($one_more_key, $field_value, false)) {
                                continue;
                            }

                            $values_arr[] = $one_more_text;
                        }

                        echo !empty($values_arr) ? implode(', ', $values_arr) : $this->_pt('N/A');
                    }
                    break;

                case self::INPUT_TYPE_TEXTAREA:
                    echo $field_value;
                    break;
            }
        } else {
            if (!empty($field_details['values_arr'])
                && is_array($field_details['values_arr'])) {
                echo $field_details['values_arr'][$field_value] ?? $this->_pt('N/A');
            } else {
                switch ($field_details['type']) {
                    default:
                        echo $field_value;
                        break;

                    case PHS_Params::T_BOOL:
                    case PHS_Params::T_NUMERIC_BOOL:
                        echo !empty($field_value) ? $this->_pt('Yes') : $this->_pt('No');
                        break;
                }
            }
        }

        if (false === ($string_value = @ob_get_clean())) {
            $string_value = '';
        }

        if ($use_custom_renderer
            && $custom_renderer_get_preset_buffer) {
            $callback_params['preset_content'] = $string_value;
            $callback_params['callback_params'] = (!empty($field_details['custom_renderer_params']) ? $field_details['custom_renderer_params'] : false);

            if (($string_value = @call_user_func($field_details['custom_renderer'], $callback_params)) === false
                || $string_value === null) {
                $string_value = '['.$this->_pt('Render settings field call failed.').']';
            }

            return $string_value;
        }

        return $string_value;
    }

    private function _obfuscate_settings_array(array $settings_arr) : ?array
    {
        $this->reset_error();

        // Obfuscate settings before saving in database...
        if (($obfuscating_keys = $this->get_all_settings_keys_to_obfuscate())) {
            foreach ($obfuscating_keys as $ob_key) {
                if (array_key_exists($ob_key, $settings_arr)
                    && is_scalar($settings_arr[$ob_key])) {
                    if (false === ($encrypted_data = PHS_Crypt::quick_encode($settings_arr[$ob_key]))) {
                        $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obfuscating plugin settings.'));

                        return null;
                    }

                    $settings_arr[$ob_key] = $encrypted_data;
                }
            }
        }

        return $settings_arr;
    }

    private function _deobfuscate_settings_array(?array $settings_arr) : array
    {
        if (empty($settings_arr)) {
            return [];
        }

        if (($obfuscating_keys = $this->get_all_settings_keys_to_obfuscate())) {
            foreach ($obfuscating_keys as $ob_key) {
                if (array_key_exists($ob_key, $settings_arr)
                    && is_string($settings_arr[$ob_key])) {
                    // In case we are in install mode and errors will get thrown
                    try {
                        if (false === ($settings_arr[$ob_key] = PHS_Crypt::quick_decode($settings_arr[$ob_key]))) {
                            PHS_Logger::error('[CONFIG ERROR] Error decoding old config value for ['.$this->instance_id().'] '
                                              .'settings key ['.$ob_key.']', PHS_Logger::TYPE_DEBUG);
                            $settings_arr[$ob_key] = '';
                        }
                    } catch (\Exception $e) {
                        PHS_Logger::error('[CONFIG ERROR] Error decoding old config value for ['.$this->instance_id().'] '
                                          .'settings key ['.$ob_key.']: '.$e->getMessage(), PHS_Logger::TYPE_DEBUG);
                        $settings_arr[$ob_key] = '';
                    }
                }
            }
        }

        return $settings_arr;
    }

    public static function default_custom_renderer_params() : array
    {
        return [
            'field_id'      => '',
            'field_name'    => '',
            'field_details' => false,
            'field_value'   => null,
            'form_data'     => [],
            // Any extra parameters sent from field (if any)
            'callback_params'  => false,
            'editable'         => true,
            'preset_content'   => '',
            'plugin_obj'       => null,
            'value_as_text'    => false,
            'should_obfuscate' => false,
        ];
    }

    public static function st_default_custom_save_params() : array
    {
        return [
            'tenant_id'       => 0,
            'is_multi_tenant' => false,
            'field_name'      => '',
            'field_details'   => false,
            'field_value'     => null,
            'form_data'       => [],
            'plugin_obj'      => null,
            'model_obj'       => null,
        ];
    }

    /**
     * When there is a field in instance settings which has a custom callback for saving data, it will return
     * either a scalar or an array to be merged with existing settings. Only keys which already exists as settings
     * can be provided
     * @return array
     */
    public static function st_default_custom_save_callback_result() : array
    {
        return [
            // If there are more fields affected by the callback, provide a full array which will be merged with full settings array
            '{new_settings_fields}' => [],
        ];
    }

    /**
     * Tells if settings field definition array is for a group
     * @param array $settings_field
     *
     * @return bool
     */
    public static function settings_field_is_group(array $settings_field) : bool
    {
        return !empty($settings_field['group_fields']) && is_array($settings_field['group_fields']);
    }

    /**
     * First method called when extracting plugin settings (V2)
     * @param array $context_arr
     *
     * @return null|array
     */
    public static function init_settings_context(array $context_arr) : ?array
    {
        self::st_reset_error();
        $context_arr = self::validate_array($context_arr, self::_default_context_array());

        $is_multi_tenant = PHS::is_multi_tenant();

        $plugin = $context_arr['plugin'] ?? PHS_Instantiable::CORE_PLUGIN;
        $model = $context_arr['model_id'] ?? '';
        $plugin_obj = null;
        $model_obj = null;

        $tenant_id = !$is_multi_tenant ? 0 : ($context_arr['tenant_id'] ?? 0);

        $context_arr['plugin'] = $plugin;
        $context_arr['model_id'] = $model;
        $context_arr['tenant_id'] = $tenant_id;
        $context_arr['is_multi_tenant'] = $is_multi_tenant;
        $context_arr['settings_for_tenant'] = ($is_multi_tenant && $tenant_id);

        if ($plugin !== PHS_Instantiable::CORE_PLUGIN
            && (!($instance_details = PHS_Instantiable::valid_instance_id($plugin))
                || empty($instance_details['instance_type'])
                || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                || !($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))
            )
        ) {
            $context_arr['redirect_to'] = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins'], ['unknown_plugin' => 1]);
            $context_arr['errors'] = self::arr_set_error(self::ERR_PARAMETERS, self::_t('Unknown plugin.'));

            return $context_arr;
        }
        $context_arr['plugin_instance'] = $plugin_obj;

        if ($plugin_obj === null) {
            $context_arr['models_arr'] = PHS::get_core_models();
        } else {
            $context_arr['models_arr'] = $plugin_obj->get_models();

            if (!$plugin_obj->is_multi_tenant()) {
                $context_arr['is_multi_tenant'] = false;
                $context_arr['settings_for_tenant'] = false;
                $context_arr['tenant_id'] = 0;
            }
        }

        if (!empty($model)
            && (!in_array($model, $context_arr['models_arr'], true)
                || !($model_obj = PHS::load_model($model, $plugin_obj?->instance_plugin_name()))
            )
        ) {
            $context_arr['stop_executon'] = true;
            $context_arr['errors'] = self::arr_set_error(self::ERR_PARAMETERS, self::_t('Unknown model.'));

            return $context_arr;
        }
        $context_arr['model_instance'] = $model_obj;

        if (($settings_arr = self::_extract_settings_for_instance($plugin_obj ?? $model_obj, $tenant_id))) {
            $context_arr = self::merge_array_assoc_existing($context_arr, $settings_arr);
        }

        return $context_arr;
    }

    /**
     * Given a context, extract form data and setting according to subbmited data. If no submit array is provided,
     * extract the data from a form submit as default (V2)
     *
     * @param array $context_arr
     * @param null|array $submit_arr
     *
     * @return null|array
     */
    public static function extract_settings_and_form_data_from_context(array $context_arr, ?array $submit_arr = null) : ?array
    {
        self::st_reset_error();
        $context_arr = self::validate_array($context_arr, self::_default_context_array());

        if (empty($context_arr['is_multi_tenant'])
            || !($context_arr['tenant_custom_fields'] = PHS_Params::_gp('tenant_custom_fields', PHS_Params::T_ARRAY))
        ) {
            $context_arr['tenant_custom_fields'] = [];
        }

        self::_extract_settings_and_form_data_from_submit(
            $context_arr['settings_structure'], $context_arr,
            $context_arr['submit_settings'], $context_arr['form_data'], $context_arr['tenant_custom_fields'],
            $context_arr['extract_submit'], $submit_arr
        );

        return $context_arr;
    }
    // endregion END NEW settings section

    /**
     * Check each fields for custom save callbacks and obtain values (V2)
     *
     * @param array $context_arr
     *
     * @return null|array
     */
    public static function get_custom_save_fields_settings_for_save(array $context_arr) : ?array
    {
        $callback_params = self::st_default_custom_save_params();
        $callback_params['tenant_id'] = $context_arr['tenant_id'] ?? 0;
        $callback_params['is_multi_tenant'] = $context_arr['is_multi_tenant'] ?? false;
        $callback_params['plugin_obj'] = $context_arr['plugin_instance'] ?? null;
        $callback_params['model_obj'] = $context_arr['model_instance'] ?? null;
        $callback_params['form_data'] = $context_arr['form_data'] ?? [];

        $context_arr['submit_settings'] ??= [];
        $context_arr['errors_arr'] ??= [];
        $context_arr['warnings_arr'] ??= [];

        if (!empty($context_arr['settings_structure'])) {
            self::_get_custom_save_fields_settings_for_save_from_structure(
                $context_arr['settings_structure'], $callback_params, $context_arr,
                $context_arr['submit_settings'], $context_arr['errors_arr'], $context_arr['warnings_arr']
            );
        }

        return $context_arr;
    }

    /**
     * @param null|PHS_Plugin $plugin_obj
     *
     * @return array
     */
    public static function get_plugin_models_with_settings(?PHS_Plugin $plugin_obj) : array
    {
        if ($plugin_obj === null) {
            $plugin_models_arr = PHS::get_core_models();
        } else {
            $plugin_models_arr = $plugin_obj->get_models();
        }

        if (empty($plugin_models_arr)) {
            return [];
        }

        $return_arr = [];
        foreach ($plugin_models_arr as $model_name) {
            if (!($model_instance = PHS::load_model($model_name, ($plugin_obj ? $plugin_obj->instance_plugin_name() : null)))
                || !($model_id = $model_instance->instance_id())) {
                continue;
            }

            $return_arr[$model_id] = $model_instance;
        }

        return $return_arr;
    }

    // region NEW settings section
    private static function _default_context_array() : array
    {
        $context_arr = [];
        $context_arr['is_multi_tenant'] = false;
        $context_arr['tenant_id'] = 0;
        $context_arr['settings_for_tenant'] = false;
        $context_arr['plugin'] = '';
        $context_arr['model_id'] = '';
        $context_arr['extract_submit'] = false;

        $context_arr['models_arr'] = [];

        $context_arr['stop_executon'] = false;
        $context_arr['redirect_to'] = null;
        $context_arr['errors'] = null;

        $context_arr['db_version'] = '0.0.0';
        $context_arr['script_version'] = '0.0.0';

        $context_arr['settings_keys_to_obfuscate'] = [];

        $context_arr['default_settings'] = [];
        $context_arr['db_main_settings'] = [];
        $context_arr['db_tenant_settings'] = [];
        $context_arr['db_settings'] = [];

        $context_arr['tenant_custom_fields'] = [];
        $context_arr['submit_settings'] = [];
        $context_arr['form_data'] = [];

        $context_arr['settings_structure'] = [];
        $context_arr['plugin_instance'] = null;
        $context_arr['model_instance'] = null;

        // Any errors or warnings from custom renenders or custom save callbacks
        $context_arr['errors_arr'] = [];
        $context_arr['warnings_arr'] = [];

        return $context_arr;
    }

    /**
     * Extract settings and form data from submit (V2)
     *
     * @param array $settings_structure
     * @param array $context_arr
     * @param array $submit_settings
     * @param array $form_data
     * @param array $custom_fields
     * @param bool $is_post
     * @param null|array $submit_arr
     */
    private static function _extract_settings_and_form_data_from_submit(
        array $settings_structure, array $context_arr,
        array &$submit_settings, array &$form_data, array &$custom_fields,
        bool $is_post = false, ?array $submit_arr = null
    ) : void {
        if (empty($settings_structure)) {
            return;
        }

        foreach ($settings_structure as $field_name => $field_details) {
            if (!empty($field_details['ignore_field_value'])) {
                continue;
            }

            if (self::settings_field_is_group($field_details)) {
                self::_extract_settings_and_form_data_from_submit(
                    $field_details['group_fields'], $context_arr,
                    $submit_settings, $form_data, $custom_fields,
                    $is_post, $submit_arr);

                continue;
            }

            if (!$is_post
                && !empty($context_arr['db_tenant_settings'][$field_name])) {
                $custom_fields[$field_name] = 1;
                $context_arr['tenant_custom_fields'][$field_name] = 1;
            }

            if (null === ($field_value = self::_extract_field_value_for_settings_and_form_data_from_submit(
                $field_name, $field_details, $context_arr,
                $form_data,
                $is_post, $submit_arr))) {
                continue;
            }

            $submit_settings[$field_name] = $field_value;
        }
    }

    /**
     * Given a field name, extract settings and form data from submitted data (V2)
     * @param string $field_name
     * @param array $field_details
     * @param array $context_arr
     * @param array $form_data
     * @param bool $is_post
     * @param null|array $submit_arr
     *
     * @return null|array|bool|float|int|mixed|string
     */
    private static function _extract_field_value_for_settings_and_form_data_from_submit(
        string $field_name, array $field_details, array $context_arr,
        array &$form_data,
        bool $is_post = false, ?array $submit_arr = null
    ) {
        $field_value = null;

        $db_settings = ($context_arr['db_settings'] ?? []);
        $tenant_settings = ($context_arr['db_tenant_settings'] ?? []);
        $default_settings = ($context_arr['default_settings'] ?? []);
        $field_for_tenant = $context_arr['settings_for_tenant'] ?? false;

        if (empty($field_details['editable'])) {
            if ($field_for_tenant) {
                return null;
            }

            // Check if default values have changed (upgrading plugin might change default value)
            return $db_settings[$field_name] ?? $default_settings[$field_name] ?? null;
        }

        if ($field_for_tenant
            && (
                ($is_post && empty($context_arr['tenant_custom_fields'][$field_name]))
                || (!$is_post && !isset($tenant_settings[$field_name]) && $field_details['input_type'] !== self::INPUT_TYPE_KEY_VAL_ARRAY)
            )
        ) {
            return null;
        }

        $field_details['type'] = (int)$field_details['type'];

        if (isset($submit_arr[$field_name])) {
            $form_data[$field_name]
                = PHS_Params::set_type($submit_arr[$field_name], $field_details['type'], $field_details['extra_type']);
        } else {
            $form_data[$field_name]
                = PHS_Params::_gp($field_name, $field_details['type'], $field_details['extra_type']);
        }

        if ($is_post
            && ($field_details['type'] === PHS_Params::T_BOOL
                || $field_details['type'] === PHS_Params::T_NUMERIC_BOOL)
        ) {
            $form_data[$field_name] = (!empty($form_data[$field_name]));

            if ($field_details['type'] === PHS_Params::T_NUMERIC_BOOL) {
                $form_data[$field_name] = $form_data[$field_name] ? 1 : 0;
            }
        }

        if ($is_post) {
            if (isset($form_data[$field_name])) {
                $field_value = $form_data[$field_name];
            } elseif ($field_details['input_type'] === self::INPUT_TYPE_ONE_OR_MORE_MULTISELECT) {
                if (!is_array(($field_value = $default_settings[$field_name] ?? []))) {
                    $field_value = [];
                }
            }
        } elseif ($field_for_tenant) {
            $field_value = $tenant_settings[$field_name] ?? $db_settings[$field_name] ?? $default_settings[$field_name] ?? null;
        } else {
            $field_value = $db_settings[$field_name] ?? $default_settings[$field_name] ?? null;
        }

        if ($field_details['input_type'] === self::INPUT_TYPE_KEY_VAL_ARRAY) {
            if ($field_for_tenant && !$is_post) {
                $field_value = $tenant_settings[$field_name] ?? $default_settings[$field_name];
            } else {
                $field_value = self::validate_array_to_new_array($form_data[$field_name] ?? [], $field_value);
            }
        }

        if (!$is_post) {
            $form_data[$field_name] = $field_value;
        }

        return $field_value;
    }

    /**
     * Details required when rendering settings (V2)
     * @param null|PHS_Has_db_settings $instance_obj
     * @param null|int $tenant_id
     *
     * @return array
     */
    private static function _extract_settings_for_instance(?self $instance_obj, ?int $tenant_id = null) : array
    {
        $tenant_id ??= 0;
        $is_multi_tenant = PHS::is_multi_tenant();

        if ($instance_obj === null) {
            $core_info = PHS_Plugin::core_plugin_details_fields();

            $settings_structure_arr = [];
            $default_settings = [];
            $db_main_settings = [];
            $db_tenant_settings = [];
            $db_settings = [];
            $db_version = $core_info['db_version'] ?? '0.0.0';
            $script_version = $core_info['script_version'] ?? '0.0.0';

            $settings_keys_to_obfuscate = [];
        } else {
            if (!($settings_structure_arr = $instance_obj->validate_settings_structure())) {
                $settings_structure_arr = [];
            }

            if (!($default_settings = $instance_obj->get_default_settings())) {
                $default_settings = [];
            }

            if (!$is_multi_tenant
               || empty($tenant_id)
               || !($db_tenant_settings = $instance_obj->get_tenant_db_settings($tenant_id))) {
                $db_tenant_settings = [];
            }

            if ($instance_obj instanceof PHS_Model) {
                $script_version = $instance_obj->get_model_version();
            } elseif ($instance_obj instanceof PHS_Plugin) {
                $script_version = $instance_obj->get_plugin_version();
            } else {
                $script_version = '0.0.0';
            }

            $db_main_settings = $instance_obj->get_db_settings(0);
            if ($is_multi_tenant) {
                $db_settings = $instance_obj->get_db_settings($tenant_id);
            } else {
                $db_settings = $db_main_settings;
            }
            $db_version = $instance_obj->get_db_version();

            $settings_keys_to_obfuscate = $instance_obj->get_all_settings_keys_to_obfuscate();
        }

        $return_arr = [];
        $return_arr['db_version'] = $db_version;
        $return_arr['script_version'] = $script_version;

        $return_arr['settings_structure'] = $settings_structure_arr;
        $return_arr['default_settings'] = $default_settings;
        $return_arr['db_main_settings'] = $db_main_settings;
        $return_arr['db_tenant_settings'] = $db_tenant_settings;
        $return_arr['db_settings'] = $db_settings;

        $return_arr['settings_keys_to_obfuscate'] = $settings_keys_to_obfuscate;

        // Temporary TO BE DELETED
        $return_arr['instance'] = $instance_obj;

        return $return_arr;
    }

    /**
     * Extract custom save value for each field in the group (V2)
     * @param array $settings_structure
     * @param array $callback_params
     * @param array $context_arr
     * @param array $new_settings
     * @param array $errors_arr
     * @param array $warnings_arr
     */
    private static function _get_custom_save_fields_settings_for_save_from_structure(
        array $settings_structure, array $callback_params, array $context_arr,
        array &$new_settings, array &$errors_arr, array &$warnings_arr
    ) : void {
        // make sure static error is reset
        self::st_reset_error();
        // make sure static warnings are reset
        self::st_reset_warnings();

        $all_db_settings = $context_arr['db_settings'] ?? [];
        $new_settings ??= [];

        $default_custom_save_callback_result = PHS_Plugin::st_default_custom_save_callback_result();
        foreach ($settings_structure as $field_name => $field_details) {
            if (self::settings_field_is_group($field_details)) {
                self::_get_custom_save_fields_settings_for_save_from_structure(
                    $field_details['group_fields'], $callback_params, $context_arr,
                    $new_settings, $errors_arr, $warnings_arr
                );

                continue;
            }

            if (!empty($field_details['ignore_field_value'])
                || (!empty($context_arr['settings_for_tenant'])
                    && empty($context_arr['tenant_custom_fields'][$field_name]))
                || empty($field_details['custom_save'])
                || !@is_callable($field_details['custom_save'])
            ) {
                continue;
            }

            if ($context_arr['settings_for_tenant']) {
                $current_value = $context_arr['db_tenant_settings'][$field_name] ?? $context_arr['default_settings'][$field_name] ?? null;
            } else {
                $current_value = $context_arr['db_main_settings'][$field_name] ?? $context_arr['default_settings'][$field_name] ?? null;
            }

            $new_callback_params = $callback_params;
            $new_callback_params['field_name'] = $field_name;
            $new_callback_params['field_details'] = $field_details;
            $new_callback_params['field_value'] = $current_value;

            // make sure static error is reset
            self::st_reset_error();
            // make sure static warnings are reset
            self::st_reset_warnings();

            /**
             * When there is a field in instance settings which has a custom callback for saving data, it will return
             * either a scalar or an array to be merged with existing settings. Only keys which already exists as settings
             * can be provided
             */
            if (null !== ($save_result = @call_user_func($field_details['custom_save'], $new_callback_params))) {
                if (!is_array($save_result)) {
                    $new_settings[$field_name] = $save_result;
                } else {
                    $save_result = self::merge_array_assoc($save_result, $default_custom_save_callback_result);
                    if (!empty($save_result['{new_settings_fields}'])
                        && is_array($save_result['{new_settings_fields}'])) {
                        // Main settings keep all key-values pairs
                        foreach ($all_db_settings as $s_key => $s_val) {
                            if (array_key_exists($s_key, $save_result['{new_settings_fields}'])) {
                                $new_settings[$s_key] = $save_result['{new_settings_fields}'][$s_key];
                            }
                        }
                    } else {
                        if (isset($save_result['{new_settings_fields}'])) {
                            unset($save_result['{new_settings_fields}']);
                        }

                        $new_settings[$field_name] = $save_result;
                    }
                }
            } elseif (self::st_has_error()) {
                $errors_arr[] = self::st_get_simple_error_message();
            }

            if (self::st_has_warnings()
                && ($result_warnings_arr = self::st_get_warnings())) {
                $warnings_arr = array_merge($warnings_arr, $result_warnings_arr);
            }

            // make sure static error is reset
            self::st_reset_error();
            // make sure static warnings are reset
            self::st_reset_warnings();
        }
    }

    private static function _db_details_fields_prepare_for_merge(array $db_details) : array
    {
        $fields_arr = self::_get_merged_db_details_fields();
        $return_arr = [];
        foreach ($fields_arr as $key => $def_val) {
            $return_arr[$key] = $db_details[$key] ?? $def_val;
        }

        return $return_arr;
    }

    private static function _get_merged_db_details_fields() : array
    {
        return ['tenant_id' => 0, 'instance_id' => '', 'type' => '', 'plugin' => '', 'settings' => null, 'status' => 0, 'status_date' => null,
            'last_update'   => null, 'cdate' => null,
            // "main" values
            'is_core' => false, 'version' => null, ];
    }

    private static function _default_settings_field() : array
    {
        return [
            // Used to know how to render this field in plugin settings
            'type' => PHS_Params::T_ASIS,
            // When we validate the input is there extra parameters to send to PHS_Params class?
            'extra_type' => false,
            // If type key doesn't define well how this field should be rendered in plugin details use this to know how to reneder it
            // This is a string (empty string means render as default depending on type key)
            'input_type' => '',
            // Default value if not present in database
            'default'             => null,
            'display_name'        => '',
            'display_hint'        => '',
            'display_placeholder' => '',
            // Should this field be editable?
            'editable' => true,
            // An array with key => text to be used in plugin settings (key will be saved as value and text will be displayed to user)
            'values_arr' => false,
            // Custom rendering callback function (if available)
            'custom_renderer' => false,
            // Custom rendering callback function extra parameters (if required)
            'custom_renderer_params' => false,
            // If we are using a custom renderer should we receive default framework rendering of the settings input
            'custom_renderer_get_preset_buffer' => false,
            // If we have a custom method which should parse settings form submit...
            // this function should return value to be used for current field in settings array
            // If method returns null and static error is set, statitc error message will be used to display error
            'custom_save' => false,
            // If this field is not supposed to be a value to be saved in database, but a placeholder
            // for a functionality with a custom rendering
            'ignore_field_value' => false,
            // true if this is supposed to have a single value for all tenants
            'only_main_tenant_value' => false,

            'extra_style'   => '',
            'extra_classes' => '',

            // group settings...
            // an array containing fields in this group
            'group_fields' => false,
            // Should group fold/unfold when displayed in settings interface?
            'group_foldable' => true,

            // Do not render this field (will be managed by a custom save option)
            'skip_rendering' => false,
        ];
    }

    /**
     * @param array $structure_arr
     *
     * @return array
     */
    private static function _validate_settings_structure_fields(array $structure_arr) : array
    {
        if (empty($structure_arr)) {
            return [];
        }

        $default_settings_field = self::_default_settings_field();
        $settings_structure = [];
        foreach ($structure_arr as $key => $settings_field) {
            $settings_field = self::validate_array_recursive($settings_field, $default_settings_field);

            if (self::settings_field_is_group($settings_field)) {
                $settings_field['group_fields'] = self::_validate_settings_structure_fields($settings_field['group_fields']);
            }

            $settings_structure[$key] = $settings_field;
        }

        return $settings_structure;
    }

    private static function _get_default_settings_for_structure($structure_arr) : array
    {
        $default_arr = [];
        foreach ($structure_arr as $field_name => $field_arr) {
            if (self::settings_field_is_group($field_arr)) {
                if (($group_default = self::_get_default_settings_for_structure($field_arr['group_fields']))) {
                    $default_arr = self::merge_array_assoc($default_arr, $group_default);
                }

                continue;
            }

            if (!empty($field_arr['ignore_field_value'])) {
                continue;
            }

            $default_arr[$field_name] = $field_arr['default'];
        }

        return $default_arr;
    }
}
