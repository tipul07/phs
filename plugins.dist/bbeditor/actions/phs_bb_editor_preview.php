<?php
namespace phs\plugins\bbeditor\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\bbeditor\libraries\Bbcode;

class PHS_Action_Bb_editor_preview extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX, PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        // ! TODO: Add hook to check rights on document preview

        $action_result = self::default_action_result();

        /** @var \phs\plugins\bbeditor\PHS_Plugin_Bbeditor $bbeditor_plugin */
        if (!($bbeditor_plugin = $this->get_plugin_instance())) {
            $action_result['buffer'] = $this->_pt('Couldn\'t load bbeditor plugin.');

            return $action_result;
        }

        if (!($bbcode_obj = Bbcode::get_instance())) {
            $action_result['buffer'] = $this->_pt('Couldn\'t load BB code library.');

            return $action_result;
        }

        $body = PHS_Params::_p('body', PHS_Params::T_ASIS);

        $data = [
            'bb_text'     => $bbcode_obj->bb_to_html($body),
            'bb_code_obj' => $bbcode_obj,
        ];

        return $bbeditor_plugin->quick_render_template_for_buffer('bb_editor_preview', $data);
    }
}
