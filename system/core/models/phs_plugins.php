<?php

namespace phs\system\core\models;

use \phs\PHS;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_line_params;
use \phs\libraries\PHS_logger;

class PHS_Model_Plugins extends PHS_Model
{
    const ERR_FORCE_INSTALL = 100, ERR_DB_DETAILS = 101;

    const HOOK_STATUSES = 'phs_plugins_statuses';

    const STATUS_INSTALLED = 1, STATUS_ACTIVE = 2, STATUS_INACTIVE = 3;

    // Cached database rows
    private static $db_plugins = array();

    // Cached plugin settings
    private static $plugin_settings = array();

    protected static $STATUSES_ARR = array(
        self::STATUS_INSTALLED => array( 'title' => 'Installed' ),
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
    );

    function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        $this->_reset_db_plugin_cache();
        $this->_reset_plugin_settings_cache();
    }

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
        return array( 'plugins' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'plugins';
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $new_statuses_arr = self::$STATUSES_ARR;
        if( ($extra_statuses_arr = PHS::trigger_hooks( self::HOOK_STATUSES, array( 'statuses_arr' => self::$STATUSES_ARR ) ))
        and is_array( $extra_statuses_arr ) and !empty( $extra_statuses_arr['statuses_arr'] ) )
            $new_statuses_arr = array_merge( $extra_statuses_arr['statuses_arr'], $new_statuses_arr );

        $statuses_arr = array();
        // Translate and validate statuses...
        if( !empty( $new_statuses_arr ) and is_array( $new_statuses_arr ) )
        {
            foreach( $new_statuses_arr as $status_id => $status_arr )
            {
                $status_id = intval( $status_id );
                if( empty( $status_id ) )
                    continue;

                if( empty( $status_arr['title'] ) )
                    $status_arr['title'] = self::_t( 'Status %s', $status_id );
                else
                    $status_arr['title'] = self::_t( $status_arr['title'] );

                $statuses_arr[$status_id] = array(
                    'title' => $status_arr['title']
                );
            }
        }

        return $statuses_arr;
    }

    public function active_status( $status )
    {
        if( !$this->valid_status( $status )
         or !in_array( $status, array( self::STATUS_ACTIVE ) ) )
            return false;

        return true;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
         or empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    private function _reset_plugin_settings_cache()
    {
        self::$plugin_settings = array();
    }

    private function _reset_db_plugin_cache()
    {
        self::$db_plugins = array();
    }

    public function get_db_settings( $instance_id = null, $force = false )
    {
        $this->reset_error();

        if( $instance_id != null
        and !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id == null
        and !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !empty( $force )
        and isset( self::$plugin_settings[$instance_id] ) )
            unset( self::$plugin_settings[$instance_id] );

        if( isset( self::$plugin_settings[$instance_id] ) )
            return self::$plugin_settings[$instance_id];

        if( !($db_details = $this->get_db_details( $instance_id, $force )) )
            return false;

        if( empty( $db_details['settings'] ) )
            self::$plugin_settings[$instance_id] = array();

        else
            // parse settings in database...
            self::$plugin_settings[$instance_id] = PHS_line_params::parse_string( $db_details['settings'] );

        return self::$plugin_settings[$instance_id];
    }

    public function get_db_details( $instance_id = null, $force = false )
    {
        $this->reset_error();

        if( $instance_id != null
            and !self::valid_instance_id( $instance_id ))
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Invalid instance ID.' ) );
            return false;
        }

        if( $instance_id == null
        and !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !empty( $force )
            and !empty( self::$db_plugins[$instance_id] ) )
            unset( self::$db_plugins[$instance_id] );

        if( !empty( self::$db_plugins[$instance_id] ) )
            return self::$db_plugins[$instance_id];

        $check_arr = array();
        $check_arr['instance_id'] = $instance_id;

        db_supress_errors( $this->get_db_connection() );
        if( !($db_details = $this->get_details_fields( $check_arr )) )
        {
            db_restore_errors_state( $this->get_db_connection() );

            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Couldn\'t find plugin settings in database. Try re-installing plugin.' ) );

            return false;
        }

        db_restore_errors_state( $this->get_db_connection() );

        self::$db_plugins[$instance_id] = $db_details;

        return $db_details;
    }

    public function update_db_details( $fields_arr )
    {
        if( empty( $fields_arr ) or !is_array( $fields_arr )
         or empty( $fields_arr['instance_id'] )
         or !($instance_details = self::valid_instance_id( $fields_arr['instance_id'] ))
         or !($params = $this->fetch_default_flow_params()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unknown instance database details.' ) );
            return false;
        }

        $check_arr = array();
        $check_arr['instance_id'] = $fields_arr['instance_id'];

        $check_params = array();
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        $params['fields'] = $fields_arr;

        if( !($existing_arr = $this->get_details_fields( $check_arr, $check_params )) )
        {
            $existing_arr = false;
            $params['action'] = 'insert';
        } else
        {
            $params['action'] = 'edit';
        }

        PHS_Logger::logf( 'Plugins model action ['.$params['action'].'] on plugin ['.$fields_arr['instance_id'].']', PHS_Logger::TYPE_INFO );

        if( !($validate_fields = $this->validate_data_for_fields( $params ))
         or empty( $validate_fields['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_DB_DETAILS, self::_t( 'Error validating plugin database fields.' ) );
            return false;
        }

        $new_fields_arr = $validate_fields['data_arr'];
        // Try updating settings...
        if( !empty( $new_fields_arr['settings'] )
        and !empty( $existing_arr ) and !empty( $existing_arr['settings'] ) )
        {
            $new_fields_arr['settings'] = PHS_line_params::to_string( self::validate_array_to_new_array( PHS_line_params::parse_string( $existing_arr['settings'] ), PHS_line_params::parse_string( $new_fields_arr['settings'] ) ) );
            PHS_Logger::logf( 'New settings ['.$new_fields_arr['settings'].']', PHS_Logger::TYPE_INFO );
        }

        // Prevent core plugins to be inactivated...
        if( !empty( $new_fields_arr['is_core'] ) and !empty( $new_fields_arr['status'] ) )
            $new_fields_arr['status'] = self::STATUS_ACTIVE;

        $details_arr = array();
        $details_arr['fields'] = $new_fields_arr;

        if( empty( $existing_arr ) )
            $plugin_arr = $this->insert( $details_arr );
        else
            $plugin_arr = $this->edit( $existing_arr, $details_arr );

        if( empty( $plugin_arr ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t save plugin details to database.' ) );

            PHS_Logger::logf( '!!! Error in plugins model action ['.$params['action'].'] on plugin ['.$fields_arr['instance_id'].'] ['.$this->get_error_message().']', PHS_Logger::TYPE_INFO );

            return false;
        }

        PHS_Logger::logf( 'DONE Plugins model action ['.$params['action'].'] on plugin ['.$fields_arr['instance_id'].']', PHS_Logger::TYPE_INFO );

        $return_arr = array();
        $return_arr['old_data'] = $existing_arr;
        $return_arr['new_data'] = $plugin_arr;

        return $return_arr;
    }

    /**
     * Performs any necessary actions when updating model from $old_version to $new_version
     *
     * @param string $old_version Old version of model
     * @param string $new_version New version of model
     *
     * @return bool true on success, false on failure
     */
    protected function update( $old_version, $new_version )
    {
        return true;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_insert_prepare_params( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_INSTALLED;

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid plugin status.' ) );
            return false;
        }

        if( empty( $params['fields']['instance_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a plugin id.' ) );
            return false;
        }

        $check_params = $params;
        $check_params['result_type'] = 'single';

        $check_arr = array();
        $check_arr['instance_id'] = $params['fields']['instance_id'];

        if( $this->get_details_fields( $check_arr, $check_params ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'There is already a plugin with this id in database.' ) );
            return false;
        }

        $now_date = date( self::DATETIME_DB );

        $params['fields']['status_date'] = $now_date;

        if( empty( $params['fields']['cdate'] ) or empty_db_date( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = $now_date;
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array $existing_arr Array with existing data
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params( $existing_arr, $params )
    {
        if( empty( $existing_arr ) or !is_array( $existing_arr )
         or empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['status'] )
        and !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid plugin status.' ) );
            return false;
        }

        if( !empty( $params['fields']['instance_id'] ) )
        {
            $check_params = $params;
            $check_params['result_type'] = 'single';

            $check_arr = array();
            $check_arr['instance_id'] = $params['fields']['instance_id'];
            $check_arr['id'] = array( 'check' => '!=', 'value' => $existing_arr['id'] );

            if( $this->get_details_fields( $check_arr, $check_params ) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'There is already a plugin with this id in database.' ) );
                return false;
            }
        }

        $now_date = date( self::DATETIME_DB );

        if( isset( $params['fields']['status'] )
        and empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $now_date;

        elseif( !empty( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['status_date'] ) );

        if( !empty( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    /**
     * Called right after finding a record in database in PHS_Model_Core_Base::insert_or_edit() with provided conditions. This helps unsetting some fields which should not
     * be passed to edit function in case we execute an edit.
     *
     * @param array $existing_arr Data which already exists in database (array with all database fields)
     * @param array $constrain_arr Conditional db fields
     * @param array $params Flow parameters
     *
     * @return array Returns modified parameters (if required)
     */
    protected function insert_or_edit_editing( $existing_arr, $constrain_arr, $params )
    {
        if( empty( $existing_arr ) or !is_array( $existing_arr )
         or empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['added_by'] ) )
            unset( $params['fields']['added_by'] );
        if( isset( $params['fields']['status'] ) )
            unset( $params['fields']['status'] );
        if( isset( $params['fields']['status_date'] ) )
            unset( $params['fields']['status_date'] );
        if( isset( $params['fields']['cdate'] ) )
            unset( $params['fields']['cdate'] );

        return $params;
    }

    final public function check_install_plugins_db()
    {
        static $check_result = null;

        if( $check_result !== null )
            return $check_result;

        if( $this->check_table_exists() )
        {
            $check_result = true;
            return true;
        }

        $this->reset_error();

        $check_result = $this->install();

        return $check_result;
    }

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    final protected function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'plugins':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'instance_id' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ),
                    'type' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '100',
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ),
                    'plugin' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '100',
                        'nullable' => true,
                        'editable' => false,
                        'index' => true,
                    ),
                    'added_by' => array(
                        'type' => self::FTYPE_INT,
                        'editable' => false,
                    ),
                    'is_core' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'editable' => false,
                        'index' => true,
                    ),
                    'settings' => array(
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ),
                    'version' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '30',
                        'nullable' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                        'editable' => false,
                    ),
                );
            break;
        }

        return $return_arr;
    }

    public function force_install()
    {
        $this->install();

        if( !($signal_result = $this->signal_trigger( self::SIGNAL_FORCE_INSTALL )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSTALL, self::_t( 'Error when triggering force install signal.' ) );

            return false;
        }

        if( !empty( $signal_result['error_arr'] ) and is_array( $signal_result['error_arr'] ) )
        {
            $this->copy_error_from_array( $signal_result['error_arr'], self::ERR_FORCE_INSTALL );
            return false;
        }

        return true;
    }

}
