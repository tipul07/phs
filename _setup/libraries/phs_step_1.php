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
        $config_file = PHS_SETUP_CONFIG_DIR.$this->get_config_file();
        if( !@file_exists( $config_file ) )
            return false;

        ob_start();
        include( $config_file );
        ob_end_clean();

        $this->config_file_loaded( true );

        return true;
    }

    protected function render_step_interface( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $phs_path = PHS_params::_p( 'phs_path', PHS_params::T_NOHTML );
        $phs_domain = PHS_params::_p( 'phs_domain', PHS_params::T_NOHTML );
        $phs_ssl_domain = PHS_params::_p( 'phs_ssl_domain', PHS_params::T_NOHTML );
        $phs_cookie_domain = PHS_params::_p( 'phs_cookie_domain', PHS_params::T_NOHTML );
        $phs_port = PHS_params::_p( 'phs_port', PHS_params::T_NOHTML );
        $phs_ssl_port = PHS_params::_p( 'phs_ssl_port', PHS_params::T_NOHTML );
        $phs_domain_path = PHS_params::_p( 'phs_domain_path', PHS_params::T_NOHTML );

        $do_submit = PHS_params::_p( 'do_submit', PHS_params::T_NOHTML );

        if( !empty( $do_submit ) )
        {
            if( empty( $phs_path ) )
                $this->add_error_msg( 'Please provide PHS Root path.' );

            if( empty( $phs_domain ) )
                $this->add_error_msg( 'Please provide PHS Domain.' );

            if( empty( $phs_ssl_domain ) )
                $phs_ssl_domain = $phs_domain;

            if( empty( $phs_cookie_domain ) )
                $phs_cookie_domain = $phs_domain;

            if( empty( $phs_port ) )
                $phs_port = '';

            if( empty( $phs_ssl_port ) )
                $phs_ssl_port = '';

            if( empty( $phs_domain_path ) )
                $phs_domain_path = '/';

            $defines_arr = array(
                'PHS_PATH' => $phs_path,
                'PHS_DEFAULT_DOMAIN' => $phs_domain,
                'PHS_DEFAULT_SSL_DOMAIN' => $phs_ssl_domain,
                'PHS_DEFAULT_COOKIE_DOMAIN' => $phs_cookie_domain,
                'PHS_DEFAULT_PORT' => $phs_port,
                'PHS_DEFAULT_SSL_PORT' => $phs_ssl_port,
                'PHS_DEFAULT_DOMAIN_PATH' => $phs_domain_path,
            );

            $config_params = array(
                'defines' => $defines_arr,
            );

            if( $this->save_step_config_file( $config_params ) )
                $this->add_success_msg( 'Config file saved with success. Redirecting to next step...' );

            else
            {
                if( $this->has_error() )
                    $this->add_error_msg( $this->get_error_message() );
                else
                    $this->add_error_msg( 'Error saving config file for current step.' );
            }
        }

        if( empty( $foobar ) )
        {
            if( $this->config_file_loaded() )
            {
                $this->add_success_msg( 'Existing config file loaded...' );

                $phs_path = PHS_PATH;
                $phs_domain = PHS_DEFAULT_DOMAIN;
                $phs_ssl_domain = PHS_DEFAULT_SSL_DOMAIN;
                $phs_cookie_domain = PHS_DEFAULT_COOKIE_DOMAIN;
                $phs_port = PHS_DEFAULT_PORT;
                $phs_ssl_port = PHS_DEFAULT_SSL_PORT;
                $phs_domain_path = PHS_DEFAULT_DOMAIN_PATH;
            } else
            {
                $phs_path = PHS_SETUP_PHS_PATH;

                if( ($domain_settings = PHS_Setup_utils::_detect_setup_domain()) )
                {
                    $phs_domain = $domain_settings['domain'];
                    $phs_ssl_domain = $domain_settings['ssl_domain'];
                    $phs_cookie_domain = $domain_settings['cookie_domain'];
                    $phs_port = $domain_settings['port'];
                    $phs_ssl_port = $domain_settings['ssl_port'];
                    $phs_domain_path = $domain_settings['domain_path'];
                }
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
