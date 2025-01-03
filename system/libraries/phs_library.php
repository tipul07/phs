<?php
namespace phs\libraries;

// ! All plugin libraries should extend this class
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
        if (empty($this->_parent_plugin)) {
            return null;
        }

        return $this->_parent_plugin;
    }

    /**
     * @return array Array with settings of plugin of current model
     */
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

    public static function get_library_default_location_paths() : array
    {
        return [
            'library_file' => '',
            'library_path' => '',
            'library_www'  => '',
        ];
    }
}
