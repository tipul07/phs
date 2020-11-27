<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;

//! This class define all core hooks (for usability)
class PHS_Hooks extends PHS_Registry
{
    //
    //region Framework hooks definition
    //
    const H_AFTER_BOOTSTRAP = 'after_bootstrap', H_BEFORE_ACTION_EXECUTE = 'before_action_execute', H_AFTER_ACTION_EXECUTE = 'after_action_execute',

         // Language hooks
         H_LANGUAGE_DEFINITION = 'phs_language_definition',

         // Model hooks
         H_MODEL_EMPTY_DATA = 'phs_model_empty_data', H_MODEL_VALIDATE_DATA_FIELDS = 'phs_model_validate_data_fields',
         // low level insert and edit
         H_MODEL_INSERT_DATA = 'phs_model_insert_data', H_MODEL_EDIT_DATA = 'phs_model_edit_data', H_MODEL_HARD_DELETE_DATA = 'phs_model_hard_delete_data',

         // Paginator hooks
         H_PAGINATOR_ACTION_PARAMETERS = 'phs_paginator_action_parameters',

         // Plugins hooks
         H_PLUGIN_SETTINGS = 'phs_plugin_settings', H_PLUGIN_OBFUSCATED_SETTINGS_KEYS = 'phs_plugin_obfuscated_settings_keys',
         H_PLUGIN_REGISTRY = 'phs_plugin_registry',

         // Logging hooks
         H_LOG = 'phs_logger',

         // URL hooks
         H_URL_REWRITE = 'phs_url_rewrite', H_ROUTE = 'phs_route',

         // Location / Scripts hooks
         H_PAGE_INDEX = 'phs_page_index', H_PAGE_REGISTER = 'phs_page_register',

         // Email hooks
         H_EMAIL_INIT = 'phs_email_init',

         // Notifications hooks
         H_NOTIFICATIONS_DISPLAY = 'phs_notifications_display',

         // Cookie notice hooks
         H_COOKIE_NOTICE_DISPLAY = 'phs_cookie_notice_display',

         // Messages hooks
         H_MSG_GET_SUMMARY = 'phs_messages_summary', H_MSG_TYPES = 'phs_messages_types',
         H_MSG_SINGLE_DISPLAY_TYPES_ACTIONS = 'phs_messages_single_types_actions',
         // Alter write message form
         H_MSG_RENDER_WRITE_FORM = 'phs_messages_render_write_form',
         // Used before sending messages to users
         H_MSG_BEFORE_MESSAGES = 'phs_messages_before_messages',
         // Used after messages were sent to users
         H_MSG_MESSAGES_SENT = 'phs_messages_messages_sent',
         // If any plugin wants to set custom settings for current message that was written
         H_MSG_MESSAGES_CUSTOM_SETTINGS = 'phs_messages_custom_settings',

         // Captcha hooks
         H_CAPTCHA_DISPLAY = 'phs_captcha_display', H_CAPTCHA_CHECK = 'phs_captcha_check', H_CAPTCHA_REGENERATE = 'phs_captcha_regenerate',

         // Roles hooks
         H_GUEST_ROLES_SLUGS = 'phs_guest_roles_slugs',

         // API hooks
         H_API_REQUEST_INIT = 'phs_api_request_init',
         H_API_ROUTE = 'phs_api_route', H_API_REQUEST_ENDED = 'phs_api_request_ended',
         H_API_API_INITED = 'phs_api_inited',
         H_API_ACTION_INITED = 'phs_api_action_inited', H_API_ACTION_ENDED = 'phs_api_action_ended',

         // User account hooks
         H_USER_DB_DETAILS = 'phs_user_db_details', H_USER_LEVELS = 'phs_user_levels', H_USER_STATUSES = 'phs_user_statuses',
         // triggered to obtain an account structure for a given int, array (obtained from database) or false (for guest accounts)
         H_USER_ACCOUNT_STRUCTURE = 'phs_user_account_structure',
         // triggered when an action is performed on provided account (insert, edit, etc)
         H_USER_ACCOUNT_ACTION = 'phs_user_account_action',
         // triggered to get list of roles to assign to new users
         H_USER_REGISTRATION_ROLES = 'phs_user_registration_roles',
         // triggered to manage user fields at registration
         H_USERS_REGISTRATION = 'phs_users_registration',
         // triggered before user details get updated (used to change user details fields)
         H_USERS_DETAILS_FIELDS = 'phs_users_details_fields',
         // triggered after user details are updated
         H_USERS_DETAILS_UPDATED = 'phs_users_details_updated',
         // triggered when encoding user passwords
         H_USERS_ENCODE_PASS = 'phs_users_encode_pass',
         // triggered when generating user passwords
         H_USERS_GENERATE_PASS = 'phs_users_generate_pass',
         // triggered after user logs in successfully
         H_USERS_AFTER_LOGIN = 'phs_users_after_login',
         // triggered right when execute() function of login action is called
         H_USERS_LOGIN_ACTION_START = 'phs_users_login_action_start',
        // triggered right when execute() function of logout action is called
         H_USERS_LOGOUT_ACTION_START = 'phs_users_logout_action_start',
        // triggered right when execute() function of edit profile action is called
         H_USERS_EDIT_PROFILE_ACTION_START = 'phs_users_edit_profile_action_start',
        // triggered right when execute() function of change password action is called
         H_USERS_CHANGE_PASSWORD_ACTION_START = 'phs_users_change_password_action_start',
        // triggered right when execute() function of forgot password action is called
         H_USERS_FORGOT_PASSWORD_ACTION_START = 'phs_users_forgot_password_action_start',
        // triggered right when execute() function of register action is called
         H_USERS_REGISTER_ACTION_START = 'phs_users_register_action_start',

         // Layout hooks
         H_WEB_TEMPLATE_RENDERING = 'phs_web_template_rendering',
         H_WEB_SUBVIEW_RENDERING = 'phs_web_subview_rendering',
         H_ADMIN_TEMPLATE_PAGE_HEAD = 'phs_admin_template_page_head',
         H_ADMIN_TEMPLATE_PAGE_START = 'phs_admin_template_page_start',
         H_ADMIN_TEMPLATE_PAGE_END = 'phs_admin_template_page_end',
         // Triggered when main container which holds page content is rendered
         H_ADMIN_TEMPLATE_PAGE_FIRST_CONTENT = 'phs_admin_template_page_first_content',

         H_ADMIN_TEMPLATE_BEFORE_LEFT_MENU = 'phs_admin_template_before_left_menu',
         H_ADMIN_TEMPLATE_AFTER_LEFT_MENU = 'phs_admin_template_after_left_menu',
         H_ADMIN_TEMPLATE_BEFORE_RIGHT_MENU = 'phs_admin_template_before_right_menu',
         H_ADMIN_TEMPLATE_AFTER_RIGHT_MENU = 'phs_admin_template_after_right_menu',

         H_ADMIN_TEMPLATE_BEFORE_MAIN_MENU = 'phs_admin_template_before_main_menu',
         H_ADMIN_TEMPLATE_AFTER_MAIN_MENU = 'phs_admin_template_after_main_menu',

         H_MAIN_TEMPLATE_PAGE_HEAD = 'phs_main_template_page_head',
         H_MAIN_TEMPLATE_PAGE_START = 'phs_main_template_page_start',
         H_MAIN_TEMPLATE_PAGE_END = 'phs_main_template_page_end',
         // Triggered when main container which holds page content is rendered
         H_MAIN_TEMPLATE_PAGE_FIRST_CONTENT = 'phs_main_template_page_first_content',

         H_MAIN_TEMPLATE_BEFORE_LEFT_MENU = 'phs_main_template_before_left_menu',
         H_MAIN_TEMPLATE_AFTER_LEFT_MENU = 'phs_main_template_after_left_menu',
         H_MAIN_TEMPLATE_BEFORE_RIGHT_MENU = 'phs_main_template_before_right_menu',
         H_MAIN_TEMPLATE_AFTER_RIGHT_MENU = 'phs_main_template_after_right_menu',

         H_MAIN_TEMPLATE_BEFORE_MAIN_MENU = 'phs_main_template_before_main_menu',
         H_MAIN_TEMPLATE_AFTER_MAIN_MENU = 'phs_main_template_after_main_menu',
         H_MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN = 'phs_main_template_before_main_menu_logged_in',
         H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN = 'phs_main_template_after_main_menu_logged_in',
         H_MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT = 'phs_main_template_before_main_menu_logged_out',
         H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT = 'phs_main_template_after_main_menu_logged_out';
    //
    //endregion Framework hooks definition
    //

    //
    //region Common hooks functionality
    //
    /**
     * @return array
     */
    public static function default_common_hook_args()
    {
        return array(
            'hook_errors' => self::default_error_array(),
        );
    }

    /**
     * @param array $hook_args
     * @return array
     */
    public static function get_hook_args_error( $hook_args )
    {
        if( empty( $hook_args ) or !is_array( $hook_args )
         or empty( $hook_args['hook_errors'] ) or !is_array( $hook_args['hook_errors'] ) )
            return self::default_error_array();

        return self::validate_array( $hook_args['hook_errors'], self::default_error_array() );
    }

    /**
     * @param array $hook_args
     * @return bool
     */
    public static function hook_args_has_error( $hook_args )
    {
        return self::arr_has_error( self::get_hook_args_error( $hook_args ) );
    }

    /**
     * @param array $hook_args
     * @return array
     */
    public static function hook_args_reset_error( $hook_args )
    {
        $hook_args = self::validate_array( $hook_args, self::default_common_hook_args() );

        $hook_args['hook_errors'] = self::arr_reset_error( $hook_args['hook_errors'] );

        return $hook_args;
    }

    /**
     * Set an error on provided hook arguments
     * @param array $hook_args
     * @param int $error_no
     * @param string $error_msg
     * @param string $error_debug_msg
     * @return array
     */
    public static function hook_args_set_error( $hook_args, $error_no, $error_msg, $error_debug_msg = '' )
    {
        $hook_args = self::hook_args_reset_error( $hook_args );

        $hook_args['hook_errors'] = self::arr_set_error( $error_no, $error_msg, $error_debug_msg );

        return $hook_args;
    }

    /**
     * @param array $hook_args
     *
     * @return array
     */
    public static function hook_args_definition( $hook_args )
    {
        return self::validate_array_recursive( $hook_args, self::default_common_hook_args() );
    }

    /**
     * @param array $hook_args
     *
     * @return array
     */
    public static function reset_common_hook_args( $hook_args )
    {
        if( empty( $hook_args ) or !is_array( $hook_args ) )
            return self::validate_array( $hook_args, self::default_common_hook_args() );

        $hook_args['hook_errors'] = self::default_error_array();

        return $hook_args;
    }
    //
    //endregion Common hooks functionality
    //

    //
    //region URL and routing hooks
    //
    public static function default_url_rewrite_hook_args()
    {
        return self::hook_args_definition( array(

            // Received parameters
            'route_arr' => array(),
            'args' => array(),
            'raw_args' => array(),

            // URL generated by PHS
            'stock_args' => array(),
            'stock_query_string' => array(),
            'stock_url' => '',

            // URL generated by plugins
            'new_url' => false,

        ) );
    }

    public static function default_phs_route_hook_args()
    {
        return self::hook_args_definition( array(

            // Ask plugins if they want to change this route
            'original_route' => false,

            // Plugins should change this into a route array if they want to change the route (see PHS::validate_route_from_parts( $route_arr ))
            'altered_route' => false,

        ) );
    }

    public static function default_api_hook_args()
    {
        return self::hook_args_definition( array(
            // Instantiated API instance. If no plugin picks up PHS_Hooks::H_API_REQUEST_INIT hook call \phs\PHS_Api class instance will be used
            'api_obj' => false,

            // This is an array of API route tokens (NOT a PHS route)
            // This can be translated by plugins in other API route tokens (change action which will be run in the end)
            // If no plugin alters this, API class will check all API defined routes to obtain a PHS route from tokens in this array
            // (array)
            'api_route_tokens' => false,

            // Plugins should set this to an API tokenized path (see PHS_Api::tokenize_api_route()) (array)
            'altered_api_route_tokens' => false,

            // Matched API route defined in plugins (if any) after comparing api_route_tokens against route segments of each API route
            //// (array)
            'api_route' => false,

            // PHS route action to be run for current API request
            'phs_route' => false,

            'action_obj' => false,

        ) );
    }
    //
    //endregion URL and routing hooks
    //

    //
    //region Paginator hooks
    //
    public static function default_paginator_action_parameters_hook_args()
    {
        return self::hook_args_definition( array(
            'paginator_action_obj' => false,
            'paginator_params' => array(),
        ) );
    }
    //
    //endregion Paginator hooks
    //

    //
    //region Language hooks
    //
    public static function default_language_definition_hook_args()
    {
        return self::hook_args_definition( array(
            'default_language' => false,
            'languages_arr' => array(),
        ) );
    }
    //
    //endregion Language hooks
    //

    //
    //region Action execution hooks
    //
    public static function default_page_location_hook_args()
    {
        return self::hook_args_definition( array(
            'action_result' => false,
            'page_template' => false,
            'page_template_args' => false,
            'new_page_template' => false,
            'new_page_template_args' => false,
        ) );
    }

    public static function default_action_execute_hook_args()
    {
        return self::hook_args_definition( array(
            // Tells if execution of action should be stopped and action_result returned by the hook to be used as action result
            'stop_execution' => false,
            'action_obj' => false,
            'action_result' => PHS_Action::default_action_result(),
        ) );
    }

    public static function default_single_types_actions_hook_args()
    {
        return self::hook_args_definition( array(
            'actions_arr' => array(),
            'message_data' => false,
            'destination_str' => '',
            'author_handle' => '',
        ) );
    }
    //
    //endregion Action execution hooks
    //

    //
    //region Plugin hooks
    //
    public static function default_plugin_settings_hook_args()
    {
        return self::hook_args_definition( array(
            // Instance id for which we have these settings values
            'instance_id' => '',
            // Current settings (if required)
            'settings_arr' => array(),
            // Array with keys which should be obfuscated in settings array
            'obfucate_keys_arr' => array(),
        ) );
    }
    //
    //endregion Plugin hooks
    //

    //
    //region Internal messages hooks
    //
    public static function default_message_types_hook_args()
    {
        return self::hook_args_definition( array(
            'types_arr' => array(),
        ) );
    }

    public static function default_message_hook_args()
    {
        return self::hook_args_definition( array(
            'message_data' => false,
            'reply_message_data' => false,
            'followup_message_data' => false,
            'thread_message_data' => false,
            'body_data' => false,
            'author_data' => false,
            'write_params' => false,
            'custom_settings' => array(),
            'message_results' => array(),
        ) );
    }

    public static function default_messages_summary_hook_args()
    {
        return self::hook_args_definition( array(
            'messages_new' => 0,
            'messages_count' => 0,
            'messages_list' => array(),
            'list_limit' => 5,
            'summary_container_id' => '',
            'template' => array(
                'file' => '',
                'extra_paths' => array(),
            ), // default template
            'summary_buffer' => '',
        ) );
    }
    //
    //endregion Internal messages hooks
    //

    //
    //region Database model hooks
    //
    public static function default_model_validate_data_fields_hook_args()
    {
        return self::hook_args_definition( array(
            'flow_params' => false,
            'table_fields' => array(),
        ) );
    }

    public static function default_model_empty_data_hook_args()
    {
        return self::hook_args_definition( array(
            'data_arr' => array(),
            'flow_params' => false,
        ) );
    }

    public static function default_model_insert_data_hook_args()
    {
        return self::hook_args_definition( array(
            'fields_arr' => array(),
            'table_name' => false,
            'new_db_record' => false,
        ) );
    }

    public static function default_model_edit_data_hook_args()
    {
        return self::hook_args_definition( array(
            'fields_arr' => array(),
            'table_name' => false,
            'new_db_record' => false,
            'old_db_record' => false,
        ) );
    }

    public static function default_model_hard_delete_data_hook_args()
    {
        return self::hook_args_definition( array(
            'table_name' => false,
            'db_record' => false,
        ) );
    }
    //
    //endregion Database model hooks
    //

    //
    //region User account hooks
    //
    // Default hook parameters sent for hooks related to guest roles
    public static function default_guest_roles_hook_args()
    {
        return self::hook_args_definition( [
            'guest_roles' => [],
       ] );
    }

    // Default hook parameters sent for hooks related to user account
    public static function default_user_account_hook_args()
    {
        return self::hook_args_definition( [
            'account_data' => false,
            'account_details_data' => false,
       ] );
    }

    // Default hook parameters sent for hooks related to user account (including insert/edit parameters)
    public static function default_user_account_fields_hook_args()
    {
        return self::hook_args_definition( [
            'account_data' => false,
            'account_details_data' => false,
            'account_fields' => false,
            'account_details_fields' => false,
       ] );
    }

    public static function default_user_registration_roles_hook_args()
    {
        return self::hook_args_definition( [
           'roles_arr' => [],
           'account_data' => false,
       ] );
    }

    public static function default_password_expiration_data()
    {
        return [
            'is_expired' => false,
            'show_only_warning' => false,
            'pass_expires_seconds' => 0,
            'last_pass_change_seconds' => 0,
            'expiration_days' => 0,
            'expired_for_seconds' => 0,
            'account_data' => false,
        ];
    }

    public static function default_user_db_details_hook_args()
    {
        return self::hook_args_definition( [
            'force_check' => false,
            'user_db_data' => false,
            'session_db_data' => false,
            // How many seconds since session expired (0 - session didn't expired)
            'session_expired_secs' => 0,
            // Details about password expiration
            'password_expired_data' => self::default_password_expiration_data(),
       ] );
    }

    // Used to get account structure (including roles) Account data can be empty or an empty structure (a guest empty structure)
    public static function default_account_structure_hook_args()
    {
        return self::hook_args_definition( [
            // Account id or array to be transformed into account structure (input)
            'account_data' => false,
            // Account structure (from database or empty strcuture for guests)
            'account_structure' => false,
        ] );
    }

    // Used to make extra actions on an account (including roles) Account data can be empty or an empty structure (a guest empty structure)
    public static function default_account_action_hook_args()
    {
        return self::hook_args_definition( [
            // Tells if current hook call is in a background script
            'in_background' => false,
            // Account id on which action was taken
            // We provide id as account was changed and you should normally
            'account_data' => false,
            // string which represents what action was performed on provided account
            'action_alias' => false,
            // Parameters used for action (if any)
            'action_params' => false,
            // current route for which action was taken
            'route' => false,
       ] );
    }
    //
    //endregion User account hooks
    //

    //
    //region Page buffer hooks
    //
    public static function default_buffer_hook_args()
    {
        return self::hook_args_definition( [
           // in case we are triggering this in a view which matters for requested buffer
           'view_obj' => false,
           'concatenate_buffer' => 'buffer',
           'buffer_data' => [],
           'buffer' => '',
       ] );
    }

    public static function reset_buffer_hook_args( $hook_args )
    {
        $hook_args = self::validate_array( $hook_args, self::default_buffer_hook_args() );
        $hook_args['buffer'] = '';

        return $hook_args;
    }
    //
    //endregion Page buffer hooks
    //

    //
    //region Notifications hooks
    //
    public static function default_notifications_hook_args()
    {
        return self::hook_args_definition( [
           'warnings' => [],
           'errors' => [],
           'success' => [],
           'template' => [
               'file' => '',
               'extra_paths' => [],
           ], // default template
           'display_channels' => [ 'warnings', 'errors', 'success' ],
           'output_ajax_placeholders' => true,
           'ajax_placeholders_prefix' => false,
           'notifications_buffer' => '',
        ] );
    }
    //
    //endregion Notifications hooks
    //

    //
    //region Emailing hooks
    //
    public static function default_init_email_hook_args()
    {
        return self::hook_args_definition( [
            'template' => [
               'file' => '',
               'extra_paths' => [],
            ], // default template

            // In case we don't use a template and we just pass a string as email body
            'body_buffer' => false,

            'route' => false,
            'route_settings' => false,

            'to' => '',
            'to_name' => '',
            'from_name' => '',
            'from_email' => '',
            'from_noreply' => '',
            'reply_name' => '',
            'reply_email' => '',
            'subject' => '',

            'attach_files' => [],

            'also_send' => true,
            'send_as_noreply' => true,
            'with_priority' => false,
            'native_mail_function' => false,
            'custom_headers' => [],
            'email_vars' => [],
            'internal_vars' => [],

            'email_html_body' => false,
            'email_text_body' => false,
            'full_body' => false,

            'send_result' => false,
       ] );
    }

    public static function reset_email_hook_args( $hook_args )
    {
        if( empty( $hook_args ) || !is_array( $hook_args ) )
            return self::default_init_email_hook_args();

        // in case hook arguments are cascaded...
        $hook_args['send_result'] = false;

        return self::reset_common_hook_args( $hook_args );
    }
    //
    //endregion Emailing hooks
    //

    //
    //region Captcha hooks
    //
    public static function default_captcha_display_hook_args()
    {
        return self::hook_args_definition( [
            'template' => [
               'file' => '',
               'extra_paths' => [],
            ], // default template
            'font' => 'default.ttf',
            'characters_count' => 5,
            'default_width' => 200,
            'default_height' => 50,
            'extra_img_style' => '',
            'extra_img_attrs' => '',
            'captcha_buffer' => '',
       ] );
    }

    public static function default_captcha_check_hook_args()
    {
        return self::hook_args_definition( [
            'check_code' => '',
            'check_valid' => false,
       ] );
    }

    public static function default_captcha_regeneration_hook_args()
    {
        return self::hook_args_definition( [] );
    }
    //
    //endregion Captcha hooks
    //

    /**
     * Trigger an email action
     *
     * @param array $hook_args
     *
     * @return bool|null|array
     */
    public static function trigger_email( $hook_args )
    {
        self::st_reset_error();

        $hook_args = self::reset_email_hook_args( self::validate_array( $hook_args, self::default_init_email_hook_args() ) );

        // If we don't have hooks registered, we don't send emails...
        if( ($hook_args = PHS::trigger_hooks( self::H_EMAIL_INIT, $hook_args )) === null )
            return null;

        if( is_array( $hook_args )
         && !empty( $hook_args['hook_errors'] ) && is_array( $hook_args['hook_errors'] )
         && self::arr_has_error( $hook_args['hook_errors'] ) )
        {
            self::st_copy_error_from_array( $hook_args['hook_errors'] );
            return false;
        }

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public static function trigger_guest_roles( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, self::default_guest_roles_hook_args() );

        // If we don't have hooks registered, guest users don't have role slugs...
        if( ($hook_args = PHS::trigger_hooks( self::H_GUEST_ROLES_SLUGS, $hook_args )) === null )
            return false;

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public static function trigger_current_user( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, self::default_user_db_details_hook_args() );

        // If we don't have hooks registered, we don't have user management...
        if( ($hook_args = PHS::trigger_hooks( self::H_USER_DB_DETAILS, $hook_args )) === null )
            return false;

        if( is_array( $hook_args ) )
        {
            if( !empty( $hook_args['session_expired_secs'] ) )
            {
                if( !@headers_sent()
                and PHS_Scope::current_scope() === PHS_Scope::SCOPE_WEB )
                {
                    header( 'Location: '.PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), array( 'expired_secs' => $hook_args['session_expired_secs'] ) ) );
                    exit;
                }

                return false;
            }
        }

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public static function trigger_account_action( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, self::default_account_action_hook_args() );

        if( ($hook_args = PHS::trigger_hooks( self::H_USER_ACCOUNT_ACTION, $hook_args )) === null )
            return false;

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public static function trigger_account_structure( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, self::default_account_structure_hook_args() );

        if( ($hook_args = PHS::trigger_hooks( self::H_USER_ACCOUNT_STRUCTURE, $hook_args )) === null )
            return false;

        return $hook_args;
    }

    /**
     * @param array $hook_args
     *
     * @return string
     */
    public static function trigger_captcha_display( $hook_args )
    {
        $hook_args = self::validate_array( $hook_args, self::default_captcha_display_hook_args() );

        // If we don't have hooks registered, we don't use captcha
        if( ($hook_args = PHS::trigger_hooks( self::H_CAPTCHA_DISPLAY, $hook_args )) === null )
            return '';

        if( is_array( $hook_args )
        and !empty( $hook_args['captcha_buffer'] ) )
            return $hook_args['captcha_buffer'];

        if( !empty( $hook_args['hook_errors'] ) )
            return 'Error: ['.PHS_Error::arr_get_error_code( $hook_args['hook_errors'] ).'] '.PHS_Error::arr_get_error_message( $hook_args['hook_errors'] );

        return '';
    }

    /**
     * @param string $code
     *
     * @return array|null
     */
    public static function trigger_captcha_check( $code )
    {
        $hook_args = self::validate_array( array( 'check_code' => $code ), self::default_captcha_check_hook_args() );

        return PHS::trigger_hooks( self::H_CAPTCHA_CHECK, $hook_args );
    }

    /**
     * @return array|null
     */
    public static function trigger_captcha_regeneration()
    {
        $hook_args = self::validate_array( array(), self::default_captcha_regeneration_hook_args() );

        return PHS::trigger_hooks( self::H_CAPTCHA_REGENERATE, $hook_args );
    }
}
