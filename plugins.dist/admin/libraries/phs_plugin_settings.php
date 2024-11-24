<?php

namespace phs\plugins\admin\libraries;

use phs\PHS;
use phs\PHS_Crypt;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Plugins;

class Phs_Plugin_settings extends PHS_Library
{
    public const EXPORT_TO_FILE = 1, EXPORT_TO_OUTPUT = 2, EXPORT_TO_BROWSER = 3;

    private ?PHS_Plugin_Admin $_admin_plugin = null;

    /**
     * @param bool $include_core
     *
     * @return null|array<string, array{"plugin_info":array, "instance":\phs\libraries\PHS_Plugin}>
     */
    public function get_plugins_list_as_array(bool $include_core = true) : ?array
    {
        $this->reset_error();

        /** @var PHS_Model_Plugins $plugins_model */
        if (!($plugins_model = PHS_Model_Plugins::get_instance())) {
            $this->set_error(self::ERR_RESOURCES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!($dir_entries = $plugins_model->cache_all_dir_details())
            || !is_array($dir_entries)) {
            $dir_entries = [];
        }

        $return_arr = [];

        if ($include_core) {
            $return_arr[''] = [
                'plugin_info' => PHS_Plugin::core_plugin_details_fields(),
                'instance'    => null,
            ];
        }

        foreach ($dir_entries as $plugin_dir => $plugin_instance) {
            if (empty($plugin_instance)
                || !($plugin_info_arr = $plugin_instance->get_plugin_info())
                || empty($plugin_info_arr['plugin_name'])) {
                continue;
            }

            $return_arr[$plugin_info_arr['plugin_name']] = [
                'plugin_info' => $plugin_info_arr,
                'instance'    => $plugin_instance,
            ];
        }

        return $return_arr;
    }

    //
    // region Import plugin settings
    //
    public function decode_plugin_settings_from_encoded_array(array $encoded_arr, string $crypting_key) : ?array
    {
        if (!($settings_buf = PHS_Crypt::quick_decode_from_export_array($encoded_arr, $crypting_key))) {
            $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, $this->_pt('Error decoding settings data.'));

            return null;
        }

        if (!($settings_arr = @json_decode($settings_buf, true))
            || !is_array($settings_arr)) {
            return [];
        }

        return $settings_arr;
    }
    //
    // endregion Import plugin settings
    //

    //
    // region Export plugin settings
    //
    public function export_plugin_settings(string $crypting_key, array $plugins_arr = [], ?array $export_params = null) : bool
    {
        $this->reset_error();

        $export_params ??= [];

        if (empty($export_params['export_file_dir'])) {
            $export_params['export_file_dir'] = '';
        }

        if (empty($export_params['export_to'])
            || !self::valid_export_to($export_params['export_to'])) {
            $export_params['export_to'] = self::EXPORT_TO_BROWSER;
        }

        if (empty($export_params['export_file_name'])) {
            $export_params['export_file_name'] = 'plugin_settings_export_'.date('YmdHi').'.json';
        }

        if (!($settings_json = $this->get_settings_for_plugins_as_encrypted_json($crypting_key, $plugins_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Nothing to export.'));

            return false;
        }

        switch ($export_params['export_to']) {
            case self::EXPORT_TO_FILE:
                if (empty($export_params['export_file_dir'])
                    || !($export_file_dir = rtrim($export_params['export_file_dir'], '/\\'))
                    || !@is_dir($export_file_dir)
                    || !@is_writable($export_file_dir)) {
                    $this->set_error(self::ERR_PARAMETERS,
                        $this->_pt('No directory provided to save export data to or no rights to write in that directory.'));

                    return false;
                }

                $full_file_path = $export_file_dir.'/'.$export_params['export_file_name'];
                if (!($fd = @fopen($full_file_path, 'wb'))) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t create export file.'));

                    return false;
                }

                @fwrite($fd, $settings_json);
                @fflush($fd);
                @fclose($fd);
                break;

            case self::EXPORT_TO_BROWSER:
                if (@headers_sent()) {
                    $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Headers already sent. Cannot send export file to browser.'));

                    return false;
                }

                @header('Content-Transfer-Encoding: binary');
                @header('Content-Disposition: attachment; filename="'.$export_params['export_file_name'].'"');
                @header('Expires: 0');
                @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                @header('Pragma: public');
                @header('Content-Type: application/json;charset=UTF-8');

                echo $settings_json;
                exit;

            case self::EXPORT_TO_OUTPUT:
                echo $settings_json;
                exit;
        }

        return true;
    }

    public function get_settings_for_plugins_as_encrypted_array(string $crypting_key, array $plugins_arr = []) : ?array
    {
        $this->reset_error();

        if (empty($crypting_key)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error exporting plugin settings. No crypting key provided.'));

            return null;
        }

        if (!($settings_json = $this->get_settings_for_plugins_as_json($plugins_arr))) {
            return null;
        }

        if (!($result_arr = PHS_Crypt::quick_encode_buffer_for_export_as_array($settings_json, $crypting_key))) {
            if (PHS_Crypt::st_has_error()) {
                $this->copy_static_error();
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encrypting plugin settings.'));
            }

            return null;
        }

        return $result_arr;
    }

    public function get_settings_for_plugins_as_encrypted_json(string $crypting_key, array $plugins_arr = []) : ?string
    {
        if (!($settings_json = $this->get_settings_for_plugins_as_json($plugins_arr))) {
            return null;
        }

        return PHS_Crypt::quick_encode_buffer_for_export_as_json($settings_json, $crypting_key);
    }

    public function get_settings_for_plugins_as_json(array $plugins_arr = []) : string
    {
        if (!($return_arr = @json_encode($this->get_settings_for_plugins_as_array($plugins_arr)))) {
            $return_arr = '';
        }

        return $return_arr;
    }

    public function get_settings_for_plugins_as_array(array $plugins_arr = []) : array
    {
        $this->reset_error();

        if (!($plugins_list = $this->get_plugins_list_as_array())) {
            $plugins_list = [];
        }

        $settings_arr = [];
        foreach ($plugins_list as $plugin_name => $plugin_details) {
            if ((!empty($plugins_arr)
                 && !in_array($plugin_name, $plugins_arr, true))
                || !($plugin_settings = $this->extract_settings_for_plugin($plugin_name, $plugin_details['instance'] ?? null))
            ) {
                continue;
            }

            $settings_arr[$plugin_name] = $plugin_settings;
        }

        return $settings_arr;
    }

    public function extract_settings_for_plugin(?string $plugin_name, ?PHS_Plugin $plugin_instance = null) : ?array
    {
        $this->reset_error();

        $is_core = ($plugin_name === '' || $plugin_name === null);
        if (
            // Instantiate plugin (if instance is not already provided)
            (!$is_core
             && $plugin_instance === null
             && !($plugin_instance = PHS::load_plugin($plugin_name))
            )
            // Instance checks... (keep separate from above statement)
            || (!$is_core
                && !($plugin_instance instanceof PHS_Plugin)
            )
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid plugin provided.'));

            return null;
        }

        if ($is_core) {
            $plugin_instance = null;
            $plugin_instance_id = '';
            if (!($models_arr = PHS::get_core_models())) {
                $models_arr = [];
            }
        } else {
            if (!($plugin_instance_id = $plugin_instance->instance_id())) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t obtain plugin instance id.'));

                return null;
            }

            if (!($models_arr = $plugin_instance->get_models())) {
                $models_arr = [];
            }
        }

        $plugin_settings_arr = [];

        if ($plugin_instance
            && ($settings_arr = $plugin_instance->get_plugin_settings())) {
            $plugin_settings_arr[$plugin_instance_id] = $settings_arr;
        }

        foreach ($models_arr as $model_name) {
            if (!($settings = $this->_extract_settings_for_model($model_name, $plugin_name))) {
                $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                    $this->_pt('Couldn\'t instantiate model %s to extract settings.',
                        (empty($model_name) ? '-' : $model_name)));

                return null;
            }

            // No need to export no settings...
            if (empty($settings['instance_id'])
                || empty($settings['settings'])) {
                continue;
            }

            $plugin_settings_arr[$settings['instance_id']] = $settings['settings'];
        }

        return $plugin_settings_arr;
    }

    private function _extract_settings_for_model(string $model_name, ?string $plugin = null) : ?array
    {
        $this->reset_error();

        if (empty($model_name)
            || !($model_instance = PHS::load_model($model_name, $plugin ?: null))
            || !($instance_id = $model_instance->instance_id())) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Couldn\'t initiate model %s to extract settings.',
                    (empty($model_name) ? '-' : $model_name)));

            return null;
        }

        if (!($settings_arr = $model_instance->get_db_settings())) {
            $settings_arr = [];
        }

        return [
            'instance_id' => $instance_id,
            'settings'    => $settings_arr,
        ];
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (empty($this->_admin_plugin)
            && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES,
                $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
    //
    // endregion Export plugin settings
    //

    public static function valid_export_to(int $export_to) : bool
    {
        return !empty($export_to)
               && in_array($export_to, [self::EXPORT_TO_FILE, self::EXPORT_TO_OUTPUT, self::EXPORT_TO_BROWSER], true);
    }
}
