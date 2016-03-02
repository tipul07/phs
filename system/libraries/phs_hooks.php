<?php

namespace phs\libraries;

use \phs\libraries\PHS_Registry;
use \phs\PHS;

//! This class define all core hooks (for usability)
class PHS_Hooks extends PHS_Registry
{
    const H_AFTER_BOOTSTRAP = 'after_bootstrap', H_BEFORE_ACTION_EXECUTE = 'before_action_execute', H_AFTER_ACTION_EXECUTE = 'after_action_execute',

         // Logging hooks
         H_LOG = 'phs_logger',

         // URL hooks
         H_URL_PARAMS = 'phs_url_params',

         // Notifications hooks
         H_NOTIFICATIONS_DISPLAY = 'phs_notifications_display',

         // Captcha hooks
         H_CAPTCHA_DISPLAY = 'phs_captcha_display',

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

    public static function default_captcha_hook_args()
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
            'captcha_buffer' => '',
        );
    }
}
