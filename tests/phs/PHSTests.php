<?php

namespace phs\tests\phs;

include_once( PHS_CORE_DIR.'phs_cli_plugins_trait.php' );

use \phs\PHS;
use \phs\PHS_Cli;
use \phs\traits\PHS_Cli_plugins_trait;
use \phs\libraries\PHS_Utils;
use \phs\libraries\PHS_Plugin;
use \Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;

class PHSTests extends PHS_Cli
{
    use PHS_Cli_plugins_trait;

    const APP_NAME = 'PHSTests',
          APP_VERSION = '1.0.0',
          APP_DESCRIPTION = 'Manage framework test cases.';

    const DIR_BEHAT = 'behat', DIR_PHPUNIT = 'phpunit';

    public function get_app_dir()
    {
        return __DIR__.'/';
    }

    protected function _get_app_options_definition()
    {
        return array();
    }

    protected function _get_app_commands_definition()
    {
        return array(
            'plugins' => array(
                'description' => 'List available plugins',
                'callback' => array( $this, 'cmd_list_plugins' ),
            ),
            'plugin' => array(
                'description' => 'Plugin management plugin [name] [action]. If no action is provided, display plugin details.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'behat_all' => array(
                'description' => 'Enable Behat features for all plugins which have any feature files available.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'phpunit_all' => array(
                'description' => 'Enable PHPUnit for all plugins which have any test files available.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'behat_disable' => array(
                'description' => 'Disable Behat feature files for all plugins.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'phpunit_disable' => array(
                'description' => 'Disable PHPUnit for all plugins.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'enable_all' => array(
                'description' => 'Enable Behat and PHPUnit for all plugins.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'disable_all' => array(
                'description' => 'Disable Behat and PHPUnit for all plugins.',
                'callback' => array( $this, 'cmd_plugin_action' ),
            ),
            'behat_suites' => array(
                'description' => 'Display all Behat available suites.',
                'callback' => array( $this, 'cmd_list_behat_suites' ),
            ),
        );
    }

    private static function _get_plugin_command_actions()
    {
        return array( 'info', 'behat_enable', 'behat_disable', 'phpunit_enable', 'phpunit_disable', 'enable_all', 'disable_all' );
    }

    /**
     * Returns top directory for Behat feature files. This directory will be scanned recursively for .feature files
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_behat_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.($slash_ended?'/':'');
    }

    /**
     * Returns top directory for Behat feature files. This directory will be scanned recursively for .feature files.
     * {plugin_name}/tests/behat/features directory will be symlinked here as {plugin_name}
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_behat_features_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.'/features'.($slash_ended?'/':'');
    }

    /**
     * Returns top directory for Behat context files. This directory will contain contexts used by feature files.
     * {plugin_name}/tests/behat/contexts directory will be symlinked here as {plugin_name}
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_behat_contexts_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.'/contexts'.($slash_ended?'/':'');
    }

    /**
     * Returns top directory for Behat config files. This directory will contain Behat YAML config files which will be included in behat config file
     * {plugin_name}/tests/behat/behat.yml file will be symlinked here as {plugin_name}.yml
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_behat_config_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.'/config'.($slash_ended?'/':'');
    }

    /**
     * This file will be auto-generated each time we add or remove a plugin from Behat tests
     *
     * @return string
     */
    public function get_behat_plugins_config_file()
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.'/plugins.yml';
    }

    /**
     * Returns top directory for PHPUnit tests directory. This directory will be scanned recursively for PHPUnit tests
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_phpunit_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_PHPUNIT.($slash_ended?'/':'');
    }

    //
    //region Environment initialization
    //
    protected function _init_app()
    {
        $this->reset_error();

        if( !$this->_init_behat_environment()
         or !$this->_init_phpunit_environment() )
            return false;

        return true;
    }

    protected function _init_behat_environment()
    {
        $this->reset_error();

        if( !($behat_dir = $this->get_behat_dir( false ))
         or !($behat_features_dir = $this->get_behat_features_dir( false ))
         or !($behat_contexts_dir = $this->get_behat_contexts_dir( false ))
         or !($behat_config_dir = $this->get_behat_config_dir( false )) )
        {
            $this->set_error( self::ERR_FRAMEWORK, self::_t( 'Cannot obtain Behat directories.' ) );
            return false;
        }

        if( !@is_dir( $behat_dir )
        and !PHS_Utils::mkdir_tree( $behat_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !@is_dir( $behat_features_dir )
        and !PHS_Utils::mkdir_tree( $behat_features_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat features directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !@is_dir( $behat_contexts_dir )
        and !PHS_Utils::mkdir_tree( $behat_contexts_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat contexts directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !@is_dir( $behat_config_dir )
        and !PHS_Utils::mkdir_tree( $behat_config_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat config directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !$this->_generate_behat_plugins_config_file() )
            return false;

        return true;
    }

    protected function _generate_behat_plugins_config_file()
    {
        $this->reset_error();

        if( !($behat_config_dir = $this->get_behat_config_dir( false ))
         or !($plugins_config_file = $this->get_behat_plugins_config_file()) )
        {
            $this->set_error( self::ERR_FRAMEWORK, self::_t( 'Cannot obtain Behat config directories or files.' ) );
            return false;
        }

        if( !($fil = @fopen( $plugins_config_file, 'wb' )) )
        {
            $this->set_error( self::ERR_FRAMEWORK, self::_t( 'Error opening plugins.yml config file for writing. Please check tests directory rights.' ) );
            return false;
        }

        $buf = '# This file will be generated each time a new plugin is added to behat features'."\n".
               '# DO NOT CHANGE THIS FILE MANUALLY!!!'."\n".
               "\n";

        if( !@fwrite( $fil, $buf ) )
        {
            @fclose( $fil );
            @unlink( $plugins_config_file );

            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error generating plugins.yml config file.' ) );
            return false;
        }
        @fflush( $fil );

        if( !($config_files = $this->_get_behat_installed_plugins()) )
        {
            @fclose( $fil );
            return true;
        }

        if( !@fwrite( $fil, 'imports:'."\n" ) )
        {
            @fclose( $fil );
            @unlink( $plugins_config_file );

            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error generating plugins.yml config file.' ) );
            return false;
        }

        foreach( $config_files as $config_file )
        {
            if( !@fwrite( $fil, '  - "config/'.@basename( $config_file ).'"'."\n" ) )
            {
                @fclose( $fil );
                @unlink( $plugins_config_file );

                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error generating plugins.yml config file.' ) );
                return false;
            }
        }
        @fflush( $fil );

        @fclose( $fil );

        return true;
    }

    protected function _init_phpunit_environment()
    {
        $this->reset_error();

        if( !($phpunit_dir = $this->get_phpunit_dir( false )) )
        {
            $this->set_error( self::ERR_FRAMEWORK, self::_t( 'Cannot obtain Behat or PHPUnit directories.' ) );
            return false;
        }

        if( !@is_dir( $phpunit_dir )
        and !PHS_Utils::mkdir_tree( $phpunit_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating PHPUnit directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        return true;
    }
    //
    //endregion Environment initialization
    //

    /**
     * Parse Behat YAML configuration file and return configurations for $behat_profile profile
     * as array
     *
     * @param string $behat_profile
     *
     * @return array
     */
    protected function get_behat_yaml_configuration_as_array( $behat_profile = 'default' )
    {
        try {
            $configuration_loader = new ConfigurationLoader( 'BEHAT_PARAMS', PHS_TESTS_DIR.'behat.yml' );

            if( !($configs_arr = $configuration_loader->loadConfiguration( $behat_profile )) )
                $configs_arr = [];
        } catch( \Exception $e )
        {
            $configs_arr = [];
        }

        return $configs_arr;
    }

    public function cmd_list_behat_suites()
    {
        $this->reset_error();

        if( !($command_arr = $this->get_app_command())
         or empty( $command_arr['arguments'] )
         or !($behat_profile = $this->_get_argument_chained( $command_arr['arguments'] )) )
        {
            $behat_profile = 'default';

            $this->_echo( self::_t( 'No Behat profile provided. Using %s as default Behat profile.', $this->cli_color( $behat_profile, 'green' ) ) );

            $this->_echo( 'Usage: '.$this->get_app_cli_script().' [options] behat_suites [behat_profile]' );
            $this->_echo( '' );
        }

        $this->_echo( self::_t( 'Displaying Behat suites for profile %s.', $this->cli_color( $behat_profile, 'green' ) ) );

        if( !($configs_arr = $this->get_behat_yaml_configuration_as_array( $behat_profile )) )
            $configs_arr = array();

        $suites_arr = [];
        foreach( $configs_arr as $yml_config )
        {
            if( empty( $yml_config ) or !is_array( $yml_config )
             or empty( $yml_config['suites'] ) or !is_array( $yml_config['suites'] ) )
                continue;

            foreach( $yml_config['suites'] as $suite_name => $suite_configuration )
            {
                $suites_arr[$suite_name] = true;
            }
        }

        if( empty( $suites_arr ) )
            $this->_echo( self::_t( 'No suites available for provided profile.' ) );
        else
            $this->_echo( self::_t( 'Available suites: %s.', implode( ', ', @array_keys( $suites_arr ) ) ) );

        return true;
    }

    public function cmd_plugin_action()
    {
        $this->reset_error();

        if( false === ($plugins_dirs_arr = $this->get_plugins_as_dirs()) )
        {
            $this->_echo_error( self::_t( 'Couldn\'t obtaining plugins list: %s', $this->get_simple_error_message() ) );
            return false;
        }

        if( empty( $plugins_dirs_arr ) )
        {
            $this->_echo( self::_t( 'No plugin installed in plugins directory yet.' ) );
            return false;
        }

        if( !($command_arr = $this->get_app_command())
         or empty( $command_arr['arguments'] )
         or !($plugin_name = $this->_get_argument_chained( $command_arr['arguments'] ))
         or !in_array( $plugin_name, $plugins_dirs_arr, true ) )
        {
            $this->_echo_error( self::_t( 'Please provide a valid plugin name. Use %s command to view all plugins.', $this->cli_color( 'plugins', 'green' ) ) );

            $this->_echo( 'Usage: '.$this->get_app_cli_script().' [options] plugin [plugin_action]' );
            return false;
        }

        if( !($plugin_action = $this->_get_argument_chained()) )
            $plugin_action = '';

        if( empty( $plugin_action ) )
        {
            return $this->_echo_plugin_details_for_tests( $plugin_name );
        }

        if( !in_array( $plugin_action, self::_get_plugin_command_actions(), true ) )
        {
            $this->_echo_error( self::_t( 'Invalid action.' ) );

            $this->_echo( 'Usage: '.$this->get_app_cli_script().' [options] plugin [plugin_action]' );
            $this->_echo( 'Available actions: '.implode( ', ', self::_get_plugin_command_actions() ).'.' );
            return false;
        }

        switch( $plugin_action )
        {
            case 'behat_enable':
                if( !($result_arr = $this->_install_behat_tests_for_plugin( $plugin_name )) )
                    return false;

                $this->_echo( self::_t( 'Behat tests ENABLED for plugin %s with success.', $plugin_name ) );
            break;

            case 'behat_disable':
                if( !($result_arr = $this->_uninstall_behat_tests_for_plugin( $plugin_name )) )
                    return false;

                $this->_echo( self::_t( 'Behat tests DISABLED for plugin %s with success.', $plugin_name ) );
            break;
        }

        return true;
    }

    protected function _echo_plugin_details_for_tests( $plugin_name )
    {
        if( !($plugin_info = $this->_gather_plugin_test_info( $plugin_name ))
         || !is_array( $plugin_info )
         || !$this->_echo_plugin_details( $plugin_name, $plugin_info ) )
        {
            $this->_echo_error( self::_t( 'Error obtaining plugin details for plugin %s.', $this->cli_color( $plugin_name, 'red' ) ) );
            return false;
        }

        $yes_str = self::_t( 'Yes' );
        $no_str = self::_t( 'No' );

        $this->_echo( '' );
        $this->_echo( self::_t( 'Behat integration' ).':' );
        if( empty( $plugin_info['behat'] ) or !is_array( $plugin_info['behat'] ) )
            $this->_echo( self::_t( '  N/A' ) );

        else
        {
            $this->_echo( '  '.
                          self::_t( 'AVAILABLE for Behat tests' ).': '.
                          (!empty( $plugin_info['behat']['is_installable'] )?$this->cli_color( $yes_str, 'green' ):$this->cli_color( $no_str, 'red' )).
                          ', '.
                          self::_t( 'INSTALLED for Behat tests' ).': '.
                          (!empty( $plugin_info['behat']['is_installed'] )?$this->cli_color( $yes_str, 'green' ):$this->cli_color( $no_str, 'red' ))
            );

            $available_str = '  '.self::_t( 'Feature files' ).': ';
            if( empty( $plugin_info['behat']['features_files'] ) or !is_array( $plugin_info['behat']['features_files'] ) )
                $available_str .= self::_t( 'N/A' );

            else
            {
                $base_names = array();
                foreach( $plugin_info['behat']['features_files'] as $test_file )
                {
                    $base_names[] = @basename( $test_file );
                }

                $available_str .= @implode( ', ', $base_names ).'.';
            }

            $this->_echo( $available_str );

            $context_str = '  '.self::_t( 'Context files' ).': ';
            if( empty( $plugin_info['behat']['context_files'] ) or !is_array( $plugin_info['behat']['context_files'] ) )
                $context_str .= self::_t( 'N/A' );

            else
            {
                $base_names = array();
                foreach( $plugin_info['behat']['context_files'] as $context_file )
                {
                    $base_names[] = @basename( $context_file );
                }

                $context_str .= @implode( ', ', $base_names ).'.';
            }

            $this->_echo( $context_str );
        }

        $this->_echo( '' );
        $this->_echo( self::_t( 'PHPUnit integration' ).':' );
        if( empty( $plugin_info['phpunit'] ) or !is_array( $plugin_info['phpunit'] ) )
            $this->_echo( self::_t( '  N/A' ) );

        else
        {
            $this->_echo( 'TO BE DEVELOPED.' );
        }

        return true;
    }

    public function cmd_list_plugins()
    {
        $this->reset_error();

        if( false === ($plugins_dirs_arr = $this->get_plugins_as_dirs()) )
        {
            $this->_echo( self::_t( 'Couldn\'t obtaining plugins list: %s', $this->get_simple_error_message() ) );
            return false;
        }

        if( empty( $plugins_dirs_arr ) )
        {
            $this->_echo( self::_t( 'No plugin installed in plugin directory yet.' ) );
            return false;
        }

        $this->_echo( self::_t( 'Found %s plugin directories...', count( $plugins_dirs_arr ) ) );
        foreach( $plugins_dirs_arr as $plugin_name )
        {
            if( !($plugin_info = $this->_gather_plugin_test_info( $plugin_name )) )
                $plugin_info = self::_get_default_plugin_info_definition_for_tests();

            $extra_info = '';
            if( !empty( $plugin_info ) )
            {
                if( !empty( $plugin_info['is_installed'] ) )
                    $extra_info .= '['.$this->cli_color( self::_t( 'Installed' ), 'green' ).']';
                else
                    $extra_info .= '['.$this->cli_color( self::_t( 'NOT INSTALLED' ), 'red' ).']';

                if( !empty( $plugin_info['is_installed'] ) )
                {
                    $extra_info .= (!empty( $extra_info )?' ':'');
                    if( !empty( $plugin_info['is_active'] ) )
                        $extra_info .= '['.$this->cli_color( self::_t( 'Active' ), 'green' ).']';
                    else
                        $extra_info .= '['.$this->cli_color( self::_t( 'NOT ACTIVE' ), 'red' ).']';

                    $extra_info .= ' ';
                }

                if( !empty( $plugin_info['name'] ) )
                    $extra_info .= $plugin_info['name'];
                if( !empty( $plugin_info['version'] ) )
                    $extra_info .= ($extra_info!==''?' ':'').'(v'.$plugin_info['version'].')';
                if( !empty( $plugin_info['models_count'] ) )
                    $extra_info .= ($extra_info!==''?', ':'').$plugin_info['models_count'].' models';
            }

            $this->_echo( ' - '.$this->cli_color( $plugin_name, 'blue' ).($extra_info!==''?': ':'').$extra_info );

            // Tests stats...
            $tests_str = '    '.self::_t( 'Tests' ).': ';

            // Behat...
            $tests_str .= 'Behat: ';
            if( empty( $plugin_info['behat'] )
             or empty( $plugin_info['behat']['is_installable'] ) )
                $tests_str .= 'N/A';

            elseif( empty( $plugin_info['behat']['is_installed'] ) )
            {
                $tests_str .= $this->cli_color( self::_t( 'AVAILABLE' ), 'green' ).' [F'.count( $plugin_info['behat']['features_files'] ).'/'.
                              'C'.count( $plugin_info['behat']['context_files'] ).']';

            } else
            {
                $tests_str .= $this->cli_color( self::_t( 'INSTALLED' ), 'green' ).' ['.count( $plugin_info['behat']['available_files'] ).'/'.
                              count( $plugin_info['behat']['installed_files'] ).']';

            }

            // Behat...
            $tests_str .= ', PHPUnit: ';
            if( empty( $plugin_info['phpunit'] ) )
                $tests_str .= 'N/A';

            else
            {
                $tests_str .= '['.count( $plugin_info['phpunit']['available_files'] ).'/'.
                              count( $plugin_info['phpunit']['installed_files'] ).']';
            }

            $this->_echo( $tests_str );
        }

        $this->_echo( 'DONE' );

        return true;
    }

    protected static function _get_default_behat_plugin_stats()
    {
        return array(
            // If we have a behat.yml file in {plugin_name}/tests/behat dir of plugin, it means we can install this plugin in behat tests
            'is_installable' => false,
            // If we have a {plugin_name}.yml file in {tests_dir}/behat/config dir, it means plugin is installed for behat tests
            'is_installed' => false,
            'features_dir' => '',
            'contexts_dir' => '',
            'config_file' => '',
            'features_files' => array(),
            'context_files' => array(),
        );
    }

    protected static function _get_default_plugin_info_definition_for_tests()
    {
        return self::validate_array( self::_get_default_plugin_info_definition(),
            [
            // Tests details
            'behat' => false,
            'phpunit' => false,
            ]
        );
    }

    /**
     * @param string $plugin_name Plugin name
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj Plugin instance (if available)
     *
     * @return bool|array
     */
    private function _install_behat_tests_for_plugin( $plugin_name, $plugin_obj = false )
    {
        $this->reset_error();

        if( !($plugin_name = PHS_Plugin::safe_escape_plugin_name( $plugin_name ))
         or !($behat_stats = $this->_get_behat_plugin_stats( $plugin_name, $plugin_obj )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Plugin name is not a safe name or cannot obtain Behat stats.' ) );
            return false;
        }

        if( empty( $behat_stats['is_installable'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No Behat configuration found for plugin %s.', $plugin_name ) );
            return false;
        }

        if( !($behat_config_dir = $this->get_behat_config_dir( false ))
         or !@file_exists( $behat_config_dir )
         or !@is_dir( $behat_config_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Behat config dir %s not found or is not a directory.', $behat_config_dir ) );
            return false;
        }

        // Check Behat config file (behat.yml) installation
        if( !empty( $behat_stats['config_file'] )
        and @file_exists( $behat_stats['config_file'] ) )
        {
            if( !@is_readable( $behat_stats['config_file'] ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Plugin Behat config file %s is not readable.', $behat_stats['config_file'] ) );
                return false;
            }

            if( !($plugin_behat_config_file = $this->_get_plugin_destination_behat_config_file( $plugin_name )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Could not obtain destination Behat plugin config file.' ) );
                return false;
            }

            if( @file_exists( $plugin_behat_config_file ) )
            {
                if( !@is_readable( $plugin_behat_config_file )
                and !@unlink( $plugin_behat_config_file ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Destination Behat plugin config file %s is not readable and couldn\'t delete it for regeneration.', $plugin_behat_config_file ) );
                    return false;
                }

                if( !@is_link( $plugin_behat_config_file ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Destination Behat plugin config file %s is not a symlink.'.
                                      'We recommand you delete it and then let PHS create a symlink for it.', $plugin_behat_config_file ) );
                    return false;
                }

            } elseif( !@symlink( $behat_stats['config_file'], $plugin_behat_config_file ) )
            {
                $this->_uninstall_behat_tests_for_plugin( $plugin_name, $plugin_obj );

                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Destination Behat plugin config file %s is not a symlink.'.
                                                                     'We recommand you delete it and then let PHS create a symlink for it.', $plugin_behat_config_file ) );
                return false;
            }
        }
        // END Check Behat config file (behat.yml) installation

        // Check Behat features directory installation
        $features_dir = rtrim( $behat_stats['features_dir'], '/\\' );
        if( !empty( $features_dir )
        and @file_exists( $features_dir ) )
        {
            if( !@is_dir( $features_dir )
             or !@is_readable( $features_dir ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Plugin Behat features dir %s is not readable.', $features_dir ) );
                return false;
            }

            if( !($plugin_behat_features_dir = $this->_get_plugin_destination_features_directory( $plugin_name, false )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Could not obtain destination Behat plugin features dir.' ) );
                return false;
            }

            if( @file_exists( $plugin_behat_features_dir ) )
            {
                if( !@is_readable( $plugin_behat_features_dir )
                and !@unlink( $plugin_behat_features_dir ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY,
                                      self::_t( 'Destination Behat plugin features dir %s is not readable and couldn\'t delete it for regeneration.',
                                                $plugin_behat_features_dir ) );
                    return false;
                }

                if( !@is_link( $plugin_behat_features_dir ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Destination Behat plugin features dir %s is not a symlink.'.
                                      'We recommand you delete it and then let PHS create a symlink for it.', $plugin_behat_features_dir ) );
                    return false;
                }

            } elseif( !@symlink( $features_dir, $plugin_behat_features_dir ) )
            {
                $this->_uninstall_behat_tests_for_plugin( $plugin_name, $plugin_obj );

                $this->set_error( self::ERR_FUNCTIONALITY,
                                  self::_t( 'Error creating symlink %s for destination Behat plugin features dir %s.',
                                            $features_dir, $plugin_behat_features_dir ) );
                return false;
            }
        }
        // END Check Behat features directory installation

        // Check Behat contexts directory installation
        $contexts_dir = rtrim( $behat_stats['contexts_dir'], '/\\' );
        if( !empty( $contexts_dir )
        and @file_exists( $contexts_dir ) )
        {
            if( !@is_dir( $contexts_dir )
             or !@is_readable( $contexts_dir ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Plugin Behat contexts dir %s is not readable.', $contexts_dir ) );
                return false;
            }

            if( !($plugin_behat_contexts_dir = $this->_get_plugin_destination_contexts_directory( $plugin_name, false )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Could not obtain destination Behat plugin contexts dir.' ) );
                return false;
            }

            if( @file_exists( $plugin_behat_contexts_dir ) )
            {
                if( !@is_readable( $plugin_behat_contexts_dir )
                and !@unlink( $plugin_behat_contexts_dir ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY,
                                      self::_t( 'Destination Behat plugin contexts dir %s is not readable and couldn\'t delete it for regeneration.',
                                                $plugin_behat_contexts_dir ) );
                    return false;
                }

                if( !@is_link( $plugin_behat_contexts_dir ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Destination Behat plugin contexts dir %s is not a symlink.'.
                                      'We recommand you delete it and then let PHS create a symlink for it.', $plugin_behat_contexts_dir ) );
                    return false;
                }

            } elseif( !@symlink( $contexts_dir, $plugin_behat_contexts_dir ) )
            {
                $this->_uninstall_behat_tests_for_plugin( $plugin_name, $plugin_obj );

                $this->set_error( self::ERR_FUNCTIONALITY,
                                  self::_t( 'Error creating symlink %s for destination Behat plugin contexts dir %s.',
                                            $contexts_dir, $plugin_behat_contexts_dir ) );
                return false;
            }
        }
        // END Check Behat features directory installation

        if( !$this->_generate_behat_plugins_config_file() )
        {
            $this->_uninstall_behat_tests_for_plugin( $plugin_name, $plugin_obj );

            return false;
        }

        return true;
    }

    /**
     * @param string $plugin_name Plugin name
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj Plugin instance (if available)
     *
     * @return bool|array
     */
    private function _uninstall_behat_tests_for_plugin( $plugin_name, $plugin_obj = false )
    {
        $this->reset_error();

        if( !($plugin_name = PHS_Plugin::safe_escape_plugin_name( $plugin_name ))
            or !($behat_stats = $this->_get_behat_plugin_stats( $plugin_name, $plugin_obj )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Plugin name is not a safe name or cannot obtain Behat stats.' ) );
            return false;
        }

        if( !($behat_config_dir = $this->get_behat_config_dir( false ))
         or !@file_exists( $behat_config_dir )
         or !@is_dir( $behat_config_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Behat config dir %s not found or is not a directory.', $behat_config_dir ) );
            return false;
        }

        // Delete config file/symlink...
        if( ($plugin_behat_config_file = $this->_get_plugin_destination_behat_config_file( $plugin_name ))
        and @file_exists( $plugin_behat_config_file )
        and !@unlink( $plugin_behat_config_file ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t delete destination Behat plugin config file %s.', $plugin_behat_config_file ) );
            return false;
        }

        // Delete features dir/symlink...
        if( ($plugin_behat_features_dir = $this->_get_plugin_destination_features_directory( $plugin_name, false ))
        and @file_exists( $plugin_behat_features_dir )
        and !@unlink( $plugin_behat_features_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t delete destination Behat plugin features dir %s.', $plugin_behat_features_dir ) );
            return false;
        }

        // Delete contexts dir/symlink...
        if( ($plugin_behat_contexts_dir = $this->_get_plugin_destination_contexts_directory( $plugin_name, false ))
        and @file_exists( $plugin_behat_contexts_dir )
        and !@unlink( $plugin_behat_contexts_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t delete destination Behat plugin contexts dir %s.', $plugin_behat_contexts_dir ) );
            return false;
        }

        if( !$this->_generate_behat_plugins_config_file() )
            return false;

        return true;
    }

    /**
     * @return bool|array
     */
    private function _get_behat_installed_plugins()
    {
        $this->reset_error();

        if( !($behat_config_dir = $this->get_behat_config_dir( false ))
         or !@file_exists( $behat_config_dir )
         or !@is_dir( $behat_config_dir )
         or !@is_readable( $behat_config_dir ) )
        {
            $this->set_error( self::ERR_FRAMEWORK, self::_t( 'Cannot obtain Behat config directory or is not readable.' ) );
            return false;
        }

        if( !($files_arr = @glob( $behat_config_dir.'/*.yml' ))
         or !is_array( $files_arr ) )
            return array();

        return $files_arr;
    }

    /**
     * @param string $plugin_name
     * @return bool|array
     */
    private function _plugin_is_installed_for_behat( $plugin_name )
    {
        $this->reset_error();

        if( empty( $plugin_name )
         or !($behat_config_dir = $this->get_behat_config_dir( false ))
         or !@file_exists( $behat_config_dir )
         or !@is_dir( $behat_config_dir )
         or !@is_readable( $behat_config_dir )
         or !($behat_config_file = $this->_get_plugin_destination_behat_config_file( $plugin_name ))
         or !@file_exists( $behat_config_file )
         or !@is_readable( $behat_config_file ) )
            return false;

        return $behat_config_file;
    }

    private function _get_plugin_destination_behat_config_file( $plugin_name )
    {
        $this->reset_error();

        if( empty( $plugin_name )
         or !($behat_config_dir = $this->get_behat_config_dir( false )) )
            return false;

        return $behat_config_dir.'/'.$plugin_name.'.yml';
    }

    private function _get_plugin_destination_features_directory( $plugin_name, $slash_ended = true )
    {
        $this->reset_error();

        if( empty( $plugin_name )
         or !($behat_features_dir = $this->get_behat_features_dir( false )) )
            return false;

        return $behat_features_dir.'/'.$plugin_name.($slash_ended?'/':'');
    }

    private function _get_plugin_destination_contexts_directory( $plugin_name, $slash_ended = true )
    {
        $this->reset_error();

        if( empty( $plugin_name )
         or !($behat_contexts_dir = $this->get_behat_contexts_dir( false )) )
            return false;

        return $behat_contexts_dir.'/'.$plugin_name.($slash_ended?'/':'');
    }

    /**
     * @param string $plugin_name
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj
     *
     * @return bool|array
     */
    private function _get_behat_available_features_for_plugin( $plugin_name, $plugin_obj = false )
    {
        $this->reset_error();

        if( empty( $plugin_obj )
        and (empty( $plugin_name )
            or !($plugin_obj = PHS::load_plugin( $plugin_name ))
            ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error loading plugin when obtaining Behat available features.' ) );
            return false;
        }

        if( !($behat_details = $plugin_obj->instance_plugin_behat_details())
         or empty( $behat_details['features_path'] )
         or !($features_dir = $behat_details['features_path'])
         or !($unslashed_dir = rtrim( $features_dir, '/' ))
         or !@is_dir( $unslashed_dir )
         or !@is_readable( $unslashed_dir )
         or !($files_arr = @glob( $features_dir.'*.feature' ))
         or !is_array( $files_arr ) )
            return array();

        return $files_arr;
    }

    /**
     * @param string $plugin_name
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj
     *
     * @return bool|array
     */
    private function _get_behat_available_contexts_for_plugin( $plugin_name, $plugin_obj = false )
    {
        $this->reset_error();

        if( empty( $plugin_obj )
        and (empty( $plugin_name )
            or !($plugin_obj = PHS::load_plugin( $plugin_name ))
            ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error loading plugin when obtaining Behat available contexts.' ) );
            return false;
        }

        if( !($behat_details = $plugin_obj->instance_plugin_behat_details())
         or empty( $behat_details['contexts_path'] )
         or !($contexts_dir = $behat_details['contexts_path'])
         or !($unslashed_dir = rtrim( $contexts_dir, '/' ))
         or !@is_dir( $unslashed_dir )
         or !@is_readable( $unslashed_dir )
         or !($files_arr = @glob( $contexts_dir.'*.php' ))
         or !is_array( $files_arr ) )
            return array();

        return $files_arr;
    }

    private function _gather_plugin_test_info( $plugin_name )
    {
        $plugin_info = [];
        if( ($basic_plugin_info = $this->_gather_plugin_info( $plugin_name )) )
            $plugin_info = $basic_plugin_info;

        $plugin_info = self::validate_array( $plugin_info, self::_get_default_plugin_info_definition_for_tests() );

        // Check Behat features directory...
        if( ($behat_stats = $this->_get_behat_plugin_stats( $plugin_name )) )
        {
            $plugin_info['behat'] = $behat_stats;
        }
        // END Check Behat features directory...

        return $plugin_info;
    }

    /**
     * @param string $plugin_name
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj
     *
     * @return bool|array
     */
    protected function _get_behat_plugin_stats( $plugin_name, $plugin_obj = false )
    {
        $this->reset_error();

        if( empty( $plugin_obj )
        and (empty( $plugin_name )
            or !($plugin_obj = PHS::load_plugin( $plugin_name ))
            ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error loading plugin when obtaining Behat available features.' ) );
            return false;
        }

        $behat_stats = self::_get_default_behat_plugin_stats();
        if( !($behat_details = $plugin_obj->instance_plugin_behat_details())
         or empty( $behat_details['config_file_path'] )
         or !@file_exists( $behat_details['config_file_path'] )
         or !@is_readable( $behat_details['config_file_path'] ) )
            return $behat_stats;

        $behat_stats['is_installable'] = true;
        $behat_stats['features_dir'] = $behat_details['features_path'];
        $behat_stats['contexts_dir'] = $behat_details['contexts_path'];
        $behat_stats['config_file'] = $behat_details['config_file_path'];

        if( false !== $this->_plugin_is_installed_for_behat( $plugin_name ) )
            $behat_stats['is_installed'] = true;

        if( !($feature_files = $this->_get_behat_available_features_for_plugin( $plugin_name, $plugin_obj )) )
            $feature_files = array();
        if( !($context_files = $this->_get_behat_available_contexts_for_plugin( $plugin_name, $plugin_obj )) )
            $context_files = array();

        $behat_stats['features_files'] = $feature_files;
        $behat_stats['context_files'] = $context_files;

        return $behat_stats;
    }
}
