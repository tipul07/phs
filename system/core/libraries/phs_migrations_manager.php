<?php

namespace phs\system\core\libraries;

use phs\PHS;
use phs\PHS_Maintenance;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Migration;
use phs\system\core\models\PHS_Model_Migrations;

class PHS_Migrations_manager extends PHS_Library
{
    const FILE_TIMESTAMP_FORMAT = 'YmdHis';

    private ?PHS_Model_Migrations $_migrations_model = null;

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

    public function get_existing_migrations() : array
    {
        if (self::$_existing_migrations === null) {
            $this->_load_existing_migrations();
        }

        return self::$_existing_migrations;
    }

    private function _load_existing_migrations() : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        // Cover false and null as result...
        if (!($migrations_arr = $this->_migrations_model->get_list(['table_name' => 'phs_migrations']))
            && !is_array($migrations_arr)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining a list of migration scripts.'));

            return false;
        }

        self::$_existing_migrations = [];
        self::$_existing_migrations_per_plugin = [];
        foreach ($migrations_arr as $m_id => $m_arr) {
            if (empty($m_arr['plugin'])) {
                continue;
            }

            self::$_existing_migrations[(int)$m_id] = $m_arr;
            self::$_existing_migrations_per_plugin[$m_arr['plugin']] = $m_arr['script'];
        }

        return true;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ($this->_migrations_model === null && !($this->_migrations_model = PHS_Model_Migrations::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    public static function get_migrations_scripts_from_plugin_class(string $plugin_class, array $params = []) : ?array
    {
        self::st_reset_error();

        if (empty($plugin_class)
            || !($plugin_obj = $plugin_class::get_instance())) {
            if ( !self::st_has_error()) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t obtain a plugin instance.'));
            }

            return null;
        }

        return self::get_migrations_scripts_from_plugin_instance($plugin_obj, $params);
    }

    public static function get_migrations_scripts_from_plugin_name(string $plugin_name, array $params = []) : ?array
    {
        self::st_reset_error();

        if (empty($plugin_name)
            || !($plugin_obj = PHS::load_plugin($plugin_name))) {
            if ( !self::st_has_error()) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Couldn\'t obtain a plugin instance.'));
            }

            return null;
        }

        return self::get_migrations_scripts_from_plugin_instance($plugin_obj, $params);
    }

    public static function get_migrations_scripts_from_plugin_instance(PHS_Plugin $plugin_obj, array $params = []) : ?array
    {
        self::st_reset_error();

        if (!($migrations_dir = $plugin_obj->instance_plugin_migrations_path())
            || !@file_exists($migrations_dir)
            || !@is_dir($migrations_dir)
            || !@is_readable($migrations_dir)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Plugin doesn\'t have a migrations directory.'));

            return null;
        }

        $params['maintenance_output'] = !isset($params['maintenance_output']) || !empty($params['maintenance_output']);

        if ( !($files_arr = @glob($migrations_dir.'*.php')) ) {
            return [];
        }

        $migrations_arr = [];
        foreach ( $files_arr as $file) {
            if( !($script_details = self::get_migration_file_details($file, $plugin_obj))) {
                if($params['maintenance_output']) {
                    PHS_Maintenance::output("\t".'WARNING: Plugin '.$plugin_obj->instance_plugin_name().', migration script '.(basename($file) ?: $file).': '.
                                            self::st_get_simple_error_message('Error loading migration script.'));
                }

                continue;
            }

            $migrations_arr[] = $script_details;
        }

        // Do not propagate errors...
        self::st_reset_error();

        return $migrations_arr;
    }

    public static function get_migration_file_details(string $file, PHS_Plugin $plugin_obj): ?array
    {
        self::st_reset_error();

        if( !($filename = basename($file))
            || !@preg_match('/([0-9]{14})_([a-zA-Z0-9_]+)\.php/', $filename, $matches) ) {
            self::st_set_error(self::ERR_RESOURCES, self::_t('Bad migration script naming format.'));
            return null;
        }

        if( !($filestamp = $matches[1] ?? '')
            || !($timestamp = self::_filestamp_to_timestamp($filestamp)) ) {
            if( !self::st_has_error() ) {
                self::st_set_error(self::ERR_RESOURCES, self::_t('Filestamp failed verification.'));
            }
            return null;
        }

        if( !($file_class_name = $matches[2] ?? '')
            || !($class_validation = self::_validate_file_and_classname($file, $file_class_name, $plugin_obj))
            || empty($class_validation['full_classname'])) {
            if( !self::st_has_error() ) {
                self::st_set_error(self::ERR_RESOURCES, self::_t('Error loading migration script.'));
            }
            return null;
        }

        return [
            'basename' => $filename,
            'timestamp' => $timestamp,
            'file' => $file,
            'full_classname' => $class_validation['full_classname'],
        ];
    }

    public static function is_migration_classname_safe(string $class_name): bool
    {
        return !empty($class_name)
               && !preg_match('/[^a-zA-Z0-9_]/', $class_name);
    }

    public static function get_current_filestamp(): string
    {
        return self::timestamp_to_filestamp(time());
    }

    public static function timestamp_to_filestamp(int $timestamp): string
    {
        return date(self::FILE_TIMESTAMP_FORMAT, $timestamp);
    }

    private static function _validate_file_and_classname(string $file, string $file_class_name, PHS_Plugin $plugin_obj): ?array
    {
        self::st_reset_error();

        if(!self::is_migration_classname_safe($file_class_name)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Bad file name format.'));
            return null;
        }

        ob_start();
        include_once($file);
        @ob_end_clean();

        $class_name = $plugin_obj->instance_plugin_migrations_namespace().'PHS_'.ucfirst(strtolower($file_class_name));

        if( !@class_exists($class_name, false) ) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Class %s does not exist.', $class_name));
            return null;
        }

        try {
            if (!($reflection = new \ReflectionClass($class_name))) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Error checking class %s.', $class_name));
                return null;
            }

            if ($reflection->isAbstract()) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Cannot use an abstract class %s.', $class_name));
                return null;
            }

            if (!($parent_class = $reflection->getParentClass())
                || $parent_class->getName() !== PHS_Migration::class) {
                self::st_set_error(self::ERR_PARAMETERS, self::_t('Class %s does not look like a migration class.', $class_name));
                return null;
            }
        } catch (\Exception $e) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Could not determine details of class %s.', $class_name));
            return null;
        }

        return [
            'full_classname' => $class_name,
        ];
    }

    private static function _filestamp_to_timestamp(string $filestamp): ?int
    {
        self::st_reset_error();

        if((string)((int)$filestamp) !== $filestamp
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
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Bad file timestamp format.'));
            return null;
        }

        return $my_timestamp;
    }
}
