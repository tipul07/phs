<?php

namespace phs\plugins\captcha;

use phs\libraries\PHS_Hooks;
use \phs\PHS;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Notifications;
use \phs\system\core\views\PHS_View;

class PHS_Plugin_Captcha extends PHS_Plugin
{
    const ERR_TEMPLATE = 40000, ERR_RENDER = 40001, ERR_NOGD = 40002, ERR_IMAGE = 40003;

    const OUTPUT_JPG = 1, OUTPUT_GIF = 2, OUTPUT_PNG = 3;

    const IMG_QUALITY = 95;

    const FONT_DIR = 'fonts';

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
            'template' => array(
                'file' => 'captcha',
                'extra_paths' => array(
                    PHS::relative_path( $this->instance_plugin_templates_path() ) => PHS::relative_url( $this->instance_plugin_templates_www() ),
                ),
            ), // default template
            'font' => 'default.ttf',
            'characters_count' => 5,
            'image_format' => self::OUTPUT_PNG,
            'default_width' => 200,
            'default_height' => 50,
        );
    }

    public function indexes_to_vars()
    {
        return array( 'default_width' => 'w', 'default_height' => 'h' );
    }

    public function vars_to_indexes()
    {
        $return_arr = array();
        foreach( $this->indexes_to_vars() as $index => $var )
        {
            $return_arr[$var] = $index;
        }

        return $return_arr;
    }

    public function get_font_full_path( $font )
    {
        $font = make_sure_is_filename( $font );
        if( empty( $font )
         or !($dir_path = $this->instance_plugin_path())
         or !@is_dir( $dir_path.self::FONT_DIR )
         or !@file_exists( $dir_path.self::FONT_DIR.'/'.$font ) )
            return false;

        return $dir_path.self::FONT_DIR.'/'.$font;
    }

    public function check_captcha_code( $code )
    {
        return true;
    }

    public function get_captcha_hook_args( $hook_args )
    {
        $this->reset_error();

        if( !($settings_arr = $this->get_plugin_db_settings())
         or empty( $settings_arr['template'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, self::_t( 'Couldn\'t load template from plugin settings.' ) );
            return false;
        }

        $extra_paths = array();
        if( !empty( $settings_arr['template']['extra_paths'] ) and is_array( $settings_arr['template']['extra_paths'] ) )
        {
            foreach( $settings_arr['template']['extra_paths'] as $dir_path => $dir_www )
            {
                $extra_paths[PHS::from_relative_path( $dir_path )] = PHS::from_relative_url( $dir_www );
            }
        }

        $settings_arr['template']['extra_paths'] = $extra_paths;

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_captcha_hook_args() );

        $hook_args['font'] = $settings_arr['font'];
        $hook_args['characters_count'] = $settings_arr['characters_count'];
        $hook_args['image_format'] = $settings_arr['image_format'];
        $hook_args['default_width'] = $settings_arr['default_width'];
        $hook_args['default_height'] = $settings_arr['default_height'];
        $hook_args['template'] = $settings_arr['template'];

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = array(
            'hook_args' => $hook_args,
            'settings_arr' => $settings_arr,
        );

        if( !($view_obj = PHS_View::init_view( $settings_arr['template'], $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        if( !($hook_args['captcha_buffer'] = $view_obj->render()) )
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
