<?php
namespace phs\libraries;

// ! All plugin libraries should extend this class
use phs\PHS;

abstract class PHS_Library extends PHS_Registry
{
    private ?PHS_Plugin $_parent_plugin = null;

    private array $_location_paths = [];

    public function set_library_location_paths(array $paths) : array
    {
        $this->_location_paths = self::validate_array($paths, self::get_library_default_location_paths());

        $this->_location_paths['library_path'] = rtrim($this->_location_paths['library_path'], '/\\').'/';
        $this->_location_paths['library_www'] = rtrim($this->_location_paths['library_www'], '/').'/';

        if ($this->_location_paths['library_path'] === '/') {
            $this->_location_paths['library_path'] = '';
        }
        if ($this->_location_paths['library_www'] === '/') {
            $this->_location_paths['library_www'] = '';
        }

        return $this->_location_paths;
    }

    public function get_library_location_paths() : array
    {
        return $this->_location_paths;
    }

    final public function parent_plugin(?PHS_Plugin $plugin_obj = null) : ?PHS_Plugin
    {
        if ($plugin_obj === null) {
            return $this->_parent_plugin;
        }

        if (!($plugin_obj instanceof PHS_Plugin)) {
            return null;
        }

        $this->_parent_plugin = $plugin_obj;

        return $this->_parent_plugin;
    }

    /**
     * Gets plugin instance where current instance is running
     *
     * @return null|PHS_Plugin
     */
    final public function get_plugin_instance() : ?PHS_Plugin
    {
        return $this->_parent_plugin;
    }

    public function get_plugin_settings() : array
    {
        if (!($plugin_obj = $this->get_plugin_instance())) {
            return [];
        }

        return $plugin_obj->get_db_settings() ?: $plugin_obj->get_default_settings();
    }

    final public function quick_render_template_for_buffer($template, ?array $template_data = null) : ?string
    {
        if (!($plugin_obj = $this->get_plugin_instance())) {
            return '';
        }

        return $plugin_obj->quick_render_template_for_buffer($template, $template_data);
    }

    // Overwrite this method if you want the library to be loaded always as singleton
    public static function instances_as_singletons() : bool
    {
        return true;
    }

    public static function get_library_default_location_paths() : array
    {
        return [
            'library_file' => '',
            'library_path' => '',
            'library_www'  => '',
        ];
    }

    public static function get_instance(array $init_params = [], ?bool $as_singleton = null) : ?static
    {
        if (!($library_details = self::extract_details_from_full_namespace_name(static::class))) {
            return null;
        }

        if (!$library_details['plugin']) {
            return PHS::load_core_library_by_classname(
                static::class,
                [
                    'init_params'  => $init_params,
                    'as_singleton' => $as_singleton ?? static::instances_as_singletons(),
                ]
            );
        }

        if (!($plugin_obj = PHS::load_plugin($library_details['plugin']))
           || !$plugin_obj->plugin_active()
        ) {
            self::st_set_error_if_not_set(
                self::ERR_FUNCTIONALITY,
                self::_t('Couldn\'t load library from plugin [%s]', $library_details['plugin'])
            );

            return null;
        }

        if (!($library_obj = $plugin_obj->load_library($library_details['library_file'],
            [
                'full_class_name' => static::class,
                'init_params'     => $init_params,
                'as_singleton'    => $as_singleton ?? static::instances_as_singletons(),
                'path_in_lib_dir' => $library_details['path_in_lib_dir'],
            ]))
        ) {
            self::st_copy_or_set_error(
                $plugin_obj,
                self::ERR_FUNCTIONALITY,
                self::_t('Couldn\'t load library [%s] from plugin [%s]',
                    $library_details['library_name'], $library_details['plugin'])
            );

            return null;
        }

        return $library_obj;
    }

    public static function extract_details_from_full_namespace_name(string $class_with_namespace) : ?array
    {
        if (!($namespace_parts = explode('\\', ltrim($class_with_namespace, '\\')))
           || !($library_name = array_pop($namespace_parts))
           || ($namespace_parts[0] ?? '') !== 'phs'
           || !($plugin_name = ($namespace_parts[2] ?? ''))
           || ($namespace_parts[3] ?? '') !== 'libraries'
           || !in_array(($namespace_parts[1] ?? ''), ['plugins', 'system'], true)
        ) {
            return null;
        }

        $path_in_lib_dir = '';
        if (!empty($namespace_parts[4])) {
            for ($i = 4; !empty($namespace_parts[$i]); $i++) {
                $path_in_lib_dir .= ($path_in_lib_dir !== '' ? '/' : '').$namespace_parts[$i];
            }
        }

        $library_file = strtolower($library_name);

        return [
            'plugin'          => $plugin_name === 'core' ? null : $plugin_name,
            'library_name'    => $library_name,
            'library_file'    => (!str_starts_with($library_file, 'phs_') ? 'phs_' : '').$library_file,
            'path_in_lib_dir' => $path_in_lib_dir,
        ];
    }

    public static function is_core_library(?string $library_class = null) : bool
    {
        $library_class ??= static::class;

        return str_starts_with(strtolower(ltrim($library_class, '\\')), 'phs\\system\\core\\libraries\\');
    }
}
