<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Roles;

class PHS_Action_Plugin_settings extends PHS_Action
{
    const ERR_PLUGIN = 1;

    /** @var bool|\phs\libraries\PHS_Plugin $_plugin_obj */
    private $_plugin_obj = false;
    
    public function execute()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to list plugins.' ) );
            return self::default_action_result();
        }

        $pid = PHS_params::_gp( 'pid', PHS_params::T_ASIS );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

        if( $pid != PHS_Instantiable::CORE_PLUGIN
        and (!($instance_details = PHS_Instantiable::valid_instance_id( $pid ))
                 or empty( $instance_details['instance_type'] )
                 or $instance_details['instance_type'] != PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                 or !($this->_plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] ))
            ) )
        {
            $action_result = self::default_action_result();

            $args = array(
                'unknown_plugin' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'plugins_list' ) );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( $pid == PHS_Instantiable::CORE_PLUGIN )
        {
            $this->_plugin_obj = false;
            $plugin_models_arr = PHS::get_core_modules();
        } else
        {
            $plugin_models_arr = $this->_plugin_obj->get_models();
        }

        $modules_with_settings = array();
        if( !empty( $plugin_models_arr )
        and is_array( $plugin_models_arr ) )
        {
            foreach( $plugin_models_arr as $model_name )
            {
                $module_details = array();
                $module_details['instance'] = false;
                $module_details['settings'] = array();

                if( !($model_instance = PHS::load_model( $model_name, ($this->_plugin_obj?$this->_plugin_obj->instance_plugin_name():false) ))
                 or !($settings_arr = $model_instance->validate_settings_structure()) )
                    continue;

                $model_id = $model_instance->instance_id();

                if( !($model_db_details = $model_instance->get_db_details()) )
                    $model_db_details = array();

                $modules_with_settings[$model_id]['instance'] = $model_instance;
                $modules_with_settings[$model_id]['settings'] = $settings_arr;
                $modules_with_settings[$model_id]['default_settings'] = $model_instance->get_default_settings();
                $modules_with_settings[$model_id]['db_settings'] = $model_instance->get_db_settings();
                $modules_with_settings[$model_id]['db_version'] = (!empty( $model_db_details['version'] )?$model_db_details['version']:'0.0.0');
                $modules_with_settings[$model_id]['script_version'] = $model_instance->get_model_version();
            }
        }

        $data = array(
            'back_page' => $back_page,
            'form_data' => array(),
            'modules_with_settings' => $modules_with_settings,
            'settings_fields' => array(),
            'db_settings' => array(),
            'plugin_obj' => $this->_plugin_obj,
        );

        $selected_module = PHS_params::_gp( 'selected_module', PHS_params::T_NOHTML );
        $do_submit = PHS_params::_gp( 'do_submit', PHS_params::T_NOHTML );

        $form_data = array();
        $form_data['selected_module'] = $selected_module;
        $form_data['do_submit'] = $do_submit;

        /** @var \phs\libraries\PHS_Has_db_settings $module_instance */
        $module_instance = false;
        $settings_fields = array();
        $default_settings = array();
        $db_settings = array();
        $db_version = '0.0.0';
        $script_version = '0.0.0';
        if( !empty( $form_data['selected_module'] )
        and !empty( $modules_with_settings[$form_data['selected_module']] ) )
        {
            if( !empty( $modules_with_settings[$form_data['selected_module']]['instance'] ) )
                $module_instance = $modules_with_settings[$form_data['selected_module']]['instance'];
            if( !empty( $modules_with_settings[$form_data['selected_module']]['settings'] ) )
                $settings_fields = $modules_with_settings[$form_data['selected_module']]['settings'];
            if( !empty( $modules_with_settings[$form_data['selected_module']]['default_settings'] ) )
                $default_settings = $modules_with_settings[$form_data['selected_module']]['default_settings'];
            if( !empty( $modules_with_settings[$form_data['selected_module']]['db_settings'] ) )
                $db_settings = $modules_with_settings[$form_data['selected_module']]['db_settings'];
            if( !empty( $modules_with_settings[$form_data['selected_module']]['db_version'] ) )
                $db_version = $modules_with_settings[$form_data['selected_module']]['db_version'];
            if( !empty( $modules_with_settings[$form_data['selected_module']]['script_version'] ) )
                $script_version = $modules_with_settings[$form_data['selected_module']]['script_version'];
        } else
        {
            $form_data['selected_module'] = '';
            $module_instance = $this->_plugin_obj;

            if( $this->_plugin_obj )
            {
                if( !($plugin_db_details = $this->_plugin_obj->get_db_details()) )
                    $plugin_db_details = array();

                $settings_fields = $this->_plugin_obj->validate_settings_structure();
                $default_settings = $this->_plugin_obj->get_default_settings();
                $db_settings = $this->_plugin_obj->get_db_settings();
                $db_version = (!empty( $plugin_db_details['version'] )?$plugin_db_details['version']:'0.0.0');
                $script_version = $this->_plugin_obj->get_plugin_version();
            }
        }

        $new_settings_arr = array();
        foreach( $settings_fields as $field_name => $field_details )
        {
            if( !empty( $field_details['custom_save'] ) )
                continue;

            if( empty( $field_details['editable'] ) )
            {
                if( isset( $db_settings[$field_name] ) )
                    $new_settings_arr[$field_name] = $db_settings[$field_name];
                elseif( isset( $default_settings[$field_name] ) )
                    $new_settings_arr[$field_name] = $default_settings[$field_name];
                continue;
            }

            $form_data[$field_name] = PHS_params::_gp( $field_name, $field_details['type'], $field_details['extra_type'] );

            switch( $field_details['input_type'] )
            {
                default:
                    $new_settings_arr[$field_name] = $form_data[$field_name];
                break;

                case PHS_Plugin::INPUT_TYPE_TEMPLATE:
                break;

                case PHS_Plugin::INPUT_TYPE_ONE_OR_MORE:
                    $new_settings_arr[$field_name] = $form_data[$field_name];
                break;

                case PHS_Plugin::INPUT_TYPE_KEY_VAL_ARRAY:
                    if( empty( $default_settings[$field_name] ) )
                        $new_settings_arr[$field_name] = $form_data[$field_name];
                    else
                        $new_settings_arr[$field_name] = self::validate_array_to_new_array( $form_data[$field_name], $default_settings[$field_name] );
                break;
            }
        }

        if( !empty( $do_submit ) )
        {
            $new_settings_arr = self::validate_array( $new_settings_arr, $db_settings );

            foreach( $settings_fields as $field_name => $field_details )
            {
                if( empty( $field_details['custom_save'] )
                 or !@is_callable( $field_details['custom_save'] ) )
                    continue;

                $callback_params = PHS_Plugin::st_default_custom_save_params();
                $callback_params['plugin_obj'] = $this->_plugin_obj;
                $callback_params['module_instance'] = $module_instance;
                $callback_params['field_name'] = $field_name;
                $callback_params['field_details'] = $field_details;
                $callback_params['field_value'] = (isset( $new_settings_arr[$field_name] )?$new_settings_arr[$field_name]:null);
                $callback_params['form_data'] = $form_data;
                
                // make sure static error is reset
                self::st_reset_error();

                if( ($save_value = @call_user_func( $field_details['custom_save'], $callback_params )) !== null )
                    $new_settings_arr[$field_name] = $save_value;

                elseif( self::st_has_error() )
                    PHS_Notifications::add_error_notice( self::st_get_error_message() );
            }

            if( !PHS_Notifications::have_notifications_errors() )
            {
                if( ($new_db_settings = $module_instance->save_db_settings( $new_settings_arr )) )
                {
                    $db_settings = $new_db_settings;
                    PHS_Notifications::add_success_notice( $this->_pt( 'Settings saved in database.' ) );
                } else
                {
                    if( $module_instance->has_error() )
                        PHS_Notifications::add_error_notice( $module_instance->get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( $this->_pt( 'Error saving settings in database. Please try again.' ) );
                }
            }
        }

        var_dump( $db_version );
        var_dump( $script_version );

        $form_data['pid'] = $pid;

        $data['form_data'] = $form_data;
        $data['settings_fields'] = $settings_fields;
        $data['db_settings'] = $db_settings;
        $data['db_version'] = $db_version;
        $data['script_version'] = $script_version;

        return $this->quick_render_template( 'plugin_settings', $data );
    }
}
