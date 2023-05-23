<?php
namespace phs\system\core\models;

use phs\PHS;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\traits\PHS_Model_Trait_statuses;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Model_Api_keys extends PHS_Model
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
        return '1.1.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return ['api_keys'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'api_keys';
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

    public function act_activate($record_data, $params = false)
    {
        if (empty($record_data)
         || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('API key details not found in database.'));

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

    public function act_inactivate($record_data, $params = false)
    {
        if (empty($record_data)
         || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('API key details not found in database.'));

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

    public function act_delete($record_data, $params = false)
    {
        $this->reset_error();

        if (empty($record_data)
         || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_DELETE, $this->_pt('API key details not found in database.'));

            return false;
        }

        if ($this->is_deleted($record_arr)) {
            return $record_arr;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
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
         || !($apikey_arr = $this->data_to_array($record_data))
         || $this->is_deleted($apikey_arr)
         || !($accounts_model = PHS::load_model('accounts', 'accounts'))
         || !($account_arr = $accounts_model->data_to_array($account_data))
         || !can(PHS_Roles::ROLEU_MANAGE_API_KEYS, null, $account_arr)) {
            return false;
        }

        $return_arr = [];
        $return_arr['apikey_data'] = $apikey_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    public function get_apikeys_for_user_id($user_id, $params = false)
    {
        $user_id = (int)$user_id;
        if (empty($user_id)) {
            return [];
        }

        $list_arr = [];
        $list_arr['field']['uid'] = $user_id;
        $list_arr['order_by'] = 'cdate DESC';

        if (!($return_arr = $this->get_list($list_arr))) {
            return [];
        }

        return $return_arr;
    }

    public function apikeys_count_for_user_id($user_id, $params = false) : int
    {
        $user_id = (int)$user_id;
        if (!empty($user_id)
         && ($flow_params = $this->fetch_default_flow_params())
         && ($table_name = $this->get_flow_table_name($flow_params))
         && ($qid = db_query('SELECT COUNT(*) AS total_apikeys '
                              .' FROM `'.$table_name.'`'
                              .' WHERE status != \''.self::STATUS_DELETED.'\' AND uid = \''.$user_id.'\'', $flow_params['db_connection']))
         && ($total_arr = db_fetch_assoc($qid, $flow_params['db_connection']))
         && !empty($total_arr['total_apikeys'])) {
            return (int)$total_arr['total_apikeys'];
        }

        return 0;
    }

    /**
     * @param bool $only_active
     *
     * @return array
     */
    public function get_all_api_keys(bool $only_active = false): array
    {
        static $cached_api_keys = null, $cached_active_api_keys = null;

        $this->reset_error();

        if (!$only_active) {
            if ($cached_api_keys !== null) {
                return $cached_api_keys;
            }
        } else {
            if ($cached_active_api_keys !== null) {
                return $cached_active_api_keys;
            }
        }

        $list_arr = $this->fetch_default_flow_params(['table_name' => 'api_keys']);
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => self::STATUS_DELETED];
        $list_arr['order_by'] = 'title ASC';

        $cached_active_api_keys = [];
        if (!($cached_api_keys = $this->get_list($list_arr))) {
            $cached_api_keys = [];
        } else {
            foreach ($cached_api_keys as $a_id => $a_arr) {
                if (!$this->is_active($a_arr)) {
                    continue;
                }

                $cached_active_api_keys[$a_id] = $a_arr;
            }
        }

        if ($only_active) {
            return $cached_active_api_keys;
        }

        return $cached_api_keys;
    }

    /**
     * @param bool $only_active
     *
     * @return array
     */
    public function get_all_api_keys_as_key_val(bool $only_active = false) : array
    {
        $this->reset_error();

        if (!($results_arr = $this->get_all_api_keys($only_active))) {
            return [];
        }

        $return_arr = [];
        foreach ($results_arr as $record_id => $record_arr) {
            $return_arr[$record_id] = $record_arr['title'];
        }

        return $return_arr;
    }

    public function generate_random_api_key() : string
    {
        return md5(uniqid(mt_rand(), true));
    }

    public function generate_random_api_secret() : string
    {
        return md5(uniqid(mt_rand(), true));
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
            case 'api_keys':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'added_by_uid' => [
                        'type'    => self::FTYPE_INT,
                        'comment' => 'Who added this API key',
                    ],
                    'uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'tenant_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'API Key tenant (if any)',
                    ],
                    'title' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                    ],
                    'api_key' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                    ],
                    'api_secret' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                    ],
                    'allowed_methods' => [
                        'type'     => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment'  => 'Comma separated methods',
                    ],
                    'denied_methods' => [
                        'type'     => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment'  => 'Comma separated methods',
                    ],
                    'allow_sw' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                    ],
                    'allowed_ips' => [
                        'type'     => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment'  => 'Comma separated IPs',
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_api_keys($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['api_key'])) {
            $params['fields']['api_key'] = $this->generate_random_api_key();
        }
        if (empty($params['fields']['api_secret'])) {
            $params['fields']['api_secret'] = $this->generate_random_api_secret();
        }

        $params['fields']['allow_sw'] = empty($params['fields']['allow_sw'])?0:1;

        if(!empty($params['fields']['tenant_id']) && PHS::is_multi_tenant()) {
            $params['fields']['tenant_id'] = (int)$params['fields']['tenant_id'];
        } else {
            $params['fields']['tenant_id'] = 0;
        }

        $cdate = date(self::DATETIME_DB);

        if (empty($params['fields']['status'])
         || !$this->valid_status($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_ACTIVE;
        }

        $params['fields']['cdate'] = $cdate;

        if (empty($params['fields']['status_date'])
         || empty_db_date($params['fields']['status_date'])) {
            $params['fields']['status_date'] = $params['fields']['cdate'];
        } else {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        return $params;
    }

    protected function get_edit_prepare_params_api_keys($existing_arr, $params)
    {
        if (isset($params['fields']['api_key']) && empty($params['fields']['api_key'])) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide an API key.'));

            return false;
        }

        if (isset($params['fields']['api_secret']) && empty($params['fields']['api_secret'])) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide an API secret.'));

            return false;
        }

        if (array_key_exists('allow_sw', $params['fields'])) {
            $params['fields']['allow_sw'] = !empty($params['fields']['allow_sw'])?1:0;
        }

        if( PHS::is_multi_tenant() ) {
            if( !empty( $params['fields']['tenant_id'] ) ) {
                $params['fields']['tenant_id'] = (int)$params['fields']['tenant_id'];
            } else {
                $params['fields']['tenant_id'] = 0;
            }
        } elseif( array_key_exists('tenant_id', $params['fields'])) {
            unset( $params['fields']['tenant_id'] );
        }

        if (!empty($params['fields']['status'])
         && (int)$params['fields']['status'] !== (int)$existing_arr['status']
         && (empty($params['fields']['status_date']) || empty_db_date($params['fields']['status_date']))
         && $this->valid_status($params['fields']['status'])) {
            $params['fields']['status_date'] = date(self::DATETIME_DB);
        } elseif (!empty($params['fields']['status_date'])) {
            $params['fields']['status_date'] = date(self::DATETIME_DB, parse_db_date($params['fields']['status_date']));
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_count_list_common_params($params = false)
    {
        if (!empty($params['flags']) && is_array($params['flags'])) {
            if (empty($params['db_fields'])) {
                $params['db_fields'] = '';
            }

            $model_table = $this->get_flow_table_name($params);
            foreach ($params['flags'] as $flag) {
                switch ($flag) {
                    case 'include_account_details':

                        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
                        if (!($accounts_model = PHS_Model_Accounts::get_instance())
                         || !($accounts_table = $accounts_model->get_flow_table_name(['table_name' => 'users']))) {
                            continue 2;
                        }

                        $params['db_fields'] .= ', `'.$accounts_table.'`.nick AS account_nick, '
                                                .' `'.$accounts_table.'`.email AS account_email, '
                                                .' `'.$accounts_table.'`.level AS account_level, '
                                                .' `'.$accounts_table.'`.deleted AS account_deleted, '
                                                .' `'.$accounts_table.'`.lastlog AS account_lastlog, '
                                                .' `'.$accounts_table.'`.lastip AS account_lastip, '
                                                .' `'.$accounts_table.'`.status AS account_status, '
                                                .' `'.$accounts_table.'`.status_date AS account_status_date, '
                                                .' `'.$accounts_table.'`.cdate AS account_cdate ';
                        $params['join_sql'] .= ' LEFT JOIN `'.$accounts_table.'` ON `'.$accounts_table.'`.id = `'.$model_table.'`.uid ';
                        break;
                }
            }
        }

        return $params;
    }
}
