<?php

namespace phs\plugins\accounts\contracts;

use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Contract;
use \phs\libraries\PHS_Model;

class PHS_Contract_Account_basic extends PHS_Contract
{
    /**
     * Returns an array with data nodes definition
     * @return array|bool
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    public function get_contract_data_definition()
    {
        return [
            'id' => [
                'title' => 'Account ID',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'key_type' => self::FROM_INSIDE,
            ],
            'nick' => [
                'title' => 'Account nickname',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
                'import_if_not_found' => false,
            ],
            'pass' => [
                'title' => 'Account password (only for updates)',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
                'key_type' => self::FROM_OUTSIDE,
                'import_if_not_found' => false,
            ],
            'email' => [
                'title' => 'Account email address',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
                'import_if_not_found' => false,
            ],
            'language' => [
                'title' => 'Account language (ISO 2 char language code)',
                'type' => PHS_Params::T_NOHTML,
                'default' => '',
                'import_if_not_found' => false,
            ],
            'status' => [
                'title' => 'Account status',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'import_if_not_found' => false,
            ],
            'level' => [
                'title' => 'Account level',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'import_if_not_found' => false,
            ],
        ];
    }
}
