<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_Registry;

abstract class PHS_Step extends PHS_Registry
{
    /** @var bool|\phs\setup\libraries\PHS_Setup $setup_obj */
    private $setup_obj = false;

    abstract public function step_details();
    abstract public function get_config_file();
    abstract public function step_config_passed();
    abstract public function load_current_configuration();

    abstract protected function render_step_interface( $data = false );

    function __construct( $setup_inst = false )
    {
        parent::__construct();

        $this->setup_instance( $setup_inst );
    }

    public function setup_instance( $setup_inst = false )
    {
        if( $setup_inst === false )
            return $this->setup_obj;

        $this->setup_obj = $setup_inst;

        return $this->setup_obj;
    }

    public function render( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        if( !($step_interface_buf = $this->render_step_interface( $data )) )
            $step_interface_buf = '';

        $data['step_interface_buf'] = $step_interface_buf;
        $data['step_instance'] = $this;

        return PHS_Setup_layout::get_instance()->render( 'template_steps', $data );
    }
}
