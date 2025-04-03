<?php
namespace phs\plugins\accounts\contracts;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Contract;
use phs\libraries\PHS_Model_Core_base;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Contract_Account extends PHS_Contract
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
            'email_verified' => [
                'title'               => 'Account email is verified? (0/1)',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'import_if_not_found' => false,
            ],
            'language' => [
                'title'               => 'Account language (ISO 2 char language code)',
                'type'                => PHS_Params::T_NOHTML,
                'default'             => '',
                'import_if_not_found' => false,
            ],
            'pass_generated' => [
                'title'               => 'Was password auto-generated? (0/1)',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'key_type'            => self::FROM_INSIDE,
                'import_if_not_found' => false,
            ],
            'added_by' => [
                'title'    => 'Account ID which created this account',
                'type'     => PHS_Params::T_INT,
                'default'  => 0,
                'key_type' => self::FROM_INSIDE,
            ],
            'details_id' => [
                'title'    => 'Account details ID (if available)',
                'type'     => PHS_Params::T_INT,
                'default'  => 0,
                'key_type' => self::FROM_INSIDE,
            ],
            'status' => [
                'title'               => 'Account status',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'import_if_not_found' => false,
            ],
            'status_date' => [
                'title'      => 'Account status change date',
                'type'       => PHS_Params::T_DATE,
                'type_extra' => ['format' => PHS_Model_Core_base::DATETIME_DB],
                'default'    => null,
                'key_type'   => self::FROM_INSIDE,
            ],
            'level' => [
                'title'               => 'Account level',
                'type'                => PHS_Params::T_INT,
                'default'             => 0,
                'import_if_not_found' => false,
            ],
            'deleted' => [
                'title'      => 'Account deletion date',
                'type'       => PHS_Params::T_DATE,
                'type_extra' => ['format' => PHS_Model_Core_base::DATETIME_DB],
                'default'    => null,
                'key_type'   => self::FROM_INSIDE,
            ],
            'last_pass_change' => [
                'title'      => 'Date of last account update',
                'type'       => PHS_Params::T_DATE,
                'type_extra' => ['format' => PHS_Model_Core_base::DATETIME_DB],
                'default'    => null,
                'key_type'   => self::FROM_INSIDE,
            ],
            'lastlog' => [
                'title'      => 'Date of last login',
                'type'       => PHS_Params::T_DATE,
                'type_extra' => ['format' => PHS_Model_Core_base::DATETIME_DB],
                'default'    => null,
                'key_type'   => self::FROM_INSIDE,
            ],
            'lastip' => [
                'title'    => 'Last login IP',
                'type'     => PHS_Params::T_NOHTML,
                'default'  => '',
                'key_type' => self::FROM_INSIDE,
            ],
            'cdate' => [
                'title'      => 'Date of account creation',
                'type'       => PHS_Params::T_DATE,
                'type_extra' => ['format' => PHS_Model_Core_base::DATETIME_DB],
                'default'    => null,
                'key_type'   => self::FROM_INSIDE,
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
