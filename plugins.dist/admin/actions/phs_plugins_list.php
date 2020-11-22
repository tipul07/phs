<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Action_Generic_list;
use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_Roles;

/** @property \phs\system\core\models\PHS_Model_Plugins $_paginator_model */
class PHS_Action_Plugins_list extends PHS_Action_Generic_list
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private $_accounts_model;

    public function load_depencies()
    {
        if( !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( !($this->_paginator_model = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_DEPENCIES, $this->_pt( 'Couldn\'t load plugins model.' ) );
            return false;
        }

        return true;
    }

    /**
     * @return array|bool Should return false if execution should continue or an array with an action result which should be returned by execute() method
     */
    public function should_stop_execution()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Plugins List' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

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

        if( !($scope_arr = $this->_paginator->get_scope())
         or !is_array( $scope_arr ) )
            $scope_arr = array();

        if( PHS_Params::_g( 'unknown_plugin', PHS_Params::T_INT ) )
            PHS_Notifications::add_error_notice( $this->_pt( 'Plugin ID is invalid or plugin was not found.' ) );

        $records_arr = array();

        if( !($page = $this->_paginator->pagination_params( 'page' )) )
            $page = 0;

        $count_offset = 0;
        if( !$page
        and empty( $scope_arr ) )
        {
            $count_offset = 1;
            // if on first page add core as plugin on first record...

            $core_details = PHS_Plugin::core_plugin_details_fields();

            $record_arr = array();
            $record_arr['id'] = $core_details['id'];
            $record_arr['plugin_name'] = $core_details['plugin_name'];
            $record_arr['vendor_id'] = $core_details['vendor_id'];
            $record_arr['vendor_name'] = $core_details['vendor_name'];
            $record_arr['name'] = $core_details['name'];
            $record_arr['description'] = $core_details['description'];
            $record_arr['version'] = $core_details['db_version'].' / '.$core_details['script_version'];
            $record_arr['status'] = $core_details['status'];
            $record_arr['status_date'] = date( PHS_Model::DATETIME_DB, @filemtime( PHS_PATH.'bootstrap.php' ) );
            $record_arr['cdate'] = $record_arr['status_date'];
            $record_arr['models'] = ((!empty( $core_details['models'] ) and is_array( $core_details['models'] ))?$core_details['models']:array());
            $record_arr['is_installed'] = true;
            $record_arr['is_core'] = true;
            $record_arr['is_always_active'] = $core_details['is_always_active'];
            $record_arr['is_distribution'] = $core_details['is_distribution'];

            $records_arr[] = $record_arr;
        }

        if( !($dir_entries = $this->_paginator_model->cache_all_dir_details())
         or !is_array( $dir_entries ) )
            $dir_entries = array();

        $this->_paginator->set_records_count( count( $dir_entries ) + 1 );

        if( !empty( $dir_entries ) )
        {
            $offset = $this->_paginator->pagination_params( 'offset' );
            $records_per_page = (int)$this->_paginator->pagination_params( 'records_per_page' );

            /**
             * @var string $plugin_dir
             * @var \phs\libraries\PHS_Plugin $plugin_instance
             */
            $knti = 1; // including core...
            $on_this_page = $count_offset;
            $add_records = true;
            foreach( $dir_entries as $plugin_dir => $plugin_instance )
            {
                if( !($plugin_info_arr = $plugin_instance->get_plugin_info()) )
                {
                    PHS_Notifications::add_warning_notice( $this->_pt( 'Couldn\'t get plugin info for %s.', @basename( $plugin_dir ) ) );
                    continue;
                }

                $record_arr = array();
                $record_arr['id'] = $plugin_info_arr['id'];
                $record_arr['plugin_name'] = $plugin_info_arr['plugin_name'];
                $record_arr['vendor_id'] = $plugin_info_arr['vendor_id'];
                $record_arr['vendor_name'] = $plugin_info_arr['vendor_name'];
                $record_arr['name'] = $plugin_info_arr['name'];
                $record_arr['description'] = $plugin_info_arr['description'];
                $record_arr['version'] = $plugin_info_arr['db_version'].' / '.$plugin_info_arr['script_version'];
                $record_arr['status'] = (!empty( $plugin_info_arr['db_details'] )?$plugin_info_arr['db_details']['status']:-1);
                $record_arr['status_date'] = (!empty( $plugin_info_arr['db_details'] )?$plugin_info_arr['db_details']['status_date']:null);
                $record_arr['cdate'] = (!empty( $plugin_info_arr['db_details'] )?$plugin_info_arr['db_details']['cdate']:null);
                $record_arr['models'] = ((!empty( $plugin_info_arr['models'] ) and is_array( $plugin_info_arr['models'] ))?$plugin_info_arr['models']:array());
                $record_arr['is_installed'] = $plugin_info_arr['is_installed'];
                $record_arr['is_upgradable'] = $plugin_info_arr['is_upgradable'];
                $record_arr['is_core'] = $plugin_info_arr['is_core'];
                $record_arr['is_always_active'] = $plugin_info_arr['is_always_active'];
                $record_arr['is_distribution'] = $plugin_info_arr['is_distribution'];

                if( !empty( $scope_arr['fplugin'] )
                and stripos( $record_arr['name'], $scope_arr['fplugin'] ) === false )
                    continue;

                if( !empty( $scope_arr['fvendor'] )
                and stripos( $record_arr['vendor_name'], $scope_arr['fvendor'] ) === false )
                    continue;

                if( !empty( $scope_arr['fstatus'] )
                and (int)$record_arr['status'] !== (int)$scope_arr['fstatus'] )
                    continue;

                $knti++;

                if( $on_this_page === $records_per_page )
                {
                    $add_records = false;
                    continue;
                }

                if( $offset > $knti - 1 )
                    continue;

                if( $add_records )
                {
                    $records_arr[] = $record_arr;
                    $on_this_page++;
                }
            }

            $this->_paginator->set_records_count( $knti - (empty( $scope_arr )?0:1) );
        }

        $this->_paginator->set_records( $records_arr );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_LIST_PLUGINS ) )
        {
            $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to list plugins.' ) );
            return false;
        }

        $this->_paginator_model->cache_all_db_details( true );

        $flow_params = array(
            'term_singular' => $this->_pt( 'plugin' ),
            'term_plural' => $this->_pt( 'plugins' ),
            'after_record_callback' => array( $this, 'after_record_callback' ),
            'after_table_callback' => array( $this, 'after_table_callback' ),
            'listing_title' => $this->_pt( 'Plugins List' ),
        );

        if( !($plugins_statuses = $this->_paginator_model->get_statuses_as_key_val()) )
            $plugins_statuses = array();
        if( !empty( $plugins_statuses ) )
            $plugins_statuses = self::merge_array_assoc( array( 0 => $this->_pt( ' - Choose - ' ), -1 => $this->_pt( 'Not Installed' ) ), $plugins_statuses );

        $filters_arr = array(
            array(
                'display_name' => $this->_pt( 'Plugin' ),
                'display_hint' => $this->_pt( 'All plugins for which name contains this value' ),
                'var_name' => 'fplugin',
                'record_field' => 'plugin',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'Vendor' ),
                'display_hint' => $this->_pt( 'All plugins from specified vendor' ),
                'var_name' => 'fvendor',
                'record_field' => 'plugin',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
            ),
            array(
                'display_name' => $this->_pt( 'Status' ),
                'var_name' => 'fstatus',
                'record_field' => 'status',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'values_arr' => $plugins_statuses,
            ),
        );

        $columns_arr = array(
            array(
                'column_title' => $this->_pt( 'Plugin' ),
                'record_field' => 'name',
                'display_callback' => array( $this, 'display_plugin_name' ),
                'extra_style' => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;',
                'sortable' => false,
            ),
            array(
                'column_title' => $this->_pt( 'Vendor' ),
                'record_field' => 'vendor_name',
                'extra_style' => 'vertical-align: middle;text-align:center;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable' => false,
            ),
            array(
                'column_title' => $this->_pt( 'Version' ).'<br/><small>'.$this->_pt( 'Installed / Script' ).'</small>',
                'record_field' => 'version',
                'extra_style' => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable' => false,
            ),
            array(
                'column_title' => $this->_pt( 'Status' ),
                'record_field' => 'status',
                'display_key_value' => $plugins_statuses,
                'invalid_value' => $this->_pt( 'Undefined' ),
                'extra_style' => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable' => false,
            ),
            array(
                'column_title' => $this->_pt( 'Status Date' ),
                'record_field' => 'status_date',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'N/A' ),
                'extra_style' => 'vertical-align: middle;width:120px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable' => false,
            ),
            array(
                'column_title' => $this->_pt( 'Installed' ),
                'default_sort' => 1,
                'record_field' => 'cdate',
                'display_callback' => array( &$this->_paginator, 'pretty_date' ),
                'date_format' => 'd-m-Y H:i',
                'invalid_value' => $this->_pt( 'Not Installed' ),
                'extra_style' => 'vertical-align: middle;width:120px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable' => false,
            ),
            array(
                'column_title' => $this->_pt( 'Actions' ),
                'display_callback' => array( $this, 'display_actions' ),
                'extra_style' => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;white-space: nowrap;',
                'sortable' => false,
            ),
        );

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'plugins_list' ) );
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
                PHS_Notifications::add_error_notice( $this->_pt( 'Unknown action.' ) );
                return true;
            break;

            case 'install_plugin':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Plugin installed with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Installing plugin failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage plugins.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = trim( $action['action_params'] );

                if( !($instance_details = PHS_Instantiable::valid_instance_id( $action['action_params'] ))
                 or empty( $instance_details['instance_type'] )
                 or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
                {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot install plugin. Invalid module ID.' ) );
                    return false;
                }

                if( !($plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t instantiate plugin.' ) );
                    return false;
                }

                if( !$plugin_obj->install() )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'activate_plugin':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Plugin activated with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Activating plugin failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage plugins.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = trim( $action['action_params'] );

                if( !($instance_details = PHS_Instantiable::valid_instance_id( $action['action_params'] ))
                    or empty( $instance_details['instance_type'] )
                    or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
                {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot install plugin. Invalid plugin ID.' ) );
                    return false;
                }

                if( !($plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t instantiate plugin.' ) );
                    return false;
                }

                if( !$plugin_obj->activate_plugin() )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'inactivate_plugin':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Plugin inactivated with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Inactivating plugin failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage plugins.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = trim( $action['action_params'] );

                if( !($instance_details = PHS_Instantiable::valid_instance_id( $action['action_params'] ))
                 or empty( $instance_details['instance_type'] )
                 or empty( $instance_details['plugin_name'] )
                 or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
                {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot inactivate plugin. Invalid plugin ID.' ) );
                    return false;
                }

                if( !($plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t instantiate plugin.' ) );
                    return false;
                }

                if( !$plugin_obj->inactivate_plugin() )
                    $action_result_params['action_result'] = 'failed';
                else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'upgrade_plugin':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Plugin upgraded with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Upgrading plugin failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage plugins.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = trim( $action['action_params'] );

                if( !($instance_details = PHS_Instantiable::valid_instance_id( $action['action_params'] ))
                 or empty( $instance_details['instance_type'] )
                 or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
                {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot upgrade plugin. Invalid plugin ID.' ) );
                    return false;
                }

                /** @var \phs\libraries\PHS_Plugin $plugin_obj */
                if( !($plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t instantiate plugin.' ) );
                    return false;
                }

                if( !($plugin_info_arr = $plugin_obj->get_plugin_info())
                 or (!empty( $plugin_info_arr['is_upgradable'] )
                        and !$plugin_obj->update( $plugin_info_arr['db_version'], $plugin_info_arr['script_version'] )
                    ) )
                {
                    if( $plugin_obj->has_error() )
                    {
                        $this->copy_error( $plugin_obj, self::ERR_ACTION );
                        return false;
                    }

                    $action_result_params['action_result'] = 'failed';
                } else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'uninstall_plugin':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Plugin uninstalled with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Uninstalling plugin failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage plugins.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = trim( $action['action_params'] );

                if( !($instance_details = PHS_Instantiable::valid_instance_id( $action['action_params'] ))
                 or empty( $instance_details['instance_type'] )
                 or empty( $instance_details['plugin_name'] )
                 or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
                {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot uninstall plugin. Invalid plugin ID.' ) );
                    return false;
                }

                if( in_array( $instance_details['plugin_name'], PHS::get_distribution_plugins(), true ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot uninstall this plugin.' ) );
                    return false;
                }

                if( !($plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t instantiate plugin.' ) );
                    return false;
                }

                if( !$plugin_obj->uninstall() )
                {
                    if( $plugin_obj->has_error() )
                    {
                        $this->copy_error( $plugin_obj, self::ERR_ACTION );
                        return false;
                    }

                    $action_result_params['action_result'] = 'failed';
                } else
                    $action_result_params['action_result'] = 'success';
            break;

            case 'delete_plugin':
                if( !empty( $action['action_result'] ) )
                {
                    if( $action['action_result'] === 'success' )
                        PHS_Notifications::add_success_notice( $this->_pt( 'Plugin deleted with success.' ) );
                    elseif( $action['action_result'] === 'failed' )
                        PHS_Notifications::add_error_notice( $this->_pt( 'Deleting plugin failed. Please try again.' ) );

                    return true;
                }

                if( !($current_user = PHS::user_logged_in())
                 or !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'You don\'t have rights to manage plugins.' ) );
                    return false;
                }

                if( !empty( $action['action_params'] ) )
                    $action['action_params'] = trim( $action['action_params'] );

                if( !($instance_details = PHS_Instantiable::valid_instance_id( $action['action_params'] ))
                 or empty( $instance_details['instance_type'] )
                 or empty( $instance_details['plugin_name'] )
                 or $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN )
                {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete plugin. Invalid plugin ID.' ) );
                    return false;
                }

                if( in_array( $instance_details['plugin_name'], PHS::get_distribution_plugins(), true ) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Cannot delete this plugin.' ) );
                    return false;
                }

                if( !($plugin_obj = PHS::load_plugin( $instance_details['plugin_name'] )) )
                {
                    $this->set_error( self::ERR_ACTION, $this->_pt( 'Couldn\'t instantiate plugin.' ) );
                    return false;
                }

                // if( !$plugin_obj->uninstall() )
                    $action_result_params['action_result'] = 'failed';
                // else
                //     $action_result_params['action_result'] = 'success';
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
               (!empty( $params['record']['models'] )?' - <small>'.$this->_pt( '%s models', count( $params['record']['models'] ) ).'</small>':'').
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
            <a href="javascript:void(0)" onclick="phs_plugins_list_install( '<?php echo $params['record']['id']?>' )"><i class="fa fa-plus-circle action-icons" title="<?php echo $this->_pt( 'Install plugin' )?>"></i></a>
            <?php
        }
        if( $params['record']['id'] !== PHS_Plugin::CORE_PLUGIN
        and empty( $params['record']['is_always_active'] )
        and $this->_paginator_model->is_inactive( $params['record'] ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_plugins_list_uninstall( '<?php echo $params['record']['id']?>' )"><i class="fa fa-sign-out action-icons" title="<?php echo $this->_pt( 'Uninstall plugin' )?>"></i></a>
            <a href="javascript:void(0)" onclick="phs_plugins_list_activate( '<?php echo $params['record']['id']?>' )"><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt( 'Activate plugin' )?>"></i></a>
            <?php
        }
        if( $this->_paginator_model->is_active( $params['record'] ) )
        {
            if( !empty( $params['record']['is_upgradable'] ) )
            {
                ?>
                <a href="javascript:void(0)" onclick="phs_plugins_list_upgrade( '<?php echo $params['record']['id'] ?>' )"><i class="fa fa-arrow-circle-o-up action-icons" title="<?php echo $this->_pt( 'Upgrade plugin' ) ?>"></i></a>
                <?php
            }
            ?>
            <a href="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'plugin_settings' ), array( 'pid' => $params['record']['id'], 'back_page' => $this->_paginator->get_full_url() )  )?>"><i class="fa fa-wrench action-icons" title="<?php echo $this->_pt( 'Plugin Settings' )?>"></i></a>
            <?php
            if( $params['record']['id'] !== PHS_Plugin::CORE_PLUGIN
            and empty( $params['record']['is_always_active'] ) )
            {
                ?>
                <a href="javascript:void(0)" onclick="phs_plugins_list_inactivate( '<?php echo $params['record']['id'] ?>' )"><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt( 'Inactivate plugin' ) ?>"></i></a>
                <?php
            }
        }

        if( $params['record']['id'] !== PHS_Plugin::CORE_PLUGIN
        and empty( $params['record']['is_always_active'] )
        and empty( $params['record']['is_installed'] )
        and empty( $params['record']['is_core'] ) )
        {
            ?>
            <a href="javascript:void(0)" onclick="phs_plugins_list_delete( '<?php echo $params['record']['id']?>' )"><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete plugin' )?>"></i></a>
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

        ob_start();
        ?>
        <script type="text/javascript">
        function phs_plugins_list_install( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to install this plugin?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'install_plugin',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_plugins_list_uninstall( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to uninstall this plugin?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: Plugin settings will be deleted. Some plugins will also delete information stored in database!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'uninstall_plugin',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_plugins_list_activate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to activate this plugin?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'activate_plugin',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_plugins_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to inactivate this plugin?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'inactivate_plugin',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_plugins_list_upgrade( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to upgrade this plugin?', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'upgrade_plugin',
                    'action_params' => '" + id + "',
                )
                ?>document.location = "<?php echo $this->_paginator->get_full_url( $url_params )?>";
            }
        }
        function phs_plugins_list_delete( id )
        {
            if( confirm( "<?php echo self::_e( 'Are you sure you want to DELETE this plugin?', '"' )?>" + "\n" +
                         "<?php echo self::_e( 'NOTE: You cannot undo this action!', '"' )?>" ) )
            {
                <?php
                $url_params = array();
                $url_params['action'] = array(
                    'action' => 'delete_plugin',
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
