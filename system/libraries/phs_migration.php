<?php

namespace phs\libraries;

use phs\PHS_Db;
use phs\PHS_Maintenance;
use phs\system\core\models\PHS_Model_Migrations;
use phs\system\core\events\migrations\PHS_Event_Migration_models;

abstract class PHS_Migration extends PHS_Registry
{
    public const ERR_BOOTSTRAP = 20000;

    // Keep all event listeners in order to emulate the triggers if required
    private array $_callbacks = [];

    private ?array $_migration_record = null;

    private array $_script_details = [];

    private static ?PHS_Model_Migrations $_migrations_model = null;

    public function __construct(array $script_details)
    {
        parent::__construct();
        $this->_load_dependencies();
        $this->_validate_script_details($script_details);
    }

    abstract protected function bootstrap(bool $forced = false) : bool;

    final public function register(bool $forced = false) : bool
    {
        if ( !$this->bootstrap($forced) ) {
            $this->set_error_if_not_set(self::ERR_BOOTSTRAP, self::_t('Error in bootstrap call.'));

            return false;
        }

        if ( ($is_dry_update = PHS_Db::dry_update()) ) {
            PHS_Maintenance::output(self::_t('Script %s registered, but running in dry update mode.', static::class));
        }

        if ( !$is_dry_update
             && !$this->_record_migration_script($forced) ) {
            $this->set_error_if_not_set(self::ERR_BOOTSTRAP, self::_t('Error creating migration record.'));

            return false;
        }

        return true;
    }

    // region Model listeners
    /**
     * Execute callable before installing missing specific $model_class table or before installing all missing tables
     *
     * @param callable $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running before installing all missing tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function before_missing_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?PHS_Event_Migration_models {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_missing'],
            ['model_obj' => fn () => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_before_missing(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper($event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        );
    }

    /**
     * Execute callable after installing missing specific $model_class table or after installing all missing tables
     *
     * @param callable $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running after installing all missing tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function after_missing_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?PHS_Event_Migration_models {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_after_missing'],
            ['model_obj' => fn () => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_after_missing(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper($event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        );
    }

    /**
     * Execute callable before updating specific $model_class table or before updating all tables
     *
     * @param callable $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running before updating all tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function before_update_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?PHS_Event_Migration_models {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_update'],
            ['model_obj' => fn () => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_before_missing(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper($event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        );
    }

    /**
     * Execute callable after updating specific $model_class table or after updating all tables
     *
     * @param callable $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running after updating all tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function after_update_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?PHS_Event_Migration_models {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_after_update'],
            ['model_obj' => fn () => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_after_update(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper($event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        );
    }

    final public function model_event_listener_wrapper(PHS_Event_Migration_models $event_obj, callable $callback) : bool
    {
        if (!$event_obj->is_dry_update()) {
            $this->refresh_migration_record();
        }

        if (!$callback($event_obj)) {
            if (!$event_obj->is_dry_update()) {
                $this->migration_error($this->get_simple_error_message(self::_t('Unknown error.')));
            }

            return false;
        }

        return true;
    }
    // endregion Model listeners

    protected function refresh_migration_record() : bool
    {
        if ( !$this->_we_have_migration_record() ) {
            return false;
        }

        if (!($new_migration_record = self::$_migrations_model->refresh_migration($this->_migration_record))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            return false;
        }

        $this->_migration_record = $new_migration_record;

        return true;
    }

    protected function migration_error(string $error_msg) : bool
    {
        if ( !$this->_we_have_migration_record() ) {
            return false;
        }

        if (!($new_migration_record = self::$_migrations_model->migration_error($this->_migration_record, $error_msg))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            return false;
        }

        $this->_migration_record = $new_migration_record;

        return true;
    }

    private function _we_have_migration_record() : bool
    {
        if (!$this->_load_dependencies()) {
            $this->copy_or_set_static_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        if ( empty( $this->_migration_record['id'] ) ) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Migration record not present.'));

            return false;
        }

        return true;
    }

    private function _validate_script_details(array $script_details) : void
    {
        $this->_script_details = [
            'plugin'         => $script_details['plugin'] ?? '',
            'version'        => $script_details['version'] ?? '',
            'script'         => $script_details['script'] ?? '',
            'timestamp'      => $script_details['timestamp'] ?? 0,
            'file'           => $script_details['file'] ?? '',
            'full_classname' => $script_details['full_classname'] ?? '',
        ];
    }

    private function _record_migration_script(bool $forced = false) : bool
    {
        if (!$this->_load_dependencies()) {
            $this->set_error_if_not_set(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        if ( empty( $this->_script_details['plugin'] )
             || empty( $this->_script_details['script'] )) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Required details missing while registering record for migration script.'));

            return false;
        }

        if (!($record_arr = self::$_migrations_model->start_migration(
            $this->_script_details['plugin'],
            $this->_script_details['script'],
            $this->_script_details['version'],
            $forced)) ) {
            $this->copy_or_set_error(self::$_migrations_model,
                self::ERR_PARAMETERS, self::_t('Error loading required resources while registering record for migration script.'));

            return false;
        }

        $this->_migration_record = $record_arr;

        return true;
    }

    private function _keep_listener(callable $trigger_callable, array $args = []) : void
    {
        $this->_callbacks[] = [
            'trigger_callable' => $trigger_callable,
            'args'             => $args,
        ];
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ( !self::$_migrations_model
            && !(self::$_migrations_model = PHS_Model_Migrations::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
