<?php

namespace phs\system\core\models;

use phs\libraries\PHS_Model;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\admin\libraries\Phs_Data_retention;

// Not an actual model... Used to generate required tables for data retention
class PHS_Model_Retention_mock extends PHS_Model
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?Phs_Data_retention $_retention_lib = null;

    private ?PHS_Model_Data_retention $_retention_model = null;

    private ?PHS_Model $_model_obj = null;

    private string $_table_name = '';

    private string $_data_field = '';

    private int $_policy_type = 0;

    public function get_model_version() : string
    {
        return '1.0.0';
    }

    public function get_table_names() : array
    {
        if ( !$this->_load_dependencies()
            || empty($this->_table_name)) {
            return [];
        }

        return [$this->_retention_lib->get_data_retention_table_name_from_table($this->_table_name)];
    }

    public function get_main_table_name() : string
    {
        if ( !$this->_load_dependencies()
            || empty($this->_table_name)) {
            return '';
        }

        return $this->_retention_lib->get_data_retention_table_name_from_table($this->_table_name);
    }

    public function inject_data_retention_model(PHS_Model $model, string $table_name, string $data_field, int $policy_type) : bool
    {
        $this->reset_error();

        if ( !$this->_load_dependencies() ) {
            return false;
        }

        if (empty($policy_type)
           || !$this->_retention_model->valid_type($policy_type)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid data retention policy type.'));

            return false;
        }

        $this->_model_obj = $model;
        $this->_table_name = $table_name;
        $this->_data_field = $data_field;
        $this->_policy_type = $policy_type;

        $this->_reset_tables_definition();

        if ($policy_type === $this->_retention_model::TYPE_DELETE) {
            return true;
        }

        return $this->_validate_tables_definition()
               && ($table_name = $this->get_main_table_name())
               && $this->update_table(['table_name' => $table_name]);
    }

    public function move_data_for_retention(string $last_date) : ?array
    {
        $this->reset_error();

        if (empty($this->_model_obj) || empty($this->_table_name)
           || empty($this->_data_field) || empty($this->_policy_type)) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('For data retention, you have to inject details first.'));

            return null;
        }

        if ( !($source_flow = $this->_model_obj->fetch_default_flow_params(['table_name' => $this->_table_name]))
            || !($source_table_name = $this->_model_obj->get_flow_table_name($source_flow))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error obtaining flow parameters for source.'));

            return null;
        }

        if ( !($field_definition = $this->_model_obj->check_column_exists($this->_data_field, $source_flow))
             || empty($field_definition['type'])
             || !in_array((int)$field_definition['type'], [self::FTYPE_DATE, self::FTYPE_DATETIME], true)
        ) {
            $this->set_error(self::ERR_EDIT, self::_t('Provided field is not a date or datetime field for data retention source.'));

            return null;
        }

        if (empty($last_date)
         || !($last_date_time = parse_db_date($last_date)) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid last date provided.'));

            return null;
        }

        $last_date = date(self::DATE_DB, $last_date_time);
        if ( (int)$field_definition['type'] === self::FTYPE_DATETIME ) {
            $last_date .= ' 00:00:00';
        }

        $query_condition = ' WHERE `'.$this->_data_field.'` < \''.$last_date.'\'';

        if ( $this->_policy_type === $this->_retention_model::TYPE_DELETE ) {
            if ( !db_query('DELETE FROM `'.$source_table_name.'` '.$query_condition, $source_flow['db_connection']) ) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error deleting data from source table.'));

                return null;
            }

            return [
                'affected_rows' => db_affected_rows($source_flow['db_connection']) ?: 0,
            ];
        }

        if ( !($destination_table = $this->get_main_table_name())
            || !($destination_flow = $this->fetch_default_flow_params(['table_name' => $destination_table]))
            || !($destination_table_name = $this->get_flow_table_name($destination_flow))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining flow parameters for destination.'));

            return null;
        }

        if ( !($qid = db_query('SELECT * FROM `'.$source_table_name.'` '.$query_condition, $source_flow['db_connection'])) ) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error selecting data from source table.'));

            return null;
        }

        var_dump(@mysqli_num_rows($qid));
        exit;

        return [
            'affected_rows' => db_affected_rows($source_flow['db_connection']) ?: 0,
        ];
    }

    public function fields_definition($params = false) : ?array
    {
        if (empty($this->_table_name)
           || empty($this->_model_obj)
           || empty($params['table_name'])
           || !($table_name = $this->get_main_table_name())
           || $table_name !== $params['table_name'] ) {
            return null;
        }

        return $this->_model_obj->fields_definition(['table_name' => $this->_table_name]);
    }

    private function _reset_tables_definition() : void
    {
        $this->_definition = [];
        $this->model_tables_arr = [];
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ( (empty($this->_retention_model)
             && !($this->_retention_model = PHS_Model_Data_retention::get_instance()))
            || (empty($this->_admin_plugin)
                && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
            || !($this->_retention_lib = $this->_admin_plugin->get_data_retention_instance())
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
