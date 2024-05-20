<?php

namespace phs\libraries;

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
        self::_load_dependencies();
        $this->_validate_script_details($script_details);
    }

    abstract public function bootstrap() : bool;

    final public function register() : bool
    {
        if ( !$this->bootstrap() ) {
            if ( !$this->has_error() ) {
                $this->set_error(self::ERR_BOOTSTRAP, self::_t('Error in bootstrap call.'));
            }

            return false;
        }

        if ( !$this->_record_migration_script() ) {
            if ( !$this->has_error() ) {
                $this->set_error(self::ERR_BOOTSTRAP, self::_t('Error creating migration record.'));
            }

            return false;
        }

        return true;
    }

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

        return PHS_Event_Migration_models::listen_before_missing($callback, $model_class, $table_name, $priority);
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

        return PHS_Event_Migration_models::listen_after_missing($callback, $model_class, $table_name, $priority);
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

        return PHS_Event_Migration_models::listen_before_missing($callback, $model_class, $table_name, $priority);
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

        return PHS_Event_Migration_models::listen_after_update($callback, $model_class, $table_name, $priority);
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

    private function _record_migration_script() : bool
    {
        $this->reset_error();

        if (empty(self::$_migrations_model)
           || !($flow_arr = self::$_migrations_model->fetch_default_flow_params(['table_name' => 'phs_migrations']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error loading required resources while registering record for migration script.'));

            return false;
        }

        if ( empty( $this->_script_details['plugin'] )
            || empty( $this->_script_details['script'] )) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Required details missing while registering record for migration script.'));

            return false;
        }

        $action_arr = $flow_arr;
        $action_arr['fields'] = [];

        if ( !($existing_arr = self::$_migrations_model->get_details_fields([
            'plugin' => $this->_script_details['plugin'],
            'script' => $this->_script_details['script'],
        ], $flow_arr)) ) {
            $existing_arr = null;

            $action_arr['fields']['plugin'] = $this->_script_details['plugin'];
            $action_arr['fields']['script'] = $this->_script_details['script'];
        }

        $action_arr['fields']['run_at_version'] = $this->_script_details['version'];
        $action_arr['fields']['start_run'] = date(self::$_migrations_model::DATETIME_DB);

        $record_arr = null;
        if ( (empty($existing_arr) && !($record_arr = self::$_migrations_model->insert($action_arr)))
            || (!empty($existing_arr) && !($record_arr = self::$_migrations_model->edit($existing_arr, $action_arr))) ) {
            $this->set_error(self::ERR_PARAMETERS,
                self::_t('Required details missing while registering record for migration script.'));

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

    private static function _load_dependencies() : bool
    {
        self::st_reset_error();

        if ( !self::$_migrations_model
            && !(self::$_migrations_model = PHS_Model_Migrations::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
