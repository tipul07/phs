<?php
namespace phs\system\core\models;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;

class PHS_Model_Migrations extends PHS_Model
{
    private static ?bool $_model_installed = null;

    public function get_model_version() : string
    {
        return '1.0.1';
    }

    public function get_table_names() : array
    {
        return ['phs_migrations'];
    }

    public function get_main_table_name() : string
    {
        return 'phs_migrations';
    }

    public function get_settings_structure() : array
    {
        return [
            'minutes_to_stall' => [
                'display_name' => 'Minutes to stall',
                'display_hint' => 'After how many minutes should we consider a migration script as stalling',
                'type'         => PHS_Params::T_INT,
                'default'      => 15,
            ],
        ];
    }

    public function migration_model_is_installed(bool $force = false) : bool
    {
        if (empty($force)
             && null !== self::$_model_installed) {
            return self::$_model_installed;
        }

        self::$_model_installed = $this->check_table_exists(['table_name' => 'phs_migrations'], $force);

        return self::$_model_installed;
    }

    public function start_migration(string $plugin, string $script, string $version, bool $force = false) : ?array
    {
        $this->reset_error();

        if (!$this->migration_model_is_installed()) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Migrations model is not installed.'));

            return null;
        }

        $fields_arr = [];
        if (($existing_migration = $this->get_details_fields(['plugin' => $plugin, 'script' => $script]))) {
            if (!$force
                && $this->is_finished($existing_migration)) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('This migration was already considered.'));

                return null;
            }

            $fields_arr['end_run'] = null;
        } else {
            $fields_arr['plugin'] = $plugin;
            $fields_arr['script'] = $script;
        }

        $fields_arr['pid'] = @getmypid() ?: -1;
        $fields_arr['start_run'] = date(self::DATETIME_DB);
        $fields_arr['run_at_version'] = $version;

        $new_migration = null;
        if (($existing_migration && !($new_migration = $this->edit($existing_migration, ['fields' => $fields_arr, 'table_name' => 'phs_migrations'])))
           || (!$existing_migration && !($new_migration = $this->insert(['fields' => $fields_arr, 'table_name' => 'phs_migrations'])))
        ) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            return null;
        }

        return $new_migration;
    }

    public function refresh_migration(int | array $migration_data, ?int $total_count = null, ?int $current_count = null) : ?array
    {
        $this->reset_error();

        if (!$this->migration_model_is_installed()) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Migrations model is not installed.'));

            return null;
        }

        if (empty($migration_data)
            || !($migration_arr = $this->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration not found in database.'));

            return null;
        }

        $edit_arr = [];
        if (empty($migration_arr['pid'])) {
            if (!($pid = @getmypid())) {
                $pid = -1;
            }

            $edit_arr['pid'] = $pid;
        }

        if ($total_count !== null) {
            $edit_arr['total_count'] = $total_count;
        }
        if ($current_count !== null) {
            $edit_arr['current_count'] = $current_count;
        }

        $edit_arr['last_action'] = date(self::DATETIME_DB);

        if (!($new_job_arr = $this->edit($migration_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            return null;
        }

        return $new_job_arr;
    }

    public function migration_error(int | array $migration_data, string $error_msg) : ?array
    {
        $this->reset_error();

        if (!$this->migration_model_is_installed()) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Migrations model is not installed.'));

            return null;
        }

        if (empty($migration_data)
            || !($migration_arr = $this->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration not found in database.'));

            return null;
        }

        $edit_arr = [];
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = $error_msg;
        $edit_arr['last_action'] = date(self::DATETIME_DB);

        if (!($new_job_arr = $this->edit($migration_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            return null;
        }

        return $new_job_arr;
    }

    public function migration_finish(int | array $migration_data) : ?array
    {
        $this->reset_error();

        if (!$this->migration_model_is_installed()) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Migrations model is not installed.'));

            return null;
        }

        if (empty($migration_data)
            || !($migration_arr = $this->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration not found in database.'));

            return null;
        }

        $edit_arr = [];
        $edit_arr['pid'] = 0;
        $edit_arr['last_error'] = null;
        $edit_arr['end_run'] = $edit_arr['last_action'] = date(self::DATETIME_DB);

        if (!($new_job_arr = $this->edit($migration_arr, ['fields' => $edit_arr]))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            return null;
        }

        return $new_job_arr;
    }

    public function get_stalling_minutes() : int
    {
        static $stalling_minutes = null;

        if ($stalling_minutes !== null) {
            return $stalling_minutes;
        }

        $settings_arr = $this->get_db_settings();

        $stalling_minutes = (int)($settings_arr['minutes_to_stall'] ?? 0);

        return $stalling_minutes;
    }

    public function get_seconds_since_last_action(int | array $migration_data) : ?int
    {
        $this->reset_error();

        if (!$this->migration_model_is_installed()) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Migrations model is not installed.'));

            return null;
        }

        if (empty($migration_data)
            || !($migration_arr = $this->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration not found in database.'));

            return null;
        }

        return !empty($migration_arr['last_action']) ? seconds_passed($migration_arr['last_action']) : 0;
    }

    public function is_stalling(int | array $migration_data) : ?bool
    {
        $this->reset_error();

        if (!$this->migration_model_is_installed()) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Migrations model is not installed.'));

            return null;
        }

        if (empty($migration_data)
         || !($migration_arr = $this->data_to_array($migration_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Migration not found in database.'));

            return null;
        }

        return $this->is_running($migration_arr)
               && ($minutes_to_stall = $this->get_stalling_minutes())
               && floor($this->get_seconds_since_last_action($migration_arr) / 60) >= $minutes_to_stall;
    }

    public function is_running(int | array $migration_data) : bool
    {
        return !$this->migration_model_is_installed()
               || (!empty($migration_data)
                   && ($migration_arr = $this->data_to_array($migration_data))
                   && !empty($migration_arr['pid']));
    }

    public function is_finished(int | array $migration_data) : bool
    {
        return !$this->migration_model_is_installed()
               || (!empty($migration_data)
                   && ($migration_arr = $this->data_to_array($migration_data))
                   && empty($migration_arr['pid'])
                   && !empty($migration_arr['end_run'])
               );
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'phs_migrations':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'pid' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'script' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'run_at_version' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 50,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'total_count' => [
                        'type'    => self::FTYPE_INT,
                        'comment' => 'Total steps for completion',
                    ],
                    'current_count' => [
                        'type'    => self::FTYPE_INT,
                        'comment' => 'Current step',
                    ],
                    'start_run' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'end_run' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_action' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_error' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_phs_migrations(array $params) : ?array
    {
        if (empty($params['fields']['plugin'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a plugin for the migration.'));

            return null;
        }

        if (empty($params['fields']['script'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a script for the migration.'));

            return null;
        }

        $params['fields']['last_action'] = date(self::DATETIME_DB);

        if (empty($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $params['fields']['last_action'];
        }

        return $params;
    }

    protected function get_edit_prepare_params_phs_migrations(array $existing_data, array $params) : ?array
    {
        if (empty($params['fields']['last_action'])) {
            $params['fields']['last_action'] = date(self::DATETIME_DB);
        }

        return $params;
    }
}
