<?php
namespace phs\system\core\events\layout;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;

class PHS_Event_Template extends PHS_Event_Template_details
{
    public const REGISTER = 'phs_template_register', INDEX = 'phs_template_index',
        GENERIC = 'phs_template_generic';

    protected const OLD_HOOKS = [
        self::REGISTER => [PHS_Hooks::H_PAGE_REGISTER],
        self::INDEX    => [PHS_Hooks::H_PAGE_INDEX],
        self::GENERIC  => [PHS_Hooks::H_WEB_TEMPLATE_RENDERING],
    ];

    /**
     * @param string $template
     * @param string $default_template
     * @param null|array $template_args
     * @param null|array $action_result
     *
     * @return null|array{"action_result": ?array, "page_template": string, "page_template_args": ?array}
     */
    public static function template(string $template, string $default_template, ?array $template_args = null, ?array $action_result = null) : ?array
    {
        $event_params = [];
        if (!empty(self::OLD_HOOKS[$template])) {
            $event_params['old_hooks'] = self::OLD_HOOKS[$template];
        }

        $event_input = [
            'action_result'      => $action_result,
            'page_template'      => $default_template,
            'page_template_args' => $template_args,
        ];

        if (!($event_obj = self::trigger($event_input, $template, $event_params))) {
            return null;
        }

        if (($action_result = $event_obj->get_output('action_result'))) {
            $event_obj->set_output('action_result', PHS_Action::validate_action_result($action_result));
        }

        if (($new_template = $event_obj->get_output('page_template'))
           && !is_string($new_template)) {
            $event_obj->set_output('page_template', $default_template);
        }

        if (($new_template_args = $event_obj->get_output('page_template_args'))
           && !is_array($new_template_args)) {
            $event_obj->set_output('page_template_args', $template_args);
        }

        return $event_obj->get_output();
    }
}
