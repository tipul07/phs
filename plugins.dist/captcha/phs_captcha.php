<?php
namespace phs\plugins\captcha;

use phs\PHS;
use phs\PHS_Session;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\views\PHS_View;
use phs\plugins\captcha\libraries\PHS_Image_code;

class PHS_Plugin_Captcha extends PHS_Plugin
{
    public const ERR_TEMPLATE = 1, ERR_RENDER = 2, ERR_NOGD = 3, ERR_IMAGE = 4;

    public const OUTPUT_JPG = 1, OUTPUT_GIF = 2, OUTPUT_PNG = 3;

    public const IMG_QUALITY = 95;

    public const FONT_DIR = 'fonts';

    public const SESSION_VAR = 'phs_image_code';

    public function get_output_as_key_vals() : array
    {
        return [
            self::OUTPUT_JPG => 'JPG',
            self::OUTPUT_GIF => 'GIF',
            self::OUTPUT_PNG => 'PNG',
        ];
    }

    /**
     * @inheritdoc
     */
    public function get_settings_keys_to_obfuscate() : array
    {
        return ['reference_code', ];
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            // default template
            'template' => [
                'display_name' => 'Captcha template',
                'display_hint' => 'What template should be used when displaying captcha image',
                'type'         => PHS_Params::T_ASIS,
                'input_type'   => self::INPUT_TYPE_TEMPLATE,
                'default'      => $this->template_resource_from_file('captcha'),
            ],
            'font' => [
                'display_name' => 'Font used for captcha',
                'display_hint' => 'Make sure this file exists in fonts directory in plugin',
                'type'         => PHS_Params::T_ASIS,
                'default'      => 'default.ttf',
            ],
            'reference_code' => [
                'display_name' => 'Captcha private key',
                'display_hint' => 'This acts as a private key when generating captcha codes. Once you setup plugin don\'t change this.',
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '!!Captcha private KEY!!Change this default value to something else!!',
            ],
            'characters_count' => [
                'display_name' => 'Captcha caracters',
                'type'         => PHS_Params::T_INT,
                'default'      => 5,
            ],
            'image_format' => [
                'display_name' => 'Captcha output image format',
                'type'         => PHS_Params::T_INT,
                'default'      => self::OUTPUT_PNG,
                'extra_style'  => 'min-width: 100px;',
                'values_arr'   => $this->get_output_as_key_vals(),
            ],
            'default_width' => [
                'display_name' => 'Default captcha width',
                'display_hint' => 'Width and height of captcha can be overridden in view',
                'type'         => PHS_Params::T_INT,
                'default'      => 200,
            ],
            'default_height' => [
                'display_name' => 'Default captcha height',
                'display_hint' => 'Width and height of captcha can be overridden in view',
                'type'         => PHS_Params::T_INT,
                'default'      => 50,
            ],
        ];
    }

    public function get_font_full_path($font) : ?string
    {
        $font = make_sure_is_filename($font);
        if (empty($font)
         || !($dir_path = $this->instance_plugin_path())
         || !@is_dir($dir_path.self::FONT_DIR)
         || !@file_exists($dir_path.self::FONT_DIR.'/'.$font)) {
            return null;
        }

        return $dir_path.self::FONT_DIR.'/'.$font;
    }

    public function check_captcha_code(string $code) : bool
    {
        if (!($img_library = $this->load_image_library(true))) {
            return false;
        }

        $code_valid = false;
        if (!empty($code)
            && $img_library->check_input($code)) {
            $code_valid = true;
            $img_library->refresh_public_code();
        } else {
            $img_library->regenerate_public_code();
        }

        PHS_Session::_s(self::SESSION_VAR, $img_library->get_public_code());

        return $code_valid;
    }

    public function captcha_regeneration() : bool
    {
        if (!($img_library = $this->load_image_library())) {
            return false;
        }

        $img_library->regenerate_public_code();

        PHS_Session::_s(self::SESSION_VAR, $img_library->get_public_code());

        return true;
    }

    public function generate_or_refresh_public_code() : bool
    {
        if (!($img_library = $this->load_image_library(true))) {
            return false;
        }

        if (!$img_library->refresh_public_code()) {
            return $this->captcha_regeneration();
        }

        PHS_Session::_s(self::SESSION_VAR, $img_library->get_public_code());

        return true;
    }

    public function load_image_library(bool $force_code_check = false) : ?PHS_Image_code
    {
        static $img_library = null;

        if ($img_library !== null) {
            if ($force_code_check
                && ($cimage_code = PHS_Session::_g(self::SESSION_VAR))) {
                $img_library->set_public_code($cimage_code);
            }

            return $img_library;
        }

        $this->reset_error();

        if (!($settings_arr = $this->get_plugin_settings())) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Couldn\'t load template from plugin settings.'));

            return null;
        }

        if (!($cimage_code = PHS_Session::_g(self::SESSION_VAR))) {
            $cimage_code = '';
        }

        $library_params = [];
        $library_params['full_class_name'] = PHS_Image_code::class;
        $library_params['init_params'] = [
            'cnumbers'       => $settings_arr['characters_count'],
            'public_code'    => $cimage_code,
            'img_type'       => $settings_arr['image_format'],
            'reference_code' => $settings_arr['reference_code'] ?? '',
            'code_timeout'   => 1800,
        ];
        $library_params['as_singleton'] = true;

        /** @var PHS_Image_code $img_library */
        if (!($img_library = $this->load_library('phs_image_code', $library_params))) {
            $this->set_error_if_not_set(self::ERR_LIBRARY, $this->_pt('Error loading image captcha library.'));

            return null;
        }

        return $img_library;
    }

    public function get_captcha_check_hook_args($hook_args) : array
    {
        $this->reset_error();

        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_captcha_check_hook_args());

        $hook_args['check_valid'] = true;
        if (empty($hook_args['check_code'])
            || !is_string($hook_args['check_code'])
            || !$this->check_captcha_code($hook_args['check_code'])) {
            $hook_args['check_valid'] = false;
        }

        if ($this->has_error()) {
            $hook_args['hook_errors'] = self::validate_array($this->get_error(), PHS_Error::default_error_array());
        }

        return $hook_args;
    }

    public function captcha_regenerate_hook_args($hook_args)
    {
        $this->reset_error();

        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_captcha_regeneration_hook_args());

        if (!$this->captcha_regeneration()) {
            if ($this->has_error()) {
                $hook_args['hook_errors'] = self::validate_array($this->get_error(), PHS_Error::default_error_array());
            }
        }

        return $hook_args;
    }

    public function get_captcha_display_hook_args($hook_args) : array
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive($hook_args, PHS_Hooks::default_captcha_display_hook_args());

        if (!($settings_arr = $this->get_plugin_settings())
         || empty($settings_arr['template'])) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Couldn\'t load template from plugin settings.'));

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if (!($captcha_template = PHS_View::validate_template_resource($settings_arr['template']))) {
            $this->set_error(self::ERR_TEMPLATE, $this->_pt('Failed validating captcha template file.'));

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $hook_args['font'] = $settings_arr['font'];
        $hook_args['characters_count'] = $settings_arr['characters_count'];
        $hook_args['image_format'] = $settings_arr['image_format'];
        $hook_args['default_width'] = $settings_arr['default_width'];
        $hook_args['default_height'] = $settings_arr['default_height'];
        $hook_args['template'] = $captcha_template;

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = [
            'hook_args'    => $hook_args,
            'settings_arr' => $settings_arr,
        ];

        if (!($view_obj = PHS_View::init_view($captcha_template, $view_params))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        if (null === ($hook_args['captcha_buffer'] = $view_obj->render())) {
            // Make sure buffer is a string
            $hook_args['captcha_buffer'] = '';

            if ($view_obj->has_error()) {
                $this->copy_error($view_obj, self::ERR_RENDER);
            } else {
                $this->set_error(self::ERR_RENDER, $this->_pt('Error rendering template [%s].', $view_obj->get_template()));
            }

            $hook_args['hook_errors'] = $this->get_error();

            return $hook_args;
        }

        $hook_args['captcha_buffer'] ??= '';

        return $hook_args;
    }
}
