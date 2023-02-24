<?php
namespace phs\system\core\models;

use phs\PHS;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\traits\PHS_Model_Trait_statuses;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Model_Tenants extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_DELETED = 3;

    protected static array $STATUSES_ARR = [
        self::STATUS_ACTIVE   => ['title' => 'Active'],
        self::STATUS_INACTIVE => ['title' => 'Inactive'],
        self::STATUS_DELETED  => ['title' => 'Deleted'],
    ];

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.3';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return ['phs_tenants'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'phs_tenants';
    }

    public function is_active($record_data)
    {
        if (!($record_arr = $this->data_to_array($record_data))
         || (int)$record_arr['status'] !== self::STATUS_ACTIVE) {
            return false;
        }

        return $record_arr;
    }

    public function is_inactive($record_data)
    {
        if (!($record_arr = $this->data_to_array($record_data))
         || (int)$record_arr['status'] !== self::STATUS_INACTIVE) {
            return false;
        }

        return $record_arr;
    }

    public function is_deleted($record_data)
    {
        if (!($record_arr = $this->data_to_array($record_data))
         || (int)$record_arr['status'] !== self::STATUS_DELETED) {
            return false;
        }

        return $record_arr;
    }

    public function is_default_tenant($record_data)
    {
        if (!($record_arr = $this->data_to_array($record_data))
         || empty($record_arr['is_default'])) {
            return false;
        }

        return $record_arr;
    }

    public function act_activate($record_data)
    {
        if (empty($record_data)
         || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return false;
        }

        if ($this->is_active($record_arr)) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit($record_arr, $edit_params);
    }

    public function act_inactivate($record_data)
    {
        if (empty($record_data)
         || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return false;
        }

        if ($this->is_inactive($record_arr)) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_INACTIVE;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit($record_arr, $edit_params);
    }

    public function act_delete($record_data)
    {
        $this->reset_error();

        if (empty($record_data)
         || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_DELETE, $this->_pt('Tenant details not found in database.'));

            return false;
        }

        if ($this->is_deleted($record_arr)) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params_arr = [];
        $edit_params_arr['fields'] = $edit_arr;

        return $this->edit($record_arr, $edit_params_arr);
    }

    public function can_user_edit($record_data, $account_data)
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (empty($record_data) || empty($account_data)
         || !PHS::is_multi_tenant()
         || !($tenant_arr = $this->data_to_array($record_data))
         || $this->is_deleted($tenant_arr)
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($account_arr = $accounts_model->data_to_array($account_data))
         || !can(PHS_Roles::ROLEU_TENANTS_MANAGE, null, $account_arr)) {
            return false;
        }

        $return_arr = [];
        $return_arr['tenant_data'] = $tenant_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    public function generate_identifier() : string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false)
    {
        if (empty($params) || !is_array($params)
         || empty($params['table_name'])) {
            return false;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'phs_tenants':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'added_by_uid' => [
                        'type'     => self::FTYPE_INT,
                    ],
                    'name' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'domain' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'index' => true,
                    ],
                    'identifier' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 36,
                        'index' => true,
                    ],
                    'is_default' => [
                        'type'     => self::FTYPE_TINYINT,
                        'length'   => 2,
                        'index' => true,
                        'default'  => 0,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_edit' => [
                        'type'  => self::FTYPE_DATETIME,
                    ],
                    'deleted' => [
                        'type'  => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_phs_tenants($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['name'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant name.'));

            return false;
        }

        if (empty($params['fields']['domain'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant domain.'));

            return false;
        }

        if (empty($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_INACTIVE;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid status for the tenant.'));

            return false;
        }

        if( $this->get_details_fields( ['domain' => $params['fields']['domain'] ]) ) {
            $this->set_error(self::ERR_INSERT, $this->_pt( 'There is already a tenant defined for this domain.' ));
            return false;
        }

        if (empty($params['fields']['identifier'])) {
            $params['fields']['identifier'] = $this->generate_identifier();
            while($this->get_details_fields( ['identifier' => $params['fields']['identifier'] ])) {
                $params['fields']['identifier'] = $this->generate_identifier();
            }
        }

        $params['fields']['is_default'] = (!empty($params['fields']['is_default'])?1:0);

        $params['fields']['last_edit'] = date(self::DATETIME_DB);

        if (empty($params['fields']['cdate'])
         || empty_db_date($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $params['fields']['last_edit'];
        }

        if (empty($params['fields']['status_date'])
            || empty_db_date($params['fields']['status_date'])) {
            $params['fields']['status_date'] = $params['fields']['last_edit'];
        }

        return $params;
    }

    protected function get_edit_prepare_params_phs_tenants($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if( !empty( $params['fields']['domain'] )
         && $this->get_details_fields(
             [
                 'domain' => $params['fields']['domain'],
                 'id' => [ 'check' => '!=', 'value' => $existing_data['id'] ]
             ]) ) {
            $this->set_error(self::ERR_INSERT, $this->_pt( 'There is already a tenant defined for this domain.' ));
            return false;
        }

        if( !empty( $params['fields']['identifier'] )
         && $this->get_details_fields(
             [
                 'identifier' => $params['fields']['identifier'],
                 'id' => [ 'check' => '!=', 'value' => $existing_data['id'] ]
             ]) ) {
            $this->set_error(self::ERR_INSERT, $this->_pt( 'A tenant with same identifier already exists.' ));
            return false;
        }

        if( isset( $params['fields']['is_default'] ) ) {
            $params['fields']['is_default'] = (!empty($params['fields']['is_default']) ? 1 : 0);
        }

        $now_date = date(self::DATETIME_DB);

        if (empty($params['fields']['last_edit'])
         || empty_db_date($params['fields']['last_edit'])) {
            $params['fields']['last_edit'] = $now_date;
        }

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a valid status.'));

                return false;
            }

            $params['fields']['status_date'] = $now_date;

            if ($params['fields']['status'] === self::STATUS_DELETED) {
                $params['fields']['deleted'] = $now_date;
            }
        }

        return $params;
    }

    protected function insert_after_phs_tenants($insert_arr, $params)
    {
        if( !empty( $params['fields']['is_default'] )
         && ($flow_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_tenants' ] ))
         && ($table_name = $this->get_flow_table_name($flow_arr))
        ) {
            // low level update, so we don't trigger anything in model
            db_query( 'UPDATE `'.$table_name.'` SET is_default = 0 WHERE id != \''.$insert_arr['id'].'\'',
                $flow_arr['db_connection'] );
        }

        return $insert_arr;
    }

    protected function edit_after_phs_tenants($existing_data, $edit_arr, $params)
    {
        if( !empty( $params['fields']['is_default'] )
         && empty( $existing_data['is_default'] )
         && ($flow_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_tenants' ] ))
         && ($table_name = $this->get_flow_table_name($flow_arr))
        ) {
            // low level update, so we don't trigger anything in model
            db_query( 'UPDATE `'.$table_name.'` SET is_default = 0 WHERE id != \''.$existing_data['id'].'\'',
                $flow_arr['db_connection'] );
        }

        return $existing_data;
    }
}
