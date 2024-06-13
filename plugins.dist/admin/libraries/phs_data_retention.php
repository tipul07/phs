<?php

namespace phs\plugins\admin\libraries;

use phs\PHS;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\system\core\models\PHS_Model_Data_retention;
use phs\system\core\models\PHS_Model_Retention_mock;

class Phs_Data_retention extends PHS_Library
{
    public const TABLES_SUFFIX = '__dr_data';

    private ?PHS_Model_Data_retention $_data_retention_model = null;

    private ?PHS_Model_Retention_mock $_retention_mock_model = null;

    public function get_data_retention_table_name_from_table(string $table_name) : string
    {
        return $table_name.self::TABLES_SUFFIX;
    }

    public function run_data_retention(int | array $retention_data) : ?array
    {
        if ( !$this->_load_dependencies() ) {
            return null;
        }

        if ( empty($retention_data)
            || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
            || $this->_data_retention_model->is_deleted($retention_arr) ) {
            $this->set_error( self::ERR_PARAMETERS,
                $this->_pt( 'Data retention not found in database.' ) );

            return null;
        }

        if ( !$this->_check_retention_requirements($retention_arr)
            || !($migration_result = $this->_do_retention_migration($retention_arr))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                $this->_pt( 'Error running data retention migration.' ));

            return null;
        }

        return $migration_result;
    }

    private function _check_retention_requirements(int | array $retention_data) : bool
    {
        if ( !$this->_load_dependencies() ) {
            return false;
        }

        if ( empty($retention_data)
            || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
            || $this->_data_retention_model->is_deleted($retention_arr) ) {
            $this->set_error( self::ERR_PARAMETERS,
                $this->_pt( 'Data retention not found in database.' ) );

            return false;
        }

        if ( !$this->_check_retention_requirements_for_existing_table($retention_arr) ) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Error while checking data retention existing table.'));

            return false;
        }

        if ( !$this->_check_retention_requirements_for_destination_table($retention_arr) ) {
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
        if ( empty($retention_arr['model'])
            || empty($retention_arr['table'])
            || empty($retention_arr['data_field'])
            || (!empty($plugin)
                && (!($plugin_obj = PHS::load_plugin($plugin))
                    || !$plugin_obj->plugin_active()))
            || !($model_obj = PHS::load_model($retention_arr['model'], $plugin))
            || !($field_definition = $model_obj->check_column_exists($retention_arr['data_field'], ['table_name' => $retention_arr['table']]))
            || empty($field_definition['type'])
            || !in_array($field_definition['type'], [$model_obj::FTYPE_DATE, $model_obj::FTYPE_DATETIME], true)
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

        if ( empty($retention_arr['model'])
            || empty($retention_arr['table'])
            || empty($retention_arr['data_field']) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention record is invalid.'));

            return false;
        }

        if (!($model_obj = PHS::load_model($retention_arr['model'], $retention_arr['plugin'] ?? null))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error loading data retention model.'));

            return false;
        }

        if ( !$this->_retention_mock_model->inject_data_retention_model(
            $model_obj, $retention_arr['table'], $retention_arr['data_field'], $retention_arr['type']
        ) ) {
            $this->copy_or_set_error($this->_retention_mock_model,
                self::ERR_PARAMETERS, self::_t('Error setting up data retention model.'));

            return false;
        }

        return true;
    }

    private function _do_retention_migration(int | array $retention_data) : ?array
    {
        if ( !$this->_load_dependencies() ) {
            return null;
        }

        if ( empty($retention_data)
             || !($retention_arr = $this->_data_retention_model->data_to_array($retention_data))
             || $this->_data_retention_model->is_deleted($retention_arr) ) {
            $this->set_error( self::ERR_PARAMETERS,
                $this->_pt( 'Data retention not found in database.' ) );

            return null;
        }

        if ( !($interval = $this->_data_retention_model->parse_retention_interval_from_retention_data($retention_arr))
            || !($retention_time = $this->_data_retention_model->generate_retention_interval_time($interval))
            || !($retention_date = date($this->_data_retention_model::DATE_DB, $retention_time))
            || !$this->_data_retention_model->valid_type($retention_arr['type'])
        ) {
            $this->copy_or_set_error( $this->_data_retention_model,
                self::ERR_PARAMETERS, $this->_pt( 'Data retention details are invalid.' ) );

            return null;
        }

        if ( !($retention_result = $this->_retention_mock_model->move_data_for_retention($retention_date)) ) {
            $this->copy_or_set_error( $this->_data_retention_model,
                self::ERR_PARAMETERS, $this->_pt( 'Error moving records for data retention policy.' ) );

            return null;
        }

        return $retention_result;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ( (empty( $this->_data_retention_model )
              && !($this->_data_retention_model = PHS_Model_Data_retention::get_instance()))
             || (empty( $this->_retention_mock_model )
                 && !($this->_retention_mock_model = PHS_Model_Retention_mock::get_instance()))
        ) {
            $this->set_error( self::ERR_DEPENDENCIES,
                $this->_pt( 'Error loading required resources.' ) );

            return false;
        }

        return true;
    }
}
