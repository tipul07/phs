<?php
namespace phs\system\core\views;

use \phs\PHS;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Signal_and_slot;
use \phs\libraries\PHS_Controller;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Hooks;

class PHS_View extends PHS_Signal_and_slot
{
    const ERR_BAD_CONTROLLER = 40000, ERR_BAD_ACTION = 40001, ERR_BAD_TEMPLATE = 40002, ERR_BAD_THEME = 40003, ERR_TEMPLATE_DIRS = 40004, ERR_INIT_VIEW = 40005;

    const VIEW_CONTEXT_DATA_KEY = 'phs_view_context';

    const THEMES_PLUGINS_TEMPLATES_DIR = 'plugins';

    protected $_template = '';
    protected $_theme = '';
    // Array of directories where we check if template exists
    protected $_template_dirs = array();
    // Array of directories where we check if template exists (others than the ones we detect)
    protected $_extra_template_dirs = array();

    // Resulting template file
    protected $_template_file = '';

    /** @var PHS_Controller|bool */
    protected $_controller = false;

    /** @var PHS_Action|bool */
    protected $_action = false;

    /** @var PHS_View|bool */
    protected $_parent_view = false;

    public function instance_type()
    {
        return self::INSTANCE_TYPE_VIEW;
    }

    protected function reset_view()
    {
        $this->_template = '';
        $this->_theme = '';
        $this->_template_dirs = array();
        $this->_template_file = '';
    }

    function __clone()
    {
        $this->reset_view();
    }

    public static function default_template_resource_arr()
    {
        return array(
            'file' => '',
            'extra_paths' => array(),
            'resource_validated' => false,
        );
    }

    /**
     * @param string|array $template
     * @param bool|array $params
     *
     * @return array|bool
     */
    public static function validate_template_resource( $template, $params = false )
    {
        if( empty( $template )
         or (!is_string( $template ) and !is_array( $template )) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['theme_relative_dirs'] ) or !is_array( $params['theme_relative_dirs'] ) )
            $params['theme_relative_dirs'] = array();

        if( !empty( $params['theme'] ) )
        {
            if( !($validated_theme = PHS::valid_theme( $params['theme'] )) )
            {
                self::st_set_error( self::ERR_BAD_THEME, self::_t( 'Invalid theme passed to template.' ) );
                return false;
            }

            $params['theme'] = $validated_theme;
        } else
            $params['theme'] = PHS::get_theme();

        $template_structure = self::default_template_resource_arr();
        if( is_string( $template ) )
            $template_structure['file'] = $template;

        elseif( is_array( $template ) )
        {
            if( empty( $template['file'] ) )
                return false;

            if( empty( $template['extra_paths'] ) or !is_array( $template['extra_paths'] ) )
                $extra_paths = array();

            else
            {
                $extra_paths = array();
                foreach( $template['extra_paths'] as $dir_path => $dir_www )
                {
                    $full_path = rtrim( PHS::from_relative_path( $dir_path ), '/\\' );
                    $full_www = rtrim( PHS::from_relative_url( $dir_www ), '/' );

                    $extra_paths[$full_path.'/'] = $full_www.'/';
                }
            }

            $template_structure['file'] = $template['file'];
            $template_structure['extra_paths'] = $extra_paths;
        }

        if( !empty( $params['theme_relative_dirs'] ) )
        {
            foreach( $params['theme_relative_dirs'] as $theme_dir )
            {
                if( ($extra_dirs = self::st_add_extra_theme_dir( $theme_dir, $params['theme'] )) )
                {
                    foreach( $extra_dirs as $dir_path => $dir_www )
                    {
                        $template_structure['extra_paths'][$dir_path] = $dir_www;
                    }
                }
            }
        }

        $template_structure['resource_validated'] = true;

        return $template_structure;
    }

    public static function quick_render_template( $template, $plugin = false, $template_data = false )
    {
        self::st_reset_error();

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['plugin'] = $plugin;
        $view_params['template_data'] = $template_data;

        if( !($view_obj = self::init_view( $template, $view_params )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_INIT_VIEW, self::_t( 'Error initializing view.' ) );

            return false;
        }

        $action_result = PHS_Action::default_action_result();

        if( ($action_result['buffer'] = $view_obj->render()) === false )
        {
            if( $view_obj->has_error() )
                self::st_copy_error( $view_obj );
            else
                self::st_set_error( self::ERR_INIT_VIEW, self::_t( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        if( empty( $action_result['buffer'] ) )
            $action_result['buffer'] = '';

        return $action_result;
    }

    /**
     * @param $template
     * @param bool $params
     *
     * @return bool|false|\phs\system\core\views\PHS_View
     */
    public static function init_view( $template, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['theme'] ) )
            $params['theme'] = false;
        if( empty( $params['view_class'] ) )
            $params['view_class'] = false;
        if( empty( $params['plugin'] ) )
            $params['plugin'] = false;
        if( empty( $params['as_singleton'] ) )
            $params['as_singleton'] = false;

        if( empty( $params['action_obj'] ) )
            $params['action_obj'] = false;
        if( empty( $params['controller_obj'] ) )
            $params['controller_obj'] = false;
        if( empty( $params['parent_plugin_obj'] ) )
            $params['parent_plugin_obj'] = false;

        if( empty( $params['template_data'] ) )
            $params['template_data'] = false;

        if( !($view_obj = PHS::load_view( $params['view_class'], $params['plugin'], $params['as_singleton'] )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_INIT_VIEW, self::_t( 'Error instantiating view class.' ) );
            return false;
        }

        if( !$view_obj->set_action( $params['action_obj'] )
         or !$view_obj->set_controller( $params['controller_obj'] )
         or !$view_obj->set_theme( $params['theme'] )
         or !$view_obj->set_template( $template )
         or (!empty( $params['parent_plugin_obj'] ) and !$view_obj->parent_plugin( $params['parent_plugin_obj'] ))
        )
        {
            if( $view_obj->has_error() )
                self::st_copy_error( $view_obj );
            else
                self::st_set_error( self::ERR_INIT_VIEW, self::_t( 'Error setting up view instance.' ) );

            return false;
        }

        if( !empty( $params['template_data'] ) )
            $view_obj->set_view_var( $params['template_data'] );

        return $view_obj;
    }

    public function set_controller( $controller_obj )
    {
        if( $controller_obj !== false
        and !($controller_obj instanceof PHS_Controller) )
        {
            $this->set_error( self::ERR_BAD_CONTROLLER, self::_t( 'Not a controller instance.' ) );
            return false;
        }

        $this->_controller = $controller_obj;
        return true;
    }

    public function set_action( $action_obj )
    {
        if( $action_obj !== false
        and !($action_obj instanceof PHS_Action) )
        {
            $this->set_error( self::ERR_BAD_ACTION, self::_t( 'Not an action instance.' ) );
            return false;
        }

        $this->_action = $action_obj;
        return true;
    }

    public function set_parent_view( $view_obj )
    {
        if( $view_obj !== false
        and !($view_obj instanceof PHS_View) )
        {
            $this->set_error( self::ERR_BAD_ACTION, self::_t( 'Not a view instance.' ) );
            return false;
        }

        $this->_parent_view = $view_obj;
        return true;
    }

    public function is_admin_controller()
    {
        return ($this->_controller and $this->_controller->is_admin_controller()?true:false);
    }

    /**
     * @return bool|\phs\libraries\PHS_Controller Controller that "owns" this view or false if no controller
     */
    public function get_controller()
    {
        return $this->_controller;
    }

    /**
     * @return bool|\phs\libraries\PHS_Action Action that "owns" this view or false if no action
     */
    public function get_action()
    {
        return $this->_action;
    }

    /**
     * @return bool|PHS_View View that "owns" this sub-view or false if no parent view
     */
    public function get_parent_view()
    {
        return $this->_parent_view;
    }

    /**
     * @return array If current view has an action associated, return it's action result
     */
    public function get_action_result()
    {
        $default_action_result = PHS_Action::default_action_result();
        /** @var \phs\libraries\PHS_Action $action */
        if( !($action = $this->get_action()) )
            return $default_action_result;

        return self::validate_array( $action->get_action_result(), $default_action_result );
    }

    public function get_theme()
    {
        return $this->_theme;
    }

    public function get_template()
    {
        return $this->_template;
    }

    public function get_template_dirs()
    {
        return $this->_template_dirs;
    }

    public function get_template_file()
    {
        return $this->_template_file;
    }

    public function add_extra_template_dir( $dir_path, $dir_www )
    {
        if( empty( $dir_path ) )
            return false;

        $dir_path = rtrim( $dir_path, '/\\' );
        if( !@is_dir( $dir_path ) or !@is_readable( $dir_path ) )
            return false;

        $dir_www = rtrim( $dir_www, '/' ).'/';

        $this->_extra_template_dirs[$dir_path.'/'] = $dir_www;

        return true;
    }

    /**
     * @param string $theme_relative_dir
     * @param bool|string $theme
     *
     * @return array|bool
     */
    public static function st_add_extra_theme_dir( $theme_relative_dir, $theme = false )
    {
        if( $theme === false )
            $theme = PHS::get_theme();

        $theme_relative_dir = rtrim( $theme_relative_dir, '/\\' );
        if( empty( $theme_relative_dir )
         or (!empty( $theme ) and !($theme = PHS::valid_theme( $theme ))) )
            return false;

        $extra_dirs = array();
        if( defined( 'PHS_THEMES_WWW' ) and defined( 'PHS_THEMES_DIR' ) )
        {
            if( !empty( $theme )
            and @file_exists( PHS_THEMES_DIR . $theme . '/'. $theme_relative_dir )
            and @is_dir( PHS_THEMES_DIR . $theme . '/'. $theme_relative_dir ) )
                $extra_dirs[PHS_THEMES_DIR . $theme . '/' . $theme_relative_dir . '/'] = PHS_THEMES_WWW . $theme . '/' . $theme_relative_dir . '/';

            if( ($themes_arr = PHS::get_cascading_themes())
            and is_array( $themes_arr ) )
            {
                foreach( $themes_arr as $c_theme )
                {
                    if( !empty( $c_theme )
                    and @file_exists( PHS_THEMES_DIR . $c_theme . '/'. $theme_relative_dir )
                    and @is_dir( PHS_THEMES_DIR . $c_theme . '/'. $theme_relative_dir )
                    and empty( $extra_dirs[PHS_THEMES_DIR . $c_theme . '/' . $theme_relative_dir . '/'] ) )
                        $extra_dirs[PHS_THEMES_DIR . $c_theme . '/' . $theme_relative_dir . '/'] = PHS_THEMES_WWW . $c_theme . '/' . $theme_relative_dir . '/';
                }
            }

            if( ($default_theme = PHS::get_default_theme())
            and $default_theme !== $theme
            and @file_exists( PHS_THEMES_DIR . $default_theme . '/'. $theme_relative_dir )
            and @is_dir( PHS_THEMES_DIR . $default_theme . '/'. $theme_relative_dir ) )
                $extra_dirs[PHS_THEMES_DIR . $default_theme . '/' . $theme_relative_dir . '/'] = PHS_THEMES_WWW . $default_theme . '/' . $theme_relative_dir . '/';
        }

        return $extra_dirs;
    }

    public function add_extra_theme_dir( $theme_relative_dir )
    {
        if( !($extra_dirs = self::st_add_extra_theme_dir( $theme_relative_dir, $this->get_theme() )) )
            return false;

        foreach( $extra_dirs as $dir_path => $dir_www )
            $this->_extra_template_dirs[$dir_path] = $dir_www;

        return true;
    }

    protected function _get_template_directories()
    {
        $this->_template_dirs = array();

        $current_language = PHS_Language::get_current_language();

        if( defined( 'PHS_THEMES_WWW' ) and defined( 'PHS_THEMES_DIR' ) )
        {
            // Check if current theme overrides plugin template
            $plugins_check_arr = array( $this->_action, $this->_controller, $this->parent_plugin(), $this->get_plugin_instance() );
            foreach( $plugins_check_arr as $instance_obj )
            {
                if( empty( $instance_obj )
                 or !is_object( $instance_obj )
                 or !($instance_obj instanceof PHS_Instantiable)
                 or $instance_obj->instance_is_core() )
                    continue;

                $plugin_name = $instance_obj->instance_plugin_name();

                if( !empty( $this->_theme )
                and @file_exists( PHS_THEMES_DIR . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name )
                and @is_dir( PHS_THEMES_DIR . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name ) )
                {
                    if( @file_exists( PHS_THEMES_DIR . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language )
                    and @is_dir( PHS_THEMES_DIR . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language ) )
                        $this->_template_dirs[PHS_THEMES_DIR . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language .'/']
                            = PHS_THEMES_WWW . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language .'/';

                    $this->_template_dirs[PHS_THEMES_DIR . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/']
                            = PHS_THEMES_WWW . $this->_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/';
                }

                if( ($themes_arr = PHS::get_cascading_themes())
                and is_array( $themes_arr ) )
                {
                    foreach( $themes_arr as $c_theme )
                    {
                        if( !@file_exists( PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name )
                         or !@is_dir( PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name ) )
                            continue;

                        if( @file_exists( PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language )
                        and @is_dir( PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language ) )
                            $this->_template_dirs[PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language .'/']
                                = PHS_THEMES_WWW . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language .'/';

                        $this->_template_dirs[PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/']
                            = PHS_THEMES_WWW . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/';
                    }
                }

                if( ($default_theme = PHS::get_default_theme())
                and $default_theme !== $this->_theme
                and @file_exists( PHS_THEMES_DIR . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name )
                and @is_dir( PHS_THEMES_DIR . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name ) )
                {
                    if( @file_exists( PHS_THEMES_DIR . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language )
                    and @is_dir( PHS_THEMES_DIR . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language ) )
                        $this->_template_dirs[PHS_THEMES_DIR . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language .'/']
                            = PHS_THEMES_WWW . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. $current_language .'/';

                    $this->_template_dirs[PHS_THEMES_DIR . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/']
                            = PHS_THEMES_WWW . $default_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/';
                }
            }
        }

        // take first dirs custom ones... (if any)
        if( !empty( $this->_extra_template_dirs ) and is_array( $this->_extra_template_dirs ) )
        {
            foreach( $this->_extra_template_dirs as $dir_path => $dir_www )
            {
                $dir_path = rtrim( $dir_path, '/\\' );
                $dir_www = rtrim( $dir_www, '/' );

                if( @file_exists( $dir_path . '/'.$current_language )
                and @is_dir( $dir_path . '/'.$current_language ) )
                    $this->_template_dirs[$dir_path . '/'.$current_language.'/'] = $dir_www . '/'.$current_language.'/';

                $this->_template_dirs[$dir_path . '/'] = $dir_www . '/';
            }
        }

        if( !empty( $this->_controller )
        and !$this->_controller->instance_is_core()
        and @file_exists( $this->_controller->instance_plugin_path() . '/' . PHS_Instantiable::TEMPLATES_DIR )
        and @is_dir( $this->_controller->instance_plugin_path() . '/' . PHS_Instantiable::TEMPLATES_DIR ) )
        {
            $plugin_path = $this->_controller->instance_plugin_path();
            $plugin_www = $this->_controller->instance_plugin_www();

            if( @file_exists( $plugin_path . '/' . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language )
            and @is_dir( $plugin_path . '/' . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language ) )
                $this->_template_dirs[$plugin_path . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language . '/']
                    = $plugin_www . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language . '/';

            $this->_template_dirs[$plugin_path . PHS_Instantiable::TEMPLATES_DIR . '/'] = $plugin_www . PHS_Instantiable::TEMPLATES_DIR . '/';
        }

        if( !empty( $this->_action )
        and !$this->_action->instance_is_core()
        and @file_exists( $this->_action->instance_plugin_path() . '/' . PHS_Instantiable::TEMPLATES_DIR )
        and @is_dir( $this->_action->instance_plugin_path() . '/' . PHS_Instantiable::TEMPLATES_DIR ) )
        {
            $plugin_path = $this->_action->instance_plugin_path();
            $plugin_www = $this->_action->instance_plugin_www();

            if( @file_exists( $plugin_path . '/' . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language )
            and @is_dir( $plugin_path . '/' . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language ) )
                $this->_template_dirs[$plugin_path . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language . '/']
                    = $plugin_www . PHS_Instantiable::TEMPLATES_DIR . '/' . $current_language . '/';

            $this->_template_dirs[$plugin_path . PHS_Instantiable::TEMPLATES_DIR . '/'] = $plugin_www . PHS_Instantiable::TEMPLATES_DIR . '/';
        }

        if( defined( 'PHS_THEMES_WWW' ) and defined( 'PHS_THEMES_DIR' ) )
        {
            if( !empty( $this->_theme ) )
            {
                if( @file_exists( PHS_THEMES_DIR . $this->_theme . '/' . $current_language )
                and @is_dir( PHS_THEMES_DIR . $this->_theme . '/' . $current_language ) )
                    $this->_template_dirs[PHS_THEMES_DIR . $this->_theme . '/' . $current_language . '/'] = PHS_THEMES_WWW . $this->_theme . '/' . $current_language . '/';

                $this->_template_dirs[PHS_THEMES_DIR . $this->_theme . '/'] = PHS_THEMES_WWW . $this->_theme . '/';
            }

            if( ($themes_arr = PHS::get_cascading_themes())
            and is_array( $themes_arr ) )
            {
                foreach( $themes_arr as $c_theme )
                {
                    if( @file_exists( PHS_THEMES_DIR . $c_theme . '/' . $current_language )
                    and @is_dir( PHS_THEMES_DIR . $c_theme . '/' . $current_language ) )
                        $this->_template_dirs[PHS_THEMES_DIR . $c_theme . '/' . $current_language . '/'] = PHS_THEMES_WWW . $c_theme . '/' . $current_language . '/';

                    $this->_template_dirs[PHS_THEMES_DIR . $c_theme . '/'] = PHS_THEMES_WWW . $c_theme . '/';
                }
            }

            if( ($default_theme = PHS::get_default_theme())
            and $default_theme !== $this->_theme )
            {
                if( @file_exists( PHS_THEMES_DIR . $default_theme . '/' . $current_language )
                and @is_dir( PHS_THEMES_DIR . $default_theme . '/' . $current_language ) )
                    $this->_template_dirs[PHS_THEMES_DIR . $default_theme . '/' . $current_language . '/'] = PHS_THEMES_WWW . $default_theme . '/' . $current_language . '/';

                $this->_template_dirs[PHS_THEMES_DIR . $default_theme . '/'] = PHS_THEMES_WWW . $default_theme . '/';
            }
        }

        return $this->_template_dirs;
    }

    private function _get_file_details( $file_name )
    {
        $this->reset_error();

        if( empty( $file_name ) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Please provide a resource file.' ) );
            return false;
        }

        if( !($dirs_list = $this->_get_template_directories())
         or !is_array( $dirs_list ) )
        {
            $this->set_error( self::ERR_TEMPLATE_DIRS, self::_t( 'Couldn\'t get includes directories.' ) );
            return false;
        }

        @clearstatcache();
        foreach( $dirs_list as $dir_path => $dir_www )
        {
            if( @file_exists( $dir_path.$file_name ) )
            {
                return array(
                    'full_path' => $dir_path.$file_name,
                    'full_url' => $dir_www.$file_name,
                    'path' => $dir_path,
                    'url' => $dir_www,
                );
            }
        }

        return false;
    }

    /**
     * Return full path to template file based on themes, action, controller, parent plugin and current plugin
     * @return bool|string
     */
    protected function _get_template_path()
    {
        $this->reset_error();

        if( !empty( $this->_template_file ) )
            return $this->_template_file;

        if( empty( $this->_template ) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Please provide a template first.' ) );
            return false;
        }

        if( !($template_details = $this->_get_file_details( $this->_template.'.php' ))
         or empty( $template_details['full_path'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Template [%s] not found, theme [%s].', $this->_template, $this->_theme ) );
            return false;
        }

        $this->_template_file = $template_details['full_path'];
        return true;
    }

    /**
     * Return resource details for found file based on themes, action, controller, parent plugin and current plugin
     * @param string $file
     *
     * @return array|bool
     */
    public function get_resource_details( $file )
    {
        if( empty( $file )
         or !($file = self::safe_escape_resource( $file )) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Invalid resource file.' ) );
            return false;
        }

        if( !($file_details = $this->_get_file_details( $file )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Resource file [%s] not found.', $file ) );
            return false;
        }

        return $file_details;
    }

    /**
     * Return URL to file resource
     * @param string $file
     *
     * @return string
     */
    public function get_resource_url( $file )
    {
        if( empty( $file )
         or !($resource_details = $this->get_resource_details( $file ))
         or empty( $resource_details['full_url'] ) )
            return '#'.$file.'-not-found';

        return $resource_details['full_url'];
    }

    /**
     * Return full server path to resource file
     * @param string $file
     *
     * @return bool|string
     */
    public function get_resource_path( $file )
    {
        if( empty( $file )
         or !($resource_details = $this->get_resource_details( $file ))
         or empty( $resource_details['full_path'] ) )
            return false;

        return $resource_details['full_path'];
    }

    public static function safe_escape_template( $template )
    {
        if( empty( $template ) or !is_string( $template )
         or preg_match( '@[^a-zA-Z0-9_-]@', $template ) )
            return false;

        return $template;
    }

    public static function safe_escape_resource( $resource )
    {
        if( empty( $resource ) or !is_string( $resource )
         or preg_match( '@[^a-zA-Z0-9_\-\./]@', $resource ) )
            return false;

        $resource = str_replace( '..', '', trim( $resource, '/' ) );

        return $resource;
    }

    /**
     * Set template for current view
     * @param string|array $template
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function set_template( $template, $params = false )
    {
        $this->reset_error();

        if( !($template_structure = self::validate_template_resource( $template, $params )) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Invalid template structure.' ) );
            return false;
        }

        if( !self::safe_escape_template( $template_structure['file'] ) )
        {
            $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Invalid template file.' ) );
            return false;
        }

        $this->_template = '';

        if( !empty( $template_structure['extra_paths'] ) and is_array( $template_structure['extra_paths'] ) )
        {
            $this->_extra_template_dirs = array();
            foreach( $template_structure['extra_paths'] as $dir_path => $dir_www )
            {
                $this->add_extra_template_dir( $dir_path, $dir_www );

                //if( !$this->add_extra_template_dir( $dir_path, $dir_www ) )
                //{
                //    $this->_extra_template_dirs = array();
                //    $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Invalid template extra directories.' ) );
                //    return false;
                //}
            }
        }

        $this->_template = $template_structure['file'];

        return $template_structure;
    }

    /**
     * Force a specific theme for current view
     * @param string $theme
     *
     * @return bool
     */
    public function set_theme( $theme )
    {
        $this->reset_error();

        if( empty( $theme ) )
            $theme = PHS::get_theme();

        if( !PHS::valid_theme( $theme ) )
        {
            $this->set_error( self::ERR_BAD_THEME, self::_t( 'Invalid theme.' ) );
            return false;
        }

        $this->_theme = $theme;
        return true;
    }

    public function sub_view( $template, $force_theme = false )
    {
        $this->reset_error();

        $subview_obj = clone $this;

        if( empty( $force_theme ) )
            $view_theme = $this->get_theme();
        else
            $view_theme = $force_theme;

        if( !$subview_obj->set_theme( $view_theme ) )
        {
            if( $subview_obj->has_error() )
                $this->copy_error( $subview_obj );
            else
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error setting theme for provided sub-view.' ) );

            return false;
        }

        $subview_obj->set_parent_view( $this );

        $template_params = array();
        $template_params['theme'] = $view_theme;

        if( !($subview_template = $subview_obj->set_template( $template, $template_params ))
         or ($subview_buffer = $subview_obj->render( $template )) === false )
        {
            if( $subview_obj->has_error() )
                $this->copy_error( $subview_obj );
            else
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error rendering sub-view template.' ) );

            return false;
        }

        $hook_args = PHS_Hooks::default_buffer_hook_args();
        $hook_args['buffer_data'] = $subview_obj::get_full_data();
        $hook_args['buffer'] = $subview_buffer;

        if( !empty( $subview_template['file'] )
        and ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_WEB_SUBVIEW_RENDERING.'_'.$subview_template['file'], $hook_args ))
        and is_array( $hook_args )
        and isset( $hook_args['buffer'] ) and is_string( $hook_args['buffer'] ) )
            $subview_buffer = $hook_args['buffer'];

        return $subview_buffer;
    }

    public function get_all_view_vars()
    {
        if( !($vars_arr = $this->get_context( self::VIEW_CONTEXT_DATA_KEY )) )
            $vars_arr = array();

        return $vars_arr;
    }

    /**
     * Retrieve view variable
     *
     * @param string $key What variable to retrieve
     *
     * @return bool|mixed Variable value
     */
    public function view_var( $key )
    {
        if( !($_VIEW_CONTEXT = $this->get_context( self::VIEW_CONTEXT_DATA_KEY ))
         or !isset( $_VIEW_CONTEXT[$key] ) )
            return false;

        return $_VIEW_CONTEXT[$key];
    }

    public function set_view_var( $key, $val = null )
    {
        if( ($parent_view = $this->get_parent_view()) )
            $parent_view->set_view_var( $key, $val );

        if( !($_VIEW_CONTEXT = $this->get_context( self::VIEW_CONTEXT_DATA_KEY )) )
            $_VIEW_CONTEXT = array();

        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                $_VIEW_CONTEXT[$kkey] = $kval;
            }
        } else
        {
            if( !is_scalar( $key ) )
                return false;

            $_VIEW_CONTEXT[$key] = $val;
        }

        $this->set_context( self::VIEW_CONTEXT_DATA_KEY, $_VIEW_CONTEXT );

        return true;
    }

    /**
     * Backwards compatibility
     *
     * @param string $key What variable to retrieve
     *
     * @return bool|mixed Variable value
     */
    public function context_var( $key )
    {
        return $this->view_var( $key );
    }

    /**
     * Render template set for current view or the template provided in parameters
     *
     * @param bool|array|string $template
     * @param bool|string $force_theme
     * @param bool|array $params
     *
     * @return bool|string
     */
    public function render( $template = false, $force_theme = false, $params = false )
    {
        if( $template !== false )
        {
            if( !$this->set_template( $template ) )
                return false;
        }

        if( $force_theme !== false )
        {
            if( !$this->set_theme( $force_theme ) )
                return false;
        }

        if( !$this->_get_template_path() )
        {
            if( self::st_debugging_mode() )
                PHS_Logger::logf( 'Template [%s, file: %s] not found using theme [%s].',
                    (!empty( $this->_template )?$this->_template:'N/A'),
                    (!empty( $this->_template_file )?$this->_template_file:'N/A'),
                    $this->get_theme(),
                    PHS_Logger::TYPE_DEBUG );

            return false;
        }

        $resulting_buf = '';

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['only_string_result'] ) )
            $params['only_string_result'] = true;
        else
            $params['only_string_result'] = (!empty( $params['only_string_result'] ));

        // sanity check...
        if( !empty( $this->_template_file )
        and @file_exists( $this->_template_file ) )
        {
            ob_start();
            if( !($resulting_buf = include( $this->_template_file )) )
                $resulting_buf = '';

            if( empty( $params['only_string_result'] ) )
                ob_end_clean();

            else
            {
                if( !is_string( $resulting_buf ) )
                    $resulting_buf = '';

                $resulting_buf .= ob_get_clean();
            }
        }

        return $resulting_buf;
    }
}
