<?php
namespace phs\plugins\accounts\contracts;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Contract;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Contract_Account_basic extends PHS_Contract
{
    private ?PHS_Model_Accounts $_accounts_model = null;

    /**
     * @inheritdoc
     */
    public function get_parsing_data_model() : ?PHS_Model
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        return $this->_accounts_model;
    }

    /**
     * @inheritdoc
     */
    public function get_parsing_data_model_flow() : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        return $this->_accounts_model->fetch_default_flow_params(['table_name' => 'users']);
    }

    /**
     * @inheritdoc
     */
    public function get_contract_data_definition() : ?array
    {
        return [
            'id' => [
                'title'    => 'Account ID',
                'type'     => PHS_Params::T_INT,
                'default'  => 0,
                'key_type' => self::FROM_INSIDE,
            ],
            'nick' => [
                'title'               => 'Account nickname',
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'import_if_not_found' => false,
            ],
            'pass' => [
                'title'               => 'Account password (only for updates)',
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'key_type'            => self::FROM_OUTSIDE,
                'import_if_not_found' => false,
            ],
            'email' => [
                'title'               => 'Account email address',
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'import_if_not_found' => false,
            ],
            'language' => [
                'title'               => 'Account language (ISO 2 char language code)',
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'import_if_not_found' => false,
            ],
            'status' => [
                'title'               => 'Account status',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'import_if_not_found' => false,
            ],
            'level' => [
                'title'               => 'Account level',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'import_if_not_found' => false,
            ],
            'roles' => [
                'title'                 => 'List of roles assigned to user',
                'description'           => 'An array of role slugs assigned to user',
                'default'               => [],
                'recurring_key_type'    => PHS_Params::T_INT,
                'recurring_node'        => true,
                'recurring_scalar_node' => true,
                'key_type'              => self::FROM_INSIDE,
                'type'                  => PHS_Params::T_NOHTML,
            ],
            'roles_units' => [
                'title'                 => 'List of role units assigned to user',
                'description'           => 'An array of role unit slugs assigned to user',
                'default'               => [],
                'recurring_key_type'    => PHS_Params::T_INT,
                'recurring_node'        => true,
                'recurring_scalar_node' => true,
                'key_type'              => self::FROM_INSIDE,
                'type'                  => PHS_Params::T_NOHTML,
            ],
        ];
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (!$this->_accounts_model
            && !($this->_accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
