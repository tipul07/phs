<?php

namespace phs\plugins\admin\controllers;

use \phs\PHS;
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
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_RUN_ACTION, $this->_pt( 'Error loading accounts model.' ) );
            return false;
        }

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

        if( !$accounts_model->acc_is_admin( $current_user ) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You don\'t have enough rights to access this section...' ) );

            return $this->execute_foobar_action();
        }

        if( !($action_result = parent::execute_action( $action, $plugin )) )
            return false;

        $action_result['page_template'] = 'template_admin';

        return $action_result;
    }
}
