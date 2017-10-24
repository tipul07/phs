<?php

namespace phs\system\core\models;

use \phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\PHS;

class PHS_Model_Api_keys extends PHS_Model
{
    const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_DELETED = 3;
    protected static $STATUSES_ARR = array(
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
        self::STATUS_DELETED => array( 'title' => 'Deleted' ),
    );

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
        return array( 'api_keys' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'api_keys';
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $statuses_arr = array();
        // Translate and validate statuses...
        foreach( self::$STATUSES_ARR as $status_id => $status_arr )
        {
            $status_id = intval( $status_id );
            if( empty( $status_id ) )
                continue;

            if( empty( $status_arr['title'] ) )
                $status_arr['title'] = self::_pt( 'Status %s', $status_id );
            else
                $status_arr['title'] = self::_pt( $status_arr['title'] );

            $statuses_arr[$status_id] = $status_arr;
        }

        return $statuses_arr;
    }

    final public function get_statuses_as_key_val()
    {
        static $statuses_key_val_arr = false;

        if( $statuses_key_val_arr !== false )
            return $statuses_key_val_arr;

        $statuses_key_val_arr = array();
        if( ($statuses = $this->get_statuses()) )
        {
            foreach( $statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $statuses_key_val_arr[$key] = $val['title'];
            }
        }

        return $statuses_key_val_arr;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
            or empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    public function is_active( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or $record_arr['status'] != self::STATUS_ACTIVE )
            return false;

        return $record_arr;
    }

    public function is_inactive( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or $record_arr['status'] != self::STATUS_INACTIVE )
            return false;

        return $record_arr;
    }

    public function is_deleted( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data ))
         or $record_arr['status'] != self::STATUS_DELETED )
            return false;

        return $record_arr;
    }

    public function act_activate( $record_data, $params = false )
    {
        if( empty( $record_data )
         or !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'API key details not found in database.' ) );
            return false;
        }

        if( $this->is_active( $record_arr ) )
            return $record_arr;

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    public function act_inactivate( $record_data, $params = false )
    {
        if( empty( $record_data )
         or !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'API key details not found in database.' ) );
            return false;
        }

        if( $this->is_inactive( $record_arr ) )
            return $record_arr;

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_INACTIVE;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    public function act_delete( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         or !($record_arr = $this->data_to_array( $record_data )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'API key details not found in database.' ) );
            return false;
        }

        if( $this->is_deleted( $record_arr ) )
            return $record_arr;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params_arr = array();
        $edit_params_arr['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params_arr );
    }

    public function can_user_edit( $record_data, $account_data )
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( empty( $record_data ) or empty( $account_data )
         or !($apikey_arr = $this->data_to_array( $record_data ))
         or $this->is_deleted( $apikey_arr )
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or !($account_arr = $accounts_model->data_to_array( $account_data ))
         or !PHS_Roles::user_has_role_units( $account_arr, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
            return false;

        $return_arr = array();
        $return_arr['apikey_data'] = $apikey_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    public function get_apikeys_for_user_id( $user_id, $params = false )
    {
        $user_id = intval( $user_id );
        if( empty( $user_id ) )
            return array();

        $list_arr = array();
        $list_arr['field']['uid'] = $user_id;
        $list_arr['order_by'] = 'cdate DESC';

        if( !($return_arr = $this->get_list( $list_arr )) )
            return array();

        return $return_arr;
    }

    public function apikeys_count_for_user_id( $user_id, $params = false )
    {
        $user_id = intval( $user_id );
        if( empty( $user_id ) )
            return false;

        if( ($flow_params = $this->fetch_default_flow_params())
        and ($table_name = $this->get_flow_table_name( $flow_params ))
        and ($qid = db_query( 'SELECT COUNT(*) AS total_apikeys '.
                              ' FROM `'.$table_name.'`'.
                              ' WHERE status != \''.self::STATUS_DELETED.'\' AND uid = \''.$user_id.'\'', $flow_params['db_connection'] ))
        and ($total_arr = @mysqli_fetch_assoc( $qid ))
        and !empty( $total_arr['total_apikeys'] ) )
            return $total_arr['total_apikeys'];

        return 0;
    }

    public function generate_random_api_key()
    {
        return md5( uniqid( rand(), true ) );
    }

    public function generate_random_api_secret()
    {
        return md5( uniqid( rand(), true ) );
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_api_keys( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['api_key'] ) )
            $params['fields']['api_key'] = $this->generate_random_api_key();
        if( empty( $params['fields']['api_secret'] ) )
            $params['fields']['api_secret'] = $this->generate_random_api_secret();

        $cdate = date( self::DATETIME_DB );

        if( empty( $params['fields']['status'] )
         or !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_ACTIVE;

        $params['fields']['cdate'] = $cdate;

        if( empty( $params['fields']['status_date'] )
         or empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];
        else
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        return $params;
    }

    protected function get_edit_prepare_params_api_keys( $existing_arr, $params )
    {
        if( isset( $params['fields']['api_key'] ) and empty( $params['fields']['api_key'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide an API key.' ) );
            return false;
        }

        if( isset( $params['fields']['api_secret'] ) and empty( $params['fields']['api_secret'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide an API secret.' ) );
            return false;
        }

        if( !empty( $params['fields']['status'] )
        and (empty( $params['fields']['status_date'] ) or empty_db_date( $params['fields']['status_date'] ))
        and $this->valid_status( $params['fields']['status'] )
        and $params['fields']['status'] != $existing_arr['status'] )
            $params['fields']['status_date'] = date( self::DATETIME_DB );

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_count_list_common_params( $params = false )
    {
        if( !empty( $params['flags'] ) and is_array( $params['flags'] ) )
        {
            if( empty( $params['db_fields'] ) )
                $params['db_fields'] = '';

            $model_table = $this->get_flow_table_name( $params );
            foreach( $params['flags'] as $flag )
            {
                switch( $flag )
                {
                    case 'include_account_details':

                        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
                        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
                         or !($accounts_table = $accounts_model->get_flow_table_name( array( 'table_name' => 'users' ) )) )
                            continue;

                        $params['db_fields'] .= ', `'.$accounts_table.'`.nick AS account_nick, '.
                                                ' `'.$accounts_table.'`.email AS account_email, '.
                                                ' `'.$accounts_table.'`.level AS account_level, '.
                                                ' `'.$accounts_table.'`.deleted AS account_deleted, '.
                                                ' `'.$accounts_table.'`.lastlog AS account_lastlog, '.
                                                ' `'.$accounts_table.'`.lastip AS account_lastip, '.
                                                ' `'.$accounts_table.'`.status AS account_status, '.
                                                ' `'.$accounts_table.'`.status_date AS account_status_date, '.
                                                ' `'.$accounts_table.'`.cdate AS account_cdate ';
                        $params['join_sql'] .= ' LEFT JOIN `'.$accounts_table.'` ON `'.$accounts_table.'`.id = `'.$model_table.'`.uid ';
                    break;
                }
            }
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'api_keys':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'added_by_uid' => array(
                        'type' => self::FTYPE_INT,
                        'comment' => 'Who added this API key',
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'api_key' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                    ),
                    'api_secret' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                    ),
                    'allowed_methods' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Comma separated methods'
                    ),
                    'denied_methods' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Comma separated methods'
                    ),
                    'allowed_ips' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'comment' => 'Comma separated IPs'
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
