<?php

namespace phs\system\core\events\migrations;

include_once 'phs_migration.php';

use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Model_Core_base;

class PHS_Event_Migration_models extends PHS_Event_Migration
{
    public const EP_BEFORE_MISSING = 'before_missing', EP_AFTER_MISSING = 'after_missing',
        EP_BEFORE_UPDATE = 'before_update', EP_AFTER_UPDATE = 'after_update';

    protected function _input_parameters() : array
    {
        return array_merge(parent::_input_parameters(), [
            'model_instance_id' => '',
            'model_class'       => '',
            'table_name'        => '',
            'flow_params'       => [],
            'model_obj'         => null,
        ]);
    }

    public static function trigger_before_missing(
        PHS_Model_Core_base $model_obj,
        string $table_name = '',
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($model_obj, $table_name, $old_version, $new_version, $is_dry_update, $is_forced),
            $model_obj::class.'_'.self::EP_BEFORE_MISSING.'_'.$table_name,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function trigger_after_missing(
        PHS_Model_Core_base $model_obj,
        string $table_name = '',
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($model_obj, $table_name, $old_version, $new_version, $is_dry_update, $is_forced),
            $model_obj::class.'_'.self::EP_AFTER_MISSING.'_'.$table_name,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function trigger_before_update(
        PHS_Model_Core_base $model_obj,
        string $table_name = '',
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($model_obj, $table_name, $old_version, $new_version, $is_dry_update, $is_forced),
            $model_obj::class.'_'.self::EP_BEFORE_UPDATE.'_'.$table_name,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function trigger_after_update(
        PHS_Model_Core_base $model_obj,
        string $table_name = '',
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($model_obj, $table_name, $old_version, $new_version, $is_dry_update, $is_forced),
            $model_obj::class.'_'.self::EP_BEFORE_UPDATE.'_'.$table_name,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function listen_before_missing(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $model_class.'_'.self::EP_BEFORE_MISSING.'_'.$table_name,
            ['priority' => $priority]
        );
    }

    public static function listen_after_missing(
        callable $callback,
        string $model_class,
        string $table_name = '',
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $model_class.'_'.self::EP_AFTER_MISSING.'_'.$table_name,
            ['priority' => $priority]
        );
    }

    public static function listen_before_update(
        callable $callback,
        string $model_class,
        string $table_name,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $model_class.'_'.self::EP_BEFORE_UPDATE.'_'.$table_name,
            ['priority' => $priority]
        );
    }

    public static function listen_after_update(
        callable $callback,
        string $model_class,
        string $table_name,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $model_class.'_'.self::EP_AFTER_UPDATE.'_'.$table_name,
            ['priority' => $priority]
        );
    }

    private static function _generate_event_input(
        PHS_Model_Core_base $model_obj,
        string $table_name,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : array {
        /** @var null|PHS_Plugin $plugin_obj */
        $plugin_obj = $model_obj->get_plugin_instance();

        return [
            'is_forced'          => $is_forced,
            'is_dry_update'      => $is_dry_update,
            'old_version'        => $old_version,
            'new_version'        => $new_version,
            'model_instance_id'  => $model_obj->instance_id(),
            'model_class'        => $model_obj::class,
            'plugin_instance_id' => $plugin_obj ? $plugin_obj->instance_id() : '',
            'plugin_class'       => $plugin_obj ? $plugin_obj::class : '',
            'table_name'         => $table_name,
            'flow_params'        => ['table_name' => $table_name],
            'model_obj'          => $model_obj,
        ];
    }
}
