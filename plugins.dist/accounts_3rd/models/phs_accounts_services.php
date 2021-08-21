<?php

namespace phs\plugins\accounts_3rd\models;

use \phs\libraries\PHS_Model;
use \phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Accounts_services extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    const SERVICE_GOOGLE = 1, SERVICE_APPLE = 2, SERVICE_FACEBOOK = 3;
    protected static $SERVICES_ARR = [
        self::SERVICE_GOOGLE => [ 'title' => 'Google' ],
        self::SERVICE_APPLE => [ 'title' => 'Apple' ],
        self::SERVICE_FACEBOOK => [ 'title' => 'Facebook' ],
    ];

    const STATUS_WAIT_IMPORT = 1, STATUS_IMPORTED = 2, STATUS_IMPORT_ERROR = 3, STATUS_IMPORT_SKIPPED = 4, STATUS_DELETED = 5;
    protected static $STATUSES_ARR = [
        self::STATUS_WAIT_IMPORT => [ 'title' => 'Waiting import' ],
        self::STATUS_IMPORTED => [ 'title' => 'Imported' ],
        self::STATUS_IMPORT_ERROR => [ 'title' => 'Import error' ],
        self::STATUS_IMPORT_SKIPPED => [ 'title' => 'Import skipped' ],
        self::STATUS_DELETED => [ 'title' => 'Deleted' ],
    ];

    /**
     * @inheritDoc
     */
    public function get_model_version()
    {
        return '1.0.1';
    }

    /**
     * @inheritDoc
     */
    public function get_table_names()
    {
        return [ 'users_services', 'users_services_links' ];
    }

    /**
     * @inheritDoc
     */
    public function get_main_table_name()
    {
        return 'users_services';
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_services( $lang = false )
    {
        static $services_arr = [];

        if( empty( self::$SERVICES_ARR ) )
            return [];

        if( $lang === false
         && !empty( $services_arr ) )
            return $services_arr;

        $result_arr = $this->translate_array_keys( self::$SERVICES_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $services_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_services_as_key_val( $lang = false )
    {
        static $services_key_val_arr = false;

        if( $lang === false
         && $services_key_val_arr !== false )
            return $services_key_val_arr;

        $key_val_arr = [];
        if( ($services = $this->get_services( $lang )) )
        {
            foreach( $services as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $services_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $service
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_service( $service, $lang = false )
    {
        $all_services = $this->get_statuses( $lang );
        if( empty( $service )
         || !isset( $all_services[$service] ) )
            return false;

        return $all_services[$service];
    }

    public function is_waiting_import( $record_data )
    {
        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_WAIT_IMPORT )
            return false;

        return $record_arr;
    }

    public function is_imported( $record_data )
    {
        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_IMPORTED )
            return false;

        return $record_arr;
    }

    public function is_import_error( $record_data )
    {
        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_IMPORT_ERROR )
            return false;

        return $record_arr;
    }

    public function is_deleted( $record_data )
    {
        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data ))
         || (int)$record_arr['status'] !== self::STATUS_DELETED )
            return false;

        return $record_arr;
    }

    /**
     * @param int $account_id Account ID
     * @param false|int $service_id 3rd party service ID
     * @param bool|array $params Parameters array
     *
     * @return array|bool
     */
    public function account_id_is_linked( $account_id, $service_id = false, $params = false )
    {
        $this->reset_error();

        $account_id = (int)$account_id;
        if( empty( $account_id ) )
            return false;

        if( $service_id !== false )
            $service_id = (int)$service_id;

        if( !empty( $service_id )
         && !$this->valid_service( $service_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid service ID provided.' ) );
        }

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !isset( $params['also_get_account_details'] ) )
            $params['also_get_account_details'] = true;
        else
            $params['also_get_account_details'] = (!empty( $params['also_get_account_details'] ));

        // If no service was provided, get the latest service linked with this account
        $links_flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services_links' ] );
        $links_flow_params['order_by'] = 'cdate DESC';

        $check_arr = [];
        $check_arr['account_id'] = $account_id;
        if( !empty( $service_id ) )
            $check_arr['service_id'] = $service_id;

        if( !($existing_link_arr = $this->get_details_fields( $check_arr, $links_flow_params )) )
            return false;

        // We have a link, check if we have also the details...

        $as_flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services' ] );

        $check_arr = [];
        $check_arr['service_token'] = $existing_link_arr['service_token'];
        $check_arr['service_id'] = $existing_link_arr['service_id'];

        if( empty( $params['also_get_account_details'] )
         || empty( $existing_link_arr['service_token'] )
         || !($existing_account_arr = $this->get_details_fields( $check_arr, $as_flow_params )) )
            $existing_account_arr = false;

        $existing_arr = [];
        $existing_arr['{service_link}'] = $existing_link_arr;
        $existing_arr['{service_account}'] = $existing_account_arr;

        return $existing_arr;
    }

    /**
     * @param string $service_token
     * @param int $service_id
     *
     * @return array|false
     */
    public function service_token_is_linked( $service_token, $service_id )
    {
        $this->reset_error();

        $service_token = trim( $service_token );
        $service_id = (int)$service_id;
        if( empty( $service_token )
         || !$this->valid_service( $service_id ) )
            return false;

        $links_flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services_links' ] );

        if( !($existing_link_arr = $this->get_details_fields( [ 'service_id' => $service_id, 'service_token' => $service_token ], $links_flow_params )) )
            return false;

        // We have a link, check if we have also the details...

        $as_flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services' ] );

        if( !($existing_account_arr = $this->get_details_fields( [ 'service_id' => $service_id, 'service_token' => $service_token ], $as_flow_params )) )
            $existing_account_arr = false;

        $existing_arr = [];
        $existing_arr['{service_link}'] = $existing_link_arr;
        $existing_arr['{service_account}'] = $existing_account_arr;

        return $existing_arr;
    }

    public function add_service_account_as_link( $service_token, $service_id, $account_id = 0 )
    {
        $this->reset_error();

        $service_token = trim( $service_token );
        $service_id = (int)$service_id;
        $account_id = (int)$account_id;
        if( empty( $service_token ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide service account token.' ) );
            return false;
        }

        if( empty( $service_id )
         || !$this->valid_service( $service_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid service ID provided.' ) );
            return false;
        }

        if( empty( $account_id ) )
            $account_id = 0;

        $flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services_links' ] );

        $action_fields_arr = [];
        $action_fields_arr['account_id'] = $account_id;
        $action_fields_arr['service_id'] = $service_id;
        $action_fields_arr['service_token'] = $service_token;

        $action_arr = $flow_params;
        $action_arr['fields'] = $action_fields_arr;

        if( ($existing_arr = $this->get_details_fields( [ 'service_id' => $service_id, 'service_token' => $service_token ], $flow_params )) )
            return $this->edit( $existing_arr, $action_arr );

        return $this->insert( $action_arr );
    }

    public function finish_service_account_import( $service_token, $service_id, $account_id )
    {
        $this->reset_error();

        $service_id = (int)$service_id;
        $account_id = (int)$account_id;

        if( empty( $service_id )
         || !$this->valid_service( $service_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid service ID provided.' ) );
            return false;
        }

        if( empty( $account_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide account id linked with service account token.' ) );
            return false;
        }

        if( !($linkage_arr = $this->service_token_is_linked( $service_token, $service_id )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided service account token is not in import process.' ) );
            return false;
        }

        if( empty( $linkage_arr['{service_account}'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided service account token is not in import process.' ) );
            return false;
        }

        $service_account_arr = $linkage_arr['{service_account}'];
        if( $this->is_imported( $service_account_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided service account is already imported.' ) );
            return false;
        }

        $service_acc_fields = [];
        $service_acc_fields['status'] = self::STATUS_IMPORTED;

        $service_account_edit = $this->fetch_default_flow_params( [ 'table_name' => 'users_services' ] );
        $service_account_edit['fields'] = $service_acc_fields;

        if( !($service_account_arr = $this->edit( $service_account_arr, $service_account_edit ))
         || !$this->add_service_account_as_link( $service_token, $service_id, $account_id ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating service account status or linkage.' ) );
            return false;
        }

        return $service_account_arr;
    }

    public function error_in_service_account_import( $service_token, $service_id )
    {
        $this->reset_error();

        if( !($linkage_arr = $this->service_token_is_linked( $service_token, $service_id )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided service account token is not in import process.' ) );
            return false;
        }

        if( empty( $linkage_arr['{service_account}'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided service account token is not in import process.' ) );
            return false;
        }

        $service_account_arr = $linkage_arr['{service_account}'];
        if( $this->is_imported( $service_account_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Provided service account is already imported.' ) );
            return false;
        }

        $service_acc_fields = [];
        $service_acc_fields['status'] = self::STATUS_IMPORT_ERROR;

        $service_account_edit = $this->fetch_default_flow_params( [ 'table_name' => 'users_services' ] );
        $service_account_edit['fields'] = $service_acc_fields;

        if( !($service_account_arr = $this->edit( $service_account_arr, $service_account_edit )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error updating service account status.' ) );
            return false;
        }

        return $service_account_arr;
    }

    protected function get_insert_prepare_params_users_services( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( !empty( $params['fields']['service_token'] ) )
            $params['fields']['service_token'] = trim( $params['fields']['service_token'] );
        if( !empty( $params['fields']['account_details'] ) )
            $params['fields']['account_details'] = trim( $params['fields']['account_details'] );

        if( empty( $params['fields']['service_token'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide service account token.' ) );
            return false;
        }

        if( empty( $params['fields']['service_id'] )
         || !$this->valid_service( $params['fields']['service_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a valid service for this account.' ) );
            return false;
        }

        if( empty( $params['fields']['account_details'] )
         || !is_string( $params['fields']['account_details'] )
         || !($account_details_arr = @json_decode( $params['fields']['account_details'], true )) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide service account details. This should be a JSON string.' ) );
            return false;
        }

        if( empty( $params['fields']['status'] )
         || !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_WAIT_IMPORT;

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        if( empty( $params['fields']['status_date'] )
         || empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];
        else
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        if( !isset( $params['{update_account_linkage}'] ) )
            $params['{update_account_linkage}'] = true;
        else
            $params['{update_account_linkage}'] = (!empty( $params['{update_account_linkage}'] ));

        return $params;
    }

    /**
     * Called right after a successful insert in database. Some model need more database work after successfully adding records in database or eventually chaining
     * database inserts. If one chain fails function should return false so all records added before to be hard-deleted. In case of success, function will return an array with all
     * key-values added in database.
     *
     * @param array $insert_arr Data array added with success in database
     * @param array $params Flow parameters
     *
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function insert_after_users_services( $insert_arr, $params )
    {
        $insert_arr['{service_link}'] = false;

        if( !empty( $params['{update_account_linkage}'] ) )
        {
            if( ($insert_arr['{service_link}'] = $this->add_service_account_as_link( $insert_arr['service_token'], $insert_arr['service_id'], 0 )) === false )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_INSERT, $this->_pt( 'Error linking service account token in database. Please try again.' ) );

                return false;
            }
        }

        return $insert_arr;
    }

    protected function get_edit_prepare_params_users_services( $existing_data, $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( isset( $params['fields']['service_token'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'You cannot edit service account token field.' ) );
            return false;
        }

        if( isset( $params['fields']['service_id'] )
         && !$this->valid_service( $params['fields']['service_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid service for this account.' ) );
            return false;
        }

        if( isset( $params['fields']['status'] )
        && !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Invalid service account status.' ) );
            return false;
        }

        if( !empty( $params['fields']['account_details'] ) )
            $params['fields']['account_details'] = trim( $params['fields']['account_details'] );

        if( !empty( $params['fields']['account_details'] )
        && (!is_string( $params['fields']['account_details'] )
             || !($account_details_arr = @json_decode( $params['fields']['account_details'], true ))
            ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide valid service account details. This should be a JSON string.' ) );
            return false;
        }

        if( !empty( $params['fields']['status'] ) )
        {
            if( empty( $params['fields']['status_date'] ) || empty_db_date( $params['fields']['status_date'] ) )
                $params['fields']['status_date'] = date( self::DATETIME_DB );
            else
                $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );
        }

        return $params;
    }

    protected function get_insert_prepare_params_users_services_links( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( !empty( $params['fields']['service_token'] ) )
            $params['fields']['service_token'] = trim( $params['fields']['service_token'] );
        if( !empty( $params['fields']['account_id'] ) )
            $params['fields']['account_id'] = (int)$params['fields']['account_id'];

        if( empty( $params['fields']['service_id'] )
         || !$this->valid_service( $params['fields']['service_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a valid service for this account.' ) );
            return false;
        }

        if( empty( $params['fields']['service_token'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a service account token.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['service_id'] = $params['fields']['service_id'];
        $check_arr['account_id'] = $params['fields']['account_id'];
        $check_arr['service_token'] = $params['fields']['service_token'];

        if( ($existing_arr = $this->get_details_fields( $check_arr, $this->fetch_default_flow_params( [ 'table_name' => 'users_services_links' ] ) )) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Service account token, account id pair already exists in database. Maybe account was already imported.' ) );
            return false;
        }

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        return $params;
    }

    protected function get_edit_prepare_params_users_services_links( $existing_data, $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( isset( $params['fields']['service_id'] )
         && (empty( $params['fields']['service_id'] )
            || !$this->valid_service( $params['fields']['service_id'] )
            ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid service for this account.' ) );
            return false;
        }

        if( !empty( $params['fields']['service_token'] ) )
            $params['fields']['service_token'] = trim( $params['fields']['service_token'] );
        if( !empty( $params['fields']['account_id'] ) )
            $params['fields']['account_id'] = (int)$params['fields']['account_id'];

        if( (!empty( $params['fields']['service_token'] ) && (string)$params['fields']['service_token'] !== (string)$existing_data['service_token'])
         || (!empty( $params['fields']['account_id'] ) && (int)$params['fields']['account_id'] !== (int)$existing_data['account_id'])
         || (!empty( $params['fields']['service_id'] ) && (int)$params['fields']['service_id'] !== (int)$existing_data['service_id']) )
        {
            if( !empty( $params['fields']['service_token'] ) )
                $service_token = $params['fields']['service_token'];
            else
                $service_token = $existing_data['service_token'];

            if( !empty( $params['fields']['account_id'] ) )
                $account_id = $params['fields']['account_id'];
            else
                $account_id = $existing_data['account_id'];

            if( !empty( $params['fields']['service_id'] ) )
                $service_id = $params['fields']['service_id'];
            else
                $service_id = $existing_data['service_id'];

            $check_arr = [];
            $check_arr['service_id'] = $service_id;
            $check_arr['account_id'] = $account_id;
            $check_arr['service_token'] = $service_token;

            if( ($old_existing_arr = $this->get_details_fields( $check_arr, $this->fetch_default_flow_params( [ 'table_name' => 'users_services_links' ] ) )) )
            {
                $this->set_error( self::ERR_EDIT, $this->_pt( 'Service account token, account id pair already exists in database.' ) );
                return false;
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
        if( empty( $params ) || !is_array( $params )
         || empty( $params['table_name'] ) )
            return false;

        $return_arr = [];
        switch( $params['table_name'] )
        {
            case 'users_services':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'service_id' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 4,
                        'index' => true,
                    ],
                    'service_token' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'index' => true,
                        'comment' => 'Token passed by 3rd party',
                    ],
                    'account_details' => [
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                        'comment' => 'JSON containing details passed by 3rd party',
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
            break;

            case 'users_services_links':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'service_id' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 4,
                        'index' => true,
                    ],
                    'service_token' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'index' => true,
                        'comment' => 'Token passed by 3rd party',
                    ],
                    'account_id' => [
                        'type' => self::FTYPE_INT,
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
