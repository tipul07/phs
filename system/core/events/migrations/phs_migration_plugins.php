<?php

namespace phs\system\core\events\migrations;

include_once 'phs_migration.php';

use Closure;
use phs\libraries\PHS_Plugin;

class PHS_Event_Migration_plugins extends PHS_Event_Migration
{
    public const EP_INSTALL = 'install', EP_START = 'start',
        EP_AFTER_ROLES = 'after_roles', EP_AFTER_JOBS = 'after_jobs', EP_FINISH = 'finish';

    // region Triggers

    /**
     * This will be triggered only when installing plugin. Normal updates will not trigger this.
     *
     * @param null|PHS_Plugin $plugin_obj
     * @param null|string $old_version
     * @param null|string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_install(
        ?PHS_Plugin $plugin_obj,
        ?string $old_version = '',
        ?string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        if ( !$plugin_obj ) {
            return null;
        }

        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'::'.self::EP_INSTALL,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    /**
     * This is triggered when starting plugin update OR installation. At installation `trigger_install()` will be called first, then this trigger.
     *
     * @param null|PHS_Plugin $plugin_obj
     * @param null|string $old_version
     * @param null|string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_start(
        ?PHS_Plugin $plugin_obj,
        ?string $old_version = '',
        ?string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        if ( !$plugin_obj ) {
            return null;
        }

        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'::'.self::EP_START,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    /**
     * This is triggered after plugin roles have been installed (at plugin update OR installation)
     *
     * @param null|PHS_Plugin $plugin_obj
     * @param null|string $old_version
     * @param null|string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_after_roles(
        ?PHS_Plugin $plugin_obj,
        ?string $old_version = '',
        ?string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        if ( !$plugin_obj ) {
            return null;
        }

        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'::'.self::EP_AFTER_ROLES,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    /**
     * This is triggered after plugin agent jobs have been installed (at plugin update OR installation)
     *
     * @param null|PHS_Plugin $plugin_obj
     * @param null|string $old_version
     * @param null|string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_after_jobs(
        ?PHS_Plugin $plugin_obj,
        ?string $old_version = '',
        ?string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        if ( !$plugin_obj ) {
            return null;
        }

        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'::'.self::EP_AFTER_JOBS,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }

    /**
     * This is triggered after plugin installation or update is finished (even after all models have been installed or updated)
     *
     * @param null|PHS_Plugin $plugin_obj
     * @param null|string $old_version
     * @param null|string $new_version
     * @param bool $is_dry_update
     * @param bool $is_forced
     *
     * @return null|self
     */
    public static function trigger_finish(
        ?PHS_Plugin $plugin_obj,
        ?string $old_version = '',
        ?string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : ?self {
        if ( !$plugin_obj ) {
            return null;
        }

        return self::trigger(
            self::_generate_event_input($plugin_obj, $old_version, $new_version, $is_dry_update, $is_forced),
            $plugin_obj::class.'::'.self::EP_FINISH,
            ['stop_on_first_error' => true, 'include_listeners_without_prefix' => false]
        );
    }
    // endregion Triggers

    // region Listeners
    public static function listen_install(
        callable | array | string | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $plugin_class.'::'.self::EP_INSTALL,
            ['priority' => $priority]
        );
    }

    public static function listen_start(
        callable | array | string | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $plugin_class.'::'.self::EP_START,
            ['priority' => $priority]
        );
    }

    public static function listen_after_roles(
        callable | array | string | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $plugin_class.'::'.self::EP_AFTER_ROLES,
            ['priority' => $priority]
        );
    }

    public static function listen_after_jobs(
        callable | array | string | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $plugin_class.'::'.self::EP_AFTER_JOBS,
            ['priority' => $priority]
        );
    }

    public static function listen_finish(
        callable | array | string | Closure $callback,
        string $plugin_class,
        int $priority = 10
    ) : ?self {
        return self::listen(
            $callback,
            $plugin_class.'::'.self::EP_FINISH,
            ['priority' => $priority]
        );
    }
    // endregion Listeners

    private static function _generate_event_input(
        PHS_Plugin $plugin_obj,
        ?string $old_version = '',
        ?string $new_version = '',
        bool $is_dry_update = false,
        bool $is_forced = false,
    ) : array {
        return [
            'is_forced'          => $is_forced,
            'is_dry_update'      => $is_dry_update,
            'old_version'        => $old_version ?: '',
            'new_version'        => $new_version ?: '',
            'plugin_instance_id' => $plugin_obj->instance_id(),
            'plugin_class'       => $plugin_obj::class,
        ];
    }
}
