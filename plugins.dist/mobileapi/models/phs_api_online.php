<?php

namespace phs\plugins\mobileapi\models;

use \phs\PHS;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Logger;

class PHS_Model_Api_online extends PHS_Model
{
    const LAT_LONG_DIGITS = 7;

    const ERR_SESSION_CREATE = 1;

    const DEVICE_KEY = '{device_data}';

    const SOURCE_NATIVE = 'native';

    private static $_sources_arr = array(
        self::SOURCE_NATIVE => array(
            'title' => 'Native (PHS)',
        )
    );

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
        return '1.0.2';
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
    public function get_main_table_name()
    {
        return 'mobileapi_online';
    }

    public function valid_source( $source )
    {
        if( empty( self::$_sources_arr ) or !is_array( self::$_sources_arr )
         or empty( self::$_sources_arr[$source] ) )
            return false;

        return self::$_sources_arr[$source];
    }

    public function default_source_definition()
    {
        return array(
            'title' => '',
        );
    }

    /**
     * @param string $source Source name
     * @param array $source_arr Source definition array
     *
     * @return bool true on success, false on error
     */
    public function define_source( $source, $source_arr )
    {
        if( empty( $source ) or !is_string( $source )
         or empty( $source_arr ) or !is_array( $source_arr ) )
            return false;

        $source_arr = self::validate_array( $source_arr, self::default_source_definition() );

        self::$_sources_arr[$source] = $source_arr;

        // Force recaching sources...
        $this->get_sources_as_key_val( false, true );

        return true;
    }

    public function get_sources( $lang = false, $force = false )
    {
        static $sources_arr = array();

        if( empty( $force )
        and $lang === false
        and !empty( $sources_arr ) )
            return $sources_arr;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Native (PHS)' );

        $result_arr = $this->translate_array_keys( self::$_sources_arr, array( 'title' ), $lang );

        if( $lang === false )
            $sources_arr = $result_arr;

        return $result_arr;
    }

    final public function get_sources_as_key_val( $lang = false, $force = false )
    {
        static $sources_key_val_arr = false;

        if( empty( $force )
        and $lang === false
        and $sources_key_val_arr !== false )
            return $sources_key_val_arr;

        $key_val_arr = array();
        if( ($sources_arr = $this->get_sources( $lang, $force )) )
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
        $type = (int)$type;
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

    public static function get_api_data_session_fields()
    {
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobileapi_plugin */
        if( !($mobileapi_plugin = PHS::load_plugin( 'mobileapi' )) )
            return array();

        return array(
            'uid' => array(
                'key' => 'account_id',
                'type' => PHS_Params::T_INT,
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'api_key' => array(
                'key' => 'api_key',
                'type' => PHS_Params::T_NOHTML,
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'api_secret' => array(
                'key' => 'api_secret',
                'type' => PHS_Params::T_NOHTML,
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'last_update' => array(
                'key' => 'last_update',
                'type' => PHS_Params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'cdate' => array(
                'key' => 'cdate',
                'type' => PHS_Params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            self::DEVICE_KEY => array(
                'key' => 'device_data',
                'type' => PHS_Params::T_ASIS,
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
        );
    }

    public static function get_api_data_device_fields()
    {
        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobileapi_plugin */
        if( !($mobileapi_plugin = PHS::load_plugin( 'mobileapi' )) )
            return array();

        return array(
            'owner_id' => array(
                'key' => 'owner_id',
                'type' => PHS_Params::T_INT,
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'uid' => array(
                'key' => 'account_id',
                'type' => PHS_Params::T_INT,
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'device_type' => array(
                'key' => 'device_type',
                'type' => PHS_Params::T_INT,
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'device_name' => array(
                'key' => 'device_name',
                'type' => PHS_Params::T_NOHTML,
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'device_version' => array(
                'key' => 'device_version',
                'type' => PHS_Params::T_NOHTML,
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'device_token' => array(
                'key' => 'device_token',
                'type' => PHS_Params::T_ASIS,
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'source' => array(
                'key' => 'source',
                'type' => PHS_Params::T_NOHTML,
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'lat' => array(
                'key' => 'lat',
                'type' => PHS_Params::T_FLOAT,
                'type_extra' => array( 'digits' => self::LAT_LONG_DIGITS ),
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'long' => array(
                'key' => 'long',
                'type' => PHS_Params::T_FLOAT,
                'type_extra' => array( 'digits' => self::LAT_LONG_DIGITS ),
                'key_type' => $mobileapi_plugin::API_KEY_BOTH,
            ),
            'last_update' => array(
                'key' => 'last_update',
                'type' => PHS_Params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
            'cdate' => array(
                'key' => 'cdate',
                'type' => PHS_Params::T_DATE,
                'type_extra' => array( 'format' => self::DATETIME_DB ),
                'key_type' => $mobileapi_plugin::API_KEY_OUTPUT,
            ),
        );
    }

    public function export_data_from_session_data( $session_data )
    {
        $this->reset_error();

        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobileapi_plugin */
        if( !($mobileapi_plugin = PHS::load_plugin( 'mobileapi' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading MobileAPI plugin.' ) );
            return false;
        }

        if( empty( $session_data )
         or !($session_arr = $this->populate_session_with_device_data( $session_data )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Session not found in database.' ) );
            return false;
        }

        if( !empty( $session_arr[self::DEVICE_KEY] ) and is_array( $session_arr[self::DEVICE_KEY] ) )
            $session_arr[self::DEVICE_KEY] = $this->export_data_from_device_data( $session_arr[self::DEVICE_KEY] );

        return $mobileapi_plugin::export_api_data_with_definition_as_array( $session_arr, self::get_api_data_session_fields() );
    }

    public function export_data_from_device_data( $device_data )
    {
        $this->reset_error();

        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobileapi_plugin */
        if( !($mobileapi_plugin = PHS::load_plugin( 'mobileapi' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading MobileAPI plugin.' ) );
            return false;
        }

        if( empty( $device_data )
         or !($device_arr = $this->data_to_array( $device_data, array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Device not found in database.' ) );
            return false;
        }

        return $mobileapi_plugin::export_api_data_with_definition_as_array( $device_arr, self::get_api_data_device_fields() );
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
         or (int)$device_arr['uid'] !== (int)$session_arr['uid'] )
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

    public function generate_session( $device_data, $account_id = 0 )
    {
        $this->reset_error();

        $account_id = intval( $account_id );
        if( empty( $device_data ) or !is_array( $device_data ) )
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

        if( empty( $device_data['device_token'] )
         or !$this->valid_device_type( $device_data['device_type'] ) )
        {
            $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Please provide device details.' ) );
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
            // AND uid = \''.$account_id.'\'
            // low level query so we don't trigger anything when we delete old sessions
            db_query( 'DELETE FROM `'.$this->get_flow_table_name( $session_flow ).'` '.
                      ' WHERE device_id = \''.$session_arr[self::DEVICE_KEY]['id'].'\' '.
                      ' AND id != \''.$session_arr['id'].'\'', $this->get_db_connection( $session_flow ) );
        }

        return $session_arr;
    }

    public function update_session( $session_data, $device_fields, $account_id = false, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['device_data'] ) )
            $params['device_data'] = false;
        if( empty( $params['regenerate_keys'] ) )
            $params['regenerate_keys'] = false;
        else
            $params['regenerate_keys'] = true;

        if( !($session_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_online' ) ))
         or !($devices_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Couldn\'t initialize session parameters.' ) );
            return false;
        }

        if( empty( $session_data )
         or !($session_arr = $this->data_to_array( $session_data, $session_flow )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Session details not found in database.' ) );

            return false;
        }

        if( empty( $params['device_data'] ) )
            $params['device_data'] = $session_arr['device_id'];

        if( empty( $params['device_data'] )
         or !($device_arr = $this->data_to_array( $params['device_data'], $devices_flow )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Session details not found in database.' ) );

            return false;
        }

        if( $account_id !== false )
        {
            $account_id = intval( $account_id );

            if( empty( $account_id ) )
                $account_id = 0;
        }

        $sess_fields = array();
        if( $account_id !== false )
        {
            if( empty( $device_fields ) or !is_array( $device_fields ) )
                $device_fields = array();

            $sess_fields['uid'] = $account_id;
            $device_fields['uid'] = $account_id;
        }

        if( !empty( $params['regenerate_keys'] ) )
        {
            if( empty( $sess_fields['api_key'] ) )
                $sess_fields['api_key'] = $this->generate_api_key();
            if( empty( $sess_fields['api_secret'] ) )
                $sess_fields['api_secret'] = $this->generate_api_secret();
        }

        // Make sure we have something to update
        $sess_fields['last_update'] = date( self::DATETIME_DB );

        $edit_arr = $session_flow;
        if( !empty( $device_fields ) )
            $edit_arr[self::DEVICE_KEY] = $device_fields;
        $edit_arr['fields'] = $sess_fields;

        if( !($session_arr = $this->edit( $session_arr, $edit_arr )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_SESSION_CREATE, $this->_pt( 'Error saving session details in database.' ) );

            return false;
        }

        // clean old sessions from same user/device pair...
        // in case we want to have an user logged in only on one device
        if( false
        and !empty( $session_arr[self::DEVICE_KEY] )
        and !empty( $session_arr[self::DEVICE_KEY]['id'] ) )
        {
            // AND uid = \''.$account_id.'\'
            // low level query so we don't trigger anything when we delete old sessions
            db_query( 'DELETE FROM `'.$this->get_flow_table_name( $session_flow ).'` '.
                      ' WHERE device_id = \''.$session_arr[self::DEVICE_KEY]['id'].'\' '.
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
            $params['fields']['uid'] = 0;

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
            // Update new updated fields...
            foreach( $edit_arr as $field => $val )
            {
                if( @array_key_exists( $field, $existing_data ) )
                    $existing_data[$field] = $val;
            }

            // Update session device
            if( !($new_device_arr = $this->update_session_device( $existing_data, $params[self::DEVICE_KEY] )) )
                return false;

            $existing_data['device_id'] = $new_device_arr['id'];
            $existing_data[self::DEVICE_KEY] = $new_device_arr;
        }

        return $existing_data;
    }

    /**
     * @param int|array $account_data
     * @param bool|array $params
     *
     * @return bool|array
     */
    public function get_account_devices( $account_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['only_logged_in'] ) )
            $params['only_logged_in'] = false;
        else
            $params['only_logged_in'] = true;

        if( empty( $params['device_type'] ) )
            $params['device_type'] = false;
        else
            $params['device_type'] = (int)$params['device_type'];

        if( !empty( $params['device_type'] )
        and !$this->valid_device_type( $params['device_type'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid device type.' ) );
            return false;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading accounts model.' ) );
            return false;
        }

        if( empty( $account_data )
         or !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Account not found in database.' ) );
            return false;
        }

        if( !($devices_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t initiate device update details.' ) );
            return false;
        }

        $list_arr = $devices_flow;
        if( !empty( $params['only_logged_in'] ) )
            $list_arr['fields']['uid'] = $account_arr['id'];
        else
            $list_arr['fields']['owner_id'] = $account_arr['id'];

        if( !($devices_arr = $this->get_list( $list_arr )) )
        {
            if( $this->has_error() )
                return false;

            $devices_arr = array();
        }

        return $devices_arr;
    }

    public function update_device( $device_params, $device_data = false )
    {
        $this->reset_error();

        if( empty( $device_params ) or !is_array( $device_params )
         or !($devices_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_devices' ) )) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Couldn\'t initiate device update details.' ) );
            return false;
        }

        if( empty( $device_params['device_type'] ) or empty( $device_params['device_token'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Device type and device token not provided for current session.' ) );
            return false;
        }

        $db_device_arr = false;
        if( empty( $device_data )
         or !($db_device_arr = $this->data_to_array( $device_data, $devices_flow )) )
            $db_device_arr = false;

        // If device from session doesn't match provided type and token, ignore it and search for it in db
        if( !empty( $db_device_arr )
        and ($db_device_arr['device_type'] != $device_params['device_type']
                or (string)$db_device_arr['device_token'] !== (string)$device_params['device_token'])
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

        // id field might be null
        if( array_key_exists( 'id', $db_fields_device ) )
            unset( $db_fields_device['id'] );

        if( empty( $db_device_arr ) )
        {
            if( empty( $db_fields_device['source'] ) )
                $db_fields_device['source'] = self::SOURCE_NATIVE;

            if( !$this->valid_source( $db_fields_device['source'] ) )
            {
                $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid device source.' ) );
                return false;
            }

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

        return $new_device_arr;
    }

    public function update_session_device( $sesison_data, $device_params )
    {
        $this->reset_error();

        if( empty( $device_params ) or !is_array( $device_params )
         or !($session_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_online' ) )) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Couldn\'t initiate data to update avatar profile location details.' ) );
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

        $db_fields_device = $device_params;

        // Make sure address is linked to this contact
        $db_fields_device['uid'] = $session_arr['uid'];
        $db_fields_device['owner_id'] = $session_arr['uid'];
        $db_fields_device['session_id'] = $session_arr['id'];

        if( !($new_device_arr = $this->update_device( $db_fields_device, $device_id )) )
        {
            if( $this->has_error() )
                $this->set_error( self::ERR_EDIT, $this->_pt( 'Couldn\'t update session device.' ) );

            return false;
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

    public function check_api_sessions_ag()
    {
        $this->reset_error();

        /** @var \phs\plugins\mobileapi\PHS_Plugin_Mobileapi $mobileapi_plugin */
        if( !($mobileapi_plugin = PHS::load_plugin( 'mobileapi' ))
         or !($mapi_online_flow = $this->fetch_default_flow_params( array( 'table_name' => 'mobileapi_online' ) ))
         or !($mo_table_name = $this->get_flow_table_name( $mapi_online_flow )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t initialize required resources to check mobile API sessions.' ) );
            return false;
        }

        $return_arr = array();
        $return_arr['expired'] = 0;
        $return_arr['errors'] = 0;
        $return_arr['deleted'] = 0;

        if( !($plugin_settings = $mobileapi_plugin->get_plugin_settings())
         or !is_array( $plugin_settings )
         or empty( $plugin_settings['api_session_lifetime'] ) )
            return $return_arr;

        $plugin_settings['api_session_lifetime'] = (int)$plugin_settings['api_session_lifetime'];

        $list_arr = $mapi_online_flow;
        $list_arr['fields']['last_update'] = array( 'check' => '<=', 'value' => date( self::DATETIME_DB, time() + $plugin_settings['api_session_lifetime'] * 3600 ) );

        if( ($sessions_list = $this->get_list( $list_arr )) === false
         or !is_array( $sessions_list ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error querying database for API mobile sessions.' ) );
            return false;
        }

        if( empty( $sessions_list ) )
            return $return_arr;

        PHS_Logger::logf( 'Deleting '.count( $sessions_list ).' API mobile sessions.', $mobileapi_plugin::LOG_CHANNEL );

        foreach( $sessions_list as $session_id => $session_arr )
        {
            $return_arr['expired']++;

            if( !$this->logout_session( $session_arr ) )
            {
                $return_arr['errors']++;

                $error_msg = 'N/A';
                if( $this->has_error() )
                    $error_msg = $this->get_simple_error_message();

                PHS_Logger::logf( 'Error logging out session #'.$session_id.': '.$error_msg, $mobileapi_plugin::LOG_CHANNEL );
                continue;
            }

            $return_arr['deleted']++;
        }

        return $return_arr;
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

        if( empty( $params['fields']['owner_id'] ) and !empty( $params['fields']['uid'] ) )
            $params['fields']['owner_id'] = $params['fields']['uid'];

        if( empty( $params['fields']['api_key'] ) )
            $params['fields']['api_key'] = $this->generate_api_key();
        if( empty( $params['fields']['api_secret'] ) )
            $params['fields']['api_secret'] = $this->generate_api_secret();

        $params['fields']['cdate'] = date( self::DATETIME_DB );
        $params['fields']['last_update'] = $params['fields']['cdate'];

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
                            continue 2;

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

    public function distance_from_area_center( $lat1, $long1, $lat2, $long2, $lat, $long )
    {
        $center = $this->get_area_center( $lat1, $long1, $lat2, $long2 );

        return $this->distance_between_points( $center['lat'], $center['long'], $lat, $long );
    }

    public function get_area_center( $lat1, $long1, $lat2, $long2 )
    {
        // spare some cycles if points are same
        if( $lat1 == $lat2 and $long1 == $long2 )
            return array(
                'lat' => $lat1,
                'long' => $long1,
            );

        $center_y_dist = max( $lat1, $lat2 ) - min( $lat1, $lat2 );
        $center_x_dist = max( $long1, $long2 ) - min( $long1, $long2 );

        $center_lat = min( $lat1, $lat2 ) + $center_y_dist / 2;
        $center_long = min( $long1, $long2 ) + $center_x_dist / 2;

        return array(
            'lat' => $center_lat,
            'long' => $center_long,
        );
    }

    public function distance_between_points( $lat1, $long1, $lat2, $long2 )
    {
        // spare some cycles if points are same
        if( $lat1 == $lat2 and $long1 == $long2 )
            return 0;

        $earth_radius = 6371;

        $latFrom = deg2rad( $lat1 );
        $lonFrom = deg2rad( $long1 );
        $latTo = deg2rad( $lat2 );
        $lonTo = deg2rad( $long2 );

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
             pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earth_radius;
    }

    public function get_distance_query( $lat, $long, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['distance_field_name'] ) )
            $params['distance_field_name'] = 'distance';
        if( empty( $params['table_lat'] ) )
            $params['table_lat'] = 'lat';
        if( empty( $params['table_long'] ) )
            $params['table_long'] = 'long';

        if( !isset( $params['result_in_km'] ) )
            $params['result_in_km'] = true;
        else
            $params['result_in_km'] = (!empty( $params['result_in_km'] )?true:false);

        if( empty( $params['range'] ) )
            $params['range'] = 0;

        if( $params['result_in_km'] )
        {
            $earth_radius = 6371;
            $one_deg_lat = 111;
        } else
        {
            $earth_radius = 3959;
            $one_deg_lat = 69;
        }

        $query_arr = array();
        $query_arr['range_lat1'] = 0;
        $query_arr['range_long1'] = 0;
        $query_arr['range_lat2'] = 0;
        $query_arr['range_long2'] = 0;
        $query_arr['extra_sql'] = '';
        $query_arr['having_sql'] = '';

        // 3959 - miles, 6371 - kilometers = Earth radius
        $query_arr['db_fields'] = ' ('.$earth_radius.' * acos( cos( radians('.$lat.') ) * cos( radians( '.$params['table_lat'].' ) ) '.
                               ' * cos( radians( '.$params['table_long'].' ) - radians('.$long.')) + sin(radians('.$lat.')) '.
                               ' * sin( radians('.$params['table_lat'].')))) AS `'.$params['distance_field_name'].'`';

        if( !empty( $params['range'] ) )
        {
            // 1 deg of latitude ~= 69 miles (111km)
            // 1 deg of longitude ~= cos(latitude)*69
            // set lon1 = mylon-dist/abs(cos(radians(mylat))*69);
            // set lon2 = mylon+dist/abs(cos(radians(mylat))*69);
            // set lat1 = mylat-(dist/69);
            // set lat2 = mylat+(dist/69);
            if( ($long_term = abs(cos( deg2rad( $lat ) ) * $one_deg_lat )) )
            {
                $range_lat = $params['range'] / $one_deg_lat;

                $lat1 = $lat - $range_lat;
                $lat2 = $lat + $range_lat;

                $range_long = $params['range'] / $long_term;

                $long1 = $long - $range_long;
                $long2 = $long + $range_long;

                $query_arr['range_lat1'] = $lat1;
                $query_arr['range_long1'] = $long1;
                $query_arr['range_lat2'] = $lat2;
                $query_arr['range_long2'] = $long2;

                $query_arr['extra_sql'] = ' ('.$params['table_lat'].' BETWEEN '.$lat1.' AND '.$lat2.' '.
                                          ' AND '.
                                          ' '.$params['table_long'].' BETWEEN '.$long1.' AND '.$long2.')';
            }

            $query_arr['having_sql'] = ' `'.$params['distance_field_name'].'` < '.$params['range'];
        }

        return $query_arr;
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
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                    ),
                    'api_secret' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
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
                    'source' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                    ),
                    'device_type' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                        'editable' => false,
                    ),
                    'device_name' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                    ),
                    'device_version' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                    ),
                    'device_token' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'editable' => false,
                    ),
                    'lat' => array(
                        'type' => self::FTYPE_DECIMAL,
                        'length' => (self::LAT_LONG_DIGITS+3).','.self::LAT_LONG_DIGITS,
                        'default' => 0,
                    ),
                    'long' => array(
                        'type' => self::FTYPE_DECIMAL,
                        'length' => (self::LAT_LONG_DIGITS+3).','.self::LAT_LONG_DIGITS,
                        'default' => 0,
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
