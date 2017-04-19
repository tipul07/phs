<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Rule_edit extends PHS_Action
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
        PHS::page_settings( 'page_title', $this->_pt( 'Edit Rule' ) );

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
        if( !($rules_model = PHS::load_model( 'rules', 'backup' ))
         or !($r_flow_params = $rules_model->fetch_default_flow_params( array( 'table_name' => 'backup_rules' ) )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load backup rules model.' ) );
            return self::default_action_result();
        }

        $rid = PHS_params::_gp( 'rid', PHS_params::T_INT );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

        if( empty( $rid )
         or !($rule_arr = $rules_model->get_details( $rid, $r_flow_params )) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid backup rule...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'unknown_rule' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'backup', 'a' => 'rules_list' ) );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        $days_options_arr = array(
            7 => $this->_pt( 'One week' ),
            14 => $this->_pt( 'Two weeks' ),
            30 => $this->_pt( '30 days' ),
            60 => $this->_pt( '60 days' ),
        );

        if( PHS_params::_g( 'changes_saved', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Backup rule details saved.' ) );

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $title = PHS_params::_p( 'title', PHS_params::T_NOHTML );
        $hour = PHS_params::_p( 'hour', PHS_params::T_INT );
        $delete_after_days = PHS_params::_p( 'delete_after_days', PHS_params::T_INT );
        $cdelete_after_days = PHS_params::_p( 'cdelete_after_days', PHS_params::T_INT );
        if( !($target_arr = PHS_params::_p( 'target_arr', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_INT ) )) )
            $target_arr = array();
        if( !($days_arr = PHS_params::_p( 'days_arr', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_INT ) )) )
            $days_arr = array();
        if( !($location = PHS_params::_p( 'location', PHS_params::T_NOHTML )) )
            $location = '';

        $do_submit = PHS_params::_p( 'do_submit' );

        if( !empty( $do_submit ) )
        {
            if( $delete_after_days == -1 )
                PHS_Notifications::add_error_notice( $this->_pt( 'Please choose an option for delete action.' ) );

            elseif( $delete_after_days === 0 )
                $cdelete_after_days = 0;

            elseif( $delete_after_days == -2 )
            {
                if( empty( $cdelete_after_days ) or $cdelete_after_days < 0 )
                    $cdelete_after_days = 0;
            } else
                $cdelete_after_days = $delete_after_days;
        }

        if( empty( $foobar ) )
        {
            $title = $rule_arr['title'];
            $hour = $rule_arr['hour'];
            $delete_after_days = $rule_arr['delete_after_days'];
            $location = $rule_arr['location'];
            if( !($days_arr = $rules_model->get_rule_days_as_array( $rule_arr['id'] )) )
                $days_arr = array();
            if( !($target_arr = $rules_model->bits_to_targets_arr( $rule_arr['target'] )) )
                $target_arr = array();

            $delete_after_days = intval( $delete_after_days );

            $cdelete_after_days = $delete_after_days;
            if( $cdelete_after_days <= 0 )
                $cdelete_after_days = 1;

            if( $delete_after_days !== 0
            and empty( $days_options_arr[$delete_after_days] ) )
                $delete_after_days = -2;
        }

        if( !($plugin_settings = $backup_plugin->get_db_settings())
         or empty( $plugin_settings['location'] ) )
            $plocation = '';
        else
            $plocation = $plugin_settings['location'];

        if( !($rule_days = $rules_model->get_rule_days()) )
            $rule_days = array();
        if( !($targets_arr = $rules_model->get_targets_as_key_val()) )
            $targets_arr = array();
        if( !($rule_location = $backup_plugin->get_location_for_path( $location )) )
            $rule_location = '';
        if( !($plugin_location = $backup_plugin->get_location_for_path( $plocation )) )
            $plugin_location = '';

        if( !empty( $do_submit )
        and !PHS_Notifications::have_any_notifications() )
        {
            $edit_arr = array();
            $edit_arr['title'] = $title;
            $edit_arr['location'] = $location;
            $edit_arr['hour'] = $hour;
            $edit_arr['delete_after_days'] = $cdelete_after_days;
            $edit_arr['target'] = $target_arr;

            $edit_params_arr = $r_flow_params;
            $edit_params_arr['fields'] = $edit_arr;
            $edit_params_arr['{days_arr}'] = $days_arr;

            if( ($new_role = $rules_model->edit( $rule_arr, $edit_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Backup rule details saved...' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'backup', 'a' => 'rule_edit' ), array( 'rid' => $rid, 'changes_saved' => 1 ) );

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
            'rid' => $rule_arr['id'],
            'back_page' => $back_page,

            'title' => $title,
            'hour' => $hour,
            'delete_after_days' => $delete_after_days,
            'target_arr' => $target_arr,
            'days_arr' => $days_arr,
            'location' => $location,

            'days_options_arr' => $days_options_arr,
            'cdelete_after_days' => $cdelete_after_days,
            'rule_days' => $rule_days,
            'targets_arr' => $targets_arr,
            'rule_location' => $rule_location,
            'plugin_location' => $plugin_location,
            'backup_plugin' => $backup_plugin,
        );

        return $this->quick_render_template( 'rule_edit', $data );
    }
}
