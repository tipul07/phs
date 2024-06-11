<?php

namespace phs\plugins\admin\actions\retention;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
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
    public function execute(): ?array
    {
        PHS::page_settings('page_title', $this->_pt('Add Data Retention Policy'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Model_Data_retention $retention_model */
        /** @var PHS_Model_Plugins $plugins_model */
        if (!($retention_model = PHS_Model_Data_retention::get_instance())
            || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!can(PHS_Roles::ROLEU_MANAGE_DATA_RETENTION)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $plugin = PHS_Params::_p('plugin', PHS_Params::T_NOHTML) ?: null;
        $model = PHS_Params::_p('model', PHS_Params::T_NOHTML);
        $table = PHS_Params::_p('table', PHS_Params::T_NOHTML);
        $data_field = PHS_Params::_p('data_field', PHS_Params::T_NOHTML);
        $type = PHS_Params::_p('type', PHS_Params::T_INT) ?? 0;
        $retention_type = PHS_Params::_p('retention_type', PHS_Params::T_NOHTML);
        $retention_count = PHS_Params::_p('retention_count', PHS_Params::T_INT) ?? 0;

        $do_submit = PHS_Params::_p('do_submit');

        $plugins_arr = array_merge([PHS_Instantiable::CORE_PLUGIN => null], $plugins_model->cache_all_dir_details() ?: []);
        $models_arr = $this->_get_models_for_plugin($plugin);
        $tables_arr = $this->_get_tables_for_model($model, $plugin);
        $fields_arr = $this->_get_date_fields_for_table($table, $model, $plugin);

        $plugin_obj = $plugins_arr[$plugin] ?? null;
        $model_obj = empty($model) ? null : PHS::load_model($model, $plugin);

        if( (!empty($plugin)
             && $plugin !== PHS_Instantiable::CORE_PLUGIN
             && empty($plugins_arr[$plugin]))
            || (!empty($model)
                && !in_array($model, $models_arr, true))
            || (!empty($table)
                && !in_array($table, $tables_arr, true))
        ) {
            $plugin_obj = null;
            $model_obj = null;
            $plugin = null;
            $model = '';
            $table = '';
            $data_field = '';

            if( isset($do_submit )) {
                unset($do_submit);
            }

            PHS_Notifications::add_warning_notice(
                self::_t('Provided details are not valid. Please try again.'));
        }

        if (!empty($do_submit)) {
            $insert_arr = [];
            $insert_arr['added_by_uid'] = $current_user['id'];
            $insert_arr['uid'] = $uid;
            if ($is_multi_tenant) {
                $insert_arr['tenant_id'] = $tenant_id;
            }
            $insert_arr['title'] = $title;
            $insert_arr['api_key'] = $api_key;
            $insert_arr['api_secret'] = $api_secret;
            $insert_arr['allow_sw'] = $allow_sw;
            $insert_arr['allowed_methods'] = (!empty($allowed_methods) ? implode(',', $allowed_methods) : null);
            $insert_arr['denied_methods'] = (!empty($denied_methods) ? implode(',', $denied_methods) : null);

            if ($retention_model->insert(['fields' => $insert_arr])) {
                PHS_Notifications::add_success_notice($this->_pt('Data retention policy details saved...'));

                return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'retention'], ['policy_added' => 1]);
            }

            PHS_Notifications::add_error_notice(
                $retention_model->get_simple_error_message(
                    $this->_pt('Error saving details to database. Please try again.')));
        }

        $data = [
            'plugin'              => $plugin,
            'model'        => $model,
            'table' => $table,
            'data_field'            => $data_field,
            'type'          => $type,
            'retention_type'       => $retention_type,
            'retention_count'         => $retention_count,

            'plugins_arr' => $plugins_arr,
            'models_arr' => $models_arr,
            'tables_arr' => $tables_arr,

            'plugin_obj'                   => $plugin_obj,
            'model_obj'                   => $model_obj,
        ];

        return $this->quick_render_template('retention/add', $data);
    }

    private function _get_models_for_plugin(?string $plugin): array
    {
        if(empty($plugin)) {
            return [];
        }

        $models_arr = [];
        /** @var PHS_Plugin $plugin_obj */
        if ($plugin === PHS_Instantiable::CORE_PLUGIN) {
            $models_arr = PHS::get_core_models();
        } elseif(($plugin_obj = PHS::load_plugin($plugin))) {
            $models_arr = $plugin_obj->get_models();
        }

        return $models_arr;
    }

    private function _get_tables_for_model(?string $model, ?string $plugin): array
    {
        if(empty($model)) {
            return [];
        }

        $tables_arr = [];
        /** @var \phs\libraries\PHS_Model $model_obj */
        if (($model_obj = PHS::load_model($model, $plugin))) {
            $tables_arr = $model_obj->get_table_names();
        }

        return $tables_arr;
    }
}
