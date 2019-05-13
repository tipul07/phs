<?php

namespace phs\plugins\mobileapi\models;

use \phs\PHS;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Roles;
use \phs\plugins\accounts\models\PHS_Model_Accounts;
use \phs\plugins\accounts\models\PHS_Model_Accounts_details;

class PHS_Model_Api_online extends PHS_Model
{
    const ERR_SESSION_CREATE = 1;

    const DEVICE_KEY = '{device_data}';

    const DEV_TYPE_ANDROID = 1, DEV_TYPE_IOS = 2, DEV_TYPE_UNDEFINED = 3;
    protected static $DEVICE_TYPES_ARR = array(
        self::DEV_TYPE_ANDROID => array( 'title' => 'Android' ),
        self::DEV_TYPE_IOS => array( 'title' => 'iOS' ),
        self::DEV_TYPE_UNDEFINED => array( 'title' => 'Undefined' ),
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
        return array( 'mobileapi_online', 'mobileapi_devices' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'mobileapi_online';
    }

    final public function get_device_types( $lang = false )
    {
        static $device_types = array();

        if( $lang === false
        and !empty( $device_types ) )
            return $device_types;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Android' );
        $this->_pt( 'iOS' );
        $this->_pt( 'Undefined' );

        $result_arr = $this->translate_array_keys( self::$DEVICE_TYPES_ARR, array( 'title' ), $lang );

        if( $lang === false )
            $device_types = $result_arr;

        return $result_arr;
    }

    final public function get_device_types_as_key_val( $lang = false )
    {
        static $device_types_key_val_arr = false;

        if( $lang === false
        and $device_types_key_val_arr !== false )
            return $device_types_key_val_arr;

        $key_val_arr = array();
        if( ($device_types = $this->get_device_types( $lang )) )
        {
            foreach( $device_types as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $device_types_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function valid_device_type( $type, $lang = false )
    {
        $all_device_types = $this->get_device_types( $lang );
        if( empty( $type )
         or empty( $all_device_types[$type] ) )
            return false;

        return $all_device_types[$type];
    }

    public function act_delete( $record_data )
    {
        $this->reset_error();
        
        if( empty( $record_data )
         or !($record_arr = $this->data_to_array( $record_data, array( 'table_name' => 'mobileapi_online' ) )) )
        {
            $this->set_error( self::ERR_DELETE, $this->_pt( 'Session not found in database.' ) );
            return false;
        }

        return $this->hard_delete( $record_arr, array( 'table_name' => 'mobileapi_online' ) );
    }

    public function generate_api_key()
    {
        return md5( uniqid( rand(), true ) );
    }

    public function generate_api_secret()
    {
        return md5( uniqid( rand(), true ) );
    }

    public static function export_data_session_fields()
    {
        return array(
            'uid' => array(
                'key' => 'account_id',
                'type' => PHS_params::T_INT,
            ),
            'api_key' => array(
                'key' => 'api_key',
                'type' => PHS_params::T_NOHTML,
            ),
            'api_secret' => array(
                'key' => 'api_secret',
                'type' => PHS_params::T_NOHTML,
            ),
            'last_update' => array(
                'key' => 'last_update',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
            ),
            'cdate' => array(
                'key' => 'cdate',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
            ),
            self::DEVICE_KEY => array(
                'key' => 'device_data',
                'type' => PHS_params::T_ASIS,
            ),
        );
    }

    public static function export_data_device_fields()
    {
        return array(
            'owner_id' => array(
                'key' => 'owner_id',
                'type' => PHS_params::T_INT,
            ),
            'uid' => array(
                'key' => 'account_id',
                'type' => PHS_params::T_INT,
            ),
            'device_type' => array(
                'key' => 'device_type',
                'type' => PHS_params::T_INT,
            ),
            'device_name' => array(
                'key' => 'device_name',
                'type' => PHS_params::T_NOHTML,
            ),
            'device_version' => array(
                'key' => 'device_version',
                'type' => PHS_params::T_NOHTML,
            ),
            'device_token' => array(
                'key' => 'device_token',
                'type' => PHS_params::T_ASIS,
            ),
            'last_update' => array(
                'key' => 'last_update',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
            ),
            'cdate' => array(
                'key' => 'cdate',
                'type' => PHS_params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
            ),
        );
    }

    public function export_data_from_session_data( $session_data )
    {
        $this->reset_error();

        if( empty( $session_data )
         or !($session_arr = $this->populate_session_with_device_data( $session_data )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Session not found in database.' ) );
            return false;
        }

        if( !empty( $session_arr[self::DEVICE_KEY] ) and is_array( $session_arr[self::DEVICE_KEY] ) )
            $session_arr[self::DEVICE_KEY] = $this->export_data_from_device_data( $session_arr[self::DEVICE_KEY] );

        $export_arr = array();
        $fields_arr = self::export_data_session_fields();
        foreach( $fields_arr as $field => $field_arr )
        {
            if( !array_key_exists( $field, $session_arr ) )
                continue;

            $export_arr[$field_arr['key']] = PHS_params::set_type( $session_arr[$field], $field_arr['type'],
                (!empty( $field_arr['type_extra'] )?$field_arr['type_extra']:false) );
        }

        return $export_arr;
    }

    public function export_data_from_device_data( $device_data )
    {
        $this->reset_error();

        if( empty( $device_data )
         or !($device_arr = $this->data_to_array( $device_data, array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Device not found in database.' ) );
            return false;
        }

        $export_arr = array();
        $fields_arr = self::export_data_device_fields();
        foreach( $fields_arr as $field => $field_arr )
        {
            if( !array_key_exists( $field, $device_arr ) )
                continue;

            $export_arr[$field_arr['key']] = PHS_params::set_type( $device_arr[$field], $field_arr['type'],
                (!empty( $field_arr['type_extra'] )?$field_arr['type_extra']:false) );
        }

        return $export_arr;
    }

    public function get_session_device( $session_data )
    {
        $this->reset_error();

        if( !($session_arr = $this->populate_session_with_device_data( $session_data ))
         or empty( $session_arr[self::DEVICE_KEY] ) )
            return false;

        return $session_arr[self::DEVICE_KEY];
    }

    public function populate_session_with_device_data( $session_data )
    {
        $this->reset_error();

        if( empty( $session_data )
         or !($session_arr = $this->data_to_array( $session_data, array( 'table_name' => 'mobileapi_online' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Session not found in database.' ) );
            return false;
        }

        // take this test to be sure device_id didn't change to 0 (avoid cache)
        if( empty( $session_arr['device_id'] ) )
        {
            $session_arr[self::DEVICE_KEY] = false;
            return $session_arr;
        }

        // Check if we already have device data
        if( !empty( $session_arr[self::DEVICE_KEY] ) )
            return $session_arr;

        // get device from database and make sure device is linked to same account as current session
        if( !($device_arr = $this->get_details( $session_arr['device_id'], array( 'table_name' => 'mobileapi_devices' ) ))
         or $device_arr['uid'] != $session_arr['uid'] )
        {
            // couldn't find device in database... don't update session, just wait for next call (might be a database communication error)
            // Device will be fixed anyway at next login...
            $session_arr[self::DEVICE_KEY] = false;
            return $session_arr;
        }

        $session_arr[self::DEVICE_KEY] = $device_arr;

        return $session_arr;
    }

    public function get_session_by_apikey( $api_key, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['include_device_data'] ) )
            $params['include_device_data'] = true;
        else
            $params['include_device_data'] = (!empty( $params['include_device_data'] )?true:false);

        $check_arr = array();
        $check_arr['api_key'] = $api_key;

        if( !($session_arr = $this->get_details_fields( $check_arr, array( 'table_name' => 'mobileapi_online' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Session not found in database.' ) );
            return false;
        }

        if( !empty( $params['include_device_data'] ) )
        {
            if( !($new_session = $this->populate_session_with_device_data( $session_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t obtain session device.' ) );

                return false;
            }

            $session_arr = $new_session;
        }

        return $session_arr;
    }

    public function check_session_authentication( $session_data, $api_secret )
    {
        $this->reset_error();

        if( empty( $session_data )
         or !($session_arr = $this->data_to_array( $session_data, array( 'table_name' => 'mobileapi_online' ) ))
         or (string)$session_arr['api_secret'] !== (string)$api_secret )
            return false;

        return $session_arr;
    }

    public function generate_session( $account_id, $device_data )
    {
        $this->reset_error();

        $account_id = intval( $account_id );
        if( empty( $account_id )
         or empty( $device_data ) or !is_array( $device_data ) )
        {
            $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Please provide session details.' ) );
            return false;
        }

        if( !($session_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_online' ) ))
         or !($devices_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Couldn\'t initialize session parameters.' ) );
            return false;
        }

        $sess_fields = array();
        $sess_fields['uid'] = $account_id;

        $insert_arr = $session_flow;
        $insert_arr[self::DEVICE_KEY] = $device_data;
        $insert_arr['fields'] = $sess_fields;

        if( !($session_arr = $this->insert( $insert_arr )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Error saving session details in database.' ) );

            return false;
        }

        // clean old sessions from same user/device pair...
        if( !empty( $session_arr[self::DEVICE_KEY] )
        and !empty( $session_arr[self::DEVICE_KEY]['id'] ) )
        {
            // low level query so we don't trigger anything when we delete old sessions
            db_query( 'DELETE FROM `'.$this->get_flow_table_name( $session_flow ).'` '.
                      ' WHERE device_id = \''.$session_arr[self::DEVICE_KEY]['id'].'\' AND uid = \''.$account_id.'\' '.
                      ' AND id != \''.$session_arr['id'].'\'', $this->get_db_connection( $session_flow ) );
        }

        return $session_arr;
    }

    public function logout_session( $session_data )
    {
        $this->reset_error();

        if( !($session_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_online' ) ))
         or !($devices_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t initialize session parameters.' ) );
            return false;
        }

        if( empty( $session_data )
         or !($session_arr = $this->populate_session_with_device_data( $session_data )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Session not found in database.' ) );
            return false;
        }

        if( !empty( $session_arr[self::DEVICE_KEY] ) )
        {
            $device_fields = array();
            $device_fields['uid'] = 0;
            $device_fields['session_id'] = 0;

            $edit_arr = $devices_flow;
            $edit_arr['fields'] = $device_fields;

            if( !($new_device_arr = $this->edit( $session_arr[self::DEVICE_KEY], $edit_arr )) )
            {
                $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Error saving device details in database.' ) );
                return false;
            }
        }

        $this->hard_delete( $session_arr, $session_flow );

        return $session_arr;
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_mobileapi_online( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['uid'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an account for this session.' ) );
            return false;
        }

        if( empty( $params['fields']['api_key'] ) )
            $params['fields']['api_key'] = $this->generate_api_key();
        if( empty( $params['fields']['api_secret'] ) )
            $params['fields']['api_secret'] = $this->generate_api_secret();

        $params['fields']['cdate'] = date( self::DATETIME_DB );
        $params['fields']['last_update'] = $params['fields']['cdate'];

        if( empty( $params[self::DEVICE_KEY] ) or !is_array( $params[self::DEVICE_KEY] ) )
            $params[self::DEVICE_KEY] = false;

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_edit_prepare_params_mobileapi_online( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['uid'] ) and empty( $params['fields']['uid'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an account for this session.' ) );
            return false;
        }

        $params['fields']['last_update'] = date( self::DATETIME_DB );

        if( empty( $params[self::DEVICE_KEY] ) or !is_array( $params[self::DEVICE_KEY] ) )
            $params[self::DEVICE_KEY] = false;

        return $params;
    }

    protected function insert_after_mobileapi_online( $insert_arr, $params )
    {
        if( !empty( $params[self::DEVICE_KEY] ) and is_array( $params[self::DEVICE_KEY] ) )
        {
            // Update contact address
            if( !($new_device_arr = $this->update_session_device( $insert_arr, $params[self::DEVICE_KEY] )) )
                return false;

            $insert_arr['device_id'] = $new_device_arr['id'];
            $insert_arr[self::DEVICE_KEY] = $new_device_arr;
        }

        return $insert_arr;
    }

    protected function edit_after_mobileapi_online( $existing_data, $edit_arr, $params )
    {
        if( !empty( $params[self::DEVICE_KEY] ) and is_array( $params[self::DEVICE_KEY] ) )
        {
            // Update session device
            if( !($new_device_arr = $this->update_session_device( $existing_data, $params[self::DEVICE_KEY] )) )
                return false;

            $existing_data['device_id'] = $new_device_arr['id'];
            $existing_data[self::DEVICE_KEY] = $new_device_arr;
        }

        return $existing_data;
    }

    public function update_session_device( $sesison_data, $device_params )
    {
        $this->reset_error();

        if( empty( $device_params ) or !is_array( $device_params )
         or !($session_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_online' ) ))
         or !($devices_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Couldn\'t initiate data to update avatar profile location details.' ) );
            return false;
        }

        if( empty( $device_params['device_type'] ) or empty( $device_params['device_token'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Device type and device token not provided for current session.' ) );
            return false;
        }

        if( empty( $sesison_data )
         or !($session_arr = $this->data_to_array( $sesison_data, $session_flow )) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Couldn\'t find session details in database.' ) );
            return false;
        }

        $device_id = 0;
        if( !empty( $session_arr['device_id'] ) )
            $device_id = $session_arr['device_id'];

        $db_device_arr = false;
        if( empty( $device_id )
         or !($db_device_arr = $this->get_details( $device_id, $devices_flow )) )
            $db_device_arr = false;

        // If device from session doesn't match provided type and token, ignore it and search for it in db
        if( !empty( $db_device_arr )
        and ($db_device_arr['device_type'] != $device_params['device_type']
                or $db_device_arr['device_token'] != $device_params['device_token'])
        )
            $db_device_arr = false;

        if( empty( $db_device_arr ) )
        {
            $check_arr = array();
            $check_arr['device_type'] = $device_params['device_type'];
            $check_arr['device_token'] = $device_params['device_token'];

            if( !($db_device_arr = $this->get_details_fields( $check_arr, $devices_flow )) )
                $db_device_arr = false;
        }

        $db_fields_device = $device_params;

        // Make sure address is linked to this contact
        $db_fields_device['uid'] = $session_arr['uid'];
        $db_fields_device['owner_id'] = $session_arr['uid'];
        $db_fields_device['session_id'] = $session_arr['id'];

        // id field might be null
        if( array_key_exists( 'id', $db_fields_device ) )
            unset( $db_fields_device['id'] );

        if( empty( $db_device_arr ) )
        {
            $device_params_arr = $devices_flow;
            $device_params_arr['fields'] = $db_fields_device;

            if( !($new_device_arr = $this->insert( $device_params_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_EDIT, $this->_pt( 'Error saving session device details to database.' ) );

                return false;
            }
        } else
        {
            $device_params_arr = $devices_flow;
            $device_params_arr['fields'] = $db_fields_device;

            if( !($new_device_arr = $this->edit( $db_device_arr, $device_params_arr )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_EDIT, $this->_pt( 'Error saving session device details to database.' ) );

                return false;
            }
        }

        if( empty( $session_arr['device_id'] )
         or $session_arr['device_id'] != $new_device_arr['id'] )
        {
            $edit_arr = array();
            $edit_arr['device_id'] = $new_device_arr['id'];

            $edit_params = $session_flow;
            $edit_params['fields'] = $edit_arr;

            if( !($new_session_arr = $this->edit( $session_arr, $edit_params )) )
            {
                $this->set_error( self::ERR_EDIT, $this->_pt( 'Error linking device to session.' ) );
                return false;
            }
        }

        return $new_device_arr;
    }

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params_mobileapi_devices( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['device_token'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a device token.' ) );
            return false;
        }

        if( empty( $params['fields']['device_type'] )
         or !$this->valid_device_type( $params['fields']['device_type'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a valid device type.' ) );
            return false;
        }

        if( empty( $params['fields']['uid'] ) )
            $params['fields']['uid'] = 0;

        if( empty( $params['fields']['owner_id'] ) )
            $params['fields']['owner_id'] = $params['fields']['uid'];

        if( empty( $params['fields']['api_key'] ) )
            $params['fields']['api_key'] = $this->generate_api_key();
        if( empty( $params['fields']['api_secret'] ) )
            $params['fields']['api_secret'] = $this->generate_api_secret();

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        return $params;
    }

    /**
     * @inheritdoc
     */
    protected function get_edit_prepare_params_mobileapi_devices( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['device_token'] ) and empty( $params['fields']['device_token'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a device token.' ) );
            return false;
        }

        if( isset( $params['fields']['device_type'] )
        and !$this->valid_device_type( $params['fields']['device_type'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a valid device type.' ) );
            return false;
        }

        if( empty( $existing_data['owner_id'] ) and !empty( $params['fields']['uid'] ) )
            $params['fields']['owner_id'] = $params['fields']['uid'];

        $params['fields']['last_update'] = date( self::DATETIME_DB );

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

                        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
                         or !($accounts_table = $accounts_model->get_flow_table_name()) )
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
            case 'mobileapi_online':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'device_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'api_key' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                    ),
                    'api_secret' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'last_update' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;

            case 'mobileapi_devices':
                $return_arr = array(

                    self::EXTRA_INDEXES_KEY => array(
                        'pair_key' => array(
                            'unique' => true,
                            'fields' => array( 'device_type', 'device_token' ),
                        ),
                    ),

                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'owner_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'Tells last user connected with this device',
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'User currently logged in with this device',
                    ),
                    'session_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'device_type' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'editable' => false,
                    ),
                    'device_name' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'device_version' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'device_token' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'editable' => false,
                    ),
                    'last_update' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
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
