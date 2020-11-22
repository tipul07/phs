<?php

namespace phs\plugins\bbeditor\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Bb_editor_preview extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AJAX, PHS_Scope::SCOPE_WEB );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        $action_result = self::default_action_result();

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result['request_login'] = true;

            return $action_result;
        }

        //! TODO: Add hook to check rights on document preview

        /** @var \phs\plugins\bbeditor\PHS_Plugin_Bbeditor $bbeditor_plugin */
        if( !($bbeditor_plugin = $this->get_plugin_instance()) )
        {
            $action_result['buffer'] = $this->_pt( 'Couldn\'t load bbeditor plugin.' );
            return $action_result;
        }

        if( !($bbcode_obj = $bbeditor_plugin->get_bbcode_instance()) )
        {
            $action_result['buffer'] = $this->_pt( 'Couldn\'t load BB code library.' );
            return $action_result;
        }

        $body = PHS_Params::_p( 'body', PHS_Params::T_ASIS );

        $data = array(
            'bb_text' => $bbcode_obj->bb_to_html( $body ),
            'bb_code_obj' => $bbcode_obj,
        );

        return $bbeditor_plugin->quick_render_template_for_buffer( 'bb_editor_preview', $data );
    }
}
