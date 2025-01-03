<?php
namespace phs\cli\apps\libraries;

use phs\PHS;
use phs\PHS_Cli;
use phs\PHS_Crypt;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Instantiable;
use phs\traits\PHS_Cli_plugins_trait;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Plugins;

/**
 * Use import/export functionality
 * @method \phs\libraries\PHS_Error::set_error()
 * @method \phs\libraries\PHS_Error::copy_error()
 * @method \phs\libraries\PHS_Error::reset_error()
 * @method \phs\libraries\PHS_Language::_t()
 */
trait PHS_Export_import
{
    //
    // region Common for import and export
    //
    protected function _get_phs_root_path(string $forced_dir = '', bool $slash_ended = true) : string
    {
        static $root_path = '';

        if (!empty($root_path)) {
            return $root_path.($slash_ended ? '/' : '');
        }

        if (defined('PHS_PATH')) {
            $root_path = rtrim(PHS_PATH, '/');

            return $root_path.($slash_ended ? '/' : '');
        }

        if (empty($forced_dir)) {
            $forced_dir = @realpath(__DIR__.'/../../../');
        }

        // "Hardcoded" version without caching...
        if (empty($forced_dir)
         || !($forced_dir = rtrim($forced_dir, '/'))) {
            return __DIR__.'/../../..'.($slash_ended ? '/' : '');
        }

        $root_path = $forced_dir;

        return $root_path.($slash_ended ? '/' : '');
    }

    protected function _get_phs_uploads_path($forced_dir = '', $slash_ended = true) : string
    {
        return (defined('PHS_UPLOADS_DIR')
                ? rtrim(PHS_UPLOADS_DIR, '/')
                : $this->_get_phs_root_path($forced_dir, true).'_uploads')
               .($slash_ended ? '/' : '');
    }

    protected function platform_import_export_json_structure() : array
    {
        return [
            'version'   => 1,
            'themes'    => [],
            'languages' => [],
            'plugins'   => [
                'symlinks' => [],
                'plugins'  => [],
            ],
        ];
    }
    //
    // endregion Common for import and export
    //

    //
    // region Export functionality
    //
    protected function platform_export_json_structure() : array
    {
        return [
            'export' => ['all', 'symlinks', 'plugin_settings', 'themes', 'languages'],
            // Because of sensitive data, we will delete export action file after the export
            'delete_json_file_after_action' => true,
            // this can be null (all plugins) or an array of plugin names...
            'only_plugins' => null,
            // this can be null (all themes) or an array of theme names...
            'only_themes' => null,
            // this can be null (all languages) or an array of language names...
            'only_languages' => null,
            'crypt_key'      => '',
            'export_path'    => $this->_get_phs_uploads_path(false),
            'export_file'    => 'export_setup_'.time().'.json',
        ];
    }

    protected function _do_platform_delete_action_file($action_json_arr) : ?bool
    {
        if (!empty($action_json_arr['delete_json_file_after_action'])
         && !empty($action_json_arr['action_file'])
         && @file_exists($action_json_arr['action_file'])) {
            return @unlink($action_json_arr['action_file']);
        }

        return null;
    }

    protected function _do_platform_export_action_to_file(?array $action_json_arr) : bool
    {
        $this->reset_error();

        if (!($buf = $this->_do_platform_export_action_as_buffer($action_json_arr))
         || empty($action_json_arr['export_full_file'])
         || !@file_put_contents($action_json_arr['export_full_file'], $buf)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error exporting data to file.'));
            }

            return false;
        }

        return true;
    }

    protected function _do_platform_export_action_as_buffer(array $action_json_arr) : ?string
    {
        $this->reset_error();

        if (!($arr = $this->_do_platform_export_action_as_array($action_json_arr))
         || !($buf = @json_encode($arr))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining export data.'));
            }

            return null;
        }

        return $buf;
    }

    protected function _do_platform_export_action_as_array(?array $action_json_arr) : ?array
    {
        $this->reset_error();

        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Plugins $plugins_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return null;
        }

        if (!($plugins_dirs_arr = $plugins_model->get_all_plugin_names_from_dir())) {
            $plugins_dirs_arr = [];
        }

        $repo_links = [];
        $repo_plugins = [];
        $all_plugins = true;
        if (!empty($action_json_arr['export_symlinks'])
         || !empty($action_json_arr['export_plugin_settings'])) {
            foreach ($plugins_dirs_arr as $plugin_name) {
                if ((!empty($action_json_arr['only_plugins'])
                     && !in_array($plugin_name, $action_json_arr['only_plugins'], true))
                    || !($instance_details = PHS_Instantiable::get_instance_details(
                        'PHS_Plugin_'.ucfirst(strtolower($plugin_name)),
                        $plugin_name,
                        PHS_Instantiable::INSTANCE_TYPE_PLUGIN))
                    || empty($instance_details['plugin_is_setup'])
                    || empty($instance_details['plugin_is_link'])) {
                    $all_plugins = false;
                    continue;
                }

                $repo_links[$instance_details['plugin_name']] = $instance_details['plugin_link_path'];
                $repo_plugins[] = $instance_details['plugin_name'];
            }
        }

        $export_arr = $this->platform_import_export_json_structure();
        if ($action_json_arr['export_themes']) {
            $export_arr['themes'] = [];
        }

        if ($action_json_arr['export_languages']) {
            $export_arr['languages'] = [];
        }

        if (!empty($action_json_arr['export_symlinks'])
         || !empty($action_json_arr['export_plugin_settings'])) {
            $export_plugins_arr = [];
            if ($action_json_arr['export_symlinks']) {
                $export_plugins_arr['symlinks'] = $repo_links;
            }

            if ($action_json_arr['export_plugin_settings']
             && null === ($export_plugins_arr['settings']
                = $admin_plugin->get_settings_for_plugins_as_encrypted_array($action_json_arr['crypt_key'],
                    ($all_plugins ? [] : $repo_plugins)))) {
                if ($admin_plugin->has_error()) {
                    $this->copy_error($admin_plugin, self::ERR_FUNCTIONALITY);
                } else {
                    $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining plugin settings.'));
                }

                return null;
            }

            if (!empty($export_plugins_arr)) {
                $export_arr['plugins'] = $export_plugins_arr;
            }
        }

        return $export_arr;
    }

    protected function _setup_action_import_json_structure() : array
    {
        return [
            'import' => ['all', 'symlinks', 'plugin_settings', 'themes', 'languages'],
            // Because of sensitive data, we will delete import action file after the import
            'delete_json_file_after_action' => true,
            // this can be null (all plugins) or an array of plugin names...
            'only_plugins' => null,
            // this can be null (all themes) or an array of theme names...
            'only_themes' => null,
            // this can be null (all languages) or an array of language names...
            'only_languages' => null,
            'crypt_key'      => '',
            'import_file'    => $this->_get_phs_uploads_path(false).'/import_setup.json',
        ];
    }

    protected function _do_platform_import_action_read_import_file($import_file) : ?array
    {
        $this->reset_error();

        if (empty($import_file)
         || !@file_exists($import_file)
         || !($buf = @file_get_contents($import_file))
         || !($import_arr = @json_decode($buf, true))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error parsing JSON details from input file.'));

            return null;
        }

        return $import_arr;
    }

    protected function _do_platform_import_action($action_json_arr) : bool
    {
        $this->reset_error();

        if (!($action_json_arr = $this->_validate_setup_action_import_json_structure($action_json_arr))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error validating import JSON structure.'));

            return false;
        }

        if (empty($action_json_arr['import_file'])
         || !($import_arr = $this->_do_platform_import_action_read_import_file($action_json_arr['import_file']))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Error obtaining import JSON data.'));
            }

            return false;
        }

        $imported_symlinks_arr = null;
        // Make sure we have symlinks to plugins
        if (!empty($action_json_arr['import_symlinks'])
         && isset($import_arr['plugins']['symlinks'])) {
            if (null === ($imported_symlinks_arr = $this->_do_platform_import_action_for_plugin_symlinks($import_arr['plugins']['symlinks'], $action_json_arr['only_plugins']))) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_PARAMETERS, self::_t('Error importing symlinks from import file.'));
                }

                return false;
            }
        }

        // Import plugin settings for provided plugins...
        if (!empty($action_json_arr['import_plugin_settings'])
            && isset($import_arr['plugins']['settings'])
            && is_array($import_arr['plugins']['settings'])
            && !empty($imported_symlinks_arr)
            && ($plugin_names = @array_keys($imported_symlinks_arr))
            && !$this->_do_platform_import_settings_for_plugins($import_arr['plugins']['settings'], $action_json_arr['crypt_key'], $plugin_names)) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS,
                self::_t('Error importing plugin settings from import file.'));

            return false;
        }

        return true;
    }

    /**
     * @param array $symlinks
     * @param null|array $only_plugins
     *
     * @return null|array
     */
    protected function _do_platform_import_action_for_plugin_symlinks(array $symlinks, ?array $only_plugins = null) : ?array
    {
        $this->reset_error();

        if (empty($only_plugins)) {
            $only_plugins = [];
        }

        if (empty($symlinks)) {
            PHS_Maintenance::output('Importing symlinks... Nothing to import');

            return [];
        }

        PHS_Maintenance::output('Cheking '.count($symlinks).' symlinks for import...');

        $imported_symlinks = [];
        foreach ($symlinks as $plugin_name => $symlink) {
            if (!empty($only_plugins)
             && in_array($plugin_name, $only_plugins, true)) {
                continue;
            }

            if (($repo_dir = @dirname($symlink))
             && PHS_Maintenance::plugin_is_symlinked_with_repo($plugin_name, $repo_dir)) {
                PHS_Maintenance::output('Plugin ['.$plugin_name.'] already symlinked to ['.$symlink.']');
                $imported_symlinks[$plugin_name] = $symlink;
                continue;
            }

            if (!$repo_dir
             || !PHS_Maintenance::symlink_plugin_from_repo($plugin_name, $repo_dir)) {
                if (PHS_Maintenance::st_has_error()) {
                    $this->copy_static_error();
                } else {
                    $this->set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t locate plugin repository directory %s for plugin %s.',
                        (!empty($repo_dir) ? $repo_dir : 'N/A'), $plugin_name));
                }

                return null;
            }

            PHS_Maintenance::output('Imported plugin ['.$plugin_name.'] as symlink to ['.$symlink.']');
            $imported_symlinks[$plugin_name] = $symlink;
        }

        PHS_Maintenance::output('Imported '.count($imported_symlinks).' symlinks...');

        return $imported_symlinks;
    }

    /**
     * @param array $encrypted_setting_arr
     * @param string $crypting_key
     * @param false|array $only_plugins
     *
     * @return bool
     */
    protected function _do_platform_import_settings_for_plugins(array $encrypted_setting_arr, string $crypting_key, ?array $only_plugins = null) : bool
    {
        if (null === ($all_settings_arr = $this->_do_platform_import_get_plugins_settings_array_from_encrypted_array($encrypted_setting_arr, $crypting_key))
         || !is_array($all_settings_arr)) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error decoding settings from encrypted array.'));

            return false;
        }

        PHS_Maintenance::output('Importing settings for '.count($all_settings_arr).' plugins...');

        foreach ($all_settings_arr as $plugin_name => $plugin_settings_arr) {
            if (empty($plugin_name)
             || empty($plugin_settings_arr) || !is_array($plugin_settings_arr)
             || (!empty($only_plugins)
                && !in_array($plugin_name, $only_plugins, true)
             )) {
                continue;
            }

            if (!$this->_import_settings_for_plugin($plugin_name, $plugin_settings_arr)) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error saving settings for plugin %s.', $plugin_name));
                }

                return false;
            }

            PHS_Maintenance::output('Imported settings for plugin ['.$plugin_name.']');
        }

        PHS_Maintenance::output('Imported settings for '.count($all_settings_arr).' plugins...');

        return true;
    }

    /**
     * @param string $json_buf
     * @param string $crypting_key
     *
     * @return null|array
     */
    protected function _do_platform_import_settings_for_plugins_from_json_buffer(string $json_buf, string $crypting_key) : ?array
    {
        $this->reset_error();

        if (empty($json_buf)) {
            return [];
        }

        if (!($json_arr = @json_decode($json_buf, true))
         || !is_array($json_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error decoding JSON buffer to array.'));

            return null;
        }

        return $this->_do_platform_import_get_plugins_settings_array_from_encrypted_array($json_arr, $crypting_key);
    }

    /**
     * @param array $json_arr
     * @param string $crypting_key
     *
     * @return null|array
     */
    protected function _do_platform_import_get_plugins_settings_array_from_encrypted_array(array $json_arr, string $crypting_key) : ?array
    {
        $this->reset_error();

        if (!($settings_buf = PHS_Crypt::quick_decode_from_export_array($json_arr, $crypting_key))) {
            if (PHS_Crypt::st_has_error()) {
                $this->copy_static_error();
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error decoding settings from encrypted array.'));
            }

            return null;
        }

        if (!($settings_arr = @json_decode($settings_buf, true))
         || !is_array($settings_arr)) {
            $settings_arr = [];
        }

        return $settings_arr;
    }

    /**
     * @param null|array $json_arr
     * @param array $structure
     *
     * @return null|array
     */
    private function _validate_import_export_json_structure(?array $json_arr, array $structure) : ?array
    {
        $this->reset_error();

        if (empty($structure)) {
            return $json_arr;
        }

        foreach ($structure as $key => $val) {
            if (!array_key_exists($key, $json_arr)) {
                $json_arr[$key] = $val;
                continue;
            }

            if (is_array($val)) {
                if (!is_array($json_arr[$key])
                    && !is_scalar($json_arr[$key])) {
                    $this->set_error(self::ERR_PARAMETERS, self::_t('Key in configuration file %s can be: %s',
                        $key, @implode(', ', $val)));

                    return null;
                }

                if (is_scalar($json_arr[$key])) {
                    $json_arr[$key] = [$json_arr[$key]];
                }

                foreach ($json_arr[$key] as $json_val) {
                    if (!in_array($json_val, $val, true)) {
                        $this->set_error(self::ERR_PARAMETERS, self::_t('Key in configuration file %s can be: %s',
                            $key, @implode(', ', $val)));

                        return null;
                    }
                }
            }
        }

        return $json_arr;
    }

    private function _platform_import_export_decode_action_file($action_file) : array
    {
        if (!@file_exists($action_file)
         || !@is_readable($action_file)
         || !($action_json_buf = @file_get_contents($action_file))
         || !($action_json_arr = @json_decode($action_json_buf, true))
        ) {
            return [];
        }

        $action_json_arr['action_file'] = $action_file;

        return $action_json_arr;
    }

    /**
     * @param array $json_arr
     *
     * @return null|array
     */
    private function _validate_platform_export_action_json_structure(array $json_arr) : ?array
    {
        $this->reset_error();

        if (!($return_arr = $this->_validate_import_export_json_structure($json_arr, $this->platform_export_json_structure()))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Error validating export JSON structure.'));
            }

            return null;
        }

        if (!empty($return_arr['export_plugin_settings'])
         && empty($return_arr['crypt_key'])) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Please provide a crypting key required when working with sensitive data.'));

            return null;
        }

        if (empty($return_arr['export_path'])) {
            $return_arr['export_path'] = $this->_get_phs_uploads_path(false);
        }

        if (empty($return_arr['export_path'])
         || !($return_arr['export_path'] = @realpath(rtrim($return_arr['export_path'], '/')))
         || !@is_dir($return_arr['export_path'])
         || !@is_writable($return_arr['export_path'])) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('export_path (%s) is not a writeable directory within framework root path.',
                    $return_arr['export_path']));

            return null;
        }

        if (empty($return_arr['export_file'])) {
            $return_arr['export_file'] = 'export_'.date('YmdHis').'.json';
        } elseif (strtolower(substr($return_arr['export_file'], -5)) !== '.json') {
            $return_arr['export_file'] .= '.json';
        }

        if (empty($return_arr['export_file'])
         || !($return_arr['export_file'] = basename($return_arr['export_file']))
         || @file_exists($return_arr['export_path'].'/'.$return_arr['export_file'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('export_file (%s) is invalid or file already exists.',
                $return_arr['export_path'].'/'.$return_arr['export_file']));

            return null;
        }

        $return_arr['export_full_file'] = $return_arr['export_path'].'/'.$return_arr['export_file'];

        if (empty($return_arr['only_plugins']) || !is_array($return_arr['only_plugins'])) {
            $return_arr['only_plugins'] = [];
        }
        if (empty($return_arr['only_themes']) || !is_array($return_arr['only_themes'])) {
            $return_arr['only_themes'] = [];
        }
        if (empty($return_arr['only_languages']) || !is_array($return_arr['only_languages'])) {
            $return_arr['only_languages'] = [];
        }

        if (empty($return_arr['export']) || !is_array($return_arr['export'])) {
            $return_arr['export'] = ['all'];
        }

        $return_arr['export_all'] = in_array('all', $return_arr['export'], true);
        $return_arr['export_symlinks'] = ($return_arr['export_all'] || in_array('symlinks', $return_arr['export'], true));
        $return_arr['export_plugin_settings'] = ($return_arr['export_all'] || in_array('plugin_settings', $return_arr['export'], true));
        $return_arr['export_themes'] = ($return_arr['export_all'] || in_array('themes', $return_arr['export'], true));
        $return_arr['export_languages'] = ($return_arr['export_all'] || in_array('languages', $return_arr['export'], true));

        return $return_arr;
    }
    //
    // endregion Export functionality
    //

    //
    // region Import functionality
    //
    private function _validate_setup_action_import_json_structure(?array $json_arr) : ?array
    {
        $this->reset_error();

        if (!($return_arr = $this->_validate_import_export_json_structure($json_arr, $this->_setup_action_import_json_structure()))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Error validating import JSON structure.'));
            }

            return null;
        }

        if (empty($return_arr['import_file'])
         || !@file_exists($return_arr['import_file'])
         || !@is_readable($return_arr['import_file'])
         || strtolower(substr($return_arr['import_file'], -5)) !== '.json') {
            $this->set_error(self::ERR_PARAMETERS, self::_t('import_file (%s) doesn\'t exist.',
                $return_arr['import_file'] ?: 'N/A'));

            return null;
        }

        if (empty($return_arr['only_plugins']) || !is_array($return_arr['only_plugins'])) {
            $return_arr['only_plugins'] = [];
        }
        if (empty($return_arr['only_themes']) || !is_array($return_arr['only_themes'])) {
            $return_arr['only_themes'] = [];
        }
        if (empty($return_arr['only_languages']) || !is_array($return_arr['only_languages'])) {
            $return_arr['only_languages'] = [];
        }

        if (empty($return_arr['import']) || !is_array($return_arr['import'])) {
            $return_arr['import'] = ['all'];
        }

        $return_arr['import_all'] = in_array('all', $return_arr['import'], true);
        $return_arr['import_symlinks'] = ($return_arr['import_all'] || in_array('symlinks', $return_arr['import'], true));
        $return_arr['import_plugin_settings'] = ($return_arr['import_all'] || in_array('plugin_settings', $return_arr['import'], true));
        $return_arr['import_themes'] = ($return_arr['import_all'] || in_array('themes', $return_arr['import'], true));
        $return_arr['import_languages'] = ($return_arr['import_all'] || in_array('languages', $return_arr['import'], true));

        return $return_arr;
    }

    /**
     * @param string $plugin_name
     * @param array $plugin_settings_arr
     *
     * @return bool
     */
    private function _import_settings_for_plugin(string $plugin_name, array $plugin_settings_arr) : bool
    {
        $this->reset_error();

        if (!($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error instantiating plugin %s.', $plugin_name));

            return false;
        }

        foreach ($plugin_settings_arr as $instance_id => $settings_arr) {
            if (!is_array($settings_arr)
             || !($instance_arr = PHS_Instantiable::valid_instance_id($instance_id))
             || empty($instance_arr['instance_type'])
             || empty($instance_arr['instance_name'])
             // Plugins and models have settings at the moment
             || !in_array($instance_arr['instance_type'],
                 [PHS_Instantiable::INSTANCE_TYPE_PLUGIN, PHS_Instantiable::INSTANCE_TYPE_MODEL], true)) {
                continue;
            }

            if ($instance_arr['instance_type'] === PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                if (!$plugin_obj->save_db_settings($settings_arr)) {
                    $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error saving settings for plugin %s.', $plugin_name));

                    return false;
                }

                continue;
            }

            if (!($model_obj = PHS::load_model($instance_arr['instance_name'], $plugin_name))
             || !$model_obj->save_db_settings($settings_arr)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error saving settings for model %s, plugin %s.', $instance_arr['instance_name'], $plugin_name));

                return false;
            }
        }

        return true;
    }
    //
    // endregion Import functionality
    //
}
