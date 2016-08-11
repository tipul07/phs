<?php

namespace phs\plugins\messages\controllers;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action;

class PHS_Controller_Index extends PHS_Controller
{
    /**
     * @inheritdoc
     */
    public function execute_action( $action, $plugin = null )
    {
        if( PHS_Scope::current_scope() != PHS_Scope::SCOPE_BACKGROUND )
        {
            if( !($current_user = PHS::user_logged_in()) )
            {
                PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

                $action_result = PHS_Action::default_action_result();

                $args = array(
                    'back_page' => PHS::current_url()
                );

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

                return $this->execute_foobar_action( $action_result );
            }

            /** @var \phs\plugins\s2p_companies\PHS_Plugin_S2p_companies $plugin_obj */
            if( !($plugin_obj = $this->get_plugin_instance()) )
            {
                PHS_Notifications::add_warning_notice( $this->_pt( 'Couldn\'t obtain plugin instance.' ) );

                return $this->execute_foobar_action();
            }

            if( !$plugin_obj->user_has_any_of_defined_role_units() )
            {
                PHS_Notifications::add_warning_notice( $this->_pt( 'You don\'t have rights to access this section.' ) );

                return $this->execute_foobar_action();
            }
        }

        if( !($action_result = parent::execute_action( $action, $plugin )) )
            return false;

        $action_result['page_template'] = 'template_main';

        return $action_result;
    }
}
