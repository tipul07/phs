<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Instantiable;

class PHS_Action_Plugin_settings extends PHS_Action
{
    const ERR_PLUGIN = 1;

    /** @var bool|\phs\libraries\PHS_Plugin $_plugin_obj */
    private $_plugin_obj = false;
    
    public function execute()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( self::_t( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        if( !$accounts_model->can_manage_plugins( $current_user ) )
        {
            PHS_Notifications::add_error_notice( self::_t( 'You don\'t have rights to list plugins.' ) );
            return self::default_action_result();
        }

        $pid = PHS_params::_gp( 'pid', PHS_params::T_ASIS );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

        if( !($instance_details = PHS_Instantiable::valid_instance_id( $pid ))
         or empty( $instance_details['instance_type'] )
         or $instance_details['instance_type'] != PHS_Instantiable::INSTANCE_TYPE_PLUGIN
         or !($this->_plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
        {
            $action_result = self::default_action_result();

            $args = array(
                'unknown_plugin' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'plugins_list' ) );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( !($form_data = $this->extract_form_data()) )
            $form_data = array();

        $data = array(
            'back_page' => $back_page,
            'form_data' => $form_data,
            'plugin_obj' => $this->_plugin_obj,
        );

        return $this->quick_render_template( 'plugin_settings', $data );
    }

    private function extract_form_data()
    {
        if( empty( $this->_plugin_obj ) )
            return false;

        return array();
    }
}
