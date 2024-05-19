<?php
namespace phs\plugins\accounts\models;

use phs\libraries\PHS_Model;

class PHS_Model_Accounts_tenants extends PHS_Model
{
    /** @var bool|PHS_Model_Accounts */
    private static $_accounts_model = false;

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return ['users_tenants'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'users_tenants';
    }

    /**
     * Return how to many tenants is the provided account linked to
     *
     * @param int $account_id
     *
     * @return null|int
     */
    public function get_account_tenants_count(int $account_id) : ?int
    {
        if (empty($account_id)
         || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'users_tenants']))
         || !($table_name = $this->get_flow_table_name($flow_arr['table_name']))
         || !($qid = db_query('SELECT COUNT(*) AS total_tenants FROM `'.$table_name.'` WHERE account_id = \''.$account_id.'\'', $flow_arr['db_connection']))
         || !($total_arr = db_fetch_assoc($qid, $flow_arr['db_connection']))) {
            return null;
        }

        return empty($total_arr['total_tenants']) ? 0 : (int)$total_arr['total_tenants'];
    }

    /**
     * Returning an empty array means that account belongs to all tenants
     *
     * @param int $account_id
     *
     * @return array[int]
     */
    public function get_account_tenants_as_ids_array(int $account_id) : array
    {
        if (empty($account_id)
         || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'users_tenants']))
         || !($table_name = $this->get_flow_table_name($flow_arr['table_name']))
         || !($qid = db_query('SELECT tenant_id FROM `'.$table_name.'` WHERE account_id = \''.$account_id.'\'', $flow_arr['db_connection']))) {
            return [];
        }

        $return_arr = [];
        while (($link_arr = db_fetch_assoc($qid, $flow_arr['db_connection']))) {
            $return_arr[] = (int)$link_arr['tenant_id'];
        }

        return $return_arr;
    }

    /**
     * Links tenants with an account.
     *
     * @param array|int $account_data Account id or account array
     * @param array $tenants_arr Tenants passed as id array
     * @param null|array $params Functionality parameters
     *
     * @return bool
     */
    public function link_tenants_to_account($account_data, array $tenants_arr, ?array $params = null) : bool
    {
        $this->reset_error();

        if (empty($params)) {
            $params = [];
        }

        $params['append_tenants'] = (!isset($params['append_tenants']) || !empty($params['append_tenants']));

        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($account_data)
         || !($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'users_tenants']))
         || !($ut_table = $this->get_flow_table_name($flow_params))
         || !($u_flow = self::$_accounts_model->fetch_default_flow_params(['table_name' => 'users']))
         || !($u_table = self::$_accounts_model->get_flow_table_name($u_flow))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid flow parameters.'));

            return false;
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (empty($tenants_arr)) {
            if (!empty($params['append_tenants'])) {
                return true;
            }

            // Unlink all roles...
            if (!db_query('UPDATE `'.$u_table.'` SET is_multitenant = 1 WHERE id = \''.$account_arr['id'].'\'', $u_flow['db_connection'])
             || !db_query('DELETE FROM `'.$ut_table.'` WHERE account_id = \''.$account_arr['id'].'\'', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error un-linking old tenants from account.'));

                return false;
            }

            return true;
        }

        if (!($existing_ids = $this->get_account_tenants_as_ids_array($account_arr['id']))) {
            $existing_ids = [];
        }

        $insert_ids = [];
        $delete_ids = [];
        foreach ($tenants_arr as $tenant_id) {
            if (!in_array((int)$tenant_id, $existing_ids, true)) {
                $insert_ids[] = $tenant_id;
            }
        }

        foreach ($insert_ids as $tenant_id) {
            if (!db_query('INSERT INTO `'.$ut_table.'` SET account_id = \''.$account_arr['id'].'\', tenant_id = \''.$tenant_id.'\', cdate = NOW()', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error linking all tenants to account.'));

                return false;
            }
        }

        if (empty($params['append_tenants'])) {
            foreach ($existing_ids as $tenant_id) {
                if (!in_array($tenant_id, $tenants_arr, true)) {
                    $delete_ids[] = $tenant_id;
                }
            }

            if (!empty($delete_ids)
             && !db_query('DELETE FROM `'.$ut_table.'` WHERE account_id = \''.$account_arr['id'].'\' '
                           .' AND tenant_id IN ('.implode(',', $delete_ids).')', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error un-linking old tenants from account.'));

                return false;
            }
        }

        if (null === ($tenants_count = $this->get_account_tenants_count($account_arr['id']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error obtaining tenants count for the account.'));

            return false;
        }

        if ($tenants_count > 0) {
            if (!empty($account_arr['is_multitenant'])
             && !db_query('UPDATE `'.$u_table.'` SET is_multitenant = 0 WHERE id = \''.$account_arr['id'].'\'', $u_flow['db_connection'])) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating multi-tenant details for account.'));

                return false;
            }
        } elseif (empty($account_arr['is_multitenant'])
             && !db_query('UPDATE `'.$u_table.'` SET is_multitenant = 1 WHERE id = \''.$account_arr['id'].'\'', $u_flow['db_connection'])) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error updating multi-tenant details for account.'));

            return false;
        }

        return true;
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
            case 'users_tenants':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'account_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'tenant_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'cdate' => [
                        'type'    => self::FTYPE_DATETIME,
                        'comment' => 'When was this user linked to the tenant',
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_users_tenants($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['account_id'])
         || empty($params['fields']['tenant_id'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide account tenant details.'));

            return false;
        }

        return $params;
    }

    protected function get_edit_prepare_params_users_details($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if ((isset($params['fields']['account_id']) && empty($params['fields']['account_id']))
         || (isset($params['fields']['tenant_id']) && empty($params['fields']['tenant_id']))) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide account tenant details.'));

            return false;
        }

        return $params;
    }

    private function _load_dependencies() : bool
    {
        if (empty(self::$_accounts_model)
            && !(self::$_accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
