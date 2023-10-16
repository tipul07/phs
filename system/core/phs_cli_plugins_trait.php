<?php
namespace phs\traits;

use phs\PHS;
use phs\libraries\PHS_Instantiable;
use phs\system\core\models\PHS_Model_Plugins;

/**
 * Adds plugin functionality for CLI apps
 * @method \phs\libraries\PHS_Error::set_error()
 * @method \phs\libraries\PHS_Error::copy_error()
 * @method \phs\libraries\PHS_Language::_t()
 */
trait PHS_Cli_plugins_trait
{
    /** @var null|\phs\system\core\models\PHS_Model_Plugins */
    protected ?PHS_Model_Plugins $_plugins_model = null;

    public function get_plugins_as_dirs()
    {
        if (!($plugins_model = $this->_get_plugins_model())) {
            return false;
        }

        if (($plugins_arr = $plugins_model->get_all_plugin_names_from_dir()) === false
         || !is_array($plugins_arr)) {
            if (!$plugins_model->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining plugins list.'));
            } else {
                $this->copy_error($plugins_model);
            }

            return false;
        }

        return $plugins_arr;
    }

    protected function _gather_plugin_info(string $plugin_name) : array
    {
        $plugin_info = self::_get_default_plugin_info_definition();

        $plugin_info['dir_name'] = $plugin_name;

        // If plugin has a JOSN available, try getting as much data from it
        if (($json_arr = PHS_Instantiable::get_plugin_json_info($plugin_name))) {
            foreach (['name', 'version', 'vendor_id', 'vendor_name'] as $field) {
                if (!empty($json_arr[$field])) {
                    $plugin_info[$field] = $json_arr[$field];
                }
            }
            if (!empty($json_arr['agent_jobs']) && is_array($json_arr['agent_jobs'])) {
                $plugin_info['agent_jobs'] = $json_arr['agent_jobs'];
            }
            if (!empty($json_arr['models']) && is_array($json_arr['models'])) {
                $plugin_info['models_count'] = count($json_arr['models']);
                foreach ($json_arr['models'] as $model_name) {
                    $new_model = self::_get_default_model_info_definition();
                    $new_model['name'] = $model_name;

                    $plugin_info['models'][] = $new_model;
                }
            }
        }

        // Try to instantiate plugin...
        /** @var \phs\libraries\PHS_Plugin $plugin_obj */
        if (!($plugin_obj = PHS::load_plugin($plugin_name))) {
            PHS::st_reset_error();

            return $plugin_info;
        }

        if (($instance_info = $plugin_obj->get_plugin_info())) {
            foreach (['is_core', 'is_distribution', 'is_installed', 'is_active', 'is_upgradable', 'db_version'] as $field) {
                $plugin_info[$field] = $instance_info[$field];
            }
        }

        // Get model details...
        if (empty($plugin_info['models']) || !is_array($plugin_info['models'])) {
            $plugin_info['models'] = [];
            if (($models_arr = $plugin_obj->get_models())) {
                $plugin_info['models_count'] = count($models_arr);
                foreach ($models_arr as $model_name) {
                    $new_model = self::_get_default_model_info_definition();
                    $new_model['name'] = $model_name;

                    $plugin_info['models'][] = $new_model;
                }
            }
        }

        if (!empty($plugin_info['models']) && is_array($plugin_info['models'])) {
            $new_models = [];
            foreach ($plugin_info['models'] as $model_arr) {
                /** @var \phs\libraries\PHS_Model $model_obj */
                if (empty($model_arr['name'])
                 || !($model_obj = PHS::load_model($model_arr['name'], $plugin_name))) {
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

        return $plugin_info;
    }

    /**
     * @param string $plugin_name
     * @param null|array $plugin_info
     *
     * @return bool
     */
    protected function _echo_plugin_details(string $plugin_name, ?array $plugin_info = null) : bool
    {
        if (empty($plugin_info)
            && !($plugin_info = $this->_gather_plugin_info($plugin_name))) {
            $this->_echo_error(self::_t('Error obtaining plugin details for plugin %s.', $this->cli_color($plugin_name, 'red')));

            return false;
        }

        $yes_str = self::_t('Yes');
        $no_str = self::_t('No');

        $this->_echo(self::_t('Plugin name').': '.$this->cli_color($plugin_info['name'], 'green'));
        $this->_echo(self::_t('Vendor').': '.$this->cli_color($plugin_info['vendor_name'], 'green').' ('.$plugin_info['vendor_id'].')');
        $this->_echo(self::_t('Version')
                      .': Script - '.$this->cli_color($plugin_info['version'], 'green')
                      .', Database - '.$this->cli_color($plugin_info['db_version'], 'green')
                      .(!empty($plugin_info['is_upgradable']) ? ' ['.$this->cli_color(self::_t('Upgradable'), 'red').']' : ''));

        $this->_echo(self::_t('Flags').': '
                      .self::_t('Core plugin').': '.(!empty($plugin_info['is_core']) ? $yes_str : $no_str).', '
                      .self::_t('Distribution plugin').': '.(!empty($plugin_info['is_distribution']) ? $yes_str : $no_str).', '
                      .self::_t('Is installed').': '.(!empty($plugin_info['is_installed']) ? $yes_str : $no_str).', '
                      .self::_t('Is active').': '.(!empty($plugin_info['is_active']) ? $yes_str : $no_str).'.'
        );

        $this->_echo(self::_t('Models').':');
        if (empty($plugin_info['models']) || !is_array($plugin_info['models'])) {
            $this->_echo('  '.self::_t('N/A'));
        } else {
            foreach ($plugin_info['models'] as $model_arr) {
                $this->_echo('  - '.$this->cli_color($model_arr['name'], 'green').' ('.$model_arr['driver'].', v'.$model_arr['version'].'), '
                              .self::_t('Main table').': '.$model_arr['main_table'].', '
                              .self::_t('Tables').': '.@implode(', ', $model_arr['tables'])
                );
            }
        }

        $this->_echo(self::_t('Agent jobs').':');
        if (empty($plugin_info['agent_jobs']) || !is_array($plugin_info['agent_jobs'])) {
            $this->_echo('  '.self::_t('N/A'));
        } else {
            foreach ($plugin_info['agent_jobs'] as $job_arr) {
                $route_str = PHS::route_from_parts(PHS::convert_route_to_short_parts($job_arr['route']));

                $this->_echo('  - '.$this->cli_color($job_arr['title'], 'green').', '
                              .self::_t('Route').': '.$route_str.', '
                              .self::_t('Runs once %ss', $job_arr['timed_seconds'])
                );
            }
        }

        return true;
    }

    protected function _get_plugins_model() : ?PHS_Model_Plugins
    {
        if (empty($this->_plugins_model)
         && !$this->_load_plugins_model()) {
            return null;
        }

        return $this->_plugins_model;
    }

    private function _load_plugins_model() : bool
    {
        if (empty($this->_plugins_model)
         && !($this->_plugins_model = PHS_Model_Plugins::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error instantiating plugins model.'));

            return false;
        }

        return true;
    }

    protected static function _get_default_model_info_definition() : array
    {
        return [
            'name'       => '',
            'driver'     => '',
            'version'    => '',
            'main_table' => '',
            'tables'     => [],
        ];
    }

    protected static function _get_default_plugin_info_definition() : array
    {
        return [
            'is_core'         => false,
            'is_distribution' => false,
            'is_installed'    => false,
            'is_active'       => false,
            'is_upgradable'   => false,
            'dir_name'        => '',
            'name'            => '',
            'version'         => '',
            'db_version'      => '',
            'vendor_id'       => '',
            'vendor_name'     => '',
            'models_count'    => 0,
            'models'          => [],
            'agent_jobs'      => [],
        ];
    }
}
