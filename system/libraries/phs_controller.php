<?php
namespace phs\libraries;

use \phs\PHS;

abstract class PHS_Controller extends PHS_Signal_and_slot
{
    /**
     * @return array An array of strings which are the models used by this controller
     */
    abstract public function get_models();

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_CONTROLLER;
    }

    public function init_view( $template, $theme = false, $view_class = false, $plugin = false )
    {
        if( $plugin === false )
            $plugin = $this->instance_plugin_name();

        if( !($view_obj = PHS::load_view( $view_class, $plugin )) )
        {
            $this->copy_static_error();
            return false;
        }

        if( !$view_obj->set_controller( $this )
         or !$view_obj->set_theme( $theme )
         or !$view_obj->set_template( $template )
        )
        {
            $this->copy_error( $view_obj );
            return false;
        }

        return $view_obj;
    }

}
