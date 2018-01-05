<?php

namespace phs\libraries;

use \phs\PHS;

abstract class PHS_Has_db_settings extends PHS_Signal_and_slot
{
    const ERR_PLUGINS_MODEL = 40000;
    
    const INPUT_TYPE_TEMPLATE = 'template', INPUT_TYPE_ONE_OR_MORE = 'one_or_more', INPUT_TYPE_KEY_VAL_ARRAY = 'key_val_array';

    // Validated settings fields structure array
    protected $_settings_structure = array();
    // Array with default values for settings (key => val) array
    protected $_default_settings = array();
    // Database record
    protected $_db_details = array();
    // Database settings field parsed as array
    protected $_db_settings = array();

    /** @var bool|\phs\system\core\models\PHS_Model_Plugins $_plugins_instance */
    protected $_plugins_instance = false;

    /**
     * @return bool true on success, false on failure
     */
    protected function _load_plugins_instance()
    {
        $this->reset_error();

        if( $this->_plugins_instance )
            return true;

        if( !($this->_plugins_instance = PHS::load_model( 'plugins' )) )
        {
            $this->_plugins_instance = false;
            $this->set_error( self::ERR_PLUGINS_MODEL, self::_t( 'Couldn\'t load plugins model.' ) );
            return false;
        }

        return true;
    }

    /**
     * Override this function and return an array with settings fields definition
     *
     * @return array
     */
    public function get_settings_structure()
    {
        return array();
    }

    protected function default_settings_field()
    {
        return array(
            // Used to know how to render this field in plugin settings
            'type' => PHS_params::T_ASIS,
            // When we validate the input is there extra parameters to send to PHS_params class?
            'extra_type' => false,
            // If type key doesn't define well how this field should be rendered in plugin details use this to know how to reneder it
            // This is a string (empty string means render as default depending on type key)
            'input_type' => '',
            // Default value if not present in database
            'default' => null,
            'display_name' => '',
            'display_hint' => '',
            'display_placeholder' => '',
            // Should this field be editable?
            'editable' => true,
            // An array with key => text to be used in plugin settings (key will be saved as value and text will be displayed to user)
            'values_arr' => false,
            // Custom rendering callback function (if available)
            'custom_renderer' => false,
            // If we have a custom method which should parse settings form submit...
            // this function should return value to be used for current field in settings array
            // If method returns null and static error is set, statitc error message will be used to display error
            'custom_save' => false,
            // If this field is not supposed to be a value to be saved in database, but a placeholder
            // for a functionality with a custom rendering
            'ignore_field_value' => false,

            'extra_style' => '',
            'extra_classes' => '',
        );
    }

    public function default_custom_renderer_params()
    {
        return array(
            'field_id' => '',
            'field_name' => '',
            'field_details' => false,
            'field_value' => null,
            'form_data' => array(),
            'editable' => true,
            'plugin_obj' => false,
        );
    }

    public function default_custom_save_params()
    {
        return self::st_default_custom_save_params();
    }

    static public function st_default_custom_save_params()
    {
        return array(
            'plugin_obj' => false,
            'module_instance' => false,
            'field_name' => '',
            'field_details' => false,
            'field_value' => null,
            'form_data' => array(),
        );
    }

    public function validate_settings_structure()
    {
        if( !empty( $this->_settings_structure ) )
            return $this->_settings_structure;

        $this->_settings_structure = array();

        // Validate settings structure
        if( !($settings_structure_arr = $this->get_settings_structure()) )
            return array();

        $default_settings_field = $this->default_settings_field();

        foreach( $settings_structure_arr as $key => $settings_field )
        {
            $settings_field = self::validate_array_recursive( $settings_field, $default_settings_field );

            $this->_settings_structure[$key] = $settings_field;
        }

        return $this->_settings_structure;
    }

    /**
     * @return array
     */
    final public function get_default_settings()
    {
        if( !empty( $this->_default_settings ) )
            return $this->_default_settings;

        if( empty( $this->_settings_structure ) )
            $this->validate_settings_structure();

        $this->_default_settings = array();
        foreach( $this->_settings_structure as $key => $field_arr )
        {
            $this->_default_settings[$key] = $field_arr['default'];
        }

        return $this->_default_settings;
    }

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array|bool
     */
    public function get_db_details( $force = false )
    {
        if( empty( $force )
        and !empty( $this->_db_details ) )
            return $this->_db_details;

        if( !$this->_load_plugins_instance()
         or !($db_details = $this->_plugins_instance->get_plugins_db_details( $this->instance_id(), $force ))
         or !is_array( $db_details ) )
            return false;

        $this->_db_details = $db_details;

        return $this->_db_details;
    }

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array|bool Settings saved in database for current instance
     */
    public function get_db_settings( $force = false )
    {
        if( empty( $force )
        and !empty( $this->_db_settings ) )
            return $this->_db_settings;

        if( !$this->_load_plugins_instance()
         or !($db_settings = $this->_plugins_instance->get_plugins_db_settings( $this->instance_id(), $this->get_default_settings(), $force ))
         or !is_array( $db_settings ) )
            return false;

        $this->_db_settings = $db_settings;

        return $this->_db_settings;
    }

    public function save_db_settings( $settings_arr )
    {
        if( !$this->_load_plugins_instance()
         or !($db_settings = $this->_plugins_instance->save_plugins_db_settings( $settings_arr, $this->instance_id() ))
         or !is_array( $db_settings ) )
            return false;

        $this->_db_settings = $db_settings;

        // invalidate cached data...
        $this->_db_details = false;

        return $this->_db_settings;
    }

    public function db_record_active()
    {
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !$this->_load_plugins_instance()
         or !($db_details = $this->get_db_details())
         or !$this->_plugins_instance->active_status( $db_details['status'] ) )
            return false;

        return $db_details;
    }

}
