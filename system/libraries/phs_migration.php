<?php
namespace phs\libraries;

use phs\system\core\models\PHS_Model_Migrations;
use phs\system\core\events\migrations\PHS_Event_Migration_models;

abstract class PHS_Migration extends PHS_Registry
{
    public const ERR_BOOTSTRAP = 20000;

    private static ?PHS_Model_Migrations $_migrations_model = null;

    // Keep all event listeners in order to emulate the triggers if required
    private array $_callbacks = [];

    abstract public function bootstrap(): bool;

    public function __construct() {
        parent::__construct();
        self::_load_dependencies();
    }

    final public function register(): bool
    {
        if( !$this->bootstrap() ) {
            if( !$this->has_error() ) {
                $this->set_error(self::ERR_BOOTSTRAP, self::_t('Error in bootstrap call.'));
            }

            return false;
        }
    }

    /**
     * Execute callable before installing missing specific $model_class table or before installing all missing tables
     *
     * @param  callable  $callback Callable
     * @param  string  $model_class What's the model for which we want to run this migration
     * @param  string  $table_name Empty table_name means running before installing all missing tables for model
     * @param  int  $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function before_missing_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ): ?PHS_Event_Migration_models
    {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_missing'],
            ['model_obj' => fn() => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_before_missing($callback, $model_class, $table_name, $priority);
    }

    /**
     * Execute callable after installing missing specific $model_class table or after installing all missing tables
     *
     * @param  callable  $callback Callable
     * @param  string  $model_class What's the model for which we want to run this migration
     * @param  string  $table_name Empty table_name means running after installing all missing tables for model
     * @param  int  $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function after_missing_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ): ?PHS_Event_Migration_models
    {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_after_missing'],
            ['model_obj' => fn() => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_after_missing($callback, $model_class, $table_name, $priority);
    }

    /**
     * Execute callable before updating specific $model_class table or before updating all tables
     *
     * @param  callable  $callback Callable
     * @param  string  $model_class What's the model for which we want to run this migration
     * @param  string  $table_name Empty table_name means running before updating all tables for model
     * @param  int  $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function before_update_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ): ?PHS_Event_Migration_models
    {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_before_update'],
            ['model_obj' => fn() => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_before_missing($callback, $model_class, $table_name, $priority);
    }

    /**
     * Execute callable after updating specific $model_class table or after updating all tables
     *
     * @param  callable  $callback Callable
     * @param  string  $model_class What's the model for which we want to run this migration
     * @param  string  $table_name Empty table_name means running after updating all tables for model
     * @param  int  $priority
     *
     * @return null|PHS_Event_Migration_models
     */
    final public function after_update_table(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ): ?PHS_Event_Migration_models
    {
        $this->_keep_listener(
            [PHS_Event_Migration_models::class, 'trigger_after_update'],
            ['model_obj' => fn() => $model_class::get_instance(), 'table_name' => $table_name, 'is_forced' => true]
        );

        return PHS_Event_Migration_models::listen_after_update($callback, $model_class, $table_name, $priority);
    }

    private function _keep_listener(callable $trigger_callable, array $args = []): void
    {
        $this->_callbacks[] = [
            'callback' => $trigger_callable,
            'args' => $args,
        ];
    }

    private static function _load_dependencies(): bool
    {
        self::st_reset_error();

        if( !self::$_migrations_model
            && !(self::$_migrations_model = PHS_Model_Migrations::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));
            return false;
        }

        return true;
    }
}
