<?php
namespace phs\libraries;

use phs\PHS;

abstract class PHS_Instantiable extends PHS_Registry
{
    public const ERR_INSTANCE = 20000, ERR_INSTANCE_ID = 20001, ERR_INSTANCE_CLASS = 20002, ERR_CLASS_NAME = 20002;

    public const INSTANCE_TYPE_UNDEFINED = 'undefined',
    INSTANCE_TYPE_PLUGIN = 'plugin', INSTANCE_TYPE_MODEL = 'model', INSTANCE_TYPE_CONTROLLER = 'controller',
    INSTANCE_TYPE_ACTION = 'action', INSTANCE_TYPE_VIEW = 'view', INSTANCE_TYPE_SCOPE = 'scope',
    INSTANCE_TYPE_CONTRACT = 'contract', INSTANCE_TYPE_EVENT = 'event';

    public const CORE_PLUGIN = 'core', TEMPLATES_DIR = 'templates', LANGUAGES_DIR = 'languages', THEMES_PLUGINS_TEMPLATES_DIR = 'plugins',
    TESTS_DIR = 'tests',
    // Behat features directory in tests directory of plugin
    BEHAT_DIR = 'behat',
    // Files required for test unit (eg. PHPUnit)
    TESTUNIT_DIR = 'testunit';

    private array $instance_details = [];

    /** @var null|PHS_Plugin */
    private ?PHS_Plugin $_parent_plugin = null;

    protected static array $instances = [];

    protected static array $instances_details = [];

    // String values will be used when generating instance_id
    private static array $INSTANCE_TYPES_ARR = [
        self::INSTANCE_TYPE_UNDEFINED  => ['title' => 'Undefined', 'dir_name' => '', 'phs_loader_method' => ''],
        self::INSTANCE_TYPE_PLUGIN     => ['title' => 'Plugin', 'dir_name' => '', 'phs_loader_method' => 'load_plugin'],
        self::INSTANCE_TYPE_MODEL      => ['title' => 'Model', 'dir_name' => 'models', 'phs_loader_method' => 'load_model'],
        self::INSTANCE_TYPE_CONTROLLER => ['title' => 'Controller', 'dir_name' => 'controllers', 'phs_loader_method' => 'load_controller'],
        self::INSTANCE_TYPE_ACTION     => ['title' => 'Action', 'dir_name' => 'actions', 'phs_loader_method' => 'load_action'],
        self::INSTANCE_TYPE_VIEW       => ['title' => 'View', 'dir_name' => 'views', 'phs_loader_method' => 'load_view'],
        self::INSTANCE_TYPE_SCOPE      => ['title' => 'Scope', 'dir_name' => 'scopes', 'phs_loader_method' => 'load_scope'],
        self::INSTANCE_TYPE_CONTRACT   => ['title' => 'Contract', 'dir_name' => 'contracts', 'phs_loader_method' => 'load_contract'],
        self::INSTANCE_TYPE_EVENT      => ['title' => 'Event', 'dir_name' => 'events', 'phs_loader_method' => 'load_event'],
    ];

    /**
     * PHS_Instantiable constructor.
     *
     * @param bool|array $instance_details
     */
    private function __construct($instance_details = false)
    {
        parent::__construct();
        $this->_do_construct($instance_details);
    }

    /**
     * @return string Should return INSTANCE_TYPE_* constant
     */
    abstract public function instance_type() : string;

    /**
     * @param bool|PHS_Plugin $plugin_obj
     *
     * @return null|bool|PHS_Plugin
     */
    final public function parent_plugin($plugin_obj = false)
    {
        if ($this->instance_type() === self::INSTANCE_TYPE_UNDEFINED) {
            return null;
        }

        if ($plugin_obj === false) {
            return $this->_parent_plugin;
        }

        if (!($plugin_obj instanceof PHS_Plugin)) {
            return false;
        }

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

        if ($this->instance_type() === self::INSTANCE_TYPE_UNDEFINED) {
            return null;
        }

        if (!empty($this->_parent_plugin)) {
            return $this->_parent_plugin;
        }

        if (!($plugin_name = $this->instance_plugin_name())
         || $plugin_name === self::CORE_PLUGIN
         || !($plugin_obj = PHS::load_plugin($plugin_name))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            return false;
        }

        return $plugin_obj;
    }

    /**
     * Used as {PHS_Instantiable}::get_instance()?->with_active_plugin() in PHP 8.0
     * @return null|$this
     */
    final public function with_active_plugin() : ?self
    {
        $this->reset_error();

        if (!($plugin_obj = $this->get_plugin_instance())
         || !$plugin_obj->plugin_active()) {
            return null;
        }

        return $this;
    }

    /**
     * @return array Array with settings of plugin
     */
    public function get_plugin_settings() : array
    {
        if (!($plugin_obj = $this->get_plugin_instance())) {
            return [];
        }

        if (!($plugins_settings = $plugin_obj->get_db_settings())) {
            $plugins_settings = $plugin_obj->get_default_settings();
        }

        return $plugins_settings;
    }

    /**
     * @return array Array with registry records of plugin
     */
    public function get_plugin_registry() : array
    {
        if (!($plugin_obj = $this->get_plugin_instance())) {
            return [];
        }

        if (!($plugin_registry = $plugin_obj->get_db_registry())
         || !is_array($plugin_registry)) {
            $plugin_registry = [];
        }

        return $plugin_registry;
    }

    /**
     * @return string
     */
    final public function instance_id() : string
    {
        if (empty($this->instance_details)
         || empty($this->instance_details['instance_id'])) {
            return '';
        }

        return $this->instance_details['instance_id'];
    }

    /**
     * @return bool
     */
    final public function instance_is_core() : bool
    {
        return !empty($this->instance_details)
                && !empty($this->instance_details['plugin_name'])
                && $this->instance_details['plugin_name'] === self::CORE_PLUGIN;
    }

    /**
     * @return string
     */
    final public function instance_name() : string
    {
        if (empty($this->instance_details)
         || empty($this->instance_details['instance_name'])) {
            return '';
        }

        return $this->instance_details['instance_name'];
    }

    /**
     * @return false|string
     */
    final public function instance_plugin_name()
    {
        if (empty($this->instance_details)
         || empty($this->instance_details['plugin_name'])) {
            return false;
        }

        return $this->instance_details['plugin_name'];
    }

    /**
     * @return string
     */
    final public function instance_plugin_www() : string
    {
        if (empty($this->instance_details)
         || empty($this->instance_details['plugin_www'])) {
            return '';
        }

        return $this->instance_details['plugin_www'];
    }

    /**
     * @return string
     */
    final public function instance_plugin_path() : string
    {
        if (empty($this->instance_details)
         || empty($this->instance_details['plugin_path'])) {
            return '';
        }

        return $this->instance_details['plugin_path'];
    }

    /**
     * @return string
     */
    final public function instance_subdir() : string
    {
        if (empty($this->instance_details)
         || empty($this->instance_details['instance_subdir'])) {
            return '';
        }

        return $this->instance_details['instance_subdir'];
    }

    /**
     * @return string
     */
    final public function instance_plugin_templates_www() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_www())) {
            return '';
        }

        return $prefix.self::TEMPLATES_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_templates_path() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_path())) {
            return '';
        }

        return $prefix.self::TEMPLATES_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_tests_www() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_www())) {
            return '';
        }

        return $prefix.self::TESTS_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_tests_path() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_path())) {
            return '';
        }

        return $prefix.self::TESTS_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_behat_www() : string
    {
        if (!($prefix = $this->instance_plugin_tests_www())) {
            return '';
        }

        return $prefix.self::BEHAT_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_behat_path() : string
    {
        if (!($prefix = $this->instance_plugin_tests_path())) {
            return '';
        }

        return $prefix.self::BEHAT_DIR.'/';
    }

    final public function instance_plugin_behat_details() : array
    {
        $behat_path = $this->instance_plugin_behat_path();
        $behat_www = $this->instance_plugin_behat_www();

        $features_dir = 'features';
        $contexts_dir = 'contexts';

        return [
            'behat_path'       => $behat_path,
            'behat_www'        => $behat_www,
            'features_dir'     => $features_dir,
            'contexts_dir'     => $contexts_dir,
            'features_path'    => $behat_path.$features_dir.'/',
            'contexts_path'    => $behat_path.$contexts_dir.'/',
            'config_file'      => 'behat.yml',
            'config_file_path' => $behat_path.'behat.yml',
        ];
    }

    /**
     * @return string
     */
    final public function instance_plugin_testunit_www() : string
    {
        if (!($prefix = $this->instance_plugin_tests_www())) {
            return '';
        }

        return $prefix.self::TESTUNIT_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_testunit_path() : string
    {
        if (!($prefix = $this->instance_plugin_tests_path())) {
            return '';
        }

        return $prefix.self::TESTUNIT_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_languages_www() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_www())) {
            return '';
        }

        return $prefix.self::LANGUAGES_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_languages_path() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_path())) {
            return '';
        }

        return $prefix.self::LANGUAGES_DIR.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_email_templates_www() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_www())) {
            return '';
        }

        return $prefix.self::TEMPLATES_DIR.'/'.PHS_EMAILS_DIRS.'/';
    }

    /**
     * @return string
     */
    final public function instance_plugin_email_templates_path() : string
    {
        if ($this->instance_is_core()
         || !($prefix = $this->instance_plugin_path())) {
            return '';
        }

        return $prefix.self::TEMPLATES_DIR.'/'.PHS_EMAILS_DIRS.'/';
    }

    /**
     * @return array
     */
    final public function instance_plugin_themes_email_templates_pairs() : array
    {
        if ($this->instance_is_core()
         || !($plugin_name = $this->instance_plugin_name())
         || !($prefix_path = $this->instance_plugin_path())
         || !($prefix_www = $this->instance_plugin_www())) {
            return [];
        }

        $current_lang = PHS::get_current_language();

        $pairs_arr = [];
        if (($theme = PHS::get_theme())) {
            $check_dir = PHS_THEMES_DIR.$theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang;
            if (!empty($current_lang)
             && @file_exists($check_dir)
             && @is_dir($check_dir)) {
                $pairs_arr[$check_dir.'/']
                    = PHS_THEMES_WWW.$theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang.'/';
            }

            $check_dir = PHS_THEMES_DIR.$theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS;
            if (@file_exists($check_dir)
             && @is_dir($check_dir)) {
                $pairs_arr[$check_dir.'/']
                    = PHS_THEMES_WWW.$theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/';
            }
        }

        if (($themes_arr = PHS::get_cascading_themes())
        && is_array($themes_arr)) {
            foreach ($themes_arr as $c_theme) {
                if (empty($c_theme)) {
                    continue;
                }

                $check_dir = PHS_THEMES_DIR.$c_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang;
                if (!empty($current_lang)
                 && empty($pairs_arr[$check_dir.'/'])
                 && @file_exists($check_dir)
                 && @is_dir($check_dir)) {
                    $pairs_arr[$check_dir.'/']
                        = PHS_THEMES_WWW.$c_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.PHS_EMAILS_DIRS.$current_lang.'/';
                }

                $check_dir = PHS_THEMES_DIR.$c_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS;
                if (!empty($current_lang)
                 && empty($pairs_arr[$check_dir.'/'])
                 && @file_exists($check_dir)
                 && @is_dir($check_dir)) {
                    $pairs_arr[$check_dir.'/']
                        = PHS_THEMES_WWW.$c_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.PHS_EMAILS_DIRS.'/';
                }
            }
        }

        if (($default_theme = PHS::get_default_theme())
         && $default_theme !== $theme) {
            if (!empty($current_lang)) {
                $check_dir = PHS_THEMES_DIR.$default_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang;
                if (@file_exists($check_dir)
                 && @is_dir($check_dir)) {
                    $pairs_arr[$check_dir.'/']
                        = PHS_THEMES_WWW.$default_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/'.$current_lang.'/';
                }
            }

            $check_dir = PHS_THEMES_DIR.$default_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS;
            if (@file_exists($check_dir)
             && @is_dir($check_dir)) {
                $pairs_arr[$check_dir.'/']
                    = PHS_THEMES_WWW.$default_theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name.'/'.PHS_EMAILS_DIRS.'/';
            }
        }

        return $pairs_arr;
    }

    final public function instance_details() : array
    {
        return $this->instance_details;
    }

    /**
     * @param false|array $instance_details
     */
    protected function _do_construct($instance_details = false) : void
    {
        if (empty($instance_details) || !is_array($instance_details)) {
            $instance_details = self::empty_instance_details();
        }

        $this->_set_instance_details($instance_details);
    }

    private function _set_instance_details($details_arr) : void
    {
        $this->instance_details = $details_arr ?? [];
    }

    public static function get_instance_types() : array
    {
        return self::$INSTANCE_TYPES_ARR;
    }

    /**
     * @param string $type
     *
     * @return array
     */
    public static function valid_instance_type(string $type) : array
    {
        if (empty($type)
         || !($types_arr = self::get_instance_types())
         || empty($types_arr[$type])) {
            return [];
        }

        return $types_arr[$type];
    }

    /**
     * Return dirs in plugin structure that allow subdirs
     *
     * @return array
     */
    public static function get_instance_type_dirs_that_allow_subdirs() : array
    {
        if (!($allow_arr = self::instance_types_that_allow_subdirs())
         || !($types_arr = self::get_instance_types())) {
            return [];
        }

        $dirs_that_allow_subdirs = [];
        foreach ($allow_arr as $type_id) {
            if (empty($types_arr[$type_id])) {
                continue;
            }

            $dirs_that_allow_subdirs[] = $types_arr[$type_id]['dir_name'];
        }

        return $dirs_that_allow_subdirs;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public static function instance_type_dir(string $type) : string
    {
        if (!($types_details = self::valid_instance_type($type))) {
            return '';
        }

        return $types_details['dir_name'];
    }

    /**
     * If instance has subdir, add subdir to $instance_name as namespace
     *  e.g. self::generate_instance_id( self::INSTANCE_TYPE_MODEL, 'subdir\\accounts', 'accounts' ) ->  'model:accounts:subdir\\accounts'
     *  This is only an example!!! Models don't support yet subdirs
     *
     * @param string $instance_type What kind of instance is this
     * @param string $instance_name Calss name after phs prefix (e.g. phs_model_), prefixed with subdir (if applicable) as namespace path (eg. subdir\\Myaction vs Myaction with no subdir)
     * @param string|bool $plugin_name Plugin name or false meaning core class
     *
     * @return string|false Returns generated string from $instance_name and $plugin_name. This will uniquely identify the file we have to load. false on error
     */
    public static function generate_instance_id(string $instance_type, string $instance_name, $plugin_name = false) : string
    {
        self::st_reset_error();

        if ($plugin_name !== false
         && (!is_string($plugin_name) || empty($plugin_name))) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Please provide a valid plugin name.'));

            return '';
        }

        if (empty($plugin_name)) {
            $plugin_name = self::CORE_PLUGIN;
        } else {
            $plugin_name = self::safe_escape_plugin_name($plugin_name);
        }

        if (empty($instance_name)) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Please provide a valid instance name.'));

            return '';
        }

        if (!self::valid_instance_type($instance_type)) {
            self::st_set_error(self::ERR_INSTANCE_ID, self::_t('Please provide a valid instance type.'));

            return '';
        }

        return strtolower($instance_type.':'.$plugin_name.':'.$instance_name);
    }

    /**
     * @param string $instance_id
     *
     * @return false|array
     */
    public static function valid_instance_id(string $instance_id)
    {
        if (empty($instance_id)
         || @strpos($instance_id, ':') === false
         || !($instance_parts = explode(':', $instance_id, 3))
         || !is_array($instance_parts)
         || empty($instance_parts[0]) || empty($instance_parts[1]) || empty($instance_parts[2])
         || !self::valid_instance_type($instance_parts[0])
         || ($instance_parts[1] !== self::CORE_PLUGIN && $instance_parts[1] !== self::safe_escape_plugin_name($instance_parts[1]))) {
            self::st_set_error(self::ERR_INSTANCE_ID, self::_t('Invalid instance ID.'));

            return false;
        }

        return [
            'instance_type' => $instance_parts[0],
            'plugin_name'   => $instance_parts[1],
            'instance_name' => $instance_parts[2],
        ];
    }

    public static function empty_instance_details() : array
    {
        return [
            'plugin_name'                   => '',
            'plugin_www'                    => '',
            'plugin_path'                   => '',
            'plugin_is_setup'               => false,
            'plugin_is_link'                => false,
            'plugin_link_path'              => '',
            'plugin_real_path'              => '',
            'instance_type'                 => '',
            'instance_type_accepts_subdirs' => false,
            'instance_subdir'               => '',
            'instance_path'                 => '',
            'instance_full_class'           => '',
            'instance_class'                => '',
            'instance_name'                 => '',
            // Including subdir as namespace
            'instance_full_name' => '',
            'instance_file_name' => '',
            'instance_id'        => '',
            'plugin_paths'       => [],
        ];
    }

    /**
     * @param string $class Class name without subdir (if applicable)
     * @param string|bool $plugin_name
     * @param string $instance_type
     * @param string $instance_subdir Subdir provided as file system path
     *
     * @return array|bool Returns array with details about a class in core or plugin
     */
    public static function get_instance_details(string $class, $plugin_name = false, string $instance_type = '', string $instance_subdir = '')
    {
        self::st_reset_error();

        if (!($instance_type_details = self::valid_instance_type($instance_type))) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Please provide a valid instance type.'));

            return false;
        }

        $instance_type_accepts_subdirs = in_array($instance_type, self::instance_types_that_allow_subdirs(), true);

        if (!is_string($instance_subdir)) {
            $instance_subdir = '';
        } else {
            $instance_subdir = trim(trim($instance_subdir), '/\\');
        }

        if ('' !== $instance_subdir
         && (!$instance_type_accepts_subdirs || '' === ($instance_subdir = self::safe_escape_instance_subdir_path($instance_subdir)))) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Provided instance type doesn\'t support sub-directories or sub-directory is not allowed.'));

            return false;
        }

        if (empty($class)
         || !($class = self::safe_escape_class_name($class))) {
            self::st_set_error(self::ERR_INSTANCE_CLASS, self::_t('Bad class name.'));

            return false;
        }

        if ($plugin_name !== false
         && (!is_string($plugin_name) || empty($plugin_name))) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Please provide a valid plugin name.'));

            return false;
        }

        if (empty($plugin_name)) {
            $plugin_name = self::CORE_PLUGIN;
        } elseif (!($plugin_name = self::safe_escape_plugin_name($plugin_name))) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Plugin name not allowed.'));

            return false;
        }

        // We don't have core plugins...
        if ($plugin_name === self::CORE_PLUGIN && $instance_type === self::INSTANCE_TYPE_PLUGIN) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Unknown plugin name.'));

            return false;
        }

        // Atm we support only one level of subdirs, but let's make it general...
        $subdir_namespace = '';
        $subdir_path = '';
        if (!empty($instance_subdir)) {
            $subdir_namespace = str_replace('/', '\\', $instance_subdir);
            $subdir_path = trim($instance_subdir, '/\\');
        }

        $return_arr = self::empty_instance_details();
        $return_arr['loader_method'] = (!empty($instance_type_details['phs_loader_method']) ? $instance_type_details['phs_loader_method'] : false);
        $return_arr['plugin_name'] = $plugin_name;
        $return_arr['instance_type'] = $instance_type;
        $return_arr['instance_type_accepts_subdirs'] = $instance_type_accepts_subdirs;
        $return_arr['instance_subdir'] = $subdir_path;
        $return_arr['instance_class'] = $class;
        $return_arr['instance_full_class'] = '\\phs\\';

        if ($plugin_name === self::CORE_PLUGIN) {
            $return_arr['instance_full_class'] .= 'system\\core\\';
        } else {
            $return_arr['instance_full_class'] .= 'plugins\\'.$plugin_name.'\\';
        }

        if (!($instance_type_dir = self::instance_type_dir($instance_type))) {
            $instance_type_dir = '';
        } else {
            $return_arr['instance_full_class'] .= $instance_type_dir.'\\';
        }

        if (!empty($subdir_namespace)) {
            $return_arr['instance_full_class'] .= $subdir_namespace.'\\';
        }

        $return_arr['instance_full_class'] .= $class;

        switch ($instance_type) {
            default:
                self::st_set_error(self::ERR_INSTANCE, self::_t('Unknown instance type.'));

                return false;
                break;

            case self::INSTANCE_TYPE_MODEL:

                if (stripos($class, 'phs_model_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework models.'));

                    return false;
                }

                $return_arr['instance_name'] = substr($class, 10);

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_MODEL_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_CONTROLLER:

                if (stripos($class, 'phs_controller_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework controller.'));

                    return false;
                }

                $return_arr['instance_name'] = substr($class, 15);

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_CONTROLLER_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_ACTION:

                if (stripos($class, 'phs_action_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework action.'));

                    return false;
                }

                $return_arr['instance_name'] = trim(substr($class, 11), '_');

                if (empty($return_arr['instance_name'])) {
                    $return_arr['instance_name'] = 'index';
                }

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_ACTION_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_CONTRACT:

                if (stripos($class, 'phs_contract_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework contract.'));

                    return false;
                }

                $return_arr['instance_name'] = trim(substr($class, 13), '_');

                if (empty($return_arr['instance_name'])) {
                    $return_arr['instance_name'] = 'index';
                }

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_CONTRACT_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_EVENT:

                if (stripos($class, 'phs_event_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework event.'));

                    return false;
                }

                $return_arr['instance_name'] = trim(substr($class, 10), '_');

                if (empty($return_arr['instance_name'])) {
                    $return_arr['instance_name'] = 'index';
                }

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_EVENT_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_VIEW:

                if (stripos($class, 'phs_view_') !== 0
                 && strtolower($class) !== 'phs_view') {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework view.'));

                    return false;
                }

                $return_arr['instance_name'] = trim(substr($class, 8), '_');

                if (empty($return_arr['instance_name'])) {
                    $return_arr['instance_name'] = 'view';
                }

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_VIEW_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_SCOPE:

                if (stripos($class, 'phs_scope_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework scope.'));

                    return false;
                }

                $return_arr['instance_name'] = trim(substr($class, 10), '_');

                if ($plugin_name === self::CORE_PLUGIN) {
                    $return_arr['instance_path'] = PHS_CORE_SCOPE_DIR;
                } else {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/'.$instance_type_dir.'/';
                }
                break;

            case self::INSTANCE_TYPE_PLUGIN:

                if (stripos($class, 'phs_plugin_') !== 0) {
                    self::st_set_error(self::ERR_INSTANCE, self::_t('Class name is not a framework plugin.'));

                    return false;
                }

                $return_arr['instance_name'] = substr($class, 11);

                $return_arr['plugin_www'] = PHS_PLUGINS_WWW.$plugin_name.'/';
                $return_arr['plugin_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                $return_arr['instance_path'] = PHS_PLUGINS_DIR.$plugin_name.'/';
                break;
        }

        if ($subdir_path !== '') {
            $return_arr['instance_path'] .= $subdir_path.'/';
        }

        $return_arr['instance_name'] = ucfirst(strtolower($return_arr['instance_name']));
        $return_arr['instance_full_name'] = ($subdir_namespace !== '' ? $subdir_namespace.'\\' : '').$return_arr['instance_name'];

        if (!($instance_id = self::generate_instance_id($instance_type, $return_arr['instance_full_name'], $plugin_name))) {
            return false;
        }

        if (!empty(self::$instances_details[$instance_id])) {
            return self::$instances_details[$instance_id];
        }

        if ($plugin_name === self::CORE_PLUGIN) {
            $return_arr['plugin_is_setup'] = true;
        } elseif (!empty($return_arr['plugin_path'])
         && ($noslash_path = rtrim($return_arr['plugin_path'], '/'))
         && ($return_arr['plugin_is_setup'] = @file_exists($noslash_path))) {
            if (($return_arr['plugin_is_link'] = @is_link($noslash_path))
             && ($link_details = @readlink($noslash_path))) {
                $return_arr['plugin_real_path'] = $return_arr['plugin_link_path'] = $link_details;
                if (substr($link_details, 0, 1) !== '/'
                 && ($real_path = @realpath(PHS_PLUGINS_DIR.$return_arr['plugin_link_path']))) {
                    $return_arr['plugin_real_path'] = $real_path;
                }
            }
        }

        $return_arr['instance_json_file'] = self::get_plugin_details_json_file($return_arr['instance_name']);
        $return_arr['instance_id'] = $instance_id;
        $return_arr['instance_file_name'] = 'phs_'.strtolower($return_arr['instance_name']).'.php';
        $return_arr['plugin_paths'] = [];

        if (($instance_types_arr = self::get_instance_types())) {
            if ($return_arr['plugin_name'] === self::CORE_PLUGIN) {
                $path_prefix = PHS_CORE_DIR;
            } else {
                $path_prefix = $return_arr['plugin_path'];
            }

            foreach ($instance_types_arr as $type_id => $type_details) {
                $return_arr['plugin_paths'][$type_id] = $path_prefix.$type_details['dir_name'].($type_details['dir_name'] !== '' ? '/' : '');
            }

            if ($return_arr['plugin_name'] !== self::CORE_PLUGIN) {
                $return_arr['plugin_paths'][self::TEMPLATES_DIR] = $path_prefix.self::TEMPLATES_DIR.'/';
                $return_arr['plugin_paths'][self::LANGUAGES_DIR] = $path_prefix.self::LANGUAGES_DIR.'/';
            }
        }

        self::$instances_details[$return_arr['instance_id']] = $return_arr;

        return $return_arr;
    }

    public static function get_plugin_details_json_file($plugin_name)
    {
        self::st_reset_error();

        if (!($plugin_name = self::safe_escape_plugin_name($plugin_name))
         || $plugin_name === self::CORE_PLUGIN) {
            self::st_set_error(self::ERR_INSTANCE, self::_t('Plugin name not allowed.'));

            return false;
        }

        return 'phs_'.strtolower($plugin_name).'.json';
    }

    final public static function instance_types_that_allow_subdirs() : array
    {
        return [self::INSTANCE_TYPE_ACTION, self::INSTANCE_TYPE_CONTRACT, self::INSTANCE_TYPE_EVENT];
    }

    /**
     * @param string $dir
     *
     * @return string
     */
    public static function safe_escape_instance_subdir_path(string $dir) : string
    {
        if (empty($dir)
         || preg_match('@[^a-zA-Z0-9/]@', $dir)) {
            return '';
        }

        return strtolower(trim($dir));
    }

    /**
     * @param string $dir
     *
     * @return string
     */
    public static function safe_escape_instance_subdir(string $dir) : string
    {
        if (empty($dir)
         || preg_match('@[^a-zA-Z0-9_]@', $dir)) {
            return '';
        }

        return strtolower(trim($dir));
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function safe_escape_action_name(string $name) : string
    {
        if (empty($name)
         || preg_match('@[^a-zA-Z0-9_]@', $name)) {
            return '';
        }

        return strtolower($name);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function safe_escape_library_name(string $name) : string
    {
        if (empty($name)
         || preg_match('@[^a-zA-Z0-9_]@', $name)) {
            return '';
        }

        return $name;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function safe_escape_class_name(string $name) : string
    {
        if (empty($name)
         || preg_match('@[^a-zA-Z0-9_]@', $name)) {
            return '';
        }

        return $name;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_class_name_with_subdirs(string $name)
    {
        if (empty($name)
         || preg_match('@[^a-zA-Z0-9_\\]@', $name)) {
            return false;
        }

        return $name;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    public static function safe_escape_class_name_with_namespace(string $name)
    {
        if (empty($name)
         || preg_match('@[^a-zA-Z0-9_/]@', $name)) {
            return false;
        }

        return $name;
    }

    /**
     * @param string|false $name
     *
     * @return string
     */
    public static function safe_escape_plugin_name($name) : string
    {
        if (empty($name) || !is_string($name)
         || preg_match('@[^a-zA-Z0-9_]@', $name)) {
            return '';
        }

        return strtolower($name);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function safe_escape_theme_name($name) : string
    {
        if (empty($name) || !is_string($name)
         || preg_match('@[^a-zA-Z0-9_]@', $name)) {
            return '';
        }

        return strtolower($name);
    }

    public static function extract_details_from_full_namespace_name($class_with_namespace)
    {
        self::st_reset_error();

        if (empty($class_with_namespace)
         || !($class_namespace_path = explode('\\', $class_with_namespace))) {
            self::st_set_error(self::ERR_CLASS_NAME, self::_t('Seems like class name doesn\'t contain namespace.'));

            return false;
        }

        $class_name = array_pop($class_namespace_path);
        if (!empty($class_namespace_path[3])) {
            $type_and_path = '';
            for ($i = 3; isset($class_namespace_path[$i]); $i++) {
                $type_and_path .= ($type_and_path !== '' ? '\\' : '').$class_namespace_path[$i];
                unset($class_namespace_path[$i]);
            }

            $class_namespace_path[3] = $type_and_path;
        }

        if (empty($class_namespace_path[0])
         || empty($class_namespace_path[1])
         || empty($class_namespace_path[2])
         || $class_namespace_path[0] !== 'phs'
         || !in_array($class_namespace_path[1], ['plugins', 'system'])) {
            self::st_set_error(self::ERR_INSTANCE_CLASS, self::_t('Couldn\'t create instance for classes outside phs namespace.'));

            return false;
        }

        $plugin_name = $class_namespace_path[2];
        $instance_type_dir = ($class_namespace_path[3] ?? '');

        $instance_subdir = '';

        // Special case for plugin classes used in plugins dirs...
        if ($class_namespace_path[1] === 'plugins' && !isset($class_namespace_path[3])) {
            $instance_type = self::INSTANCE_TYPE_PLUGIN;
        } elseif (($guessed_type = self::_validate_instance_type_dir_from_namespace($instance_type_dir))) {
            $instance_type = (!empty($guessed_type['type']) ? $guessed_type['type'] : false);
            if (!empty($guessed_type['subdir'])) {
                $instance_subdir = $guessed_type['subdir'];
            }
        } else {
            $instance_type = false;
        }

        return [
            'class_name'      => $class_name,
            'plugin_name'     => $plugin_name,
            'instance_type'   => $instance_type,
            'instance_subdir' => $instance_subdir,
        ];
    }

    /**
     * @param bool $as_singleton
     * @param null|string $full_class_name Required for autoloader...
     *
     * @return null|static::class|\phs\libraries\PHS_Plugin|\phs\libraries\PHS_Model|\phs\libraries\PHS_Controller|\phs\libraries\PHS_Action|\phs\system\core\views\PHS_View|\phs\PHS_Scope|\phs\libraries\PHS_Contract|\phs\libraries\PHS_Undefined_instantiable
     */
    public static function get_instance(bool $as_singleton = true, ?string $full_class_name = null)
    {
        self::st_reset_error();

        if (empty($full_class_name)) {
            $full_class_name = static::class;
        }

        if (empty($full_class_name)
         || !($details = self::extract_details_from_full_namespace_name($full_class_name))
         || empty($details['class_name']) || empty($details['plugin_name']) || empty($details['instance_type'])
         || !($instance_details = self::get_instance_details($details['class_name'], $details['plugin_name'], $details['instance_type'], $details['instance_subdir']))
         || empty($instance_details['loader_method'])
         || !@method_exists(PHS::class, $instance_details['loader_method'])) {
            self::st_set_error(self::ERR_CLASS_NAME, self::_t('Cannot extract required information to instantiate class.'));

            return null;
        }

        if (empty($instance_details['plugin_is_setup'])) {
            self::st_set_error(self::ERR_PLUGIN_SETUP, self::_t('Plugin %s is not setup.', $instance_details['plugin_name'] ?? '-'));

            return null;
        }

        $loader_method = $instance_details['loader_method'];

        if ($details['instance_type'] === self::INSTANCE_TYPE_PLUGIN) {
            $obj = PHS::$loader_method($details['plugin_name']);
        } elseif ($details['instance_type'] === self::INSTANCE_TYPE_VIEW) {
            $obj = self::$loader_method($instance_details['instance_name'], $details['plugin_name'], $as_singleton);
        } elseif (!empty($instance_details['instance_type_accepts_subdirs'])) {
            $obj = PHS::$loader_method($instance_details['instance_name'], $details['plugin_name'],
                str_replace('/', '_', $details['instance_subdir']));
        } else {
            $obj = PHS::$loader_method($instance_details['instance_name'], $details['plugin_name']);
        }

        if (empty($obj) || self::st_has_error()) {
            if (self::st_debugging_mode()) {
                $error_msg = 'Error loading class ['.$full_class_name.']';
                if (self::st_has_error()) {
                    $error_msg .= ' ERROR: '.self::st_get_simple_error_message();
                }

                PHS_Logger::error($error_msg, PHS_Logger::TYPE_DEBUG);
            }

            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_INSTANCE, self::_t('Cannot instantiate provided class.'));
            }

            return null;
        }

        return $obj;
    }

    /**
     * @param null|string $class_name
     * @param string|bool $plugin_name
     * @param string|bool $instance_type
     * @param string $instance_subdir Instance subdir provided as file system path
     * @param bool $singleton
     *
     * @return null|PHS_Instantiable
     */
    final public static function get_instance_for_loads(?string $class_name = null, $plugin_name = false,
        $instance_type = false, bool $singleton = true, string $instance_subdir = '') : ?self
    {
        self::st_reset_error();

        if ($class_name === null) {
            if (!($class_details = self::extract_details_from_full_namespace_name(@get_called_class()))) {
                return null;
            }

            $class_name = $class_details['class_name'];
            $plugin_name = $class_details['plugin_name'];
            $instance_type = $class_details['instance_type'];
            $instance_subdir = $class_details['instance_subdir'];
        }

        if (!($instance_details = self::get_instance_details($class_name, $plugin_name, $instance_type, $instance_subdir))
         || empty($instance_details['instance_id'])) {
            return null;
        }

        if (!@class_exists($instance_details['instance_full_class'], false)) {
            $instance_file_path = $instance_details['instance_path'].$instance_details['instance_file_name'];

            if (!@file_exists($instance_file_path)) {
                if (PHS::st_debugging_mode()) {
                    self::st_set_error(self::ERR_INSTANCE_CLASS,
                        self::_t('Couldn\'t load instance file for class %s from plugin %s.', $class_name,
                            $instance_details['plugin_name']));
                } else {
                    self::st_set_error(self::ERR_INSTANCE_CLASS, self::_t('Couldn\'t obtain required instance.'));
                }

                return null;
            }

            ob_start();
            include_once $instance_file_path;
            ob_end_clean();

            if (!@class_exists($instance_details['instance_full_class'], false)) {
                if (PHS::st_debugging_mode()) {
                    self::st_set_error(self::ERR_INSTANCE_CLASS,
                        self::_t('Class %s not defined in %s file.', $instance_details['instance_full_class'],
                            $instance_details['instance_file_name']));
                } else {
                    self::st_set_error(self::ERR_INSTANCE_CLASS, self::_t('Couldn\'t obtain required instance after loading file.'));
                }

                return null;
            }
        }

        /** @var PHS_Model $instance_obj */
        if (!empty($singleton)
         && isset(self::$instances[$instance_details['instance_id']])) {
            return self::$instances[$instance_details['instance_id']];
        }

        $instance_class = $instance_details['instance_full_class'];

        try {
            // Check if class is abstract...
            if (($is_abstract = new \ReflectionClass($instance_class))
             && $is_abstract->isAbstract()) {
                self::st_set_error(self::ERR_INSTANCE_CLASS,
                    self::_t('Error instantiating abstract class %s.',
                        $instance_details['instance_full_class']));

                return null;
            }
        } catch (\Exception $e) {
        }

        /** @var PHS_Instantiable $instance_obj */
        if (!($instance_obj = new $instance_class($instance_details))) {
            self::st_set_error(self::ERR_INSTANCE_CLASS,
                self::_t('Error instantiating class %s from %s file.',
                    $instance_details['instance_full_class'], $instance_details['instance_file_name']));

            return null;
        }

        if (($instance_obj instanceof PHS_Undefined_instantiable)) {
            self::st_set_error(self::ERR_INSTANCE_CLASS,
                self::_t('Cannot instantiate provided class.'));

            return null;
        }

        if (!($instance_obj instanceof self)) {
            self::st_set_error(self::ERR_INSTANCE_CLASS,
                self::_t('Loaded class doesn\'t appear to be a PHS instance.'));

            return null;
        }

        if ($instance_obj->has_error()) {
            self::st_copy_error($instance_obj);

            return null;
        }

        if (!empty($singleton)) {
            self::$instances[$instance_details['instance_id']] = $instance_obj;

            return self::$instances[$instance_details['instance_id']];
        }

        return $instance_obj;
    }

    private static function _validate_instance_type_dir_from_namespace($type_dir)
    {
        if (!($types_arr = self::get_instance_types())) {
            return false;
        }

        if (!($allow_subdirs = self::get_instance_type_dirs_that_allow_subdirs())) {
            $allow_subdirs = [];
        }

        foreach ($types_arr as $type => $type_details) {
            $type_dir_parts = false;
            if ($type_details['dir_name'] === $type_dir
                // Instances with subdirs
                || (
                    false !== ($dir_pos = array_search($type_details['dir_name'], $allow_subdirs, true))
                 && false !== strpos($type_dir, '\\')
                 && ($type_dir_parts = explode('\\', $type_dir))
                 && is_array($type_dir_parts)
                 && ($path_dir = array_shift($type_dir_parts))
                 && $dir_pos === array_search($path_dir, $allow_subdirs, true)
                )
            ) {
                return [
                    'type'   => $type,
                    'subdir' => ((!empty($type_dir_parts) && is_array($type_dir_parts)) ? implode('/', $type_dir_parts) : ''),
                ];
            }
        }

        return false;
    }
}
