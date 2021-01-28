<?php

namespace phs\plugins\accounts;

use \phs\PHS;
use \phs\PHS_Api;
use \phs\PHS_Scope;
use \phs\PHS_Session;
use \phs\PHS_Crypt;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Roles;

class PHS_Plugin_Accounts extends PHS_Plugin
{
    const ERR_LOGOUT = 40000, ERR_LOGIN = 40001, ERR_CONFIRMATION = 40002;

    const PARAM_CONFIRMATION = '_a';

    const CONF_REASON_ACTIVATION = 'activation', CONF_REASON_EMAIL = 'email', CONF_REASON_FORGOT = 'forgot';

    // After how many seconds from last request should we clean up sessions?
    // !!! should be less than 'session_expire_minutes_normal' config value
    const IDLERS_GC_SECONDS = 900; // 15 min

    private static $_session_key = 'PHS_sess';

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            'email_mandatory' => [
                'display_name' => $this->_pt( 'Email mandatory at registration' ),
                'type' => PHS_Params::T_BOOL,
                'default' => true,
            ],
            'replace_nick_with_email' => [
                'display_name' => $this->_pt( 'Replace nick with email' ),
                'display_hint' => $this->_pt( 'If, by any reasons, nickname is not provided when creating an account should it be replaced with provided email?' ),
                'type' => PHS_Params::T_BOOL,
                'default' => true,
            ],
            'no_nickname_only_email' => [
                'display_name' => $this->_pt( 'Use only email, no nickname' ),
                'display_hint' => $this->_pt( 'Hide nickname complately and use only email as nickname.' ),
                'type' => PHS_Params::T_BOOL,
                'default' => false,
            ],
            'account_requires_activation' => [
                'display_name' => $this->_pt( 'Account requires activation' ),
                'display_hint' => $this->_pt( 'Should an account be activated before login after registration? When admin creates accounts, these will be automatically active.' ),
                'type' => PHS_Params::T_BOOL,
                'default' => true,
            ],
            'generate_pass_if_not_present' => [
                'display_name' => $this->_pt( 'Generate password if not present' ),
                'display_hint' => $this->_pt( 'If, by any reasons, password is not present when creating an account autogenerate a password or return error?' ),
                'type' => PHS_Params::T_BOOL,
                'default' => true,
            ],
            'email_unique' => [
                'display_name' => $this->_pt( 'Emails should be unique' ),
                'display_hint' => $this->_pt( 'Should account creation fail if same email already exists in database?' ),
                'type' => PHS_Params::T_BOOL,
                'default' => true,
            ],
            'min_password_length' => [
                'display_name' => $this->_pt( 'Minimum password length' ),
                'type' => PHS_Params::T_INT,
                'default' => 8,
            ],
            // Make sure password generator method in accounts model follows this rule... (escape char is /)
            // password regular expression (leave empty if not wanted)
            'password_regexp' => [
                'display_name' => $this->_pt( 'Password reg-exp' ),
                'display_hint' => $this->_pt( 'If provided, all passwords have to pass this regular expression. Previous created accounts will not be affected by this. Please use / as preg_match delimiter.' ),
                'type' => PHS_Params::T_ASIS,
                'default' => '',
            ],
            'password_regexp_explanation' => [
                'display_name' => $this->_pt( 'Password explanation' ),
                'display_hint' => $this->_pt( 'Explain password rules (if required) in a friendly text (eg. Password should contain lower and upper chars, with at least one digit, etc) This will pass translation as string to be available in other languages.' ),
                'type' => PHS_Params::T_ASIS,
                'default' => '',
            ],
            'pass_salt_length' => [
                'display_name' => $this->_pt( 'Password salt length' ),
                'display_hint' => $this->_pt( 'Each account uses it\'s own password salt. (Google salt for more details)' ),
                'type' => PHS_Params::T_INT,
                'default' => 8,
            ],
            'expire_passwords_days' => [
                'display_name' => $this->_pt( 'Expire passwords days' ),
                'display_hint' => $this->_pt( 'After how many days should passwords expire (0 - no expiration)' ),
                'type' => PHS_Params::T_INT,
                'default' => 0,
            ],
            'passwords_history_count' => [
                'display_name' => $this->_pt( 'Old passwords history' ),
                'display_hint' => $this->_pt( 'When changing password, keep a history of older passwords and don\'t allow using an old one as the new password. (0 - no history)' ),
                'type' => PHS_Params::T_INT,
                'default' => 0,
            ],
            'block_after_expiration' => [
                'display_name' => $this->_pt( 'Block expired accounts time' ),
                'display_hint' => $this->_pt( 'After how many hours to force user to change account password by redirecting to change password page. (0 - right away, -1 - don\'t block, only alerts)' ),
                'type' => PHS_Params::T_INT,
                'default' => 0,
            ],
            'announce_pass_change' => [
                'display_name' => $this->_pt( 'Announce password change' ),
                'display_hint' => $this->_pt( 'Should system send an email to account\'s email address when password changes?' ),
                'type' => PHS_Params::T_BOOL,
                'default' => true,
            ],
            'session_expire_minutes_remember' => [
                'display_name' => $this->_pt( 'Password lifetime (long) mins' ),
                'display_hint' => $this->_pt( 'After how many minutes should session expire if user ticked "Remember Me" checkbox' ),
                'type' => PHS_Params::T_INT,
                'default' => 2880, // 2 days
            ],
            'session_expire_minutes_normal' => [
                'display_name' => $this->_pt( 'Password lifetime (short) mins' ),
                'display_hint' => $this->_pt( 'After how many minutes should session expire if user DIDN\'T tick "Remember Me" checkbox' ),
                'type' => PHS_Params::T_INT,
                'default' => 60, // 1 hour
            ],
        ];
    }

    /**
     * @param null|string $key
     *
     * @return string
     */
    public static function session_key( $key = null )
    {
        if( $key === null )
            return self::$_session_key;

        if( !is_string( $key ) )
            return false;

        self::$_session_key = $key;

        return self::$_session_key;
    }

    /**
     * This method should not set any errors as it runs independent of user actions...
     * @return bool
     */
    public function resolve_idler_sessions()
    {
        // preserve previous errors...
        $prev_errors = $this->stack_all_errors();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            $this->restore_errors( $prev_errors );
            return false;
        }

        // If current request doesn't have a session ID which means it's a logged in user, there's no use in cleaning old sessions...
        if( !($online_db_data = $this->_get_current_session_data( [ 'accounts_model' => $accounts_model ] ))
         || seconds_passed( $online_db_data['idle'] ) < self::IDLERS_GC_SECONDS )
        {
            if( !empty( $online_db_data ) )
                $accounts_model->update_current_session( $online_db_data );

            $this->restore_errors( $prev_errors );
            return true;
        }

        $accounts_model->clear_idler_sessions();

        // if session expired refresh cached session data...
        if( parse_db_date( $online_db_data['expire_date'] ) < time()
         && !($online_db_data = $this->_get_current_session_data( [ 'force' => true, 'accounts_model' => $accounts_model ] )) )
        {
            $this->restore_errors( $prev_errors );
            return true;
        }

        $accounts_model->update_current_session( $online_db_data );

        $this->restore_errors( $prev_errors );

        return true;
    }

    /**
     * @return bool
     */
    public function do_logout_subaccount()
    {
        $this->reset_error();

        if( !($db_details = $this->get_current_user_db_details())
         || empty( $db_details['session_db_data'] )
         || !is_array( $db_details['session_db_data'] )
         || empty( $db_details['session_db_data']['id'] )
         || empty( $db_details['session_db_data']['auid'] ) )
            return true;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        if( !($accounts_model->session_logout_subaccount( $db_details['session_db_data'] )) )
        {
            if( $accounts_model->has_error() )
                $this->copy_error( $accounts_model );
            else
                $this->set_error( self::ERR_LOGOUT, $this->_pt( 'Couldn\'t logout from subaccount.' ) );

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function do_logout()
    {
        $this->reset_error();

        if( !($db_details = $this->get_current_user_db_details())
         || empty( $db_details['session_db_data'] ) || !is_array( $db_details['session_db_data'] )
         || empty( $db_details['session_db_data']['id'] ) || empty( $db_details['session_db_data']['uid'] ) )
            return true;

        if( !empty( $db_details['session_db_data']['auid'] ) )
            return $this->do_logout_subaccount();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        if( !($accounts_model->session_logout( $db_details['session_db_data'] )) )
        {
            if( $accounts_model->has_error() )
                $this->copy_error( $accounts_model );
            else
                $this->set_error( self::ERR_LOGOUT, $this->_pt( 'Couldn\'t logout from your account. Please retry.' ) );

            return false;
        }

        if( !PHS_Session::_d( self::session_key() ) )
        {
            $this->set_error( self::ERR_LOGOUT, $this->_pt( 'Couldn\'t logout from your account. Please retry.' ) );
            return false;
        }

        return true;
    }

    /**
     * @param int|array $account_data
     * @param false|array $params
     *
     * @return array|bool
     */
    public function do_login( $account_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['expire_mins'] ) )
            $params['expire_mins'] = 0;
        else
            $params['expire_mins'] = (int)$params['expire_mins'];

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            $this->set_error( self::ERR_LOGIN, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( empty( $account_data )
         || !($account_arr = $accounts_model->data_to_array( $account_data ))
         || ! $accounts_model->is_active( $account_arr ) )
        {
            $this->set_error( self::ERR_LOGIN, $this->_pt( 'Unknown or inactive account.' ) );
            return false;
        }

        $login_params = [];
        $login_params['expire_mins'] = $params['expire_mins'];

        if( !($onuser_arr = $accounts_model->login( $account_arr, $login_params ))
         || empty( $onuser_arr['wid'] ) )
        {
            if( $accounts_model->has_error() )
                $this->copy_error( $accounts_model, self::ERR_LOGIN );
            else
                $this->set_error( self::ERR_LOGIN, $this->_pt( 'Login failed. Please try again.' ) );

            return false;
        }

        if( !PHS_Session::_s( self::session_key(), $onuser_arr['wid'] ) )
        {
            $accounts_model->session_logout( $onuser_arr );

            $this->set_error( self::ERR_LOGIN, $this->_pt( 'Login failed. Please try again.' ) );
            return false;
        }

        PHS::user_logged_in( true );

        return $onuser_arr;
    }

    /**
     * @param int|array $account_data
     * @param false|string $reason
     * @param false|array $params
     *
     * @return array|false
     */
    public function get_confirmation_params( $account_data, $reason = false, $params = false )
    {
        $this->reset_error();

        if( $reason === false )
            $reason = self::CONF_REASON_ACTIVATION;

        if( !$this->valid_confirmation_reason( $reason ) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Invalid confirmation reason.' ) );
            return false;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( empty( $account_data )
         || !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Unknown account.' ) );
            return false;
        }

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['link_expire_seconds'] ) )
            $params['link_expire_seconds'] = 0; // 0 means it doesn't expire

        $link_expire_seconds = 0;
        if( !empty( $params['link_expire_seconds'] ) )
            $link_expire_seconds = time() + $params['link_expire_seconds'];

        $pub_key = str_replace( '.', '', microtime( true ) );
        $confirmation_param = PHS_Crypt::quick_encode( $account_arr['id'].'::'.$reason.'::'.$link_expire_seconds.'::'.md5( $account_arr['nick'].':'.$pub_key.':'.$account_arr['email'] ) ).'::'.$pub_key;

        return [
            'expiration_time' => $link_expire_seconds,
            'confirmation_param' => $confirmation_param,
            'pub_key' => $pub_key,
            'account_data' => $account_arr,
        ];
    }

    /**
     * @param string $param_str
     *
     * @return array|false
     */
    public function decode_confirmation_param( $param_str )
    {
        $this->reset_error();

        if( empty( $param_str )
         || @strpos( $param_str, '::' ) === false
         || !($parts_arr = explode( '::', $param_str, 2 ))
         || empty( $parts_arr[0] ) || empty( $parts_arr[1] ) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Confirmation parameter is invalid or expired.' ) );
            return false;
        }

        $crypted_data = $parts_arr[0];
        $pub_key = $parts_arr[1];

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($decrypted_data = PHS_Crypt::quick_decode( $crypted_data ))
         || !($decrypted_parts = explode( '::', $decrypted_data, 4 ))
         || empty( $decrypted_parts[0] ) || empty( $decrypted_parts[1] ) || !isset( $decrypted_parts[2] ) || empty( $decrypted_parts[3] )
         || !($account_id = (int)$decrypted_parts[0])
         || !$this->valid_confirmation_reason( $decrypted_parts[1] )
         || (($link_expire_seconds = (int)$decrypted_parts[2]) && $link_expire_seconds < time())
         || !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() ))
         || !($account_arr = $accounts_model->get_details( $account_id ))
         || $decrypted_parts[3] !== md5( $account_arr['nick'].':'.$pub_key.':'.$account_arr['email'] ) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Confirmation parameter is invalid or expired.' ) );
            return false;
        }

        return [
            'reason' => $decrypted_parts[1],
            'pub_key' => $pub_key,
            'account_data' => $account_arr,
        ];
    }

    /**
     * @return array
     */
    public function confirmation_reasons()
    {
        // key - value pair of reson name and success message...
        return [
            self::CONF_REASON_ACTIVATION => $this->_pt( 'Your account is now active.' ),
            self::CONF_REASON_EMAIL => $this->_pt( 'Your email address is now confirmed.' ),
            self::CONF_REASON_FORGOT => $this->_pt( 'You can now change your password.' ),
        ];
    }

    /**
     * @param string $reason
     *
     * @return false|string
     */
    public function valid_confirmation_reason( $reason )
    {
        if( empty( $reason )
         || !($reasons_arr = $this->confirmation_reasons()) || empty( $reasons_arr[$reason] ) )
            return false;

        return $reasons_arr[$reason];
    }

    /**
     * @param int|array $account_data
     * @param false|string $reason
     * @param false|array $params
     *
     * @return false|string
     */
    public function get_confirmation_link( $account_data, $reason = false, $params = false )
    {
        $this->reset_error();

        if( $reason === false )
            $reason = self::CONF_REASON_ACTIVATION;

        if( !$this->valid_confirmation_reason( $reason ) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Invalid confirmation reason.' ) );
            return false;
        }

        if( !($confirmation_parts = $this->get_confirmation_params( $account_data, $reason, $params ))
         || empty( $confirmation_parts['confirmation_param'] ) || empty( $confirmation_parts['pub_key'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Couldn\'t obtain confirmation parameters.' ) );

            return false;
        }

        return PHS::url( [ 'p' => 'accounts', 'a' => 'activation' ], [ self::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param'] ] );
    }

    /**
     * @param int|array $account_data
     * @param string $reason
     *
     * @return array|false
     */
    public function do_confirmation_reason( $account_data, $reason )
    {
        $this->reset_error();

        if( !$this->valid_confirmation_reason( $reason ) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Invalid confirmation reason.' ) );
            return false;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Couldn\'t load accounts model.' ) );
            return false;
        }

        if( empty( $account_data )
         || !($account_arr = $accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Unknown account.' ) );
            return false;
        }

        $redirect_url = false;
        switch( $reason )
        {
            default:
                $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Confirmation reason unknown.' ) );
                return false;
            break;

            case self::CONF_REASON_ACTIVATION:
                if( !$accounts_model->needs_activation( $account_arr ) )
                {
                    $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Account doesn\'t require activation.' ) );
                    return false;
                }

                if( !($account_arr = $accounts_model->activate_account_after_registration( $account_arr )) )
                {
                    $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Failed activating account. Please try again.' ) );
                    return false;
                }
            break;

            case self::CONF_REASON_EMAIL:
                if( empty( $account_arr['email_verified'] )
                 && !($account_arr = $accounts_model->email_verified( $account_arr )) )
                {
                    $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Failed confirming email address. Please try again.' ) );
                    return false;
                }
            break;

            case self::CONF_REASON_FORGOT:
                if( !$accounts_model->is_active( $account_arr ) )
                {
                    $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Cannot change password for this account.' ) );
                    return false;
                }

                if( !($confirmation_parts = $this->get_confirmation_params( $account_arr, self::CONF_REASON_FORGOT, [ 'link_expire_seconds' => 3600 ] ))
                 || empty( $confirmation_parts['confirmation_param'] ) || empty( $confirmation_parts['pub_key'] ) )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_CONFIRMATION, $this->_pt( 'Couldn\'t obtain change password page parameters. Please try again.' ) );

                    return false;
                }

                $redirect_url = PHS::url( [ 'p' => $this->instance_plugin_name(), 'a' => 'change_password' ], [ self::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param'] ] );
            break;
        }

        return [
            'redirect_url' => $redirect_url,
            'account_data' => $account_arr,
        ];
    }

    /**
     * @param false|array $params
     *
     * @return array|false
     */
    private function _get_current_session_data( $params = false )
    {
        static $online_db_details = false;

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['force'] ) )
            $params['force'] = false;

        if( !empty( $online_db_details )
         && empty( $params['force'] ) )
            return $online_db_details;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( empty( $params['accounts_model'] ) )
            $accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() );
        else
            $accounts_model = $params['accounts_model'];

        if( empty( $accounts_model )
         || !($skey_value = PHS_Session::_g( self::session_key() ))
         || !($online_db_details = $accounts_model->get_details_fields( [ 'wid' => $skey_value, ], [ 'table_name' => 'online', ] )) )
            return false;

        return $online_db_details;
    }

    /**
     * @return array|false
     */
    public function get_empty_account_structure()
    {
        static $empty_structure = false;

        if( $empty_structure !== false )
            return $empty_structure;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
            return false;

        $empty_structure = $accounts_model->get_empty_data();

        $roles_slugs_arr = [];
        $role_units_slugs_arr = [];
        if( ($guest_roles = $this->get_guest_roles_and_role_units()) )
        {
            $roles_slugs_arr = $guest_roles['roles_slugs'];
            $role_units_slugs_arr = $guest_roles['role_units_slugs'];
        }

        $empty_structure[$accounts_model::ROLES_USER_KEY] = $roles_slugs_arr;
        $empty_structure[$accounts_model::ROLE_UNITS_USER_KEY] = $role_units_slugs_arr;

        return $empty_structure;
    }

    /**
     * @param false|array $hook_args
     *
     * @return array
     */
    public function get_account_structure( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_account_structure_hook_args() );

        if( empty( $hook_args['account_data'] )
         || (is_array( $hook_args['account_data'] ) && empty( $hook_args['account_data']['id'] )) )
        {
            $hook_args['account_structure'] = $this->get_empty_account_structure();

            return $hook_args;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
            return $hook_args;

        if( !($hook_args['account_structure'] = $accounts_model->data_to_array( $hook_args['account_data'] ))
         || !is_array( $hook_args['account_structure'] ) )
            $hook_args['account_structure'] = false;

        else
        {
            if( !isset( $hook_args['account_structure'][$accounts_model::ROLES_USER_KEY] ) )
            {
                if( !($slugs_arr = PHS_Roles::get_user_roles_slugs( $hook_args['account_structure'] )) )
                    $slugs_arr = [];

                $hook_args['account_structure'][$accounts_model::ROLES_USER_KEY] = $slugs_arr;
            }

            if( !isset( $hook_args['account_structure'][$accounts_model::ROLE_UNITS_USER_KEY] ) )
            {
                if( !($units_slugs_arr = PHS_Roles::get_user_role_units_slugs( $hook_args['account_structure'] )) )
                    $units_slugs_arr = [];

                $hook_args['account_structure'][$accounts_model::ROLE_UNITS_USER_KEY] = $units_slugs_arr;
            }

        }

        return $hook_args;
    }

    /**
     * @param false|array $hook_args
     *
     * @return array|bool
     */
    public function get_current_user_db_details( $hook_args = false )
    {
        static $check_result = false;

        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_user_db_details_hook_args() );

        if( empty( $hook_args['force_check'] )
         && !empty( $check_result ) )
            return $check_result;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
            return $hook_args;

        // Check if we are in API scope and we have a valid API instance...
        if( PHS_Scope::current_scope() === PHS_Scope::SCOPE_API
         && ($api_obj = PHS_Api::global_api_instance()) )
        {
            $online_db_details = $accounts_model->get_empty_data( [ 'table_name' => 'online' ] );

            if( !($account_id = $api_obj->api_user_account_id())
             || !($user_db_details = $accounts_model->get_details( $account_id ))
             || !$accounts_model->is_active( $user_db_details ) )
            {
                $hook_args['session_db_data'] = $online_db_details;
                $hook_args['user_db_data'] = $this->get_empty_account_structure();

                return $hook_args;
            }
        } else
        {
            if( !($skey_value = PHS_Session::_g( self::session_key() ))
             || !($online_db_details = $this->_get_current_session_data( [ 'accounts_model' => $accounts_model, 'force' => $hook_args['force_check'] ] )) )
            {
                $hook_args['session_db_data'] = $accounts_model->get_empty_data( [ 'table_name' => 'online' ] );
                $hook_args['user_db_data'] = $this->get_empty_account_structure();

                return $hook_args;
            }

            if( empty( $online_db_details['uid'] )
             || !($user_db_details = $accounts_model->get_details( $online_db_details['uid'] ))
             || !$accounts_model->is_active( $user_db_details )
            )
            {
                $accounts_model->hard_delete( $online_db_details, [ 'table_name' => 'online' ] );

                // session expired?
                $hook_args['session_expired_secs'] = seconds_passed( $online_db_details['idle'] );

                $hook_args['session_db_data'] = $accounts_model->get_empty_data( [ 'table_name' => 'online' ] );
                $hook_args['user_db_data'] = $this->get_empty_account_structure();

                return $hook_args;
            }
        }

        if( !($units_slugs_arr = PHS_Roles::get_user_role_units_slugs( $user_db_details )) )
            $units_slugs_arr = [];
        if( !($slugs_arr = PHS_Roles::get_user_roles_slugs( $user_db_details )) )
            $slugs_arr = [];

        $user_db_details[$accounts_model::ROLES_USER_KEY] = $slugs_arr;
        $user_db_details[$accounts_model::ROLE_UNITS_USER_KEY] = $units_slugs_arr;

        $hook_args['session_db_data'] = $online_db_details;
        $hook_args['user_db_data'] = $user_db_details;

        // Password expiration (if required)...
        if( !($hook_args['password_expired_data'] = $accounts_model->is_password_expired( $user_db_details )) )
            $hook_args['password_expired_data'] = PHS_Hooks::default_user_db_details_hook_args();
        // END Password expiration (if required)...

        $check_result = $hook_args;

        return $hook_args;
    }

    /**
     * @return array
     */
    public function get_guest_roles_and_role_units()
    {
        static $resulting_roles = false;

        if( !empty( $resulting_roles ) )
            return $resulting_roles;

        $guest_roles = [ PHS_Roles::ROLE_GUEST ];

        $hook_params = [];
        $hook_params['guest_roles'] = $guest_roles;

        if( ($hook_params = PHS_Hooks::trigger_guest_roles( $hook_params ))
         && !empty( $hook_params['guest_roles'] ) && is_array( $hook_params['guest_roles'] ) )
            $guest_roles = self::array_merge_unique_values( $guest_roles, $hook_params['guest_roles'] );

        if( empty( $guest_roles )
         || !($units_slugs_arr = PHS_Roles::get_role_units_slugs_from_roles_slugs( $guest_roles )) )
            $units_slugs_arr = [];

        $resulting_roles = [
            'roles_slugs' => $guest_roles,
            'role_units_slugs' => $units_slugs_arr,
        ];

        return $resulting_roles;
    }
}
