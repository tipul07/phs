<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_Params;
use \phs\PHS_Db;

class PHS_Step_finish extends PHS_Step
{
    public function step_details()
    {
        return array(
            'title' => 'Framework Setup Completed',
            'description' => 'Congratulations you finished setting up framework...',
        );
    }

    public function get_config_file()
    {
        return 'main_finish.php';
    }

    public function step_config_passed()
    {
        return false;
    }

    public function load_current_configuration()
    {
        return true;
    }

    protected function render_step_interface( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        return PHS_Setup_layout::get_instance()->render( 'step_finish', $data );
    }
}
