<?php

namespace phs\libraries;

use \phs\libraries\PHS_Registry;
use \phs\PHS;

//! This class define all core hooks (for usability)
class PHS_Hooks extends PHS_Registry
{
    const H_AFTER_BOOTSTRAP = 'after_bootstrap', H_BEFORE_ACTION_EXECUTE = 'before_action_execute', H_AFTER_ACTION_EXECUTE = 'after_action_execute',

         // Plugins hooks
         H_PLUGIN_SETTINGS = 'phs_plugin_settings',

         // Logging hooks
         H_LOG = 'phs_logger',

         // URL hooks
         H_URL_PARAMS = 'phs_url_params',

         // Notifications hooks
         H_NOTIFICATIONS_DISPLAY = 'phs_notifications_display',

         // Captcha hooks
         H_CAPTCHA_DISPLAY = 'phs_captcha_display', H_CAPTCHA_CHECK = 'phs_captcha_check',

         // User account hooks
         H_USER_DB_DETAILS = 'phs_user_db_details';

    public static function default_user_db_details_hook_args()
    {
        return array(
            'user_db_data' => false,
            'session_db_data' => false,
        );
    }

    public static function default_notifications_hook_args()
    {
        return array(
            'warnings' => array(),
            'errors' => array(),
            'success' => array(),
            'template' => array(
                'file' => '',
                'extra_paths' => array(),
            ), // default template
            'display_channels' => array( 'warnings', 'errors', 'success' ),
            'notifications_buffer' => '',
        );
    }

    public static function default_captcha_display_hook_args()
    {
        return array(
            'template' => array(
                'file' => '',
                'extra_paths' => array(),
            ), // default template
            'font' => 'default.ttf',
            'characters_count' => 5,
            'default_width' => 200,
            'default_height' => 50,
            'extra_img_style' => '',
            'extra_img_attrs' => '',
            'hook_errors' => false,
            'captcha_buffer' => '',
        );
    }

    public static function default_captcha_check_hook_args()
    {
        return array(
            'check_code' => '',
            'check_valid' => false,
            'hook_errors' => false,
        );
    }

    public static function trigger_captcha_display( $hook_args )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_captcha_display_hook_args() );

        // If we don't have hooks registered, we don't use captcha
        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_CAPTCHA_DISPLAY, $hook_args )) === null )
            return '';

        if( is_array( $hook_args )
        and !empty( $hook_args['captcha_buffer'] ) )
            return $hook_args['captcha_buffer'];

        if( !empty( $hook_args['hook_errors'] ) )
            return 'Error: ['.PHS_Error::arr_get_error_code( $hook_args['hook_errors'] ).'] '.PHS_Error::arr_get_error_message( $hook_args['hook_errors'] );

        return '';
    }

    public static function trigger_captcha_check( $code )
    {
        $hook_args = self::validate_array( array( 'check_code' => $code ), PHS_Hooks::default_captcha_check_hook_args() );

        return PHS::trigger_hooks( PHS_Hooks::H_CAPTCHA_CHECK, $hook_args );
    }
}
