<?php
namespace phs\system\core\events\layout;

use phs\libraries\PHS_Hooks;

class PHS_Event_Layout extends PHS_Event_Layout_buffer
{
    public const
        ADMIN_TEMPLATE_PAGE_HEAD = 'phs_admin_template_page_head',
        ADMIN_TEMPLATE_PAGE_START = 'phs_admin_template_page_start',
        ADMIN_TEMPLATE_PAGE_FIRST_CONTENT = 'phs_admin_template_page_first_content',

        ADMIN_TEMPLATE_BEFORE_LEFT_MENU = 'phs_admin_template_before_left_menu',
        ADMIN_TEMPLATE_AFTER_LEFT_MENU = 'phs_admin_template_after_left_menu',
        ADMIN_TEMPLATE_BEFORE_RIGHT_MENU = 'phs_admin_template_before_right_menu',
        ADMIN_TEMPLATE_AFTER_RIGHT_MENU = 'phs_admin_template_after_right_menu',

        ADMIN_TEMPLATE_BEFORE_MAIN_MENU = 'phs_admin_template_before_main_menu',
        ADMIN_TEMPLATE_AFTER_MAIN_MENU = 'phs_admin_template_after_main_menu',

        MAIN_TEMPLATE_BEFORE_LEFT_MENU = 'phs_main_template_before_left_menu',
        MAIN_TEMPLATE_AFTER_LEFT_MENU = 'phs_main_template_after_left_menu',
        MAIN_TEMPLATE_BEFORE_RIGHT_MENU = 'phs_main_template_before_right_menu',
        MAIN_TEMPLATE_AFTER_RIGHT_MENU = 'phs_main_template_after_right_menu',

        MAIN_TEMPLATE_BEFORE_MAIN_MENU = 'phs_main_template_before_main_menu',
        MAIN_TEMPLATE_AFTER_MAIN_MENU = 'phs_main_template_after_main_menu',
        MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN = 'phs_main_template_before_main_menu_logged_in',
        MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN = 'phs_main_template_after_main_menu_logged_in',
        MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT = 'phs_main_template_before_main_menu_logged_out',
        MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT = 'phs_main_template_after_main_menu_logged_out';

    public const OLD_HOOKS = [
        self::ADMIN_TEMPLATE_PAGE_HEAD          => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_HEAD],
        self::ADMIN_TEMPLATE_PAGE_START         => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_START],
        self::ADMIN_TEMPLATE_PAGE_FIRST_CONTENT => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_FIRST_CONTENT],

        self::ADMIN_TEMPLATE_BEFORE_LEFT_MENU  => [PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_LEFT_MENU],
        self::ADMIN_TEMPLATE_AFTER_LEFT_MENU   => [PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU],
        self::ADMIN_TEMPLATE_BEFORE_RIGHT_MENU => [PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_RIGHT_MENU],
        self::ADMIN_TEMPLATE_AFTER_RIGHT_MENU  => [PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_RIGHT_MENU],

        self::ADMIN_TEMPLATE_BEFORE_MAIN_MENU => [PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_MAIN_MENU],
        self::ADMIN_TEMPLATE_AFTER_MAIN_MENU  => [PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_MAIN_MENU],

        self::MAIN_TEMPLATE_BEFORE_LEFT_MENU  => [PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_LEFT_MENU],
        self::MAIN_TEMPLATE_AFTER_LEFT_MENU   => [PHS_Hooks::H_MAIN_TEMPLATE_AFTER_LEFT_MENU],
        self::MAIN_TEMPLATE_BEFORE_RIGHT_MENU => [PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_RIGHT_MENU],
        self::MAIN_TEMPLATE_AFTER_RIGHT_MENU  => [PHS_Hooks::H_MAIN_TEMPLATE_AFTER_RIGHT_MENU],

        self::MAIN_TEMPLATE_BEFORE_MAIN_MENU            => [PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_MAIN_MENU],
        self::MAIN_TEMPLATE_AFTER_MAIN_MENU             => [PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU],
        self::MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN  => [PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN],
        self::MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN   => [PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN],
        self::MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT => [PHS_Hooks::H_MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT],
        self::MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT  => [PHS_Hooks::H_MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT],
    ];

    public static function get_buffer(string $area = '', array $event_input = [], array $event_params = []) : string
    {
        if (!empty(self::OLD_HOOKS[$area])) {
            $event_params['old_hooks'] = self::OLD_HOOKS[$area];
        }

        if (!($event_obj = self::trigger($event_input, $area, $event_params))
         || !($buffer = $event_obj->get_output('buffer'))) {
            return '';
        }

        return $buffer;
    }
}
