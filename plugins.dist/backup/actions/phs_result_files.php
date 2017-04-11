<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_line_params;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Result_files extends PHS_Action
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
        PHS::page_settings( 'page_title', $this->_pt( 'Backup Result Files' ) );

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
        if( !($backup_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load packages plugin.' ) );
            return self::default_action_result();
        }

        if( !PHS_Roles::user_has_role_units( $current_user, $backup_plugin::ROLEU_LIST_BACKUPS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to list backup result files.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Results $results_model */
        if( !($results_model = PHS::load_model( 'results', 'backup' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load backup results model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load backup rules model.' ) );
            return self::default_action_result();
        }

        $result_id = PHS_params::_gp( 'result_id', PHS_params::T_INT );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

        if( empty( $result_id )
         or !($result_arr = $results_model->get_details( $result_id ))
         or empty( $result_arr['rule_id'] )
         or !($rule_arr = $rules_model->get_details( $result_arr['rule_id'] )) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid backup result...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'unknown_backup_result' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'backup', 'a' => 'backups_list' ) );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( !($result_files_arr = $results_model->get_result_files( $result_arr['id'] )) )
            $result_files_arr = array();

        $data = array(
            'back_page' => $back_page,
            'result_files_arr' => $result_files_arr,

            'result_id' => $result_id,
            'result_data' => $result_arr,
            'rule_data' => $rule_arr,

            'backup_plugin' => $backup_plugin,
            'results_model' => $results_model,
            'rules_model' => $rules_model,
            'accounts_model' => $accounts_model,
        );

        return $this->quick_render_template( 'result_files', $data );
    }
}
