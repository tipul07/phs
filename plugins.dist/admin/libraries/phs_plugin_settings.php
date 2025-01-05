<?php
namespace phs\plugins\admin\libraries;

use phs\PHS;
use Exception;
use phs\PHS_Crypt;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Instantiable;
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

        $dir_entries = $plugins_model->cache_all_dir_details() ?: [];

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

    public function do_platform_import_settings_for_plugins_from_cli(array $encrypted_setting_arr, string $crypting_key, array $only_plugins = [], ?int $tenant_id = null) : bool
    {
        if (null === ($all_settings_arr = $this->do_platform_import_get_plugins_settings_array_from_encrypted_array($encrypted_setting_arr, $crypting_key))
            || !is_array($all_settings_arr)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error decoding settings from encrypted array.'));

            return false;
        }

        return $this->do_platform_import_settings_for_plugins($all_settings_arr, $only_plugins, $tenant_id);
    }

    public function do_platform_import_settings_for_plugins_from_interface(array $decoded_settings_arr, array $only_plugins = [], ?int $tenant_id = null) : bool
    {
        if (empty($decoded_settings_arr['settings']) || !is_array($decoded_settings_arr['settings'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Import JSON doesn\'t contain settngs for import..'));

            return false;
        }

        return $this->do_platform_import_settings_for_plugins($decoded_settings_arr['settings'], $only_plugins, $tenant_id);
    }

    public function do_platform_import_settings_for_plugins(array $all_settings_arr, array $only_plugins = [], ?int $tenant_id = null) : bool
    {
        PHS_Maintenance::output('Importing settings for '.count($only_plugins ?? $all_settings_arr).' plugins, tenant '.($tenant_id ?: 'Default').'...');

        foreach ($all_settings_arr as $plugin_name => $plugin_settings_arr) {
            if (empty($plugin_settings_arr) || !is_array($plugin_settings_arr)
                || ($only_plugins
                    && !in_array($plugin_name, $only_plugins, true)
                )) {
                continue;
            }

            PHS_Maintenance::output('['.($plugin_name ?: 'core').'] Importing settings...');

            if (!$this->_import_settings_for_plugin($plugin_name, $plugin_settings_arr, $tenant_id)) {
                $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error saving settings for plugin %s.', $plugin_name ?: 'core'));

                return false;
            }

            PHS_Maintenance::output('['.($plugin_name ?: 'core').'] DONE');
        }

        PHS_Maintenance::output('Imported settings for '.count($only_plugins ?? $all_settings_arr).' plugins, tenant '.($tenant_id ?: 'Default').'...');

        return true;
    }

    public function do_platform_import_settings_for_plugins_from_json_buffer(string $json_buf, string $crypting_key) : ?array
    {
        $this->reset_error();

        if (empty($json_buf)) {
            return [];
        }

        if (!($json_arr = @json_decode($json_buf, true))
            || !is_array($json_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error decoding JSON buffer to array.'));

            return null;
        }

        return $this->do_platform_import_get_plugins_settings_array_from_encrypted_array($json_arr, $crypting_key);
    }

    public function do_platform_import_get_plugins_settings_array_from_encrypted_array(array $json_arr, string $crypting_key) : ?array
    {
        $this->reset_error();

        if (!$json_arr
            || !($settings_buf = PHS_Crypt::quick_decode_from_export_array($json_arr, $crypting_key))) {
            $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, $this->_pt('Error decoding settings from encrypted array.'));

            return null;
        }

        if (!($settings_arr = @json_decode($settings_buf, true))
            || !is_array($settings_arr)) {
            $settings_arr = [];
        }

        return $settings_arr;
    }
    //
    // endregion Import plugin settings
    //

    //
    // region Export plugin settings
    //
    public function export_plugin_settings_from_interface(string $crypting_key, array $plugins_arr = [], array $export_params = []) : bool
    {
        $this->reset_error();

        $export_params['export_file_dir'] ??= '';

        if (empty($export_params['export_to'])
            || !self::valid_export_to($export_params['export_to'])) {
            $export_params['export_to'] = self::EXPORT_TO_BROWSER;
        }

        $export_params['export_file_name'] ??= 'plugin_settings_export_'.date('YmdHi').'.json';

        if (!($settings_json = $this->get_settings_for_plugins_as_encrypted_json_for_export_from_interface($crypting_key, $plugins_arr))) {
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

    public function get_settings_for_plugins_as_encrypted_array_for_export(string $crypting_key, array $plugins_arr = []) : ?array
    {
        $this->reset_error();

        if (empty($crypting_key)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error exporting plugin settings. No crypting key provided.'));

            return null;
        }

        if (!($settings_json = $this->get_settings_for_plugins_as_json_for_export($plugins_arr))) {
            return null;
        }

        if (!($result_arr = PHS_Crypt::quick_encode_buffer_for_export_as_array($settings_json, $crypting_key))) {
            $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encrypting plugin settings.'));

            return null;
        }

        return $result_arr;
    }

    public function get_settings_for_plugins_as_encrypted_json_for_export_from_interface(string $crypting_key, array $plugins_arr = []) : ?string
    {
        if (!($settings_json = $this->get_settings_for_plugins_as_json_for_export_from_interface($plugins_arr))) {
            return null;
        }

        return PHS_Crypt::quick_encode_buffer_for_export_as_json($settings_json, $crypting_key);
    }

    public function get_settings_for_plugins_as_json_for_export_from_interface(array $plugins_arr = []) : string
    {
        try {
            return @json_encode([
                'source_name' => PHS_SITE_NAME.' ('.PHS_SITEBUILD_VERSION.')',
                'source_url'  => PHS::url(['force_https' => true]),
                'settings'    => $this->_get_settings_for_plugins_as_array_for_export($plugins_arr),
            ], JSON_THROW_ON_ERROR) ?: '';
        } catch (Exception) {
            return '';
        }
    }

    public function get_settings_for_plugins_as_json_for_export(array $plugins_arr = []) : string
    {
        try {
            return @json_encode($this->_get_settings_for_plugins_as_array_for_export($plugins_arr), JSON_THROW_ON_ERROR) ?: '';
        } catch (Exception) {
            return '';
        }
    }

    protected function _get_settings_for_plugins_as_array_for_export(array $plugins_arr = []) : array
    {
        $this->reset_error();

        $plugins_list = $this->get_plugins_list_as_array() ?: [];

        $settings_arr = [];
        foreach ($plugins_list as $plugin_name => $plugin_details) {
            if (($plugins_arr
                 && !in_array($plugin_name, $plugins_arr, true))
                || !($plugin_settings = $this->_extract_settings_for_plugin_for_export($plugin_name))
            ) {
                continue;
            }

            $settings_arr[$plugin_name] = $plugin_settings;
        }

        return $settings_arr;
    }

    protected function _extract_settings_for_plugin_for_export(?string $plugin_name) : ?array
    {
        $this->reset_error();

        $plugin_name = $plugin_name ?: null;

        $plugin_instance = null;
        if ($plugin_name
            && !($plugin_instance = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid plugin provided.'));

            return null;
        }

        if (!($plugin_settings_arr = $this->_extract_settings_for_plugin_only_for_export($plugin_name, $plugin_instance))) {
            $this->set_error_if_not_set(
                self::ERR_FUNCTIONALITY, $this->_pt('Couldn\'t obtain plugin settings.')
            );

            return null;
        }

        if (null === ($models_setings_arr = $this->_get_settings_for_models_as_array_for_export(
            $plugin_instance ? $plugin_instance->get_models() : PHS::get_core_models(), $plugin_name))
        ) {
            $this->set_error_if_not_set(
                self::ERR_FUNCTIONALITY, $this->_pt('Couldn\'t obtain plugin\'s models settings.')
            );

            return null;
        }

        if (!$plugin_settings_arr['settings'] && !$models_setings_arr) {
            return [];
        }

        if ($models_setings_arr) {
            $plugin_settings_arr['models'] = $models_setings_arr;
        }

        return $plugin_settings_arr;
    }

    private function _import_settings_for_plugin(string $plugin_name, array $plugin_settings_arr, ?int $tenant_id = null) : bool
    {
        $this->reset_error();

        $plugin_instance = null;
        if ($plugin_name
            && !($plugin_instance = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error instantiating plugin %s.', $plugin_name));

            return false;
        }

        if (!empty($plugin_settings_arr['settings']) && is_array($plugin_settings_arr['settings'])
            && !$plugin_instance->save_db_settings($plugin_settings_arr['settings'], $tenant_id)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error saving settings for plugin %s.', $plugin_name ?: 'core'));

            return false;
        }

        if (!empty($plugin_settings_arr['models']) && is_array($plugin_settings_arr['models'])
            && !$this->_import_settings_for_plugin_models($plugin_name, $plugin_settings_arr['models'], $tenant_id)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, $this->_pt('Error saving models settings for plugin %s.', $plugin_name ?: 'core'));

            return false;
        }

        return true;
    }

    private function _import_settings_for_plugin_models(string $plugin_name, array $models_arr, ?int $tenant_id = null) : bool
    {
        $this->reset_error();

        foreach ($models_arr as $models_settings_arr) {
            if (empty($models_settings_arr['instance_id'])
                || empty($models_settings_arr['settings'])
                || !is_array($models_settings_arr['settings'])
                || !($instance_arr = PHS_Instantiable::valid_instance_id($models_settings_arr['instance_id']))
                || empty($instance_arr['instance_type'])
                || empty($instance_arr['instance_name'])
                || $instance_arr['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_MODEL) {
                continue;
            }

            if (!($model_obj = PHS::load_model($instance_arr['instance_name'], $plugin_name ?: null))
                || !$model_obj->save_db_settings($models_settings_arr['settings'], $tenant_id)) {
                $this->set_error(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error saving settings for model %s, plugin %s.', $instance_arr['instance_name'], $plugin_name ?: 'core'));

                return false;
            }

            PHS_Maintenance::output('['.($plugin_name ?: 'core').'] Imported settings for model ['.$instance_arr['instance_name'].']');
        }

        return true;
    }

    private function _extract_settings_for_plugin_only_for_export(?string $plugin_name, ?PHS_Plugin $plugin_instance) : ?array
    {
        $this->reset_error();

        if ($plugin_instance) {
            if (!($instance_id = $plugin_instance->instance_id())) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t obtain plugin instance id.'));

                return null;
            }
            $name = $plugin_instance->get_plugin_display_name() ?: $plugin_name;
            $version = $plugin_instance->get_plugin_version();
            $settings = $plugin_instance->get_plugin_settings();
        } else {
            $instance_id = '';
            $name = $this->_pt('Core');
            $version = PHS_VERSION;
            $settings = [];
        }

        return [
            'instance_id' => $instance_id,
            'name'        => $name,
            'version'     => $version,
            'settings'    => $settings,
            'models'      => [],
        ];
    }

    private function _get_settings_for_models_as_array_for_export(array $models_arr, ?string $plugin_name) : ?array
    {
        $models_settings_arr = [];

        foreach ($models_arr as $model_name) {
            if (null === ($settings = $this->_extract_settings_for_model($model_name, $plugin_name))) {
                $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                    $this->_pt('Couldn\'t instantiate model %s to extract settings.', $model_name ?: '-'));

                return null;
            }

            if (!$settings) {
                continue;
            }

            $models_settings_arr[] = $settings;
        }

        return $models_settings_arr;
    }

    private function _extract_settings_for_model(string $model_name, ?string $plugin_name) : ?array
    {
        $this->reset_error();

        if (empty($model_name)
            || !($model_instance = PHS::load_model($model_name, $plugin_name ?: null))
            || !($instance_id = $model_instance->instance_id())) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Couldn\'t initiate model %s to extract settings.', $model_name ?: '-'));

            return null;
        }

        if (!($settings_arr = $model_instance->get_db_settings() ?: [])) {
            return [];
        }

        return [
            'instance_id' => $instance_id,
            'name'        => $model_name,
            'version'     => $model_instance->get_model_version(),
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
