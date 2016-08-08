<?php

namespace phs\plugins\messages\models;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use \phs\PHS;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_params;

class PHS_Model_Messages extends PHS_Model
{
    const ERR_READ = 10000, ERR_WRITE = 10001;

    const DEST_TYPE_HANDLERS = 1, DEST_TYPE_USER = 2, DEST_TYPE_LEVEL = 3, DEST_TYPE_ROLE = 4, DEST_TYPE_ROLE_UNIT = 5;
    protected static $DEST_TYPES_ARR = array(
        self::DEST_TYPE_HANDLERS => array( 'title' => 'Handlers list' ),
        self::DEST_TYPE_USER => array( 'title' => 'User' ),
        self::DEST_TYPE_LEVEL => array( 'title' => 'Level' ),
        self::DEST_TYPE_ROLE => array( 'title' => 'Role' ),
        self::DEST_TYPE_ROLE_UNIT => array( 'title' => 'Role unit' ),
    );

    const TYPE_NORMAL = 1;
    protected static $TYPES_ARR = array(
        self::TYPE_NORMAL => array( 'title' => 'Normal' ),
    );

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private static $_accounts_model = false;

    /** @var bool|\phs\system\core\models\PHS_Model_Roles $_roles_model */
    private static $_roles_model = false;

    /** @var bool|\phs\plugins\messages\PHS_Plugin_Messages $_messages_plugin */
    private static $_messages_plugin = false;

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
        return array( 'messages', 'messages_body', 'messages_users' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'messages_users';
    }

    public function get_settings_structure()
    {
        return array(
            'roles_cache_size' => array(
                'display_name' => 'Role cache size',
                'display_hint' => 'How many records to read from roles table. Increase this value if you use more roles.',
                'type' => PHS_params::T_INT,
                'default' => 1000,
            ),
            'units_cache_size' => array(
                'display_name' => 'Role units cache size',
                'display_hint' => 'How many records to read from role units table. Increase this value if you use more role units.',
                'type' => PHS_params::T_INT,
                'default' => 1000,
            ),
        );
    }

    private function load_dependencies()
    {
        if( empty( self::$_accounts_model ) )
        {
            if( !(self::$_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading accounts model.' ) );
                return false;
            }
        }

        if( empty( self::$_roles_model ) )
        {
            if( !(self::$_roles_model = PHS::load_model( 'roles' )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading roles model.' ) );
                return false;
            }
        }

        if( empty( self::$_messages_plugin ) )
        {
            if( !(self::$_messages_plugin = $this->get_plugin_instance()) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading messages plugin.' ) );
                return false;
            }
        }

        return true;
    }

    public function get_summary_listing( $hook_args, $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for summary listing of messages.' ) );
            return false;
        }

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_messages_summary_hook_args() );

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        if( !($account_arr = $accounts_model->data_to_array( $account_data ))
         or !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_READ_MESSAGE ) )
            return array();

        $list_fields_arr = array();
        $list_fields_arr['user_id'] = $account_arr['id'];
        $list_fields_arr['is_author'] = 0;

        $list_arr = array();
        $list_arr['fields'] = $list_fields_arr;
        $list_arr['enregs_no'] = $hook_args['list_limit'];
        $list_arr['order_by'] = '`'.$this->get_flow_table_name( $flow_params ).'`.cdate DESC';
        $list_arr['group_by'] = '`'.$this->get_flow_table_name( $flow_params ).'`.thread_id';

        if( !($summary_list = $this->get_list( $list_arr )) )
            return array();

        return $summary_list;
    }

    public static function default_full_message_data()
    {
        return array(
            'message' => false,
            'message_user' => false,
            'account_data' => false,
            'message_body' => false,
        );
    }

    public static function is_full_message_data( $full_message_data )
    {
        if( empty( $full_message_data ) or !is_array( $full_message_data )
         or empty( $full_message_data['message'] ) or !is_array( $full_message_data['message'] )
         or empty( $full_message_data['message']['id'] )
         or empty( $full_message_data['message_body'] ) or !is_array( $full_message_data['message_body'] )
         or empty( $full_message_data['message_body']['id'] ) )
            return false;

        return true;
    }

    public function full_data_to_array( $full_message_data, $account_data = false )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        $message_data = false;
        $message_user = false;
        $message_body = false;
        if( is_numeric( $full_message_data ) )
            $message_data = $full_message_data;

        elseif( self::is_full_message_data( $full_message_data ) )
        {
            $message_data = $full_message_data['message'];
            $message_user = $full_message_data['message_user'];
            $account_data = $full_message_data['account_data'];
            $message_body = $full_message_data['message_body'];
        }

        if( empty( $message_data )
         or !($msg_body_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         or !($msg_users_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         or !($msg_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         or !($message_arr = $this->data_to_array( $message_data, $msg_flow )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t retrieve message data.' ) );
            return false;
        }

        $return_arr = self::default_full_message_data();
        $return_arr['message'] = $message_arr;

        $account_arr = false;
        if( !empty( $account_data )
        and !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t load account data of the message.' ) );
            return false;
        }

        $return_arr['account_data'] = $account_arr;

        if( empty( $message_body ) )
        {
            $check_arr = array();
            $check_arr['message_id'] = $message_arr['id'];

            if( !($message_body = $this->get_details_fields( $check_arr, $msg_body_flow )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t retrieve message body.' ) );
                return false;
            }
        }

        $return_arr['message_body'] = $message_body;

        if( !empty( $account_arr )
        and empty( $message_user ) )
        {
            $check_arr = array();
            $check_arr['message_id'] = $message_arr['id'];
            $check_arr['uid'] = $account_arr['id'];

            if( !($message_user = $this->get_details_fields( $check_arr, $msg_users_flow )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t retrieve user message details.' ) );
                return false;
            }

            $return_arr['message_user'] = $message_user;
        }

        return $return_arr;
    }

    public function is_sticky( $record_data )
    {
        if( !($full_message_arr = $this->full_data_to_array( $record_data ))
         or empty( $full_message_arr['message']['sticky'] ) )
        {
            $this->reset_error();
            return false;
        }

        return $full_message_arr;
    }

    public function is_new( $record_data )
    {
        if( !($full_message_arr = $this->full_data_to_array( $record_data ))
         or empty( $full_message_arr['message_user']['is_new'] ) )
        {
            $this->reset_error();
            return false;
        }

        return $full_message_arr;
    }

    final public function get_dest_types()
    {
        static $dest_types_arr = array();

        if( !empty( $dest_types_arr ) )
            return $dest_types_arr;

        $dest_types_arr = array();
        // Translate and validate statuses...
        foreach( self::$DEST_TYPES_ARR as $type_id => $type_arr )
        {
            $type_id = intval( $type_id );
            if( empty( $type_id ) )
                continue;

            if( empty( $type_arr['title'] ) )
                $type_arr['title'] = $this->_pt( 'Destination type %s', $type_id );
            else
                $type_arr['title'] = $this->_pt( $type_arr['title'] );

            $dest_types_arr[$type_id] = array(
                'title' => $type_arr['title']
            );
        }

        return $dest_types_arr;
    }

    final public function get_dest_types_as_key_val()
    {
        static $dest_types_key_val_arr = false;

        if( $dest_types_key_val_arr !== false )
            return $dest_types_key_val_arr;

        $dest_types_key_val_arr = array();
        if( ($dest_types_arr = $this->get_dest_types()) )
        {
            foreach( $dest_types_arr as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $dest_types_key_val_arr[$key] = $val['title'];
            }
        }

        return $dest_types_key_val_arr;
    }

    public function valid_dest_type( $dest_type )
    {
        $all_dest_types = $this->get_dest_types();
        if( empty( $dest_type )
         or empty( $all_dest_types[$dest_type] ) )
            return false;

        return $all_dest_types[$dest_type];
    }

    final public function get_types()
    {
        static $types_arr = array();

        if( !empty( $types_arr ) )
            return $types_arr;

        $types_arr = array();
        // Translate and validate statuses...
        foreach( self::$TYPES_ARR as $type_id => $type_arr )
        {
            $type_id = intval( $type_id );
            if( empty( $type_id ) )
                continue;

            if( empty( $type_arr['title'] ) )
                $type_arr['title'] = $this->_pt( 'Type %s', $type_id );
            else
                $type_arr['title'] = $this->_pt( $type_arr['title'] );

            $types_arr[$type_id] = array(
                'title' => $type_arr['title']
            );
        }

        return $types_arr;
    }

    final public function get_types_as_key_val()
    {
        static $types_key_val_arr = false;

        if( $types_key_val_arr !== false )
            return $types_key_val_arr;

        $types_key_val_arr = array();
        if( ($types_arr = $this->get_types()) )
        {
            foreach( $types_arr as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $types_key_val_arr[$key] = $val['title'];
            }
        }

        return $types_key_val_arr;
    }

    public function valid_type( $type )
    {
        $all_types = $this->get_types();
        if( empty( $type )
         or empty( $all_types[$type] ) )
            return false;

        return $all_types[$type];
    }

    public function get_accounts_from_handlers( $dest_handlers )
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $account_details_model */
        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $account_details_model */
        if( !($account_details_model = PHS::load_model( 'account_details', 'accounts' ))
         or !($ad_flow_params = $account_details_model->fetch_default_flow_params()) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading depedencies.' ) );
            return false;
        }

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        $return_arr = array();
        $return_arr['invalid_handlers'] = array();
        $return_arr['result_list'] = array();

        if( empty( $dest_handlers ) or !is_string( $dest_handlers ) )
            return $return_arr;

        $dest_handlers_arr = explode( ',', $dest_handlers );
        $clean_handlers_arr = array();
        $sql_handlers_arr = array();
        foreach( $dest_handlers_arr as $handler )
        {
            $handler = trim( $handler );
            if( empty( $handler ) )
                continue;

            $clean_handlers_arr[$handler] = true;
            $sql_handlers_arr[] = db_escape( $handler, $ad_flow_params['db_connection'] );
        }

        $list_fields_arr = array();
        $list_fields_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER] = array( 'check' => 'IN', 'value' => '(\''.implode( '\',\'', $sql_handlers_arr ).'\')' );

        $list_arr = array();
        $list_arr['fields'] = $list_fields_arr;

        if( !($ud_list_arr = $account_details_model->get_list( $list_arr )) )
        {
            $return_arr['invalid_handlers'] = array_keys( $clean_handlers_arr );

            return array();
        }

        $ids_arr = array();
        $ids_to_handler_arr = array();
        $invalid_handlers_arr = $clean_handlers_arr;
        foreach( $ud_list_arr as $ud_id => $ud_arr )
        {
            if( empty( $ud_arr['uid'] )
             or empty( $ud_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER] ) )
                continue;

            $ids_arr[$ud_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER]] = $ud_arr['uid'];
            $ids_to_handler_arr[$ud_arr['uid']] = $ud_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER];
        }

        $list_fields_arr = array();
        $list_fields_arr[] = array( 'check' => 'IN', 'value' => '(\''.implode( '\',\'', $sql_handlers_arr ).'\')' );

        $list_arr = array();
        $list_arr['fields'] = $list_fields_arr;

        if( !($ud_list_arr = $accounts_model->get_list( $list_arr )) )
        {
            $return_arr['invalid_handlers'] = array_keys( $clean_handlers_arr );

            return array();
        }

        return $return_arr;
    }

    public function write_message( $params )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        $accounts_model = self::$_accounts_model;

        if( empty( $params ) or !is_array( $params ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid parameters when saving message in database.' ) );
            return false;
        }

        if( empty( $params['dest_type'] )
         or !($dest_type = $this->valid_dest_type( $params['dest_type'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid destination type.' ) );
            return false;
        }

        switch( $params['dest_type'] )
        {
            case self::DEST_TYPE_USER:
                if( empty( $params['dest_id'] )
                and empty( $params['dest_handlers'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Unknown destination account.' ) );
                    return false;
                }

                if( !($destination_list = $this->get_accounts_from_handlers( $params['dest_handlers'] )) )
            break;
        }
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
            case 'messages':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'thread_id' => array(
                        'type' => self::FTYPE_INT,
                        'default' => 0,
                        'index' => true,
                        'comment' => 'Id of first message in conversation',
                    ),
                    'body_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'dest_type' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'User, Level, Role, Role unit',
                    ),
                    'dest_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'from_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => '0 - system, X - User ID',
                    ),
                    'title' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'type' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'Normal (can be extended) Link to other data',
                    ),
                    'type_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'importance' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'Low, Normal, High',
                    ),
                    'sticky' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'If this message should be presented in top of list',
                    ),
                    'can_reply' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'comment' => 'User can reply to this message',
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;

            case 'messages_body':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'message_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'body' => array(
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                        'default' => null,
                    ),
                );
            break;

            case 'messages_users':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'user_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'message_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'thread_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'is_new' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'Message was read or not',
                    ),
                    'email_sent' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'Email alert sent for this message',
                    ),
                    'is_author' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                        'comment' => 'user is author of this message (sent folder)',
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
