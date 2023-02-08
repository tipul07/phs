<?php
namespace phs\plugins\cookie_notice;

use phs\PHS_Session;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\views\PHS_View;

class PHS_Plugin_Cookie_notice extends PHS_Plugin
{
    public const ERR_TEMPLATE = 40000, ERR_RENDER = 40001;

    public const COOKIE_NAME = '__phs_c_p_a', COOKIE_EXPIRE_SECS = 31536000; // 1 year

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            // default template
            'template' => [
                'display_name' => 'Notice template',
                'display_hint' => 'What template should be used when displaying fact that site uses cookies',
                'type'         => PHS_Params::T_ASIS,
                'input_type'   => self::INPUT_TYPE_TEMPLATE,
                'default'      => $this->template_resource_from_file('cookie_notice'),
            ],
            'rejection_url' => [
                'display_name' => 'Rejection URL',
                'display_hint' => 'In case end-user doesn\'t accept cookies policy, system will redirect to this URL (leave empty to hide the link in template)',
                'type'         => PHS_Params::T_URL,
                'default'      => 'https://en.wikipedia.org/wiki/Directive_on_Privacy_and_Electronic_Communications',
            ],
            'read_more_url' => [
                'display_name' => 'Read mode URL',
                'display_hint' => 'End-user will be presented option to read more about cookies policy. System will redirect to this URL. (leave empty to hide the link in template)',
                'type'         => PHS_Params::T_URL,
                'default'      => 'https://en.wikipedia.org/wiki/Directive_on_Privacy_and_Electronic_Communications',
            ],
        ];
    }

    public function agreed_cookies()
    {
        return (bool)PHS_Session::get_cookie(self::COOKIE_NAME);
    }

    public function accept_cookie_agreement()
    {
        return PHS_Session::set_cookie(self::COOKIE_NAME, 1, ['expire_secs' => self::COOKIE_EXPIRE_SECS]);
    }

    public function get_cookie_notice_hook_args($hook_args)
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive($hook_args, PHS_Hooks::default_buffer_hook_args());

        if ($this->agreed_cookies()) {
            $this->accept_cookie_agreement();

            return $hook_args;
        }

        if (!($settings_arr = $this->get_db_settings())
         || empty($settings_arr['template'])) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Couldn\'t load template from plugin settings.'));

            return false;
        }

        if (!($notifications_template = PHS_View::validate_template_resource($settings_arr['template']))) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Failed validating notifications template file.'));

            $hook_args['hook_errors'] = self::validate_array($this->get_error(), PHS_Error::default_error_array());

            return $hook_args;
        }

        $hook_args['template'] = $notifications_template;

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = [
            'rejection_url' => (!empty($settings_arr['rejection_url']) ? $settings_arr['rejection_url'] : ''),
            'read_more_url' => (!empty($settings_arr['read_more_url']) ? $settings_arr['read_more_url'] : ''),
            'cookie_name'   => self::COOKIE_NAME,
            'plugin_obj'    => $this,
        ];

        if (!($view_obj = PHS_View::init_view($notifications_template, $view_params))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            return false;
        }

        if (($hook_args['buffer'] = $view_obj->render()) === false) {
            if ($view_obj->has_error()) {
                $this->copy_error($view_obj);
            } else {
                $this->set_error(self::ERR_RENDER, $this->_pt('Error rendering template [%s].', $view_obj->get_template()));
            }

            return false;
        }

        if (empty($hook_args['buffer'])) {
            $hook_args['buffer'] = '';
        }

        return $hook_args;
    }
}
