<?php
namespace phs\system\core\events\layout;

use Closure;
use phs\libraries\PHS_Hooks;
use phs\system\core\views\PHS_View;
use phs\system\core\events\accounts\PHS_Event_Accounts_info_template;

class PHS_Event_Layout extends PHS_Event_Layout_buffer
{
    public const
        ADMIN_TEMPLATE_PAGE_HEAD = 'phs_admin_template_page_head',
        ADMIN_TEMPLATE_PAGE_START = 'phs_admin_template_page_start',
        ADMIN_TEMPLATE_PAGE_END = 'phs_admin_template_page_end',
        ADMIN_TEMPLATE_PAGE_FIRST_CONTENT = 'phs_admin_template_page_first_content',

        ADMIN_TEMPLATE_BEFORE_LEFT_MENU = 'phs_admin_template_before_left_menu',
        ADMIN_TEMPLATE_AFTER_LEFT_MENU = 'phs_admin_template_after_left_menu',
        ADMIN_TEMPLATE_BEFORE_RIGHT_MENU = 'phs_admin_template_before_right_menu',
        ADMIN_TEMPLATE_AFTER_RIGHT_MENU = 'phs_admin_template_after_right_menu',

        ADMIN_TEMPLATE_BEFORE_MAIN_MENU = 'phs_admin_template_before_main_menu',
        ADMIN_TEMPLATE_AFTER_MAIN_MENU = 'phs_admin_template_after_main_menu',

        ADMIN_TEMPLATE_BEFORE_FOOTER_LINKS = 'phs_admin_template_before_footer_links',
        ADMIN_TEMPLATE_AFTER_FOOTER_LINKS = 'phs_admin_template_after_footer_links',

        MAIN_TEMPLATE_PAGE_HEAD = 'phs_main_template_page_head',
        MAIN_TEMPLATE_PAGE_START = 'phs_main_template_page_start',
        MAIN_TEMPLATE_PAGE_END = 'phs_main_template_page_end',
        MAIN_TEMPLATE_PAGE_FIRST_CONTENT = 'phs_main_template_page_first_content',

        MAIN_TEMPLATE_BEFORE_LEFT_MENU = 'phs_main_template_before_left_menu',
        MAIN_TEMPLATE_AFTER_LEFT_MENU = 'phs_main_template_after_left_menu',
        MAIN_TEMPLATE_BEFORE_RIGHT_MENU = 'phs_main_template_before_right_menu',
        MAIN_TEMPLATE_AFTER_RIGHT_MENU = 'phs_main_template_after_right_menu',

        MAIN_TEMPLATE_BEFORE_MAIN_MENU = 'phs_main_template_before_main_menu',
        MAIN_TEMPLATE_AFTER_MAIN_MENU = 'phs_main_template_after_main_menu',
        MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_IN = 'phs_main_template_before_main_menu_logged_in',
        MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_IN = 'phs_main_template_after_main_menu_logged_in',
        MAIN_TEMPLATE_BEFORE_MAIN_MENU_LOGGED_OUT = 'phs_main_template_before_main_menu_logged_out',
        MAIN_TEMPLATE_AFTER_MAIN_MENU_LOGGED_OUT = 'phs_main_template_after_main_menu_logged_out',

        MAIN_TEMPLATE_BEFORE_FOOTER_LINKS = 'phs_main_template_before_footer_links',
        MAIN_TEMPLATE_AFTER_FOOTER_LINKS = 'phs_main_template_after_footer_links';

    public const OLD_HOOKS = [
        self::ADMIN_TEMPLATE_PAGE_HEAD          => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_HEAD],
        self::ADMIN_TEMPLATE_PAGE_START         => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_START],
        self::ADMIN_TEMPLATE_PAGE_END           => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_END],
        self::ADMIN_TEMPLATE_PAGE_FIRST_CONTENT => [PHS_Hooks::H_ADMIN_TEMPLATE_PAGE_FIRST_CONTENT],

        self::ADMIN_TEMPLATE_BEFORE_LEFT_MENU  => [PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_LEFT_MENU],
        self::ADMIN_TEMPLATE_AFTER_LEFT_MENU   => [PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_LEFT_MENU],
        self::ADMIN_TEMPLATE_BEFORE_RIGHT_MENU => [PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_RIGHT_MENU],
        self::ADMIN_TEMPLATE_AFTER_RIGHT_MENU  => [PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_RIGHT_MENU],

        self::ADMIN_TEMPLATE_BEFORE_MAIN_MENU => [PHS_Hooks::H_ADMIN_TEMPLATE_BEFORE_MAIN_MENU],
        self::ADMIN_TEMPLATE_AFTER_MAIN_MENU  => [PHS_Hooks::H_ADMIN_TEMPLATE_AFTER_MAIN_MENU],

        self::MAIN_TEMPLATE_PAGE_HEAD          => [PHS_Hooks::H_MAIN_TEMPLATE_PAGE_HEAD],
        self::MAIN_TEMPLATE_PAGE_START         => [PHS_Hooks::H_MAIN_TEMPLATE_PAGE_START],
        self::MAIN_TEMPLATE_PAGE_END           => [PHS_Hooks::H_MAIN_TEMPLATE_PAGE_END],
        self::MAIN_TEMPLATE_PAGE_FIRST_CONTENT => [PHS_Hooks::H_MAIN_TEMPLATE_PAGE_FIRST_CONTENT],

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

    public static function get_buffer(string $area = '', array $input_arr = [], array $params = []) : string
    {
        if (!empty(static::OLD_HOOKS[$area])) {
            $params['old_hooks'] = static::OLD_HOOKS[$area];
        }

        if (!($event_obj = static::trigger($input_arr, $area, $params))
            || !($buffer = $event_obj->get_output('buffer'))) {
            return '';
        }

        return $buffer;
    }

    // !! This should be use only with a child class
    public static function listen_for_buffer(callable | array | string | Closure $callback, array $options = []) : ?self
    {
        if (static::class === self::class) {
            self::st_set_error(self::ERR_LISTEN, self::_t('Cannot listen for buffer in base class %s.', self::class));

            return null;
        }

        return static::listen($callback, static::class, $options);
    }

    // !! This should be use only with a child class
    public static function buffer(PHS_View $view_obj, array $template_data = [], array $params = []) : string
    {
        if (static::class === self::class) {
            return self::_t('Cannot ask for buffer in base class %s.', self::class);
        }

        return static::get_buffer(
            static::class,
            [
                'view_obj'    => $view_obj,
                'buffer_data' => $template_data,
            ],
            $params
        );
    }
}
