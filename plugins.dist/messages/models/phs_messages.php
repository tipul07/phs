<?php

namespace phs\plugins\messages\models;

use \phs\PHS;
use \phs\PHS_Bg_jobs;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Line_params;

class PHS_Model_Messages extends PHS_Model
{
    const CUSTOM_SETTINGS_KEY = '{custom_settings}';

    const ERR_READ = 10000, ERR_WRITE = 10001;

    const DEST_TYPE_USERS_IDS = 1, DEST_TYPE_HANDLERS = 2, DEST_TYPE_USERS = 3, DEST_TYPE_LEVEL = 4,
          DEST_TYPE_ROLE = 5, DEST_TYPE_ROLE_UNIT = 6;
    protected static $DEST_TYPES_ARR = array(
        self::DEST_TYPE_USERS_IDS => array( 'title' => 'User IDs' ),
        self::DEST_TYPE_HANDLERS => array( 'title' => 'Handlers list' ),
        self::DEST_TYPE_USERS => array( 'title' => 'User nicknames list' ),
        self::DEST_TYPE_LEVEL => array( 'title' => 'User level' ),
        self::DEST_TYPE_ROLE => array( 'title' => 'Role' ),
        self::DEST_TYPE_ROLE_UNIT => array( 'title' => 'Role unit' ),
    );

    const TYPE_NORMAL = 'msg_normal';
    protected static $TYPES_ARR = array(
        self::TYPE_NORMAL => array( 'title' => 'Normal' ),
    );

    const IMPORTANCE_LOW = 1, IMPORTANCE_NORMAL = 2, IMPORTANCE_HIGH = 3;
    protected static $IMPORTANCE_ARR = array(
        self::IMPORTANCE_LOW => array( 'title' => 'Low' ),
        self::IMPORTANCE_NORMAL => array( 'title' => 'Normal' ),
        self::IMPORTANCE_HIGH => array( 'title' => 'High' ),
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
        return '1.1.0';
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
    public function get_main_table_name()
    {
        return 'messages_users';
    }

    public function get_settings_structure()
    {
        return array(
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

    public function get_relative_account_message_handler( $account_data, $current_user = false )
    {
        if( empty( $account_data ) )
            return $this->_pt( 'System' );

        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        if( empty( $current_user )
         || !($current_user_arr = $accounts_model->data_to_array( $current_user )))
            return $this->get_account_message_handler( $account_data );

        if( empty( $account_data )
         || !($account_arr = $accounts_model->data_to_array( $account_data )))
            return false;

        if( (int)$current_user_arr['id'] === (int)$account_arr['id'] )
            return $this->_pt( 'You' );

        return $this->get_account_message_handler( $account_arr );
    }

    public function get_account_message_handler( $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        if( empty( $account_data )
         || !($account_details_arr = $accounts_model->get_account_details( $account_data ))
         || empty( $account_details_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER] ) )
            return false;

        return $account_details_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER];
    }

    public function get_new_messages_count( $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for summary listing of messages.' ) );
            return false;
        }

        $accounts_model = self::$_accounts_model;

        if( empty( $account_data)
         || !($account_arr = $accounts_model->data_to_array( $account_data ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params )) )
            return 0;

        $list_fields_arr = array();
        $list_fields_arr['`'.$mu_table_name.'`.user_id'] = $account_arr['id'];
        $list_fields_arr['`'.$mu_table_name.'`.is_author'] = 0;
        $list_fields_arr['`'.$mu_table_name.'`.is_new'] = 1;

        $list_arr = $mu_flow_params;
        $list_arr['fields'] = $list_fields_arr;
        $list_arr['count_field'] = '`'.$mu_table_name.'`.thread_id';

        if( !($new_messages_count = $this->get_count( $list_arr )) )
            return 0;

        return $new_messages_count;
    }

    public function get_total_messages_count( $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for summary listing of messages.' ) );
            return false;
        }

        $accounts_model = self::$_accounts_model;

        if( empty( $account_data)
         || !($account_arr = $accounts_model->data_to_array( $account_data ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params )) )
            return 0;

        $list_fields_arr = array();
        $list_fields_arr['`'.$mu_table_name.'`.user_id'] = $account_arr['id'];
        $list_fields_arr['`'.$mu_table_name.'`.is_author'] = 0;

        $list_arr = $mu_flow_params;
        $list_arr['fields'] = $list_fields_arr;
        $list_arr['count_field'] = '`'.$mu_table_name.'`.thread_id';

        if( !($new_messages_count = $this->get_count( $list_arr )) )
            return 0;

        return $new_messages_count;
    }

    public function get_summary_listing( $hook_args, $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for summary listing of messages.' ) );
            return false;
        }

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_messages_summary_hook_args() );

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        if( !($account_arr = $accounts_model->data_to_array( $account_data ))
         || !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_READ_MESSAGE )
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params ))
         || !($m_table_name = $this->get_flow_table_name( $m_flow_params )) )
            return array();

        $list_fields_arr = array();
        $list_fields_arr['`'.$mu_table_name.'`.user_id'] = $account_arr['id'];
        $list_fields_arr['`'.$mu_table_name.'`.is_author'] = 0;

        $list_arr = $mu_flow_params;
        $list_arr['fields'] = $list_fields_arr;
        $list_arr['join_sql'] = ' LEFT JOIN `'.$m_table_name.'` ON `'.$mu_table_name.'`.message_id = `'.$m_table_name.'`.id ';
        $list_arr['db_fields'] = 'MAX(`'.$m_table_name.'`.id) AS m_id, `'.$m_table_name.'`.*, MAX(`'.$m_table_name.'`.cdate) AS m_cdate, MAX(`'.$mu_table_name.'`.id) AS mu_id, `'.$mu_table_name.'`.* ';
        $list_arr['enregs_no'] = $hook_args['list_limit'];
        $list_arr['order_by'] = '`'.$m_table_name.'`.sticky ASC, `'.$mu_table_name.'`.cdate DESC';
        $list_arr['group_by'] = '`'.$mu_table_name.'`.thread_id';

        if( !($summary_list = $this->get_list( $list_arr )) )
            return array();

        $return_arr = array();
        $full_message_fields = self::default_full_message_data();
        foreach( $summary_list as $mid => $message_fields )
        {
            if( empty( $message_fields ) || !is_array( $message_fields ) )
                continue;

            $message_arr = array();
            $message_user_arr = array();
            $last_val = '';
            foreach( $message_fields as $key => $val )
            {
                if( $key == 'id' )
                    continue;

                if( $key == 'm_cdate' )
                {
                    $message_arr['cdate'] = $val;
                    continue;
                }

                if( $key == 'm_id' || $key == 'mu_id' )
                {
                    if( $key == 'm_id' )
                        $message_arr['id'] = $val;
                    elseif( $key == 'mu_id' )
                        $message_user_arr['id'] = $val;

                    $last_val = $key;
                    continue;
                }

                if( $last_val == 'm_id' )
                    $message_arr[$key] = $val;
                elseif( $last_val == 'mu_id' )
                    $message_user_arr[$key] = $val;
            }

            if( isset( $message_arr['cdate'] ) )
                $message_user_arr['cdate'] = $message_arr['cdate'];

            $full_message_arr = $this->emulate_full_message( $message_user_arr, $message_arr, $account_arr );

            $return_arr[$message_arr['id']] = $full_message_arr;
        }

        return $return_arr;
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
        if( empty( $full_message_data ) || !is_array( $full_message_data )
         || empty( $full_message_data['message'] ) || !is_array( $full_message_data['message'] )
         || empty( $full_message_data['message']['id'] )
         || empty( $full_message_data['message_body'] ) || !is_array( $full_message_data['message_body'] )
         || empty( $full_message_data['message_body']['id'] ) )
            return false;

        return true;
    }

    public function emulate_full_message( $message_user_data, $message_data = false, $account_data = false )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params ))
         || !($m_table_name = $this->get_flow_table_name( $m_flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for message emulation.' ) );
            return false;
        }

        if( empty( $message_user_data )
         || !($message_user_arr = $this->data_to_array( $message_user_data, $mu_flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t find message details in database.' ) );
            return false;
        }

        if( empty( $account_data ) )
            $account_data = $message_user_arr['user_id'];
        if( empty( $message_data ) )
            $message_data = $message_user_arr['message_id'];

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        if( empty( $message_data )
         || !($message_arr = $this->data_to_array( $message_data, $m_flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load message details from database.' ) );
            return false;
        }

        if( empty( $account_data )
         || !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load account details from database.' ) );
            return false;
        }

        if( ($new_message_arr = $this->populate_message_custom_settings( $message_arr )) )
            $message_arr = $new_message_arr;

        $return_arr = self::default_full_message_data();
        $return_arr['message'] = $message_arr;
        $return_arr['message_user'] = $message_user_arr;
        $return_arr['account_data'] = $account_data;
        $return_arr['message_body'] = array(
            'id' => $message_arr['body_id'],
            'message_id' => $message_user_arr['message_id'],
            'body' => $this->_pt( 'Message body not available yet...' ),
        );

        return $return_arr;
    }

    public function populate_message_custom_settings( $message_data )
    {
        if( !($msg_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($message_arr = $this->data_to_array( $message_data, $msg_flow )) )
            return false;

        if( !isset( $message_arr[self::CUSTOM_SETTINGS_KEY] ) )
        {
            if( empty( $message_arr['custom_settings'] ) )
                $custom_settings_arr = array();
            else
                $custom_settings_arr = PHS_Line_params::parse_string( $message_arr['custom_settings'] );

            $message_arr[self::CUSTOM_SETTINGS_KEY] = $custom_settings_arr;
        }

        return $message_arr;
    }

    public function get_message_custom_settings( $message_data, $key = false )
    {
        if( !($message_arr = $this->populate_message_custom_settings( $message_data )) )
            return null;

        if( $key !== false )
        {
            if( !isset( $message_arr[self::CUSTOM_SETTINGS_KEY][$key] ) )
                return null;

            return $message_arr[self::CUSTOM_SETTINGS_KEY][$key];
        }

        return $message_arr[self::CUSTOM_SETTINGS_KEY];
    }

    public function full_data_to_array( $full_message_data, $account_data = false, $params = false )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( empty( $params ) || !is_array( $params ) )
            $params = array();

        if( empty( $params['ignore_user_message'] ) )
            $params['ignore_user_message'] = false;
        else
            $params['ignore_user_message'] = true;

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
         || !($msg_body_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         || !($msg_users_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($msg_flow = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($message_arr = $this->data_to_array( $message_data, $msg_flow )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Couldn\'t retrieve message data.' ) );
            return false;
        }

        if( ($new_message_arr = $this->populate_message_custom_settings( $message_arr )) )
            $message_arr = $new_message_arr;

        $return_arr = self::default_full_message_data();
        $return_arr['message'] = $message_arr;

        $accounts_model = self::$_accounts_model;

        $account_arr = false;
        if( !empty( $account_data )
         && !($account_arr = $accounts_model->data_to_array( $account_data )) )
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
         && empty( $message_user ) )
        {
            $check_arr = array();
            $check_arr['message_id'] = $message_arr['id'];
            $check_arr['user_id'] = $account_arr['id'];

            if( !($message_user = $this->get_details_fields( $check_arr, $msg_users_flow )) )
            {
                if( empty( $params['ignore_user_message'] ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t retrieve user message details.' ) );
                    return false;
                }

                $message_user = false;
            }
        }

        $return_arr['message_user'] = $message_user;

        return $return_arr;
    }

    /**
     * Tells if given account is destination for given message. This method should not generate errors as it is used as helper for other methods.
     *
     * @param int|array $record_data Id of message or full message
     * @param int|array $account_data Id of account or account array
     *
     * @return array|bool Tells if given account is destination for given message
     **/
    public function account_is_destination( $record_data, $account_data )
    {
        $this->reset_error();

        if( empty( $record_data ) || empty( $account_data )
         || !($full_message_arr = $this->full_data_to_array( $record_data, $account_data )) )
        {
            $this->reset_error();
            return false;
        }

        if( empty( $full_message_arr['message_user'] )
         || !empty( $full_message_arr['message_user']['is_author'] ) )
            return false;

        return $full_message_arr;
    }

    /**
     * Tells if given account is author of given message. This method should not generate errors as it is used as helper for other methods.
     *
     * @param int|array $record_data Id of message or full message
     * @param int|array $account_data Id of account or account array
     *
     * @return array|bool Tells if given account is author of given message
     **/
    public function account_is_author( $record_data, $account_data )
    {
        $this->reset_error();

        if( empty( $record_data ) || empty( $record_data )
         || !($full_message_arr = $this->full_data_to_array( $record_data, $account_data )) )
        {
            $this->reset_error();
            return false;
        }

        if( empty( $full_message_arr['message_user']['is_author'] ) )
            return false;

        return $full_message_arr;
    }

    /**
     * @param int|array $record_data
     * @param bool|array $params
     *
     * @return false|array
     */
    public function can_reply( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( empty( $params ) || !is_array( $params ) )
            $params = array();

        if( empty( $params['account_data'] ) )
            $params['account_data'] = false;

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        $account_arr = false;
        if( !empty( $params['account_data'] )
         && (!($account_arr = $accounts_model->data_to_array( $params['account_data'] ))
                || !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_REPLY_MESSAGE )
            ) )
            return false;

        if( !($full_message_arr = $this->full_data_to_array( $record_data, $account_arr )) )
        {
            if( empty( $account_arr )
             || !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_CAN_REPLY_TO_ALL )
             || !($full_message_arr = $this->full_data_to_array( $record_data, $account_arr, array( 'ignore_user_message' => true ) )) )
            {
                $this->reset_error();
                return false;
            }
        }

        if( empty( $full_message_arr )
         || empty( $full_message_arr['message']['from_uid'] )
         || empty( $full_message_arr['message']['can_reply'] ) )
        {
            $this->reset_error();
            return false;
        }

        if( !empty( $account_arr )
         && !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_CAN_REPLY_TO_ALL )
         && !$this->account_is_destination( $full_message_arr, $account_arr ) )
        {
            $this->reset_error();
            return false;
        }

        return $full_message_arr;
    }

    /**
     * @param int|array $record_data
     * @param bool|array $params
     *
     * @return false|array
     */
    public function can_followup( $record_data, $params = false )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( empty( $params ) || !is_array( $params ) )
            $params = array();

        if( empty( $params['account_data'] ) )
            $params['account_data'] = false;

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        $account_arr = false;
        if( !empty( $params['account_data'] )
         && (!($account_arr = $accounts_model->data_to_array( $params['account_data'] ))
                || !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_FOLLOWUP_MESSAGE )
            ) )
            return false;

        if( !($full_message_arr = $this->full_data_to_array( $record_data, $account_arr )) )
        {
            if( empty( $account_arr )
             || !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_CAN_REPLY_TO_ALL )
             || !($full_message_arr = $this->full_data_to_array( $record_data, $account_arr, array( 'ignore_user_message' => true ) )) )
            {
                $this->reset_error();
                return false;
            }
        }

        if( empty( $full_message_arr )
         || empty( $full_message_arr['message']['from_uid'] ) )
        {
            $this->reset_error();
            return false;
        }

        if( !empty( $account_arr )
         && !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_CAN_REPLY_TO_ALL )
         && !$this->account_is_author( $full_message_arr, $account_arr ) )
        {
            $this->reset_error();
            return false;
        }

        return $full_message_arr;
    }

    /**
     * @param int|array $message_user_data
     *
     * @return array|bool
     */
    public function mark_as_read( $message_user_data )
    {
        $this->reset_error();

        if( !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($message_user_arr = $this->data_to_array( $message_user_data, $mu_flow_params ))
         || empty( $message_user_arr['id'] ) )
        {
            $this->reset_error();
            return false;
        }

        $edit_fields_arr = array();
        $edit_fields_arr['is_new'] = 0;

        $edit_arr = $mu_flow_params;
        $edit_arr['fields'] = $edit_fields_arr;

        if( !($new_message_user_arr = $this->edit( $message_user_arr, $edit_arr )) )
            return false;

        return $new_message_user_arr;
    }

    /**
     * @param int|array $message_data
     *
     * @return array|bool
     */
    public function need_write_finish( $message_data )
    {
        $this->reset_error();

        if( !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($message_arr = $this->data_to_array( $message_data, $m_flow_params ))
         || !empty( $message_arr['thread_id'] ) )
        {
            $this->reset_error();
            return false;
        }

        return $message_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return array|bool
     */
    public function is_sticky( $record_data )
    {
        $this->reset_error();

        if( !($full_message_arr = $this->full_data_to_array( $record_data ))
         || empty( $full_message_arr['message']['sticky'] ) )
        {
            $this->reset_error();
            return false;
        }

        return $full_message_arr;
    }

    /**
     * @param int|array $record_data
     *
     * @return array|bool
     */
    public function is_new( $record_data )
    {
        if( !($full_message_arr = $this->full_data_to_array( $record_data ))
         || empty( $full_message_arr['message_user']['is_new'] ) )
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
            $type_id = (int)$type_id;
            if( empty( $type_id ) )
                continue;

            if( empty( $type_arr['title'] ) )
                $type_arr['title'] = $this->_pt( 'Destination type %s', $type_id );
            else
                $type_arr['title'] = $this->_pt( $type_arr['title'] );

            $dest_types_arr[$type_id] = $type_arr;
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
         || empty( $all_dest_types[$dest_type] ) )
            return false;

        return $all_dest_types[$dest_type];
    }

    final public function get_types()
    {
        static $types_arr = array();

        if( !empty( $types_arr ) )
            return $types_arr;

        $new_types_arr = self::$TYPES_ARR;
        $hook_args = PHS_Hooks::default_message_types_hook_args();
        $hook_args['types_arr'] = $new_types_arr;

        if( ($extra_types_arr = PHS::trigger_hooks( PHS_Hooks::H_MSG_TYPES, $hook_args ))
         && is_array( $extra_types_arr ) && !empty( $extra_types_arr['types_arr'] ) )
            $new_types_arr = self::merge_array_assoc( $extra_types_arr['types_arr'], $new_types_arr );

        $types_arr = array();
        // Translate and validate types...
        if( !empty( $new_types_arr ) && is_array( $new_types_arr ) )
        {
            foreach( $new_types_arr as $type_id => $type_arr )
            {
                if( empty( $type_id ) )
                    continue;

                if( empty( $type_arr['title'] ) )
                    $type_arr['title'] = $this->_pt( 'Type %s', $type_id );
                else
                    $type_arr['title'] = $this->_pt( $type_arr['title'] );

                $types_arr[$type_id] = $type_arr;
            }
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
         || empty( $all_types[$type] ) )
            return false;

        return $all_types[$type];
    }

    final public function get_importances()
    {
        static $importances_arr = array();

        if( !empty( $importances_arr ) )
            return $importances_arr;

        $importances_arr = array();
        // Translate and validate types...
        foreach( self::$IMPORTANCE_ARR as $imp_id => $imp_arr )
        {
            $imp_id = (int)$imp_id;
            if( empty( $imp_id ) )
                continue;

            if( empty( $imp_arr['title'] ) )
                $imp_arr['title'] = $this->_pt( 'Importance %s', $imp_id );
            else
                $imp_arr['title'] = $this->_pt( $imp_arr['title'] );

            $importances_arr[$imp_id] = $imp_arr;
        }

        return $importances_arr;
    }

    final public function get_importances_as_key_val()
    {
        static $importances_key_val_arr = false;

        if( $importances_key_val_arr !== false )
            return $importances_key_val_arr;

        $importances_key_val_arr = array();
        if( ($all_importances = $this->get_importances()) )
        {
            foreach( $all_importances as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $importances_key_val_arr[$key] = $val['title'];
            }
        }

        return $importances_key_val_arr;
    }

    public function valid_importance( $importance )
    {
        $all_importances = $this->get_importances();
        if( empty( $importance )
         || empty( $all_importances[$importance] ) )
            return false;

        return $all_importances[$importance];
    }

    public function check_orphan_thread( $thread_id )
    {
        if( !empty( $thread_id ) )
            $thread_id = (int)$thread_id;

        if( empty( $thread_id )
         || !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($m_table_name = $this->get_flow_table_name( $m_flow_params ))
         || !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params ))
         || !($mb_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         || !($mb_table_name = $this->get_flow_table_name( $mb_flow_params )) )
            return false;

        if( ($qid = db_query( 'SELECT * FROM `'.$m_table_name.'` WHERE thread_id = \''.$thread_id.'\'', $m_flow_params['db_connection'] ))
         && @mysqli_num_rows( $qid ) )
        {
            while( ($message_arr = @mysqli_fetch_assoc( $qid )) )
            {
                if( ($tmp_qid = db_query( 'SELECT 1 FROM `'.$mu_table_name.'` WHERE message_id = \''.$message_arr['id'].'\' AND user_id != 0 LIMIT 0, 1', $mu_flow_params['db_connection'] ))
                 && @mysqli_num_rows( $tmp_qid ) )
                    continue;

                db_query( 'DELETE FROM `'.$m_table_name.'` WHERE id = \''.$message_arr['id'].'\'', $m_flow_params['db_connection'] );
                db_query( 'DELETE FROM `'.$mu_table_name.'` WHERE message_id = \''.$message_arr['id'].'\'', $mu_flow_params['db_connection'] );
                db_query( 'DELETE FROM `'.$mb_table_name.'` WHERE message_id = \''.$message_arr['id'].'\'', $mb_flow_params['db_connection'] );
            }
        }

        return true;
    }

    public function act_delete_thread( $message_user_data )
    {
        $this->reset_error();

        if( empty( $message_user_data )
         || !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params ))
         || !($message_user_arr = $this->data_to_array( $message_user_data, $mu_flow_params ))
         || empty( $message_user_arr['thread_id'] ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t find message details in database.' ) );
            return false;
        }

        db_query( 'DELETE FROM `'.$mu_table_name.'` WHERE thread_id = \''.$message_user_arr['thread_id'].'\' AND user_id = \''.$message_user_arr['user_id'].'\'', $mu_flow_params['db_connection'] );

        $this->check_orphan_thread( $message_user_arr['thread_id'] );

        return $message_user_arr;
    }

    public function act_mark_as_read_thread( $message_user_data )
    {
        $this->reset_error();

        if( empty( $message_user_data )
         || !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params ))
         || !($message_user_arr = $this->data_to_array( $message_user_data, $mu_flow_params ))
         || empty( $message_user_arr['thread_id'] ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t find message details in database.' ) );
            return false;
        }

        db_query( 'UPDATE `'.$mu_table_name.'` SET is_new = 0 WHERE thread_id = \''.$message_user_arr['thread_id'].'\' AND user_id = \''.$message_user_arr['user_id'].'\'', $mu_flow_params['db_connection'] );

        return $message_user_arr;
    }

    public function get_thread_messages_flow( $thread_id, $user_id = 0 )
    {
        if( !empty( $thread_id ) )
            $thread_id = (int)$thread_id;
        if( !empty( $user_id ) )
            $user_id = (int)$user_id;

        if( empty( $thread_id )
         || !($um_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($um_table_name = $this->get_flow_table_name( $um_flow_params )) )
            return array();

        if( !empty( $user_id ) )
            $extra_order_sql = ', (user_id = \''.$user_id.'\') DESC, is_author ASC';
        else
            $extra_order_sql = ', is_author DESC';

        if( !($qid = db_query( 'SELECT * FROM `'.$um_table_name.'` '.
                               ' WHERE thread_id = \''.$thread_id.'\' '.
                               ' ORDER BY message_id ASC, cdate ASC '.$extra_order_sql, $um_flow_params['db_connection'] ))
         || !@mysqli_num_rows( $qid ) )
            return array();

        $result_list_arr = array();
        $last_message_id = 0;
        while( ($user_message_arr = @mysqli_fetch_assoc( $qid )) )
        {
            if( (int)$user_message_arr['message_id'] !== $last_message_id )
            {
                $result_list_arr[$user_message_arr['id']] = $user_message_arr;
                $last_message_id = (int)$user_message_arr['message_id'];
            }
        }

        return $result_list_arr;
    }

    /**
     * @param int $account_id
     * @param bool|array $params
     *
     * @return array|bool|\mysqli_result
     */
    public function get_all_account_messages( $account_id, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = array();

        if( empty( $params['get_query_id'] ) )
            $params['get_query_id'] = false;
        else
            $params['get_query_id'] = true;

        $account_id = (int)$account_id;
        if( empty( $account_id ) )
        {
            if( empty( $params['return_qid'] ) )
                return array();

            return false;
        }

        if( !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($m_table_name = $this->get_flow_table_name( $m_flow_params ))
         || !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($mu_table_name = $this->get_flow_table_name( $mu_flow_params ))
         || !($mb_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         || !($mb_table_name = $this->get_flow_table_name( $mb_flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load required functionality.' ) );
            return false;
        }

        $list_arr = $mu_flow_params;
        $list_arr['fields'] = array();
        $list_arr['fields']['`'.$mu_table_name.'`.user_id'] = $account_id;

        $list_arr['join_sql'] = ' LEFT JOIN `'.$m_table_name.'` ON `'.$mu_table_name.'`.message_id = `'.$m_table_name.'`.id '.
                                ' LEFT JOIN `'.$mb_table_name.'` ON `'.$mu_table_name.'`.message_id = `'.$mb_table_name.'`.id ';

        $list_arr['db_fields'] = '`' . $m_table_name . '`.*,'.
                                 '`' . $mu_table_name . '`.*, '.
                                 'MAX(`' . $m_table_name . '`.id) AS m_id, '.
                                 'MAX(`' . $m_table_name . '`.cdate) AS m_cdate, '.
                                 'MAX(`' . $mu_table_name . '`.id) AS mu_id, '.
                                 'MAX(`' . $mu_table_name . '`.is_new) AS mu_is_new, '.
                                 'COUNT( `' . $mu_table_name . '`.id ) AS m_thread_count';

        $list_arr['order_by'] = 'm_cdate DESC';
        $list_arr['count_field'] = '`'.$mu_table_name.'`.thread_id';
        $list_arr['group_by'] = '`'.$mu_table_name.'`.thread_id';

        $list_arr['get_query_id'] = $params['get_query_id'];

        return $this->get_list( $list_arr );
    }

    public function get_accounts_from_handlers( $dest_handlers )
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $account_details_model */
        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $account_details_model */
        if( !($account_details_model = PHS::load_model( 'account_details', 'accounts' ))
         || !($ad_flow_params = $account_details_model->fetch_default_flow_params()) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading depedencies.' ) );
            return false;
        }

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;

        $return_arr = array();
        $return_arr['invalid_handlers'] = array();
        $return_arr['result_list'] = array();

        if( empty( $dest_handlers ) || !is_string( $dest_handlers ) )
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
             || empty( $ud_arr[$messages_plugin::UD_COLUMN_MSG_HANDLER] ) )
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

    public function get_destination_as_string( $message_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        $accounts_model = self::$_accounts_model;
        $roles_model = self::$_roles_model;

        if( !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for message.' ) );
            return false;
        }

        if( empty( $message_data )
         || !($message_arr = $this->data_to_array( $message_data, $m_flow_params )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Couldn\'t load message details from database.' ) );
            return false;
        }

        $destination_str = '';
        switch( $message_arr['dest_type'] )
        {
            case self::DEST_TYPE_USERS_IDS:
                $destination_str = 'IDs: '.$message_arr['dest_str'];
            break;

            case self::DEST_TYPE_USERS:
            case self::DEST_TYPE_HANDLERS:
                $destination_str = $message_arr['dest_str'];
            break;

            case self::DEST_TYPE_LEVEL:
                if( ($user_levels = $accounts_model->get_levels_as_key_val())
                 && !empty( $user_levels[$message_arr['dest_id']] ) )
                    $destination_str = $user_levels[$message_arr['dest_id']];
                else
                    $destination_str = '['.$this->_pt( 'Unknown user level' ).']';
            break;

            case self::DEST_TYPE_ROLE:
                if( ($roles_arr = $roles_model->get_all_roles())
                 && !empty( $roles_arr[$message_arr['dest_id']] ) )
                    $destination_str = $roles_arr[$message_arr['dest_id']]['name'];
                else
                    $destination_str = '['.$this->_pt( 'Unknown role' ).']';
            break;

            case self::DEST_TYPE_ROLE_UNIT:
                if( ($roles_units_arr = $roles_model->get_all_role_units())
                 && !empty( $roles_units_arr[$message_arr['dest_id']] ) )
                    $destination_str = $roles_units_arr[$message_arr['dest_id']]['name'];
                else
                    $destination_str = '['.$this->_pt( 'Unknown role unit' ).']';
            break;
        }

        return $destination_str;
    }

    public function prepare_custom_settings_arr( $settings_arr )
    {
        if( empty( $settings_arr ) || !is_array( $settings_arr ) )
            $settings_arr = array();

        return PHS_Line_params::to_string( $settings_arr );
    }

    public function write_message( $params )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;
        $roles_model = self::$_roles_model;

        if( !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($mb_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         || !($user_details_model = PHS::load_model( 'accounts_details', 'accounts' ))
         || !($users_details_flow_params = $user_details_model->fetch_default_flow_params( array( 'table_name' => 'users_details' ) ))
         || !($users_flow_params = $accounts_model->fetch_default_flow_params( array( 'table_name' => 'users' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for message.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid parameters when saving message in database.' ) );
            return false;
        }

        if( empty( $params['subject'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Please provide a subject for this message.' ) );
            return false;
        }

        if( empty( $params['custom_settings'] ) || !is_array( $params['custom_settings'] ) )
            $params['custom_settings'] = array();

        if( empty( $params['reply_message'] ) )
            $params['reply_message'] = false;
        if( empty( $params['followup_message'] ) )
            $params['followup_message'] = false;

        if( empty( $params['account_data'] ) )
            $params['account_data'] = false;
        if( !isset( $params['exclude_author'] ) )
            $params['exclude_author'] = true;

        if( empty( $params['body'] ) )
            $params['body'] = '';
        if( empty( $params['type'] ) )
            $params['type'] = self::TYPE_NORMAL;
        if( empty( $params['type_id'] ) )
            $params['type_id'] = 0;

        if( empty( $params['bg_job_params'] ) || !is_array( $params['bg_job_params'] ) )
            $params['bg_job_params'] = array();

        if( empty( $params['reply_message'] )
         || !($reply_message = $this->full_data_to_array( $params['reply_message'] )) )
            $reply_message = false;

        if( empty( $params['followup_message'] )
         || !($followup_message = $this->full_data_to_array( $params['followup_message'] )) )
            $followup_message = false;

        $account_arr = false;
        if( !empty( $params['account_data'] )
         && !($account_arr = $accounts_model->data_to_array( $params['account_data'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid account for message sender.' ) );
            return false;
        }

        if( !empty( $reply_message )
         && ((!empty( $account_arr ) && !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_REPLY_MESSAGE ))
                || !$this->can_reply( $reply_message, array( 'account_data' => $account_arr ) )
            ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Unknown message or you don\'t have rights to reply to this messages.' ) );
            return false;
        }

        if( !empty( $followup_message )
         && ((!empty( $account_arr ) && !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_FOLLOWUP_MESSAGE ))
                || !$this->can_followup( $followup_message, array( 'account_data' => $account_arr ) )
            ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Unknown message or you don\'t have rights to follow up this messages.' ) );
            return false;
        }

        if( empty( $reply_message )
         && empty( $followup_message )
         && !empty( $account_arr )
         && !PHS_Roles::user_has_role_units( $account_arr, $messages_plugin::ROLEU_WRITE_MESSAGE ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'You don\'t have rights to compose messages.' ) );
            return false;
        }

        if( empty( $params['dest_type'] )
         || !($dest_type = $this->valid_dest_type( $params['dest_type'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid destination type.' ) );
            return false;
        }

        if( !($message_type = $this->valid_type( $params['type'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid message type.' ) );
            return false;
        }

        if( !($author_handle = $this->get_relative_account_message_handler( $account_arr )) )
            $author_handle = '['.$this->_pt( 'Unknown author' ).']';

        $message_fields = array();

        $message_fields['subject'] = trim( $params['subject'] );

        if( !empty( $reply_message ) )
            $message_fields['reply_id'] = $reply_message['message']['id'];
        else
            $message_fields['reply_id'] = 0;

        if( !empty( $followup_message ) )
            $message_fields['followup_id'] = $followup_message['message']['id'];
        else
            $message_fields['followup_id'] = 0;

        $message_fields['thread_id'] = 0;  // will be updated with own id or reply thread id...

        $message_fields['dest_type'] = $params['dest_type'];
        $message_fields['from_uid'] = (!empty( $account_arr )?$account_arr['id']:0);
        $message_fields['from_handle'] = $author_handle;
        $message_fields['type'] = $params['type'];
        $message_fields['type_id'] = $params['type_id'];

        if( empty( $params['sticky'] ) )
            $message_fields['sticky'] = 0;
        else
            $message_fields['sticky'] = (!empty( $params['sticky'] )?1:0);

        if( empty( $params['can_reply'] ) )
            $message_fields['can_reply'] = 0;
        else
            $message_fields['can_reply'] = (!empty( $params['can_reply'] )?1:0);

        $accounts_count = 0;
        switch( $params['dest_type'] )
        {
            case self::DEST_TYPE_USERS_IDS:
                if( empty( $params['dest_type_users_ids'] )
                 || !($users_parts = self::extract_integers_from_comma_separated( $params['dest_type_users_ids'] )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Users ids list not provided for message destination.' ) );
                    return false;
                }

                $list_arr = $users_flow_params;
                $list_arr['fields']['id'] = array( 'check' => 'IN', 'value' => '('.implode( ',', $users_parts ).')' );
                $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $account_arr )
                 && !empty( $params['exclude_author'] ) )
                    $list_arr['fields']['id'] = array( 'check' => '!=', 'value' => $account_arr['id'] );

                if( !($accounts_count = $accounts_model->get_count( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }

                $message_fields['dest_str'] = implode( ', ', $users_parts );
            break;

            case self::DEST_TYPE_USERS:
                if( empty( $params['dest_type_users'] )
                 || !($users_parts = self::extract_strings_from_comma_separated( $params['dest_type_users'] )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'User list not provided for message destination.' ) );
                    return false;
                }

                $safe_strings_arr = array();
                foreach( $users_parts as $user_name )
                    $safe_strings_arr[] = prepare_data( $user_name );

                $list_arr = $users_flow_params;
                $list_arr['fields']['nick'] = array( 'check' => 'IN', 'value' => '(\''.implode( '\',\'', $safe_strings_arr ).'\')' );
                $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $account_arr )
                 && !empty( $params['exclude_author'] ) )
                    $list_arr['fields']['id'] = array( 'check' => '!=', 'value' => $account_arr['id'] );

                if( !($accounts_count = $accounts_model->get_count( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }

                $message_fields['dest_str'] = implode( ', ', $users_parts );
            break;

            case self::DEST_TYPE_HANDLERS:
                if( empty( $params['dest_type_handlers'] )
                 || !($handlers_parts = self::extract_strings_from_comma_separated( $params['dest_type_handlers'] )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'User list not provided for message destination.' ) );
                    return false;
                }

                $safe_strings_arr = array();
                foreach( $handlers_parts as $user_handle )
                    $safe_strings_arr[] = prepare_data( $user_handle );

                $users_table = $accounts_model->get_flow_table_name( $users_flow_params );
                $users_details_table = $user_details_model->get_flow_table_name( $users_details_flow_params );

                $list_arr = $users_flow_params;
                $list_arr['join_sql'] = ' LEFT JOIN `'.$users_details_table.'` ON `'.$users_table.'`.details_id = `'.$users_details_table.'`.id ';
                $list_arr['fields']['`'.$users_table.'`.status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                $list_arr['fields']['`'.$users_details_table.'`.'.$messages_plugin::UD_COLUMN_MSG_HANDLER] = array( 'check' => 'IN', 'value' => '(\''.implode( '\',\'', $safe_strings_arr ).'\')' );
                if( !empty( $account_arr )
                 && !empty( $params['exclude_author'] ) )
                    $list_arr['fields']['`'.$users_table.'`.id'] = array( 'check' => '!=', 'value' => $account_arr['id'] );

                if( !($accounts_count = $accounts_model->get_count( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No message handlers match destination you provided.' ) );
                    return false;
                }

                $message_fields['dest_str'] = implode( ', ', $handlers_parts );
            break;

            case self::DEST_TYPE_LEVEL:
                if( empty( $params['dest_type_level'] )
                 || !$accounts_model->valid_level( $params['dest_type_level'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'User level not provided for message destination.' ) );
                    return false;
                }

                $list_arr = $users_flow_params;
                $list_arr['fields']['level'] = $params['dest_type_level'];
                $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $account_arr )
                 && !empty( $params['exclude_author'] ) )
                    $list_arr['fields']['id'] = array( 'check' => '!=', 'value' => $account_arr['id'] );

                if( !($accounts_count = $accounts_model->get_count( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }

                $message_fields['dest_id'] = $params['dest_type_level'];
            break;

            case self::DEST_TYPE_ROLE:
                if( empty( $params['dest_type_role'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Role not provided for message destination.' ) );
                    return false;
                }

                $roles_users_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) );
                $roles_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles' ) );
                $roles_users_table = $roles_model->get_flow_table_name( $roles_users_flow_params );
                $users_table = $accounts_model->get_flow_table_name( $users_flow_params );

                $role_arr = false;
                if( !is_numeric( $params['dest_type_role'] )
                 || !($role_id = (int)$params['dest_type_role'])
                 || !($role_arr = $roles_model->get_details( $role_id, $roles_flow_params )) )
                {
                    $roles_fields_arr = array();
                    $roles_fields_arr['slug'] = $params['dest_type_role'];
                    $roles_fields_arr['status'] = $roles_model::STATUS_ACTIVE;

                    if( !($role_arr = $roles_model->get_details_fields( $roles_fields_arr, $roles_flow_params )) )
                    {
                        $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Role not found in database for message destination.' ) );
                        return false;
                    }
                }

                $list_arr = $users_flow_params;
                $list_arr['join_sql'] = ' INNER JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$users_table.'`.id ';
                $list_arr['fields']['`'.$roles_users_table.'`.role_id'] = $role_arr['id'];
                $list_arr['fields']['`'.$users_table.'`.status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $account_arr )
                 && !empty( $params['exclude_author'] ) )
                    $list_arr['fields']['`'.$users_table.'`.id'] = array( 'check' => '!=', 'value' => $account_arr['id'] );

                if( !($accounts_count = $accounts_model->get_count( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }

                $message_fields['dest_id'] = $role_arr['id'];
            break;

            case self::DEST_TYPE_ROLE_UNIT:
                if( empty( $params['dest_type_role_unit'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Role unit not provided for message destination.' ) );
                    return false;
                }

                $roles_users_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) );
                $roles_units_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_units' ) );
                $roles_users_table = $roles_model->get_flow_table_name( $roles_users_flow_params );
                $roles_units_links_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) );
                $roles_units_links_table = $roles_model->get_flow_table_name( $roles_units_links_flow_params );
                $users_table = $accounts_model->get_flow_table_name( $users_flow_params );

                $role_unit_arr = false;
                if( !is_numeric( $params['dest_type_role_unit'] )
                 || !($role_unit_id = (int)$params['dest_type_role_unit'])
                 || !($role_unit_arr = $roles_model->get_details( $role_unit_id, $roles_units_flow_params )) )
                {
                    $roles_units_fields_arr = array();
                    $roles_units_fields_arr['slug'] = $params['dest_type_role_unit'];
                    $roles_units_fields_arr['status'] = $roles_model::STATUS_ACTIVE;

                    if( !($role_unit_arr = $roles_model->get_details_fields( $roles_units_fields_arr, $roles_units_flow_params )) )
                    {
                        $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Role unit not found in database for message destination.' ) );
                        return false;
                    }
                }

                $list_arr = $users_flow_params;
                $list_arr['join_sql'] = ' INNER JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$users_table.'`.id ';
                $list_arr['fields']['`'.$roles_users_table.'`.role_id'] = array( 'check' => 'IN', 'value' =>
                    '(SELECT role_id FROM `'.$roles_units_links_table.'` WHERE `'.$roles_units_links_table.'`.role_unit_id = \''.$role_unit_arr['id'].'\')' );
                $list_arr['fields']['`'.$users_table.'`.status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $account_arr )
                 && !empty( $params['exclude_author'] ) )
                    $list_arr['fields']['`'.$users_table.'`.id'] = array( 'check' => '!=', 'value' => $account_arr['id'] );

                if( !($accounts_count = $accounts_model->get_count( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }

                $message_fields['dest_id'] = $role_unit_arr['id'];
            break;
        }

        $hook_args = PHS_Hooks::default_message_hook_args();
        $hook_args['message_data'] = $message_fields;
        $hook_args['reply_message_data'] = $reply_message;
        $hook_args['followup_message_data'] = $followup_message;
        $hook_args['author_data'] = $account_arr;
        $hook_args['write_params'] = $params;
        $hook_args['custom_settings'] = $params['custom_settings'];

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MSG_MESSAGES_CUSTOM_SETTINGS, $hook_args ))
         && ($hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_message_hook_args() ))
         && !empty( $hook_args['custom_settings'] ) && is_array( $hook_args['custom_settings'] ) )
            $message_fields['custom_settings'] = $hook_args['custom_settings'];
        else
            $message_fields['custom_settings'] = $params['custom_settings'];

        $message_fields['custom_settings'] = $this->prepare_custom_settings_arr( $message_fields['custom_settings'] );

        $m_insert = $m_flow_params;
        $m_insert['fields'] = $message_fields;

        if( !($message_arr = $this->insert( $m_insert )) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Error saving message details to database.' ) );
            return false;
        }

        $message_body_fields = array();
        $message_body_fields['body'] = $params['body'];
        $message_body_fields['message_id'] = $message_arr['id'];

        $mb_insert = $mb_flow_params;
        $mb_insert['fields'] = $message_body_fields;

        if( !($message_body_arr = $this->insert( $mb_insert )) )
        {
            $this->hard_delete( $message_arr, $m_flow_params );

            $this->set_error( self::ERR_INSERT, $this->_pt( 'Error saving message body details to database.' ) );
            return false;
        }

        if( !db_query( 'UPDATE `' . $this->get_flow_table_name( $m_flow_params ) . '` '.
                       ' SET body_id = \'' . $message_body_arr['id'] . '\' '.
                       ' WHERE id = \'' . $message_arr['id'] . '\'', $m_flow_params['db_connection'] ) )
        {
            $this->hard_delete( $message_arr, $m_flow_params );
            $this->hard_delete( $message_body_arr, $mb_flow_params );

            $this->set_error( self::ERR_INSERT, $this->_pt( 'Error updating message details to database.' ) );
            return false;
        }

        $message_arr['body_id'] = $message_body_arr['id'];

        $bg_job_params = $params['bg_job_params'];
        $bg_job_params['mid'] = $message_arr['id'];

        if( !PHS_Bg_jobs::run( array( 'plugin' => 'messages', 'controller' => 'index_bg', 'action' => 'write_message_bg' ),
                               $bg_job_params,
                               array( 'same_thread_if_bg' => true ) ) )
        {
            $this->hard_delete( $message_arr, $m_flow_params );
            $this->hard_delete( $message_body_arr, $mb_flow_params );

            if( self::st_has_error() )
                $error_msg = self::st_get_error_message();
            else
                $error_msg = $this->_pt( 'Error completing message flow. Please try again.' );

            $this->set_error( self::ERR_FUNCTIONALITY, $error_msg );
            return false;
        }

        return array(
            'message' => $message_arr,
            'message_body' => $message_body_arr,
        );
    }

    /**
     * Do the actual database inserts in background process
     * @param int|array $message_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function write_message_finish_bg( $message_data, $params = false )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( empty( $params ) || !is_array( $params ) )
            $params = array();

        if( !isset( $params['email_author'] ) )
            $params['email_author'] = true;
        else
            $params['email_author'] = (!empty( $params['email_author'] ));

        if( !isset( $params['email_destination'] ) )
            $params['email_destination'] = true;
        else
            $params['email_destination'] = (!empty( $params['email_destination'] ));

        $accounts_model = self::$_accounts_model;
        $messages_plugin = self::$_messages_plugin;
        $roles_model = self::$_roles_model;


        if( !($settings_arr = $this->get_plugin_settings()) )
            $settings_arr = array();

        if( !isset( $settings_arr['send_emails'] ) )
            $settings_arr['send_emails'] = true;
        if( empty( $settings_arr['include_body'] ) )
            $settings_arr['include_body'] = false;

        if( !($m_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         || !($mu_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) ))
         || !($mb_flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'messages_body' ) ))
         || !($user_details_model = PHS::load_model( 'accounts_details', 'accounts' ))
         || !($users_details_flow_params = $user_details_model->fetch_default_flow_params( array( 'table_name' => 'users_details' ) ))
         || !($users_flow_params = $accounts_model->fetch_default_flow_params( array( 'table_name' => 'users' ) )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error initiating parameters for message.' ) );
            return false;
        }

        if( empty( $message_data )
         || !($message_arr = $this->data_to_array( $message_data, $m_flow_params ))
         || !$this->need_write_finish( $message_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error obtaining message details from database.' ) );
            return false;
        }

        $author_arr = false;
        if( !empty( $message_arr['from_uid'] )
         && !($author_arr = $accounts_model->get_details( $message_arr['from_uid'] )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Sender account details not found.' ) );
            return false;
        }

        if( !($author_handle = $this->get_relative_account_message_handler( $author_arr )) )
            $author_handle = '['.$this->_pt( 'Unknown author' ).']';

        $thread_id = $message_arr['id'];

        $message_body = false;
        if( !empty( $message_arr['body_id'] ) )
        {
            if( !($message_body = $this->get_details( $message_arr['body_id'], $mb_flow_params )) )
            {
                $this->hard_delete( $message_arr, $m_flow_params );

                $this->set_error( self::ERR_INSERT, $this->_pt( 'Couldn\'t find message body in database.' ) );
                return false;
            }
        }

        $reply_message = false;
        if( !empty( $message_arr['reply_id'] ) )
        {
            if( !($reply_message = $this->get_details( $message_arr['reply_id'], $m_flow_params )) )
            {
                $this->hard_delete( $message_arr, $m_flow_params );
                if( !empty( $message_arr['body_id'] ) )
                    $this->hard_delete( $message_arr['body_id'], $mb_flow_params );

                $this->set_error( self::ERR_INSERT, $this->_pt( 'Couldn\'t find reply message in database.' ) );
                return false;
            }

            $thread_id = $reply_message['thread_id'];
        }

        $followup_message = false;
        if( !empty( $message_arr['followup_id'] ) )
        {
            if( !($followup_message = $this->get_details( $message_arr['followup_id'], $m_flow_params )) )
            {
                $this->hard_delete( $message_arr, $m_flow_params );
                if( !empty( $message_arr['body_id'] ) )
                    $this->hard_delete( $message_arr['body_id'], $mb_flow_params );

                $this->set_error( self::ERR_INSERT, $this->_pt( 'Couldn\'t find follow up message in database.' ) );
                return false;
            }

            $thread_id = $followup_message['thread_id'];
        }
        $thread_id = (int)$thread_id;

        $thread_message = false;
        if( !empty( $thread_id ) )
        {
            if( $thread_id === (int)$message_arr['id'] )
                $thread_message = $message_arr;

            elseif( !($thread_message = $this->get_details( $thread_id, $m_flow_params )) )
                $thread_message = false;
        }

        if( !db_query( 'UPDATE `' . $this->get_flow_table_name( $m_flow_params ) . '` '.
                       ' SET thread_id = \'' . $thread_id . '\' '.
                       ' WHERE id = \'' . $message_arr['id'] . '\'', $m_flow_params['db_connection'] ) )
        {
            $this->hard_delete( $message_arr, $m_flow_params );
            if( !empty( $message_arr['body_id'] ) )
                $this->hard_delete( $message_arr['body_id'], $mb_flow_params );

            $this->set_error( self::ERR_INSERT, $this->_pt( 'Error updating message details to database.' ) );
            return false;
        }

        $message_arr['thread_id'] = $thread_id;

        $accounts_list = array();
        switch( $message_arr['dest_type'] )
        {
            case self::DEST_TYPE_USERS_IDS:
                if( empty( $message_arr['dest_str'] )
                 || !($users_parts = self::extract_integers_from_comma_separated( $message_arr['dest_str'] )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Users ids list not provided for message destination.' ) );
                    return false;
                }

                $list_arr = $users_flow_params;
                $list_arr['fields']['id'] = array( 'check' => 'IN', 'value' => '('.implode( ',', $users_parts ).')' );
                $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $author_arr ) )
                    $list_arr['fields']['id'] = array( 'check' => '!=', 'value' => $author_arr['id'] );

                if( !($accounts_list = $accounts_model->get_list( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }
            break;

            case self::DEST_TYPE_USERS:
                if( empty( $message_arr['dest_str'] )
                 || !($users_parts = self::extract_strings_from_comma_separated( $message_arr['dest_str'] )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'User list not provided for message destination.' ) );
                    return false;
                }

                $safe_strings_arr = array();
                foreach( $users_parts as $user_name )
                    $safe_strings_arr[] = prepare_data( $user_name );

                $list_arr = $users_flow_params;
                $list_arr['fields']['nick'] = array( 'check' => 'IN', 'value' => '(\''.implode( '\',\'', $safe_strings_arr ).'\')' );
                $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $author_arr ) )
                    $list_arr['fields']['id'] = array( 'check' => '!=', 'value' => $author_arr['id'] );

                if( !($accounts_list = $accounts_model->get_list( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }
            break;

            case self::DEST_TYPE_HANDLERS:
                if( empty( $message_arr['dest_str'] )
                 || !($handlers_parts = self::extract_strings_from_comma_separated( $message_arr['dest_str'] )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'User list not provided for message destination.' ) );
                    return false;
                }

                $safe_strings_arr = array();
                foreach( $handlers_parts as $user_handle )
                    $safe_strings_arr[] = prepare_data( $user_handle );

                $users_table = $accounts_model->get_flow_table_name( $users_flow_params );
                $users_details_table = $user_details_model->get_flow_table_name( $users_details_flow_params );

                $list_arr = $users_flow_params;
                $list_arr['join_sql'] = ' LEFT JOIN `'.$users_details_table.'` ON `'.$users_table.'`.details_id = `'.$users_details_table.'`.id ';
                $list_arr['fields']['`'.$users_table.'`.status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                $list_arr['fields']['`'.$users_details_table.'`.'.$messages_plugin::UD_COLUMN_MSG_HANDLER] = array( 'check' => 'IN', 'value' => '(\''.implode( '\',\'', $safe_strings_arr ).'\')' );
                if( !empty( $author_arr ) )
                    $list_arr['fields']['`'.$users_table.'`.id'] = array( 'check' => '!=', 'value' => $author_arr['id'] );

                if( !($accounts_list = $accounts_model->get_list( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No message handlers match destination you provided.' ) );
                    return false;
                }
            break;

            case self::DEST_TYPE_LEVEL:
                if( empty( $message_arr['dest_id'] )
                 || !$accounts_model->valid_level( $message_arr['dest_id'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'User level not provided for message destination.' ) );
                    return false;
                }

                $list_arr = $users_flow_params;
                $list_arr['fields']['level'] = $message_arr['dest_id'];
                $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $author_arr ) )
                    $list_arr['fields']['id'] = array( 'check' => '!=', 'value' => $author_arr['id'] );

                if( !($accounts_list = $accounts_model->get_list( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }
            break;

            case self::DEST_TYPE_ROLE:
                if( empty( $message_arr['dest_id'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Role not provided for message destination.' ) );
                    return false;
                }

                $roles_users_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) );
                $roles_users_table = $roles_model->get_flow_table_name( $roles_users_flow_params );
                $users_table = $accounts_model->get_flow_table_name( $users_flow_params );

                $list_arr = $users_flow_params;
                $list_arr['join_sql'] = ' INNER JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$users_table.'`.id ';
                $list_arr['fields']['`'.$roles_users_table.'`.role_id'] = $message_arr['dest_id'];
                $list_arr['fields']['`'.$users_table.'`.status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $author_arr ) )
                    $list_arr['fields']['`'.$users_table.'`.id'] = array( 'check' => '!=', 'value' => $author_arr['id'] );

                if( !($accounts_list = $accounts_model->get_list( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }
            break;

            case self::DEST_TYPE_ROLE_UNIT:
                if( empty( $message_arr['dest_id'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Role unit not provided for message destination.' ) );
                    return false;
                }

                $roles_users_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) );
                $roles_users_table = $roles_model->get_flow_table_name( $roles_users_flow_params );
                $roles_units_links_flow_params = $roles_model->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) );
                $roles_units_links_table = $roles_model->get_flow_table_name( $roles_units_links_flow_params );
                $users_table = $accounts_model->get_flow_table_name( $users_flow_params );

                $list_arr = $users_flow_params;
                $list_arr['join_sql'] = ' INNER JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$users_table.'`.id ';
                $list_arr['fields']['`'.$roles_users_table.'`.role_id'] = array( 'check' => 'IN', 'value' =>
                    '(SELECT role_id FROM `'.$roles_units_links_table.'` WHERE `'.$roles_units_links_table.'`.role_unit_id = \''.$message_arr['dest_id'].'\')' );
                $list_arr['fields']['`'.$users_table.'`.status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );
                if( !empty( $author_arr ) )
                    $list_arr['fields']['`'.$users_table.'`.id'] = array( 'check' => '!=', 'value' => $author_arr['id'] );

                if( !($accounts_list = $accounts_model->get_list( $list_arr )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'No users match destination you provided.' ) );
                    return false;
                }
            break;
        }

        $return_arr = array();
        $return_arr['message'] = $message_arr;
        $return_arr['email_sent_to_author'] = false;
        $return_arr['email_sent_to_destination'] = false;
        $return_arr['users_messaged'] = array();
        $return_arr['users_not_messaged'] = array();

        $message_date = date( 'Y-m-d H:i', parse_db_date( $message_arr['cdate'] ) );

        $email_sent_to_author = false;
        $email_sent_to_destination = false;
        if( !empty( $author_arr ) )
        {
            $message_users_fields = array();
            $message_users_fields['user_id'] = $author_arr['id'];
            $message_users_fields['message_id'] = $message_arr['id'];
            $message_users_fields['thread_id'] = $message_arr['thread_id'];
            $message_users_fields['is_new'] = 1;
            $message_users_fields['email_sent'] = 0;
            $message_users_fields['is_author'] = 1;
            $message_users_fields['cdate'] = $message_arr['cdate'];

            $messages_users_arr = $mu_flow_params;
            $messages_users_arr['fields'] = $message_users_fields;

            if( !($mu_details_arr = $this->insert( $messages_users_arr )) )
            {
                PHS_Logger::logf( 'Error inserting sent message #'.$message_arr['id'].' for user '.$author_arr['nick'].' (#'.$author_arr['id'].').' );
            } else
            {
                if( !empty( $params['email_author'] )
                 && !empty( $settings_arr['send_emails'] ) )
                {
                    // send confirmation email...
                    $hook_args = array();
                    $hook_args['template'] = $messages_plugin->email_template_resource_from_file( 'message_author' );
                    $hook_args['to'] = $author_arr['email'];
                    $hook_args['to_name'] = $author_arr['nick'];
                    $hook_args['subject'] = $this->_pt( 'Internal message sent' ).': '.$message_arr['subject'];
                    $hook_args['email_vars'] = array(
                        'author_nick' => $author_arr['nick'],
                        'message_date' => $message_date,
                        'message_subject' => $message_arr['subject'],
                        'message_body' => ((!empty( $settings_arr['include_body'] ) && !empty( $message_body ))?strip_tags( $message_body['body'] ):false),
                        'message_link' => PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ), array( 'muid' => $mu_details_arr['id'] ) ),
                        'contact_us_link' => PHS::url( array( 'a' => 'contact_us' ) ),
                    );

                    if( ($hook_results = PHS_Hooks::trigger_email( $hook_args )) === null
                     || (is_array( $hook_results ) && !empty( $hook_results['send_result'] )) )
                    {
                        $email_sent_to_author = true;
                    }
                }

                // If author should not receive email, just tell system we sent the email so it will not try resending
                if( empty( $params['email_author'] ) )
                    $email_sent_to_author = true;

                if( $email_sent_to_author )
                {
                    $message_users_fields = array();
                    $message_users_fields['email_sent'] = 1;

                    $messages_users_arr = $mu_flow_params;
                    $messages_users_arr['fields'] = $message_users_fields;

                    if( $this->edit( $mu_details_arr, $messages_users_arr ) )
                    {
                        $mu_details_arr['email_sent'] = 1;
                    }
                }
            }
        }

        $return_arr['email_sent_to_author'] = $email_sent_to_author;

        foreach( $accounts_list as $account_id => $account_arr )
        {
            $message_users_fields = array();
            $message_users_fields['user_id'] = $account_id;
            $message_users_fields['message_id'] = $message_arr['id'];
            $message_users_fields['thread_id'] = $message_arr['thread_id'];
            $message_users_fields['is_new'] = 1;
            $message_users_fields['email_sent'] = 0;
            $message_users_fields['is_author'] = 0;
            $message_users_fields['cdate'] = $message_arr['cdate'];

            $messages_users_arr = $mu_flow_params;
            $messages_users_arr['fields'] = $message_users_fields;

            if( !($mu_details_arr = $this->insert( $messages_users_arr )) )
            {
                PHS_Logger::logf( 'Error sending message #'.$message_arr['id'].' to user '.$account_arr['nick'].' ('.$account_arr['id'].').' );

                $return_arr['users_not_messaged'][$account_id] = $account_arr;
            } else
            {
                $return_arr['users_messaged'][$account_id] = $account_arr;

                if( !empty( $params['email_destination'] )
                 && !empty( $settings_arr['send_emails'] ) )
                {
                    // send confirmation email...
                    $hook_args = array();
                    $hook_args['template'] = $messages_plugin->email_template_resource_from_file( 'message_destination' );
                    $hook_args['to'] = $account_arr['email'];
                    $hook_args['to_name'] = $account_arr['nick'];
                    $hook_args['subject'] = $this->_pt( 'New internal message' ).': '.$message_arr['subject'];
                    $hook_args['email_vars'] = array(
                        'destination_nick' => $account_arr['nick'],
                        'author_handle' => $author_handle,
                        'message_date' => $message_date,
                        'message_subject' => $message_arr['subject'],
                        'message_body' => ((!empty( $settings_arr['include_body'] ) && !empty( $message_body ))?strip_tags( $message_body['body'] ):false),
                        'message_link' => PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ), array( 'muid' => $mu_details_arr['id'] ) ),
                        'contact_us_link' => PHS::url( array( 'a' => 'contact_us' ) ),
                    );

                    if( ($hook_results = PHS_Hooks::trigger_email( $hook_args )) === null
                     || (is_array( $hook_results ) && !empty( $hook_results['send_result'] )) )
                    {
                        $email_sent_to_destination = true;
                    }
                }

                // If author should not receive email, just tell system we sent the email so it will not try resending
                if( empty( $params['email_destination'] ) )
                    $email_sent_to_destination = true;

                if( $email_sent_to_destination )
                {
                    $message_users_fields = array();
                    $message_users_fields['email_sent'] = 1;

                    $messages_users_arr = $mu_flow_params;
                    $messages_users_arr['fields'] = $message_users_fields;

                    if( $this->edit( $mu_details_arr, $messages_users_arr ) )
                    {
                        $mu_details_arr['email_sent'] = 1;
                    }
                }
            }
        }

        $return_arr['email_sent_to_destination'] = $email_sent_to_destination;

        $hook_args = PHS_Hooks::default_message_hook_args();
        $hook_args['message_data'] = $message_arr;
        $hook_args['reply_message_data'] = $reply_message;
        $hook_args['followup_message_data'] = $followup_message;
        $hook_args['thread_message_data'] = $thread_message;
        $hook_args['body_data'] = ((!empty( $settings_arr['include_body'] ) && !empty( $message_body ))?$message_body:false);
        $hook_args['author_data'] = $author_arr;
        $hook_args['write_params'] = $params;
        $hook_args['message_results'] = $return_arr;

        PHS::trigger_hooks( PHS_Hooks::H_MSG_MESSAGES_SENT, $hook_args );

        return $return_arr;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
     */
    protected function get_insert_prepare_params_messages( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( empty( $params['fields']['type'] ) )
            $params['fields']['type'] = self::TYPE_NORMAL;
        if( empty( $params['fields']['type_id'] ) )
            $params['fields']['type_id'] = 0;
        if( empty( $params['fields']['type_str'] ) )
            $params['fields']['type_str'] = '';
        if( empty( $params['fields']['dest_id'] ) )
            $params['fields']['dest_id'] = 0;
        if( empty( $params['fields']['dest_str'] ) )
            $params['fields']['dest_str'] = '';
        if( empty( $params['fields']['custom_settings'] ) )
            $params['fields']['custom_settings'] = '';

        if( empty( $params['fields']['importance'] )
         || !$this->valid_importance( $params['fields']['importance'] ) )
            $params['fields']['importance'] = self::IMPORTANCE_NORMAL;

        if( !$this->valid_type( $params['fields']['type'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Invalid message type.' ) );
            return false;
        }

        if( !$this->valid_dest_type( $params['fields']['dest_type'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Invalid destination type.' ) );
            return false;
        }

        if( empty( $params['fields']['cdate'] ) || empty_db_date( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB );
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
     */
    protected function get_insert_prepare_params_messages_users( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( empty( $params['fields']['user_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Unknown user for this user message.' ) );
            return false;
        }

        if( empty( $params['fields']['message_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Unknown message details for this user message.' ) );
            return false;
        }

        if( empty( $params['fields']['thread_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Unknown thread id for this user message.' ) );
            return false;
        }

        if( !isset( $params['fields']['is_new'] ) )
            $params['fields']['is_new'] = 1;
        else
            $params['fields']['is_new'] = (!empty( $params['fields']['is_new'] )?1:0);

        if( empty( $params['fields']['email_sent'] ) )
            $params['fields']['email_sent'] = 0;
        else
            $params['fields']['email_sent'] = 1;

        if( empty( $params['fields']['is_author'] ) )
            $params['fields']['is_author'] = 0;
        else
            $params['fields']['is_author'] = 1;

        if( empty( $params['fields']['cdate'] ) || empty_db_date( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB );
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

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
                    'reply_id' => array(
                        'type' => self::FTYPE_INT,
                        'default' => 0,
                        'index' => true,
                        'comment' => 'Id of message replying to',
                    ),
                    'followup_id' => array(
                        'type' => self::FTYPE_INT,
                        'default' => 0,
                        'index' => true,
                        'comment' => 'Id of follow up message',
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
                    'dest_str' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Comma separated values'
                    ),
                    'from_uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => '0 - system, X - User ID',
                    ),
                    'from_handle' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Handle of author',
                    ),
                    'subject' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'type' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                        'comment' => 'Normal (can be extended) Link to other data',
                    ),
                    'type_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'type_str' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Comma separated values'
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
                    'custom_settings' => array(
                        'type' => self::FTYPE_TEXT,
                        'nullable' => true,
                        'default' => null,
                        'comment' => 'Other plugins settings'
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
