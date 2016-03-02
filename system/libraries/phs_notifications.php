<?php

namespace phs\libraries;


class PHS_Notifications extends PHS_Language
{
    private static $_notifications_arr = false;

    function __construct()
    {
        parent::__construct();

        self::reset_notifications();
    }

    public static function default_notifications_arr()
    {
        return array(
            'warnings' => array(),
            'errors' => array(),
            'success' => array(),
        );
    }

    public static function reset_notifications()
    {
        self::$_notifications_arr = self::default_notifications_arr();
    }

    public static function get_all_notifications()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return self::$_notifications_arr;
    }

    public static function notifications_errors()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return (!empty( self::$_notifications_arr['errors'] )?self::$_notifications_arr['errors']:array());
    }

    public static function notifications_warnings()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return (!empty( self::$_notifications_arr['warnings'] )?self::$_notifications_arr['warnings']:array());
    }

    public static function notifications_success()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return (!empty( self::$_notifications_arr['success'] )?self::$_notifications_arr['success']:array());
    }

    public static function have_notifications_errors()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return (!empty( self::$_notifications_arr['errors'] )?true:false);
    }

    public static function have_notifications_warnings()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return (!empty( self::$_notifications_arr['warnings'] )?true:false);
    }

    public static function have_notifications_success()
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        return (!empty( self::$_notifications_arr['success'] )?true:false);
    }

    public static function add_error_notice( $msg )
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        self::$_notifications_arr['errors'][] = $msg;
    }

    public static function add_warning_notice( $msg )
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        self::$_notifications_arr['warnings'][] = $msg;
    }

    public static function add_success_notice( $msg )
    {
        if( self::$_notifications_arr === false )
            self::reset_notifications();

        self::$_notifications_arr['success'][] = $msg;
    }

}
