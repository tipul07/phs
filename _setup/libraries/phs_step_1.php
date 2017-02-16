<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_params;

class PHS_Step_1 extends PHS_Step
{
    public function step_details()
    {
        return array(
            'title' => 'Detect paths and domain',
            'description' => 'Try to detect paths in the system where framework runs and domain. If paths detection fails setup the path manually. '.
                             'Domain is detecting depending on web request done to run setup script.',
        );
    }

    public function get_config_file()
    {
        return 'main_paths_and_domain.php';
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

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $phs_root_dir = PHS_params::_p( 'phs_root_dir', PHS_params::T_NOHTML );

        if( empty( $foobar ) )
        {
            $phs_root_dir = PHS_SETUP_PHS_PATH;
        }

        $data['phs_root_dir'] = $phs_root_dir;

        return PHS_Setup_layout::get_instance()->render( 'step1', $data );
    }
}
