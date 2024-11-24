<?php

namespace phs\plugins\foobar\models;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Model_Mongo;

class PHS_Model_Foobar_mongo extends PHS_Model_Mongo
{
    public const ERR_DB_JOB = 10000;

    public function get_model_version() : string
    {
        return '1.0.0';
    }

    public function get_table_names() : array
    {
        return ['testcol'];
    }

    public function get_main_table_name() : string
    {
        return 'testcol';
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'testcol':
                $return_arr = [
                    'anint' => [
                        'type'    => self::FTYPE_INTEGER,
                        'default' => 0,
                    ],
                    'fname' => [
                        'type'    => self::FTYPE_STRING,
                        'default' => '',
                    ],
                    'lname' => [
                        'type'    => self::FTYPE_STRING,
                        'default' => '',
                    ],
                    'tstamp' => [
                        'type'    => self::FTYPE_TIMESTAMP,
                        'default' => 0,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATE,
                    ],
                ];
                break;
        }

        return $return_arr;
    }
}
