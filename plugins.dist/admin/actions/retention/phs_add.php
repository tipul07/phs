<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Data_retention;

class PHS_Action_Add extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute() : ?array
    {
        PHS::page_settings('page_title', $this->_pt('Add Data Retention Policy'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Data_retention $retention_model */
        /** @var PHS_Model_Plugins $plugins_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($retention_model = PHS_Model_Data_retention::get_instance())
            || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_data_retention()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $plugin = PHS_Params::_p('plugin', PHS_Params::T_NOHTML) ?: null;
        $model = PHS_Params::_p('model', PHS_Params::T_NOHTML);
        $table = PHS_Params::_p('table', PHS_Params::T_NOHTML);
        $date_field = PHS_Params::_p('date_field', PHS_Params::T_NOHTML);
        $type = PHS_Params::_p('type', PHS_Params::T_INT) ?? 0;
        $retention_interval = PHS_Params::_p('retention_interval', PHS_Params::T_NOHTML);
        $retention_count = PHS_Params::_p('retention_count', PHS_Params::T_INT) ?? 0;

        $do_submit = PHS_Params::_p('do_submit');

        $plugins_arr = array_merge([PHS_Instantiable::CORE_PLUGIN => null], $plugins_model->cache_all_dir_details() ?: []);

        $plugin_obj = ($plugin && !empty($plugins_arr[$plugin])) ? $plugins_arr[$plugin] : null;
        $model_obj = empty($model) ? null : PHS::load_model($model, $plugin);

        $models_arr = $plugin ? $this->_get_models_for_plugin($plugin_obj) : [];
        $tables_arr = $model_obj ? $model_obj->get_table_names() : [];
        $fields_arr = ($model_obj !== null && !empty($table))
            ? ($retention_model->get_model_date_fields($model_obj, $table) ?: [])
            : [];

        $types_arr = $retention_model->get_types_as_key_val();
        $intervals_arr = $retention_model->get_intervals_as_key_val();

        if ((!empty($plugin)
             && $plugin !== PHS_Instantiable::CORE_PLUGIN
             && empty($plugins_arr[$plugin]))
            || (!empty($model)
                && !in_array($model, $models_arr, true))
            || (!empty($table)
                && !in_array($table, $tables_arr, true))
            || (!empty($date_field)
                && !in_array($date_field, $fields_arr, true))
        ) {
            $plugin_obj = null;
            $model_obj = null;
            $plugin = null;
            $model = '';
            $table = '';
            $date_field = '';

            if (isset($do_submit)) {
                unset($do_submit);
            }

            PHS_Notifications::add_warning_notice(
                self::_t('Provided details are not valid. Please try again.'));
        }

        if (!empty($do_submit)) {
            if (!($retention = $retention_model->generate_retention_field(['count' => $retention_count, 'interval' => $retention_interval]))) {
                PHS_Notifications::add_error_notice(self::_t('Invalid retention interval. Please try again.'));
            } else {
                $insert_arr = [];
                $insert_arr['added_by_uid'] = $current_user['id'];
                $insert_arr['plugin'] = $plugin !== PHS_Instantiable::CORE_PLUGIN ? $plugin : null;
                $insert_arr['model'] = $model;
                $insert_arr['table'] = $table;
                $insert_arr['date_field'] = $date_field;
                $insert_arr['type'] = $type;
                $insert_arr['retention'] = $retention;

                if ($retention_model->insert(['fields' => $insert_arr])) {
                    PHS_Notifications::add_success_notice($this->_pt('Data retention policy details saved...'));

                    return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'retention'], ['policy_added' => 1]);
                }

                PHS_Notifications::add_error_notice(
                    $retention_model->get_simple_error_message(
                        $this->_pt('Error saving details to database. Please try again.')));
            }
        }

        $data = [
            'plugin'             => $plugin,
            'model'              => $model,
            'table'              => $table,
            'date_field'         => $date_field,
            'type'               => $type,
            'retention_interval' => $retention_interval,
            'retention_count'    => $retention_count,

            'plugins_arr'   => $plugins_arr,
            'models_arr'    => $models_arr,
            'tables_arr'    => $tables_arr,
            'fields_arr'    => $fields_arr,
            'types_arr'     => $types_arr,
            'intervals_arr' => $intervals_arr,

            'plugin_obj' => $plugin_obj,
            'model_obj'  => $model_obj,
        ];

        return $this->quick_render_template('retention/add', $data);
    }

    private function _get_models_for_plugin(?PHS_Plugin $plugin_obj) : array
    {
        if (!$plugin_obj) {
            return PHS::get_core_models();
        }

        return $plugin_obj->get_models();
    }
}
