<?php

namespace phs\plugins\admin\actions;

use phs\libraries\PHS_Model;
use \phs\PHS;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;

/** @property \phs\system\core\models\PHS_Model_Plugins $_paginator_model */
class PHS_Action_Modules_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    public function load_depencies()
    {
        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, self::_t( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, self::_t( 'Couldn\'t load plugins model.' ) );
            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
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

        return false;
    }

    // Do any actions required after paginator was instantiated and initialized (eg. columns, filters, model and bulk actions were set)
    public function we_initialized_paginator()
    {
        $this->reset_error();

        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        $records_arr = array();
        if( ($dir_entries = $this->_paginator_model->cache_all_dir_details())
        and is_array( $dir_entries ) )
        {
            $this->_paginator->set_records_count( count( $dir_entries ) );

            $offset = $this->_paginator->pagination_params( 'offset' );
            $records_per_page = $this->_paginator->pagination_params( 'records_per_page' );

            if( !($scope_arr = $this->_paginator->get_scope())
             or !is_array( $scope_arr ) )
                $scope_arr = array();

            /**
             * @var string $plugin_dir
             * @var \phs\libraries\PHS_Plugin $plugin_instance
             */
            $knti = -1;
            $on_this_page = 0;
            foreach( $dir_entries as $plugin_dir => $plugin_instance )
            {
                if( !($plugin_info_arr = $plugin_instance->get_plugin_info()) )
                {
                    PHS_Notifications::add_warning_notice( self::_t( 'Couldn\'t get plugin info for %s.', @basename( $plugin_dir ) ) );
                    continue;
                }

                $record_arr = array();
                $record_arr['id'] = $plugin_info_arr['id'];
                $record_arr['is_installed'] = $plugin_info_arr['is_installed'];
                $record_arr['is_core'] = $plugin_info_arr['is_core'];
                $record_arr['name'] = $plugin_info_arr['name'];
                $record_arr['description'] = $plugin_info_arr['description'];
                $record_arr['version'] = $plugin_info_arr['db_version'].' / '.$plugin_info_arr['script_version'];
                $record_arr['status'] = (!empty( $plugin_info_arr['db_details'] )?$plugin_info_arr['db_details']['status']:-1);
                $record_arr['status_date'] = (!empty( $plugin_info_arr['db_details'] )?$plugin_info_arr['db_details']['status_date']:PHS_Model::DATETIME_EMPTY);
                $record_arr['cdate'] = (!empty( $plugin_info_arr['db_details'] )?$plugin_info_arr['db_details']['cdate']:PHS_Model::DATETIME_EMPTY);
                $record_arr['models'] = ((!empty( $plugin_info_arr['models'] ) and is_array( $plugin_info_arr['models'] ))?$plugin_info_arr['models']:array());

                if( !empty( $scope_arr['fplugin'] )
                and stristr( $record_arr['name'], $scope_arr['fplugin'] ) === false )
                    continue;

                if( !empty( $scope_arr['fstatus'] )
                and $record_arr['status'] != $scope_arr['fstatus'] )
                    continue;

                $knti++;
                if( $on_this_page == $records_per_page )
                    break;

                if( $offset > $knti )
                    continue;

                $records_arr[] = $record_arr;

                $on_this_page++;
            }
        }

        $this->_paginator->set_records( $records_arr );

        return true;
    }

    /**
     * @return array|bool
     */
    public function load_paginator_params()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            $this->set_error( self::ERR_ACTION, self::_t( 'You should login first...' ) );
            return false;
        }

        if( !$this->_accounts_model->can_list_modules( $current_user ) )
        {
            $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to list modules.' ) );
            return false;
        }

        $this->_paginator_model->cache_all_db_details( true );

        $flow_params = array(
            'term_singular' => self::_t( 'module' ),
            'term_plural' => self::_t( 'modules' ),
            'after_record_callback' => array( $this, 'after_record_callback' ),
            'after_table_callback' => array( $this, 'after_table_callback' ),
        );

        if( !($modules_statuses = $this->_paginator_model->get_statuses_as_key_val()) )
            $modules_statuses = array();
        if( !empty( $modules_statuses ) )
            $modules_statuses = self::merge_array_assoc( array( -1 => self::_t( 'N/A' ), 0 => self::_t( ' - Choose - ' ) ), $modules_statuses );

        $filters_arr = array(
            array(
                'display_name' => self::_t( 'Plugin' ),
                'display_hint' => self::_t( 'All records containing this value' ),
                'var_name' => 'fplugin',
                'record_field' => 'plugin',
                'type' => PHS_params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => self::_t( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_params::T_INT,
                'default' => 0,
                'values_arr' => $modules_statuses,
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => self::_t( 'Plugin' ),
                'record_field' => 'name',
                'display_callback' => array( $this, 'display_plugin_name' ),
                'extra_style' => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;',
                'sortable' => false,
            ),
            array(
                'column_title' => self::_t( 'Version<br/><small>Installed / Script</small>' ),
                'record_field' => 'version',
                'extra_style' => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable' => false,
            ),
            array(
                'column_title' => self::_t( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $modules_statuses,
                'invalid_value' => self::_t( 'Undefined' ),
                'extra_style' => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable' => false,
            ),
            array(
                'column_title' => self::_t( 'Status Date' ),
                'record_field' => 'status_date',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd M y H:i',
                'invalid_value' => self::_t( 'N/A' ),
                'extra_style' => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable' => false,
            ),
            array(
                'column_title' => self::_t( 'Installed' ),
                'default_sort' => 1,
                'record_field' => 'cdate',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd M y H:i',
                'invalid_value' => self::_t( 'Not Installed' ),
                'extra_style' => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable' => false,
            ),
            array(
                'column_title' => self::_t( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'extra_style' => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable' => false,
            ),
        );

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'modules_list' ) );
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    /**
     * Manages actions to be taken for current listing
     *
     * @param array $action Action details array
     *
     * @return array|bool Returns true if no error or no action taken, false if there was an error while taking action or an action array in case action was taken (with success or not)
     */
    public function manage_action( $action )
    {
        $this->reset_error();

        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        $action_result_params = $this->_paginator->default_action_params();

        if( empty( $action ) or !is_array( $action )
         or empty( $action['action'] ) )
            return $action_result_params;

        $action_result_params['action'] = $action['action'];

        switch( $action['action'] )
        {
            default:
                PHS_Notifications::add_error_notice( self::_t( 'Unknown action.' ) );
                return true;
            break;

            case 'activate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account activated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Activating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );
                 
                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'Cannot activate account. Account not found.' ) );
                    return false;
                }

                if( !$this->_accounts_model->activate_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'inactivate_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account inactivated with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Inactivating account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'Cannot inactivate account. Account not found.' ) );
                    return false;
                }

                if( !$this->_accounts_model->inactivate_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
           break;

            case 'delete_account':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] == 'success' )
                        PHS_Notifications::add_success_notice( self::_t( 'Account deleted with success.' ) );
                    elseif( $action['action_result'] == 'failed' )
                        PHS_Notifications::add_error_notice( self::_t( 'Deleting account failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !$this->_accounts_model->can_manage_accounts( $current_user ) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'You don\'t have rights to manage accounts.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = intval( $action['action_params'] );

                if( empty( $action['action_params'] )
                 or !($account_arr = $this->_accounts_model->get_details( $action['action_params'] )) )
                {
                    $this->set_error( self::ERR_ACTION, self::_t( 'Cannot delete account. Account not found.' ) );
                    return false;
                }

                if( !$this->_accounts_model->delete_account( $account_arr ) )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;
        }

        return $action_result_params;
    }

    public function display_plugin_name( $params )
    {
        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] ) )
            return false;

        return '<div style="float:left;width:64px;max-width:64px;height:64px;max-height:64px;text-align: center;overflow: hidden;"><i class="fa fa-2x fa-puzzle-piece" style="line-height:64px;margin: 0 auto;"></i></div>'.
               '<strong>'.$params['preset_content'].'</strong>'.
               (!empty( $params['record']['models'] )?' - <small>'.self::_t( '%s models', count( $params['record']['models'] ) ).'</small>':'').
               (!empty( $params['record']['description'] )?'<br/><small>'.$params['record']['description'].'</small>':'');
    }

    public function display_actions( $params )
    {
        if( empty( $this->_paginator_model ) )
        {
            if( !$this->load_depencies() )
                return false;
        }

        if( empty( $params )
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or empty( $params['record']['id'] ) )
            return false;

        ob_start();
        if( empty( $params['record']['is_installed'] ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_install( '<?php echo $params['record']['id']?>' )"><i class="fa fa-plus-circle action-icons" title="<?php echo self::_t( 'Install module' )?>"></i></a>
            <?php
        }
        if( $this->_paginator_model->inactive_status( $params['record']['status'] ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_activate( '<?php echo $params['record']['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo self::_t( 'Activate module' )?>"></i></a>
            <?php
        }
        if( $this->_paginator_model->active_status( $params['record']['status'] ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_inactivate( '<?php echo $params['record']['id']?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo self::_t( 'Inactivate module' )?>"></i></a>
            <?php
        }

        if( empty( $params['record']['is_installed'] )
        and empty( $params['record']['is_core'] ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_modules_list_delete( '<?php echo $params['record']['id']?>' )"><i class="fa fa-times-circle-o action-icons" title="<?php echo self::_t( 'Delete module' )?>"></i></a>
            <?php
        }

        return ob_get_clean();
    }

    public function after_record_callback( $params )
    {

    }

    public function after_table_callback( $params )
    {
        static $js_functionality = false;

        if( !empty( $js_functionality ) )
            return '';

        $js_functionality = true;
        
        if( !($flow_params_arr = $this->_paginator->flow_params()) )
            $flow_params_arr = array();

        ob_start();
        ?>
        <script type="text/javascript">
        function phs_modules_list_install( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to install this module?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'install_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_modules_list_activate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to activate this module?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'activate_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_modules_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to inactivate this module?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'inactivate_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_modules_list_delete( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this module?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'delete_module',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        </script>
        <?php

        return ob_get_clean();
    }
}
