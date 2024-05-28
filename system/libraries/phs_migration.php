<?php

namespace phs\libraries;

use Closure;
use phs\PHS_Db;
use phs\PHS_Maintenance;
use phs\system\core\models\PHS_Model_Migrations;
use phs\system\core\events\migrations\PHS_Event_Migration;
use phs\system\core\events\migrations\PHS_Event_Migration_models;
use phs\system\core\events\migrations\PHS_Event_Migration_plugins;
use phs\system\core\events\migrations\PHS_Event_Migrations_finish;

abstract class PHS_Migration extends PHS_Registry
{
    public const ERR_BOOTSTRAP = 20000, ERR_LISTENER = 20001, ERR_MIGRATION_RUN = 20002;

    // ! Once how many steps should the migration script call PHS_Maintenance::output() to keep the connection alive
    // ! (in case of long-running scripts) You can overwrite this in your migration script
    protected int $_progress_step = 50;

    // At which step did the migration script output progress
    private int $_last_step_output = 0;

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

        PHS_Event_Migrations_finish::listen([$this, 'finish_migration_record']);

        return true;
    }

    public function rerun() : bool
    {
        $this->reset_error();

        if (empty($this->_callbacks)) {
            return true;
        }

        foreach ($this->_callbacks as $callback_details) {
            if (!($callback = $callback_details['trigger_callback'] ?? '')
                || !@is_callable($callback)) {
                $this->set_error(self::ERR_MIGRATION_RUN, self::_t('Invalid listener callback.'));

                return false;
            }

            $args = $this->_resolve_trigger_callback_arguments($callback_details['args'] ?? []);

            /** @var PHS_Event_Migration $event_obj */
            if (!($event_obj = @$callback(...$args))
                || !($event_obj instanceof PHS_Event_Migration)
                || $event_obj->result_has_error()) {
                $this->set_error(self::ERR_MIGRATION_RUN,
                    self::_t('Error running migration functionality.'));

                PHS_Logger::error('Error while running migration script '.$this->get_migration_script()
                                  .', plugin: '.$this->get_migration_plugin().': '
                                  .($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'),
                    PHS_Logger::TYPE_MAINTENANCE);

                return false;
            }
        }

        PHS_Logger::notice('Finished running migration script '.$this->get_migration_script()
                          .', plugin: '.$this->get_migration_plugin().'.',
            PHS_Logger::TYPE_MAINTENANCE);

        /** @var PHS_Event_Migrations_finish $event_obj */
        if ( !($event_obj = PHS_Event_Migrations_finish::get_instance_with_input(['is_forced' => true, 'is_dry_update' => false]))
            || !$this->finish_migration_record($event_obj)) {
            PHS_Logger::error('Error triggering migration finish event for migration script '.$this->get_migration_script()
                               .', plugin: '.$this->get_migration_plugin().': '
                              .$this->get_simple_error_message(self::_t('Unknown error.')),
                PHS_Logger::TYPE_MAINTENANCE);

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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_install'],
            [
                'plugin_obj'    => fn () => $plugin_class::get_instance(),
                'old_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'new_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_plugins::listen_install(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper(PHS_Event_Migration_plugins::EP_INSTALL, $event_obj, $callback),
            $plugin_class,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_start'],
            [
                'plugin_obj'    => fn () => $plugin_class::get_instance(),
                'old_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'new_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_plugins::listen_start(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper(PHS_Event_Migration_plugins::EP_START, $event_obj, $callback),
            $plugin_class,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_after_roles'],
            [
                'plugin_obj'    => fn () => $plugin_class::get_instance(),
                'old_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'new_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_plugins::listen_after_roles(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper(PHS_Event_Migration_plugins::EP_AFTER_ROLES, $event_obj, $callback),
            $plugin_class,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_after_jobs'],
            [
                'plugin_obj'    => fn () => $plugin_class::get_instance(),
                'old_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'new_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_plugins::listen_after_jobs(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper(PHS_Event_Migration_plugins::EP_AFTER_JOBS, $event_obj, $callback),
            $plugin_class,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_plugins::class, 'trigger_finish'],
            [
                'plugin_obj'    => fn () => $plugin_class::get_instance(),
                'old_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'new_version'   => fn () => $plugin_class::get_instance()?->get_plugin_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_plugins::listen_finish(
            fn (PHS_Event_Migration_plugins $event_obj) => $this->plugin_event_listener_wrapper(PHS_Event_Migration_plugins::EP_FINISH, $event_obj, $callback),
            $plugin_class,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
    }

    final public function plugin_event_listener_wrapper(string $trigger_name, PHS_Event_Migration_plugins $event_obj, string | callable | array | Closure $callback) : bool
    {
        if (!$event_obj->is_dry_update()) {
            $this->refresh_migration_record();
        }

        PHS_Maintenance::output("\t".'START migration script '.$this->get_migration_script().' for plugin trigger '.$trigger_name);

        if (!$callback($event_obj)) {
            PHS_Maintenance::output("\t".'ERROR migration script '.$this->get_migration_script().' for plugin trigger '.$trigger_name);

            if (!$event_obj->is_dry_update()) {
                $this->migration_error($this->get_simple_error_message(self::_t('Unknown error.')));
            }

            return false;
        }

        PHS_Maintenance::output("\t".'FINISH migration script '.$this->get_migration_script().' for plugin trigger '.$trigger_name);

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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_missing'],
            [
                'model_obj'     => fn () => $model_class::get_instance(),
                'table_name'    => $table_name,
                'old_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'new_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_models::listen_before_missing(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper(PHS_Event_Migration_models::EP_BEFORE_MISSING, $event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_after_missing'],
            [
                'model_obj'     => fn () => $model_class::get_instance(),
                'table_name'    => $table_name,
                'old_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'new_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_models::listen_after_missing(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper(PHS_Event_Migration_models::EP_AFTER_MISSING, $event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_update'],
            [
                'model_obj'     => fn () => $model_class::get_instance(),
                'table_name'    => $table_name,
                'old_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'new_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_models::listen_before_update(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper(PHS_Event_Migration_models::EP_BEFORE_UPDATE, $event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
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
        $this->reset_error();

        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_after_update'],
            [
                'model_obj'     => fn () => $model_class::get_instance(),
                'table_name'    => $table_name,
                'old_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'new_version'   => fn () => $model_class::get_instance()?->get_model_version(),
                'is_dry_update' => false,
                'is_forced'     => true,
            ]
        );

        if ( !($listen_obj = PHS_Event_Migration_models::listen_after_update(
            fn (PHS_Event_Migration_models $event_obj) => $this->model_event_listener_wrapper(PHS_Event_Migration_models::EP_AFTER_UPDATE, $event_obj, $callback),
            $model_class,
            $table_name,
            $priority
        )) ) {
            $this->set_error(self::ERR_LISTENER,
                self::st_get_simple_error_message(self::_t('Error installing migration listener event.')));

            return null;
        }

        return $listen_obj;
    }

    final public function model_event_listener_wrapper(string $trigger_name, PHS_Event_Migration_models $event_obj, string | callable | array | Closure $callback) : bool
    {
        if (!$event_obj->is_dry_update()) {
            $this->refresh_migration_record();
        }

        PHS_Maintenance::output("\t".'START migration script '.$this->get_migration_script().' for model trigger '.$trigger_name);

        if (!$callback($event_obj)) {
            PHS_Maintenance::output("\t".'ERROR migration script '.$this->get_migration_script().' for model trigger '.$trigger_name);

            if (!$event_obj->is_dry_update()) {
                $this->migration_error($this->get_simple_error_message(self::_t('Unknown error.')));
            }

            return false;
        }

        PHS_Maintenance::output("\t".'FINISH migration script '.$this->get_migration_script().' for model trigger '.$trigger_name);

        return true;
    }
    // endregion Model listeners

    final public function finish_migration_record(PHS_Event_Migrations_finish $event_obj) : bool
    {
        if ( !$this->_we_have_migration_record() ) {
            return true;
        }

        if (!($new_migration_record = self::$_migrations_model->migration_finish($this->_migration_record))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating migration details.'));

            $event_obj->add_result_error('WARNING: Error updating migration script: '.$this->get_migration_script()
                                         .', plugin: '.$this->get_migration_plugin());

            return false;
        }

        $this->_migration_record = $new_migration_record;

        return true;
    }

    protected function refresh_migration_record(?int $total_count = null, ?int $current_count = null) : bool
    {
        $this->reset_error();

        if ( $current_count !== null
             && $this->_progress_step !== 0
             && ($current_count % $this->_progress_step === 0
                 // Maybe we started another process?
                 || $current_count < $this->_last_step_output)
        ) {
            $progress_perc = !empty($total_count)
                ? min(100, ceil(($current_count * 100) / $total_count))
                : 0;

            PHS_Maintenance::output("\t".'Script '.$this->get_migration_script().', plugin '.$this->get_migration_plugin()
                                    .', progress '.$current_count.($total_count !== null ? '/'.$total_count.' ('.$progress_perc.'%)' : '').'.');
            $this->_last_step_output = $current_count;
        }

        if ( !$this->_we_have_migration_record() ) {
            return false;
        }

        if (!($new_migration_record = self::$_migrations_model->refresh_migration($this->_migration_record, $total_count, $current_count))) {
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

    protected function get_migration_plugin() : string
    {
        return $this->_script_details['plugin'] ?? '';
    }

    protected function get_migration_plugin_version() : string
    {
        return $this->_script_details['version'] ?? '';
    }

    protected function get_migration_script() : string
    {
        return $this->_script_details['script'] ?? '';
    }

    private function _resolve_trigger_callback_arguments(array $args) : array
    {
        $new_args = [];
        foreach ($args as $key => $val) {
            if (is_callable($val)) {
                $new_args[$key] = $val();
                continue;
            }

            $new_args[$key] = $val;
        }

        return $new_args;
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

    private function _keep_listener(string | callable | array | Closure $trigger_callback, array $args = []) : void
    {
        $this->_callbacks[] = [
            'trigger_callback' => $trigger_callback,
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
