<?php

namespace phs\plugins\admin\libraries;

use phs\PHS;
use phs\PHS_Agent;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Data_retention;
use phs\system\core\models\PHS_Model_Retention_mock;

class Phs_Data_retention extends PHS_Library
{
    public const TABLES_SUFFIX = '__dr_data';

    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Model_Data_retention $_data_retention_model = null;

    private ?PHS_Model_Retention_mock $_retention_mock_model = null;

    public function get_data_retention_table_name_from_table(string $table_name) : string
    {
        return $table_name.self::TABLES_SUFFIX;
    }

    public function run_data_retention(int | array $retention_data, array $job_extra = []) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($retention_data)
            || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
            || !$this->_data_retention_model->is_active($retention_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid data retention policy.'));

            return false;
        }

        return $this->run_data_retention_for_list([$retention_arr], $job_extra);
    }

    public function run_data_retention_for_list(array $retention_list, array $job_extra = []) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        $retention_ids = [];
        foreach ($retention_list as $retention_data) {
            if (empty($retention_data)
                || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data, ['table_name' => 'phs_data_retention']))
                || !$this->_data_retention_model->is_active($retention_arr)) {
                continue;
            }

            $retention_ids[] = (int)$retention_arr['id'];
        }

        if (empty($retention_ids)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid data retention policy ids.'));

            return false;
        }

        $job_extra['same_thread_if_bg'] = !empty($job_extra['same_thread_if_bg']);

        $job_route = ['plugin' => 'admin', 'controller' => 'index_bg', 'action' => 'run_retention_bg', 'action_dir' => 'retention'];
        $job_params = ['retention_ids' => $retention_ids];

        if (!PHS_Bg_jobs::run($job_route, $job_params, $job_extra)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_pt('Error launching background job for data retention run.'));

            return false;
        }

        return true;
    }

    public function run_data_retention_for_list_bg(array $retention_list) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $retentions_arr = [];
        foreach ($retention_list as $retention_data) {
            if (empty($retention_data)
                || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data, ['table_name' => 'phs_data_retention']))
                || !$this->_data_retention_model->is_active($retention_arr)) {
                continue;
            }

            $retentions_arr[] = $retention_arr;
        }

        if (empty($retentions_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid retention policy list.'));

            return null;
        }

        PHS_Logger::notice('[START] Start running '.count($retentions_arr).' data retention policies.',
            $this->_admin_plugin::LOG_DATA_RETENTION);

        $return_arr = [
            'total_policies' => count($retentions_arr),
            'error_policies' => 0,
            'total_rows'     => 0,
            'affected_rows'  => 0,
        ];
        foreach ($retentions_arr as $retention_arr) {
            if (!($run_result = $this->run_data_retention_bg($retention_arr))) {
                $return_arr['error_policies']++;

                PHS_Logger::notice('[ERROR] Error running data retention policy #'.$retention_arr['id'].': '
                                   .$this->get_simple_error_message($this->_pt('Unknown error.')),
                    $this->_admin_plugin::LOG_DATA_RETENTION);

                continue;
            }

            PHS_Logger::notice('[END] Finished running data retention policy #'.$retention_arr['id'].': '
                               .'from: '.$run_result['source_table'].', '
                               .'to: '.($run_result['destination_table'] ?: 'N/A').', '
                               .'action: '.($this->_data_retention_model->get_type_title($run_result['policy_type']) ?: 'N/A').', '
                               .'affected rows: '.$run_result['affected_rows'].'/'.$run_result['total_rows'],
                $this->_admin_plugin::LOG_DATA_RETENTION);

            $return_arr['total_rows'] += ($run_result['total_rows'] ?? 0);
            $return_arr['affected_rows'] += ($run_result['affected_rows'] ?? 0);
        }

        PHS_Logger::notice('[END] Finished running '.count($retentions_arr).' data retention policies.',
            $this->_admin_plugin::LOG_DATA_RETENTION);

        return $return_arr;
    }

    public function run_data_retention_bg(int | array $retention_data) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (empty($retention_data)
            || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
            || !$this->_data_retention_model->is_active($retention_arr)) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Data retention not found in database.'));

            return null;
        }

        if (!$this->_check_retention_requirements($retention_arr)
            || !($migration_result = $this->_do_retention_migration($retention_arr))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                $this->_pt('Error running data retention migration.'));

            return null;
        }

        return $migration_result;
    }

    private function _check_retention_requirements(int | array $retention_data) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($retention_data)
            || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
            || !$this->_data_retention_model->is_active($retention_arr)) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Data retention not found in database.'));

            return false;
        }

        if (!$this->_check_retention_requirements_for_existing_table($retention_arr)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error while checking data retention existing table.'));

            return false;
        }

        if (!$this->_check_retention_requirements_for_destination_table($retention_arr)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error while checking data retention destination table.'));

            return false;
        }

        return true;
    }

    private function _check_retention_requirements_for_existing_table(array $retention_arr) : bool
    {
        /** @var PHS_Plugin $plugin_obj */
        /** @var PHS_Model $model_obj */
        $plugin = $retention_arr['plugin'] ?? null;
        if (empty($retention_arr['model'])
             || empty($retention_arr['table'])
             || empty($retention_arr['date_field'])
             || (!empty($plugin)
                 && (!($plugin_obj = PHS::load_plugin($plugin))
                     || !$plugin_obj->plugin_active()))
             || !($model_obj = PHS::load_model($retention_arr['model'], $plugin))
             || !($flow_arr = $model_obj->fetch_default_flow_params(['table_name' => $retention_arr['table']]))
             || !$model_obj->set_maintenance_database_credentials($flow_arr)
             || !($field_definition = $model_obj->check_column_exists($retention_arr['date_field'], $flow_arr))
             || empty($field_definition['type'])
             || !in_array($field_definition['type'], [$model_obj::FTYPE_DATE, $model_obj::FTYPE_DATETIME], true)
             || !$model_obj->reset_maintenance_database_credentials($flow_arr)
        ) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Data retention configuration is invalid.'));

            return false;
        }

        return true;
    }

    private function _check_retention_requirements_for_destination_table(array $retention_arr) : bool
    {
        $this->reset_error();

        if (empty($retention_arr['model'])
            || empty($retention_arr['table'])
            || empty($retention_arr['date_field'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention record is invalid.'));

            return false;
        }

        if (!($model_obj = PHS::load_model($retention_arr['model'], $retention_arr['plugin'] ?? null))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error loading data retention model.'));

            return false;
        }

        if (!$this->_retention_mock_model->inject_data_retention_model($model_obj, $retention_arr)) {
            $this->copy_or_set_error($this->_retention_mock_model,
                self::ERR_PARAMETERS, self::_t('Error setting up data retention model.'));

            return false;
        }

        return true;
    }

    private function _do_retention_migration(int | array $retention_data) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (empty($retention_data)
             || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
             || !$this->_data_retention_model->is_active($retention_arr)) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Data retention not found in database.'));

            return null;
        }

        if (!($interval = $this->_data_retention_model->parse_retention_interval_from_retention_data($retention_arr))
            || !($retention_time = $this->_data_retention_model->generate_retention_interval_time($interval))
            || !($retention_date = date($this->_data_retention_model::DATE_DB, $retention_time))
            || !$this->_data_retention_model->valid_type($retention_arr['type'])
        ) {
            $this->copy_or_set_error($this->_data_retention_model,
                self::ERR_PARAMETERS, $this->_pt('Data retention details are invalid.'));

            return null;
        }

        if (!($retention_result = $this->_retention_mock_model->move_data_for_retention($retention_date))) {
            $this->copy_or_set_error($this->_data_retention_model,
                self::ERR_PARAMETERS, $this->_pt('Error moving records for data retention policy.'));

            return null;
        }

        return $retention_result;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ((empty($this->_admin_plugin)
              && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
             || (empty($this->_data_retention_model)
                 && !($this->_data_retention_model = PHS_Model_Data_retention::get_instance()))
             || (empty($this->_retention_mock_model)
                 && !($this->_retention_mock_model = PHS_Model_Retention_mock::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES,
                $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
