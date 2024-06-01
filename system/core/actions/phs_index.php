<?php

namespace phs\system\core\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\system\core\events\layout\PHS_Event_Template;

class PHS_Action_Index extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Welcome'));

        $template = 'index';
        $template_data = [];

        if (($event_result = PHS_Event_Template::template(PHS_Event_Template::INDEX, $template, $template_data))) {
            if (!empty($event_result['action_result']) && is_array($event_result['action_result'])) {
                return $event_result['action_result'];
            }

            if (!empty($event_result['page_template'])) {
                $template = $event_result['page_template'];
            }
            if (!empty($event_result['page_template_args'])) {
                $template_data = $event_result['page_template_args'];
            }
        }

        return $this->quick_render_template($template, $template_data);
    }
}
