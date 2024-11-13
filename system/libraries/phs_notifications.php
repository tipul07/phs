<?php
namespace phs\libraries;

class PHS_Notifications extends PHS_Language
{
    private static array $_notifications_arr = [];

    public function __construct()
    {
        parent::__construct();
        self::reset_notifications();
    }

    public static function default_notifications_arr() : array
    {
        return [
            'warnings' => [],
            'errors'   => [],
            'success'  => [],
        ];
    }

    public static function reset_notifications() : void
    {
        self::$_notifications_arr = self::default_notifications_arr();
    }

    public static function get_all_notifications() : array
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return self::$_notifications_arr;
    }

    public static function notifications_errors() : array
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return self::$_notifications_arr['errors'] ?? [];
    }

    public static function notifications_warnings() : array
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return self::$_notifications_arr['warnings'] ?? [];
    }

    public static function notifications_success() : array
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return self::$_notifications_arr['success'] ?? [];
    }

    public static function have_notifications_errors() : bool
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return !empty(self::$_notifications_arr['errors']);
    }

    public static function have_notifications_warnings() : bool
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return !empty(self::$_notifications_arr['warnings']);
    }

    public static function have_notifications_success() : bool
    {
        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        return !empty(self::$_notifications_arr['success']);
    }

    public static function have_any_notifications() : bool
    {
        return self::have_notifications_success() || self::have_notifications_warnings() || self::have_notifications_errors();
    }

    public static function have_errors_or_warnings_notifications() : bool
    {
        return self::have_notifications_warnings() || self::have_notifications_errors();
    }

    public static function add_error_notice($msg) : void
    {
        self::_add_something($msg, 'errors');
    }

    public static function add_warning_notice($msg) : void
    {
        self::_add_something($msg, 'warnings');
    }

    public static function add_success_notice($msg) : void
    {
        self::_add_something($msg, 'success');
    }

    private static function _add_something($msg, string $key) : void
    {
        if (empty($msg)) {
            return;
        }

        if (empty(self::$_notifications_arr)) {
            self::reset_notifications();
        }

        if (is_string($msg)) {
            self::$_notifications_arr[$key][] = $msg;
        } elseif (is_array($msg)) {
            foreach ($msg as $msg_str) {
                if (!is_string($msg_str)) {
                    continue;
                }

                self::$_notifications_arr[$key][] = $msg_str;
            }
        }
    }
}
