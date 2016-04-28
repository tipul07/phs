<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Instantiable;

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

        if( !$accounts_model->can_manage_plugins( $current_user ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to list plugins.' ) );
            return self::default_action_result();
        }

        $pid = PHS_params::_gp( 'pid', PHS_params::T_ASIS );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

        if( !($instance_details = PHS_Instantiable::valid_instance_id( $pid ))
         or empty( $instance_details['instance_type'] )
         or $instance_details['instance_type'] != PHS_Instantiable::INSTANCE_TYPE_PLUGIN
         or !($this->_plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
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
        
        $modules_with_settings = array();
        if( ($plugin_models_arr = $this->_plugin_obj->get_models())
        and is_array( $plugin_models_arr ) )
        {
            foreach( $plugin_models_arr as $model_name )
            {
                $module_details = array();
                $module_details['instance'] = false;
                $module_details['settings'] = array();

                if( !($model_instance = PHS::load_model( $model_name, $this->_plugin_obj->instance_plugin_name() ))
                 or !($settings_arr = $model_instance->validate_settings_structure()) )
                    continue;

                $model_id = $model_instance->instance_id();

                $modules_with_settings[$model_id]['instance'] = $model_instance;
                $modules_with_settings[$model_id]['settings'] = $settings_arr;
            }
        }

        $data = array(
            'back_page' => $back_page,
            'form_data' => array(),
            'modules_with_settings' => $modules_with_settings,
            'settings_fields' => array(),
            'plugin_obj' => $this->_plugin_obj,
        );

        if( !($form_data = $this->extract_form_data( $data )) )
            $form_data = array();

        if( !empty( $form_data['selected_module'] )
        and !empty( $modules_with_settings[$form_data['selected_module']] )
        and !empty( $modules_with_settings[$form_data['selected_module']]['settings'] ) )
            $settings_fields = $modules_with_settings[$form_data['selected_module']]['settings'];

        else
        {
            $settings_fields = $this->_plugin_obj->validate_settings_structure();
            $form_data['selected_module'] = '';
        }

        $form_data['pid'] = $pid;

        $data['form_data'] = $form_data;
        $data['settings_fields'] = $settings_fields;

        return $this->quick_render_template( 'plugin_settings', $data );
    }

    private function extract_form_data( $data )
    {
        if( empty( $this->_plugin_obj ) )
            return false;

        $selected_module = PHS_params::_gp( 'selected_module', PHS_params::T_NOHTML );

        $form_data = array();
        $form_data['selected_module'] = $selected_module;

        return $form_data;
    }
}
