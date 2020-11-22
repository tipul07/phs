<?php

namespace phs\libraries;

use \phs\PHS;

abstract class PHS_Instantiable extends PHS_Registry
{
    const ERR_INSTANCE = 20000, ERR_INSTANCE_ID = 20001, ERR_INSTANCE_CLASS = 20002, ERR_CLASS_NAME = 20002;

    const INSTANCE_TYPE_PLUGIN = 'plugin', INSTANCE_TYPE_MODEL = 'model', INSTANCE_TYPE_CONTROLLER = 'controller', INSTANCE_TYPE_ACTION = 'action',
          INSTANCE_TYPE_VIEW = 'view', INSTANCE_TYPE_SCOPE = 'scope', INSTANCE_TYPE_CONTRACT = 'contract';

    const CORE_PLUGIN = 'core', TEMPLATES_DIR = 'templates', LANGUAGES_DIR = 'languages', THEMES_PLUGINS_TEMPLATES_DIR = 'plugins',
          TESTS_DIR = 'tests',
          // Behat features directory in tests directory of plugin
          BEHAT_DIR = 'behat',
          // Files required for test unit (eg. PHPUnit)
          TESTUNIT_DIR = 'testunit';

    // String values will be used when generating instance_id
    private static $INSTANCE_TYPES_ARR = [
        self::INSTANCE_TYPE_PLUGIN => [ 'title' => 'Plugin', 'dir_name' => '' ],
        self::INSTANCE_TYPE_MODEL => [ 'title' => 'Model', 'dir_name' => 'models' ],
        self::INSTANCE_TYPE_CONTROLLER => [ 'title' => 'Controller', 'dir_name' => 'controllers' ],
        self::INSTANCE_TYPE_ACTION => [ 'title' => 'Action', 'dir_name' => 'actions' ],
        self::INSTANCE_TYPE_VIEW => [ 'title' => 'View', 'dir_name' => 'views' ],
        self::INSTANCE_TYPE_SCOPE => [ 'title' => 'Scope', 'dir_name' => 'scopes' ],
        self::INSTANCE_TYPE_CONTRACT => [ 'title' => 'Contract', 'dir_name' => 'contracts' ],
    ];

    protected static $instances = [];

    private $instance_details = [];

    /** @var PHS_Plugin|null $_parent_plugin */
    private $_parent_plugin = null;

    /**
     * @return string Should return INSTANCE_TYPE_* constant
     */
    abstract public function instance_type();

    public static function get_instance_types()
    {
        return self::$INSTANCE_TYPES_ARR;
    }

    public static function valid_instance_type( $type )
    {
        if( empty( $type )
         or !($types_arr = self::get_instance_types())
         or empty( $types_arr[$type] ) )
            return false;

        return $types_arr[$type];
    }

    /**
     * Return dirs in plugin structure that allow subdirs
     *
     * @return array
     */
    public static function get_instance_type_dirs_that_allow_subdirs()
    {
        if( !($allow_arr = self::instance_types_that_allow_subdirs())
         or !($types_arr = self::get_instance_types()) )
            return array();

        $dirs_that_allow_subdirs = array();
        foreach( $allow_arr as $type_id )
        {
            if( empty( $types_arr[$type_id] ) )
                continue;

            $dirs_that_allow_subdirs[] = $types_arr[$type_id]['dir_name'];
        }

        return $dirs_that_allow_subdirs;
    }

    private static function _validate_instance_type_dir_from_namespace( $type_dir )
    {
        if( !($types_arr = self::get_instance_types()) )
            return false;

        if( !($allow_subdirs = self::get_instance_type_dirs_that_allow_subdirs() ))
            $allow_subdirs = [];

        foreach( $types_arr as $type => $type_details )
        {
            $type_dir_parts = false;
            if( $type_details['dir_name'] === $type_dir

                ||

                // Instances with subdirs
                (
                    false !== ($dir_pos = array_search( $type_details['dir_name'], $allow_subdirs, true ))
                 && false !== strpos( $type_dir, '\\' )
                 && ($type_dir_parts = explode( '\\', $type_dir ))
                 && is_array( $type_dir_parts )
                 && $dir_pos === array_search( array_pop( $type_dir_parts ), $allow_subdirs, true )
                )
            )
            {
                return [
                    'type' => $type,
                    'subdir' => ((!empty( $type_dir_parts ) && is_array( $type_dir_parts ))?implode( '/', $type_dir_parts ):''),
                ];
            }
        }

        return false;
    }

    public static function instance_type_dir( $type )
    {
        if( !($types_details = self::valid_instance_type( $type )) )
            return false;

        return $types_details['dir_name'];
    }

    /**
     * PHS_Instantiable constructor.
     *
     * @param bool|array $instance_details
     */
    public function __construct( $instance_details = false )
    {
        parent::__construct();

        if( empty( $instance_details ) or !is_array( $instance_details ) )
            $instance_details = self::empty_instance_details();

        $this->_set_instance_details( $instance_details );
    }

    /**
     * @param bool|PHS_Plugin $plugin_obj
     *
     * @return bool|PHS_Plugin|null
     */
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
         or $plugin_name === self::CORE_PLUGIN
         or !($plugin_obj = PHS::load_plugin( $plugin_name )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        return $plugin_obj;
    }

    /**
     * @return array Array with settings of plugin
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
     * @return array Array with registry records of plugin
     */
    public function get_plugin_registry()
    {
        if( !($plugin_obj = $this->get_plugin_instance()) )
            return array();

        if( !($plugin_registry = $plugin_obj->get_db_registry())
         or !is_array( $plugin_registry ) )
            $plugin_registry = array();

        return $plugin_registry;
    }

    /**
     * @return string
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

        return ($this->instance_details['plugin_name']===self::CORE_PLUGIN);
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
     * @return string
     */
    final public function instance_subdir()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['instance_subdir'] ) )
            return '';

        return $this->instance_details['instance_subdir'];
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
    final public function instance_plugin_tests_www()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_www()) )
            return false;

        return $prefix.self::TESTS_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_tests_path()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_path()) )
            return false;

        return $prefix.self::TESTS_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_behat_www()
    {
        if( !($prefix = $this->instance_plugin_tests_www()) )
            return false;

        return $prefix.self::BEHAT_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_behat_path()
    {
        if( !($prefix = $this->instance_plugin_tests_path()) )
            return false;

        return $prefix.self::BEHAT_DIR.'/';
    }

    final public function instance_plugin_behat_details()
    {
        $behat_path = $this->instance_plugin_behat_path();
        $behat_www = $this->instance_plugin_behat_www();

        $features_dir = 'features';
        $contexts_dir = 'contexts';

        return array(
            'behat_path' => $behat_path,
            'behat_www' => $behat_www,
            'features_dir' => $features_dir,
            'contexts_dir' => $contexts_dir,
            'features_path' => $behat_path.$features_dir.'/',
            'contexts_path' => $behat_path.$contexts_dir.'/',
            'config_file' => 'behat.yml',
            'config_file_path' => $behat_path.'behat.yml',
        );
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_testunit_www()
    {
        if( !($prefix = $this->instance_plugin_tests_www()) )
            return false;

        return $prefix.self::TESTUNIT_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_testunit_path()
    {
        if( !($prefix = $this->instance_plugin_tests_path()) )
            return false;

        return $prefix.self::TESTUNIT_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_languages_www()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_www()) )
            return false;

        return $prefix.self::LANGUAGES_DIR.'/';
    }

    /**
     * @return bool|string
     */
    final public function instance_plugin_languages_path()
    {
        if( $this->instance_is_core()
         or !($prefix = $this->instance_plugin_path()) )
            return false;

        return $prefix.self::LANGUAGES_DIR.'/';
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
     * @return bool|array
     */
    final public function instance_plugin_themes_email_templates_pairs()
    {
        if( $this->instance_is_core()
         or !($plugin_name = $this->instance_plugin_name())
         or !($prefix_path = $this->instance_plugin_path())
         or !($prefix_www = $this->instance_plugin_www()) )
            return false;

        $current_lang = PHS::get_current_language();

        $pairs_arr = array();
        if( ($theme = PHS::get_theme()) )
        {
            $check_dir = PHS_THEMES_DIR . $theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. PHS_EMAILS_DIRS .'/'. $current_lang;
            if( !empty( $current_lang )
            and @file_exists( $check_dir )
            and @is_dir( $check_dir ) )
                $pairs_arr[$check_dir.'/'] = PHS_THEMES_WWW.$theme.'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang.'/';

            $check_dir = PHS_THEMES_DIR . $theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. PHS_EMAILS_DIRS;
            if( @file_exists( $check_dir )
            and @is_dir( $check_dir ) )
                $pairs_arr[$check_dir.'/'] = PHS_THEMES_WWW.$theme.'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/';
        }

        if( ($themes_arr = PHS::get_cascading_themes())
        and is_array( $themes_arr ) )
        {
            foreach( $themes_arr as $c_theme )
            {
                if( empty( $c_theme ) )
                    continue;

                $check_dir = PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. PHS_EMAILS_DIRS .'/'. $current_lang;
                if( !empty( $current_lang )
                and @file_exists( $check_dir )
                and @is_dir( $check_dir )
                and empty( $pairs_arr[$check_dir . '/'] ) )
                    $pairs_arr[$check_dir . '/'] = PHS_THEMES_WWW . $c_theme . '/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/' . $plugin_name . PHS_EMAILS_DIRS . $current_lang . '/';

                $check_dir = PHS_THEMES_DIR . $c_theme .'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'. $plugin_name .'/'. PHS_EMAILS_DIRS;
                if( !empty( $current_lang )
                and @file_exists( $check_dir )
                and @is_dir( $check_dir )
                and empty( $pairs_arr[$check_dir . '/'] ) )
                    $pairs_arr[$check_dir . '/'] = PHS_THEMES_WWW . $c_theme . '/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/' . $plugin_name . PHS_EMAILS_DIRS . '/';
            }
        }

        if( ($default_theme = PHS::get_default_theme())
        and $default_theme !== $theme )
        {
            if( !empty( $current_lang ) )
            {
                $check_dir = PHS_THEMES_DIR.$default_theme.'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang;
                if( @file_exists( $check_dir )
                and @is_dir( $check_dir ) )
                    $pairs_arr[$check_dir.'/'] =
                        PHS_THEMES_WWW.$default_theme.'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang.'/';
            }

            $check_dir = PHS_THEMES_DIR.$default_theme.'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'.$plugin_name.'/'.PHS_EMAILS_DIRS;
            if( @file_exists( $check_dir )
            and @is_dir( $check_dir ) )
                $pairs_arr[$check_dir.'/'] =
                    PHS_THEMES_WWW.$default_theme.'/'. self::THEMES_PLUGINS_TEMPLATES_DIR .'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/';
        }

        return $pairs_arr;
    }

    /**
     * If instance has subdir, add subdir to $instance_name as namespace
     *  eg. self::generate_instance_id( self::INSTANCE_TYPE_MODEL, 'subdir\\accounts', 'accounts' ) ->  'model:accounts:subdir\\accounts'
     *  This is only an example!!! Models don't support yet subdirs
     * @param string $instance_type What kind of instance is this
     * @param string $instance_name Calss name after phs prefix (eg. phs_model_), prefixed with subdir (if applicable) as namespace path (eg. subdir\\Myaction vs Myaction with no subdir)
     * @param string|bool $plugin_name Plugin name or false meaning core class
     * @return string|false Returns generated string from $instance_name and $plugin_name. This will uniquely identify the file we have to load. false on error
     */
    public static function generate_instance_id( $instance_type, $instance_name, $plugin_name = false )
    {
        self::st_reset_error();

        if( $plugin_name !== false
         && (!is_string( $plugin_name ) || empty( $plugin_name )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid plugin name.' ) );
            return false;
        }

        if( empty( $plugin_name ) )
            $plugin_name = self::CORE_PLUGIN;
        else
            $plugin_name = self::safe_escape_plugin_name( $plugin_name );

        if( !is_string( $instance_name ) || empty( $instance_name ) )
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
     * @param string $instance_id
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
         or ($instance_parts[1] !== self::CORE_PLUGIN and $instance_parts[1] !== self::safe_escape_plugin_name( $instance_parts[1] )) )
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

    private function _set_instance_details( $details_arr )
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
            'instance_type_accepts_subdirs' => false,
            'instance_subdir' => '',
            'instance_path' => '',
            'instance_full_class' => '',
            'instance_class' => '',
            'instance_name' => '',
            // Including subdir as namespace
            'instance_full_name' => '',
            'instance_file_name' => '',
            'instance_id' => '',
            'plugin_paths' => array(),
        );
    }

    /**
     * @param string $class Class name without subdir (if applicable)
     * @param string|bool $plugin_name
     * @param string|bool $instance_type
     * @param string $instance_subdir Subdir provided as file system path
     * @return array|bool Returns array with details about a class in core or plugin
     */
    public static function get_instance_details( $class, $plugin_name = false, $instance_type = false, $instance_subdir = '' )
    {
        self::st_reset_error();

        if( !($instance_type_details = self::valid_instance_type( $instance_type )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid instance type.' ) );
            return false;
        }

        $instance_type_accepts_subdirs = (in_array( $instance_type, self::instance_types_that_allow_subdirs(), true )?true:false);

        if( !is_string( $instance_subdir ) )
            $instance_subdir = '';
        else
            $instance_subdir = trim( trim( $instance_subdir ), '/\\' );

        if( '' !== $instance_subdir
         && (!$instance_type_accepts_subdirs || '' === ($instance_subdir = self::safe_escape_instance_subdir_path( $instance_subdir ))) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Provided instance type doesn\'t support sub-directories or sub-directory is not allowed.' ) );
            return false;
        }

        if( empty( $class )
         || !($class = self::safe_escape_class_name( $class )) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Bad class name.' ) );
            return false;
        }

        if( $plugin_name !== false
         && (!is_string( $plugin_name ) || empty( $plugin_name )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid plugin name.' ) );
            return false;
        }

        if( empty( $plugin_name ) )
            $plugin_name = self::CORE_PLUGIN;
        else
        {
            $plugin_name = self::safe_escape_plugin_name( $plugin_name );

            if( $plugin_name === self::CORE_PLUGIN )
            {
                self::st_set_error( self::ERR_INSTANCE, self::_t( 'Plugin name not allowed.' ) );
                return false;
            }
        }

        // We don't have core plugins...
        if( $plugin_name === self::CORE_PLUGIN && $instance_type === self::INSTANCE_TYPE_PLUGIN )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Unknown plugin name.' ) );
            return false;
        }

        // Atm we support only one level of subdirs, but lets make it general...
        $subdir_namespace = '';
        $subdir_path = '';
        if( !empty( $instance_subdir ) )
        {
            $subdir_namespace = str_replace( '/', '\\', $instance_subdir );
            $subdir_path = trim( $instance_subdir, '/\\' );
        }

        $return_arr = self::empty_instance_details();
        $return_arr['plugin_name'] = $plugin_name;
        $return_arr['instance_type'] = $instance_type;
        $return_arr['instance_type_accepts_subdirs'] = $instance_type_accepts_subdirs;
        $return_arr['instance_subdir'] = $subdir_path;
        $return_arr['instance_class'] = $class;
        $return_arr['instance_full_class'] = '\\phs\\';

        if( $plugin_name === self::CORE_PLUGIN )
            $return_arr['instance_full_class'] .= 'system\\core\\';
        else
            $return_arr['instance_full_class'] .= 'plugins\\'.$plugin_name.'\\';

        if( !($instance_type_dir = self::instance_type_dir( $instance_type )) )
            $instance_type_dir = '';

        else
            $return_arr['instance_full_class'] .= $instance_type_dir.'\\';

        if( !empty( $subdir_namespace ) )
            $return_arr['instance_full_class'] .= $subdir_namespace.'\\';

        $return_arr['instance_full_class'] .= $class;

        switch( $instance_type )
        {
            default:
                self::st_set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance type.' ) );
                return false;
            break;

            case self::INSTANCE_TYPE_MODEL:

                if( empty( $class )
                 || stripos( $class, 'phs_model_' ) !== 0 )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework models.' ) );
                    return false;
                }

                $return_arr['instance_name'] = substr( $class, 10 );

                if( $plugin_name === self::CORE_PLUGIN )
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
                 || stripos( $class, 'phs_controller_' ) !== 0 )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework controller.' ) );
                    return false;
                }

                $return_arr['instance_name'] = substr( $class, 15 );

                if( $plugin_name === self::CORE_PLUGIN )
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
                 || stripos( $class, 'phs_action_' ) !== 0 )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework action.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 11 ), '_' );

                if( empty( $return_arr['instance_name'] ) )
                    $return_arr['instance_name'] = 'index';

                if( $plugin_name === self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_ACTION_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_CONTRACT:

                if( empty( $class )
                 || stripos( $class, 'phs_contract_' ) !== 0 )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework contract.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 13 ), '_' );

                if( empty( $return_arr['instance_name'] ) )
                    $return_arr['instance_name'] = 'index';

                if( $plugin_name === self::CORE_PLUGIN )
                {
                    $return_arr['instance_path'] = PHS_CORE_CONTRACT_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/'.$instance_type_dir.'/';
                }
            break;

            case self::INSTANCE_TYPE_VIEW:

                if( empty( $class )
                 || (stripos( $class, 'phs_view_' ) !== 0
                        && strtolower( $class ) !== 'phs_view' ) )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework view.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 8 ), '_' );

                if( empty( $return_arr['instance_name'] ) )
                    $return_arr['instance_name'] = 'view';

                if( $plugin_name === self::CORE_PLUGIN )
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
                 || stripos( $class, 'phs_scope_' ) !== 0 )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework scope.' ) );
                    return false;
                }

                $return_arr['instance_name'] = trim( substr( $class, 10 ), '_' );

                if( $plugin_name === self::CORE_PLUGIN )
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
                 || stripos( $class, 'phs_plugin_' ) !== 0 )
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

        if( $subdir_path !== '' )
            $return_arr['instance_path'] .= $subdir_path.'/';


        $return_arr['instance_name'] = ucfirst( strtolower( $return_arr['instance_name'] ) );
        $return_arr['instance_full_name'] = ($subdir_namespace!==''?$subdir_namespace.'\\':'').$return_arr['instance_name'];

        if( !($instance_id = self::generate_instance_id( $instance_type, $return_arr['instance_full_name'], $plugin_name )) )
            return false;

        $return_arr['instance_json_file'] = 'phs_'.strtolower( $return_arr['instance_name'] ).'.json';
        $return_arr['instance_id'] = $instance_id;
        $return_arr['instance_file_name'] = 'phs_'.strtolower( $return_arr['instance_name'] ).'.php';
        $return_arr['plugin_paths'] = [];

        if( ($instance_types_arr = self::get_instance_types()) )
        {
            if( $return_arr['plugin_name'] === self::CORE_PLUGIN )
                $path_prefix = PHS_CORE_DIR;
            else
                $path_prefix = $return_arr['plugin_path'];

            foreach( $instance_types_arr as $type_id => $type_details )
            {
                $return_arr['plugin_paths'][$type_id] = $path_prefix.$type_details['dir_name'].($type_details['dir_name']!==''?'/':'');
            }

            if( $return_arr['plugin_name'] !== self::CORE_PLUGIN )
            {
                $return_arr['plugin_paths'][self::TEMPLATES_DIR] = $path_prefix . self::TEMPLATES_DIR . '/';
                $return_arr['plugin_paths'][self::LANGUAGES_DIR] = $path_prefix . self::LANGUAGES_DIR . '/';
            }
        }

        return $return_arr;
    }

    final public static function instance_types_that_allow_subdirs()
    {
        return [ self::INSTANCE_TYPE_ACTION, self::INSTANCE_TYPE_CONTRACT ];
    }

    /**
     * @param string $dir
     *
     * @return string
     */
    public static function safe_escape_instance_subdir_path( $dir )
    {
        if( empty( $dir ) || !is_string( $dir )
         || preg_match( '@[^a-zA-Z0-9/]@', $dir ) )
            return '';

        return strtolower( trim( $dir ) );
    }

    /**
     * @param string $dir
     *
     * @return string
     */
    public static function safe_escape_instance_subdir( $dir )
    {
        if( empty( $dir ) || !is_string( $dir )
         || preg_match( '@[^a-zA-Z0-9_]@', $dir ) )
            return '';

        return strtolower( trim( $dir ) );
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_action_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return strtolower( $name );
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_library_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return $name;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_class_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return $name;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_class_name_with_subdirs( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_\\]@', $name ) )
            return false;

        return $name;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_class_name_with_namespace( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_/]@', $name ) )
            return false;

        return $name;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_plugin_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '@[^a-zA-Z0-9_]@', $name ) )
            return false;

        return strtolower( $name );
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
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
         || !($class_namespace_path = explode( '\\', $class_with_namespace )) )
        {
            self::st_set_error( self::ERR_CLASS_NAME, self::_t( 'Seems like class name doesn\'t contain namespace.' ) );
            return false;
        }

        $class_name = array_pop( $class_namespace_path );

        if( empty( $class_namespace_path[0] ) || $class_namespace_path[0] !== 'phs'
         || empty( $class_namespace_path[1] ) || !in_array( $class_namespace_path[1], [ 'plugins', 'system' ] )
         || empty( $class_namespace_path[2] ) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Couldn\'t create instance for classes outside phs namespace.' ) );
            return false;
        }

        $plugin_name = (isset( $class_namespace_path[2] )?$class_namespace_path[2]:'');
        $instance_type_dir = (isset( $class_namespace_path[3] )?$class_namespace_path[3]:'');

        $instance_subdir = '';

        // Special case for plugin classes used in plugins dirs...
        if( $class_namespace_path[1] === 'plugins' && !isset( $class_namespace_path[3] ) )
        {
            $instance_type = self::INSTANCE_TYPE_PLUGIN;
        } elseif( ($guessed_type = self::_validate_instance_type_dir_from_namespace( $instance_type_dir )) )
        {
            $instance_type = (!empty( $guessed_type['type'] )?$guessed_type['type']:false);
            if( !empty( $guessed_type['subdir'] ) )
                $instance_subdir = $guessed_type['subdir'];
        } else
        {
            $instance_type = false;
        }

        return [
          'class_name' => $class_name,
          'plugin_name' => $plugin_name,
          'instance_type' => $instance_type,
          'instance_subdir' => $instance_subdir,
        ];
    }

    /**
     * @param string|null $class_name
     * @param string|bool $plugin_name
     * @param string|bool $instance_type
     * @param string $instance_subdir Instance subdir provided as file system path
     * @param bool $singleton
     *
     * @return bool|mixed|PHS_Instantiable|PHS_Model
     */
    final public static function get_instance( $class_name = null, $plugin_name = false, $instance_type = false, $singleton = true, $instance_subdir = '' )
    {
        self::st_reset_error();

        if( $class_name === null )
        {
            if( !($class_details = self::extract_details_from_full_namespace_name( @get_called_class() )) )
                return false;

            $class_name = $class_details['class_name'];
            $plugin_name = $class_details['plugin_name'];
            $instance_type = $class_details['instance_type'];
            $instance_subdir = $class_details['instance_subdir'];
        }

        if( !($instance_details = self::get_instance_details( $class_name, $plugin_name, $instance_type, $instance_subdir ))
         || empty( $instance_details['instance_id'] ) )
            return false;

        if( !@class_exists( $instance_details['instance_full_class'], false ) )
        {
            $instance_file_path = $instance_details['instance_path'].$instance_details['instance_file_name'];

            if( !@file_exists( $instance_file_path ) )
            {
                if( PHS::st_debugging_mode() )
                    self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Couldn\'t load instance file for class %s from plugin %s.', $class_name, $instance_details['plugin_name'] ) );
                else
                    self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Couldn\'t obtain required instance.' ) );

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
         && isset( self::$instances[$instance_details['instance_id']] ) )
            return self::$instances[$instance_details['instance_id']];

        $instance_class = $instance_details['instance_full_class'];

        /** @var PHS_Instantiable $instance_obj */
        if( !($instance_obj = new $instance_class( $instance_details )) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Error instantiating class %s from %s file.', $instance_details['instance_full_class'], $instance_details['instance_file_name'] ) );
            return false;
        }

        if( !($instance_obj instanceof PHS_Instantiable) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Loaded class doesn\'t appear to be a PHS instance.' ) );
            return false;
        }

        if( $instance_obj->has_error() )
        {
            self::st_copy_error( $instance_obj );
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
