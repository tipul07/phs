<?php

namespace phs\system\core\libraries;

use phs\PHS;
use phs\PHS_Bg_jobs;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Migration;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Migrations;

class PHS_Migrations_manager extends PHS_Library
{
    public const FILE_TIMESTAMP_FORMAT = 'YmdHis';

    private ?PHS_Model_Migrations $_migrations_model = null;

    private ?PHS_Model_Plugins $_plugins_model = null;

    private static ?array $_existing_migrations = null;

    private static ?array $_existing_migrations_per_plugin = null;

    public function __construct(?array $params = null)
    {
        parent::__construct();

        include_once PHS_LIBRARIES_DIR.'phs_migration.php';

        $this->reset_error();
    }

    public function get_existing_migrations_per_plugin() : array
    {
        if (self::$_existing_migrations_per_plugin === null) {
            $this->_load_existing_migrations();
        }

        return self::$_existing_migrations_per_plugin;
    }

    public function get_existing_migrations_for_plugin(string $plugin_name) : array
    {
        return ($migrations_arr = $this->get_existing_migrations_per_plugin()) && !empty($migrations_arr[$plugin_name])
            ? $migrations_arr[$plugin_name]
            : [];
    }

    public function get_existing_migrations() : array
    {
        if (self::$_existing_migrations === null) {
            $this->_load_existing_migrations();
        }

        return self::$_existing_migrations;
    }

    public function register_migrations_for_plugins(array $plugin_names) : ?array
    {
        if (null === ($migrations_arr = $this->get_migration_scripts_to_be_run_for_plugins($plugin_names))) {
            return null;
        }

        $return_arr = [
            'plugins' => 0,
            'scripts' => 0,
        ];

        if (!$migrations_arr) {
            return $return_arr;
        }

        foreach ($migrations_arr as $scripts_arr) {
            foreach ($scripts_arr as $script_details) {
                if (!$this->_register_script_details($script_details)) {
                    if ($this->has_error()) {
                        return null;
                    }

                    continue;
                }

                $return_arr['scripts']++;
            }

            $return_arr['plugins']++;
        }

        return $return_arr;
    }

    public function get_migration_scripts_to_be_run_for_plugins(array $plugin_names) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $scripts_arr = [];
        foreach ($plugin_names as $plugin_name) {
            if (empty($plugin_name)
                || !($migrations_arr = $this->get_migration_scripts_to_be_run_for_one_plugin($plugin_name))) {
                continue;
            }

            $scripts_arr[$plugin_name] = $migrations_arr;
        }

        return $scripts_arr;
    }

    public function get_migration_scripts_to_be_run_for_one_plugin(string $plugin_name) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        /**
         * @var PHS_Plugin $plugin_obj
         */
        if (!($plugin_obj = $this->_plugins_model->plugin_name_is_instantiable($plugin_name))
            || !($migrations = $this->get_migrations_scripts_from_plugin_instance($plugin_obj))) {
            return [];
        }

        $migrations_arr = [];
        foreach ($migrations as $migration_details) {
            if (empty($migration_details['script'])
               || $this->_migration_script_already_run($plugin_name, $migration_details['script'])) {
                continue;
            }

            $migrations_arr[] = $migration_details;
        }

        return $migrations_arr;
    }

    public function get_migrations_scripts_from_plugin_class(string $plugin_class, array $params = []) : ?array
    {
        self::st_reset_error();

        if (empty($plugin_class)
            || !($plugin_obj = $plugin_class::get_instance())) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Couldn\'t obtain a plugin instance: %s',
                    self::st_get_simple_error_message(self::_t('Unknown error.'))));

            return null;
        }

        return $this->get_migrations_scripts_from_plugin_instance($plugin_obj, $params);
    }

    public function get_migrations_scripts_from_plugin_name(string $plugin_name, array $params = []) : ?array
    {
        $this->reset_error();

        if (empty($plugin_name)
            || !($plugin_obj = PHS::load_plugin($plugin_name))) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Couldn\'t obtain a plugin instance: %s',
                    self::st_get_simple_error_message(self::_t('Unknown error.'))));

            return null;
        }

        return $this->get_migrations_scripts_from_plugin_instance($plugin_obj, $params);
    }

    public function get_migrations_scripts_from_plugin_instance(PHS_Plugin $plugin_obj, array $params = []) : ?array
    {
        $this->reset_error();

        if (!($migrations_dir = $plugin_obj->instance_plugin_migrations_path())
            || !@file_exists($migrations_dir)
            || !@is_dir($migrations_dir)
            || !@is_readable($migrations_dir)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Plugin doesn\'t have a migrations directory.'));

            return null;
        }

        $params['maintenance_output'] = !isset($params['maintenance_output']) || !empty($params['maintenance_output']);

        if (!($files_arr = @glob($migrations_dir.'*.php'))) {
            return [];
        }

        $migrations_arr = [];
        foreach ($files_arr as $file) {
            if (!($script_details = $this->_get_migration_file_details($file, $plugin_obj))) {
                if ($params['maintenance_output']) {
                    PHS_Maintenance::output("\t".'WARNING: Plugin '.$plugin_obj->instance_plugin_name().', migration script '.(basename($file) ?: $file).': '
                                            .$this->get_simple_error_message('Error loading migration script.'));
                }

                continue;
            }

            $migrations_arr[] = $script_details;
        }

        // Do not propagate errors...
        $this->reset_error();

        return $migrations_arr;
    }

    public function launch_rerun_migration_job(int | array $migration_data) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($migration_data)
            || !($migration_arr = $this->_migrations_model->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration data not found in database.'));

            return false;
        }

        if (!PHS_Bg_jobs::run(
            ['a' => 'rerun_bg', 'ad' => 'migrations', 'c' => 'index_bg', 'p' => 'admin'],
            ['migration_id' => $migration_arr['id']])
        ) {
            $error_msg = self::st_get_simple_error_message(self::_t('Error launching background job for migration script.'));

            PHS_Logger::error('Error launching background job for migration record #'.$migration_arr['id'].': '.$error_msg,
                PHS_Logger::TYPE_MAINTENANCE);
            $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

            return false;
        }

        return true;
    }

    public function rerun_migration_data(int | array $migration_data) : bool
    {
        if (!($migration_obj = $this->_register_migration_from_migration_data($migration_data, true))) {
            return false;
        }

        if (!$migration_obj->rerun()) {
            $this->copy_or_set_error($migration_obj,
                self::ERR_FUNCTIONALITY, self::_t('Error running migration script.'));

            return false;
        }

        return true;
    }

    private function _register_migration_from_migration_data(int | array $migration_data, bool $forced) : ?PHS_Migration
    {
        if (!($script_details = $this->_get_script_details_from_migration_data($migration_data))) {
            return null;
        }

        return $this->_register_script_details($script_details, $forced);
    }

    private function _get_script_details_from_migration_data(int | array $migration_data) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (empty($migration_data)
            || !($migration_arr = $this->_migrations_model->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration data not found in database.'));

            return null;
        }

        if (empty($migration_arr['plugin'])
            || !($plugin_obj = $this->_plugins_model->plugin_name_is_instantiable($migration_arr['plugin']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Could not instantiate the plugin of migration script.'));

            return null;
        }

        if (!($migrations_dir = $plugin_obj->instance_plugin_migrations_path())
            || !@file_exists($migrations_dir)
            || !@is_dir($migrations_dir)
            || !@is_readable($migrations_dir)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Plugin doesn\'t have a migrations directory.'));

            return null;
        }

        if (empty($migration_arr['script'])
            || !@is_file(($file = $migrations_dir.'/'.$migration_arr['script']))
            || !($script_details = $this->_get_migration_file_details($file, $plugin_obj))) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Migration script file not found.'));

            return null;
        }

        return $script_details;
    }

    private function _register_script_details(array $script_details, bool $forced = false) : ?PHS_Migration
    {
        $this->reset_error();

        if (empty($script_details['full_classname'])) {
            return null;
        }

        /** @var PHS_Migration $migration_obj */
        $migration_obj = new $script_details['full_classname']($script_details);

        if (!$migration_obj->register($forced)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error registering script %s, plugin %s: %s',
                    $script_details['script'], $script_details['plugin'],
                    $migration_obj->get_simple_error_message(self::_t('Unknown error.'))),
            );

            return null;
        }

        return $migration_obj;
    }

    private function _migration_script_already_run(string $plugin_name, string $script) : bool
    {
        return ($scripts_arr = $this->get_existing_migrations_for_plugin($plugin_name))
               && in_array($script, $scripts_arr, true);
    }

    private function _get_migration_file_details(string $file, PHS_Plugin $plugin_obj) : ?array
    {
        $this->reset_error();

        if (!($script = basename($file))
             || !@preg_match('/([0-9]{14})_([a-zA-Z0-9_]+)\.php/', $script, $matches)) {
            $this->set_error(self::ERR_RESOURCES, self::_t('Bad migration script naming format.'));

            return null;
        }

        if (!($filestamp = $matches[1] ?? '')
             || !($timestamp = $this->_filestamp_to_timestamp($filestamp))) {
            $this->set_error_if_not_set(self::ERR_RESOURCES, self::_t('Filestamp failed verification.'));

            return null;
        }

        if (!($file_class_name = $matches[2] ?? '')
             || !($class_validation = $this->_validate_file_and_classname($file, $file_class_name, $plugin_obj))
             || empty($class_validation['full_classname'])) {
            $this->set_error_if_not_set(self::ERR_RESOURCES, self::_t('Error loading migration script.'));

            return null;
        }

        return [
            'plugin'         => $plugin_obj->instance_plugin_name(),
            'plugin_class'   => $plugin_obj::class,
            'version'        => $plugin_obj->get_plugin_version(),
            'script'         => $script,
            'timestamp'      => $timestamp,
            'file'           => $file,
            'full_classname' => $class_validation['full_classname'],
        ];
    }

    private function _validate_file_and_classname(string $file, string $file_class_name, PHS_Plugin $plugin_obj) : ?array
    {
        $this->reset_error();

        if (!@is_file($file)
            || !@is_readable($file)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration file is not readable.'));

            return null;
        }

        if (!$this->_is_migration_classname_safe($file_class_name)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Bad file name format.'));

            return null;
        }

        ob_start();
        include_once $file;
        @ob_end_clean();

        $class_name = $plugin_obj->instance_plugin_migrations_namespace().'PHS_'.ucfirst(strtolower($file_class_name));

        if (!@class_exists($class_name, false)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Class %s does not exist.', $class_name));

            return null;
        }

        try {
            if (!($reflection = new \ReflectionClass($class_name))) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Error checking class %s.', $class_name));

                return null;
            }

            if ($reflection->isAbstract()) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Cannot use an abstract class %s.', $class_name));

                return null;
            }

            if (!($parent_class = $reflection->getParentClass())
                || $parent_class->getName() !== PHS_Migration::class) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Class %s does not look like a migration class.', $class_name));

                return null;
            }
        } catch (\Exception $e) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Could not determine details of class %s.', $class_name));

            return null;
        }

        return [
            'full_classname' => $class_name,
        ];
    }

    private function _is_migration_classname_safe(string $class_name) : bool
    {
        return !empty($class_name)
               && !preg_match('/[^a-zA-Z0-9_]/', $class_name);
    }

    private function _filestamp_to_timestamp(string $filestamp) : ?int
    {
        $this->reset_error();

        if ((string)((int)$filestamp) !== $filestamp
           || strlen($filestamp) !== 14
           || !(int)($year = substr($filestamp, 0, 4))
           || !(int)($month = substr($filestamp, 4, 2))
           || !(int)($day = substr($filestamp, 6, 2))
           || '' === ($hours = substr($filestamp, 8, 2))
           || '' === ($minutes = substr($filestamp, 10, 2))
           || '' === ($seconds = substr($filestamp, 12, 2))
           || !($my_timestamp = mktime($hours, $minutes, $seconds, $month, $day, $year))
           || date(self::FILE_TIMESTAMP_FORMAT, $my_timestamp) !== (string)$filestamp
        ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Bad file timestamp format.'));

            return null;
        }

        return $my_timestamp;
    }

    private function _load_existing_migrations() : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        self::$_existing_migrations = [];
        self::$_existing_migrations_per_plugin = [];

        if (!$this->_migrations_model->migration_model_is_installed()) {
            return true;
        }

        $list_arr = $this->_migrations_model->fetch_default_flow_params(['table_name' => 'phs_migrations']) ?: [];
        $list_arr['order_by'] = 'plugin ASC, cdate DESC';

        // Cover false and null as result...
        if (!($migrations_arr = $this->_migrations_model->get_list($list_arr))
            && !is_array($migrations_arr)) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining a list of migration scripts.'));

            return false;
        }

        foreach ($migrations_arr as $m_id => $m_arr) {
            if (empty($m_arr['plugin'])
                || !$this->_migrations_model->is_finished($m_arr)) {
                continue;
            }

            self::$_existing_migrations[(int)$m_id] = $m_arr;

            self::$_existing_migrations_per_plugin[$m_arr['plugin']] ??= [];
            self::$_existing_migrations_per_plugin[$m_arr['plugin']][] = $m_arr['script'];
        }

        return true;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            ($this->_migrations_model === null && !($this->_migrations_model = PHS_Model_Migrations::get_instance()))
            || ($this->_plugins_model === null && !($this->_plugins_model = PHS_Model_Plugins::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }

    public static function get_current_filestamp() : string
    {
        return self::timestamp_to_filestamp(time());
    }

    public static function timestamp_to_filestamp(int $timestamp) : string
    {
        return date(self::FILE_TIMESTAMP_FORMAT, $timestamp);
    }
}
