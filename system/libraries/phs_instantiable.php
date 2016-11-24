<?php

namespace phs\libraries;

use \phs\PHS;

abstract class PHS_Instantiable extends PHS_Registry
{
    const ERR_INSTANCE = 20000, ERR_INSTANCE_ID = 20001, ERR_INSTANCE_CLASS = 20002, ERR_CLASS_NAME = 20002;

    const INSTANCE_TYPE_PLUGIN = 'plugin', INSTANCE_TYPE_MODEL = 'model', INSTANCE_TYPE_CONTROLLER = 'controller', INSTANCE_TYPE_ACTION = 'action', INSTANCE_TYPE_VIEW = 'view',
          INSTANCE_TYPE_SCOPE = 'scope';

    const CORE_PLUGIN = 'core', TEMPLATES_DIR = 'templates';

    // String values will be used when generating instance_id
    private static $INSTANCE_TYPES_ARR = array(
        self::INSTANCE_TYPE_PLUGIN => array( 'title' => 'Plugin', 'dir_name' => '' ),
        self::INSTANCE_TYPE_MODEL => array( 'title' => 'Model', 'dir_name' => 'models' ),
        self::INSTANCE_TYPE_CONTROLLER => array( 'title' => 'Controller', 'dir_name' => 'controllers' ),
        self::INSTANCE_TYPE_ACTION => array( 'title' => 'Action', 'dir_name' => 'actions' ),
        self::INSTANCE_TYPE_VIEW => array( 'title' => 'View', 'dir_name' => 'views' ),
        self::INSTANCE_TYPE_SCOPE => array( 'title' => 'Scope', 'dir_name' => 'scopes' ),
    );

    protected static $instances = array();

    private $instance_details = array();

    /** @var PHS_Plugin|null $_parent_plugin */
    private $_parent_plugin = null;

    /**
     * @return int Should return INSTANCE_TYPE_* constant
     */
    abstract public function instance_type();

    static public function get_instance_types()
    {
        return self::$INSTANCE_TYPES_ARR;
    }

    static public function valid_instance_type( $type )
    {
        if( empty( $type )
         or !($types_arr = self::get_instance_types())
         or empty( $types_arr[$type] ) )
            return false;

        return $types_arr[$type];
    }

    static public function validate_instance_type_dir( $type_dir )
    {
        if( !($types_arr = self::get_instance_types()) )
            return false;

        foreach( $types_arr as $type => $type_details )
        {
            if( $type_details['dir_name'] === $type_dir )
                return $type;
        }

        return false;
    }

    static public function instance_type_dir( $type )
    {
        if( !($types_details = self::valid_instance_type( $type )) )
            return false;

        return $types_details['dir_name'];
    }

    function __construct( $instance_details = false )
    {
        parent::__construct();

        if( empty( $instance_details ) or !is_array( $instance_details ) )
            $instance_details = self::empty_instance_details();

        $this->set_instance_details( $instance_details );
    }

    final public function parent_plugin( $plugin_obj = false )
    {
        if( $plugin_obj === false )
            return $this->_parent_plugin;

        if( !($plugin_obj instanceof PHS_Plugin) )
            return false;

        $this->_parent_plugin = $plugin_obj;
        
        return $this->_parent_plugin;
    }

    /**
     * Gets plugin instance where current instance is running
     *
     * @return bool|false|PHS_Plugin
     */
    final public function get_plugin_instance()
    {
        $this->reset_error();

        if( !empty( $this->_parent_plugin ) )
            return $this->_parent_plugin;

        if( !($plugin_name = $this->instance_plugin_name())
         or $plugin_name == self::CORE_PLUGIN
         or !($plugin_obj = PHS::load_plugin( $plugin_name )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        return $plugin_obj;
    }

    /**
     * @return array Array with settings of plugin of current model
     */
    public function get_plugin_settings()
    {
        if( !($plugin_obj = $this->get_plugin_instance()) )
            return array();

        if( ($plugins_settings = $plugin_obj->get_db_settings()) === false
         or empty( $plugins_settings ) or !is_array( $plugins_settings ) )
            $plugins_settings = $plugin_obj->get_default_settings();

        return $plugins_settings;
    }

    /**
     * @return bool|string
     */
    final public function instance_id()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['instance_id'] ) )
            return '';

        return $this->instance_details['instance_id'];
    }

    /**
     * @return bool
     */
    final public function instance_is_core()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['plugin_name'] ) )
            return false;

        return ($this->instance_details['plugin_name']==self::CORE_PLUGIN);
    }

    /**
     * @return bool|string
     */
    final public function instance_name()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['instance_name'] ) )
            return false;

        return $this->instance_details['instance_name'];
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_name()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['plugin_name'] ) )
            return false;

        return $this->instance_details['plugin_name'];
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_www()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['plugin_www'] ) )
            return false;

        return $this->instance_details['plugin_www'];
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_path()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['plugin_path'] ) )
            return false;

        return $this->instance_details['plugin_path'];
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_templates_www()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_www()) )
            return false;

        return $prefix.self::TEMPLATES_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_templates_path()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_path()) )
            return false;

        return $prefix.self::TEMPLATES_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_email_templates_www()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_www()) )
            return false;

        return $prefix.self::TEMPLATES_DIR.'/'.PHS_EMAILS_DIRS.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_email_templates_path()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_path()) )
            return false;

        return $prefix.self::TEMPLATES_DIR.'/'.PHS_EMAILS_DIRS.'/';
    }

    /**
     * @param string $instance_type What kind of instance is this
     * @param string $instance_name Part of class name after predefined prefix (eg. phs_model_ for models, phs_controller_ for controller etc)
     * @param string|bool $plugin_name Plugin name or false meaning core class
     * @return string|false Returns generated string from $instance_name and $plugin_name. This will uniquely identify the file we have to load. false on error
     */
    public static function generate_instance_id( $instance_type, $instance_name, $plugin_name = false )
    {
        self::st_reset_error();

        if( $plugin_name !== false
        and (!is_string( $plugin_name ) or empty( $plugin_name )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid plugin name.' ) );
            return false;
        }

        if( empty( $plugin_name ) )
            $plugin_name = self::CORE_PLUGIN;
        else
            $plugin_name = self::safe_escape_plugin_name( $plugin_name );

        if( !is_string( $instance_name ) or empty( $instance_name ) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid instance name.' ) );
            return false;
        }

        if( !self::valid_instance_type( $instance_type ) )
        {
            self::st_set_error( self::ERR_INSTANCE_ID, self::_t( 'Please provide a valid instance type.' ) );
            return false;
        }

        return strtolower( $instance_type.':'.$plugin_name.':'.$instance_name );
    }

    /**
     * @return bool|array
     */
    public static function valid_instance_id( $instance_id )
    {
        if( empty( $instance_id )
         or @strpos( $instance_id, ':' ) === false
         or !($instance_parts = explode( ':', $instance_id, 3 ))
         or !is_array( $instance_parts )
         or empty( $instance_parts[0] ) or empty( $instance_parts[1] ) or empty( $instance_parts[2] )
         or !self::valid_instance_type( $instance_parts[0] )
         or ($instance_parts[1] != self::CORE_PLUGIN and $instance_parts[1] != self::safe_escape_plugin_name( $instance_parts[1] )) )
        {
            self::st_set_error( self::ERR_INSTANCE_ID, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        return array(
            'instance_type' => $instance_parts[0],
            'plugin_name' => $instance_parts[1],
            'instance_name' => $instance_parts[2],
        );
    }

    final private function set_instance_details( $details_arr )
    {
        $this->instance_details = $details_arr;
    }

    final public function instance_details()
    {
        return $this->instance_details;
    }

    public static function empty_instance_details()
    {
        return array(
            'plugin_name' => '',
            'plugin_www' => '',
            'plugin_path' => '',
            'instance_type' => '',
            'instance_path' => '',
            'instance_full_class' => '',
            'instance_class' => '',
            'instance_name' => '',
            'instance_file_name' => '',
            'instance_id' => '',
            'plugin_paths' => array(),
        );
    }

    /**
     * @param string $class Class name
     * @param string|bool|false $plugin_name
     * @param string|bool|false $instance_type
     * @return array|bool Returns array with details about a class in core or plugin
     */
    public static function get_instance_details( $class, $plugin_name = false, $instance_type = false )
    {
        self::st_reset_error();

        if( empty( $class )
         or !($class = self::safe_escape_class_name( $class )) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Bad class name.' ) );
            return false;
        }

        if( $plugin_name !== false
        and (!is_string( $plugin_name ) or empty( $plugin_name )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid plugin name.' ) );
            return false;
        }

        if( !($instance_type_details = self::valid_instance_type( $instance_type )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid instance type.' ) );
            return false;
        }

        if( empty( $plugin_name ) )
            $plugin_name = self::CORE_PLUGIN;
        else
        {
            $plugin_name = self::safe_escape_plugin_name( $plugin_name );

            if( $plugin_name == self::CORE_PLUGIN )
            {
                self::st_set_error( self::ERR_INSTANCE, self::_t( 'Plugin name not allowed.' ) );
                return false;
            }
        }

        // We don't have core plugins...
        if( $plugin_name == self::CORE_PLUGIN and $instance_type == self::INSTANCE_TYPE_PLUGIN )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Unknown plugin name.' ) );
            return false;
        }

        $return_arr = self::empty_instance_details();
        $return_arr['plugin_name'] = $plugin_name;
        $return_arr['instance_type'] = $instance_type;
        $return_arr['instance_class'] = $class;
        $return_arr['instance_full_class'] = '\\phs\\';

        if( $plugin_name == self::CORE_PLUGIN )
            $return_arr['instance_full_class'] .= 'system\\core\\';
        else
            $return_arr['instance_full_class'] .= 'plugins\\'.$plugin_name.'\\';

        if( !($instance_type_dir = self::instance_type_dir( $instance_type )) )
            $instance_type_dir = '';

        $return_arr['instance_full_class'] .= $instance_type_dir;

        if( !empty( $instance_type_dir ) )
            $return_arr['instance_full_class'] .= '\\';

        $return_arr['instance_full_class'] .= $class;

        switch( $instance_type )
        {
            default:
                self::st_set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance type.' ) );
                return false;
            break;

            case self::INSTANCE_TYPE_MODEL:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 10 ) ) != 'phs_model_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework models.' ) );
                    return false;
                }

                $return_arr['instance_name'] = substr( $class, 10 );

                if( $plugin_name == self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_MODEL_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_CONTROLLER:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 15 ) ) != 'phs_controller_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework controller.' ) );
                    return false;
                }

                $return_arr['instance_name'] = substr( $class, 15 );

                if( $plugin_name == self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_CONTROLLER_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_ACTION:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 11 ) ) != 'phs_action_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework action.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 11 ), '_' );

                if( empty( $return_arr['instance_name'] ) )
                    $return_arr['instance_name'] = 'index';

                if( $plugin_name == self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_ACTION_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_VIEW:

                if( empty( $class )
                 or (strtolower( substr( $class, 0, 9 ) ) != 'phs_view_'
                        and strtolower( $class ) != 'phs_view' ) )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework view.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 8 ), '_' );

                if( empty( $return_arr['instance_name'] ) )
                    $return_arr['instance_name'] = 'view';

                if( $plugin_name == self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_VIEW_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_SCOPE:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 10 ) ) != 'phs_scope_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework scope.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 10 ), '_' );

                if( $plugin_name == self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_SCOPE_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_PLUGIN:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 11 ) ) != 'phs_plugin_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework plugin.' ) );
                    return false;
                }

                $return_arr['instance_name'] = substr( $class, 11 );

                $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
            break;
        }

        if( !($instance_id = self::generate_instance_id( $instance_type, $return_arr['instance_name'], $plugin_name )) )
            return false;

        $return_arr['instance_name'] = ucfirst( strtolower( $return_arr['instance_name'] ) );
        $return_arr['instance_id'] = $instance_id;
        $return_arr['instance_file_name'] = 'phs_'.strtolower( $return_arr['instance_name'] ).'.php';
        $return_arr['plugin_paths'] = array();

        if( ($instance_types_arr = self::get_instance_types()) )
        {
            if( $return_arr['plugin_name'] == self::CORE_PLUGIN )
                $path_prefix = PHS_CORE_DIR;
            else
                $path_prefix = $return_arr['plugin_path'];

            foreach( $instance_types_arr as $type_id => $type_details )
            {
                $return_arr['plugin_paths'][$type_id] = $path_prefix.$type_details['dir_name'].($type_details['dir_name']!=''?'/':'');
            }

            if( $return_arr['plugin_name'] != self::CORE_PLUGIN )
                $return_arr['plugin_paths'][self::TEMPLATES_DIR] = $path_prefix . self::TEMPLATES_DIR . '/';
        }

        return $return_arr;
    }

    public static function safe_escape_action_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return strtolower( $name );
    }

    public static function safe_escape_class_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return $name;
    }

    public static function safe_escape_class_name_with_namespace( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_/]@', $name ) )
            return false;

        return $name;
    }

    public static function safe_escape_plugin_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return strtolower( $name );
    }

    public static function safe_escape_theme_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return strtolower( $name );
    }

    public static function extract_details_from_full_namespace_name( $class_with_namespace )
    {
        self::st_reset_error();

        if( empty( $class_with_namespace )
         or !($class_namespace_path = explode( '\\', $class_with_namespace )) )
        {
            self::st_set_error( self::ERR_CLASS_NAME, self::_t( 'Seems like class name doesn\'t contain namespace.' ) );
            return false;
        }

        $class_name = array_pop( $class_namespace_path );

        if( empty( $class_namespace_path[0] ) or $class_namespace_path[0] != 'phs'
         or empty( $class_namespace_path[1] ) or !in_array( $class_namespace_path[1], array( 'plugins', 'system' ) )
         or empty( $class_namespace_path[2] ) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Couldn\'t create instance for classes outside phs namespace.' ) );
            return false;
        }

        $plugin_name = (isset( $class_namespace_path[2] )?$class_namespace_path[2]:'');
        $instance_type_dir = (isset( $class_namespace_path[3] )?$class_namespace_path[3]:'');

        // Special case for plugin classes used in plugins dirs...
        if( $class_namespace_path[1] == 'plugins' and !isset( $class_namespace_path[3] ) )
            $instance_type = self::INSTANCE_TYPE_PLUGIN;
        else
            $instance_type = self::validate_instance_type_dir( $instance_type_dir );

        return array(
          'class_name' => $class_name,
          'plugin_name' => $plugin_name,
          'instance_type' => $instance_type,
        );
    }

    final public static function get_instance( $class_name = null, $plugin_name = false, $instance_type = false, $singleton = true )
    {
        self::st_reset_error();

        if( is_null( $class_name ) )
        {
            if( !($class_details = self::extract_details_from_full_namespace_name( get_called_class() )) )
                return false;

            $class_name = $class_details['class_name'];
            $plugin_name = $class_details['plugin_name'];
            $instance_type = $class_details['instance_type'];
        }

        if( !($instance_details = self::get_instance_details( $class_name, $plugin_name, $instance_type ))
         or empty( $instance_details['instance_id'] ) )
            return false;

        if( !@class_exists( $instance_details['instance_full_class'], false ) )
        {
            $instance_file_path = $instance_details['instance_path'].$instance_details['instance_file_name'];

            if( !@file_exists( $instance_file_path ) )
            {
                self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Couldn\'t load instance file for class %s from plugin %s.', $class_name, $instance_details['plugin_name'] ) );
                return false;
            }

            ob_start();
            include_once( $instance_file_path );
            ob_end_clean();
        }


        if( !@class_exists( $instance_details['instance_full_class'], false ) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Class %s not defined in %s file.', $instance_details['instance_full_class'], $instance_details['instance_file_name'] ) );
            return false;
        }

        /** @var PHS_Model $instance_obj */
        if( !empty( $singleton )
        and isset( self::$instances[$instance_details['instance_id']] ) )
            $instance_obj = self::$instances[$instance_details['instance_id']];
        else
        {
            $instance_class = $instance_details['instance_full_class'];

            /** @var PHS_Instantiable $instance_obj */
            $instance_obj = new $instance_class( $instance_details );

            if( $instance_obj->has_error() )
            {
                self::st_copy_error( $instance_obj );
                return false;
            }
        }

        if( !($instance_obj instanceof PHS_Instantiable) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Loaded class doesn\'t appear to be a PHS instance.' ) );
            return false;
        }

        if( !empty( $singleton ) )
        {
            self::$instances[$instance_details['instance_id']] = $instance_obj;
            return self::$instances[$instance_details['instance_id']];
        }

        return $instance_obj;
    }
}
