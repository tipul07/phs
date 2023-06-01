<?php
namespace phs\system\core\views;

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Language;
use phs\libraries\PHS_Controller;
use phs\libraries\PHS_Instantiable;

class PHS_View extends PHS_Instantiable
{
    public const ERR_BAD_CONTROLLER = 40000, ERR_BAD_ACTION = 40001, ERR_BAD_TEMPLATE = 40002,
    ERR_BAD_THEME = 40003, ERR_TEMPLATE_DIRS = 40004, ERR_INIT_VIEW = 40005;

    public const VIEW_CONTEXT_DATA_KEY = 'phs_view_context';

    protected string $_template = '';

    protected $_theme = '';

    // Array of directories where we check if template exists
    protected array $_template_dirs = [];

    // Array of directories where we check if template exists (others than the ones we detect)
    protected array $_extra_template_dirs = [];

    // Resulting template file
    protected string $_template_file = '';

    /** @var PHS_Controller|bool */
    protected $_controller = false;

    /** @var PHS_Action|bool */
    protected $_action = false;

    /** @var PHS_View|bool */
    protected $_parent_view = false;

    final public function instance_type() : string
    {
        return self::INSTANCE_TYPE_VIEW;
    }

    public function set_controller($controller_obj)
    {
        $this->reset_error();

        if ($controller_obj !== false
         && !($controller_obj instanceof PHS_Controller)) {
            $this->set_error(self::ERR_BAD_CONTROLLER, self::_t('Not a controller instance.'));

            return false;
        }

        $this->_controller = $controller_obj;

        return true;
    }

    public function set_action($action_obj)
    {
        $this->reset_error();

        if ($action_obj !== false
         && !($action_obj instanceof PHS_Action)) {
            $this->set_error(self::ERR_BAD_ACTION, self::_t('Not an action instance.'));

            return false;
        }

        $this->_action = $action_obj;

        return true;
    }

    public function set_parent_view($view_obj)
    {
        $this->reset_error();

        if ($view_obj !== false
         && !($view_obj instanceof self)) {
            $this->set_error(self::ERR_BAD_ACTION, self::_t('Not a view instance.'));

            return false;
        }

        $this->_parent_view = $view_obj;

        return true;
    }

    final public function is_admin_controller()
    {
        return $this->_controller && $this->_controller->is_admin_controller();
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
    final public function get_action()
    {
        return $this->_action;
    }

    /**
     * @return bool|PHS_View View that "owns" this sub-view or false if no parent view
     */
    final public function get_parent_view()
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
        if (!($action = $this->get_action())) {
            return $default_action_result;
        }

        return self::validate_array($action->get_action_result(), $default_action_result);
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

    public function get_template_file() : string
    {
        return $this->_template_file;
    }

    public function add_extra_template_dir($dir_path, $dir_www)
    {
        if (empty($dir_path)) {
            return false;
        }

        $dir_path = rtrim($dir_path, '/\\');
        if (!@is_dir($dir_path) || !@is_readable($dir_path)) {
            return false;
        }

        $dir_www = rtrim($dir_www, '/').'/';

        $this->_extra_template_dirs[$dir_path.'/'] = $dir_www;

        return true;
    }

    public function add_extra_theme_dir(string $theme_relative_dir) : bool
    {
        if (!($extra_dirs = self::st_add_extra_theme_dir($theme_relative_dir, $this->get_theme()))) {
            return false;
        }

        foreach ($extra_dirs as $dir_path => $dir_www) {
            $this->_extra_template_dirs[$dir_path] = $dir_www;
        }

        return true;
    }

    /**
     * Return resource details for found file based on themes, action, controller, parent plugin and current plugin
     *
     * @param string $file
     *
     * @return null|array
     */
    public function get_resource_details(string $file) : ?array
    {
        $this->reset_error();

        if (empty($file)
         || !($file = self::safe_escape_resource($file))) {
            $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Invalid resource file.'));

            return null;
        }

        if (!($file_details = $this->_get_file_details($file))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Resource file [%s] not found.', $file));
            }

            return null;
        }

        return $file_details;
    }

    /**
     * Return resource details for found file based on themes, action, controller, parent plugin and current plugin
     *
     * @param string $template
     *
     * @return array|bool
     */
    public function get_template_file_details(string $template)
    {
        $this->reset_error();

        if (empty($template)
         || !($template = self::safe_escape_template($template))) {
            $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Invalid template file.'));

            return false;
        }

        if (!($template_details = $this->_get_file_details($template.'.php'))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Template file [%s] not found.', $template));
            }

            return false;
        }

        return $template_details;
    }

    /**
     * Return URL to file resource
     * @param string|array $resource
     *
     * @return string
     */
    public function get_resource_url($resource)
    {
        if (!($resource_details = $this->_get_resource_details($resource))
         || empty($resource_details['full_url'])) {
            return '#resource_not_found';
        }

        return $resource_details['full_url'];
    }

    /**
     * Return full server path to resource file
     * @param string|array $file
     *
     * @return bool|string
     */
    public function get_resource_path($file)
    {
        if (!($resource_details = $this->_get_resource_details($file))
         || empty($resource_details['full_path'])) {
            return false;
        }

        return $resource_details['full_path'];
    }

    /**
     * Set template for current view
     * @param string|array $template
     * @param null|array $params
     *
     * @return array|bool
     */
    public function set_template($template, ?array $params = null)
    {
        $this->reset_error();

        if (!($template_structure = self::validate_template_resource($template, $params))) {
            $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Invalid template structure.'));

            return false;
        }

        if (!($template_structure['file'] = self::safe_escape_template($template_structure['file']))) {
            $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Invalid template file.'));

            return false;
        }

        $this->_template = '';

        if (!empty($template_structure['extra_paths']) && is_array($template_structure['extra_paths'])) {
            $this->_extra_template_dirs = [];
            foreach ($template_structure['extra_paths'] as $dir_path => $dir_www) {
                $this->add_extra_template_dir($dir_path, $dir_www);

                // if( !$this->add_extra_template_dir( $dir_path, $dir_www ) )
                // {
                //    $this->_extra_template_dirs = [];
                //    $this->set_error( self::ERR_BAD_TEMPLATE, self::_t( 'Invalid template extra directories.' ) );
                //    return false;
                // }
            }
        }

        $this->_template = $template_structure['file'];

        return $template_structure;
    }

    /**
     * Force a specific theme for current view
     * @param null|string $theme
     *
     * @return bool
     */
    public function set_theme(?string $theme) : bool
    {
        $this->reset_error();

        if (empty($theme)) {
            $theme = PHS::get_theme();
        }

        if (!($theme = PHS::valid_theme($theme))) {
            $this->set_error(self::ERR_BAD_THEME, self::_t('Invalid theme.'));

            return false;
        }

        $this->_theme = $theme;

        return true;
    }

    /**
     * @param string|array $template
     * @param null|string $force_theme
     *
     * @return bool|mixed|string
     */
    public function sub_view_if_exists($template, ?string $force_theme = null)
    {
        $this->reset_error();

        if (empty($force_theme)) {
            $view_theme = $this->get_theme();
        } else {
            $view_theme = $force_theme;
        }

        $template_params = [];
        $template_params['theme'] = $view_theme;

        if (!($template = self::validate_template_resource($template, $template_params))
         || empty($template['file'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error validating sub-view template.'));

            return false;
        }

        if (!($template_file = $this->get_template_file_details($template['file']))) {
            $this->reset_error();

            return '';
        }

        return $this->sub_view($template, $view_theme);
    }

    /**
     * @param string|array $template
     * @param null|string $force_theme
     *
     * @return bool|mixed|string
     */
    public function sub_view($template, ?string $force_theme = null)
    {
        $this->reset_error();

        $subview_obj = clone $this;

        if (empty($force_theme)) {
            $view_theme = $this->get_theme();
        } else {
            $view_theme = $force_theme;
        }

        if (!$subview_obj->set_theme($view_theme)) {
            if ($subview_obj->has_error()) {
                $this->copy_error($subview_obj);
            } else {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error setting theme for provided sub-view.'));
            }

            return false;
        }

        $subview_obj->set_parent_view($this);

        $template_params = [];
        $template_params['theme'] = $view_theme;

        if (!($subview_template = $subview_obj->set_template($template, $template_params))
         || ($subview_buffer = $subview_obj->render($template)) === null) {
            if ($subview_obj->has_error()) {
                $this->copy_error($subview_obj);
            } else {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error rendering sub-view template.'));
            }

            return false;
        }

        $hook_args = PHS_Hooks::default_buffer_hook_args();
        $hook_args['buffer_data'] = $subview_obj::get_full_data();
        $hook_args['buffer'] = $subview_buffer;

        if (!empty($subview_template['file'])
         && ($hook_args = PHS::trigger_hooks(PHS_Hooks::H_WEB_SUBVIEW_RENDERING.'_'.$subview_template['file'], $hook_args))
         && is_array($hook_args)
         && isset($hook_args['buffer']) && is_string($hook_args['buffer'])) {
            $subview_buffer = $hook_args['buffer'];
        }

        return $subview_buffer;
    }

    public function get_all_view_vars()
    {
        if (!($vars_arr = $this->get_context(self::VIEW_CONTEXT_DATA_KEY))) {
            $vars_arr = [];
        }

        return $vars_arr;
    }

    /**
     * Retrieve view variable
     *
     * @param string $key What variable to retrieve
     *
     * @return bool|mixed Variable value
     */
    public function view_var($key)
    {
        if (!($_VIEW_CONTEXT = $this->get_context(self::VIEW_CONTEXT_DATA_KEY))
         || !isset($_VIEW_CONTEXT[$key])) {
            return false;
        }

        return $_VIEW_CONTEXT[$key];
    }

    final public function set_view_var($key, $val = null)
    {
        if (($parent_view = $this->get_parent_view())) {
            $parent_view->set_view_var($key, $val);
        }

        if (!($_VIEW_CONTEXT = $this->get_context(self::VIEW_CONTEXT_DATA_KEY))) {
            $_VIEW_CONTEXT = [];
        }

        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                $_VIEW_CONTEXT[$kkey] = $kval;
            }
        } else {
            if (!is_scalar($key)) {
                return false;
            }

            $_VIEW_CONTEXT[$key] = $val;
        }

        $this->set_context(self::VIEW_CONTEXT_DATA_KEY, $_VIEW_CONTEXT);

        return true;
    }

    /**
     * Backwards compatibility
     *
     * @param string $key What variable to retrieve
     *
     * @return bool|mixed Variable value
     */
    public function context_var($key)
    {
        return $this->view_var($key);
    }

    /**
     * Render template set for current view or the template provided in parameters
     *
     * @param null|array|string $template
     * @param null|string $force_theme
     * @param null|array $params
     *
     * @return null|string
     */
    public function render($template = null, ?string $force_theme = null, ?array $params = null) : ?string
    {
        if ($template !== null
         && !$this->set_template($template)) {
            return null;
        }

        if ($force_theme !== null
         && !$this->set_theme($force_theme)) {
            return null;
        }

        if (!$this->_get_template_path()) {
            if (self::st_debugging_mode()) {
                PHS_Logger::debug(
                    sprintf('Template [%s, file: %s] not found using theme [%s].',
                        (!empty($this->_template) ? $this->_template : 'N/A'),
                        (!empty($this->_template_file) ? $this->_template_file : 'N/A'),
                        $this->get_theme()
                    ),
                    PHS_Logger::TYPE_DEBUG);
            }

            return null;
        }

        $resulting_buf = '';

        if (empty($params)) {
            $params = [];
        }

        $params['only_string_result'] = (!isset($params['only_string_result']) || !empty($params['only_string_result']));

        // sanity check...
        if (!empty($this->_template_file)
         && @file_exists($this->_template_file)) {
            ob_start();
            if (!($resulting_buf = include($this->_template_file))) {
                $resulting_buf = '';
            }

            if (empty($params['only_string_result'])) {
                ob_end_clean();
            } else {
                if (!is_string($resulting_buf)) {
                    $resulting_buf = '';
                }

                $resulting_buf .= ob_get_clean();
            }
        }

        return $resulting_buf;
    }

    protected function reset_view() : void
    {
        $this->_template = '';
        $this->_theme = '';
        $this->_template_dirs = [];
        $this->_template_file = '';
    }

    protected function _check_directory_for_template(string $path, string $www, string $language, array &$matching_arr) : void
    {
        if (@file_exists($path)
            && @is_dir($path)) {
            if (@file_exists($path.'/'.$language)
                && @is_dir($path.'/'.$language)) {
                $matching_arr[$path.'/'.$language.'/'] = $www.'/'.$language.'/';
            }

            $matching_arr[$path.'/'] = $www.'/';
        }
    }

    protected function _get_template_directories() : array
    {
        $this->_template_dirs = [];

        $current_language = PHS_Language::get_current_language();
        $themes_stack = PHS::get_all_themes_stack($this->_theme);

        if (defined('PHS_THEMES_WWW') && defined('PHS_THEMES_DIR')) {
            // Check if current theme overrides plugin template
            $plugins_check_arr = [$this->_action, $this->_controller, $this->parent_plugin(), $this->get_plugin_instance()];
            foreach ($plugins_check_arr as $instance_obj) {
                if (empty($instance_obj)
                 || !is_object($instance_obj)
                 || !($instance_obj instanceof PHS_Instantiable)
                 || $instance_obj->instance_is_core()) {
                    continue;
                }

                $plugin_name = $instance_obj->instance_plugin_name();

                if ($themes_stack) {
                    foreach ($themes_stack as $theme) {
                        $location = $theme.'/'.self::THEMES_PLUGINS_TEMPLATES_DIR.'/'.$plugin_name;
                        $this->_check_directory_for_template(
                            PHS_THEMES_DIR.$location,
                            PHS_THEMES_WWW.$location,
                            $current_language,
                            $this->_template_dirs
                        );
                    }
                }
            }
        }

        // take first dirs custom ones... (if any)
        if (!empty($this->_extra_template_dirs)) {
            foreach ($this->_extra_template_dirs as $dir_path => $dir_www) {
                $this->_check_directory_for_template(
                    rtrim($dir_path, '/\\'),
                    rtrim($dir_www, '/'),
                    $current_language,
                    $this->_template_dirs
                );
            }
        }

        if (!empty($this->_controller)
         && !$this->_controller->instance_is_core()
         && ($plugin_path = $this->_controller->instance_plugin_path())) {
            $this->_check_directory_for_template(
                $plugin_path.'/'.self::TEMPLATES_DIR,
                $this->_controller->instance_plugin_www().self::TEMPLATES_DIR,
                $current_language,
                $this->_template_dirs
            );
        }

        if (!empty($this->_action)
         && !$this->_action->instance_is_core()
         && ($plugin_path = $this->_action->instance_plugin_path())) {
            $this->_check_directory_for_template(
                $plugin_path.'/'.self::TEMPLATES_DIR,
                $this->_action->instance_plugin_www().self::TEMPLATES_DIR,
                $current_language,
                $this->_template_dirs
            );
        }

        if (defined('PHS_THEMES_WWW') && defined('PHS_THEMES_DIR')
            && $themes_stack) {
            foreach ($themes_stack as $theme) {
                $this->_check_directory_for_template(
                    PHS_THEMES_DIR.$theme,
                    PHS_THEMES_WWW.$theme,
                    $current_language,
                    $this->_template_dirs
                );
            }
        }

        return $this->_template_dirs;
    }

    /**
     * Return full path to template file based on themes, action, controller, parent plugin and current plugin
     * @return bool|string
     */
    protected function _get_template_path()
    {
        $this->reset_error();

        if (!empty($this->_template_file)) {
            return $this->_template_file;
        }

        if (empty($this->_template)) {
            $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Please provide a template first.'));

            return false;
        }

        if (!($template_details = $this->get_template_file_details($this->_template))
         || empty($template_details['full_path'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Template [%s] not found, theme [%s].', $this->_template, $this->_theme));
            }

            return false;
        }

        $this->_template_file = $template_details['full_path'];

        return true;
    }

    /**
     * Return resource details
     * @param string|array $res_file
     *
     * @return string|false
     */
    protected function _get_resource_details($res_file)
    {
        if (empty($res_file)
         || (!is_array($res_file) && !is_string($res_file))) {
            return false;
        }

        if (is_string($res_file)) {
            $res_file = [$res_file];
        }

        foreach ($res_file as $file) {
            if (empty($file)) {
                continue;
            }

            if (($resource_details = $this->get_resource_details($file))) {
                return $resource_details;
            }
        }

        return false;
    }

    private function _get_file_details($file_name) : ?array
    {
        $this->reset_error();

        if (empty($file_name)) {
            $this->set_error(self::ERR_BAD_TEMPLATE, self::_t('Please provide a resource file.'));

            return null;
        }

        if (!($dirs_list = $this->_get_template_directories())) {
            $this->set_error(self::ERR_TEMPLATE_DIRS, self::_t('Couldn\'t get includes directories.'));

            return null;
        }

        @clearstatcache();
        foreach ($dirs_list as $dir_path => $dir_www) {
            if (@file_exists($dir_path.$file_name)) {
                return [
                    'full_path' => $dir_path.$file_name,
                    'full_url'  => $dir_www.$file_name,
                    'path'      => $dir_path,
                    'url'       => $dir_www,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{"file": string, "extra_paths": array, "resource_validated": bool}
     */
    public static function default_template_resource_arr() : array
    {
        return [
            'file'               => '',
            'extra_paths'        => [],
            'resource_validated' => false,
        ];
    }

    /**
     * @param string|array $template
     * @param null|array $params
     *
     * @return null|array
     */
    public static function validate_template_resource($template, ?array $params = null) : ?array
    {
        self::st_reset_error();

        if (empty($template)
         || (!is_string($template) && !is_array($template))) {
            return null;
        }

        if (empty($params)) {
            $params = [];
        }

        if (empty($params['theme_relative_dirs']) || !is_array($params['theme_relative_dirs'])) {
            $params['theme_relative_dirs'] = [];
        }

        if (!empty($params['theme'])) {
            if (!is_string($params['theme'])
             || !($validated_theme = PHS::valid_theme($params['theme']))) {
                self::st_set_error(self::ERR_BAD_THEME, self::_t('Invalid theme passed to template.'));

                return null;
            }

            $params['theme'] = $validated_theme;
        } else {
            $params['theme'] = PHS::get_theme();
        }

        $template_structure = self::default_template_resource_arr();
        if (is_string($template)) {
            $template_structure['file'] = $template;
        } elseif (is_array($template)) {
            if (empty($template['file']) || !is_string($template['file'])) {
                return null;
            }

            if (empty($template['extra_paths']) || !is_array($template['extra_paths'])) {
                $extra_paths = [];
            } else {
                $extra_paths = [];
                foreach ($template['extra_paths'] as $dir_path => $dir_www) {
                    $full_path = rtrim(PHS::from_relative_path($dir_path), '/\\');
                    $full_www = rtrim(PHS::from_relative_url($dir_www), '/');

                    $extra_paths[$full_path.'/'] = $full_www.'/';
                }
            }

            $template_structure['file'] = $template['file'];
            $template_structure['extra_paths'] = $extra_paths;
        }

        if (!empty($params['theme_relative_dirs'])) {
            foreach ($params['theme_relative_dirs'] as $theme_dir) {
                if (is_string($theme_dir)
                 && ($extra_dirs = self::st_add_extra_theme_dir($theme_dir, $params['theme']))) {
                    foreach ($extra_dirs as $dir_path => $dir_www) {
                        $template_structure['extra_paths'][$dir_path] = $dir_www;
                    }
                }
            }
        }

        $template_structure['resource_validated'] = true;

        return $template_structure;
    }

    public static function quick_render_template($template, $plugin = false, $template_data = false)
    {
        self::st_reset_error();

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['plugin'] = $plugin;
        $view_params['template_data'] = $template_data;

        if (!($view_obj = self::init_view($template, $view_params))) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_INIT_VIEW, self::_t('Error initializing view.'));
            }

            return false;
        }

        $action_result = PHS_Action::default_action_result();

        if (($action_result['buffer'] = $view_obj->render()) === null) {
            if ($view_obj->has_error()) {
                self::st_copy_error($view_obj);
            } else {
                self::st_set_error(self::ERR_INIT_VIEW, self::_t('Error rendering template [%s].', $view_obj->get_template()));
            }

            return false;
        }

        if (empty($action_result['buffer'])) {
            $action_result['buffer'] = '';
        }

        return $action_result;
    }

    /**
     * @param string|array $template
     * @param null|array $params
     *
     * @return null|\phs\system\core\views\PHS_View
     */
    public static function init_view($template, ?array $params = null) : ?self
    {
        if (empty($params)) {
            $params = [];
        }

        if (empty($params['theme']) || !is_string($params['theme'])) {
            $params['theme'] = '';
        }
        if (empty($params['view_class'])) {
            $params['view_class'] = false;
        }
        if (empty($params['plugin'])) {
            $params['plugin'] = false;
        }
        if (empty($params['as_singleton'])) {
            $params['as_singleton'] = false;
        }

        if (empty($params['action_obj'])) {
            $params['action_obj'] = false;
        }
        if (empty($params['controller_obj'])) {
            $params['controller_obj'] = false;
        }
        if (empty($params['parent_plugin_obj'])) {
            $params['parent_plugin_obj'] = false;
        }

        if (empty($params['template_data']) || !is_array($params['template_data'])) {
            $params['template_data'] = null;
        }

        if (!($view_obj = PHS::load_view($params['view_class'], $params['plugin'], $params['as_singleton']))) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_INIT_VIEW, self::_t('Error instantiating view class.'));
            }

            return null;
        }

        if (!$view_obj->set_action($params['action_obj'])
         || !$view_obj->set_controller($params['controller_obj'])
         || !$view_obj->set_theme($params['theme'])
         || !$view_obj->set_template($template)
         || (!empty($params['parent_plugin_obj']) && !$view_obj->parent_plugin($params['parent_plugin_obj']))
        ) {
            if ($view_obj->has_error()) {
                self::st_copy_error($view_obj);
            } else {
                self::st_set_error(self::ERR_INIT_VIEW, self::_t('Error setting up view instance.'));
            }

            return null;
        }

        if (!empty($params['template_data'])) {
            $view_obj->set_view_var($params['template_data']);
        }

        return $view_obj;
    }

    /**
     * @param string $theme_relative_dir
     * @param null|string $theme
     *
     * @return null|array
     */
    public static function st_add_extra_theme_dir(string $theme_relative_dir, ?string $theme = null) : ?array
    {
        if ($theme === null) {
            $theme = PHS::get_theme();
        }

        $theme_relative_dir = rtrim($theme_relative_dir, '/\\');
        if (empty($theme_relative_dir)
         || (!empty($theme) && !($theme = PHS::valid_theme($theme)))) {
            return null;
        }

        $extra_dirs = [];
        if (defined('PHS_THEMES_WWW') && defined('PHS_THEMES_DIR')
         && ($themes_stack = PHS::get_all_themes_stack($theme))) {
            foreach ($themes_stack as $c_theme) {
                if (!empty($c_theme)
                    && @file_exists(PHS_THEMES_DIR.$c_theme.'/'.$theme_relative_dir)
                    && @is_dir(PHS_THEMES_DIR.$c_theme.'/'.$theme_relative_dir)) {
                    $extra_dirs[PHS_THEMES_DIR.$c_theme.'/'.$theme_relative_dir.'/'] = PHS_THEMES_WWW.$c_theme.'/'.$theme_relative_dir.'/';
                }
            }
        }

        return $extra_dirs;
    }

    public static function safe_escape_template($template)
    {
        if (empty($template) || !is_string($template)
         || preg_match('@[^a-zA-Z0-9_\-\./]@', $template)) {
            return false;
        }

        return str_replace('..', '', trim($template, '/'));
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public static function safe_escape_resource(string $resource) : string
    {
        if (empty($resource)
         || preg_match('@[^a-zA-Z0-9_\-\./]@', $resource)) {
            return '';
        }

        return str_replace('..', '', trim($resource, '/'));
    }

    public function __clone()
    {
        $this->reset_view();
    }
}
