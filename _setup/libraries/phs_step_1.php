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
        return @file_exists( PHS_SETUP_CONFIG_DIR.$this->get_config_file() );
    }

    public function load_current_configuration()
    {
        if( !@file_exists( PHS_SETUP_CONFIG_DIR.$this->get_config_file() ) )
            return false;

        return true;
    }

    protected function render_step_interface( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $phs_path = PHS_params::_p( 'phs_path', PHS_params::T_NOHTML );
        $phs_cookie_domain = PHS_params::_p( 'phs_cookie_domain', PHS_params::T_NOHTML );
        $phs_domain = PHS_params::_p( 'phs_domain', PHS_params::T_NOHTML );
        $phs_ssl_domain = PHS_params::_p( 'phs_ssl_domain', PHS_params::T_NOHTML );
        $phs_port = PHS_params::_p( 'phs_port', PHS_params::T_NOHTML );
        $phs_ssl_port = PHS_params::_p( 'phs_ssl_port', PHS_params::T_NOHTML );
        $phs_domain_path = PHS_params::_p( 'phs_domain_path', PHS_params::T_NOHTML );

        $do_submit = PHS_params::_p( 'do_submit', PHS_params::T_NOHTML );

        if( empty( $foobar ) )
        {
            $phs_path = PHS_SETUP_PHS_PATH;

            if( ($domain_settings = PHS_Setup_utils::_detect_setup_domain()) )
            {
                $phs_domain = $domain_settings['domain'];
                $phs_cookie_domain = $domain_settings['cookie_domain'];
                $phs_ssl_domain = $domain_settings['ssl_domain'];
                $phs_port = $domain_settings['port'];
                $phs_ssl_port = $domain_settings['ssl_port'];
                $phs_domain_path = $domain_settings['domain_path'];
            }
        }

        $data['phs_path'] = $phs_path;
        $data['phs_domain'] = $phs_domain;
        $data['phs_ssl_domain'] = $phs_ssl_domain;
        $data['phs_cookie_domain'] = $phs_cookie_domain;
        $data['phs_port'] = $phs_port;
        $data['phs_ssl_port'] = $phs_ssl_port;
        $data['phs_domain_path'] = $phs_domain_path;

        return PHS_Setup_layout::get_instance()->render( 'step1', $data );
    }
}
