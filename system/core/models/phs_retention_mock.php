<?php

namespace phs\system\core\models;

use Generator;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\plugins\admin\libraries\Phs_Data_retention;

// Not an actual model... Used to generate required tables for data retention
class PHS_Model_Retention_mock extends PHS_Model
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?Phs_Data_retention $_retention_lib = null;

    private ?PHS_Model_Data_retention $_retention_model = null;

    private ?PHS_Model $_model_obj = null;

    private ?array $_retention_arr = null;

    public function get_model_version() : string
    {
        return '1.0.0';
    }

    public function get_table_names() : array
    {
        if ( empty($this->_retention_arr['table'])
             || !$this->_load_dependencies()) {
            return [];
        }

        return [$this->_retention_lib->get_data_retention_table_name_from_table($this->_retention_arr['table'])];
    }

    public function get_main_table_name() : string
    {
        if ( empty($this->_retention_arr['table'])
             || !$this->_load_dependencies()) {
            return '';
        }

        return $this->_retention_lib->get_data_retention_table_name_from_table($this->_retention_arr['table']);
    }

    public function fields_definition($params = false) : ?array
    {
        if (empty($this->_retention_arr['table'])
            || empty($this->_model_obj)
            || empty($params['table_name'])
            || !($table_name = $this->get_main_table_name())
            || $table_name !== $params['table_name'] ) {
            return null;
        }

        return $this->_model_obj->fields_definition(['table_name' => $this->_retention_arr['table']]);
    }

    public function inject_data_retention_model(PHS_Model $model, int | array $retention_data) : bool
    {
        $this->reset_error();

        if ( !$this->_load_dependencies() ) {
            return false;
        }

        if (empty($retention_data)
           || !($retention_arr = $this->_retention_model->data_to_array($retention_data))
            || $this->_retention_model->is_deleted($retention_arr)
            || empty($retention_arr['type'])
            || empty($retention_arr['table'])
            || empty($retention_arr['date_field'])
            || !$this->_retention_model->valid_type($retention_arr['type'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide a valid data retention record.'));

            return false;
        }

        $retention_arr['type'] = (int)$retention_arr['type'];

        $this->_model_obj = $model;
        $this->_retention_arr = $retention_arr;

        $this->_reset_tables_definition();

        if ($retention_arr['type'] === $this->_retention_model::TYPE_DELETE) {
            return true;
        }

        return $this->_validate_tables_definition()
               && ($table_name = $this->get_main_table_name())
               && $this->update_table(['table_name' => $table_name]);
    }

    public function move_data_for_retention(string $last_date) : ?array
    {
        $this->reset_error();

        if (empty($this->_model_obj) || empty($this->_retention_arr)
            || empty($this->_retention_arr['table'])
            || empty($this->_retention_arr['date_field'])
            || empty($this->_retention_arr['type'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('For data retention, you have to inject details first.'));

            return null;
        }

        if ( !($source_flow = $this->_model_obj->fetch_default_flow_params(['table_name' => $this->_retention_arr['table']]))
            || !($source_table_name = $this->_model_obj->get_flow_table_name($source_flow))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Error obtaining flow parameters for source.'));

            return null;
        }

        if ( !($field_definition = $this->_model_obj->check_column_exists($this->_retention_arr['date_field'], $source_flow))
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

        $return_arr = [];
        $return_arr['policy_type'] = (int)$this->_retention_arr['type'];
        $return_arr['source_table'] = $this->_retention_arr['table'];
        $return_arr['date_field'] = $this->_retention_arr['date_field'];
        $return_arr['last_date'] = $last_date;
        $return_arr['destination_table'] = '';
        $return_arr['total_rows'] = 0;
        $return_arr['affected_rows'] = 0;
        $return_arr['run_record'] = null;

        $query_condition = ' WHERE `'.$this->_retention_arr['date_field'].'` < \''.$last_date.'\'';

        if ( !($qid = db_query('SELECT COUNT(*) AS total_rows FROM `'.$source_table_name.'` '.$query_condition, $source_flow['db_connection']))
             || !($total_count = db_fetch_assoc($qid, $source_flow['db_connection']))) {
            $error_msg = self::_t('Error querying source table for data.');

            if ( !$this->_retention_model->start_retention_run(
                $this->_retention_arr, $last_date, 0, true, error: $error_msg
            ) ) {
                PHS_Logger::error('Error saving data retention run for record #'.$this->_retention_arr['id'].': '
                                  .$error_msg, $this->_admin_plugin::LOG_DATA_RETENTION);
            }

            $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

            return null;
        }

        if ( empty( $total_count['total_rows'] ) ) {
            if ( !($run_record = $this->_retention_model->start_retention_run(
                $this->_retention_arr, $last_date, 0, true
            )) ) {
                PHS_Logger::error('Error saving data retention run for record #'.$this->_retention_arr['id'].': '
                                  .'No records to move.', $this->_admin_plugin::LOG_DATA_RETENTION);
            }

            $return_arr['run_record'] = $run_record;

            return $return_arr;
        }

        $return_arr['total_rows'] = (int)$total_count['total_rows'];

        if ( !($run_record = $this->_retention_model->start_retention_run($this->_retention_arr, $last_date, $return_arr['total_rows'])) ) {
            PHS_Logger::error('Error saving data retention run for record #'.$this->_retention_arr['id'].': '
                              .$this->_retention_model->get_simple_error_message('Unknown error.'),
                $this->_admin_plugin::LOG_DATA_RETENTION);
        }

        $return_arr['run_record'] = $run_record;

        if ( $this->_retention_arr['type'] === $this->_retention_model::TYPE_DELETE ) {
            if ( !db_query('DELETE FROM `'.$source_table_name.'` '.$query_condition, $source_flow['db_connection']) ) {
                $error_msg = self::_t('Error deleting data from source table.');

                if ( !empty($run_record)
                    && !$this->_retention_model->update_retention_run(
                        $run_record, 0, true, $error_msg
                    ) ) {
                    PHS_Logger::error('Error saving data retention run for record RD#'.$this->_retention_arr['id'].', #'.$run_record['id'].': '
                                      .$error_msg.'; '
                                      .$this->_retention_model->get_simple_error_message('Unknown error.'),
                        $this->_admin_plugin::LOG_DATA_RETENTION);
                }

                $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);

                return null;
            }

            $return_arr['affected_rows'] = db_affected_rows($source_flow['db_connection']) ?: 0;

            if ( !empty($run_record)
                && !($new_run_record = $this->_retention_model->update_retention_run(
                    $run_record, $return_arr['affected_rows'], true, null
                )) ) {
                PHS_Logger::error('Error saving data retention run for record RD#'.$this->_retention_arr['id'].', #'.$run_record['id'].': '
                                  .$this->_retention_model->get_simple_error_message('Unknown error.'),
                    $this->_admin_plugin::LOG_DATA_RETENTION);
            }

            if (!empty($new_run_record)) {
                $return_arr['run_record'] = $new_run_record;
            }

            return $return_arr;
        }

        if ( !($destination_table = $this->get_main_table_name())
            || !($destination_flow = $this->fetch_default_flow_params(['table_name' => $destination_table]))
            || !($destination_table_name = $this->get_flow_table_name($destination_flow))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining flow parameters for destination.'));

            return null;
        }

        $new_run_record = null;
        if ( !empty($run_record)
            && !($new_run_record = $this->_retention_model->update_retention_run(
                $run_record, 0, destination_table: $destination_table
            )) ) {
            PHS_Logger::error('Error saving data retention run for record RD#'.$this->_retention_arr['id'].', #'.$run_record['id'].': '
                              .'Error updating destination table; '
                              .$this->_retention_model->get_simple_error_message('Unknown error.'),
                $this->_admin_plugin::LOG_DATA_RETENTION);
        }

        if ( !empty($new_run_record)) {
            $run_record = $new_run_record;
            $return_arr['run_record'] = $run_record;
        }

        $return_arr['destination_table'] = $destination_table;

        $source_id_key = $this->_model_obj->get_primary_key(['table_name' => $this->_retention_arr['table']]);

        $sql = 'SELECT * FROM `'.$source_table_name.'` '.$query_condition;

        $knti = 0;
        foreach ($this->_get_records_from_query_as_generator($sql, $source_flow['db_connection']) as $source_arr) {
            if (empty($source_arr)) {
                break;
            }

            $knti++;
            if (!empty($run_record)
                && !($knti % 50)
                && ($new_run_record = $this->_retention_model->update_retention_run($run_record, $knti))) {
                $run_record = $new_run_record;
            }

            // Make sure we don't duplicate destination
            if (empty($source_arr[$source_id_key])
                || (($check_qid = db_query('SELECT 1 as it_exists FROM `'.$destination_table_name.'` '
                                           .'WHERE `'.$source_id_key.'` = \''.$source_arr[$source_id_key].'\' LIMIT 0, 1',
                    $destination_flow['db_connection']))
                    && ($check_result = db_fetch_assoc($check_qid, $destination_flow['db_connection']))
                    && !empty($check_result['it_exists'])
                )
            ) {
                continue;
            }

            // Low level query
            if (!($sql = db_quick_insert($destination_table_name, $source_arr, $destination_flow['db_connection']))
                || !($item_id = db_query_insert($sql, $destination_flow['db_connection']))) {
                PHS_Logger::error('Error moving #'.$source_arr[$source_id_key].' from '.$this->_retention_arr['table'].' to '.$destination_table.'.',
                    $this->_admin_plugin::LOG_DATA_RETENTION);
            }
        }
        $return_arr['affected_rows'] = $knti;

        if (!empty($run_record)
            && ($new_run_record = $this->_retention_model->update_retention_run(
                $run_record, $knti, true, null))
        ) {
            $run_record = $new_run_record;
        }

        // Once we moved data to archive, delete from source
        if ( !db_query('DELETE FROM `'.$source_table_name.'` '.$query_condition, $source_flow['db_connection']) ) {
            PHS_Logger::error('Error deleting data from source for Policy#'.$this->_retention_arr['id'].', Run#'.($run_record['id'] ?? 'N/A').'.',
                $this->_admin_plugin::LOG_DATA_RETENTION);
        }

        if ( !empty($run_record)) {
            $return_arr['run_record'] = $run_record;
        }

        return $return_arr;
    }

    private function _get_records_from_query_as_generator(string $query, bool | string $db_connection, int $step = 500) : ?Generator
    {
        for ($offset = 0; ; $offset += $step) {
            if (!($qid = db_query($query.' LIMIT '.$offset.', '.$step, $db_connection))
                || !($records_count = db_num_rows($qid, $db_connection))) {
                return null;
            }

            while (($record_arr = db_fetch_assoc($qid, $db_connection))) {
                yield $record_arr;
            }

            if ($records_count < $step) {
                return null;
            }
        }
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
