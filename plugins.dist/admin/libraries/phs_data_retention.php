<?php

namespace phs\plugins\admin\libraries;

use phs\PHS;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Library;
use phs\system\core\models\PHS_Model_Data_retention;

class Phs_Data_retention extends PHS_Library
{
    public const TABLES_SUFFIX = '__dr_data';

    private ?PHS_Model_Data_retention $_data_retention_model = null;

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
            || !$this->_do_retention_migration($retention_arr)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                $this->_pt( 'Error running data retention migration.' ));

            return null;
        }

        return true;
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

        /** @var PHS_Plugin $plugin_obj */
        /** @var PHS_Model $model_obj */
        $plugin = $retention_arr['plugin'] ?? null;
        $plugin_obj = null;
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

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ( empty( $this->_data_retention_model )
            && !($this->_data_retention_model = PHS_Model_Data_retention::get_instance()) ) {
            $this->set_error( self::ERR_DEPENDENCIES,
                $this->_pt( 'Error loading required resources.' ) );

            return false;
        }

        return true;
    }
}
