<?php

namespace phs\libraries;

use Closure;
use phs\PHS_Db;
use phs\PHS_Maintenance;
use phs\system\core\models\PHS_Model_Migrations;
use phs\system\core\events\migrations\PHS_Event_Migration_models;
use phs\system\core\events\migrations\PHS_Event_Migration_plugins;

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
            PHS_Maintenance::output(self::_t('Migration script %s registered, but running in dry update mode.', static::class));
        }

        if ( !$is_dry_update
             && !$this->_record_migration_script($forced) ) {
            $this->set_error_if_not_set(self::ERR_BOOTSTRAP, self::_t('Error creating migration record.'));

            return false;
        }

        return true;
    }

    // region Plugins listeners

    /**
     * Execute callable when starting plugin installation
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $plugin_class
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_plugins
     */
    final public function plugin_install(
        string | callable | array | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?PHS_Event_Migration_plugins {
        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_install'],
            ['plugin_obj' => fn () => $plugin_class::get_instance(), 'is_forced' => true]
        );

        return PHS_Event_Migration_plugins::listen_install(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper($event_obj, $callback),
            $plugin_class,
            $priority
        );
    }

    /**
     * Execute callable when starting a plugin installation or a plugin update.
     * At installation start event is called after install event
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $plugin_class
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_plugins
     */
    final public function plugin_start(
        string | callable | array | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?PHS_Event_Migration_plugins {
        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_start'],
            ['plugin_obj' => fn () => $plugin_class::get_instance(), 'is_forced' => true]
        );

        return PHS_Event_Migration_plugins::listen_start(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper($event_obj, $callback),
            $plugin_class,
            $priority
        );
    }

    /**
     * Execute callable at plugin installation or update after roles were installed
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $plugin_class
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_plugins
     */
    final public function plugin_after_roles(
        string | callable | array | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?PHS_Event_Migration_plugins {
        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_after_roles'],
            ['plugin_obj' => fn () => $plugin_class::get_instance(), 'is_forced' => true]
        );

        return PHS_Event_Migration_plugins::listen_after_roles(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper($event_obj, $callback),
            $plugin_class,
            $priority
        );
    }

    /**
     * Execute callable at plugin installation or update after agent jobs were installed
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $plugin_class
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_plugins
     */
    final public function plugin_after_jobs(
        string | callable | array | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?PHS_Event_Migration_plugins {
        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_after_jobs'],
            ['plugin_obj' => fn () => $plugin_class::get_instance(), 'is_forced' => true]
        );

        return PHS_Event_Migration_plugins::listen_after_jobs(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper($event_obj, $callback),
            $plugin_class,
            $priority
        );
    }

    /**
     * Execute callable when plugin installation or plugin update is finished (after models are updated)
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $plugin_class
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_plugins
     */
    final public function plugin_finish(
        string | callable | array | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?PHS_Event_Migration_plugins {
        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_finish'],
            ['plugin_obj' => fn () => $plugin_class::get_instance(), 'is_forced' => true]
        );

        return PHS_Event_Migration_plugins::listen_finish(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper($event_obj, $callback),
            $plugin_class,
            $priority
        );
    }

    final public function plugin_event_listener_wrapper(PHS_Event_Migration_plugins $event_obj, string | callable | array | Closure $callback) : bool
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
    // endregion Plugins listeners

    // region Model listeners
    /**
     * Execute callable before installing missing specific $model_class table or before installing all missing tables
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running before installing all missing tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function before_missing_table(
        string | callable | array | Closure $callback,
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
     * @param string|callable|array|Closure $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running after installing all missing tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function after_missing_table(
        string | callable | array | Closure $callback,
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
     * @param string|callable|array|Closure $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running before updating all tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function before_update_table(
        string | callable | array | Closure $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?PHS_Event_Migration_models {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_update'],
            ['model_obj' => fn () => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_before_update(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper($event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        );
    }

    /**
     * Execute callable after updating specific $model_class table or after updating all tables
     *
     * @param string|callable|array|Closure $callback Callable
     * @param string $model_class What's the model for which we want to run this migration
     * @param string $table_name Empty table_name means running after updating all tables for model
     * @param int $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function after_update_table(
        string | callable | array | Closure $callback,
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

    final public function model_event_listener_wrapper(PHS_Event_Migration_models $event_obj, string | callable | array | Closure $callback) : bool
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

    private function _keep_listener(string | callable | array | Closure $trigger_callable, array $args = []) : void
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
