<?php

namespace phs\plugins\captcha;

use \phs\PHS;
use \phs\PHS_Session;
use \phs\libraries\PHS_Error;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Params;
use \phs\system\core\views\PHS_View;

class PHS_Plugin_Captcha extends PHS_Plugin
{
    const ERR_TEMPLATE = 1, ERR_RENDER = 2, ERR_NOGD = 3, ERR_IMAGE = 4;

    const OUTPUT_JPG = 1, OUTPUT_GIF = 2, OUTPUT_PNG = 3;

    const IMG_QUALITY = 95;

    const FONT_DIR = 'fonts';

    const SESSION_VAR = 'phs_image_code';

    public function get_output_as_key_vals()
    {
        return array(
            self::OUTPUT_JPG => 'JPG',
            self::OUTPUT_GIF => 'GIF',
            self::OUTPUT_PNG => 'PNG',
        );
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
            // default template
            'template' => array(
                'display_name' => 'Captcha template',
                'display_hint' => 'What template should be used when displaying captcha image',
                'type' => PHS_Params::T_ASIS,
                'input_type' => self::INPUT_TYPE_TEMPLATE,
                'default' => $this->template_resource_from_file( 'captcha' ),
            ),
            'font' => array(
                'display_name' => 'Font used for captcha',
                'display_hint' => 'Make sure this file exists in fonts directory in plugin',
                'type' => PHS_Params::T_ASIS,
                'default' => 'default.ttf',
            ),
            'reference_code' => array(
                'display_name' => 'Captcha private key',
                'display_hint' => 'This acts as a private key when generating captcha codes. Once you setup plugin don\'t change this.',
                'type' => PHS_Params::T_NOHTML,
                'default' => '!!Captcha private KEY!!Change this default value to something else!!',
            ),
            'characters_count' => array(
                'display_name' => 'Captcha caracters',
                'type' => PHS_Params::T_INT,
                'default' => 5,
            ),
            'image_format' => array(
                'display_name' => 'Captcha output image format',
                'type' => PHS_Params::T_INT,
                'default' => self::OUTPUT_PNG,
                'extra_style' => 'min-width: 100px;',
                'values_arr' => $this->get_output_as_key_vals(),
            ),
            'default_width' => array(
                'display_name' => 'Default captcha width',
                'display_hint' => 'Width and height of captcha can be overridden in view',
                'type' => PHS_Params::T_INT,
                'default' => 200,
            ),
            'default_height' => array(
                'display_name' => 'Default captcha height',
                'display_hint' => 'Width and height of captcha can be overridden in view',
                'type' => PHS_Params::T_INT,
                'default' => 50,
            ),
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
        $this->reset_error();

        if( !($settings_arr = $this->get_db_settings()) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Couldn\'t load template from plugin settings.' ) );
            return false;
        }

        if( ($cimage_code = PHS_Session::_g( self::SESSION_VAR )) === null )
            $cimage_code = '';

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\captcha\\libraries\\PHS_Image_code';
        $library_params['init_params'] = array(
            'cnumbers' => $settings_arr['characters_count'],
            'param_code' => $cimage_code,
            'img_type' => $settings_arr['image_format'],
            'code_timeout' => 3600,
        );
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\captcha\libraries\PHS_Image_code $img_library */
        if( !($img_library = $this->load_library( 'phs_image_code', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading image captcha library.' ) );

            return false;
        }

        $code_valid = false;
        if( !empty( $code )
        and $img_library->check_input( $code ) )
        {
            $code_valid = true;
            if( $img_library->refresh_public_code() )
                $cimage_code = $img_library->get_public_code();
        } else
        {
            $img_library->regenerate_public_code();
            $cimage_code = $img_library->get_public_code();
        }

        PHS_Session::_s( self::SESSION_VAR, $cimage_code );

        return $code_valid;
    }

    public function captcha_regeneration()
    {
        $this->reset_error();

        if( !($settings_arr = $this->get_db_settings()) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Couldn\'t load template from plugin settings.' ) );
            return false;
        }

        if( ($cimage_code = PHS_Session::_g( self::SESSION_VAR )) === null )
            $cimage_code = '';

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\captcha\\libraries\\PHS_Image_code';
        $library_params['init_params'] = array(
            'cnumbers' => $settings_arr['characters_count'],
            'param_code' => $cimage_code,
            'img_type' => $settings_arr['image_format'],
        );
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\captcha\libraries\PHS_Image_code $img_library */
        if( !($img_library = $this->load_library( 'phs_image_code', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading image captcha library.' ) );

            return false;
        }

        $img_library->regenerate_public_code();
        $cimage_code = $img_library->get_public_code();

        PHS_Session::_s( self::SESSION_VAR, $cimage_code );

        return true;
    }

    public function get_captcha_check_hook_args( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_captcha_check_hook_args() );

        $hook_args['check_valid'] = true;
        if( empty( $hook_args['check_code'] )
         or !$this->check_captcha_code( $hook_args['check_code'] ) )
            $hook_args['check_valid'] = false;

        if( $this->has_error() )
            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

        return $hook_args;
    }

    public function captcha_regenerate_hook_args( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_captcha_regeneration_hook_args() );

        if( !$this->captcha_regeneration() )
        {
            if( $this->has_error() )
                $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );
        }

        return $hook_args;
    }

    public function get_captcha_display_hook_args( $hook_args )
    {
        $this->reset_error();

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_captcha_display_hook_args() );

        if( !($settings_arr = $this->get_db_settings())
         or empty( $settings_arr['template'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Couldn\'t load template from plugin settings.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( !($captcha_template = PHS_View::validate_template_resource( $settings_arr['template'] )) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed validating captcha template file.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        $hook_args['font'] = $settings_arr['font'];
        $hook_args['characters_count'] = $settings_arr['characters_count'];
        $hook_args['image_format'] = $settings_arr['image_format'];
        $hook_args['default_width'] = $settings_arr['default_width'];
        $hook_args['default_height'] = $settings_arr['default_height'];
        $hook_args['template'] = $captcha_template;

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = array(
            'hook_args' => $hook_args,
            'settings_arr' => $settings_arr,
        );

        if( !($view_obj = PHS_View::init_view( $captcha_template, $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( ($hook_args['captcha_buffer'] = $view_obj->render()) === false )
        {
            // Make sure buffer is a string
            $hook_args['captcha_buffer'] = '';

            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, $this->_pt( 'Error rendering template [%s].', $view_obj->get_template() ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( empty( $hook_args['captcha_buffer'] ) )
            $hook_args['captcha_buffer'] = '';

        return $hook_args;
    }
}
