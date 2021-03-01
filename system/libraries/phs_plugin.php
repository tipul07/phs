<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Maintenance;
use \phs\PHS_Agent;
use \phs\system\core\models\PHS_Model_Plugins;
use \phs\system\core\views\PHS_View;
use \phs\libraries\PHS_Roles;

abstract class PHS_Plugin extends PHS_Has_db_registry
{
    const ERR_MODEL = 50000, ERR_INSTALL = 50001, ERR_UPDATE = 50002, ERR_UNINSTALL = 50003, ERR_CHANGES = 50004, ERR_LIBRARY = 50005, ERR_RENDER = 50006,
          ERR_ACTIVATE = 50007, ERR_INACTIVATE = 50008;

    const SIGNAL_INSTALL = 'phs_plugin_install', SIGNAL_UNINSTALL = 'phs_plugin_uninstall',
          SIGNAL_UPDATE = 'phs_plugin_update', SIGNAL_FORCE_INSTALL = 'phs_plugin_force_install';

    const LIBRARIES_DIR = 'libraries';

    private $_libraries_instances = [];
    // Plugin details as defined in default_plugin_details_fields() method
    private $_plugin_details = [];
    // Plugin details as defined in JSON file
    /** @var bool|array $_plugin_json_details */
    private $_plugin_json_details = false;

    private $_custom_lang_files_included = false;

    final public function instance_type()
    {
        return self::INSTANCE_TYPE_PLUGIN;
    }

    public function __construct( $instance_details )
    {
        parent::__construct( $instance_details );
    }

    /**
     * Plugin details will be obtained from JSON file starting with version 1.1.0.0
     * This method should be used in special cases (eg. plugin with dynamic models)
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return [];
    }

    /**
     * @return array An array of strings which are the models used by this plugin
     */
    public function get_models()
    {
        if( !($json_arr = $this->get_plugin_json_info())
         || empty( $json_arr['models'] ) )
            return [];

        return $json_arr['models'];
    }

    /**
     * @return string Returns version of plugin
     */
    public function get_plugin_version()
    {
        if( !($json_arr = $this->get_plugin_json_info())
         || empty( $json_arr['version'] ) )
            return '0.0.0';

        return $json_arr['version'];
    }

    /**
     * If your plugin must define custom roles, overwrite this method to provide roles and roles units to be defined
     *
     * eg.
     *
     * return [
     *   '{role_slug}' => [
     *      'name' => 'Role name',
     *      'description' => 'Role description...',
     *      'role_units' => [
     *          '{role_unit_slug1}' => [
     *              'name' => 'Role unit name',
     *              'description' => 'Role unit description...',
     *          ],
     *          '{role_unit_slug2}' => [
     *              'name' => 'Role unit name',
     *              'description' => 'Role unit description...',
     *          ],
     *          ...
     *      ],
     *   ],
     *   ...
     * ];
     *
     * @return array Array of roles definition
     */
    public function get_roles_definition()
    {
        return [];
    }

    /**
     * If you need agent jobs defined, define an array in JSON plugin file
     * Overwriting this method to provide agent jobs definition is DEPRECATED
     * This function will get final in future release
     *
     * Handler should be unique!
     *
     * eg.
     *
     * return [
     *   '{handler}' => [
     *      'route' => [
     *          'p' or 'plugin' => 'plugin_slug',
     *          'c' or 'controller' => 'controller_slug',
     *          'a' or 'action' => 'action_slug',
     *          'ad' or 'action_dir' => 'action_directories',
     *      ],
     *      'params' => false|[ 'param1' => 'value1', 'param2' => 'value2', ... ], // any required parameters
     *      'run_async' => 1, // tells if job should run in parallel with agent_bg script or agent_bg script should
     *      'timed_seconds' => 3600, // interval in seconds. Once how many seconds should this route be executed
     *   ],
     *   ...
     * ];
     *
     * @return array Array of roles definition
     */
    public function get_agent_jobs_definition()
    {
        if( !($json_arr = $this->get_plugin_json_info())
         || empty( $json_arr['agent_jobs'] )
         || !is_array( $json_arr['agent_jobs'] ) )
            return [];

        return $json_arr['agent_jobs'];
    }

    final public function quick_init_view_instance( $template, $template_data = false )
    {
        $this->reset_error();

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = $template_data;

        if( is_string( $template ) )
            $template = $this->template_resource_from_file( $template );

        elseif( is_array( $template ) )
        {
            if( !($template = PHS_View::validate_template_resource( $template )) )
            {
                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_RENDER );
                else
                    $this->set_error( self::ERR_RENDER, $this->_pt( 'Error validating template resource.' ) );

                return false;
            }

            $path_key = PHS::relative_path( $this->instance_plugin_templates_path() );
            if( empty( $template['extra_paths'] ) || !is_array( $template['extra_paths'] )
             || !in_array( $path_key, $template['extra_paths'], true ) )
            {
                $template['extra_paths'][] = [ $path_key => PHS::relative_url( $this->instance_plugin_templates_www() ) ];
            }
        }

        if( !($view_obj = PHS_View::init_view( $template, $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        return $view_obj;
    }

    final public function quick_render_template_for_buffer( $template, $template_data = false )
    {
        $this->reset_error();

        if( !($view_obj = $this->quick_init_view_instance( $template, $template_data )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_RENDER, self::_t( 'Instantiating view from plugin.' ) );

            return false;
        }

        if( ($buffer = $view_obj->render()) === false )
        {
            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, self::_t( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        if( empty( $buffer ) )
            $buffer = '';

        return $buffer;
    }

    public function include_plugin_language_files()
    {
        if( $this->_custom_lang_files_included
         || !($current_language = self::get_current_language()) )
            return;

        $this->_custom_lang_files_included = true;

        $languages_dir = $this->instance_plugin_languages_path();

        if( !@is_dir( rtrim( $languages_dir, '/\\' ) ) )
            return;

        self::scan_for_language_files( $languages_dir );
    }

    /**
     * @param bool $slash_ended
     * @return bool|string
     */
    final public function get_plugin_libraries_www( $slash_ended = true )
    {
        if( $this->instance_is_core()
         || !($prefix = $this->instance_plugin_www()) )
            return false;

        return $prefix.self::LIBRARIES_DIR.($slash_ended?'/':'');
    }

    /**
     * @param bool $slash_ended
     * @return bool|string
     */
    final public function get_plugin_libraries_path( $slash_ended = true )
    {
        if( $this->instance_is_core()
         || !($prefix = $this->instance_plugin_path()) )
            return false;

        return $prefix.self::LIBRARIES_DIR.($slash_ended?'/':'');
    }

    /**
     * @param string $library
     * @param bool|array $params
     *
     * @return bool|string
     */
    public function get_library_full_path( $library, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['path_in_lib_dir'] ) )
            $params['path_in_lib_dir'] = '';
        else
            $params['path_in_lib_dir'] = trim( str_replace( '.', '', $params['path_in_lib_dir'] ), '\\/' ).'/';

        $library = self::safe_escape_library_name( $library );
        if( empty( $library )
         || !($dir_path = $this->get_plugin_libraries_path( false ))
         || !@is_dir( $dir_path.(!empty( $params['path_in_lib_dir'] )?'/'.$params['path_in_lib_dir']:'') )
         || !@file_exists( $dir_path.'/'.$params['path_in_lib_dir'].$library.'.php' ) )
            return false;

        return $dir_path.'/'.$params['path_in_lib_dir'].$library.'.php';
    }

    /**
     * @param string $library
     * @param bool|array $params
     *
     * @return bool|string
     */
    public function get_library_full_www( $library, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['path_in_lib_dir'] ) )
            $params['path_in_lib_dir'] = '';
        else
            $params['path_in_lib_dir'] = trim( str_replace( '.', '', $params['path_in_lib_dir'] ), '\\/' ).'/';

        $library = self::safe_escape_library_name( $library );
        if( empty( $library )
         || !($dir_path = $this->get_plugin_libraries_path( false ))
         || !@is_dir( $dir_path.(!empty( $params['path_in_lib_dir'] )?'/'.$params['path_in_lib_dir']:'') )
         || !@file_exists( $dir_path.'/'.$params['path_in_lib_dir'].$library.'.php' ) )
            return false;

        return $this->get_plugin_libraries_www( true ).$params['path_in_lib_dir'].$library.'.php';
    }

    /**
     * @param string $library
     * @param bool|array $params
     *
     * @return bool|mixed|PHS_Library
     */
    public function load_library( $library, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        // We assume $library represents class name without namespace (otherwise it won't be a valid library name)
        // so class name is from "root" namespace
        if( empty( $params['full_class_name'] ) )
            $params['full_class_name'] = '\\'.ltrim( $library, '\\' );
        if( empty( $params['init_params'] ) )
            $params['init_params'] = false;
        if( empty( $params['as_singleton'] ) )
            $params['as_singleton'] = true;
        if( empty( $params['path_in_lib_dir'] ) )
            $params['path_in_lib_dir'] = '';

        if( !($library = self::safe_escape_library_name( $library )) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load library from plugin [%s]', $this->instance_plugin_name() ) );
            return false;
        }

        if( !empty( $params['as_singleton'] )
         && !empty( $this->_libraries_instances[$library] ) )
            return $this->_libraries_instances[$library];

        if( !($file_path = $this->get_library_full_path( $library, [ 'path_in_lib_dir' => $params['path_in_lib_dir'] ] )) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t load library [%s] from plugin [%s]', $library, $this->instance_plugin_name() ) );
            return false;
        }

        ob_start();
        include_once( $file_path );
        ob_get_clean();

        if( !@class_exists( $params['full_class_name'], false ) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Couldn\'t instantiate library class for library [%s] from plugin [%s]', $library, $this->instance_plugin_name() ) );
            return false;
        }

        /** @var \phs\libraries\PHS_Library $library_instance */
        if( empty( $params['init_params'] ) )
            $library_instance = new $params['full_class_name']();
        else
            $library_instance = new $params['full_class_name']( $params['init_params'] );

        if( !($library_instance instanceof PHS_Library) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Library [%s] from plugin [%s] is not a PHS library.', $library, $this->instance_plugin_name() ) );
            return false;
        }

        if( !$library_instance->parent_plugin( $this ) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Library [%s] from plugin [%s] couldn\'t set plugin parent.', $library, $this->instance_plugin_name() ) );
            return false;
        }

        $location_details = $library_instance::get_library_default_location_paths();
        $location_details['library_file'] = $file_path;
        $location_details['library_path'] = @dirname( $file_path );
        $location_details['library_www'] = @dirname( $this->get_library_full_www( $library, [ 'path_in_lib_dir' => $params['path_in_lib_dir'] ] ) );

        if( !$library_instance->set_library_location_paths( $location_details ) )
        {
            $this->set_error( self::ERR_LIBRARY, self::_t( 'Library [%s] from plugin [%s] couldn\'t set location paths.', $library, $this->instance_plugin_name() ) );
            return false;
        }

        if( !empty( $params['as_singleton'] ) )
            $this->_libraries_instances[$library] = $library_instance;

        return $library_instance;
    }

    public function email_template_resource_from_file( $file )
    {
        if( !($init_arr = $this->instance_plugin_themes_email_templates_pairs()) )
            $init_arr = [];

        $template_arr = [
            'file' => $file,
            'extra_paths' => $init_arr,
        ];

        if( ($plugin_path = $this->instance_plugin_email_templates_path())
         && ($www_path = $this->instance_plugin_email_templates_www()) )
            $template_arr['extra_paths'][PHS::relative_path( $plugin_path )] = PHS::relative_url( $www_path );

        return $template_arr;
    }

    public function template_resource_from_file( $file )
    {
        return [
            'file' => $file,
            'extra_paths' => [
                PHS::relative_path( $this->instance_plugin_templates_path() ) => PHS::relative_url( $this->instance_plugin_templates_www() ),
            ],
        ];
    }

    public function plugin_active()
    {
        return ($this->db_record_active()?true:false);
    }

    public function check_installation()
    {
        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Checking installation...' );

        if( !$this->install_roles()
         || !$this->install_agent_jobs() )
            return false;

        if( !($db_details = $this->get_db_details()) )
        {
            $this->reset_error();

            return $this->install();
        }

        if( version_compare( $db_details['version'], $this->get_plugin_version(), '!=' ) )
            return $this->update( $db_details['version'], $this->get_plugin_version() );

        // Check if plugin has dynamic structure models
        elseif( ($models_arr = $this->get_models())
             && is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_UPDATE );
                    else
                        $this->set_error( self::ERR_UPDATE, self::_t( 'Error updating model %s.', $model_name ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Error loading plugin model ['.$model_obj->instance_id().']: '.$this->get_error_message() );

                    return false;
                }

                if( $model_obj->dynamic_table_structure() )
                    return $this->update( $db_details['version'], $this->get_plugin_version() );
            }
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] All ok' );

        return true;
    }

    final public function activate_plugin()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_CHANGES, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Activating plugin...' );

        if( !$this->_load_plugins_instance() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error instantiating plugins model.' );

            $this->set_error( self::ERR_CHANGES, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['instance_id'] = $this_instance_id;

        $check_params = [];
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if( !($plugin_arr = $this->_plugins_instance->get_details_fields( $check_arr, $check_params ))
         || (string)$plugin_arr['type'] !== self::INSTANCE_TYPE_PLUGIN )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Plugin not found in database.' );

            $this->set_error( self::ERR_CHANGES, self::_t( 'Plugin not found in database.' ) );
            return false;
        }

        if( $this->_plugins_instance->is_active( $plugin_arr ) )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Plugin already active.' );
            return $plugin_arr;
        }

        if( !$this->unsuspend_agent_jobs() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error re-activating agent jobs.' );
            return false;
        }

        $list_arr = [];
        $list_arr['fields']['plugin'] = $plugin_arr['plugin'];

        if( ($plugins_modules_arr = $this->_plugins_instance->get_list( $list_arr ))
         && is_array( $plugins_modules_arr ) )
        {
            $edit_params_arr = [];
            $edit_params_arr['fields'] = [
                'status' => PHS_Model_Plugins::STATUS_ACTIVE,
            ];

            foreach( $plugins_modules_arr as $module_id => $module_arr )
            {
                if( (int)$module_arr['status'] === PHS_Model_Plugins::STATUS_ACTIVE )
                    continue;

                if( !$this->_plugins_instance->edit( $module_arr, $edit_params_arr ) )
                {
                    if( $this->_plugins_instance->has_error() )
                        $this->copy_error( $this->_plugins_instance, self::ERR_CHANGES );
                    else
                        $this->set_error( self::ERR_CHANGES, self::_t( 'Error activating %s %s.', $module_arr['type'], $module_arr['instance_id'] ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error activating database record ['.$module_arr['instance_id'].']' );

                    return false;
                }
            }
        }

        $plugin_details = [];
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_ACTIVE;

        if( !($db_details = $this->_plugins_instance->update_db_details( $plugin_details, $this->get_all_settings_keys_to_obfuscate() ))
         || empty( $db_details['new_data'] ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_CHANGES, self::_t( 'Error activating plugin.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error activating database record ['.$this_instance_id.']' );

            return false;
        }

        $plugin_arr = $db_details['new_data'];

        if( !$this->custom_activate( $plugin_arr ) )
        {
            if( !$this->has_warnings( 'plugin_activation_'.$plugin_arr['plugin'] ) )
            {
                if( $this->has_error() )
                    $warning_msg = $this->get_error_message();
                else
                    $warning_msg = self::_t( 'Plugin custom activation functionality failed.' );

                $this->add_warning( $warning_msg, 'plugin_activation_'.$plugin_arr['plugin'] );
                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom activate functionality: '.$warning_msg );
            }
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Plugin activated.' );

        return $plugin_arr;
    }

    final public function inactivate_plugin()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_CHANGES, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Inactivating plugin...' );

        if( !$this->_load_plugins_instance() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error instantiating plugins model.' );

            $this->set_error( self::ERR_CHANGES, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['instance_id'] = $this_instance_id;

        $check_params = [];
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if( !($plugin_arr = $this->_plugins_instance->get_details_fields( $check_arr, $check_params ))
         || (string)$plugin_arr['type'] !== self::INSTANCE_TYPE_PLUGIN )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Plugin not found in database.' );

            $this->set_error( self::ERR_CHANGES, self::_t( 'Plugin not found in database.' ) );
            return false;
        }

        if( $this->_plugins_instance->is_inactive( $plugin_arr ) )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Plugin already inactive.' );
            return $plugin_arr;
        }

        if( !$this->suspend_agent_jobs() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error suspending agent jobs.' );
            return false;
        }

        if( !$this->custom_inactivate( $plugin_arr ) )
        {
            if( !$this->has_warnings( 'plugin_inactivation_'.$plugin_arr['plugin'] ) )
            {
                if( $this->has_error() )
                    $warning_msg = $this->get_error_message();
                else
                    $warning_msg = self::_t( 'Plugin custom inactivation functionality failed.' );

                $this->add_warning( $warning_msg, 'plugin_inactivation_'.$plugin_arr['plugin'] );
                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom inactivate functionality: '.$warning_msg );
            }
        }

        $list_arr = [];
        $list_arr['fields']['instance_id'] = [ 'check' => '!=', 'value' => $this_instance_id ];
        $list_arr['fields']['plugin'] = $plugin_arr['plugin'];

        if( ($plugins_modules_arr = $this->_plugins_instance->get_list( $list_arr ))
         && is_array( $plugins_modules_arr ) )
        {
            $edit_params_arr = [];
            $edit_params_arr['fields'] = [
                'status' => PHS_Model_Plugins::STATUS_INACTIVE,
            ];

            foreach( $plugins_modules_arr as $module_id => $module_arr )
            {
                if( (int)$module_arr['status'] === PHS_Model_Plugins::STATUS_INACTIVE )
                    continue;

                if( !$this->_plugins_instance->edit( $module_arr, $edit_params_arr ) )
                {
                    if( $this->_plugins_instance->has_error() )
                        $this->copy_error( $this->_plugins_instance, self::ERR_CHANGES );
                    else
                        $this->set_error( self::ERR_CHANGES, self::_t( 'Error inactivating %s %s.', $module_arr['type'], $module_arr['instance_id'] ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error inactivating database record ['.$module_arr['instance_id'].']' );

                    return false;
                }
            }
        }

        $plugin_details = [];
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_INACTIVE;

        if( !($db_details = $this->_plugins_instance->update_db_details( $plugin_details, $this->get_all_settings_keys_to_obfuscate() ))
         || empty( $db_details['new_data'] ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_CHANGES, self::_t( 'Error inactivating plugin.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error inactivating database record ['.$this_instance_id.']' );

            return false;
        }

        $plugin_arr = $db_details['new_data'];

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Plugin inactivated.' );

        return $plugin_arr;
    }

    public static function role_unit_structure()
    {
        return [
            'name' => '',
            'description' => '',
        ];
    }

    public static function role_structure()
    {
        return [
            'name' => '',
            'description' => '',
            'role_units' => [],
        ];
    }

    public static function agent_job_structure()
    {
        return [
            'title' => '',
            'handler' => '',
            'route' => '',
            'params' => null,
            'run_async' => 1,
            'timed_seconds' => 0,
        ];
    }

    final public function user_has_any_of_defined_role_units()
    {
        if( !($role_definition = $this->get_roles_definition())
         || !is_array( $role_definition ) )
            return false;

        // Do slug check even if user is not logged in
        // but if we couldn't generate an empty user structure, assume no slugs are assigned
        if( !($cuser_arr = PHS::account_structure( PHS::user_logged_in() )) )
            return false;

        $role_units_arr = [];
        foreach( $role_definition as $role_slug => $role_arr )
        {
            if( empty( $role_arr['role_units'] ) || !is_array( $role_arr['role_units'] ) )
                continue;

            foreach( $role_arr['role_units'] as $role_unit_slug => $role_unit_arr )
            {
                // if we cannot validate the slug we assume this is not assigned to any role...
                if( !($role_unit_slug = PHS_Roles::transform_string_to_slug( $role_unit_slug )) )
                    return false;

                $role_units_arr[$role_unit_slug] = true;
            }
        }

        // if plugin defined no role units we assume user has assigned (nothing to be assigned) role unit... :p
        if( empty( $role_units_arr ) )
            return true;

        return PHS_Roles::user_has_role_units( $cuser_arr, array_keys( $role_units_arr ), [ 'logical_operation' => 'or' ] );
    }

    final public function install_agent_jobs()
    {
        $this->reset_error();

        if( !($agent_jobs_definition = $this->get_agent_jobs_definition())
         || !is_array( $agent_jobs_definition ) )
            return true;

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Installing agent jobs...' );

        /** @var \phs\system\core\models\PHS_Model_Agent_jobs $agent_jobs_model */
        if( !($agent_jobs_model = PHS::load_model( 'agent_jobs' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t load agent jobs model.' ) );
            return false;
        }

        $agent_job_structure = self::agent_job_structure();
        foreach( $agent_jobs_definition as $handle => $agent_job_arr )
        {
            $agent_job_arr = self::validate_array( $agent_job_arr, $agent_job_structure );

            if( !empty( $agent_job_arr['title'] ) )
                $agent_job_arr['title'] = trim( $agent_job_arr['title'] );
            else
                $agent_job_arr['title'] = '';

            if( !empty( $agent_job_arr['timed_seconds'] ) )
                $agent_job_arr['timed_seconds'] = (int)$agent_job_arr['timed_seconds'];

            // Hardcoded job to run once an hour rather than stopping install
            if( empty( $agent_job_arr['timed_seconds'] ) || $agent_job_arr['timed_seconds'] < 0 )
                $agent_job_arr['timed_seconds'] = 3600;

            if( empty( $agent_job_arr['params'] ) || !is_array( $agent_job_arr['params'] ) )
                $agent_job_arr['params'] = false;

            if( empty( $agent_job_arr['run_async'] ) )
                $agent_job_arr['run_async'] = 0;
            else
                $agent_job_arr['run_async'] = 1;

            if( empty( $agent_job_arr['route'] )
             || !is_array( $agent_job_arr['route'] ) )
            {
                $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t install agent job [%s] for plugin [%s]', $handle, $this->instance_id() ) );

                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Agent job has invalid or no route ['.$handle.']' );

                return false;
            }

            $job_extra_arr = [];
            $job_extra_arr['title'] = $agent_job_arr['title'];
            $job_extra_arr['run_async'] = $agent_job_arr['run_async'];
            $job_extra_arr['status'] = ($this->plugin_active()?$agent_jobs_model::STATUS_ACTIVE:$agent_jobs_model::STATUS_SUSPENDED);
            $job_extra_arr['plugin'] = $this->instance_plugin_name();

            if( !($role_unit = PHS_Agent::add_job( $handle, $agent_job_arr['route'], $agent_job_arr['timed_seconds'], $agent_job_arr['params'], $job_extra_arr )) )
            {
                $this->uninstall_agent_jobs();

                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_INSTALL );
                else
                    $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t install agent job [%s] for [%s]', $handle, $this->instance_id() ) );

                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error when registering agent job ['.$handle.']: '.$this->get_error_message() );

                return false;
            }
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Agent jobs installed' );

        return true;
    }

    final public function uninstall_agent_jobs()
    {
        $this->reset_error();

        if( !($agent_jobs_definition = $this->get_agent_jobs_definition())
         || !is_array( $agent_jobs_definition ) )
            return true;

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Uninstalling agent jobs...' );

        $we_have_error = false;
        foreach( $agent_jobs_definition as $handle => $agent_job_arr )
        {
            if( !PHS_Agent::remove_job_handler( $handle ) )
            {
                $we_have_error = true;

                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_INSTALL );
                else
                    $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t uninstall agent job [%s] for [%s]', $handle, $this->instance_id() ) );

                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error when uninstalling agent job ['.$handle.']: '.$this->get_error_message() );
            }
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Agent jobs uninstalled' );

        return $we_have_error;
    }

    final public function suspend_agent_jobs()
    {
        $this->reset_error();

        if( !($agent_jobs_definition = $this->get_agent_jobs_definition())
         || !is_array( $agent_jobs_definition ) )
            return true;

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Suspending agent jobs...' );

        if( !PHS_Agent::suspend_agent_jobs( $this->instance_plugin_name()) )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] FAILED Suspending agent jobs' );
            $this->copy_static_error();
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Agent jobs suspended' );

        return true;
    }

    final public function unsuspend_agent_jobs()
    {
        $this->reset_error();

        if( !($agent_jobs_definition = $this->get_agent_jobs_definition())
         || !is_array( $agent_jobs_definition ) )
            return true;

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Re-activating agent jobs...' );

        if( !PHS_Agent::unsuspend_agent_jobs( $this->instance_plugin_name()) )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] FAILED Re-activating agent jobs' );
            $this->copy_static_error();
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Agent jobs re-activated' );

        return true;
    }

    final public function install_roles()
    {
        $this->reset_error();

        if( !($role_definition = $this->get_roles_definition())
         || !is_array( $role_definition ) )
            return true;

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Installing roles...' );

        $role_structure = self::role_structure();
        $role_unit_structure = self::role_unit_structure();
        $db_roles_arr = [];
        foreach( $role_definition as $role_slug => $role_arr )
        {
            if( !($new_role_slug = PHS_Roles::transform_string_to_slug( $role_slug )) )
            {
                $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t get a correct slug for role [%s]', $role_slug ) );

                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error installing role ['.$role_slug.']: '.$this->get_error_message() );

                return false;
            }

            $role_slug = $new_role_slug;

            $role_arr = self::validate_array( $role_arr, $role_structure );
            if( empty( $role_arr['role_units'] ) || !is_array( $role_arr['role_units'] ) )
                $role_arr['role_units'] = [];

            $role_units_slugs_arr = [];
            $db_role_units_arr = [];
            foreach( $role_arr['role_units'] as $role_unit_slug => $role_unit_arr )
            {
                if( !($new_role_unit_slug = PHS_Roles::transform_string_to_slug( $role_unit_slug )) )
                {
                    $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t get a correct slug for role unit [%s]', $role_unit_slug ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error installing role unit ['.$role_unit_slug.']: '.$this->get_error_message() );

                    return false;
                }

                $role_unit_slug = $new_role_unit_slug;

                $role_unit_arr = self::validate_array( $role_unit_arr, $role_unit_structure );

                $role_unit_details_arr = [];
                $role_unit_details_arr['slug'] = $role_unit_slug;
                $role_unit_details_arr['plugin'] = $this->instance_plugin_name();
                $role_unit_details_arr['name'] = $role_unit_arr['name'];
                $role_unit_details_arr['description'] = $role_unit_arr['description'];

                if( !($role_unit = PHS_Roles::register_role_unit( $role_unit_details_arr )) )
                {
                    // TODO: in case we have error on registering role, delete all registered roles and role units for current plugin
                    if( self::st_has_error() )
                        $this->copy_static_error( self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t install role unit [%s]', $role_unit_slug ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error when registering role unit ['.$role_unit_slug.']: '.$this->get_error_message() );

                    return false;
                }

                $db_role_units_arr[$role_unit['slug']] = $role_unit;

                $role_units_slugs_arr[$role_unit['slug']] = true;
            }

            $role_details_arr = [];
            $role_details_arr['slug'] = $role_slug;
            $role_details_arr['plugin'] = $this->instance_plugin_name();
            $role_details_arr['name'] = $role_arr['name'];
            $role_details_arr['description'] = $role_arr['description'];
            $role_details_arr['predefined'] = 1;
            $role_details_arr['{role_units}'] = array_keys( $role_units_slugs_arr );

            if( !($role = PHS_Roles::register_role( $role_details_arr )) )
            {
                // TODO: in case we have error on registering role, delete all registered roles and role units for current plugin
                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_INSTALL );
                else
                    $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t install role [%s]', $role_slug ) );

                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error when registering role ['.$role_slug.']: '.$this->get_error_message() );

                return false;
            }

            $db_roles_arr[$role['slug']] = $role;
            $db_roles_arr[$role['slug']]['{role_units}'] = $db_role_units_arr;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Roles installed' );

        return $db_roles_arr;
    }

    final public function install()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Installing plugin...' );

        if( !$this->_load_plugins_instance() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error instantiating plugins model.' );

            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( !$this->_plugins_instance->check_install_plugins_db() )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing plugins model.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error installing plugins model: '.$this->get_error_message() );

            return false;
        }

        if( !$this->custom_install() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSTALL, self::_t( 'Plugin custom install functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom install functionality: '.$this->get_error_message() );

            return false;
        }

        if( ($plugin_info = $this->get_plugin_info())
         && !empty( $plugin_info['name'] ) )
            $plugin_name = $plugin_info['name'];
        else
            $plugin_name = $this->instance_plugin_name();

        $plugin_details = [];
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['plugin'] = $this->instance_plugin_name();
        $plugin_details['plugin_name'] = $plugin_name;
        $plugin_details['type'] = $this->instance_type();
        $plugin_details['is_core'] = ($this->instance_is_core() ? 1 : 0);
        $plugin_details['settings'] = PHS_Line_params::to_string( $this->get_default_settings() );
        $plugin_details['version'] = $this->get_plugin_version();

        if( !($db_details = $this->_plugins_instance->update_db_details( $plugin_details, $this->get_all_settings_keys_to_obfuscate() ))
         || empty( $db_details['new_data'] ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_INSTALL, self::_t( 'Error saving plugin details to database.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error saving plugin details to database: '.$this->get_error_message() );

            return false;
        }

        $plugin_arr = $db_details['new_data'];
        $old_plugin_arr = (!empty( $db_details['old_data'] )?$db_details['old_data']:false);

        if( !empty( $old_plugin_arr ) )
        {
            // Performs any necessary actions when updating model from old version to new version
            if( version_compare( $old_plugin_arr['version'], $plugin_arr['version'], '<' ) )
            {
                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Calling update method from version ['.$old_plugin_arr['version'].'] to version ['.$plugin_arr['version'].']' );

                // Installed version is bigger than what we already had in database... update...
                if( !$this->update( $old_plugin_arr['version'], $plugin_arr['version'] ) )
                {
                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Update failed: '.$this->get_error_message() );

                    return false;
                }

                PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Update with success!' );
            }
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Installing plugin models...' );

        if( ($models_arr = $this->get_models())
         && is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Error loading model %s.', $model_name ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Error loading model ['.$model_name.']: '.$this->get_error_message() );

                    return false;
                }

                if( !$model_obj->install() )
                {
                    if( $model_obj->has_error() )
                        $this->copy_error( $model_obj, self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing model %s.', $model_obj->instance_id() ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Error installing model ['.$model_name.']: '.$this->get_error_message() );

                    return false;
                }
            }
        }

        if( !$this->custom_after_install() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSTALL, self::_t( 'Finishing plugin installation failed. Please uninstall, then re-install the plugin.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom install finish functionality: '.$this->get_error_message() );

            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] DONE installing plugin' );

        return $plugin_arr;
    }

    final public function uninstall()
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Uninstalling plugin...' );

        if( !$this->_load_plugins_instance() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error instantiating plugins model.' );

            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['instance_id'] = $this_instance_id;

        db_supress_errors( $this->_plugins_instance->get_db_connection() );
        if( !($db_details = $this->_plugins_instance->get_details_fields( $check_arr ))
         || empty( $db_details['type'] )
         || $db_details['type'] !== self::INSTANCE_TYPE_PLUGIN )
        {
            // Set it to false so models will also get uninstall signal
            $db_details = false;

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Plugin doesn\'t seem to be installed.' );
        }

        db_restore_errors_state( $this->_plugins_instance->get_db_connection() );

        if( $db_details
         && $this->_plugins_instance->active_status( $db_details['status'] ) )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Plugin is still active. Please inactivate it first.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Plugin is still active. Please inactivate it first.' );

            return false;
        }

        if( !$this->custom_uninstall() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSTALL, self::_t( 'Plugin custom un-install functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom un-install functionality: '.$this->get_error_message() );

            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Uninstalling plugin models...' );

        if( ($models_arr = $this->get_models())
         && is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_INSTALL );
                    else
                        $this->set_error( self::ERR_INSTALL, self::_t( 'Error loading model %s.', $model_name ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Error loading model ['.$model_name.']: '.$this->get_error_message() );

                    return false;
                }

                if( !$model_obj->uninstall() )
                {
                    if( $model_obj->has_error() )
                        $this->copy_error( $model_obj );
                    else
                        $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error un-installing model %s.', $model_name ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Error un-installing model ['.$model_name.']: '.$this->get_error_message() );

                    return false;
                }
            }
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Deleting plugin records...' );

        // Logging and error is set in method...
        // we don't stop all uninstall process because of registry failure...
        $this->delete_db_registry();

        if( $db_details
         && !$this->_plugins_instance->hard_delete( $db_details ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error hard-deleting plugin from database.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error deleting plugin database record: '.$this->get_error_message() );

            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] DONE uninstalling plugin!' );

        return $db_details;
    }

    /**
     * Performs any necessary custom actions when installing plugin
     * Overwrite this method to do particular install actions.
     * If this function returns false whole install will stop and error set in this method will be used.
     *
     * @return bool true on success, false on failure
     */
    protected function custom_install()
    {
        return true;
    }

    /**
     * Performs any necessary custom actions right after installing plugin with success
     * Overwrite this method to do particular install finishing actions.
     * If this function returns false whole install will stop and error set in this method will be used.
     *
     * @return bool true on success, false on failure
     */
    protected function custom_after_install()
    {
        return true;
    }

    /**
     * Performs any necessary custom actions when un-installing plugin
     * Overwrite this method to do particular un-install actions.
     * If this function returns false whole un-install will stop and error set in this method will be used.
     *
     * @return bool true on success, false on failure
     */
    protected function custom_uninstall()
    {
        return true;
    }

    /**
     * Performs any necessary custom actions when activating plugin
     * Overwrite this method to do particular activation actions.
     * This method is called after plugin and all models in the plugin are activated so you have full access to all models.
     *
     * @return bool true on success, false on failure
     */
    protected function custom_activate( $plugin_arr )
    {
        return true;
    }

    /**
     * Performs any necessary custom actions when inactivating plugin
     * Overwrite this method to do particular inactivation actions.
     * This method is called after plugin and all models in the plugin are still activate so you have full access to all models.
     *
     * @return bool true on success, false on failure
     */
    protected function custom_inactivate( $plugin_arr )
    {
        return true;
    }

    /**
     * Performs any necessary custom actions when updating plugin from $old_version to $new_version
     * Overwrite this method to do particular updates.
     * If this function returns false whole update will stop and error set in this method will be used.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    protected function custom_update( $old_version, $new_version )
    {
        return true;
    }

    /**
     * Performs any necessary custom actions after updating plugin from $old_version to $new_version
     * Overwrite this method to do particular updates.
     * If this function returns false whole update will stop, however model database structure updates will remain.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    protected function custom_after_update( $old_version, $new_version )
    {
        return true;
    }

    /**
     * Performs any necessary actions when updating plugin from $old_version to $new_version
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    final public function update( $old_version, $new_version )
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Couldn\'t obtain plugin instance ID.' );

            $this->set_error( self::ERR_UPDATE, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] Updating plugin from ['.$old_version.'] to ['.$new_version.']...' );

        if( !$this->custom_update( $old_version, $new_version ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Plugin custom update functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom update functionality: '.$this->get_error_message() );

            return false;
        }

        if( !$this->_load_plugins_instance() )
        {
            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error instantiating plugins model.' );

            $this->set_error( self::ERR_UPDATE, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( ($models_arr = $this->get_models())
         && is_array( $models_arr ) )
        {
            foreach( $models_arr as $model_name )
            {
                if( !($model_obj = PHS::load_model( $model_name, $this->instance_plugin_name() )) )
                {
                    if( PHS::st_has_error() )
                        $this->copy_static_error( self::ERR_UPDATE );
                    else
                        $this->set_error( self::ERR_UPDATE, self::_t( 'Error loading model %s.', $model_name ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error loading plugin model ['.$model_name.']: '.$this->get_error_message() );

                    return false;
                }

                if( !($model_details = $model_obj->get_db_details( true ))
                 || empty( $model_details['version'] ) )
                    $old_model_version = '0.0.0';
                else
                    $old_model_version = $model_details['version'];

                $current_version = $model_obj->get_model_version();

                if( version_compare( $old_model_version, $current_version, '==' )
                 && !$model_obj->dynamic_table_structure() )
                {
                    PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$model_obj->instance_name().'] Same version ['.$current_version.']...' );
                    continue;
                }

                if( !$model_obj->update( $old_model_version, $current_version ) )
                {
                    if( $model_obj->has_error() )
                        $this->copy_error( $model_obj, self::ERR_UPDATE );
                    else
                        $this->set_error( self::ERR_UPDATE, self::_t( 'Error updating model [%s] from plugin [%s]', $model_obj->instance_name(), $this->instance_name() ) );

                    PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error updating plugin model ['.$model_name.']: '.$this->get_error_message() );

                    return false;
                }
            }
        }

        if( !$this->custom_after_update( $old_version, $new_version ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Plugin custom after update functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error in plugin custom update functionality: '.$this->get_error_message() );

            return false;
        }

        if( ($plugin_info = $this->get_plugin_info())
         && !empty( $plugin_info['name'] ) )
            $plugin_name = $plugin_info['name'];
        else
            $plugin_name = $this->instance_plugin_name();

        $plugin_details = [];
        $plugin_details['plugin_name'] = $plugin_name;
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['version'] = $this->get_plugin_version();

        if( !($db_details = $this->_plugins_instance->update_db_details( $plugin_details, $this->get_all_settings_keys_to_obfuscate() ))
         || empty( $db_details['new_data'] ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_UPDATE, self::_t( 'Error saving plugin details to database.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().'] !!! Error saving plugin details to database: '.$this->get_error_message() );

            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().'] DONE Updating plugin!' );

        return $db_details['new_data'];
    }

    final public static function default_plugin_details_fields()
    {
        return [
            'id' => '', // full instance id $instance_type.':'.$plugin_name.':'.$instance_name
            'plugin_name' => false, // default consider plugin as core plugin (short plugin name)
            'vendor_id' => '', // unique vendor identifier
            'vendor_name' => '', // readable vendor name
            'name' => '',
            'description' => '',
            'script_version' => '0.0.0',
            // Alias of script_version (used in JSON)
            'version' => '0.0.0',
            'db_version' => '0.0.0',
            'update_url' => '',
            'status' => 0,
            'is_installed' => false,
            'is_active' => false,
            'is_upgradable' => false,
            'is_core' => false,
            'is_always_active' => false,
            'is_distribution' => false,
            'data_from_json' => false,
            'db_details' => false,
            'models' => [],
            'settings_arr' => [],
            // Tells if plugin has any dependencies (key is plugin name and value is min version required)
            'requires' => [],
            'agent_jobs' => [],
        ];
    }

    public static function core_plugin_details_fields()
    {
        $return_arr = [
            'id' => PHS_Instantiable::CORE_PLUGIN,
            'vendor_id' => 'phs',
            'vendor_name' => 'PHS',
            'name' => self::_t( 'CORE Framework' ),
            'description' => self::_t( 'CORE functionality' ),
            'script_version' => PHS_VERSION,
            'db_version' => PHS_KNOWN_VERSION,
            'status' => PHS_Model_Plugins::STATUS_ACTIVE,
            'is_installed' => true,
            'is_upgradable' => false,
            'is_core' => true,
            'is_always_active' => true,
            'is_distribution' => true,
            'models' => PHS::get_core_models(),
        ];

        return self::validate_array_to_new_array( $return_arr, self::default_plugin_details_fields() );
    }

    /**
     * Returns plugin information as described in plugin JSON file (if available) as array or false in case there is no JSON file
     * @return array
     */
    final public function get_plugin_json_info()
    {
        if( $this->_plugin_json_details !== false )
            return $this->_plugin_json_details;

        if( !($plugin_name = $this->instance_plugin_name())
         || !($json_arr = PHS::get_plugin_json_info( $plugin_name )) )
            $json_arr = [];

        $this->_plugin_json_details = self::validate_array_to_new_array( $json_arr, self::default_plugin_details_fields() );

        return $this->_plugin_json_details;
    }

    final public function get_plugin_info()
    {
        if( !empty( $this->_plugin_details ) )
            return $this->_plugin_details;

        if( !$this->_load_plugins_instance() )
            return false;

        $plugin_details = self::validate_array( $this->get_plugin_details(), self::default_plugin_details_fields() );
        if( ($json_info = $this->get_plugin_json_info())
         && !empty( $json_info['data_from_json'] ) )
            $plugin_details = self::merge_array_assoc( $plugin_details, $json_info );

        $plugin_details['id'] = $this->instance_id();
        $plugin_details['plugin_name'] = $this->instance_plugin_name();

        if( empty( $plugin_details['name'] ) )
            $plugin_details['name'] = $this->instance_name();

        $plugin_details['script_version'] = $this->get_plugin_version();
        $plugin_details['models'] = $this->get_models();

        if( ($db_details = $this->get_db_details()) )
        {
            $plugin_details['db_details'] = $db_details;
            $plugin_details['is_installed'] = true;
            $plugin_details['is_active'] = ($this->_plugins_instance->is_active( $db_details )?true:false);
            $plugin_details['db_version'] = (!empty( $db_details['version'] )?$db_details['version']:'0.0.0');
            $plugin_details['is_upgradable'] = ((string)$plugin_details['db_version'] !== (string)$plugin_details['script_version']);
            $plugin_details['is_core'] = (!empty( $db_details['is_core'] )?true:false);
        }

        $plugin_details['is_always_active'] = in_array( $plugin_details['plugin_name'], PHS::get_always_active_plugins(), true );
        $plugin_details['is_distribution'] = in_array( $plugin_details['plugin_name'], PHS::get_distribution_plugins(), true );

        $plugin_details['settings_arr'] = $this->get_plugin_settings();

        $this->_plugin_details = $plugin_details;

        return $this->_plugin_details;
    }

}
