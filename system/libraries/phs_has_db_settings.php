<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Crypt;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_saved;
use phs\system\core\events\plugins\PHS_Event_Plugin_settings_obfuscated_keys;

abstract class PHS_Has_db_settings extends PHS_Instantiable
{
    public const ERR_PLUGINS_MODEL = 40000;

    public const INPUT_TYPE_TEMPLATE = 'template', INPUT_TYPE_ONE_OR_MORE = 'one_or_more',
    INPUT_TYPE_ONE_OR_MORE_MULTISELECT = 'one_or_more_multiselect', INPUT_TYPE_KEY_VAL_ARRAY = 'key_val_array',
    INPUT_TYPE_TEXTAREA = 'textarea';

    /** @var bool|\phs\system\core\models\PHS_Model_Plugins */
    protected $_plugins_instance = false;

    // Validated settings fields structure array
    private array $_settings_structure = [];

    // Array with default values for settings (key => val) array
    private array $_default_settings = [];

    // Database record
    private array $_db_details = [];

    // Database settings field parsed as array
    private array $_db_settings = [];

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
    public function get_db_details(bool $force = false) : ?array
    {
        if (empty($force)
         && !empty($this->_db_details)) {
            return $this->_db_details;
        }

        if (!$this->_load_plugins_instance()
         || !($db_details = $this->_plugins_instance->get_plugins_db_details($this->instance_id(), $force))) {
            return null;
        }

        $this->_db_details = $db_details;

        return $this->_db_details;
    }

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array Settings saved in database for current instance
     */
    public function get_db_settings(bool $force = false) : array
    {
        if (empty($force)
         && !empty($this->_db_settings)) {
            return $this->_db_settings;
        }

        $instance_id = $this->instance_id();

        if (!$this->_load_plugins_instance()
            || !($db_settings = $this->_plugins_instance->get_plugins_db_settings($instance_id, $force))
            || !($db_settings = $this->_deobfuscate_settings_array($db_settings))) {
            return [];
        }

        if (($default_settings = $this->get_default_settings())) {
            $db_settings = self::validate_array($db_settings, $default_settings);
        }

        // Low level hook for plugin settings keys that should be obfuscated (allows only keys that are not present in plugin settings)
        /** @var PHS_Event_Plugin_settings $event_obj */
        if (($event_obj = PHS_Event_Plugin_settings::trigger([
            'instance_id'  => $instance_id,
            'settings_arr' => $db_settings,
        ]))
            && ($extra_settings_arr = $event_obj->get_output('settings_arr'))
        ) {
            $db_settings = self::validate_array($extra_settings_arr, $db_settings);
        }

        $this->_db_settings = $db_settings;

        return $this->_db_settings;
    }

    public function save_db_settings(array $settings_arr) : ?array
    {
        $this->reset_error();

        $instance_id = $this->instance_id();
        $old_settings = $this->get_db_settings();

        if (!$this->_load_plugins_instance()
         || null === ($obfuscated_settings_arr = $this->_obfuscate_settings_array($settings_arr))
         || !($db_settings = $this->_plugins_instance->save_plugins_db_settings($instance_id, $obfuscated_settings_arr))
         || !is_array($db_settings)) {
            if (!$this->has_error()
                && $this->_plugins_instance->has_error()) {
                $this->copy_error($this->_plugins_instance);
            }

            return null;
        }

        PHS_Event_Plugin_settings_saved::trigger([
            'instance_id'       => $instance_id,
            'instance_type'     => $this->instance_type(),
            'plugin_name'       => $this->instance_plugin_name(),
            'old_settings_arr'  => $old_settings,
            'new_settings_arr'  => $db_settings,
            'obfucate_keys_arr' => $this->get_all_settings_keys_to_obfuscate(),
        ]);

        $this->_db_settings = $db_settings;

        // invalidate cached data...
        $this->_db_details = [];

        return $this->_db_settings;
    }

    public function db_record_active() : ?array
    {
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if (!$this->_load_plugins_instance()
         || !($db_details = $this->get_db_details())
         || !$this->_plugins_instance->active_status($db_details['status'])) {
            return null;
        }

        return $db_details;
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
            'callback_params' => false,
            'editable'        => true,
            'preset_content'  => '',
            'plugin_obj'      => null,
        ];
    }

    public static function st_default_custom_save_params() : array
    {
        return [
            'plugin_obj'      => null,
            'module_instance' => null,
            'field_name'      => '',
            'field_details'   => false,
            'field_value'     => null,
            'form_data'       => [],
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
            // fields with values to be saved in instance settings as key/value pairs
            '{new_settings_fields}' => [],
        ];
    }

    /**
     * Tells if settings field definition array is for a group
     * @param array $settings_field
     *
     * @return bool
     */
    public static function settings_field_is_group($settings_field) : bool
    {
        return !empty($settings_field['group_fields']) && is_array($settings_field['group_fields']);
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

            'extra_style'   => '',
            'extra_classes' => '',

            // group settings...
            // an array containing fields in this group
            'group_fields' => false,
            // Should group fold/unfold when displayed in settings interface?
            'group_foldable' => true,
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
