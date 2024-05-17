<?php

namespace phs\plugins\notifications;

use phs\PHS;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\views\PHS_View;
use phs\libraries\PHS_Notifications;

class PHS_Plugin_Notifications extends PHS_Plugin
{
    public const ERR_TEMPLATE = 40000, ERR_RENDER = 40001;

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            // default template
            'template' => [
                'display_name' => 'Captcha template',
                'display_hint' => 'What template should be used when displaying captcha image',
                'type'         => PHS_Params::T_ASIS,
                'input_type'   => self::INPUT_TYPE_TEMPLATE,
                'default'      => $this->template_resource_from_file('notifications'),
            ],
            'display_channels' => [
                'display_name' => 'Channels to be rendered',
                'type'         => PHS_Params::T_ARRAY,
                'extra_type'   => ['type' => PHS_Params::T_NOHTML, 'trim_before' => true],
                'input_type'   => self::INPUT_TYPE_ONE_OR_MORE,
                'default'      => ['success', 'warnings', 'errors'],
                'values_arr'   => ['success' => 'Success messages', 'warnings' => 'Warnings', 'errors' => 'Errors'],
            ],
        ];
    }

    public function get_notifications_hook_args($hook_args)
    {
        $this->reset_error();

        if (!($settings_arr = $this->get_plugin_settings())
         || empty($settings_arr['template'])) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Couldn\'t load template from plugin settings.'));

            return false;
        }

        if (!($notifications_template = PHS_View::validate_template_resource($settings_arr['template']))) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Failed validating notifications template file.'));

            $hook_args['hook_errors'] = self::validate_array($this->get_error(), PHS_Error::default_error_array());

            return $hook_args;
        }

        $notifications_arr = PHS_Notifications::get_all_notifications();

        $hook_args = self::validate_array_recursive($hook_args, PHS_Hooks::default_notifications_hook_args());

        $hook_args['warnings'] = $notifications_arr['warnings'];
        $hook_args['errors'] = $notifications_arr['errors'];
        $hook_args['success'] = $notifications_arr['success'];

        $hook_args['display_channels'] = $settings_arr['display_channels'];
        $hook_args['template'] = $notifications_template;

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = [
            'output_ajax_placeholders' => $hook_args['output_ajax_placeholders'],
            'ajax_placeholders_prefix' => $hook_args['ajax_placeholders_prefix'],
            'notifications'            => $notifications_arr,
            'display_channels'         => $hook_args['display_channels'],
        ];

        if (!($view_obj = PHS_View::init_view($notifications_template, $view_params))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            return false;
        }

        if (($hook_args['notifications_buffer'] = $view_obj->render()) === null) {
            if ($view_obj->has_error()) {
                $this->copy_error($view_obj);
            } else {
                $this->set_error(self::ERR_RENDER, $this->_pt('Error rendering template [%s].', $view_obj->get_template()));
            }

            return false;
        }

        if (empty($hook_args['notifications_buffer'])) {
            $hook_args['notifications_buffer'] = '';
        }

        return $hook_args;
    }
}
