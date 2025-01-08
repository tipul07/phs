<?php
namespace phs\cli\apps;

include_once PHS_CORE_DIR.'phs_cli_plugins_trait.php';
include_once PHS_CLI_APPS_LIBRARIES_DIR.'PHS_Export_import.php';

use phs\PHS;
use phs\PHS_Db;
use phs\PHS_Cli;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Instantiable;
use phs\traits\PHS_Cli_plugins_trait;
use phs\cli\apps\libraries\PHS_Export_import;

class PHSMaintenance extends PHS_Cli
{
    use PHS_Cli_plugins_trait, PHS_Export_import;

    public const APP_NAME = 'PHSMaintenance',
        APP_VERSION = '1.1.0',
        APP_DESCRIPTION = 'Manage framework functionality and plugins.';

    public const ITEM_TYPE_EVENT = 'event', ITEM_TYPE_MIGRATION = 'migration', ITEM_TYPE_GQLTYPE = 'gqltype';

    public function get_app_dir() : string
    {
        return __DIR__.'/';
    }

    public function cli_maintenance_output($msg) : void
    {
        $this->_echo($msg);
    }

    public function cmd_plugin_action() : bool
    {
        if (null === ($plugins_dirs_arr = $this->get_plugins_as_dirs())) {
            $this->_echo_error(self::_t('Couldn\'t obtain plugins list: %s', $this->get_simple_error_message()));

            return false;
        }

        if (empty($plugins_dirs_arr)) {
            $this->_echo(self::_t('No plugin installed in plugins directory yet.'));

            return false;
        }

        if (!($command_arr = $this->get_app_command())
         || empty($command_arr['arguments'])
         || !($plugin_name = $this->_get_argument_chained($command_arr['arguments']))
         || (($plugin_action = $this->_get_argument_chained())
            && in_array($plugin_action, self::_get_plugin_command_actions_with_valid_plugins(), true)
            && !in_array($plugin_name, $plugins_dirs_arr, true)
         )) {
            $this->_echo_error(self::_t('Please provide a valid plugin name. Use %s command to view all plugins that are setup.', $this->cli_color('plugins', 'green')));

            $this->_echo('Usage: '.$this->get_app_cli_script().' [options] plugin [plugin] [action]');
            $this->_echo('Available actions: '.implode(', ', self::_get_plugin_command_actions()).'.');
            $this->_echo('If no action is provided, plugin details will be displayed.');

            return false;
        }

        if (empty($plugin_action)) {
            $plugin_action = '';
        }

        if (empty($plugin_action)) {
            return $this->_echo_plugin_details($plugin_name);
        }

        if (!in_array($plugin_action, self::_get_plugin_command_actions(), true)) {
            $this->_echo_error(self::_t('Invalid action.'));

            $this->_echo('Usage: '.$this->get_app_cli_script().' [options] plugin [plugin_action]');
            $this->_echo('Available actions: '.implode(', ', self::_get_plugin_command_actions()).'.');

            return false;
        }

        switch ($plugin_action) {
            case 'info':
                return $this->_echo_plugin_details($plugin_name);
            case 'activate':
                if (!$this->_activate_plugin($plugin_name)) {
                    if ($this->has_error()) {
                        $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '.$this->get_simple_error_message());
                    }

                    return false;
                }

                $this->_echo(self::_t('Plugin %s %s with success.',
                    $this->cli_color($plugin_name, 'white'),
                    $this->cli_color(self::_t('ACTIVATED'), 'green')));
                break;

            case 'inactivate':
                if (!$this->_inactivate_plugin($plugin_name)) {
                    if ($this->has_error()) {
                        $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '.$this->get_simple_error_message());
                    }

                    return false;
                }

                $this->_echo(self::_t('Plugin %s %s with success.',
                    $this->cli_color($plugin_name, 'white'),
                    $this->cli_color(self::_t('INACTIVATED'), 'green')));
                break;

            case 'install':
                if (!$this->_install_plugin($plugin_name)) {
                    $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '
                                 .$this->get_simple_error_message(self::_t('Unknown error.')));

                    return false;
                }

                $this->_echo(self::_t('Plugin %s %s with success.',
                    $this->cli_color($plugin_name, 'white'),
                    $this->cli_color(self::_t('INSTALLED'), 'green')));
                break;

            case 'uninstall':
                if (!$this->_uninstall_plugin($plugin_name)) {
                    $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '
                                 .$this->get_simple_error_message(self::_t('Unknown error.')));

                    return false;
                }

                $this->_echo(self::_t('Plugin %s %s with success.',
                    $this->cli_color($plugin_name, 'white'),
                    $this->cli_color(self::_t('UNINSTALLED'), 'green')));
                break;

            case 'symlink':
                if (!$this->_symlink_plugin($plugin_name)) {
                    if ($this->has_error()) {
                        $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '.$this->get_simple_error_message());
                    }

                    return false;
                }

                $this->_echo(self::_t('Plugin %s %s with success.',
                    $this->cli_color($plugin_name, 'white'),
                    $this->cli_color(self::_t('SYMLINK'), 'green')));
                break;

            case 'unlink':
                if (!$this->_unlink_plugin($plugin_name)) {
                    if ($this->has_error()) {
                        $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '.$this->get_simple_error_message());
                    }

                    return false;
                }

                $this->_echo(self::_t('Plugin %s %s with success.',
                    $this->cli_color($plugin_name, 'white'),
                    $this->cli_color(self::_t('UNLINK'), 'green')));
                break;
        }

        return true;
    }

    public function cmd_make_items() : bool
    {
        $this->reset_error();

        if (!($command_arr = $this->get_app_command())
            || empty($command_arr['arguments'])
            || !($item_type = $this->_get_argument_chained($command_arr['arguments']))
            || !self::_valid_items_command_item($item_type)) {
            $this->_echo_error(self::_t('Please provide a valid "item" type.'));

            $this->_display_items_command_usage();

            return false;
        }

        /** @var PHS_Plugin $plugin_obj */
        if (!($plugin_name = $this->_get_argument_chained())
            || !($plugin_name = PHS_Instantiable::safe_escape_plugin_name($plugin_name))
            || !($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->_echo_error(self::_t('Invalid plugin name. Please provide a valid plugin name. Use %s command to view all plugins that are setup.', $this->cli_color('plugins', 'green')));

            $this->_display_items_command_usage();

            return false;
        }

        if (!($item_name_with_path = $this->_get_argument_chained())) {
            $this->_echo_error(self::_t('Invalid "item" name.'));

            $this->_display_items_command_usage();

            return false;
        }

        if (!($destination_dir = $this->_get_stub_item_destination_dir($item_type, $plugin_obj))
            || !str_starts_with($destination_dir, PHS_PATH)) {
            $this->_echo_error(self::_t('Cannot obtain a destination directory for provided item type.'));

            $this->_display_items_command_usage();

            return false;
        }

        if (!($item_details = $this->_get_stub_item_details($item_type, $item_name_with_path))
            || empty($item_details['item_name'])) {
            $this->_echo_error($this->get_simple_error_message(self::_t('Error extracting stub info.')));

            $this->_display_items_command_usage();

            return false;
        }

        if (!($result_arr = $this->_create_file_for_stub(
            $item_type, $item_details['item_name'], $item_details['item_path'],
            $destination_dir,
            $plugin_obj))
        ) {
            $this->_echo_error(self::_t('Error generating item file from stub: %s',
                $this->get_simple_error_message(self::_t('Unknown error.'))));

            return false;
        }

        $this->_echo(self::_t('%s Created %s file %s with success for plugin %s.',
            $this->cli_color(self::_t('SUCCESS'), 'green'),
            $this->cli_color($item_type, 'white'),
            $this->cli_color($result_arr['destination_dir'].'/'.$result_arr['file_name'], 'white'),
            $this->cli_color($plugin_name, 'white')
        ));

        return true;
    }

    public function cmd_setup_action() : bool
    {
        $this->reset_error();

        if (!($command_arr = $this->get_app_command())
            || empty($command_arr['arguments'])
            || !($action = $this->_get_argument_chained($command_arr['arguments']))
            || !in_array($action, ['import', 'export'], true)
        ) {
            $this->_echo_error(self::_t('Please provide an action for the setup.'));
            $this->_display_cmd_setup_action_usage();

            return false;
        }

        if (!($action_file = $this->_get_argument_chained())) {
            $action_json_arr = [];
        } elseif (!($action_json_arr = $this->_platform_import_export_decode_action_file($action_file))) {
            $this->_echo_error(self::_t('Please provide action JSON file for action %s.',
                $this->cli_color($action, 'white')));
            $this->_display_cmd_setup_action_usage();

            return false;
        }

        $this->_echo(self::_t('Running action %s using action file %s...',
            $this->cli_color($action, 'white'),
            $this->cli_color(($action_file ?: 'N/A'), 'white'))
        );

        if ($action === 'export') {
            return $this->_setup_do_export($action_json_arr);
        }

        if ($action === 'import') {
            return $this->_setup_do_import($action_json_arr);
        }

        $this->_display_cmd_setup_action_usage();

        return false;
    }
    //
    // endregion setup action
    //

    public function cmd_web_update() : bool
    {
        $this->reset_error();

        echo self::_t('Update URL vailable for %s.', $this->cli_color(PHS_Utils::parse_period(PHS_Maintenance::UPDATE_TOKEN_LIFETIME), 'green'))."\n";
        echo self::_t('NOTE: Provided URL is forced to use HTTPS, if you don\'t have HTTPS enabled, change the link to use HTTP protocol.')."\n";
        echo "\n";
        echo PHS_Maintenance::get_framework_update_url_with_token()."\n";

        return true;
    }

    public function cmd_dry_update() : bool
    {
        $this->set_output_colors(false);

        return $this->cmd_update(true);
    }

    public function cmd_update(bool $dry_run = false) : bool
    {
        $this->reset_error();

        if (!defined('PHS_INSTALLING_FLOW')) {
            define('PHS_INSTALLING_FLOW', true);
        }

        if ($dry_run) {
            PHS_Db::dry_update(true);
            PHS_Db::dry_update_output('-- Running dry update on '.PHS_DOMAIN.' ('.date('r').')');
        }

        $this->_continous_flush(true);

        if (!$dry_run) {
            $this->_echo('Installing core plugins, models, etc...');
        }

        if (@file_exists(PHS_SYSTEM_DIR.'install.php')) {
            $system_install_result = include_once PHS_SYSTEM_DIR.'install.php';

            if ($system_install_result !== true) {
                $this->_echo_error('Error while running system install script [CORE INSTALL]:');
                if (is_array($system_install_result)) {
                    $system_install_result = self::arr_get_simple_error_message($system_install_result);
                }

                $this->_echo($system_install_result);

                return true;
            }
        }

        if (!$dry_run) {
            $this->_echo($this->cli_color('DONE', 'green'));

            $this->_echo('Installing custom plugins, models, etc...');
        }

        // Walk thgrough plugins install scripts (if any special install functionality is required)...
        foreach ([PHS_CORE_PLUGIN_DIR, PHS_PLUGINS_DIR] as $bstrap_dir) {
            if (($install_scripts = @glob($bstrap_dir.'*/install.php', GLOB_BRACE))
             && is_array($install_scripts)) {
                foreach ($install_scripts as $install_script) {
                    $install_result = include_once $install_script;

                    if ($install_result !== null) {
                        $install_result = self::validate_error_arr($install_result);
                        $this->_echo_error('Error while running custom install script ['.$install_script.']:');

                        if (self::arr_has_error($install_result)) {
                            $this->_echo(self::arr_get_simple_error_message($install_result));
                        }

                        return true;
                    }
                }
            }
        }

        if (!$dry_run) {
            $this->_echo($this->cli_color('DONE', 'green'));
            $this->_echo('');
        }

        if (!$dry_run
         && ($debug_data = PHS::platform_debug_data())) {
            $this->_echo('Update stats:');
            $this->_echo('DB queries: '.$debug_data['db_queries_count'].', '
                          .'bootstrap time: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                          .'running time: '.number_format($debug_data['running_time'], 6, '.', '').'s.'
            );
        }

        if ($dry_run) {
            PHS_Db::dry_update(true);
            PHS_Db::dry_update_output('-- Finished dry update on '.PHS_DOMAIN.' ('.date('r').')');
        }

        return true;
    }

    public function cmd_list_plugins() : bool
    {
        if (null === ($plugins_dirs_arr = $this->get_plugins_as_dirs())) {
            $this->_echo_error(self::_t('Couldn\'t obtain plugins list: %s', $this->get_simple_error_message()));

            return false;
        }

        if (empty($plugins_dirs_arr)) {
            $this->_echo(self::_t('No plugin installed in plugin directory yet.'));

            return false;
        }

        $this->_echo(self::_t('Found %s plugin directories...', count($plugins_dirs_arr)));
        foreach ($plugins_dirs_arr as $plugin_name) {
            if (!($plugin_info = $this->_gather_plugin_info($plugin_name))) {
                $plugin_info = self::_get_default_plugin_info_definition();
            }

            $extra_info = '';
            if (!empty($plugin_info)) {
                if (!empty($plugin_info['is_installed'])) {
                    $extra_info .= '['.$this->cli_color(self::_t('Installed'), 'green').']';
                } else {
                    $extra_info .= '['.$this->cli_color(self::_t('NOT INSTALLED'), 'red').']';
                }

                $extra_info .= ' ';

                if (!empty($plugin_info['is_installed'])) {
                    if (!empty($plugin_info['is_active'])) {
                        $extra_info .= '['.$this->cli_color(self::_t('Active'), 'green').']';
                    } else {
                        $extra_info .= '['.$this->cli_color(self::_t('NOT ACTIVE'), 'red').']';
                    }

                    $extra_info .= ' ';
                }

                if (!empty($plugin_info['name'])) {
                    $extra_info .= $plugin_info['name'];
                }
                if (!empty($plugin_info['version'])) {
                    $extra_info .= ($extra_info !== '' ? ' ' : '').'(v'.$plugin_info['version'].')';
                }
                if (!empty($plugin_info['models_count'])) {
                    $extra_info .= ($extra_info !== '' ? ', ' : '').$plugin_info['models_count'].' models';
                }
            }

            $this->_echo(' - '.$this->cli_color($plugin_name, 'yellow').($extra_info !== '' ? ': ' : '').$extra_info);
        }

        $this->_echo($this->cli_color('DONE', 'green'));

        return true;
    }

    protected function _get_app_options_definition() : array
    {
        return [];
    }

    protected function _get_app_commands_definition() : array
    {
        return [
            'phs_setup' => [
                'description' => 'Setup framework database. This is called first time to setup framework database.',
                'callback'    => null,
            ],
            'web_update' => [
                'description' => 'Provides a framework update URL which can be used to update framework in a browser for one day.',
                'callback'    => [$this, 'cmd_web_update'],
            ],
            'update' => [
                'description' => 'Check plugins database version against script version and update if required.',
                'callback'    => [$this, 'cmd_update'],
            ],
            'dry_update' => [
                'description' => 'Export to SQL statements used to update database structure.',
                'callback'    => [$this, 'cmd_dry_update'],
            ],
            'plugins' => [
                'description' => 'List available plugins',
                'callback'    => [$this, 'cmd_list_plugins'],
            ],
            'plugin' => [
                'description' => 'Plugin management plugin [name] [action]. If no action is provided, display plugin details.',
                'callback'    => [$this, 'cmd_plugin_action'],
            ],
            'make' => [
                'description' => 'Create different "items" for specified plugin.',
                'callback'    => [$this, 'cmd_make_items'],
            ],
            'setup' => [
                'description'        => 'Platform setup actions. You can import or export framework setup in/from a setup file.',
                'callback'           => [$this, 'cmd_setup_action'],
                'options_definition' => [
                    'no-settings' => [
                        'long'        => 'no-settings',
                        'short'       => 'ns',
                        'description' => 'Do not import or export settings',
                        'default'     => true,
                    ],
                ],
            ],
        ];
    }

    protected function _init_app() : bool
    {
        $this->reset_error();

        PHS_Maintenance::output_callback([$this, 'cli_maintenance_output']);

        return true;
    }

    private function _display_items_command_usage() : void
    {
        $this->_echo('Usage: '.$this->get_app_cli_script().' [options] make [item] [plugin] [options]');
        $this->_echo('Available item types: '.implode(', ', self::_get_items_command_valid_item_types()).'.');
        $this->_echo('Item options varies depending on provided item type.');
    }

    private function _create_file_for_stub(
        string $item_type, string $item_name, string $item_path,
        string $destination_dir,
        PHS_Plugin $plugin
    ) : ?array {
        $this->reset_error();

        if (!($file_details = $this->_get_stub_item_destination_file_details($item_type, $item_path, $item_name))
            || empty($file_details['file_name'])
            || empty($file_details['class_name'])
        ) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error obtaining destination file details.'));

            return null;
        }

        if (!($file_buf = $this->_get_stub_file_content($item_type))
            || !($file_buf = $this->_convert_stub_content_from_context($file_buf, $file_details['class_name'], $plugin, $file_details['class_namespace']))
        ) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error obtaining stub file content.'));

            return null;
        }

        $destination_dir = rtrim(rtrim($destination_dir, '/').'/'.$file_details['file_subdir'], '/');

        if (!@file_exists($destination_dir)
           && !PHS_Utils::mkdir_tree($destination_dir)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error creating destination directory.'));

            return null;
        }

        if (!@file_put_contents($destination_dir.'/'.$file_details['file_name'], $file_buf)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error saving resulting file to its destination.'));

            return null;
        }

        return [
            'file_name'       => $file_details['file_name'],
            'destination_dir' => $destination_dir,
        ];
    }

    private function _convert_stub_content_from_context(
        string $buf,
        string $class_name,
        PHS_Plugin $plugin,
        string $class_namespace = '',
    ) : ?string {
        $context = [
            '__PLUGIN_NAME__'     => $plugin->instance_plugin_name(),
            '__CLASS_NAME__'      => $class_name,
            '__CLASS_NAMESPACE__' => (!empty($class_namespace) ? '\\'.ltrim($class_namespace, '\\') : ''),
        ];

        return str_replace(array_keys($context), array_values($context), $buf);
    }

    private function _get_stub_item_destination_file_details(string $item_type, string $item_path, string $item_name) : ?array
    {
        $escaped_name = str_replace(' ', '_', strtolower($item_name));

        if ($item_type === self::ITEM_TYPE_MIGRATION) {
            if (!($migrations_manager = migrations_manager())) {
                self::st_reset_error();
                $this->set_error(self::ERR_DEPENDENCIES,
                    self::_t('Error loading required resources.'));

                return null;
            }

            return [
                'class_name' => ucfirst($escaped_name),
                // Migrations don't support subdirectories
                'class_namespace' => '',
                'file_name'       => $migrations_manager::get_current_filestamp().'_'.$escaped_name.'.php',
                'file_subdir'     => '',
            ];
        }

        if (in_array($item_type, [self::ITEM_TYPE_EVENT, self::ITEM_TYPE_GQLTYPE], true)) {
            return [
                'class_name'      => ucfirst($escaped_name),
                'class_namespace' => str_replace('/', '\\', strtolower($item_path)),
                'file_name'       => 'phs_'.$escaped_name.'.php',
                'file_subdir'     => $item_path,
            ];
        }

        return null;
    }

    private function _get_stub_item_details(string $item_type, string $item_name_with_path) : ?array
    {
        $item_path = '';
        $item_name = @basename($item_name_with_path);
        if ($item_type !== self::ITEM_TYPE_MIGRATION
             && $item_name !== $item_name_with_path) {
            $item_path = trim(substr($item_name_with_path, 0, -strlen($item_name)), '/');

            if (!PHS_Instantiable::safe_escape_instance_subdir_path($item_path)) {
                $this->_echo_error(self::_t('Invalid item sub directory structure.'));

                $this->_display_items_command_usage();

                return null;
            }
        }

        return [
            'item_path' => $item_path,
            'item_name' => $item_name,
        ];
    }

    private function _get_stub_item_destination_dir(string $item_type, PHS_Plugin $plugin_obj) : ?string
    {
        if ($item_type === self::ITEM_TYPE_MIGRATION) {
            return $plugin_obj->instance_plugin_migrations_path();
        }

        if ($item_type === self::ITEM_TYPE_EVENT) {
            return
                ($details_arr = PHS_Instantiable::get_instance_details('PHS_Event_Test', $plugin_obj->instance_plugin_name(), PHS_Instantiable::INSTANCE_TYPE_EVENT))
                ? ($details_arr['instance_path'] ?? null)
                : null;
        }

        if ($item_type === self::ITEM_TYPE_GQLTYPE) {
            return
                ($details_arr = PHS_Instantiable::get_instance_details('PHS_Graphql_Test', $plugin_obj->instance_plugin_name(), PHS_Instantiable::INSTANCE_TYPE_GRAPHQL))
                ? ($details_arr['instance_path'] ?? null)
                : null;
        }

        return null;
    }

    private function _get_stub_file_content(string $item_type) : ?string
    {
        if (!($stub_file = $this->_get_stub_file($item_type))) {
            return null;
        }

        return @file_get_contents($stub_file);
    }

    private function _get_stub_file(string $item_type) : ?string
    {
        $stub_dirs = [];
        if (defined('PHS_CUSTOM_STUBS_DIR')) {
            $stub_dirs[] = PHS_CUSTOM_STUBS_DIR;
        }
        if (defined('PHS_CORE_STUBS_DIR')) {
            $stub_dirs[] = PHS_CORE_STUBS_DIR;
        }

        if (empty($stub_dirs)) {
            return null;
        }

        foreach ($stub_dirs as $dir) {
            if (!@file_exists($dir.'phs_'.$item_type.'.php')) {
                continue;
            }

            return $dir.'phs_'.$item_type.'.php';
        }

        return null;
    }

    private function _install_plugin(string $plugin_name) : bool
    {
        if (!($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error instantiating plugin.'));

            return false;
        }

        if (null === ($is_installed = $plugin_obj->plugin_is_installed())) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $plugin_obj->get_simple_error_message(self::_t('Error instantiating plugin.')));

            return false;
        }

        if ($is_installed) {
            return true;
        }

        if (!$plugin_obj->install()) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error installing plugin: %s',
                    $plugin_obj->get_simple_error_message(self::_t('Unknown error.'))));

            return false;
        }

        return true;
    }

    private function _uninstall_plugin(string $plugin_name) : bool
    {
        if (!($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error instantiating plugin.'));

            return false;
        }

        if (null === ($is_installed = $plugin_obj->plugin_is_installed())) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $plugin_obj->get_simple_error_message(self::_t('Error instantiating plugin.')));

            return false;
        }

        if (!$is_installed) {
            return true;
        }

        if (!$plugin_obj->uninstall()) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error uninstalling plugin: %s',
                    $plugin_obj->get_simple_error_message(self::_t('Unknown error.'))));

            return false;
        }

        return true;
    }

    private function _activate_plugin(string $plugin_name) : bool
    {
        if (!($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error instantiating plugin.'));

            return false;
        }

        if (!$plugin_obj->activate_plugin()) {
            $error_msg = self::_t('Error inactivating plugin');
            if ($plugin_obj->has_error()) {
                $error_msg .= ': '.$plugin_obj->get_simple_error_message();
            }

            $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

            return false;
        }

        return true;
    }

    private function _inactivate_plugin(string $plugin_name) : bool
    {
        if (!($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error instantiating plugin.'));

            return false;
        }

        if (!$plugin_obj->inactivate_plugin()) {
            $error_msg = self::_t('Error inactivating plugin');
            if ($plugin_obj->has_error()) {
                $error_msg .= ': '.$plugin_obj->get_simple_error_message();
            }

            $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

            return false;
        }

        return true;
    }

    private function _symlink_repository_directory_details($repo_dir) : void
    {
        $this->_echo(self::_t('Please not that repository directory should be an absolute path to repository directory or a relative path from plugins directory.'));
        $this->_echo(self::_t('Eg. %s should point to repository directory from %s plugins directory.',
            $this->cli_color((!empty($repo_dir) ? $repo_dir : 'N/A'), 'white'),
            $this->cli_color(PHS_PLUGINS_DIR, 'white')
        ));
        $this->_echo(self::_t('Repository directory %s is invalid.',
            $this->cli_color(PHS_PLUGINS_DIR.(!empty($repo_dir) ? $repo_dir : 'N/A'), 'white')
        ));
    }

    private function _symlink_plugin($plugin_name) : bool
    {
        $repo_dir = $this->_get_argument_chained() ?: '';

        // Normally we could call PHS_Maintenance::symlink_plugin_from_repo() directly,
        // but for a better error handling we call methods separately
        if (!$repo_dir
            || !($real_path = PHS_Maintenance::convert_plugin_repo_to_real_path($repo_dir))) {
            $this->_echo_error(self::_t('Couldn\'t locate plugin repository directory %s.',
                $this->cli_color((!empty($repo_dir) ? $repo_dir : 'N/A'), 'white')
            ));

            $this->_symlink_repository_directory_details($repo_dir);

            return false;
        }

        if (!($plugin_json_arr = PHS_Maintenance::check_plugin_in_repo($plugin_name, $repo_dir))) {
            $this->_echo_error(self::_t('Couldn\'t locate plugin %s in repository directory %s (real path %s).',
                $this->cli_color($plugin_name, 'white'),
                $this->cli_color($repo_dir, 'white'),
                $this->cli_color($real_path, 'white')
            ));

            $this->_symlink_repository_directory_details($repo_dir);

            return false;
        }

        if (!PHS_Maintenance::symlink_plugin_from_repo($plugin_name, $repo_dir)) {
            $error_msg = self::_t('Error creating symlink for plugin');
            if (self::st_has_error()) {
                $error_msg .= ': '.self::st_get_simple_error_message();
            }

            $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

            return false;
        }

        return true;
    }

    private function _unlink_plugin(string $plugin_name) : bool
    {
        if (!PHS_Maintenance::unlink_plugin($plugin_name)) {
            $error_msg = self::_t('Error unlinking the plugin');
            if (self::st_has_error()) {
                $error_msg .= ': '.self::st_get_simple_error_message();
            }

            $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

            return false;
        }

        return true;
    }
    //
    // endregion plugin action
    //

    //
    // region setup action
    //
    private function _display_cmd_setup_action_usage() : void
    {
        $this->_echo('Usage: '.$this->get_app_cli_script().' [options] setup [export|import] {[action_json_file]}');
    }

    private function _setup_do_export(?array $action_json_arr) : bool
    {
        if (!($action_json_arr = $this->_validate_platform_export_action_json_structure($action_json_arr))) {
            $this->_set_and_echo_error(
                $this->get_simple_error_message(self::_t('Error validating export JSON structure.')),
                $this->get_error_code(self::ERR_PARAMETERS)
            );

            return false;
        }

        if ($this->command_option('no-settings')) {
            $action_json_arr['export_plugin_settings'] = false;
        }

        if (!$this->_do_platform_export_action_to_file($action_json_arr)) {
            $this->_set_and_echo_error(
                $this->get_simple_error_message(self::_t('Error exporting data. Please try again.')),
                $this->get_error_code(self::ERR_FUNCTIONALITY)
            );

            return false;
        }

        if (null !== ($del_result = $this->_do_platform_delete_action_file($action_json_arr))) {
            if ($del_result) {
                $this->_echo($this->cli_color('Action file was deleted with success.', 'yellow'));
            } else {
                $this->_echo($this->cli_color('Action file was NOT deleted.', 'red'));
            }
        }

        $this->_echo(self::_t('Exported settings to file %s.',
            $this->cli_color($action_json_arr['export_full_file'] ?? 'N/A', 'white'))
        );

        $this->_echo($this->cli_color('DONE', 'green'));

        return true;
    }

    private function _setup_do_import(?array $action_json_arr) : bool
    {
        if (!($action_json_arr = $this->_validate_setup_action_import_json_structure($action_json_arr))) {
            $this->_set_and_echo_error(
                $this->get_simple_error_message(self::_t('Error validating import JSON structure.')),
                $this->get_error_code(self::ERR_PARAMETERS)
            );

            return false;
        }

        if (!$this->_do_platform_import_action($action_json_arr)) {
            $this->_set_and_echo_error(
                $this->get_simple_error_message(self::_t('Error importing data. Please try again.')),
                $this->get_error_code(self::ERR_FUNCTIONALITY)
            );

            return false;
        }

        if (null !== ($del_result = $this->_do_platform_delete_action_file($action_json_arr))) {
            if ($del_result) {
                $this->_echo($this->cli_color('Action file was deleted with success.', 'yellow'));
            } else {
                $this->_echo($this->cli_color('Action file was NOT deleted.', 'red'));
            }
        }

        $this->_echo($this->cli_color('DONE', 'green'));

        return false;
    }

    private function _set_and_echo_error($error_msg, $error_code = self::ERR_FUNCTIONALITY, $force_set = true) : void
    {
        if ($force_set || !$this->has_error()) {
            $this->set_error($error_code, $error_msg);
        } else {
            $error_msg = $this->get_simple_error_message();
        }

        $this->_echo_error($error_msg);
    }

    //
    // region plugin action
    //
    private static function _get_plugin_command_actions() : array
    {
        return ['info', 'install', 'uninstall', 'activate', 'inactivate', 'symlink', 'unlink'];
    }

    private static function _get_plugin_command_actions_with_valid_plugins() : array
    {
        return ['info', 'install', 'uninstall', 'activate', 'inactivate', 'unlink'];
    }

    private static function _valid_items_command_item(string $item) : bool
    {
        return in_array($item, self::_get_items_command_valid_item_types(), true);
    }

    private static function _get_items_command_valid_item_types() : array
    {
        return [self::ITEM_TYPE_MIGRATION, self::ITEM_TYPE_EVENT, self::ITEM_TYPE_GQLTYPE];
    }
}
