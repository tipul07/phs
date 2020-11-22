<?php

namespace phs\plugins\captcha\actions;

use \phs\PHS_Session;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;

class PHS_Action_Index extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        /** @var \phs\plugins\captcha\PHS_Plugin_Captcha $plugin_instance */
        if( !($plugin_instance = $this->get_plugin_instance())
         or !$plugin_instance->plugin_active()
         or !($plugin_settings = $plugin_instance->get_db_settings()) )
        {
            echo $this->_pt( 'Couldn\'t obtain plugin settings.' );
            exit;
        }

        $params = array();
        if( ($vars_check = $plugin_instance->vars_to_indexes()) )
        {
            foreach( $vars_check as $var => $index )
            {
                $var_val = PHS_Params::_g( $var, PHS_Params::T_INT );

                $params[$index] = $var_val;
            }
        }

        $params = self::validate_array( $plugin_settings, $params );

        if( empty( $params['default_width'] ) or $params['default_width'] < 100 )
            $params['default_width'] = $plugin_settings['default_width'];
        if( empty( $params['default_height'] ) or $params['default_height'] < 30 )
            $params['default_height'] = $plugin_settings['default_height'];

        if( !@function_exists( 'imagecreatetruecolor' ) )
        {
            echo $this->_pt( 'Function imagecreatetruecolor doesn\'t exist. Maybe gd library is not installed or doesn\'t support this function.' );
            exit;
        }

        if( empty( $params['font'] )
         or !($font_file = $plugin_instance->get_font_full_path( $params['font'] )) )
        {
            echo $this->_pt( 'Font couldn\'t be found.' );
            exit;
        }

        if( ($cimage_code = PHS_Session::_g( $plugin_instance::SESSION_VAR )) === null )
            $cimage_code = '';

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\captcha\\libraries\\PHS_Image_code';
        $library_params['init_params'] = array(
            'cnumbers' => $params['characters_count'],
            'param_code' => $cimage_code,
            'img_type' => $params['image_format'],
        );
        $library_params['as_singleton'] = false;

        /** @var \phs\plugins\captcha\libraries\PHS_Image_code $img_library */
        if( !($img_library = $plugin_instance->load_library( 'phs_image_code', $library_params )) )
        {
            if( $plugin_instance->has_error() )
                echo $plugin_instance->get_error_message();
            else
                echo $this->_pt( 'Error loading image captcha library.' );

            exit;
        }

        if( empty( $cimage_code ) )
        {
            $cimage_code = $img_library->get_public_code();
            PHS_Session::_s( $plugin_instance::SESSION_VAR, $cimage_code );
        }

        $img_library->generate_image( $params['default_width'], $params['default_height'], $font_file );
        exit;
    }
}
