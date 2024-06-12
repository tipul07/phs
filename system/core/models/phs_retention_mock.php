<?php

namespace phs\system\core\models;

use phs\libraries\PHS_Model;

// Not an actual model... Used to generate required tables for data retention
class PHS_Model_Retention_mock extends PHS_Model
{
    public function get_model_version() : string
    {
        return '1.0.0';
    }

    public function get_table_names() : array
    {
        return ['phs_data_retention', 'phs_data_retention_runs'];
    }

    public function get_main_table_name() : string
    {
        return 'phs_data_retention';
    }

    public function fields_definition($params = false)
    {
    }
}
