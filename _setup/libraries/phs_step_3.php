<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_Params;
use \phs\PHS_Db;

class PHS_Step_3 extends PHS_Step
{
    const ERR_CREATE_CONNECTION = 1, ERR_DB_CONNECTION = 2;

    public function step_details()
    {
        return array(
            'title' => 'Site Setup',
            'description' => 'Settings related to site...',
        );
    }

    public function get_config_file()
    {
        return 'site_setup.php';
    }

    public function step_config_passed()
    {
        return @file_exists( PHS_SETUP_CONFIG_DIR.$this->get_config_file() );
    }

    public function load_current_configuration()
    {
        if( $this->config_file_loaded() )
            return true;

        $config_file = PHS_SETUP_CONFIG_DIR.$this->get_config_file();
        if( !@file_exists( $config_file ) )
            return false;

        ob_start();
        include( $config_file );
        ob_end_clean();

        if( defined( 'PHS_SITE_TIMEZONE' ) )
            @date_default_timezone_set( constant( 'PHS_SITE_TIMEZONE' ) );

        $this->config_file_loaded( true );

        return true;
    }

    protected function render_step_interface( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        if( !($phs_timezone_continent = PHS_Params::_p( 'phs_timezone_continent', PHS_Params::T_NOHTML )) )
            $phs_timezone_continent = '';
        if( !($phs_timezone_city = PHS_Params::_p( 'phs_timezone_city', PHS_Params::T_NOHTML )) )
            $phs_timezone_city = '';
        $phs_site_name = PHS_Params::_p( 'phs_site_name', PHS_Params::T_NOHTML );
        $phs_contact_email = PHS_Params::_p( 'phs_contact_email', PHS_Params::T_NOHTML );
        $phs_sitebuild_version = PHS_Params::_p( 'phs_sitebuild_version', PHS_Params::T_NOHTML );
        $phs_debug_mode = PHS_Params::_p( 'phs_debug_mode', PHS_Params::T_INT );
        $phs_php_cli_path = PHS_Params::_p( 'phs_php_cli_path', PHS_Params::T_NOHTML );

        $do_submit = PHS_Params::_p( 'do_submit', PHS_Params::T_NOHTML );

        if( !($all_timezones_arr = @timezone_identifiers_list()) )
            $all_timezones_arr = array();

        $phs_site_timezone = '';
        if( !empty( $phs_timezone_continent ) and !empty( $phs_timezone_city ) )
            $phs_site_timezone = $phs_timezone_continent.'/'.$phs_timezone_city;

        $timezones_arr = array();
        foreach( $all_timezones_arr as $timezone_str )
        {
            if( !($timezone_parts = explode( '/', $timezone_str, 2 ))
             or empty( $timezone_parts[0] ) or empty( $timezone_parts[1] ) )
                continue;

            if( empty( $timezones_arr[$timezone_parts[0]] ) )
                $timezones_arr[$timezone_parts[0]] = array();

            $timezones_arr[$timezone_parts[0]][] = $timezone_parts[1];
        }

        if( !empty( $all_timezones_arr )
        and !empty( $phs_site_timezone ) and in_array( $phs_site_timezone, $all_timezones_arr ) )
            @date_default_timezone_set( $phs_site_timezone );

        if( !empty( $do_submit ) )
        {
            if( !empty( $all_timezones_arr )
            and (empty( $phs_site_timezone ) or !in_array( $phs_site_timezone, $all_timezones_arr )) )
                $this->add_error_msg( 'Please provide a valid Site Timezone.' );

            if( empty( $phs_site_name ) )
                $this->add_error_msg( 'Please provide Site Name.' );

            if( empty( $phs_php_cli_path )
             or !@file_exists( $phs_php_cli_path )
             or !@is_executable( $phs_php_cli_path ) )
                $this->add_error_msg( 'Please provide PHP CLI Binary Path.' );

            if( empty( $phs_contact_email )
             or !($contact_emails_arr = self::extract_strings_from_comma_separated( $phs_contact_email, array( 'trim_parts' => true, 'dump_empty_parts' => true ) )) )
                $this->add_error_msg( 'Please provide site contact email(s).' );

            else
            {
                foreach( $contact_emails_arr as $contact_email )
                {
                    if( !PHS_Params::check_type( $contact_email, PHS_Params::T_EMAIL ) )
                        $this->add_error_msg( $this->_pt( '%s is not a valid email address for site contact email.', $contact_email ) );
                }
            }

            if( empty( $phs_sitebuild_version ) )
                $phs_sitebuild_version = '1.0.0';

            if( !$this->has_error_msgs() )
            {
                $defines_arr = array(
                    'PHS_KNOWN_VERSION' => array(
                        'value' => phs_version(),
                        'line_comment' => 'PHS version at the time of installation. bootstrap.php will announce that main.php has to be updated',
                    ),
                    'PHS_SITEBUILD_VERSION' => array(
                        'value' => trim( $phs_sitebuild_version ),
                        'line_comment' => 'Site build version',
                    ),

                    'PHS_DEFAULT_SITE_NAME' => array(
                        'value' => $phs_site_name,
                        'line_comment' => 'How site should be known by name',
                    ),

                    'PHS_CONTACT_EMAIL' => array(
                        'value' => $phs_contact_email,
                        'line_comment' => 'Comma separated emails',
                    ),

                    'PHS_DEFAULT_SITE_TIMEZONE' => array(
                        'value' => $phs_site_timezone,
                    ),

                    'PHS_DEBUG_MODE' => array(
                        'line_comment' => 'Debugging mode?',
                        'raw' => ($phs_debug_mode?'true':'false'),
                    ),

                    'PHS_DEBUG_THROW_ERRORS' => array(
                        'raw' => 'false',
                    ),

                    'PHP_EXEC' => array(
                        'line_comment' => 'PHP CLI binary executable full path',
                        'value' => $phs_php_cli_path,
                    ),
                );

                $config_params = array(
                    array(
                        'defines' => $defines_arr,
                    ),
                    array(
                        'line_comment' => 'Timezone used in the site',
                        'raw' => "\n".
                                 '@date_default_timezone_set( \''.$phs_site_timezone.'\' );'."\n".
                                 "\n".
                                 'if( @function_exists( \'mb_internal_encoding\' ) )'."\n".
                                 '    @mb_internal_encoding( \'UTF-8\' );'."\n",
                    ),

                    // array(
                    //     'raw' => "\n".
                    //              'include_once( PHS_PATH.\'bootstrap.php\' );'."\n".
                    //              "\n",
                    // ),
                );

                if( $this->save_step_config_file( $config_params ) )
                {
                    $this->add_success_msg( 'Config file saved with success. Redirecting to next step...' );

                    if( ($setup_instance = $this->setup_instance()) )
                        $setup_instance->goto_next_step();
                }

                else
                {
                    if( $this->has_error() )
                        $this->add_error_msg( $this->get_error_message() );
                    else
                        $this->add_error_msg( 'Error saving config file for current step.' );
                }
            }
        }

        if( empty( $foobar ) )
        {
            if( $this->config_file_loaded() )
            {
                $this->add_notice_msg( 'Existing config file loaded...' );

                $phs_site_name = PHS_DEFAULT_SITE_NAME;
                $phs_contact_email = PHS_CONTACT_EMAIL;
                $phs_site_timezone = (defined( 'PHS_DEFAULT_SITE_TIMEZONE' )?constant( 'PHS_DEFAULT_SITE_TIMEZONE' ):'');
                $phs_sitebuild_version = PHS_SITEBUILD_VERSION;
                $phs_debug_mode = (PHS_DEBUG_MODE?1:0);
                $phs_php_cli_path = (defined( 'PHP_EXEC' )?constant( 'PHP_EXEC' ):'');

                if( !empty( $phs_site_timezone )
                and ($timezone_parts = explode( '/', $phs_site_timezone, 2 )) )
                {
                    $phs_timezone_continent = (!empty( $timezone_parts[0] )?$timezone_parts[0]:'');
                    $phs_timezone_city = (!empty( $timezone_parts[1] )?$timezone_parts[1]:'');
                }
            } else
            {
                $phs_site_name = 'A New PHS Site';
                $phs_contact_email = '';
                $phs_sitebuild_version = '1.0.0';
                $phs_debug_mode = 1;
                ob_start();
                if( !($phs_php_cli_path = @system( 'which php' )) )
                    $phs_php_cli_path = '/var/bin/php';
                ob_end_clean();

                $phs_timezone_continent = 'Europe';
                $phs_timezone_city = 'London';
                $phs_site_timezone = $phs_timezone_continent.'/'.$phs_timezone_city;
            }
        }

        $data['timezones_arr'] = $timezones_arr;
        $data['phs_timezone_continent'] = $phs_timezone_continent;
        $data['phs_timezone_city'] = $phs_timezone_city;
        $data['phs_site_timezone'] = $phs_site_timezone;
        $data['phs_debug_mode'] = $phs_debug_mode;
        $data['phs_php_cli_path'] = $phs_php_cli_path;

        $data['phs_site_name'] = $phs_site_name;
        $data['phs_contact_email'] = $phs_contact_email;
        $data['phs_sitebuild_version'] = $phs_sitebuild_version;

        return PHS_Setup_layout::get_instance()->render( 'step3', $data );
    }
}
