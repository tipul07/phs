<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
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
        return [ PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX ];
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

            $action_result['request_login'] = true;

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

        /** @var \phs\system\core\libraries\PHS_Ftp $ftp_obj */
        if( !($ftp_obj = PHS::get_core_library_instance( 'ftp' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load FTP core library.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\backup\models\PHS_Model_Rules $rules_model */
        if( !($rules_model = PHS::load_model( 'rules', 'backup' ))
         || !($r_flow_params = $rules_model->fetch_default_flow_params( [ 'table_name' => 'backup_rules' ] )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load backup rules model.' ) );
            return self::default_action_result();
        }

        $rid = PHS_Params::_gp( 'rid', PHS_Params::T_INT );
        $back_page = PHS_Params::_gp( 'back_page', PHS_Params::T_ASIS );

        if( empty( $rid )
         || !($rule_arr = $rules_model->get_details( $rid, $r_flow_params )) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid backup rule...' ) );

            $action_result = self::default_action_result();

            $args = [ 'unknown_rule' => 1 ];

            if( empty( $back_page ) )
                $back_page = PHS::url( [ 'p' => 'backup', 'a' => 'rules_list' ] );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( ($new_rule = $rules_model->get_rule_ftp_settings( $rule_arr )) )
            $rule_arr = $new_rule;

        $days_options_arr = [
            7 => $this->_pt( 'One week' ),
            14 => $this->_pt( 'Two weeks' ),
            30 => $this->_pt( '30 days' ),
            60 => $this->_pt( '60 days' ),
        ];

        if( PHS_Params::_g( 'changes_saved', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Backup rule details saved.' ) );
        if( PHS_Params::_g( 'ftp_connection_success', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Connected to FTP server with success.' ) );
        if( PHS_Params::_g( 'ftp_connection_failed', PHS_Params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Failed connecting to FTP server.' ) );

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $title = PHS_Params::_p( 'title', PHS_Params::T_NOHTML );
        $hour = PHS_Params::_p( 'hour', PHS_Params::T_INT );
        $delete_after_days = PHS_Params::_p( 'delete_after_days', PHS_Params::T_INT );
        $cdelete_after_days = PHS_Params::_p( 'cdelete_after_days', PHS_Params::T_INT );
        $copy_results = PHS_Params::_p( 'copy_results', PHS_Params::T_INT );
        if( !($ftp_settings = PHS_Params::_p( 'ftp_settings', PHS_Params::T_ARRAY, [ 'type' => PHS_Params::T_ASIS ] )) )
            $ftp_settings = [];
        if( !($target_arr = PHS_Params::_p( 'target_arr', PHS_Params::T_ARRAY, [ 'type' => PHS_Params::T_INT ] )) )
            $target_arr = [];
        if( !($days_arr = PHS_Params::_p( 'days_arr', PHS_Params::T_ARRAY, [ 'type' => PHS_Params::T_INT ] )) )
            $days_arr = [];
        if( !($location = PHS_Params::_p( 'location', PHS_Params::T_NOHTML )) )
            $location = '';

        $do_submit = PHS_Params::_p( 'do_submit' );
        $do_test_ftp = PHS_Params::_pg( 'do_test_ftp' );

        if( !empty( $do_test_ftp ) )
            $do_submit = true;

        if( empty( $foobar ) )
        {
            $title = $rule_arr['title'];
            $hour = (int)$rule_arr['hour'];
            $delete_after_days = (int)$rule_arr['delete_after_days'];
            $copy_results = (int)$rule_arr['copy_results'];

            if( !empty( $rule_arr['{ftp_settings}'] ) )
                $ftp_settings = $rule_arr['{ftp_settings}'];
            else
                $ftp_settings = [];

            $location = $rule_arr['location'];
            if( !($days_arr = $rules_model->get_rule_days_as_array( $rule_arr['id'] )) )
                $days_arr = [];
            if( !($target_arr = $rules_model->bits_to_targets_arr( $rule_arr['target'] )) )
                $target_arr = [];

            $cdelete_after_days = $delete_after_days;
            if( $cdelete_after_days <= 0 )
                $cdelete_after_days = 1;

            if( $delete_after_days !== 0
             && empty( $days_options_arr[$delete_after_days] ) )
                $delete_after_days = -2;
        }

        if( !empty( $do_submit ) )
        {
            if( $delete_after_days === -1 )
                PHS_Notifications::add_error_notice( $this->_pt( 'Please choose an option for delete action.' ) );

            elseif( $delete_after_days === 0 )
                $cdelete_after_days = 0;

            elseif( $delete_after_days === -2 )
            {
                if( empty( $cdelete_after_days ) || $cdelete_after_days < 0 )
                    $cdelete_after_days = 0;
            } else
                $cdelete_after_days = $delete_after_days;
        }

        if( !($plugin_settings = $backup_plugin->get_db_settings())
         || empty( $plugin_settings['location'] ) )
            $plocation = '';
        else
            $plocation = $plugin_settings['location'];

        if( !($rule_days = $rules_model->get_rule_days()) )
            $rule_days = [];
        if( !($targets_arr = $rules_model->get_targets_as_key_val()) )
            $targets_arr = [];
        if( !($rule_location = $backup_plugin->get_location_for_path( $location )) )
            $rule_location = '';
        if( !($plugin_location = $backup_plugin->get_location_for_path( $plocation )) )
            $plugin_location = '';

        if( !($copy_results_arr = $rules_model->get_copy_results_as_key_val()) )
            $copy_results_arr = [];
        if( !($ftp_connection_modes_arr = $ftp_obj->get_connection_types_as_key_val()) )
            $ftp_connection_modes_arr = [];

        if( !empty( $do_submit )
         && !PHS_Notifications::have_errors_or_warnings_notifications() )
        {
            $rule_details_saved = false;
            $ftp_connection_success = null;

            if( !empty( $copy_results )
             && $copy_results === $rules_model::COPY_FTP )
            {
                if( empty( $ftp_settings ) || !is_array( $ftp_settings )
                 || empty( $ftp_settings['connection_mode'] )
                 || !$ftp_obj->valid_connection_type( $ftp_settings['connection_mode'] )
                 || !$ftp_obj::settings_valid( $ftp_settings ) )
                    PHS_Notifications::add_error_notice( $this->_pt( 'Please choose an option for delete action.' ) );

                else
                {
                    $ftp_settings['connection_mode'] = (int)$ftp_settings['connection_mode'];

                    // We hardcode binary transfers...
                    $ftp_settings['transfer_mode'] = $ftp_obj::TRANSFER_MODE_BINARY;

                    if( !empty( $ftp_settings['timeout'] ) )
                        $ftp_settings['timeout'] = (int)$ftp_settings['timeout'];
                    else
                        $ftp_settings['timeout'] = 0;

                    if( !empty( $ftp_settings['passive_mode'] ) )
                        $ftp_settings['passive_mode'] = true;
                    else
                        $ftp_settings['passive_mode'] = false;
                }
            }

            if( !PHS_Notifications::have_errors_or_warnings_notifications() )
            {
                $edit_arr = [];
                $edit_arr['title'] = $title;
                $edit_arr['location'] = $location;
                $edit_arr['hour'] = $hour;
                $edit_arr['delete_after_days'] = $cdelete_after_days;
                $edit_arr['copy_results'] = $copy_results;
                $edit_arr['ftp_settings'] = $ftp_settings;
                $edit_arr['target'] = $target_arr;

                $edit_params_arr = $r_flow_params;
                $edit_params_arr['fields'] = $edit_arr;
                $edit_params_arr['{days_arr}'] = $days_arr;

                if( ($new_role = $rules_model->edit( $rule_arr, $edit_params_arr )) )
                {
                    $rule_details_saved = true;
                } elseif( $rules_model->has_error() )
                {
                    PHS_Notifications::add_error_notice( $rules_model->get_error_message() );
                } else
                {
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
                }
            }

            // we saved all data and we try now to connect...
            if( !empty( $copy_results )
             && $copy_results === $rules_model::COPY_FTP
             && !empty( $do_test_ftp )
             && !PHS_Notifications::have_errors_or_warnings_notifications() )
            {
                $this->reset_error();

                if( !$ftp_obj->settings( $ftp_settings ) )
                {
                    if( $ftp_obj->has_error() )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Error sending FTP settings: %s.', $ftp_obj->get_error_message() ) );
                    else
                        PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t setup a FTP instance with provided settings.' ) );
                } else
                {
                    if( $ftp_obj->ls() !== false )
                        $ftp_connection_success = true;

                    else
                    {
                        $ftp_connection_success = false;
                        if( $ftp_obj->has_error() )
                            PHS_Notifications::add_error_notice( $this->_pt( 'Error connecting to FTP server: %s', $ftp_obj->get_error_message() ) );
                        else
                            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t connect to FTP server.' ) );
                    }

                    $ftp_obj->close();
                }
            }

            if( $rule_details_saved )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Backup rule details saved.' ) );

                if( !PHS_Notifications::have_errors_or_warnings_notifications() )
                {
                    $action_result = self::default_action_result();

                    $url_args = [
                        'rid' => $rid,
                        'changes_saved' => 1,
                    ];

                    if( $ftp_connection_success !== null )
                    {
                        if( !empty( $ftp_connection_success ) )
                            $url_args['ftp_connection_success'] = 1;
                        else
                            $url_args['ftp_connection_failed'] = 1;
                    }

                    $action_result['redirect_to_url'] = PHS::url( [ 'p' => 'backup', 'a' => 'rule_edit' ], $url_args );

                    return $action_result;
                }
            }
        }

        $data = [
            'rid' => $rule_arr['id'],
            'back_page' => $back_page,

            'title' => $title,
            'hour' => $hour,
            'delete_after_days' => $delete_after_days,
            'copy_results' => $copy_results,
            'ftp_settings' => $ftp_settings,
            'target_arr' => $target_arr,
            'days_arr' => $days_arr,
            'location' => $location,

            'copy_results_arr' => $copy_results_arr,
            'ftp_connection_modes_arr' => $ftp_connection_modes_arr,
            'days_options_arr' => $days_options_arr,
            'cdelete_after_days' => $cdelete_after_days,
            'rule_days' => $rule_days,
            'targets_arr' => $targets_arr,
            'rule_location' => $rule_location,
            'plugin_location' => $plugin_location,
            'rules_model' => $rules_model,
            'backup_plugin' => $backup_plugin,
        ];

        return $this->quick_render_template( 'rule_edit', $data );
    }
}
