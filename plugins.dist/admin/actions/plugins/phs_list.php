<?php
namespace phs\plugins\admin\actions\plugins;

use phs\PHS;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\libraries\PHS_Action_Generic_list;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Tenants;

/** @property \phs\system\core\models\PHS_Model_Plugins $_paginator_model */
class PHS_Action_List extends PHS_Action_Generic_list
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Model_Tenants $_tenants_model = null;

    private array $_tenants_key_val_arr = [];

    public function load_depencies(): bool
    {
        if (!($this->_admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($this->_tenants_model = PHS_Model_Tenants::get_instance())
         || !($this->_paginator_model = PHS_Model_Plugins::get_instance())) {
            $this->set_error(self::ERR_DEPENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function should_stop_execution(): ?array
    {
        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_admin_plugin->can_admin_list_plugins()) {
            PHS_Notifications::add_warning_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        return null;
    }

    public function we_initialized_paginator(): bool
    {
        $this->reset_error();

        $scope_arr = $this->_paginator->get_scope() ?: [];

        if (PHS_Params::_g('unknown_plugin', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Plugin ID is invalid or plugin was not found.'));
        }
        if (PHS_Params::_g('unknown_tenant', PHS_Params::T_INT)) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid tenant or tenant was not found in database.'));
        }

        $records_arr = $this->_paginator_model->get_all_records_for_paginator() ?: [];

        $this->_paginator->set_records_count(count($records_arr));

        $page_records_arr = [];
        $on_page_records = 0;
        if (!empty($records_arr)) {
            $offset = $this->_paginator->pagination_params('offset');
            $records_per_page = (int)$this->_paginator->pagination_params('records_per_page');

            $knti = 0;
            foreach ($records_arr as $record_arr) {
                if (!$this->_check_record_for_current_scope($record_arr, $scope_arr)) {
                    continue;
                }

                $knti++;

                if ($offset > $knti - 1
                 || $on_page_records >= $records_per_page) {
                    continue;
                }

                $page_records_arr[] = $record_arr;
                $on_page_records++;
            }

            $this->_paginator->set_records_count($knti);
        }

        $this->_paginator->set_records($page_records_arr);

        $this->_paginator->flow_param('did_query_database', true);
        $this->_paginator->flow_param('records_from_model', true);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function load_paginator_params(): ?array
    {
        PHS::page_settings('page_title', $this->_pt('Plugins List'));

        $is_multi_tenant = PHS::is_multi_tenant();
        $can_export_settings = $this->_admin_plugin->can_admin_export_plugins_settings();

        $this->_paginator_model->get_all_db_details(true);

        $flow_params = [
            'listing_title'        => $this->_pt('Plugins List'),
            'term_singular'        => $this->_pt('plugin'),
            'term_plural'          => $this->_pt('plugins'),
            'after_table_callback' => [$this, 'after_table_callback'],
        ];

        $plugins_statuses = self::merge_array_assoc([
            0 => $this->_pt(' - Choose - '),
            -1 => $this->_pt('Not Installed')
        ], $this->_paginator_model->get_statuses_as_key_val()
        );

        if ($is_multi_tenant) {
            $this->_tenants_key_val_arr = $this->_tenants_model->get_tenants_as_key_val() ?: [];
        }

        $tenants_filter_arr = [];
        if (!empty($this->_tenants_key_val_arr)) {
            $tenants_filter_arr = self::merge_array_assoc([0 => $this->_pt(' - All - ')], $this->_tenants_key_val_arr);
        }

        $bulk_actions = [];
        if ($can_export_settings) {
            $bulk_actions[] = [
                'display_name'    => $this->_pt('Export settings for selected'),
                'action'          => 'bulk_export_selected',
                'js_callback'     => 'phs_plugins_list_bulk_export_selected',
                'checkbox_column' => 'plugin_name',
            ];
            $bulk_actions[] = [
                'display_name'    => $this->_pt('Export settings for ALL'),
                'action'          => 'bulk_export_all',
                'js_callback'     => 'phs_plugins_list_bulk_export_all',
                'checkbox_column' => 'plugin_name',
            ];
        }

        $filters_arr = [
            [
                'display_name' => $this->_pt('Plugin Name'),
                'display_hint' => $this->_pt('All plugins for which name contains this value'),
                'var_name'     => 'fplugin',
                'record_field' => 'name',
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Vendor'),
                'display_hint' => $this->_pt('All plugins from specified vendor'),
                'var_name'     => 'fvendor',
                'record_field' => 'vendor_name',
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            [
                'display_name' => $this->_pt('Status'),
                'var_name'     => 'fstatus',
                'record_field' => 'status',
                'type'         => PHS_Params::T_INT,
                'default'      => 0,
                'values_arr'   => $plugins_statuses,
            ],
        ];

        if ($is_multi_tenant) {
            $filters_arr = array_merge($filters_arr, [
                [
                    'display_name' => $this->_pt('Tenant'),
                    'var_name'     => 'ftenant',
                    'record_field' => 'tenants',
                    'type'         => PHS_Params::T_INT,
                    'default'      => 0,
                    'values_arr'   => $tenants_filter_arr,
                ],
            ]);
        }

        $columns_arr = [
            [
                'column_title'     => '&nbsp;',
                'record_field'     => 'plugin_name',
                'display_callback' => function() {
                    return '';
                },
                'extra_style'         => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;',
                'sortable'            => false,
            ],
            [
                'column_title'        => $this->_pt('Plugin'),
                'record_field'        => 'name',
                'display_callback'    => [$this, 'display_plugin_name'],
                'extra_style'         => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;',
                'sortable'            => false,
            ],
            [
                'column_title'        => $this->_pt('Vendor'),
                'record_field'        => 'vendor_name',
                'extra_style'         => 'vertical-align: middle;text-align:center;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable'            => false,
            ],
        ];

        if ($is_multi_tenant) {
            $columns_arr = array_merge($columns_arr, [
                [
                    'column_title'        => $this->_pt('Tenant'),
                    'record_field'        => 'tenant',
                    'display_callback'    => [$this, 'display_plugin_tenants'],
                    'extra_style'         => 'vertical-align: middle;text-align:center;',
                    'extra_records_style' => 'vertical-align: middle;text-align:center;',
                    'sortable'            => false,
                ],
            ]);
        }

        $columns_arr = array_merge($columns_arr, [
            [
                'column_title'        => $this->_pt('Version').'<br/><small>'.$this->_pt('Installed / Script').'</small>',
                'record_field'        => 'version',
                'extra_style'         => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable'            => false,
            ],
            [
                'column_title'        => $this->_pt('Status'),
                'record_field'        => 'status',
                'display_key_value'   => $plugins_statuses,
                'invalid_value'       => $this->_pt('Undefined'),
                'extra_style'         => 'vertical-align: middle;',
                'extra_records_style' => 'vertical-align: middle;text-align:center;',
                'sortable'            => false,
            ],
            [
                'column_title'        => $this->_pt('Status Date'),
                'record_field'        => 'status_date',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('N/A'),
                'extra_style'         => 'vertical-align: middle;width:120px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable'            => false,
            ],
            [
                'column_title'        => $this->_pt('Installed'),
                'default_sort'        => 1,
                'record_field'        => 'cdate',
                'display_callback'    => [&$this->_paginator, 'pretty_date'],
                'date_format'         => 'd-m-Y H:i',
                'invalid_value'       => $this->_pt('Not Installed'),
                'extra_style'         => 'vertical-align: middle;width:120px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;',
                'sortable'            => false,
            ],
            [
                'column_title'        => $this->_pt('Actions'),
                'display_callback'    => [$this, 'display_actions'],
                'extra_style'         => 'vertical-align: middle;width:100px;',
                'extra_records_style' => 'vertical-align: middle;text-align:right;white-space: nowrap;',
                'sortable'            => false,
            ],
        ]);

        if ($this->_admin_plugin->can_admin_export_plugins_settings()) {
            $columns_arr[0]['checkbox_record_index_key'] = [
                'key'  => 'plugin_name',
                'type' => PHS_params::T_NOHTML,
            ];
        }

        $return_arr = $this->default_paginator_params();
        $return_arr['base_url'] = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins']);
        $return_arr['flow_parameters'] = $flow_params;
        $return_arr['bulk_actions'] = $bulk_actions;
        $return_arr['filters_arr'] = $filters_arr;
        $return_arr['columns_arr'] = $columns_arr;

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    public function manage_action($action): null|bool|array
    {
        $this->reset_error();
        $action_result_params = $this->_paginator->default_action_params();

        if (empty($action['action'])) {
            return $action_result_params;
        }

        $action_result_params['action'] = $action['action'];

        switch ($action['action']) {
            default:
                PHS_Notifications::add_error_notice($this->_pt('Unknown action.'));

                return true;

            case 'bulk_export_selected':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required plugin settings exported with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Exporting selected plugin settings failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed exporting all selected plugin settings. Plugin settings which failed export are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_export_plugins_settings()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($scope_arr = $this->_paginator->get_scope())
                 || !($ids_checkboxes_name = $this->_paginator->get_checkbox_name_format())
                 || !($scope_key = @sprintf($ids_checkboxes_name, 'plugin_name'))
                 || empty($scope_arr[$scope_key])
                 || !is_array($scope_arr[$scope_key])) {
                    return true;
                }

                if (!($crypt_key = PHS_Params::_p('crypt_key', PHS_Params::T_NOHTML))) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('Crypting key not provided.'));

                    return false;
                }

                if (!($plugin_settings_lib = $this->_admin_plugin->get_plugin_settings_instance())) {
                    $this->set_error(self::ERR_DEPENCIES, $this->_pt('Error loading required resources.'));

                    return false;
                }

                $export_params = [];
                $export_params['export_file_name'] = 'plugin_settings_'.date('YmdHi').'.json';

                if (!$plugin_settings_lib->export_plugin_settings($crypt_key, $scope_arr[$scope_key], $export_params)) {
                    $action_result_params['action_result'] = 'failed';
                    $action_result_params['action_redirect_url_params'] = ['force_scope' => $scope_arr];
                }
                break;

            case 'bulk_export_all':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Required plugin settings exported with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Exporting selected plugin settings failed. Please try again.'));
                    } elseif ($action['action_result'] === 'failed_some') {
                        PHS_Notifications::add_error_notice($this->_pt('Failed exporting all selected plugin settings. Plugin settings which failed export are still selected. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_export_plugins_settings()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!($crypt_key = PHS_Params::_p('crypt_key', PHS_Params::T_NOHTML))) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('Crypting key not provided.'));

                    return false;
                }

                if (!($plugin_settings_lib = $this->_admin_plugin->get_plugin_settings_instance())) {
                    $this->set_error(self::ERR_DEPENCIES, $this->_pt('Error loading required resources.'));

                    return false;
                }

                $export_params = [];
                $export_params['export_file_name'] = 'plugin_settings_all_'.date('YmdHi').'.json';

                if (!$plugin_settings_lib->export_plugin_settings($crypt_key, [], $export_params)) {
                    $action_result_params['action_result'] = 'failed';
                }
                break;

            case 'install_plugin':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Plugin installed with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Installing plugin failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_plugins()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = trim($action['action_params']);
                }

                if (!($instance_details = PHS_Instantiable::valid_instance_id($action['action_params']))
                 || empty($instance_details['instance_type'])
                 || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot install plugin. Invalid module ID.'));

                    return false;
                }

                if (!($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t instantiate plugin.'));

                    return false;
                }

                if (!$plugin_obj->install()) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'activate_plugin':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Plugin activated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Activating plugin failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_plugins()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = trim($action['action_params']);
                }

                if (!($instance_details = PHS_Instantiable::valid_instance_id($action['action_params']))
                    || empty($instance_details['instance_type'])
                    || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot install plugin. Invalid plugin ID.'));

                    return false;
                }

                if (!($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t instantiate plugin.'));

                    return false;
                }

                if (!$plugin_obj->activate_plugin()) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'inactivate_plugin':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Plugin inactivated with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Inactivating plugin failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_plugins()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = trim($action['action_params']);
                }

                if (!($instance_details = PHS_Instantiable::valid_instance_id($action['action_params']))
                 || empty($instance_details['instance_type'])
                 || empty($instance_details['plugin_name'])
                 || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot inactivate plugin. Invalid plugin ID.'));

                    return false;
                }

                if (!($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t instantiate plugin.'));

                    return false;
                }

                if (!$plugin_obj->inactivate_plugin()) {
                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'upgrade_plugin':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Plugin upgraded with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Upgrading plugin failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_plugins()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = trim($action['action_params']);
                }

                if (!($instance_details = PHS_Instantiable::valid_instance_id($action['action_params']))
                 || empty($instance_details['instance_type'])
                 || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot upgrade plugin. Invalid plugin ID.'));

                    return false;
                }

                /** @var \phs\libraries\PHS_Plugin $plugin_obj */
                if (!($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t instantiate plugin.'));

                    return false;
                }

                if (!($plugin_info_arr = $plugin_obj->get_plugin_info())
                 || (!empty($plugin_info_arr['is_upgradable'])
                        && !$plugin_obj->update($plugin_info_arr['db_version'], $plugin_info_arr['script_version'])
                 )) {
                    if ($plugin_obj->has_error()) {
                        $this->copy_error($plugin_obj, self::ERR_ACTION);

                        return false;
                    }

                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'uninstall_plugin':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Plugin uninstalled with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Uninstalling plugin failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_plugins()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = trim($action['action_params']);
                }

                if (!($instance_details = PHS_Instantiable::valid_instance_id($action['action_params']))
                 || empty($instance_details['instance_type'])
                 || empty($instance_details['plugin_name'])
                 || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot uninstall plugin. Invalid plugin ID.'));

                    return false;
                }

                if (in_array($instance_details['plugin_name'], PHS::get_distribution_plugins(), true)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot uninstall this plugin.'));

                    return false;
                }

                if (!($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t instantiate plugin.'));

                    return false;
                }

                if (!$plugin_obj->uninstall()) {
                    if ($plugin_obj->has_error()) {
                        $this->copy_error($plugin_obj, self::ERR_ACTION);

                        return false;
                    }

                    $action_result_params['action_result'] = 'failed';
                } else {
                    $action_result_params['action_result'] = 'success';
                }
                break;

            case 'delete_plugin':
                if (!empty($action['action_result'])) {
                    if ($action['action_result'] === 'success') {
                        PHS_Notifications::add_success_notice($this->_pt('Plugin deleted with success.'));
                    } elseif ($action['action_result'] === 'failed') {
                        PHS_Notifications::add_error_notice($this->_pt('Deleting plugin failed. Please try again.'));
                    }

                    return true;
                }

                if (!$this->_admin_plugin->can_admin_manage_plugins()) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('You don\'t have rights to access this section.'));

                    return false;
                }

                if (!empty($action['action_params'])) {
                    $action['action_params'] = trim($action['action_params']);
                }

                if (!($instance_details = PHS_Instantiable::valid_instance_id($action['action_params']))
                 || empty($instance_details['instance_type'])
                 || empty($instance_details['plugin_name'])
                 || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_PLUGIN) {
                    // reset error set by valid_instance_id()
                    self::st_reset_error();

                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete plugin. Invalid plugin ID.'));

                    return false;
                }

                if (in_array($instance_details['plugin_name'], PHS::get_distribution_plugins(), true)) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Cannot delete this plugin.'));

                    return false;
                }

                if (!($plugin_obj = PHS::load_plugin($instance_details['plugin_name']))) {
                    $this->set_error(self::ERR_ACTION, $this->_pt('Couldn\'t instantiate plugin.'));

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

    public function display_plugin_name($params)
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        return '<div style="float:left;width:64px;max-width:64px;height:64px;max-height:64px;text-align: center;overflow: hidden;"><i class="fa fa-2x fa-puzzle-piece" style="line-height:64px;margin: 0 auto;"></i></div>'
               .'<strong>'.$params['preset_content'].'</strong>'
               .(!empty($params['record']['models']) ? ' - <small>'.$this->_pt('%s models', count($params['record']['models'])).'</small>' : '')
               .(!empty($params['record']['description']) ? '<br/><small>'.$params['record']['description'].'</small>' : '');
    }

    public function display_plugin_tenants($params)
    {
        if (empty($params['record']) || !is_array($params['record'])) {
            return false;
        }

        if (empty($params['record']['tenants']) || !is_array($params['record']['tenants'])) {
            return '<strong>'.$this->_pt('All').'</strong>';
        }

        return implode(', ', array_merge(['<strong>'.$this->_pt('All').'</strong>'], array_filter(array_map(function(int $tenant_id) {
            if (!empty($this->_tenants_key_val_arr[$tenant_id])) {
                return $this->_tenants_key_val_arr[$tenant_id];
            }

            return null;
        }, $params['record']['tenants']))));
    }

    public function display_actions($params)
    {
        if (empty($this->_paginator_model)
            && !$this->load_depencies()) {
            return false;
        }

        if (empty($params)
         || !is_array($params)
         || empty($params['record']) || !is_array($params['record'])
         || empty($params['record']['id'])) {
            return false;
        }

        if(!$this->_admin_plugin->can_admin_manage_plugins()) {
            return '-';
        }

        ob_start();
        if (empty($params['record']['is_installed'])) {
            ?>
            <a href="javascript:void(0)" onclick="phs_plugins_list_install( '<?php echo $params['record']['id']; ?>' )"
            ><i class="fa fa-plus-circle action-icons" title="<?php echo $this->_pt('Install plugin'); ?>"></i></a>
            <?php
        }
        if ($params['record']['id'] !== PHS_Instantiable::CORE_PLUGIN
         && empty($params['record']['is_always_active'])
         && $this->_paginator_model->is_inactive($params['record'])) {
            ?>
            <a href="javascript:void(0)" onclick="phs_plugins_list_uninstall( '<?php echo $params['record']['id']; ?>' )"
            ><i class="fa fa-sign-out action-icons" title="<?php echo $this->_pt('Uninstall plugin'); ?>"></i></a>
            <a href="javascript:void(0)" onclick="phs_plugins_list_activate( '<?php echo $params['record']['id']; ?>' )"
            ><i class="fa fa-play-circle-o action-icons" title="<?php echo $this->_pt('Activate plugin'); ?>"></i></a>
            <?php
        }
        if ($this->_paginator_model->is_active($params['record'])) {
            if (!empty($params['record']['is_upgradable'])) {
                ?>
                <a href="javascript:void(0)" onclick="phs_plugins_list_upgrade( '<?php echo $params['record']['id']; ?>' )"
                ><i class="fa fa-arrow-circle-o-up action-icons" title="<?php echo $this->_pt('Upgrade plugin'); ?>"></i></a>
                <?php
            }
            if ($params['record']['id'] !== PHS_Instantiable::CORE_PLUGIN ) {
                ?>
                <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'registry', 'ad' => 'plugins'],
                    ['pname' => $params['record']['plugin_name'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
                ><i class="fa fa-database action-icons" title="<?php echo $this->_pt('Plugin Registry'); ?>"></i></a>
                <?php
            }
            ?>
            <a href="<?php echo PHS::url(['p' => 'admin', 'a' => 'settings', 'ad' => 'plugins'],
                ['pid' => $params['record']['id'], 'back_page' => $this->_paginator->get_full_url()]); ?>"
            ><i class="fa fa-wrench action-icons" title="<?php echo $this->_pt('Plugin Settings'); ?>"></i></a>
            <?php
            if ($params['record']['id'] !== PHS_Instantiable::CORE_PLUGIN
             && empty($params['record']['is_always_active'])) {
                ?>
                <a href="javascript:void(0)" onclick="phs_plugins_list_inactivate( '<?php echo $params['record']['id']; ?>' )"
                ><i class="fa fa-pause-circle-o action-icons" title="<?php echo $this->_pt('Inactivate plugin'); ?>"></i></a>
                <?php
            }
        }

        if ($params['record']['id'] !== PHS_Instantiable::CORE_PLUGIN
         && empty($params['record']['is_always_active'])
         && empty($params['record']['is_installed'])
         && empty($params['record']['is_core'])) {
            ?>
            <a href="javascript:void(0)" onclick="phs_plugins_list_delete( '<?php echo $params['record']['id']; ?>' )"
            ><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt('Delete plugin'); ?>"></i></a>
            <?php
        }

        return ob_get_clean();
    }

    public function after_table_callback($params)
    {
        static $js_functionality = false;

        if (!empty($js_functionality)) {
            return '';
        }

        $js_functionality = true;

        ob_start();
        ?>
        <script type="text/javascript">
        function phs_plugins_list_install( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to install this plugin?', '"'); ?>" ) )
            {
                show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
                <?php
                $url_params = [];
        $url_params['action'] = [
            'action'        => 'install_plugin',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_plugins_list_uninstall( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to uninstall this plugin?', '"'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: Plugin settings will be deleted. Some plugins will also delete information stored in database!', '"'); ?>" ) )
            {
                show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'uninstall_plugin',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_plugins_list_activate( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to activate this plugin?', '"'); ?>" ) )
            {
                show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'activate_plugin',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_plugins_list_inactivate( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to inactivate this plugin?', '"'); ?>" ) )
            {
                show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'inactivate_plugin',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_plugins_list_upgrade( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to upgrade this plugin?', '"'); ?>" ) )
            {
                show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'upgrade_plugin',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }
        function phs_plugins_list_delete( id )
        {
            if( confirm( "<?php echo self::_e('Are you sure you want to DELETE this plugin?', '"'); ?>" + "\n" +
                         "<?php echo self::_e('NOTE: You cannot undo this action!', '"'); ?>" ) )
            {
                show_submit_protection("<?php echo $this->_pte('Please wait...')?>");
                <?php
        $url_params = [];
        $url_params['action'] = [
            'action'        => 'delete_plugin',
            'action_params' => '" + id + "',
        ];
        ?>document.location = "<?php echo $this->_paginator->get_full_url($url_params); ?>";
            }
        }

        function phs_plugins_list_get_checked_ids_count()
        {
            const checkboxes_list = phs_paginator_get_checkboxes_checked('plugin_name');
            if( !checkboxes_list || checkboxes_list.length === 0 )
                return 0;

            return checkboxes_list.length;
        }

        function phs_cancel_export_plugin_settings_dialogue()
        {
            PHS_JSEN.closeAjaxDialog( 'phs_export_plugins_settings_' );
        }
        function phs_plugins_list_bulk_export_selected()
        {
            var container_obj = $("#phs_export_plugins_settings_container");
            if( !container_obj )
                return;

            container_obj.show();

            PHS_JSEN.createAjaxDialog( {
                suffix: 'phs_export_plugins_settings_',
                width: 550,
                height: 400,
                title: "<?php echo $this->_pte('Export Plugins\' Settings'); ?>",
                resizable: false,
                close_outside_click: false,
                source_obj: container_obj,
                source_not_cloned: true,
                onbeforeclose: closing_phs_plugins_export_dialogue
            });

            return false;
        }
        function phs_plugins_list_bulk_export_all()
        {
            var container_obj = $("#phs_export_plugins_settings_container");
            if( !container_obj )
                return;

            container_obj.show();

            PHS_JSEN.createAjaxDialog( {
                suffix: 'phs_export_plugins_settings_',
                width: 550,
                height: 380,
                title: "<?php echo $this->_pte('Export ALL Plugins\' Settings'); ?>",
                resizable: false,
                close_outside_click: false,
                source_obj: container_obj,
                source_not_cloned: true,
                onbeforeclose: closing_phs_plugins_export_dialogue
            });

            return false;
        }
        function closing_phs_plugins_export_dialogue()
        {
            var container_obj = $("#phs_export_plugins_settings_container");
            if( !container_obj )
                return;

            container_obj.hide();
        }

        let crypt_key_text = "";
        function submit_plugins_export_functionality()
        {
            if( crypt_key_text.length === 0 )
            {
                alert( "<?php echo $this->_pte('Please provide a crypting key.'); ?>" );
                return false;
            }
            if( crypt_key_text.length < 64 )
            {
                alert( "<?php echo $this->_pt('Crypting key length should be at least 64 characters length. Current length is %s characters.', '" + crypt_key_text.length + "'); ?>" );
                return false;
            }

            if(PHS_JSEN) {
                PHS_JSEN.js_messages_hide_all();
            }

            phs_cancel_export_plugin_settings_dialogue();

            // Give DOM time to update...
            setTimeout(function(){
                if(PHS_JSEN) {
                    PHS_JSEN.js_message_success("<?php echo $this->_pte('Exporting plugin settings...'); ?>");
                }
                $("#phs_export_plugin_settings_crypt_key").val( crypt_key_text );

                const form_obj = $("#<?php echo $this->_paginator->get_listing_form_name(); ?>");
                if( form_obj ) {
                    form_obj.submit();
                }
            }, 500 );

            return false;
        }
        </script>
        <div style="display: none;" id="phs_export_plugins_settings_container">
        <div class="mb-3">
            <label for="phs_export_plugin_settings_crypt_key" class="form-label"><?php echo $this->_pt('Crypt Key'); ?></label>
            <input name="crypt_key" id="phs_export_plugin_settings_crypt_key" class="form-control"
                   type="text" value="" aria-describedby="phs_crypt_key_help"
                   onchange="crypt_key_text = $(this).val().trim()"
                   placeholder="<?php echo form_str($this->_pt('Please provide a crypting key')); ?>" />
            <div id="phs_crypt_key_help" class="form-text"><?php echo $this->_pt('Min. 64 characters'); ?></div>
        </div>
        <div class="p-2">
            <?php
            // Keep this text in one string to be exported in language file in one string
            echo '<strong>'.$this->_pt('Note').'</strong>: ';
        echo $this->_pt('This crypt key will be used to encrypt plugins settings. Once you obtain the export file, make sure you keep this crypt key safe. It will be used when importing settings on other platforms. If you loose it, you will not be able to import any settings from exported file.');
        ?>
        </div>
        <div class="p-2">
            <?php
        $generation_url = 'https://passwordsgenerator.net/?length=128&symbols=0&numbers=1&lowercase=1&uppercase=1&similar=1&ambiguous=0&client=1&autoselect=1';
        // Keep this text in one string to be exported in language file in one string
        echo $this->_pt('You can generate safe crypt keys here: %s',
            '<a href="'.$generation_url.'" target="_blank">passwordsgenerator.net</a>');
        ?>
        </div>
        <div class="export_actions">
            <button type="button" id="do_export_plugin_settings_cancel" name="do_export_plugin_settings_cancel"
                    class="btn btn-default btn-medium" value="" onclick="phs_cancel_export_plugin_settings_dialogue()">
                <?php echo $this->_pt('Cancel'); ?>
            </button>
            <button type="button" id="do_export_plugin_settings_submit" name="do_export_plugin_settings_submit"
                    class="btn btn-primary btn-medium ignore_hidden_required" onclick="submit_plugins_export_functionality()"
                    value="<?php echo $this->_pt('Export Settings'); ?>" >
                <i class="fa fa-download"></i>
                <?php echo $this->_pt('Export Settings'); ?>
            </button>
        </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function _check_record_for_current_scope($record_arr, $scope_arr) : bool
    {
        return (empty($scope_arr['fstatus'])
                 || (int)$record_arr['status'] === (int)$scope_arr['fstatus'])
               && (empty($scope_arr['fplugin'])
                   || stripos($record_arr['name'], $scope_arr['fplugin']) !== false)
               && (empty($scope_arr['fvendor'])
                   || stripos($record_arr['vendor_name'], $scope_arr['fvendor']) !== false)
               && (!PHS::is_multi_tenant()
                   || empty($scope_arr['ftenant'])
                   || in_array((int)$scope_arr['ftenant'], $record_arr['tenants'] ?? [], true));
    }
}
