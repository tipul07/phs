<?php

namespace phs\tests\phs;

use \phs\PHS;
use \phs\PHS_cli;
use phs\libraries\PHS_utils;
use phs\libraries\PHS_Plugin;

class phs_tests extends PHS_cli
{
    const APP_NAME = 'PHSTests',
          APP_VERSION = '1.0.0',
          APP_DESCRIPTION = 'Manage framework test cases.';

    const DIR_BEHAT = 'behat', DIR_PHPUNIT = 'phpunit';

    /** @var \phs\system\core\models\PHS_Model_Plugins $_plugins_model */
    private $_plugins_model = false;

    public function get_app_dir()
    {
        return __DIR__.'/';
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
     * Returns top directory for Behat feature files. This directory will be scanned recursively for .feature files
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_behat_features_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.'/features'.($slash_ended?'/':'');
    }

    /**
     * Returns top directory for Behat feature files. This directory will be scanned recursively for .feature files
     * @param bool $slash_ended
     *
     * @return string
     */
    public function get_behat_contexts_dir( $slash_ended = true )
    {
        return PHS_TESTS_DIR.self::DIR_BEHAT.'/contexts'.($slash_ended?'/':'');
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

    protected function _init_app()
    {
        $this->reset_error();

        if( !($app_dir = $this->get_app_dir())
         or !($behat_dir = $this->get_behat_dir( false ))
         or !($behat_features_dir = $this->get_behat_features_dir( false ))
         or !($behat_contexts_dir = $this->get_behat_contexts_dir( false ))
         or !($phpunit_dir = $this->get_phpunit_dir( false )) )
        {
            $this->set_error( self::ERR_FRAMEWORK, self::_t( 'Cannot obtain Behat or PHPUnit directories.' ) );
            return false;
        }

        if( !@is_dir( $behat_dir )
        and !PHS_utils::mkdir_tree( $behat_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !@is_dir( $behat_features_dir )
        and !PHS_utils::mkdir_tree( $behat_features_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat features directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !@is_dir( $behat_contexts_dir )
        and !PHS_utils::mkdir_tree( $behat_contexts_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat contexts directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        if( !@is_dir( $phpunit_dir )
        and !PHS_utils::mkdir_tree( $phpunit_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating PHPUnit directory. Please check directory rights in tests directory.' ) );
            return false;
        }

        return true;
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
        );
    }

    private static function _get_plugin_command_actions()
    {
        return array( 'info', 'behat_enable', 'behat_disable', 'phpunit_enable', 'phpunit_disable', 'enable_all', 'disable_all' );
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
            $this->_echo( PHS::_t( 'No plugin installed in plugins directory yet.' ) );
            return false;
        }

        if( !($command_arr = $this->get_app_command())
         or empty( $command_arr['arguments'] )
         or !($plugin_name = $this->_get_argument_chained( $command_arr['arguments'] ))
         or !in_array( $plugin_name, $plugins_dirs_arr, true ) )
        {
            $this->_echo_error( PHS::_t( 'Please provide a valid plugin name. Use %s command to view all plugins.', $this->cli_color( 'plugins', 'green' ) ) );

            $this->_echo( 'Usage: '.$this->get_app_cli_script().' [options] plugin [plugin_action]' );
            return false;
        }

        if( !($plugin_action = $this->_get_argument_chained()) )
            $plugin_action = '';

        if( empty( $plugin_action ) )
        {
            return $this->_echo_plugin_details( $plugin_name );
        }

        if( !in_array( $plugin_action, self::_get_plugin_command_actions(), true ) )
        {
            $this->_echo_error( PHS::_t( 'Invalid action.' ) );

            $this->_echo( 'Usage: '.$this->get_app_cli_script().' [options] plugin [plugin_action]' );
            $this->_echo( 'Available actions: '.implode( ', ', self::_get_plugin_command_actions() ).'.' );
            return false;
        }

        switch( $plugin_action )
        {
            case 'behat_enable':
                if( !($result_arr = $this->_install_behat_features_for_plugin( $plugin_name )) )
                    return false;

                $this->_echo( self::_t( 'Previously installed features: %s', $result_arr['existing'] ).', '.
                              self::_t( 'Newly installed features: %s', $result_arr['existing'] ).', '.
                              self::_t( 'Failed installing features: %s', $result_arr['failed'] ).', '.
                              self::_t( 'Deleted old features: %s', $result_arr['deleted'] ).'.'
                );
            break;

            case 'behat_disable':
                if( !($result_arr = $this->_uninstall_behat_features_for_plugin( $plugin_name )) )
                    return false;

                $this->_echo( self::_t( 'Previously installed features: %s', $result_arr['existing'] ).', '.
                              self::_t( 'Uninstalled features: %s', $result_arr['uninstalled'] ).', '.
                              self::_t( 'Failed un-installing features: %s', $result_arr['failed'] ).'.'
                );
            break;
        }

        return true;
    }

    protected function _echo_plugin_details( $plugin_name )
    {
        if( !($plugin_info = $this->_gather_plugin_test_info( $plugin_name ))
         or !is_array( $plugin_info ) )
        {
            $this->_echo_error( self::_t( 'Error obtaining plugin details for plugin %s.', $this->cli_color( $plugin_name, 'red' ) ) );
            return false;
        }

        $yes_str = self::_t( 'Yes' );
        $no_str = self::_t( 'No' );

        $this->_echo( self::_t( 'Plugin name' ).': '.$this->cli_color( $plugin_info['name'], 'green' ) );
        $this->_echo( self::_t( 'Vendor' ).': '.$this->cli_color( $plugin_info['vendor_name'], 'green' ).' ('.$plugin_info['vendor_id'].')' );
        $this->_echo( self::_t( 'Version' ).
                      ': Script - '.$this->cli_color( $plugin_info['version'], 'green' ).
                      ', Database - '.$this->cli_color( $plugin_info['db_version'], 'green' ).
                      (!empty( $plugin_info['is_upgradable'] )?' ['.$this->cli_color( self::_t( 'Upgradable' ), 'red' ).']':'') );

        $this->_echo( self::_t( 'Flags' ).': '.
                      self::_t( 'Core plugin' ).': '.(!empty( $plugin_info['is_core'] )?$yes_str:$no_str).', '.
                      self::_t( 'Distribution plugin' ).': '.(!empty( $plugin_info['is_distribution'] )?$yes_str:$no_str).', '.
                      self::_t( 'Is installed' ).': '.(!empty( $plugin_info['is_installed'] )?$yes_str:$no_str).', '.
                      self::_t( 'Is active' ).': '.(!empty( $plugin_info['is_active'] )?$yes_str:$no_str).'.'
        );

        $this->_echo( self::_t( 'Models' ).':' );
        if( empty( $plugin_info['models'] ) or !is_array( $plugin_info['models'] ) )
            $this->_echo( self::_t( '  N/A' ) );

        else
        {
            foreach( $plugin_info['models'] as $model_arr )
            {
                $this->_echo( '  - '.$this->cli_color( $model_arr['name'], 'green' ).' ('.$model_arr['driver'].', v'.$model_arr['version'].'), '.
                              self::_t( 'Main table' ).': '.$model_arr['main_table'].', '.
                              self::_t( 'Tables' ).': '.@implode( ', ', $model_arr['tables'] )
                );
            }
        }

        $this->_echo( self::_t( 'Agent jobs' ).':' );
        if( empty( $plugin_info['agent_jobs'] ) or !is_array( $plugin_info['agent_jobs'] ) )
            $this->_echo( self::_t( '  N/A' ) );

        else
        {
            foreach( $plugin_info['agent_jobs'] as $job_arr )
            {
                $route_str = PHS::route_from_parts( PHS::convert_route_to_short_parts( $job_arr['route'] ) );

                $this->_echo( '  - '.$this->cli_color( $job_arr['title'], 'green' ).', '.
                              self::_t( 'Route' ).': '.$route_str.', '.
                              self::_t( 'Runs once %ss', $job_arr['timed_seconds'] )
                );
            }
        }

        $this->_echo( '' );
        $this->_echo( self::_t( 'Behat integration' ).':' );
        if( empty( $plugin_info['behat'] ) or !is_array( $plugin_info['behat'] ) )
            $this->_echo( self::_t( '  N/A' ) );

        else
        {
            $this->_echo( '  '.self::_t( 'Features dir exists' ).': '.
                          (!empty( $plugin_info['behat']['dir_exists'] )?$this->cli_color( $yes_str, 'green' ):$this->cli_color( $no_str, 'red' ))
            );

            $available_str = '  '.self::_t( 'Available files' ).': ';
            if( empty( $plugin_info['behat']['available_files'] ) or !is_array( $plugin_info['behat']['available_files'] ) )
                $available_str .= self::_t( 'N/A' );

            else
            {
                $base_names = array();
                foreach( $plugin_info['behat']['available_files'] as $test_file )
                {
                    $base_names[] = @basename( $test_file );
                }

                $available_str .= @implode( ', ', $base_names ).'.';
            }

            $this->_echo( $available_str );

            $installed_str = '  '.self::_t( 'Installed files' ).': ';
            if( empty( $plugin_info['behat']['installed_files'] ) or !is_array( $plugin_info['behat']['installed_files'] ) )
                $installed_str .= self::_t( 'N/A' );

            else
            {
                $base_names = array();
                foreach( $plugin_info['behat']['installed_files'] as $test_file )
                {
                    $base_names[] = @basename( $test_file );
                }

                $installed_str .= @implode( ', ', $base_names ).'.';
            }

            $this->_echo( $installed_str );
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
            $this->_echo( PHS::_t( 'Couldn\'t obtaining plugins list: %s', $this->get_simple_error_message() ) );
            return false;
        }

        if( empty( $plugins_dirs_arr ) )
        {
            $this->_echo( PHS::_t( 'No plugin installed in plugin directory yet.' ) );
            return false;
        }

        $this->_echo( PHS::_t( 'Found %s plugin directories...', count( $plugins_dirs_arr ) ) );
        $found_features_dirs = 0;
        foreach( $plugins_dirs_arr as $plugin_name )
        {
            if( !($plugin_info = $this->_gather_plugin_test_info( $plugin_name )) )
                $plugin_info = self::_get_default_plugin_info_definition();

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
            if( empty( $plugin_info['behat'] ) )
                $tests_str .= 'N/A';

            else
            {
                $tests_str .= '['.count( $plugin_info['behat']['available_files'] ).'/'.
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

    protected static function _get_default_model_info_definition()
    {
        return array(
            'name' => '',
            'driver' => '',
            'version' => '',
            'main_table' => '',
            'tables' => array(),
        );
    }

    protected static function _get_default_behat_plugin_stats()
    {
        return array(
            'dir' => '',
            'dir_exists' => false,
            'available_files' => array(),
            'installed_files' => array(),
        );
    }

    protected static function _get_default_plugin_info_definition()
    {
        return array(
            'is_core' => false,
            'is_distribution' => false,
            'is_installed' => false,
            'is_active' => false,
            'is_upgradable' => false,
            'dir_name' => '',
            'name' => '',
            'version' => '',
            'db_version' => '',
            'vendor_id' => '',
            'vendor_name' => '',
            'models_count' => 0,
            'models' => array(),
            'agent_jobs' => array(),

            // Tests details
            'behat' => false,
            'phpunit' => false,
        );
    }

    /**
     * @param string $plugin_name Plugin name
     * @param bool|array $feature_files Optionally install only provided feature files
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj Plugin instance (if available)
     *
     * @return bool|array
     */
    private function _install_behat_features_for_plugin( $plugin_name, $feature_files = false, $plugin_obj = false )
    {
        $this->reset_error();

        if( empty( $feature_files ) or !is_array( $feature_files ) )
            $feature_files = array();

        if( !($plugin_name = PHS_Plugin::safe_escape_plugin_name( $plugin_name ))
         or !($base_dir = $this->get_behat_features_dir( true )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Plugin name is not a safe name or cannot obtain base directory when installing behat feature files.' ) );
            return false;
        }

        $return_arr = array();
        $return_arr['existing'] = 0;
        $return_arr['installed'] = 0;
        $return_arr['failed'] = 0;
        $return_arr['deleted'] = 0;

        if( !($available_features = $this->_get_behat_available_features_for_plugin( $plugin_name, $plugin_obj )) )
        {
            if( $available_features === false )
                return false;

            return $return_arr;
        }

        $behat_install_dir = $base_dir.$plugin_name;

        if( !@is_dir( $behat_install_dir ) )
        {
            if( !PHS_utils::mkdir_tree( $behat_install_dir )
             or !@is_writable( $behat_install_dir ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating Behat features directory for plugin %s.', $plugin_name ) );
                return false;
            }
        }

        if( !($installed_features = $this->_get_behat_installed_features_for_plugin( $plugin_name )) )
            $installed_features = array();

        $installed_base_names = array();
        foreach( $installed_features as $file_path )
        {
            if( !($feature_name = @basename( $file_path )) )
                continue;

            $return_arr['existing']++;

            $installed_base_names[$feature_name] = $file_path;
        }

        $failed_str = $this->cli_color( self::_t( 'FAILED' ), 'red' );
        $success_str = $this->cli_color( self::_t( 'SUCCESS' ), 'green' );

        // Actual install...
        foreach( $available_features as $file_path )
        {
            if( !($feature_file = PHS_utils::mypathinfo( $file_path ))
             or (!empty( $feature_files )
                    and !@in_array( $feature_file['filename'], $feature_files, true )
                    and !@in_array( $feature_file['basename'], $feature_files, true )
                ) )
                continue;

            // Feature file already installed...
            if( !empty( $installed_base_names[$feature_file['basename']] ) )
            {
                unset( $installed_base_names[$feature_file['basename']] );
                continue;
            }

            $install_str = self::_t( 'Installing %s feature file... ', $feature_file['filename'] );

            if( !@symlink( $file_path, $behat_install_dir.'/'.$feature_file['filename'] ) )
            {
                $install_str .= $failed_str;
                $return_arr['failed']++;
            } else
            {
                $install_str .= $success_str;
                $return_arr['installed']++;
            }

            $this->_echo( $install_str );
        }

        // Delete old installed feature files only if not provided specific feature files to be installed
        if( empty( $feature_files )
        and !empty( $installed_base_names ) )
        {
            foreach( $installed_base_names as $base_name => $file_path )
            {
                $feature_file = @basename( $file_path );
                $delete_str = self::_t( 'Deleting %s feature file (OLD file)... ', $feature_file );

                if( @unlink( $file_path ) )
                {
                    $delete_str .= $success_str;
                    $return_arr['deleted']++;
                } else
                    $delete_str .= $failed_str;

                $this->_echo( $delete_str );
            }
        }

        return $return_arr;
    }

    /**
     * @param string $plugin_name Plugin name
     * @param bool|array $feature_files Optionally install only provided feature files
     * @param bool|\phs\libraries\PHS_Plugin $plugin_obj Plugin instance (if available)
     *
     * @return bool|array
     */
    private function _uninstall_behat_features_for_plugin( $plugin_name, $feature_files = false, $plugin_obj = false )
    {
        $this->reset_error();

        if( empty( $feature_files ) or !is_array( $feature_files ) )
            $feature_files = array();

        if( !($plugin_name = PHS_Plugin::safe_escape_plugin_name( $plugin_name ))
         or !($base_dir = $this->get_behat_features_dir( true )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Plugin name is not a safe name or cannot obtain base directory when installing behat feature files.' ) );
            return false;
        }

        $return_arr = array();
        $return_arr['existing'] = 0;
        $return_arr['uninstalled'] = 0;
        $return_arr['failed'] = 0;

        $behat_install_dir = $base_dir.$plugin_name;

        if( !@is_dir( $behat_install_dir )
        or !@is_writable( $behat_install_dir ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Behat features directory for plugin %s doesn\'t exist.', $plugin_name ) );
            return false;
        }

        if( !($installed_features = $this->_get_behat_installed_features_for_plugin( $plugin_name )) )
            $installed_features = array();

        $return_arr['existing'] = count( $installed_features );

        $failed_str = $this->cli_color( self::_t( 'FAILED' ), 'red' );
        $success_str = $this->cli_color( self::_t( 'SUCCESS' ), 'green' );

        foreach( $installed_features as $file_path )
        {
            if( !($feature_file = PHS_utils::mypathinfo( $file_path ))
             or (!empty( $feature_files )
                    and !@in_array( $feature_file['filename'], $feature_files, true )
                    and !@in_array( $feature_file['basename'], $feature_files, true )
                 ) )
                continue;

            $delete_str = self::_t( 'Deleting %s feature file... ', $feature_file['filename'] );

            if( @unlink( $file_path ) )
            {
                $delete_str .= $success_str;
                $return_arr['uninstalled']++;
            } else
            {
                $delete_str .= $failed_str;
                $return_arr['failed']++;
            }

            $this->_echo( $delete_str );
        }

        PHS_utils::rmdir_tree( $behat_install_dir, array( 'only_if_empty' => true ) );

        return $return_arr;
    }

    /**
     * @param string $plugin_name
     * @return array
     */
    private function _get_behat_installed_features_for_plugin( $plugin_name )
    {
        if( !($plugin_name = PHS_Plugin::safe_escape_plugin_name( $plugin_name ))
         or !($base_dir = $this->get_behat_features_dir( true ))
         or !@is_dir( $base_dir.$plugin_name )
         or !@is_readable( $base_dir.$plugin_name )
         or !($files_arr = @glob( $base_dir.$plugin_name.'/*.feature' ))
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

        if( !($features_dir = $plugin_obj->instance_plugin_features_path())
         or !($unslashed_dir = rtrim( $features_dir, '/' ))
         or !@is_dir( $unslashed_dir )
         or !@is_readable( $unslashed_dir )
         or !($files_arr = @glob( $features_dir.'*.feature' ))
         or !is_array( $files_arr ) )
            return array();

        return $files_arr;
    }

    private function _gather_plugin_test_info( $plugin_name )
    {
        $plugin_info = self::_get_default_plugin_info_definition();

        $plugin_info['dir_name'] = $plugin_name;

        // If plugin has a JOSN available, try getting as much data from it
        if( ($json_arr = PHS::get_plugin_json_info( $plugin_name )) )
        {
            if( !empty( $json_arr['name'] ) )
                $plugin_info['name'] = $json_arr['name'];
            if( !empty( $json_arr['version'] ) )
                $plugin_info['version'] = $json_arr['version'];
            if( !empty( $json_arr['vendor_id'] ) )
                $plugin_info['vendor_id'] = $json_arr['vendor_id'];
            if( !empty( $json_arr['vendor_name'] ) )
                $plugin_info['vendor_name'] = $json_arr['vendor_name'];
            if( !empty( $json_arr['agent_jobs'] ) and is_array( $json_arr['agent_jobs'] ) )
                $plugin_info['agent_jobs'] = $json_arr['agent_jobs'];
            if( !empty( $json_arr['models'] ) and is_array( $json_arr['models'] ) )
            {
                $plugin_info['models_count'] = count( $json_arr['models'] );
                foreach( $json_arr['models'] as $model_name )
                {
                    $new_model = self::_get_default_model_info_definition();
                    $new_model['name'] = $model_name;

                    $plugin_info['models'][] = $new_model;
                }
            }
        }

        // Try to instantiate plugin...
        /** @var \phs\libraries\PHS_Plugin $plugin_obj */
        if( !($plugin_obj = PHS::load_plugin( $plugin_name )) )
        {
            PHS::st_reset_error();
            return $plugin_info;
        }

        if( ($instance_info = $plugin_obj->get_plugin_info()) )
        {
            $plugin_info['is_core'] = $instance_info['is_core'];
            $plugin_info['is_distribution'] = $instance_info['is_distribution'];
            $plugin_info['is_installed'] = $instance_info['is_installed'];
            $plugin_info['is_active'] = $instance_info['is_active'];
            $plugin_info['is_upgradable'] = $instance_info['is_upgradable'];
            $plugin_info['db_version'] = $instance_info['db_version'];
        }

        // Get model details...
        if( empty( $plugin_info['models'] ) or !is_array( $plugin_info['models'] ) )
        {
            $plugin_info['models'] = array();
            if( ($models_arr = $plugin_obj->get_models()) )
            {
                $plugin_info['models_count'] = count( $models_arr );
                foreach( $models_arr as $model_name )
                {
                    $new_model = self::_get_default_model_info_definition();
                    $new_model['name'] = $model_name;

                    $plugin_info['models'][] = $new_model;
                }
            }
        }

        if( !empty( $plugin_info['models'] ) and is_array( $plugin_info['models'] ) )
        {
            $new_models = array();
            foreach( $plugin_info['models'] as $model_arr )
            {
                /** @var \phs\libraries\PHS_Model $model_obj */
                if( empty( $model_arr['name'] )
                 or !($model_obj = PHS::load_model( $model_arr['name'], $plugin_name )) )
                {
                    // make sure we don't propagate error because model initialization failed
                    PHS::st_reset_error();
                    continue;
                }

                $model_arr['driver'] = $model_obj->get_model_driver();
                $model_arr['version'] = $model_obj->get_model_version();
                $model_arr['main_table'] = $model_obj->get_main_table_name();
                $model_arr['tables'] = $model_obj->get_table_names();

                $new_models[] = $model_arr;
            }

            $plugin_info['models'] = $new_models;
        }
        // END Get model details...

        // Check Behat features directory...
        if( ($features_dir = $plugin_obj->instance_plugin_features_path())
        and ($unslashed_dir = rtrim( $features_dir, '/' )) )
        {
            $behat_stats = self::_get_default_behat_plugin_stats();

            $behat_stats['dir'] = $features_dir;

            if( @is_dir( $unslashed_dir )
            and @is_readable( $unslashed_dir ) )
            {
                $behat_stats['dir_exists'] = true;

                if( !($behat_stats['available_files'] = $this->_get_behat_available_features_for_plugin( $plugin_name, $plugin_obj )) )
                    $behat_stats['available_files'] = array();

                if( !($behat_stats['installed_files'] = $this->_get_behat_installed_features_for_plugin( $plugin_name )) )
                    $behat_stats['installed_files'] = array();
            }

            $plugin_info['behat'] = $behat_stats;
        }
        // END Check Behat features directory...

        return $plugin_info;
    }

    public function get_plugins_as_dirs()
    {
        if( !$this->_load_dependencies() )
            return false;

        $plugins_model = $this->_plugins_model;

        if( ($plugins_arr = $plugins_model->get_all_plugin_names_from_dir()) === false
         or !is_array( $plugins_arr ) )
        {
            if( !$plugins_model->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error obtaining plugins list.' ) );
            else
                $this->copy_error( $plugins_model );

            return false;
        }

        return $plugins_arr;
    }

    public function init_tests_environment()
    {
        $this->reset_error();

        return $this->_init_app();
    }

    protected function _load_dependencies()
    {
        if( empty( $this->_plugins_model )
        and !($this->_plugins_model = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        return true;
    }
}
