<?php

namespace phs\plugins\notifications;

use \phs\PHS;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Error;
use \phs\system\core\views\PHS_View;

class PHS_Plugin_Notifications extends PHS_Plugin
{
    const ERR_TEMPLATE = 40000, ERR_RENDER = 40001;

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.0';
    }

    public function get_models()
    {
        return array();
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     * @return array
     */
    public function get_default_settings()
    {
        return array(
            'template' => $this->template_resource_from_file( 'notifications' ), // default template
            'display_channels' => array( 'warnings', 'errors', 'success' ),
        );
    }

    public function get_notifications_hook_args( $hook_args )
    {
        $this->reset_error();

        if( !($settings_arr = $this->get_plugin_db_settings())
         or empty( $settings_arr['template'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, self::_t( 'Couldn\'t load template from plugin settings.' ) );
            return false;
        }

        if( !($notifications_template = PHS_View::validate_template_resource( $settings_arr['template'] )) )
        {
            $this->set_error( self::ERR_TEMPLATE, self::_t( 'Failed validating notifications template file.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        $notifications_arr = PHS_Notifications::get_all_notifications();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_notifications_hook_args() );

        $hook_args['warnings'] = $notifications_arr['warnings'];
        $hook_args['errors'] = $notifications_arr['errors'];
        $hook_args['success'] = $notifications_arr['success'];

        $hook_args['display_channels'] = $settings_arr['display_channels'];
        $hook_args['template'] = $notifications_template;

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = array(
            'notifications' => $notifications_arr,
            'display_channels' => $hook_args['display_channels']
        );

        if( !($view_obj = PHS_View::init_view( $notifications_template, $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        if( !($hook_args['notifications_buffer'] = $view_obj->render()) )
        {
            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, self::_t( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        return $hook_args;
    }
}
