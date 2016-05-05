<?php

namespace phs\plugins\accounts\models;

use \phs\PHS;
use \phs\PHS_crypt;
use \phs\PHS_bg_jobs;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_params;

class PHS_Model_Accounts extends PHS_Model
{
    const ERR_LOGIN = 10001, ERR_EMAIL = 10002;

    const HOOK_LEVELS = 'phs_accounts_levels', HOOK_STATUSES = 'phs_accounts_statuses';

    // "Hardcoded" minimum password length (if 'min_password_length' is not found in settings)
    const DEFAULT_MIN_PASSWORD_LENGTH = 8;

    const STATUS_INACTIVE = 1, STATUS_ACTIVE = 2, STATUS_SUSPENDED = 3, STATUS_DELETED = 4;
    protected static $STATUSES_ARR = array(
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_SUSPENDED => array( 'title' => 'Suspended' ),
        self::STATUS_DELETED => array( 'title' => 'Deleted' ),
    );

    const LVL_GUEST = 0, LVL_MEMBER = 1,
          LVL_OPERATOR = 10, LVL_ADMIN = 11, LVL_SUPERADMIN = 12, LVL_DEVELOPER = 13;
    protected static $LEVELS_ARR = array(
        self::LVL_MEMBER => array( 'title' => 'Member' ),
        self::LVL_OPERATOR => array( 'title' => 'Operator' ),
        self::LVL_ADMIN => array( 'title' => 'Admin' ),
        self::LVL_SUPERADMIN => array( 'title' => 'Super admin' ),
        self::LVL_DEVELOPER => array( 'title' => 'Developer' ),
    );

    function __construct( $instance_details = false )
    {
        //$this->add_connection( 'PHS_Model_Accounts_details', 'accounts', self::INSTANCE_TYPE_MODEL );

        parent::__construct( $instance_details );
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
        return array( 'users', 'online' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'users';
    }

    //
    //  Level checks
    //
    public static function is_developer( $lvl )
    {
        return ($lvl == self::LVL_DEVELOPER);
    }

    public static function is_sadmin( $lvl )
    {
        return ($lvl == self::LVL_SUPERADMIN or $lvl == self::LVL_DEVELOPER);
    }

    public static function is_admin( $lvl, $strict = false )
    {
        return ($lvl == self::LVL_ADMIN or (!$strict and ($lvl == self::LVL_SUPERADMIN or $lvl == self::LVL_DEVELOPER)));
    }

    public static function is_operator( $lvl, $strict = false )
    {
        return ($lvl == self::LVL_OPERATOR or (!$strict and self::is_admin( $lvl )));
    }

    public static function is_member( $lvl, $strict = false )
    {
        return ($lvl == self::LVL_MEMBER or (!$strict and self::is_admin( $lvl )));
    }
    //
    //  END Level checks
    //

    //
    //  Account level checks
    //
    public function acc_is_developer( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !self::is_developer( $user_arr['level'] ) )
            return false;

        return $user_arr;
    }

    public function acc_is_sadmin( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !self::is_sadmin( $user_arr['level'] ) )
            return false;

        return $user_arr;
    }

    public function acc_is_admin( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !self::is_admin( $user_arr['level'] ) )
            return false;

        return $user_arr;
    }

    public function acc_is_operator( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !self::is_operator( $user_arr['level'] ) )
            return false;

        return $user_arr;
    }

    public function acc_is_member( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !self::is_member( $user_arr['level'] ) )
            return false;

        return $user_arr;
    }
    //
    //  END Account level checks
    //

    public function is_active( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or $user_arr['status'] != self::STATUS_ACTIVE )
            return false;

        return $user_arr;
    }

    public function is_inactive( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or $user_arr['status'] != self::STATUS_INACTIVE )
            return false;

        return $user_arr;
    }

    public function is_deleted( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or $user_arr['status'] != self::STATUS_DELETED )
            return false;

        return $user_arr;
    }

    public function is_just_registered( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or $user_arr['lastlog'] != self::DATETIME_EMPTY )
            return false;

        return $user_arr;
    }

    public function has_logged_in( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or $user_arr['lastlog'] == self::DATETIME_EMPTY )
            return false;

        return $user_arr;
    }

    public function needs_activation( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !$this->is_just_registered( $user_arr )
         or $this->is_active( $user_arr )
         or $this->is_deleted( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function needs_confirmation_email( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or $this->has_logged_in( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function needs_email_verification( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !empty( $user_arr['email_verified'] )
         or $this->is_deleted( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function can_list_plugins( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !$this->acc_is_sadmin( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function can_manage_plugins( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !$this->acc_is_sadmin( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function can_login_subaccount( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !$this->acc_is_sadmin( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function can_manage_accounts( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !$this->acc_is_sadmin( $user_arr ) )
            return false;

        return $user_arr;
    }

    public function can_list_accounts( $user_data )
    {
        if( !($user_arr = $this->data_to_array( $user_data ))
         or !$this->acc_is_sadmin( $user_arr ) )
            return false;

        return $user_arr;
    }

    final public function get_levels()
    {
        static $levels_arr = array();

        if( !empty( $levels_arr ) )
            return $levels_arr;

        $new_levels_arr = self::$LEVELS_ARR;
        if( ($extra_levels_arr = PHS::trigger_hooks( self::HOOK_LEVELS, array( 'levels_arr' => self::$LEVELS_ARR ) ))
        and is_array( $extra_levels_arr ) and !empty( $extra_levels_arr['levels_arr'] ) )
            $new_levels_arr = self::merge_array_assoc( $extra_levels_arr['levels_arr'], $new_levels_arr );

        $levels_arr = array();
        // Translate and validate levels...
        if( !empty( $new_levels_arr ) and is_array( $new_levels_arr ) )
        {
            foreach( $new_levels_arr as $level_id => $level_arr )
            {
                $level_id = intval( $level_id );
                if( empty( $level_id ) )
                    continue;

                if( empty( $level_arr['title'] ) )
                    $level_arr['title'] = self::_t( 'Level %s', $level_id );
                else
                    $level_arr['title'] = self::_t( $level_arr['title'] );

                $levels_arr[$level_id] = array(
                    'title' => $level_arr['title']
                );
            }
        }

        return $levels_arr;
    }

    final public function get_levels_as_key_val()
    {
        static $user_levels_key_val_arr = false;

        if( $user_levels_key_val_arr !== false )
            return $user_levels_key_val_arr;

        $user_levels_key_val_arr = array();
        if( ($user_levels = $this->get_levels()) )
        {
            foreach( $user_levels as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $user_levels_key_val_arr[$key] = $val['title'];
            }
        }

        return $user_levels_key_val_arr;
    }

    public function valid_level( $level )
    {
        $all_levels = $this->get_levels();
        if( empty( $level )
         or empty( $all_levels[$level] ) )
            return false;

        return $all_levels[$level];
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $new_statuses_arr = self::$STATUSES_ARR;
        if( ($extra_statuses_arr = PHS::trigger_hooks( self::HOOK_STATUSES, array( 'statuses_arr' => self::$STATUSES_ARR ) ))
        and is_array( $extra_statuses_arr ) and !empty( $extra_statuses_arr['statuses_arr'] ) )
            $new_statuses_arr = self::merge_array_assoc( $extra_statuses_arr['statuses_arr'], $new_statuses_arr );

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

    final public function get_statuses_as_key_val()
    {
        static $user_statuses_key_val_arr = false;

        if( $user_statuses_key_val_arr !== false )
            return $user_statuses_key_val_arr;

        $user_statuses_key_val_arr = array();
        if( ($user_statuses = $this->get_statuses()) )
        {
            foreach( $user_statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $user_statuses_key_val_arr[$key] = $val['title'];
            }
        }

        return $user_statuses_key_val_arr;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
         or empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    public static function generate_password( $len = 10 )
    {
        $dict = '!ac5d#befgh9ij1kl2mn*q3(pr)4s_t-6u=vw7xy,8z.'; // all lower letters
        $dict_len = strlen( $dict );

        $ret = '';
        for( $ret_len = 0; $ret_len < $len; $ret_len++ )
        {
            $ch = substr( $dict, mt_rand( 0, $dict_len - 1 ), 1 );
            if( mt_rand( 0, 100 ) > 50 )
                $ch = strtoupper( $ch );

            $ret .= $ch;
        }

        return $ret;
    }

    public static function encode_pass( $pass, $salt )
    {
        return md5( $salt.'_'.$pass );
    }

    public function check_pass( $account_data, $pass )
    {
        if( !($account_arr = $this->data_to_array( $account_data ))
         or !isset( $account_arr['pass_salt'] )
         or self::encode_pass( $pass, $account_arr['pass_salt'] ) != $account_arr['pass'] )
            return false;

        return $account_arr;
    }

    public function obfuscate_password( $account_data )
    {
        $this->reset_error();

        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data ))
         or empty( $account_arr['pass_clear'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unknown account.' ) );
            return false;
        }

        $clean_pass = PHS_crypt::quick_decode( $account_arr['pass_clear'] );

        $obfuscated_pass = substr( $clean_pass, 0, 1 ).str_repeat( '*', strlen( $clean_pass ) - 2 ).substr( $clean_pass, -1 );

        return $obfuscated_pass;
    }

    public function clean_password( $account_data )
    {
        $this->reset_error();

        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data ))
         or empty( $account_arr['pass_clear'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unknown account.' ) );
            return false;
        }

        if( !($clean_pass = PHS_crypt::quick_decode( $account_arr['pass_clear'] )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t obtain account password.' ) );
            return false;
        }

        return $clean_pass;
    }

    public function clear_idler_sessions()
    {
        return (db_query( 'DELETE FROM `online` WHERE expire_date < \''.date( self::DATETIME_DB ).'\'', self::get_db_connection() )?true:false);
    }

    public function update_current_session( $online_data, $params = false )
    {
        if( empty( $online_data )
         or !($online_arr = $this->data_to_array( $online_data, array( 'table_name' => 'online' ) )) )
            return false;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['location'] ) )
            $params['location'] = PHS::relative_url( PHS::current_url() );
        else
            $params['location'] = trim( $params['location'] );

        if( !($host = request_ip()) )
            $host = '127.0.0.1';

        $now_time = time();
        $cdate = date( self::DATETIME_DB, $now_time );

        $edit_arr = array();
        $edit_arr['host'] = $host;
        $edit_arr['idle'] = $cdate;
        $edit_arr['expire_date'] = date( self::DATETIME_DB, $now_time + $online_arr['expire_mins'] * 60 );
        $edit_arr['location'] = $params['location'];

        $edit_params = array();
        $edit_params['table_name'] = 'online';
        $edit_params['fields'] = $edit_arr;

        if( !($online_arr = $this->edit( $online_arr, $edit_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Error saving session details to database.' ) );

            return false;
        }

        return $online_arr;
    }

    public function session_logout_subaccount( $online_data )
    {
        if( empty( $online_data )
         or !($online_arr = $this->data_to_array( $online_data, array( 'table_name' => 'online' ) ))
         or empty( $online_arr['auid'] ) )
            return false;

        $edit_arr = array();
        $edit_arr['fields'] = array();
        $edit_arr['fields']['uid'] = $online_arr['auid'];
        $edit_arr['fields']['auid'] = 0;

        return $this->edit( $online_arr, $edit_arr );
    }

    public function session_logout( $online_data )
    {
        if( empty( $online_data )
         or !($online_arr = $this->data_to_array( $online_data, array( 'table_name' => 'online' ) ))
         or empty( $online_arr['id'] ) )
            return false;

        return $this->hard_delete( $online_arr, array( 'table_name' => 'online' ) );
    }

    public function create_session_id()
    {
        return md5( uniqid( rand(), true ) );
    }

    public function login( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data ))
         or empty( $account_arr['id'] ) )
        {
            $this->set_error( self::ERR_LOGIN, self::_t( 'Unknown account.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['expire_mins'] ) )
            $params['expire_mins'] = 0;
        else
            $params['expire_mins'] = intval( $params['expire_mins'] );

        if( empty( $params['location'] ) )
            $params['location'] = PHS::relative_url( PHS::current_url() );
        else
            $params['location'] = trim( $params['location'] );

        $auid = 0;
        if( ($current_user = PHS::current_user())
        and !empty( $current_user['id'] ) )
        {
            if( !$this->can_login_subaccount( $current_user ) )
            {
                $this->set_error( self::ERR_LOGIN, self::_t( 'Already logged in.' ) );
                return false;
            }

            $auid = $current_user['id'];
        }

        if( !($host = request_ip()) )
            $host = '127.0.0.1';
        
        $now_time = time();
        $cdate = date( self::DATETIME_DB, $now_time );

        $insert_arr = array();
        $insert_arr['wid'] = $this->create_session_id();
        $insert_arr['uid'] = $account_arr['id'];
        $insert_arr['auid'] = $auid;
        $insert_arr['host'] = $host;
        $insert_arr['idle'] = $cdate;
        $insert_arr['connected'] = $cdate;
        $insert_arr['expire_date'] = (empty( $params['expire_mins'] )?self::DATETIME_EMPTY:date( self::DATETIME_DB, $now_time + $params['expire_mins'] ));
        $insert_arr['expire_mins'] = $params['expire_mins'];
        $insert_arr['location'] = $params['location'];

        $insert_params = array();
        $insert_params['table_name'] = 'online';
        $insert_params['fields'] = $insert_arr;

        if( !($onuser_arr = $this->insert( $insert_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Error saving session details to database.' ) );

            return false;
        }

        $edit_arr = array();
        $edit_arr['lastlog'] = $cdate;
        $edit_arr['lastip'] = $host;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        if( ($new_account_arr = $this->edit( $account_arr, $edit_params )) )
            $account_arr = $new_account_arr;

        return $onuser_arr;
    }

    public function email_verified( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_LOGIN, self::_t( 'Unknown account.' ) );

            return false;
        }

        if( !empty( $account_arr['email_verified'] ) )
            return $account_arr;

        $edit_arr = array();
        $edit_arr['email_verified'] = 1;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $account_arr, $edit_params );
    }

    public function activate_account_after_registration( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data ))
         or !$this->needs_activation( $account_arr ) )
        {
            $this->set_error( self::ERR_LOGIN, self::_t( 'Unknown account.' ) );

            return false;
        }

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $account_arr, $edit_params );
    }

    public function activate_account( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_LOGIN, self::_t( 'Unknown account.' ) );
            return false;
        }

        if( $this->is_active( $account_arr ) )
            return $account_arr;

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $account_arr, $edit_params );
    }

    public function inactivate_account( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_LOGIN, self::_t( 'Unknown account.' ) );
            return false;
        }

        if( $this->is_inactive( $account_arr ) )
            return $account_arr;

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_INACTIVE;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $account_arr, $edit_params );
    }

    public function delete_account( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_LOGIN, self::_t( 'Unknown account.' ) );
            return false;
        }

        if( $this->is_inactive( $account_arr ) )
            return $account_arr;

        $edit_arr = array();
        $edit_arr['nick'] = $account_arr['nick'].'_DELETED';
        $edit_arr['email'] = $account_arr['email'].'_DELETED';
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $account_arr, $edit_params );
    }

    public function send_confirmation_email( $account_data, $params = false )
    {
        if( empty( $account_data )
         or !($account_arr = $this->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_EMAIL, self::_t( 'Unknown account.' ) );
            return false;
        }

        if( !$this->needs_confirmation_email( $account_arr ) )
        {
            $this->set_error( self::ERR_EMAIL, self::_t( 'This account doesn\'t need a confirmation email anymore. Logged in before.' ) );
            return false;
        }

        if( !PHS_bg_jobs::run( array( 'plugin' => 'accounts', 'action' => 'registration_confirmation_bg' ), array( 'uid' => $account_arr['id'] ) ) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_EMAIL );
            else
                $this->set_error( self::ERR_EMAIL, self::_t( 'Error sending confirmation email. Please try again.' ) );

            return false;
        }

        return $account_arr;
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
    protected function get_insert_prepare_params_users( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( !($accounts_settings = $this->get_plugin_settings())
         or !is_array( $accounts_settings ) )
            $accounts_settings = array();

        if( !empty( $accounts_settings['email_mandatory'] )
        and empty( $params['fields']['email'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide an email.' ) );
            return false;
        }

        if( !empty( $params['fields']['email'] )
        and !PHS_params::check_type( $params['fields']['email'], PHS_params::T_EMAIL ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid email.' ) );
            return false;
        }

        if( empty( $params['fields']['nick'] )
        and !empty( $params['fields']['email'] )
        and !empty( $accounts_settings['replace_nick_with_email'] ) )
            $params['fields']['nick'] = $params['fields']['email'];

        if( empty( $params['fields']['nick'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide an username.' ) );
            return false;
        }

        if( empty( $params['fields']['level'] ) )
            $params['fields']['level'] = self::LVL_MEMBER;
        if( empty( $params['fields']['status'] ) )
        {
            if( empty( $accounts_settings['account_requires_activation'] ) )
                $params['fields']['status'] = self::STATUS_ACTIVE;
            else
                $params['fields']['status'] = self::STATUS_INACTIVE;
        }

        if( !$this->valid_level( $params['fields']['level'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid account level.' ) );
            return false;
        }

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid status.' ) );
            return false;
        }

        if( empty( $params['fields']['pass'] ) and empty( $accounts_settings['generate_pass_if_not_present'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a password.' ) );
            return false;
        }

        if( !empty( $params['fields']['pass'] ) )
        {
            if( !empty( $accounts_settings['min_password_length'] )
            and strlen( $params['fields']['pass'] ) < $accounts_settings['min_password_length'] )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Password should be at least %s characters.',
                                                              $accounts_settings['min_password_length'] ) );

                return false;
            }

            if( !empty( $accounts_settings['password_regexp'] )
            and !@preg_match( $accounts_settings['password_regexp'], $params['fields']['pass'] ) )
            {
                if( ($regexp_parts = explode( '/', $accounts_settings['password_regexp'] ))
                and !empty( $regexp_parts[1] ) )
                {
                    if( empty( $regexp_parts[2] ) )
                        $regexp_parts[2] = '';

                    $this->set_error( self::ERR_INSERT,
                                      self::_t( 'Password doesn\'t match regular expression <a href="https://regex101.com/?regex=%s&options=%s" title="Click for details" target="_blank">%s</a>.',
                                                $regexp_parts[1], $regexp_parts[2], $accounts_settings['password_regexp'] ) );
                } else
                    $this->set_error( self::ERR_INSERT, self::_t( 'Password doesn\'t match regular expression %s.', $accounts_settings['password_regexp'] ) );

                return false;
            }
        }

        $check_arr = array();
        $check_arr['nick'] = $params['fields']['nick'];

        if( $this->get_details_fields( $check_arr ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Username already exists in database. Please pick another one.' ) );
            return false;
        }

        if( !empty( $params['fields']['email'] )
        and !empty( $accounts_settings['email_unique'] ) )
        {
            $check_arr         = array();
            $check_arr['email'] = $params['fields']['email'];

            if( $this->get_details_fields( $check_arr ) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Email address exists in database. Please pick another one.' ) );
                return false;
            }
        }

        if( empty( $params['fields']['pass'] ) )
            $params['fields']['pass'] = self::generate_password( (!empty( $accounts_settings['min_password_length'] )?$accounts_settings['min_password_length']+3:self::DEFAULT_MIN_PASSWORD_LENGTH) );

        if( empty( $params['fields']['pass_salt'] ) )
            $params['fields']['pass_salt'] = self::generate_password( (!empty( $accounts_settings['pass_salt_length'] )?$accounts_settings['pass_salt_length']+3:self::DEFAULT_MIN_PASSWORD_LENGTH) );

        $params['fields']['pass_clear'] = PHS_crypt::quick_encode( $params['fields']['pass'] );
        $params['fields']['pass'] = self::encode_pass( $params['fields']['pass'], $params['fields']['pass_salt'] );

        $now_date = date( self::DATETIME_DB );

        $params['fields']['status_date'] = $now_date;

        if( empty( $params['fields']['cdate'] ) or empty_db_date( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB );
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        $params['{accounts_settings}'] = $accounts_settings;

        if( empty( $params['{users_details}'] ) or !is_array( $params['{users_details}'] ) )
            $params['{users_details}'] = false;

        if( empty( $params['{send_confirmation_email}'] ) )
            $params['{send_confirmation_email}'] = false;

        return $params;
    }

    /**
     * Called right after a successfull insert in database. Some model need more database work after successfully adding records in database or eventually chaining
     * database inserts. If one chain fails function should return false so all records added before to be hard-deleted. In case of success, function will return an array with all
     * key-values added in database.
     *
     * @param array $insert_arr Data array added with success in database
     * @param array $params Flow parameters
     *
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function insert_after_users( $insert_arr, $params )
    {
        $insert_arr['{users_details}'] = false;

        if( !empty( $params['{users_details}'] ) and is_array( $params['{users_details}'] ) )
        {
            if( !($accounts_details_model = PHS::load_model( 'accounts_details', $this->instance_plugin_name() )) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Error obtaining account details model instance.' ) );
                return false;
            }

            $params['{users_details}']['uid'] = $insert_arr['id'];

            $details_params = array();
            $details_params['fields'] = $params['{users_details}'];

            if( !($users_details = $accounts_details_model->insert( $details_params )) )
            {
                if( $accounts_details_model->has_error() )
                    $this->copy_error( $accounts_details_model );
                else
                    $this->set_error( self::ERR_INSERT, self::_t( 'Error saving account details in database. Please try again.' ) );

                return false;
            }

            $insert_arr['{users_details}'] = $users_details;

            if( !db_query( 'UPDATE users SET details_id = \''.$users_details['id'].'\' WHERE id = \''.$insert_arr['id'].'\'', self::get_db_connection() ) )
            {
                self::st_reset_error();

                $accounts_details_model->hard_delete( $users_details );

                $this->set_error( self::ERR_INSERT, self::_t( 'Couldn\'t link account details with the account. Please try again.' ) );
                return false;
            }
        }
        
        $sent_activation_email = false;
        if( !empty( $params['{accounts_settings}'] ) and is_array( $params['{accounts_settings}'] )
        and !empty( $params['{accounts_settings}']['account_requires_activation'] )
        and $this->needs_activation( $insert_arr ) )
        {
            // send activation email...
            if( !PHS_bg_jobs::run( array( 'plugin' => 'accounts', 'action' => 'registration_email_bg' ), array( 'uid' => $insert_arr['id'] ) ) )
            {
                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_INSERT );
                else
                    $this->set_error( self::ERR_INSERT, self::_t( 'Error sending activation email. Please try again.' ) );

                return false;
            }
            $sent_activation_email = true;
        }

        if( !$sent_activation_email
        and !empty( $params['{send_confirmation_email}'] ) )
        {
            // send confirmation email...
            if( !$this->send_confirmation_email( $insert_arr ) )
                return false;
        }

        return $insert_arr;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params( $existing_data, $params )
    {
        if( !($accounts_settings = $this->get_plugin_settings())
         or !is_array( $accounts_settings ) )
            $accounts_settings = array();

        if( isset( $params['fields']['nick'] )
        and $params['fields']['nick'] != $existing_data['nick'] )
        {
            // If we delete the account, just skip checks...
            if( empty( $params['fields']['status'] )
             or $params['fields']['status'] != self::STATUS_DELETED )
            {
                $check_arr         = array();
                $check_arr['nick'] = $params['fields']['nick'];
                $check_arr['id']   = array( 'check' => '!=', 'value' => $existing_data['id'] );

                if( $this->get_details_fields( $check_arr ) )
                {
                    $this->set_error( self::ERR_EDIT, self::_t( 'Nickname already exists in database. Please pick another one.' ) );
                    return false;
                }
            }
        }

        if( !empty( $params['fields']['pass'] ) )
        {
            if( !empty( $accounts_settings['min_password_length'] )
            and strlen( $params['fields']['pass'] ) < $accounts_settings['min_password_length'] )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Password should be at least %s characters.', $accounts_settings['min_password_length'] ) );
                return false;
            }

            if( !empty( $accounts_settings['password_regexp'] )
            and !@preg_match( $accounts_settings['password_regexp'], $params['fields']['pass'] ) )
            {
                if( ($regexp_parts = explode( '/', $accounts_settings['password_regexp'] ))
                and !empty( $regexp_parts[1] ) )
                {
                    if( empty( $regexp_parts[2] ) )
                        $regexp_parts[2] = '';

                    $this->set_error( self::ERR_INSERT,
                                      self::_t( 'Password doesn\'t match regular expression <a href="https://regex101.com/?regex=%s&options=%s" title="Click for details" target="_blank">%s</a>.',
                                                $regexp_parts[1], $regexp_parts[2], $accounts_settings['password_regexp'] ) );
                } else
                    $this->set_error( self::ERR_INSERT, self::_t( 'Password doesn\'t match regular expression %s.', $accounts_settings['password_regexp'] ) );

                return false;
            }

            $params['fields']['pass_salt'] = self::generate_password( (! empty($accounts_settings['pass_salt_length']) ? $accounts_settings['pass_salt_length'] + 3 : 8) );
            $params['fields']['pass_clear'] = PHS_crypt::quick_encode( $params['fields']['pass'] );
            $params['fields']['pass'] = self::encode_pass( $params['fields']['pass'], $params['fields']['pass_salt'] );
        }

        if( isset( $params['fields']['email'] )
        and $params['fields']['email'] != $existing_data['email'] )
        {
            // If we delete the account, just skip checks...
            if( empty( $params['fields']['status'] )
             or $params['fields']['status'] != self::STATUS_DELETED )
            {
                if( empty( $params['fields']['email'] )
                 or !PHS_params::check_type( $params['fields']['email'], PHS_params::T_EMAIL ) )
                {
                    $this->set_error( self::ERR_EDIT, self::_t( 'Invalid email address.' ) );
                    return false;
                }

                if( !empty( $accounts_settings['email_unique'] ) )
                {
                    $check_arr          = array();
                    $check_arr['email'] = $params['fields']['email'];
                    $check_arr['id']    = array( 'check' => '!=', 'value' => $existing_data['id'] );

                    if( $this->get_details_fields( $check_arr ) )
                    {
                        $this->set_error( self::ERR_EDIT, self::_t( 'Email address exists in database. Please pick another one.' ) );
                        return false;
                    }
                }
            }

            $params['fields']['email_verified'] = 0;
        }

        if( isset( $params['fields']['status'] ) )
        {
            if( !$this->valid_status( $params['fields']['status'] ) )
            {
                $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid status.' ) );
                return false;
            }

            $cdate = date( self::DATETIME_DB );
            $params['fields']['status_date'] = $cdate;

            if( $params['fields']['status'] == self::STATUS_DELETED )
                $params['fields']['deleted'] = $cdate;
        }

        if( isset( $params['fields']['level'] )
        and !$this->valid_level( $params['fields']['level'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid account level.' ) );
            return false;
        }

        $params['{accounts_settings}'] = $accounts_settings;

        if( empty( $params['{users_details}'] ) or !is_array( $params['{users_details}'] ) )
            $params['{users_details}'] = false;

        return $params;
    }

    /**
     * Called right after a successfull edit action. Some model need more database work after editing records. This action is called even if model didn't save anything
     * in database.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array $edit_arr Data array saved with success in database. This can also be an empty array (nothing to save in database)
     * @param array $params Flow parameters
     *
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function edit_after( $existing_data, $edit_arr, $params )
    {
        if( empty( $existing_data['{users_details}'] ) )
        {
            if( !empty( $existing_data['details_id'] ) )
                $existing_data['{users_details}'] = $existing_data['details_id'];
            else
                $existing_data['{users_details}'] = false;
        }

        if( !empty( $params['{users_details}'] ) and is_array( $params['{users_details}'] ) )
        {
            if( !($accounts_details_model = PHS::load_model( 'accounts_details', $this->instance_plugin_name() )) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Error obtaining account details model instance.' ) );
                return false;
            }

            $users_details = false;
            if( !empty( $existing_data['{users_details}'] )
            and !($users_details = $accounts_details_model->data_to_array( $existing_data['{users_details}'] )) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Error obtaining account details from database.' ) );
                return false;
            }

            if( empty( $users_details ) )
            {
                $params['{users_details}']['uid'] = $existing_data['id'];

                $details_params = array();
                $details_params['fields'] = $params['{users_details}'];

                if( !($users_details = $accounts_details_model->insert( $details_params )) )
                {
                    if( $accounts_details_model->has_error() )
                        $this->copy_error( $accounts_details_model );
                    else
                        $this->set_error( self::ERR_EDIT, self::_t( 'Error saving account details in database. Please try again.' ) );

                    return false;
                }

                if( !db_query( 'UPDATE users SET details_id = \''.$users_details['id'].'\' WHERE id = \''.$existing_data['id'].'\'', self::get_db_connection() ) )
                {
                    self::st_reset_error();

                    $accounts_details_model->hard_delete( $users_details );

                    $this->set_error( self::ERR_EDIT, self::_t( 'Couldn\'t link account details with the account. Please try again.' ) );
                    return false;
                }

                $existing_data['details_id'] = $users_details['id'];
            } else
            {
                $details_params = array();
                $details_params['fields'] = $params['{users_details}'];

                if( !($users_details = $accounts_details_model->edit( $users_details, $details_params )) )
                {
                    if( $accounts_details_model->has_error() )
                        $this->copy_error( $accounts_details_model );
                    else
                        $this->set_error( self::ERR_EDIT, self::_t( 'Error saving account details in database. Please try again.' ) );

                    return false;
                }
            }

            $existing_data['{users_details}'] = $users_details;
        }

        if( !empty( $edit_arr['pass'] )
        and !empty( $params['{accounts_settings}'] ) and is_array( $params['{accounts_settings}'] )
        and !empty( $params['{accounts_settings}']['announce_pass_change'] ) )
        {
            // send password changed email...
            PHS_bg_jobs::run( array( 'plugin' => 'accounts', 'action' => 'pass_changed_email_bg' ), array( 'uid' => $existing_data['id'] ) );
        }

        return $existing_data;
    }

    /**
     * Prepares parameters common to _count and _list methods
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_count_list_common_params( $params = false )
    {
        if( !empty( $params['flags'] ) and is_array( $params['flags'] ) )
        {
            foreach( $params['flags'] as $flag )
            {
                switch( $flag )
                {
                    case 'include_account_details':
                        $params['db_fields'] .= ', users_details.title AS users_details_title, '.
                                                ' users_details.fname AS users_details_fname, '.
                                                ' users_details.lname AS users_details_lname, '.
                                                ' users_details.phone AS users_details_phone, '.
                                                ' users_details.company AS users_details_company ';
                        $params['join_sql'] .= ' LEFT JOIN users_details WHERE users_details.id = users.details_id ';
                    break;
                }
            }
        }

        return $params;
    }

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return array Returns an array with table fields
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
            case 'users':
                $return_arr = array(
                    self::T_DETAILS_KEY => array(
                        'comment' => 'Account information (minimal details required for login)',
                    ),

                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'nick' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'nullable' => true,
                        'index' => true,
                    ),
                    'pass_salt' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'pass' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'pass_clear' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'nullable' => true,
                    ),
                    'email' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'index' => true,
                        'nullable' => true,
                    ),
                    'email_verified' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'default' => 0,
                    ),
                    'added_by' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'details_id' => array(
                        'type' => self::FTYPE_INT,
                        'comment' => 'users_details.id',
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
                    'level' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                    ),
                    'deleted' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'lastlog' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ),
                    'lastip' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'index' => false,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;

            case 'online':
                $return_arr = array(
                    self::T_DETAILS_KEY => array(
                        'comment' => 'Users session details',
                    ),

                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'wid' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'index' => true,
                        'nullable' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'auid' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'host' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'idle' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'connected' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'expire_date' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'expire_mins' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'location' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                    ),
                );
                break;
        }

        return $return_arr;
    }
}
