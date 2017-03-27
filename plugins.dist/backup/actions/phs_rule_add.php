<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Rule_add extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Add Rule' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

            return $action_result;
        }

        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return self::default_action_result();
        }

        if( !PHS_Roles::user_has_role_units( $current_user, $backup_plugin::ROLEU_MANAGE_RULES ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage backup rules.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load backup rules model.' ) );
            return self::default_action_result();
        }


        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $title = PHS_params::_p( 'title', PHS_params::T_NOHTML );
        $hour = PHS_params::_p( 'hour', PHS_params::T_INT );
        if( !($target_arr = PHS_params::_p( 'target_arr', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_INT ) )) )
            $target_arr = array();
        if( !($days_arr = PHS_params::_p( 'days_arr', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_INT ) )) )
            $days_arr = array();
        if( !($location = PHS_params::_p( 'location', PHS_params::T_NOHTML )) )
            $location = '';

        $do_submit = PHS_params::_p( 'do_submit' );

        if( !($rule_days = $rules_model->get_rule_days()) )
            $rule_days = array();
        if( !($targets_arr = $rules_model->get_targets_as_key_val()) )
            $targets_arr = array();
        if( !($rule_location = $backup_plugin->get_location_for_path( $location )) )
            $rule_location = '';

        if( !empty( $do_submit ) )
        {
            $insert_arr = array();
            $insert_arr['uid'] = $current_user['id'];
            $insert_arr['title'] = $title;
            $insert_arr['location'] = $location;
            $insert_arr['hour'] = $hour;
            $insert_arr['target'] = $target_arr;

            $insert_params_arr = $rules_model->fetch_default_flow_params( array( 'table_name' => 'backup_rules' ) );
            $insert_params_arr['fields'] = $insert_arr;
            $insert_params_arr['{days_arr}'] = $days_arr;

            if( ($new_role = $rules_model->insert( $insert_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Backup rule details saved...' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'backup', 'a' => 'rules_list' ), array( 'rule_added' => 1 ) );

                return $action_result;
            } else
            {
                if( $rules_model->has_error() )
                    PHS_Notifications::add_error_notice( $rules_model->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
            }
        }

        $data = array(
            'title' => $title,
            'hour' => $hour,
            'target_arr' => $target_arr,
            'days_arr' => $days_arr,
            'location' => $location,

            'rule_days' => $rule_days,
            'targets_arr' => $targets_arr,
            'rule_location' => $rule_location,
            'backup_plugin' => $backup_plugin,
        );

        return $this->quick_render_template( 'rule_add', $data );
    }
}
