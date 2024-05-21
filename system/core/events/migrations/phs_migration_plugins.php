<?php

namespace phs\system\core\events\migrations;

include_once 'phs_migration.php';

use phs\libraries\PHS_Plugin;

class PHS_Event_Migration_plugins extends PHS_Event_Migration
{
    public const EP_INSTALLING = 'installing', EP_START = 'start', EP_FINISH = 'finish',
        EP_AFTER_ROLES = 'after_roles', EP_AFTER_JOBS = 'after_jobs';

    // region Triggers

    /**
     * This will be triggered only when installing plugin. Normal updates will not trigger this.
     *
     * @param PHS_Plugin $plugin_obj
     * @param string $old_version
     * @param string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_installing(
        PHS_Plugin $plugin_obj,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'_'.self::EP_INSTALLING,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    /**
     * This is triggered when starting plugin update OR installation. At installation `trigger_installing()` will be called first, then this trigger.
     *
     * @param PHS_Plugin $plugin_obj
     * @param string $old_version
     * @param string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_start(
        PHS_Plugin $plugin_obj,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'_'.self::EP_START,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function trigger_finish(
        PHS_Plugin $plugin_obj,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'_'.self::EP_FINISH,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function trigger_after_roles(
        PHS_Plugin $plugin_obj,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'_'.self::EP_AFTER_ROLES,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    public static function trigger_after_jobs(
        PHS_Plugin $plugin_obj,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'_'.self::EP_AFTER_JOBS,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }
    // endregion Triggers

    // region Listeners
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
    // endregion Listeners

    private static function _generate_event_input(
        PHS_Plugin $plugin_obj,
        string $old_version = '',
        string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : array {
        return [
            'is_forced'          => $is_forced,
            'is_dry_update'      => $is_dry_update,
            'old_version'        => $old_version,
            'new_version'        => $new_version,
            'plugin_instance_id' => $plugin_obj->instance_id(),
            'plugin_class'       => $plugin_obj::class,
        ];
    }
}
