<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Params;
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

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Plugin Settings' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

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

        $pid = PHS_Params::_gp( 'pid', PHS_Params::T_ASIS );
        $back_page = PHS_Params::_gp( 'back_page', PHS_Params::T_ASIS );

        if( $pid !== PHS_Instantiable::CORE_PLUGIN
        and (!($instance_details = PHS_Instantiable::valid_instance_id( $pid ))
                 or empty( $instance_details['instance_type'] )
                 or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN
                 or !($this->_plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] ))
            ) )
        {
            $action_result = self::default_action_result();

            $args = array(
                'unknown_plugin' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'plugins_list' ) );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( $pid == PHS_Instantiable::CORE_PLUGIN )
        {
            $this->_plugin_obj = false;
            $plugin_models_arr = PHS::get_core_models();
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

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $selected_module = PHS_Params::_gp( 'selected_module', PHS_Params::T_NOHTML );
        $do_submit = PHS_Params::_gp( 'do_submit', PHS_Params::T_NOHTML );

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

        $new_settings_arr = $this->_extract_settings_fields_from_submit( $settings_fields, $default_settings, $db_settings, $foobar, $form_data );

        if( !empty( $do_submit ) )
        {
            $new_settings_arr = self::validate_array( $new_settings_arr, $db_settings );

            $callback_params = PHS_Plugin::st_default_custom_save_params();
            $callback_params['plugin_obj'] = $this->_plugin_obj;
            $callback_params['module_instance'] = $module_instance;
            $callback_params['form_data'] = $form_data;

            $new_settings_arr = $this->_extract_custom_save_settings_fields_from_submit( $settings_fields, $callback_params, $new_settings_arr, $db_settings );

            // foreach( $settings_fields as $field_name => $field_details )
            // {
            //     if( !empty( $field_details['ignore_field_value'] )
            //      or empty( $field_details['custom_save'] )
            //      or !@is_callable( $field_details['custom_save'] ) )
            //         continue;
            //
            //     $callback_params = PHS_Plugin::st_default_custom_save_params();
            //     $callback_params['plugin_obj'] = $this->_plugin_obj;
            //     $callback_params['module_instance'] = $module_instance;
            //     $callback_params['field_name'] = $field_name;
            //     $callback_params['field_details'] = $field_details;
            //     $callback_params['field_value'] = (isset( $new_settings_arr[$field_name] )?$new_settings_arr[$field_name]:null);
            //     $callback_params['form_data'] = $form_data;
            //
            //     // make sure static error is reset
            //     self::st_reset_error();
            //
            //     if( ($save_result = @call_user_func( $field_details['custom_save'], $callback_params )) !== null )
            //     {
            //         if( is_array( $save_result )
            //         and !empty( $save_result['{new_settings_fields}'] ) and is_array( $save_result['{new_settings_fields}'] ) )
            //         {
            //             foreach( $db_settings as $s_key => $s_val )
            //             {
            //                 if( array_key_exists( $s_key, $save_result['{new_settings_fields}'] ) )
            //                     $new_settings_arr[$s_key] = $save_result['{new_settings_fields}'][$s_key];
            //             }
            //         } else
            //             $new_settings_arr[$field_name] = $save_result;
            //     }
            //
            //     elseif( self::st_has_error() )
            //         PHS_Notifications::add_error_notice( self::st_get_error_message() );
            //
            //     if( self::st_has_warnings() )
            //         PHS_Notifications::add_warning_notice( self::st_get_warnings() );
            // }

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

        $form_data['pid'] = $pid;

        $data['form_data'] = $form_data;
        $data['settings_fields'] = $settings_fields;
        $data['db_settings'] = $db_settings;
        $data['db_version'] = $db_version;
        $data['script_version'] = $script_version;

        return $this->quick_render_template( 'plugin_settings', $data );
    }

    private function _extract_custom_save_settings_fields_from_submit( $settings_fields, $init_callback_params, $new_settings_arr, $db_settings )
    {
        $default_custom_save_callback_result = PHS_Plugin::st_default_custom_save_callback_result();
        foreach( $settings_fields as $field_name => $field_details )
        {
            if( !empty( $field_details['ignore_field_value'] ) )
                continue;

            if( PHS_Plugin::settings_field_is_group( $field_details ) )
            {
                if( null !== ($group_settings = $this->_extract_custom_save_settings_fields_from_submit( $field_details['group_fields'], $init_callback_params, $new_settings_arr, $db_settings )) )
                    $new_settings_arr = self::merge_array_assoc( $new_settings_arr, $group_settings );

                continue;
            }

            if( empty( $field_details['custom_save'] )
             or !@is_callable( $field_details['custom_save'] ) )
                continue;

            $callback_params = $init_callback_params;
            $callback_params['field_name'] = $field_name;
            $callback_params['field_details'] = $field_details;
            $callback_params['field_value'] = (isset( $new_settings_arr[$field_name] )?$new_settings_arr[$field_name]:null);

            // make sure static error is reset
            self::st_reset_error();
            // make sure static warnings are reset
            self::st_reset_warnings();

            /**
             * When there is a field in instance settings which has a custom callback for saving data, it will return
             * either a scalar or an array to be merged with existing settings. Only keys which already exists as settings
             * can be provided
             */
            if( ($save_result = @call_user_func( $field_details['custom_save'], $callback_params )) !== null )
            {
                if( !is_array( $save_result ) )
                    $new_settings_arr[$field_name] = $save_result;

                else
                {
                    $save_result = self::merge_array_assoc( $save_result, $default_custom_save_callback_result );
                    if( !empty( $save_result['{new_settings_fields}'] ) and is_array( $save_result['{new_settings_fields}'] ) )
                    {
                        foreach( $db_settings as $s_key => $s_val )
                        {
                            if( array_key_exists( $s_key, $save_result['{new_settings_fields}'] ) )
                                $new_settings_arr[$s_key] = $save_result['{new_settings_fields}'][$s_key];
                        }
                    } else
                        $new_settings_arr[$field_name] = $save_result;
                }
            }

            elseif( self::st_has_error() )
                PHS_Notifications::add_error_notice( self::st_get_error_message() );

            if( self::st_has_warnings() )
                PHS_Notifications::add_warning_notice( self::st_get_warnings() );
        }

        return $new_settings_arr;
    }

    private function _extract_settings_fields_from_submit( $settings_fields, $default_settings, $db_settings, $is_post, &$form_data )
    {
        $new_settings_arr = array();
        foreach( $settings_fields as $field_name => $field_details )
        {
            if( !empty( $field_details['ignore_field_value'] ) )
                continue;

            if( PHS_Plugin::settings_field_is_group( $field_details ) )
            {
                if( null !== ($group_settings = $this->_extract_settings_fields_from_submit( $field_details['group_fields'], $default_settings, $db_settings, $is_post, $form_data )) )
                    $new_settings_arr = self::merge_array_assoc( $new_settings_arr, $group_settings );

                continue;
            }

            if( null === ($field_value = $this->_extract_field_value_from_submit( $field_name, $field_details, $default_settings, $db_settings, $is_post, $form_data )) )
                continue;

            $new_settings_arr[$field_name] = $field_value;
        }

        return $new_settings_arr;
    }

    private function _extract_field_value_from_submit( $field_name, $field_details, $default_settings, $db_settings, $is_post, &$form_data )
    {
        $field_value = null;

        if( empty( $field_details['editable'] ) )
        {
            // Check if default values have changed (upgrading plugin might change default value)
            if( isset( $default_settings[$field_name] ) and isset( $db_settings[$field_name] )
            and $default_settings[$field_name] !== $db_settings[$field_name] )
                $field_value = $default_settings[$field_name];

            // if we have something in database use that value
            elseif( isset( $db_settings[$field_name] ) )
                $field_value = $db_settings[$field_name];

            // This is a new non-editable value, save default value to db
            elseif( isset( $default_settings[$field_name] ) )
                $field_value = $default_settings[$field_name];

            return $field_value;
        }

        $form_data[$field_name] = PHS_Params::_gp( $field_name, $field_details['type'], $field_details['extra_type'] );

        if( !empty( $is_post )
        and (int)$field_details['type'] === PHS_Params::T_BOOL )
            $form_data[$field_name] = (empty( $form_data[$field_name] )?false:true);

        if( !empty( $field_details['custom_save'] ) )
            return null;

        switch( $field_details['input_type'] )
        {
            default:
            case PHS_Plugin::INPUT_TYPE_ONE_OR_MORE:
            case PHS_Plugin::INPUT_TYPE_ONE_OR_MORE_MULTISELECT:
                $field_value = $form_data[$field_name];
            break;

            case PHS_Plugin::INPUT_TYPE_TEMPLATE:
            break;

            case PHS_Plugin::INPUT_TYPE_KEY_VAL_ARRAY:
                if( empty( $default_settings[$field_name] ) )
                    $field_value = $form_data[$field_name];
                else
                    $field_value = self::validate_array_to_new_array( $form_data[$field_name], $default_settings[$field_name] );
            break;
        }

        return $field_value;
    }
}
