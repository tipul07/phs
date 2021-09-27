<?php

namespace phs\plugins\remote_phs\models;

use \phs\PHS;
use \phs\libraries\PHS_Model;
use \phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Phs_remote_domains extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    const STATUS_NOT_CONNECTED = 1, STATUS_CONNECTED = 2, STATUS_SUSPENDED = 3, STATUS_DELETED = 4;
    protected static $STATUSES_ARR = [
        self::STATUS_NOT_CONNECTED => [ 'title' => 'Not Connected' ],
        self::STATUS_CONNECTED => [ 'title' => 'Connected' ],
        self::STATUS_SUSPENDED => [ 'title' => 'Suspended' ],
        self::STATUS_DELETED => [ 'title' => 'Deleted' ],
    ];

    const LOG_TYPE_INCOMING = 1, LOG_TYPE_OUTGOING = 2;
    protected static $LOG_TYPES_ARR = [
        self::LOG_TYPE_INCOMING => [ 'title' => 'Incoming' ],
        self::LOG_TYPE_OUTGOING => [ 'title' => 'Outgoing' ],
    ];

    const SOURCE_MANUALLY = 1, SOURCE_PROGRAMMATICALLY = 2;
    protected static $SOURCES_ARR = [
        self::SOURCE_MANUALLY => [ 'title' => 'Manually' ],
        self::SOURCE_PROGRAMMATICALLY => [ 'title' => 'Programmatically' ],
    ];

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.1';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return [ 'phs_remote_domains', 'phs_remote_logs' ];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'phs_remote_domains';
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_log_types( $lang = false )
    {
        static $types_arr = [];

        if( empty( self::$LOG_TYPES_ARR ) )
            return [];

        if( $lang === false
         && !empty( $types_arr ) )
            return $types_arr;

        $result_arr = $this->translate_array_keys( self::$LOG_TYPES_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $types_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_log_types_as_key_val( $lang = false )
    {
        static $types_key_val_arr = false;

        if( $lang === false
         && $types_key_val_arr !== false )
            return $types_key_val_arr;

        $key_val_arr = [];
        if( ($types_arr = $this->get_log_types( $lang )) )
        {
            foreach( $types_arr as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $types_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $type
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_log_type( $type, $lang = false )
    {
        $all_types = $this->get_log_types( $lang );
        if( empty( $type )
         || !isset( $all_types[$type] ) )
            return false;

        return $all_types[$type];
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_remote_domain_sources( $lang = false )
    {
        static $sources_arr = [];

        if( empty( self::$SOURCES_ARR ) )
            return [];

        if( $lang === false
         && !empty( $sources_arr ) )
            return $sources_arr;

        $result_arr = $this->translate_array_keys( self::$SOURCES_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $sources_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_remote_domain_sources_as_key_val( $lang = false )
    {
        static $sources_key_val_arr = false;

        if( $lang === false
         && $sources_key_val_arr !== false )
            return $sources_key_val_arr;

        $key_val_arr = [];
        if( ($sources_arr = $this->get_remote_domain_sources( $lang )) )
        {
            foreach( $sources_arr as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $sources_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $source
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_remote_domain_sources( $source, $lang = false )
    {
        $all_sources = $this->get_remote_domain_sources( $lang );
        if( empty( $source )
         || !isset( $all_sources[$source] ) )
            return false;

        return $all_sources[$source];
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_not_connected( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_NOT_CONNECTED )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_connected( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_CONNECTED )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_suspended( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_SUSPENDED )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_source_manually( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['source'] !== self::SOURCE_MANUALLY )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_source_programmatically( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['source'] !== self::SOURCE_PROGRAMMATICALLY )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return false|array
     */
    public function is_deleted( $record_data )
    {
        if( !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || (int)$record_arr['status'] !== self::STATUS_DELETED )
            return false;

        return $record_arr;
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_connect( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( $this->is_connected( $record_arr ) )
            return $record_arr;

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_CONNECTED;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_suspend( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( $this->is_suspended( $record_arr ) )
            return $record_arr;

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_SUSPENDED;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params );
    }

    /**
     * @param int|array $record_data
     * @param false|array $params
     *
     * @return false|array
     */
    public function act_delete( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( $record_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'Remote domain details not found in database.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params_arr = [];
        $edit_params_arr['fields'] = $edit_arr;

        return $this->edit( $record_arr, $edit_params_arr );
    }

    public function can_user_edit( $record_data, $account_data )
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $remote_plugin */
        if( empty( $record_data ) || empty( $account_data )
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => 'phs_remote_domains' ] ))
         || $this->is_deleted( $record_arr )
         || !($remote_plugin = PHS::load_plugin( 'remote_phs' ))
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         || !($account_arr = $accounts_model->data_to_array( $account_data, [ 'table_name' => 'users' ] ))
         || !$remote_plugin->can_admin_manage_domains( $account_arr ) )
            return false;

        $return_arr = [];
        $return_arr['remote_domain_data'] = $record_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    /**
     * @param bool $only_connected
     *
     * @return array
     */
    public function get_all_remote_domains( $only_connected = false )
    {
        static $cached_domains = false, $cached_connected_domains = false;

        $this->reset_error();

        if( !$only_connected )
        {
            if( $cached_domains !== false )
                return $cached_domains;
        } else
        {
            if( $cached_connected_domains !== false )
                return $cached_connected_domains;
        }

        $list_arr = $this->fetch_default_flow_params( [ 'table_name' => 'phs_remote_domains' ] );
        $list_arr['fields']['status'] = [ 'check' => '!=', 'value' => self::STATUS_DELETED ];
        $list_arr['order_by'] = 'title ASC';

        $cached_connected_domains = [];
        if( !($cached_domains = $this->get_list( $list_arr )) )
            $cached_domains = [];

        else
        {
            foreach( $cached_domains as $d_id => $d_arr )
            {
                if( !$this->is_connected( $d_arr ) )
                    continue;

                $cached_connected_domains[$d_id] = $d_arr;
            }
        }

        if( $only_connected )
            return $cached_connected_domains;

        return $cached_domains;
    }

    /**
     * @param bool $only_connected
     *
     * @return array
     */
    public function get_all_remote_domains_as_key_val( $only_connected = false )
    {
        $this->reset_error();

        if( !($results_arr = $this->get_all_remote_domains( $only_connected )) )
            return [];

        $return_arr = [];
        foreach( $results_arr as $record_id => $record_arr )
        {
            $return_arr[$record_id] = $record_arr['title'];
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_phs_remote_domains( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( empty( $params['fields']['title'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide remote domain title.' ) );
            return false;
        }

        if( empty( $params['fields']['domain'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide remote domain.' ) );
            return false;
        }

        if( empty( $params['fields']['handle'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a handle for this remote domain.' ) );
            return false;
        }

        if( empty( $params['fields']['out_apikey'] ) && empty( $params['fields']['out_apisecret'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide outgoing API key details for this remote domain.' ) );
            return false;
        }

        if( empty( $params['fields']['apikey_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an incomming API key for this remote domain.' ) );
            return false;
        }

        if( !isset( $params['fields']['allow_incoming'] ) )
            $params['fields']['allow_incoming'] = 1;
        else
            $params['fields']['allow_incoming'] = (!empty( $params['fields']['allow_incoming'] )?1:0);

        $params['fields']['log_requests'] = (!empty( $params['fields']['log_requests'] )?1:0);

        $check_arr = [];
        $check_arr['handle'] = $params['fields']['handle'];

        if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a remote domain using provided handle.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['domain'] = $params['fields']['domain'];

        if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a similar remote domain.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        if( empty( $params['fields']['source'] )
         || !$this->valid_status( $params['fields']['source'] ) )
            $params['fields']['source'] = self::SOURCE_MANUALLY;

        if( empty( $params['fields']['status'] )
         || !$this->valid_status( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_NOT_CONNECTED;

        $params['fields']['cdate'] = $cdate;

        if( empty( $params['fields']['status_date'] )
         || empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];
        else
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        return $params;
    }

    protected function get_edit_prepare_params_phs_remote_domains( $existing_arr, $params )
    {
        if( isset( $params['fields']['title'] ) && empty( $params['fields']['title'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain title.' ) );
            return false;
        }

        if( isset( $params['fields']['domain'] ) && empty( $params['fields']['domain'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['handle'] ) && empty( $params['fields']['handle'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a handle for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['source'] ) && !$this->valid_remote_domain_sources( $params['fields']['source'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid source for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['status'] ) && !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid status for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['out_apikey'] ) )
            $out_apikey = trim( $params['fields']['out_apikey'] );
        else
            $out_apikey = $existing_arr['out_apikey'];

        if( isset( $params['fields']['out_apisecret'] ) )
            $out_apisecret = trim( $params['fields']['out_apisecret'] );
        else
            $out_apisecret = $existing_arr['out_apisecret'];

        if( empty( $out_apikey ) && empty( $out_apisecret ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide outgoing API key details for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['apikey_id'] ) && empty( $params['fields']['apikey_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide an incomming API key for this remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['allow_incoming'] ) )
            $params['fields']['allow_incoming'] = (!empty( $params['fields']['allow_incoming'] )?1:0);

        if( isset( $params['fields']['log_requests'] ) )
            $params['fields']['log_requests'] = (!empty( $params['fields']['log_requests'] )?1:0);

        if( !empty( $params['fields']['handle'] ) )
        {
            $check_arr = [];
            $check_arr['handle'] = $params['fields']['handle'];
            $check_arr['id'] = [ 'check' => '!=', 'value' => $existing_arr['id'] ];

            if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a remote domain using provided handle.' ) );
                return false;
            }
        }

        if( !empty( $params['fields']['domain'] ) )
        {
            $check_arr = [];
            $check_arr['domain'] = $params['fields']['domain'];
            $check_arr['id'] = [ 'check' => '!=', 'value' => $existing_arr['id'] ];

            if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a similar remote domain.' ) );
                return false;
            }
        }

        if( !empty( $params['fields']['status'] )
         && (int)$params['fields']['status'] !== (int)$existing_arr['status']
         && (empty( $params['fields']['status_date'] ) || empty_db_date( $params['fields']['status_date'] )) )
            $params['fields']['status_date'] = date( self::DATETIME_DB );

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        return $params;
    }

    protected function get_insert_prepare_params_phs_remote_logs( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( empty( $params['fields']['remote_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide remote domain for this remote domain log.' ) );
            return false;
        }

        if( empty( $params['fields']['action'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an action for this remote domain log.' ) );
            return false;
        }

        $cdate = date( self::DATETIME_DB );

        if( empty( $params['fields']['type'] )
         || !$this->valid_log_type( $params['fields']['type'] ) )
            $params['fields']['type'] = self::LOG_TYPE_OUTGOING;

        $params['fields']['cdate'] = $cdate;

        if( empty( $params['fields']['status_date'] )
         || empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];
        else
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        return $params;
    }

    protected function get_edit_prepare_params_phs_remote_logs( $existing_arr, $params )
    {
        if( isset( $params['fields']['domain'] ) && empty( $params['fields']['domain'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide remote domain.' ) );
            return false;
        }

        if( isset( $params['fields']['apikey_id'] ) && empty( $params['fields']['apikey_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide an API key for this remote domain.' ) );
            return false;
        }

        if( !isset( $params['fields']['allow_incoming'] ) )
            $params['fields']['allow_incoming'] = (!empty( $params['fields']['allow_incoming'] )?1:0);

        if( !isset( $params['fields']['log_requests'] ) )
            $params['fields']['log_requests'] = (!empty( $params['fields']['log_requests'] )?1:0);

        if( !empty( $params['fields']['domain'] ) )
        {
            $check_arr = [];
            $check_arr['domain'] = $params['fields']['domain'];
            $check_arr['id'] = [ 'check' => '!=', 'value' => $existing_arr['id'] ];

            if( ($db_record_arr = $this->get_details_fields( $check_arr, [ 'table_name' => 'phs_remote_domains' ] )) )
            {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'There is already a similar remote domain.' ) );
                return false;
            }
        }

        if( !empty( $params['fields']['status'] )
         && (empty( $params['fields']['status_date'] ) || empty_db_date( $params['fields']['status_date'] ))
         && $this->valid_status( $params['fields']['status'] )
         && (int)$params['fields']['status'] !== (int)$existing_arr['status'] )
            $params['fields']['status_date'] = date( self::DATETIME_DB );

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

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
            case 'phs_remote_domains':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'added_by_uid' => [
                        'type' => self::FTYPE_INT,
                        'comment' => 'Who added this domain',
                    ],
                    'title' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'Short explanatory title',
                    ],
                    'handle' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'Unique programable identifier',
                        'index' => true,
                    ],
                    'domain' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index' => true,
                    ],
                    'apikey_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'API key used when receiving requests',
                    ],
                    'out_apikey' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'API key used when sending requests',
                    ],
                    'out_apisecret' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'API secret used when sending requests',
                    ],
                    'ips_whihtelist' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Comma separated IPs empty=all',
                    ],
                    'allow_incoming' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Allow incoming requests',
                    ],
                    'log_requests' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Create log records in phs_remote_logs',
                    ],
                    'source' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                        'comment' => 'How was this domain added',
                    ],
                    'status' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_incoming' => [
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Last incoming request',
                    ],
                    'last_outgoing' => [
                        'type' => self::FTYPE_DATETIME,
                        'comment' => 'Last outgoing request',
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
            break;

            case 'phs_remote_logs':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'remote_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'Request remote domain',
                    ],
                    'type' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'comment' => 'Request type',
                    ],
                    'action' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'comment' => 'Executed action',
                    ],
                    'body' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Request body',
                    ],
                    'error_log' => [
                        'type' => self::FTYPE_TEXT,
                        'comment' => 'Request error (if any)',
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
       }

        return $return_arr;
    }

    /**
     * This function is never used. Its purpose is to have translations for strings which are not translated in used methods
     */
    private function _never_used_only_for_translation()
    {
        $this->_pt( 'Not Connected' );
        $this->_pt( 'Connected' );
        $this->_pt( 'Suspended' );
        $this->_pt( 'Deleted' );

        $this->_pt( 'Manually' );
        $this->_pt( 'Programmatically' );

        $this->_pt( 'Incoming' );
        $this->_pt( 'Outgoing' );
    }
}
