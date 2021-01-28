<?php

namespace phs\plugins\accounts\contracts;

use \phs\PHS;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Contract;

class PHS_Contract_Account_basic extends PHS_Contract
{
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts|null $_accounts_model */
    private $_accounts_model = null;

    private function _load_dependencies()
    {
        if( !$this->_accounts_model
         && !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading required resources for accounts contract.' ) );
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function get_parsing_data_model()
    {
        if( !$this->_load_dependencies() )
            return false;

        return $this->_accounts_model;
    }

    /**
     * @inheritdoc
     */
    public function get_parsing_data_model_flow()
    {
        if( !$this->_load_dependencies() )
            return false;

        return $this->_accounts_model->fetch_default_flow_params( [ 'table_name' => 'users' ] );
    }

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
            'roles' => [
                'title' => 'List of roles assigned to user',
                'description' => 'An array of role slugs assigned to user',
                'default' => [],
                'recurring_key_type' => PHS_Params::T_INT,
                'recurring_node' => true,
                'recurring_scalar_node' => true,
                'key_type' => self::FROM_INSIDE,
                'type' => PHS_Params::T_NOHTML,
            ],
            'roles_units' => [
                'title' => 'List of role units assigned to user',
                'description' => 'An array of role unit slugs assigned to user',
                'default' => [],
                'recurring_key_type' => PHS_Params::T_INT,
                'recurring_node' => true,
                'recurring_scalar_node' => true,
                'key_type' => self::FROM_INSIDE,
                'type' => PHS_Params::T_NOHTML,
            ],
        ];
    }
}
