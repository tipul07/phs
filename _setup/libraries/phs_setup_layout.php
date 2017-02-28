<?php

namespace phs\setup\libraries;

class PHS_Setup_layout extends PHS_Setup_view
{
    /** @var bool|\phs\setup\libraries\PHS_Setup_layout $layout_instance_obj */
    private static $layout_instance_obj = false;

    private $common_data = array();

    function __construct( $templates_dir = false )
    {
        parent::__construct();

        if( @class_exists( '\\phs\\setup\\libraries\\PHS_Setup', false ) )
        {
            $this->common_data = array(
                'phs_setup_obj' => PHS_Setup::get_instance(),
            );
        } else
        {
            $this->common_data = array(
                'phs_setup_obj' => false,
            );
        }
    }

    public function render( $template, $data = false, $include_main_template = false )
    {
        $this->set_context( $this->common_data );

        if( !($template_buf = $this->render_view( $template, $data )) )
            $template_buf = '';

        if( empty( $include_main_template ) )
            return $template_buf;

        $main_template_data = array(
            'page_content' => $template_buf,
        );

        $this->set_context( $main_template_data );

        if( !($page_buf = $this->render_view( 'template_main' )) )
            $page_buf = '';

        return $page_buf;
    }

    public function get_common_data( $key = false )
    {
        if( $key === false )
            return $this->common_data;

        if( array_key_exists( $key, $this->common_data ) )
            return $this->common_data[$key];

        return null;
    }

    public function set_full_common_data( $arr, $merge = false )
    {
        if( !is_array( $arr ) )
            return false;

        if( empty( $merge ) )
            $this->common_data = $arr;
        else
            $this->common_data = PHS_Setup_utils::merge_array_assoc( $this->common_data, $arr );

        return true;
    }

    public function set_common_data( $key, $val = null )
    {
        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                $this->common_data[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key ) )
            return false;

        $this->common_data[$key] = $val;

        return true;
    }

    public static function get_instance()
    {
        if( self::$layout_instance_obj !== false )
            return self::$layout_instance_obj;

        self::$layout_instance_obj = new PHS_Setup_layout();

        return self::$layout_instance_obj;
    }
}
