<?php

namespace phs\system\core\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;

class PHS_Action_Index extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_WEB ];
    }

    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Welcome' ) );

        $template = 'index';
        $template_data = array();

        $hook_args = PHS_Hooks::default_page_location_hook_args();
        $hook_args['page_template'] = $template;
        $hook_args['page_template_args'] = $template_data;

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_PAGE_INDEX, $hook_args ))
         && is_array( $new_hook_args ) )
        {
            if( !empty( $new_hook_args['action_result'] ) && is_array( $new_hook_args['action_result'] ) )
                return self::validate_array( $new_hook_args['action_result'], PHS_Action::default_action_result() );

            if( !empty( $new_hook_args['new_page_template'] ) )
                $template = $new_hook_args['new_page_template'];
            if( isset( $new_hook_args['new_page_template_args'] ) && $new_hook_args['new_page_template_args'] !== false )
                $template_data = $new_hook_args['new_page_template_args'];
        }


        return $this->quick_render_template( $template, $template_data );
    }
}
