<?php
namespace phs\plugins\foobar\models;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;

class PHS_Model_Foobar extends PHS_Model
{
    public const ERR_DB_JOB = 10000;

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.10';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return ['foobar'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'foobar';
    }

    public function get_settings_structure()
    {
        return [
            'minutes_to_stall' => [
                'display_name' => 'Minutes to stall',
                'display_hint' => 'After how many minutes should we consider a job as stalling',
                'type'         => PHS_Params::T_INT,
                'default'      => 15,
            ],
            'another_foobar_var' => [
                'display_name' => 'Just a foobar value',
                'display_hint' => 'Bla bla...',
                'type'         => PHS_Params::T_INT,
                'default'      => 2,
                'editable'     => false,
            ],
            'check_update' => [
                'display_name' => 'Just a foobar value',
                'display_hint' => 'Bla bla...',
                'type'         => PHS_Params::T_INT,
                'default'      => 2,
                'editable'     => false,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false)
    {
        // $params should be flow parameters...
        if (empty($params) || !is_array($params)
         || empty($params['table_name'])) {
            return false;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'foobar':
                $return_arr = [
                    self::T_DETAILS_KEY => [
                        'engine'  => 'InnoDB',
                        'charset' => 'utf8',
                        'collate' => 'utf8_general_ci',
                        'comment' => 'A foobar model',
                    ],

                    self::EXTRA_INDEXES_KEY => [
                        'pid_route' => [
                            'unique' => true,
                            'fields' => ['pid', 'route', 'last_error'],
                        ],
                    ],

                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'pid' => [
                        'type'    => self::FTYPE_INT,
                        'default' => 0,
                    ],
                    'unspid' => [
                        'type'     => self::FTYPE_INT,
                        'default'  => 0,
                        'unsigned' => true,
                    ],
                    'bigintf' => [
                        'type'    => self::FTYPE_BIGINT,
                        'default' => 0,
                    ],
                    'decfield' => [
                        'type'    => self::FTYPE_DECIMAL,
                        'length'  => '5,3',
                        'default' => 0,
                    ],
                    'route' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 50,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'params' => [
                        'type'     => self::FTYPE_TEXT,
                        'nullable' => true,
                    ],
                    'last_error' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'last_action' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'timed_action' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }
}
