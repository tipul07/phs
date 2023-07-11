<?php
namespace phs\plugins\accounts;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Crypt;
use phs\PHS_Scope;
use phs\PHS_Session;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;

class PHS_Plugin_Accounts extends PHS_Plugin
{
    public const ERR_LOGOUT = 40000, ERR_LOGIN = 40001, ERR_CONFIRMATION = 40002, ERR_TOKEN = 40003;

    public const LOG_IMPORT = 'phs_accounts_import.log', LOG_SECURITY = 'phs_security.log';

    public const EXPORT_TO_FILE = 1, EXPORT_TO_OUTPUT = 2, EXPORT_TO_BROWSER = 3;

    public const ACCOUNTS_IMPORT_DIR = 'phs_accounts';

    public const PARAM_CONFIRMATION = '_a';

    public const CONF_REASON_ACTIVATION = 'activation', CONF_REASON_EMAIL = 'email', CONF_REASON_FORGOT = 'forgot',
    CONF_REASON_PASS_SETUP = 'pass_setup';

    // After how many seconds from last request should we clean up sessions?
    // !!! should be less than 'session_expire_minutes_normal' config value
    public const IDLERS_GC_SECONDS = 900; // 15 min

    // Password is mandatory, generate password if none is provided or ask user to setup a password at first login
    public const PASS_POLICY_MANDATORY = 1, PASS_POLICY_GENERATE = 2, PASS_POLICY_SETUP = 3;

    /** @var null|\phs\plugins\accounts\models\PHS_Model_Accounts */
    private ?PHS_Model_Accounts $_accounts_model = null;

    /** @var null|\phs\plugins\accounts\models\PHS_Model_Accounts_details */
    private ?PHS_Model_Accounts_details $_accounts_details_model = null;

    protected static array $PASSWORD_POLICY_ARR = [
        self::PASS_POLICY_MANDATORY => 'Password is mandatory',
        self::PASS_POLICY_GENERATE  => 'Generate password',
        self::PASS_POLICY_SETUP     => 'Setup at fist login',
    ];

    private static $_session_key = 'PHS_sess';

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            'account_registration_group' => [
                'display_name' => $this->_pt('Account Registration Settings'),
                'display_hint' => $this->_pt('Settings related to account registration.'),
                'group_fields' => [
                    'email_mandatory' => [
                        'display_name' => $this->_pt('Email mandatory at registration'),
                        'display_hint' => $this->_pt('Should users provide emails when registering?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                    'replace_nick_with_email' => [
                        'display_name' => $this->_pt('Replace nick with email'),
                        'display_hint' => $this->_pt('If, by any reasons, nickname is not provided when creating an account should it be replaced with provided email?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                    'no_nickname_only_email' => [
                        'display_name' => $this->_pt('Use only email, no nickname'),
                        'display_hint' => $this->_pt('Hide nickname complately and use only email as nickname.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'account_requires_activation' => [
                        'display_name' => $this->_pt('Account requires activation'),
                        'display_hint' => $this->_pt('Should an account be activated before login after registration? When admin creates accounts, these will be automatically active.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                    'generate_pass_if_not_present' => [
                        'display_name' => $this->_pt('Generate password if not present'),
                        'display_hint' => $this->_pt('If, by any reasons, password is not present when creating an account autogenerate a password or return error?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                    'email_unique' => [
                        'display_name' => $this->_pt('Emails should be unique'),
                        'display_hint' => $this->_pt('Should account creation fail if same email already exists in database?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                ],
            ],
            // Make sure password generator method in accounts model follows this rule... (escape char is /)
            // password regular expression (leave empty if not wanted)
            'password_settings_group' => [
                'display_name' => $this->_pt('Account Password Settings'),
                'display_hint' => $this->_pt('Settings related to account password rules.'),
                'group_fields' => [
                    'password_decryption_enabled' => [
                        'display_name' => $this->_pt('Password decryption enabled'),
                        'display_hint' => $this->_pt('If NOT ticked, system will not use internal encryption/decryption for passwords, so passwords cannot be decrypted anymore.')
                                          .' ('.$this->_pt('Accounts will have to setup a password at first login.').')',
                        'type'    => PHS_Params::T_BOOL,
                        'default' => true,
                    ],
                    'registration_password_policy' => [
                        'display_name' => $this->_pt('Password policy (if not provided)'),
                        'display_hint' => $this->_pt('When password is not provided at registration, we can generate one or ask user to setup a password at first login. If <em>Password decryption enabled</em> is NOT ticked, user will be asked to setup a password anyway.'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => self::PASS_POLICY_GENERATE,
                        'values_arr'   => self::$PASSWORD_POLICY_ARR,
                    ],
                    'min_password_length' => [
                        'display_name' => $this->_pt('Minimum password length'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 8,
                    ],
                    'password_regexp' => [
                        'display_name' => $this->_pt('Password reg-exp'),
                        'display_hint' => $this->_pt('If provided, all passwords have to pass this regular expression. Previous created accounts will not be affected by this. Please use / as preg_match delimiter.'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'password_regexp_explanation' => [
                        'display_name' => $this->_pt('Password explanation'),
                        'display_hint' => $this->_pt('Explain password rules (if required) in a friendly text (eg. Password should contain lower and upper chars, with at least one digit, etc) This will pass translation as string to be available in other languages.'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'pass_salt_length' => [
                        'display_name' => $this->_pt('Password salt length'),
                        'display_hint' => $this->_pt('Each account uses it\'s own password salt. (Google salt for more details)'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 8,
                    ],
                    'expire_passwords_days' => [
                        'display_name' => $this->_pt('Expire passwords days'),
                        'display_hint' => $this->_pt('After how many days should passwords expire (0 - no expiration)'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 0,
                    ],
                    'passwords_history_count' => [
                        'display_name' => $this->_pt('Old passwords history'),
                        'display_hint' => $this->_pt('When changing password, keep a history of older passwords and don\'t allow using an old one as the new password. (0 - no history)'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 0,
                    ],
                    'block_after_expiration' => [
                        'display_name' => $this->_pt('Block expired accounts time'),
                        'display_hint' => $this->_pt('After how many hours to force user to change account password by redirecting to change password page. (0 - right away, -1 - don\'t block, only alerts)'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 0,
                    ],
                    'announce_pass_change' => [
                        'display_name' => $this->_pt('Announce password change'),
                        'display_hint' => $this->_pt('Should system send an email to account\'s email address when password changes?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => true,
                    ],
                ],
            ],
            'session_settings_group' => [
                'display_name' => $this->_pt('Account Session Settings'),
                'display_hint' => $this->_pt('Settings related to account session rules.'),
                'group_fields' => [
                    'session_expire_minutes_remember' => [
                        'display_name' => $this->_pt('Session lifetime (long) mins'),
                        'display_hint' => $this->_pt('After how many minutes should session expire if user ticked "Remember Me" checkbox'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 2880, // 2 days
                    ],
                    'session_expire_minutes_normal' => [
                        'display_name' => $this->_pt('Session lifetime (short) mins'),
                        'display_hint' => $this->_pt('After how many minutes should session expire if user DIDN\'T tick "Remember Me" checkbox'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 60, // 1 hour
                    ],
                ],
            ],
            'security_group' => [
                'display_name' => $this->_pt('Account Security Settings'),
                'display_hint' => $this->_pt('Settings related to account security audit policies.'),
                'group_fields' => [
                    'log_account_creation' => [
                        'display_name' => $this->_pt('Log account creation'),
                        'display_hint' => $this->_pt('Should system log account creation?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_account_logins' => [
                        'display_name' => $this->_pt('Log account logins'),
                        'display_hint' => $this->_pt('Should system log account logins?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_password_changes' => [
                        'display_name' => $this->_pt('Log password changes'),
                        'display_hint' => $this->_pt('Should system log account password changes?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_roles_changes' => [
                        'display_name' => $this->_pt('Log roles changes'),
                        'display_hint' => $this->_pt('Should system log account roles changes?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return bool
     */
    public function should_log_account_creation() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_account_creation']);
    }

    /**
     * @return bool
     */
    public function should_log_account_logins() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_account_logins']);
    }

    /**
     * @return bool
     */
    public function should_log_password_changes() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_password_changes']);
    }

    /**
     * @return bool
     */
    public function should_log_roles_changes() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_roles_changes']);
    }

    /**
     * @return bool
     */
    public function is_password_decryption_enabled() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['password_decryption_enabled']);
    }

    /**
     * @return bool
     */
    public function settings_password_is_mandatory() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
                && !empty($settings_arr['registration_password_policy'])
                && $settings_arr['registration_password_policy'] === self::PASS_POLICY_MANDATORY;
    }

    /**
     * @return bool
     */
    public function settings_generate_pass_if_not_present() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
                && !empty($settings_arr['registration_password_policy'])
                && $settings_arr['registration_password_policy'] === self::PASS_POLICY_GENERATE;
    }

    /**
     * @return bool
     */
    public function settings_setup_pass_at_login_if_not_present() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
                && !empty($settings_arr['registration_password_policy'])
                && $settings_arr['registration_password_policy'] === self::PASS_POLICY_SETUP;
    }

    public function should_setup_password_at_first_login() : bool
    {
        return $this->settings_setup_pass_at_login_if_not_present()
                || !$this->is_password_decryption_enabled();
    }

    public function registration_password_mandatory() : bool
    {
        return !$this->settings_generate_pass_if_not_present() && !$this->should_setup_password_at_first_login();
    }

    public function registration_email_mandatory() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
                && !empty($settings_arr['email_mandatory']);
    }

    /**
     * This method should not set any errors as it runs independent of user actions...
     * @return bool
     */
    public function resolve_idler_sessions() : bool
    {
        // preserve previous errors...
        $prev_errors = $this->stack_all_errors();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->restore_errors($prev_errors);

            return false;
        }

        // If current request doesn't have a session ID which means it's a logged-in user, there's no use in cleaning old sessions...
        if (!($online_db_data = $this->_get_current_session_data(['accounts_model' => $accounts_model]))
         || seconds_passed($online_db_data['idle']) < self::IDLERS_GC_SECONDS) {
            if (!empty($online_db_data)) {
                $accounts_model->update_current_session($online_db_data);
            }

            $this->restore_errors($prev_errors);

            return true;
        }

        $accounts_model->clear_idler_sessions();

        // if session expired refresh cached session data...
        if (parse_db_date($online_db_data['expire_date']) < time()
         && !($online_db_data = $this->_get_current_session_data(['force' => true, 'accounts_model' => $accounts_model]))) {
            $this->restore_errors($prev_errors);

            return true;
        }

        $accounts_model->update_current_session($online_db_data);

        $this->restore_errors($prev_errors);

        return true;
    }

    /**
     * @return bool
     */
    public function do_logout_subaccount() : bool
    {
        $this->reset_error();

        if (!($db_details = $this->get_current_user_db_details())
         || empty($db_details['session_db_data'])
         || !is_array($db_details['session_db_data'])
         || empty($db_details['session_db_data']['id'])
         || empty($db_details['session_db_data']['auid'])) {
            return true;
        }

        if (!$this->_load_dependencies()) {
            return false;
        }

        if (!($this->_accounts_model->session_logout_subaccount($db_details['session_db_data']))) {
            if ($this->_accounts_model->has_error()) {
                $this->copy_error($this->_accounts_model);
            } else {
                $this->set_error(self::ERR_LOGOUT, $this->_pt('Couldn\'t logout from subaccount.'));
            }

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function do_logout() : bool
    {
        $this->reset_error();

        if (!($db_details = $this->get_current_user_db_details())
         || empty($db_details['session_db_data']) || !is_array($db_details['session_db_data'])
         || empty($db_details['session_db_data']['id']) || empty($db_details['session_db_data']['uid'])) {
            return true;
        }

        if (!empty($db_details['session_db_data']['auid'])) {
            return $this->do_logout_subaccount();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            return false;
        }

        if (!$accounts_model->session_logout($db_details['session_db_data'])) {
            if ($accounts_model->has_error()) {
                $this->copy_error($accounts_model);
            } else {
                $this->set_error(self::ERR_LOGOUT, $this->_pt('Couldn\'t logout from your account. Please retry.'));
            }

            return false;
        }

        if (!PHS::prevent_session()
         && !PHS_Session::_d(self::session_key())) {
            $this->set_error(self::ERR_LOGOUT, $this->_pt('Couldn\'t logout from your account. Please retry.'));

            return false;
        }

        if ($this->should_log_account_logins()) {
            PHS_Logger::notice('LOGOUT Account #'.($db_details['user_db_data']['id'] ?? 0).': '
                               .($db_details['user_db_data']['nick'] ?? '??').'.',
                self::LOG_SECURITY);
        }

        return true;
    }

    /**
     * @param int|array $account_data
     *
     * @return null|array
     */
    public function generate_bearer_token_for_account($account_data) : ?array
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!$this->_load_dependencies()) {
            $this->set_error(self::ERR_TOKEN, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($account_data)
         || !($account_arr = $this->_accounts_model->data_to_array($account_data))
         || !$this->_accounts_model->is_active($account_arr)) {
            $this->set_error(self::ERR_TOKEN, $this->_pt('Invalid account.'));

            return null;
        }

        if (!($token_str = PHS_Crypt::quick_encode($account_arr['id'].'::'.time()))) {
            $this->set_error(self::ERR_TOKEN, $this->_pt('Error generating bearer token.'));

            return null;
        }

        return [
            'token' => $token_str,
        ];
    }

    /**
     * @param string $token
     *
     * @return null|array
     */
    public function decode_bearer_token(string $token) : ?array
    {
        $this->reset_error();

        if (!($token_str = PHS_Crypt::quick_decode($token))
         || !($token_data = explode('::', $token_str, 2))
         || empty($token_data[0]) || empty($token_data[1])
         || $token_data[1] > time()) {
            $this->set_error(self::ERR_TOKEN, $this->_pt('Error decoding bearer token.'));

            return null;
        }

        return [
            'account_id' => (int)$token_data[0],
            'time'       => (int)$token_data[1],
        ];
    }

    /**
     * @param int|array $account_data
     * @param false|array $params
     *
     * @return array|bool
     */
    public function do_login($account_data, $params = false)
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['expire_mins'])) {
            $params['expire_mins'] = 0;
        } else {
            $params['expire_mins'] = (int)$params['expire_mins'];
        }

        if (empty($params['force_session_id']) || !is_string($params['force_session_id'])) {
            $params['force_session_id'] = '';
        } else {
            $params['force_session_id'] = trim($params['force_session_id']);
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_LOGIN, $this->_pt('Couldn\'t load accounts model.'));

            return false;
        }

        if (empty($account_data)
         || !($account_arr = $accounts_model->data_to_array($account_data))
         || !$accounts_model->is_active($account_arr)) {
            $this->set_error(self::ERR_LOGIN, $this->_pt('Unknown or inactive account.'));

            return false;
        }

        $login_params = [];
        $login_params['expire_mins'] = $params['expire_mins'];
        $login_params['force_session_id'] = $params['force_session_id'];

        if (!($onuser_arr = $accounts_model->login($account_arr, $login_params))
         || empty($onuser_arr['wid'])) {
            if ($accounts_model->has_error()) {
                $this->copy_error($accounts_model, self::ERR_LOGIN);
            } else {
                $this->set_error(self::ERR_LOGIN, $this->_pt('Login failed. Please try again.'));
            }

            return false;
        }

        if (!PHS::prevent_session()
         && !PHS_Session::_s(self::session_key(), $onuser_arr['wid'])) {
            $accounts_model->session_logout($onuser_arr);

            $this->set_error(self::ERR_LOGIN, $this->_pt('Login failed. Please try again.'));

            return false;
        }

        if ($this->should_log_account_logins()) {
            PHS_Logger::notice('LOGIN Account #'.$account_arr['id'].': '.$account_arr['nick'].'.',
                self::LOG_SECURITY);
        }

        PHS::user_logged_in(true);

        return $onuser_arr;
    }

    /**
     * @param int|array $account_data
     * @param null|string $reason
     * @param null|array $params
     *
     * @return array|false
     */
    public function get_confirmation_params($account_data, ?string $reason = null, ?array $params = null)
    {
        $this->reset_error();

        if ($reason === null) {
            $reason = self::CONF_REASON_ACTIVATION;
        }

        if (!$this->valid_confirmation_reason($reason)) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Invalid confirmation reason.'));

            return false;
        }

        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($account_data)
         || !($account_arr = $this->_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Unknown account.'));

            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['link_expire_seconds'])) {
            $params['link_expire_seconds'] = 0;
        } // 0 means it doesn't expire

        $link_expire_seconds = 0;
        if (!empty($params['link_expire_seconds'])) {
            $link_expire_seconds = time() + $params['link_expire_seconds'];
        }

        $pub_key = str_replace('.', '', microtime(true));

        if (false === ($encoded_part
                = PHS_Crypt::quick_encode(
                    $account_arr['id'].'::'.$reason.'::'.$link_expire_seconds.'::'
                    .md5($account_arr['nick'].':'.$pub_key.':'.$account_arr['email'])
                ))
        ) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Error obtaining confirmation parameter. Please try again.'));

            return false;
        }

        return [
            'expiration_time'    => $link_expire_seconds,
            'confirmation_param' => $encoded_part.'::'.$pub_key,
            'pub_key'            => $pub_key,
            'account_data'       => $account_arr,
        ];
    }

    /**
     * @param string $param_str
     *
     * @return array|false
     */
    public function decode_confirmation_param($param_str)
    {
        $this->reset_error();

        if (empty($param_str)
         || @strpos($param_str, '::') === false
         || !($parts_arr = explode('::', $param_str, 2))
         || empty($parts_arr[0]) || empty($parts_arr[1])) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Confirmation parameter is invalid or expired.'));

            return false;
        }

        $crypted_data = $parts_arr[0];
        $pub_key = $parts_arr[1];

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($decrypted_data = PHS_Crypt::quick_decode($crypted_data))
         || !($decrypted_parts = explode('::', $decrypted_data, 4))
         || empty($decrypted_parts[0]) || empty($decrypted_parts[1]) || !isset($decrypted_parts[2]) || empty($decrypted_parts[3])
         || !($account_id = (int)$decrypted_parts[0])
         || !$this->valid_confirmation_reason($decrypted_parts[1])
         || (($link_expire_seconds = (int)$decrypted_parts[2]) && $link_expire_seconds < time())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($account_arr = $accounts_model->get_details($account_id))
         || $decrypted_parts[3] !== md5($account_arr['nick'].':'.$pub_key.':'.$account_arr['email'])) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Confirmation parameter is invalid or expired.'));

            return false;
        }

        return [
            'reason'       => $decrypted_parts[1],
            'pub_key'      => $pub_key,
            'account_data' => $account_arr,
        ];
    }

    /**
     * @return array
     */
    public function confirmation_reasons() : array
    {
        // key-value pair of reson name and success message...
        return [
            self::CONF_REASON_ACTIVATION => $this->_pt('Your account is now active.'),
            self::CONF_REASON_EMAIL      => $this->_pt('Your email address is now confirmed.'),
            self::CONF_REASON_FORGOT     => $this->_pt('You can now change your password.'),
            self::CONF_REASON_PASS_SETUP => $this->_pt('Setup a password for your account.'),
        ];
    }

    /**
     * @param string $reason
     *
     * @return false|string
     */
    public function valid_confirmation_reason($reason)
    {
        if (empty($reason)
         || !($reasons_arr = $this->confirmation_reasons()) || empty($reasons_arr[$reason])) {
            return false;
        }

        return $reasons_arr[$reason];
    }

    /**
     * @param int|array $account_data
     * @param false|string $reason
     * @param null|array $params
     *
     * @return false|string
     */
    public function get_confirmation_link($account_data, $reason = false, ?array $params = null)
    {
        $this->reset_error();

        if ($reason === false) {
            $reason = self::CONF_REASON_ACTIVATION;
        }

        if (!$this->valid_confirmation_reason($reason)) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Invalid confirmation reason.'));

            return false;
        }

        if (!($confirmation_parts = $this->get_confirmation_params($account_data, $reason, $params))
         || empty($confirmation_parts['confirmation_param']) || empty($confirmation_parts['pub_key'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Couldn\'t obtain confirmation parameters.'));
            }

            return false;
        }

        return PHS::url(['p' => 'accounts', 'a' => 'activation'], [self::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param']]);
    }

    /**
     * @param int|array $account_data
     * @param string $reason
     *
     * @return array|false
     */
    public function do_confirmation_reason($account_data, $reason)
    {
        $this->reset_error();

        if (!$this->valid_confirmation_reason($reason)) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Invalid confirmation reason.'));

            return false;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Couldn\'t load accounts model.'));

            return false;
        }

        if (empty($account_data)
         || !($account_arr = $accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Unknown account.'));

            return false;
        }

        $redirect_url = false;

        switch ($reason) {
            default:
                $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Confirmation reason unknown.'));

                return false;
                break;

            case self::CONF_REASON_ACTIVATION:
                if (!$accounts_model->needs_activation($account_arr)) {
                    $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Account doesn\'t require activation.'));

                    return false;
                }

                if (!($account_arr = $accounts_model->activate_account_after_registration($account_arr))) {
                    $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Failed activating account. Please try again.'));

                    return false;
                }
                break;

            case self::CONF_REASON_EMAIL:
                if (empty($account_arr['email_verified'])
                 && !($account_arr = $accounts_model->email_verified($account_arr))) {
                    $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Failed confirming email address. Please try again.'));

                    return false;
                }
                break;

            case self::CONF_REASON_FORGOT:
                if (!$accounts_model->is_active($account_arr)) {
                    $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Cannot change password for this account.'));

                    return false;
                }

                if (!($confirmation_parts = $this->get_confirmation_params($account_arr, self::CONF_REASON_FORGOT, ['link_expire_seconds' => 3600]))
                 || empty($confirmation_parts['confirmation_param']) || empty($confirmation_parts['pub_key'])) {
                    if (!$this->has_error()) {
                        $this->set_error(self::ERR_CONFIRMATION, $this->_pt('Couldn\'t obtain change password page parameters. Please try again.'));
                    }

                    return false;
                }

                $redirect_url = PHS::url(['p' => 'accounts', 'a' => 'change_password'], [self::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param']]);
                break;

            case self::CONF_REASON_PASS_SETUP:
                if (!$accounts_model->must_setup_password($account_arr)) {
                    $redirect_args = ['setup_not_required' => 1];
                } elseif (!($confirmation_parts = $this->get_confirmation_params($account_arr, self::CONF_REASON_PASS_SETUP, ['link_expire_seconds' => 3600]))
                 || empty($confirmation_parts['confirmation_param']) || empty($confirmation_parts['pub_key'])) {
                    $redirect_args = ['request_args' => 1];
                } else {
                    $redirect_args = [self::PARAM_CONFIRMATION => $confirmation_parts['confirmation_param']];
                }

                $redirect_url = PHS::url(['p' => 'accounts', 'a' => 'setup_password'], $redirect_args);
                break;
        }

        return [
            'redirect_url' => $redirect_url,
            'account_data' => $account_arr,
        ];
    }

    /**
     * @return array|false
     */
    public function get_empty_account_structure()
    {
        static $empty_structure = false;

        if ($empty_structure !== false) {
            return $empty_structure;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS::load_model('accounts', $this->instance_plugin_name()))) {
            return false;
        }

        $empty_structure = $accounts_model->get_empty_data();

        $roles_slugs_arr = [];
        $role_units_slugs_arr = [];
        if (($guest_roles = $this->get_guest_roles_and_role_units())) {
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
    public function get_account_structure($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_account_structure_hook_args());

        if (empty($hook_args['account_data'])
         || (is_array($hook_args['account_data']) && empty($hook_args['account_data']['id']))) {
            $hook_args['account_structure'] = $this->get_empty_account_structure();

            return $hook_args;
        }

        if (!$this->_load_dependencies()) {
            return $hook_args;
        }
        $accounts_model = $this->_accounts_model;

        if (!($hook_args['account_structure'] = $accounts_model->data_to_array($hook_args['account_data']))
         || !is_array($hook_args['account_structure'])) {
            $hook_args['account_structure'] = false;
        } else {
            if (!isset($hook_args['account_structure'][$accounts_model::ROLES_USER_KEY])) {
                if (!($slugs_arr = PHS_Roles::get_user_roles_slugs($hook_args['account_structure']))) {
                    $slugs_arr = [];
                }

                $hook_args['account_structure'][$accounts_model::ROLES_USER_KEY] = $slugs_arr;
            }

            if (!isset($hook_args['account_structure'][$accounts_model::ROLE_UNITS_USER_KEY])) {
                if (!($units_slugs_arr = PHS_Roles::get_user_role_units_slugs($hook_args['account_structure']))) {
                    $units_slugs_arr = [];
                }

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
    public function get_current_user_db_details($hook_args = false)
    {
        static $check_result = false;

        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_user_db_details_hook_args());

        if (empty($hook_args['force_check'])
         && !empty($check_result)) {
            return $check_result;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            return $hook_args;
        }

        // Check if we are in API scope, and we have a valid API instance...
        if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_API
         && ($api_obj = PHS_Api::global_api_instance())) {
            $we_have_session = true;
            if (!($online_db_details = $api_obj->api_session_data())) {
                $we_have_session = false;
                $online_db_details = $accounts_model->get_empty_data(['table_name' => 'online']);
            }

            if ((!($user_db_details = $api_obj->api_account_data())
                && (!($account_id = $api_obj->api_user_account_id())
                    || !($user_db_details = $accounts_model->get_details($account_id))
                ))
                || !$accounts_model->is_active($user_db_details)
            ) {
                if ($we_have_session) {
                    $accounts_model->hard_delete($online_db_details, ['table_name' => 'online']);

                    // session expired?
                    $hook_args['session_expired_secs'] = seconds_passed($online_db_details['idle']);

                    $online_db_details = $accounts_model->get_empty_data(['table_name' => 'online']);
                }

                $hook_args['session_db_data'] = $online_db_details;
                $hook_args['user_db_data'] = $this->get_empty_account_structure();

                return $hook_args;
            }
        } else {
            if (!($skey_value = PHS_Session::_g(self::session_key()))
             || !($online_db_details = $this->_get_current_session_data(['accounts_model' => $accounts_model, 'force' => $hook_args['force_check']]))) {
                $hook_args['session_db_data'] = $accounts_model->get_empty_data(['table_name' => 'online']);
                $hook_args['user_db_data'] = $this->get_empty_account_structure();

                return $hook_args;
            }

            if (empty($online_db_details['uid'])
             || !($user_db_details = $accounts_model->get_details($online_db_details['uid']))
             || !$accounts_model->is_active($user_db_details)
            ) {
                $accounts_model->hard_delete($online_db_details, ['table_name' => 'online']);

                // session expired?
                $hook_args['session_expired_secs'] = seconds_passed($online_db_details['idle']);

                $hook_args['session_db_data'] = $accounts_model->get_empty_data(['table_name' => 'online']);
                $hook_args['user_db_data'] = $this->get_empty_account_structure();

                return $hook_args;
            }
        }

        if (!($units_slugs_arr = PHS_Roles::get_user_role_units_slugs($user_db_details))) {
            $units_slugs_arr = [];
        }
        if (!($slugs_arr = PHS_Roles::get_user_roles_slugs($user_db_details))) {
            $slugs_arr = [];
        }

        $user_db_details[$accounts_model::ROLES_USER_KEY] = $slugs_arr;
        $user_db_details[$accounts_model::ROLE_UNITS_USER_KEY] = $units_slugs_arr;

        $hook_args['session_db_data'] = $online_db_details;
        $hook_args['user_db_data'] = $user_db_details;

        // Password expiration (if required)...
        if (!($hook_args['password_expired_data'] = $accounts_model->is_password_expired($user_db_details))) {
            $hook_args['password_expired_data'] = PHS_Hooks::default_password_expiration_data();
        }
        // END Password expiration (if required)...

        $check_result = $hook_args;

        return $hook_args;
    }

    /**
     * @param false|array $hook_args
     *
     * @return array
     */
    public function trigger_plugin_settings_saved_hook($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_plugin_settings_saved_hook_args());

        // Check if accounts plugin settings were saved...
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (empty($hook_args['instance_id'])
         || $hook_args['instance_id'] !== $this->instance_id()
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($flow_arr = $accounts_model->fetch_default_flow_params(['table_name' => 'users']))) {
            return $hook_args;
        }

        if (!empty($hook_args['old_settings_arr']['password_decryption_enabled'])
        && empty($hook_args['new_settings_arr']['password_decryption_enabled'])) {
            db_query('UPDATE `'.$accounts_model->get_flow_table_name($flow_arr).'` SET pass_clear = NULL',
                $flow_arr['db_connection']);

            PHS_Logger::notice('!!! Password decryption disabled!', PHS_Logger::TYPE_MAINTENANCE);
        } else {
            PHS_Logger::notice('!!! Password decryption enabled!', PHS_Logger::TYPE_MAINTENANCE);
        }

        return $hook_args;
    }

    /**
     * @return array
     */
    public function get_guest_roles_and_role_units()
    {
        static $resulting_roles = false;

        if (!empty($resulting_roles)) {
            return $resulting_roles;
        }

        $guest_roles = [PHS_Roles::ROLE_GUEST];

        $hook_params = [];
        $hook_params['guest_roles'] = $guest_roles;

        if (($hook_params = PHS_Hooks::trigger_guest_roles($hook_params))
         && !empty($hook_params['guest_roles']) && is_array($hook_params['guest_roles'])) {
            $guest_roles = self::array_merge_unique_values($guest_roles, $hook_params['guest_roles']);
        }

        if (empty($guest_roles)
         || !($units_slugs_arr = PHS_Roles::get_role_units_slugs_from_roles_slugs($guest_roles))) {
            $units_slugs_arr = [];
        }

        $resulting_roles = [
            'roles_slugs'      => $guest_roles,
            'role_units_slugs' => $units_slugs_arr,
        ];

        return $resulting_roles;
    }

    /**
     * @param string $json_file
     * @param false|array $params
     *
     * @return false|array
     */
    public function import_accounts_from_json_file($json_file, $params = false)
    {
        $this->reset_error();

        if (empty($json_file)
         || !@file_exists($json_file)
         || !@is_file($json_file)
         || !@is_readable($json_file)) {
            $this->set_error(self::ERR_RESOURCES, $this->_pt('Import file is not readable.'));

            return false;
        }

        if (!($file_buf = @file_get_contents($json_file))
         || !($file_arr = @json_decode($file_buf, true))
         || empty($file_arr['accounts']) || !is_array($file_arr['accounts'])) {
            $this->set_error(self::ERR_RESOURCES, $this->_pt('Couldn\'t extract data from import file.'));

            return false;
        }

        return $this->import_accounts_from_json_array($file_arr, $params);
    }

    /**
     * @param array $json_arr
     * @param false|array $params
     *
     * @return false|array
     */
    public function import_accounts_from_json_array($json_arr, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        $accounts_model = $this->_accounts_model;

        /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
        if (!($a_flow = $accounts_model->fetch_default_flow_params(['table_name' => 'users']))
         || !($roles_model = PHS::load_model('roles'))) {
            $this->set_error(self::ERR_RESOURCES, $this->_pt('Error loading required resources.'));

            return false;
        }

        if (empty($json_arr) || !is_array($json_arr)
         || empty($json_arr['accounts']) || !is_array($json_arr['accounts'])) {
            $this->set_error(self::ERR_RESOURCES, $this->_pt('Accounts import data is invalid.'));

            return false;
        }

        if (!($roles_by_slug = $roles_model->get_all_roles_by_slug())) {
            $roles_by_slug = [];
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!empty($params['import_level'])
         && !$accounts_model->valid_level($params['import_level'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Import level provided is invalid.'));

            return false;
        }

        $params['insert_not_found'] = !empty($params['insert_not_found']);
        $params['override_level'] = !empty($params['override_level']);
        $params['update_roles'] = !empty($params['update_roles']);
        $params['reset_roles'] = !empty($params['reset_roles']);
        $params['update_details'] = !empty($params['update_details']);
        if (!empty($params['import_level'])) {
            $params['import_level'] = (int)$params['import_level'];
        }
        if (!isset($params['log_channel'])
         || ($params['log_channel'] !== false && !PHS_Logger::defined_channel($params['log_channel']))) {
            $params['log_channel'] = self::LOG_IMPORT;
        }

        $log_channel = $params['log_channel'];

        $return_arr = [
            'total_accounts'     => 0,
            'processed_accounts' => 0,
            'not_found'          => 0,
            'inserts'            => 0,
            'edits'              => 0,
            'errors'             => 0,
        ];

        $export_array_structure = $this->default_export_array_for_account_data();
        $extra_params = [
            'roles_by_slug' => $roles_by_slug,
        ];
        $fields_extractor_params = [
            'override_level' => $params['override_level'],
            'update_roles'   => $params['update_roles'],
            'reset_roles'    => $params['reset_roles'],
            'update_details' => $params['update_details'],
        ];

        if ($log_channel) {
            PHS_Logger::notice('[START] Importing '.count($json_arr['accounts']).' accounts...', $log_channel);
        }

        foreach ($json_arr['accounts'] as $knti => $json_account_arr) {
            if (empty($json_account_arr) || !is_array($json_account_arr)) {
                continue;
            }

            $return_arr['total_accounts']++;

            if (empty($json_account_arr['email'])) {
                if ($log_channel) {
                    PHS_Logger::error('No email set for position '.$knti.'.', $log_channel);
                }

                $return_arr['errors']++;
                continue;
            }

            if (!empty($params['import_level'])
             && !empty($json_account_arr['level'])
             && (int)$json_account_arr['level'] !== (int)$params['import_level']) {
                if ($log_channel) {
                    PHS_Logger::warning('Account with email '.$json_account_arr['email'].' ignored. Level not for import.',
                        $log_channel);
                }

                continue;
            }

            $update_roles = $params['update_roles'];
            $reset_roles = $params['reset_roles'];
            $override_level = $params['override_level'];
            $update_details = $params['update_details'];

            $account_arr = self::validate_array_recursive($json_account_arr, $export_array_structure);

            $account_deleted = false;
            if (($db_account_arr = $accounts_model->get_details_fields(['email' => $account_arr['email']]))
             && $accounts_model->is_deleted($db_account_arr)) {
                $account_deleted = true;
                $update_roles = true;
                $reset_roles = true;
                $override_level = true;
                $update_details = true;
            }

            $fields_extractor_params['update_roles'] = $update_roles;
            $fields_extractor_params['reset_roles'] = $reset_roles;
            $fields_extractor_params['override_level'] = $override_level;
            $fields_extractor_params['update_details'] = $update_details;

            if ($log_channel) {
                PHS_Logger::notice('Processing email '.$json_account_arr['email'].', DB account '
                                  .(!empty($db_account_arr) ? '#'.$db_account_arr['id'] : 'N/A')
                                  .', Update roles: '.(!empty($update_roles) ? 'YES' : 'No')
                                  .', Reset roles: '.(!empty($reset_roles) ? 'YES' : 'No')
                                  .', Update level: '.(!empty($override_level) ? 'YES' : 'No')
                                  .', Update details: '.(!empty($update_details) ? 'YES' : 'No'), $log_channel);
            }

            $extra_params['json_account_arr'] = $json_account_arr;

            if (!($action_fields = $this->_extract_account_fields_for_db($account_arr, $db_account_arr, $fields_extractor_params, $extra_params))) {
                if ($log_channel) {
                    PHS_Logger::error('Error extracting fields for DB, position '.$knti.', email '.$account_arr['email'].'.',
                        $log_channel);
                }

                $return_arr['errors']++;
                continue;
            }

            if (empty($db_account_arr)) {
                $return_arr['not_found']++;
                if (empty($params['insert_not_found'])) {
                    continue;
                }

                if (!($new_db_account_arr = $accounts_model->insert($action_fields))) {
                    if ($log_channel) {
                        if ($accounts_model->has_error()) {
                            $error_msg = $accounts_model->get_simple_error_message();
                        } else {
                            $error_msg = 'Unknown error.';
                        }

                        PHS_Logger::error('Error creating new account at '
                                           .', position '.$knti.', email '.$account_arr['email'].': '.$error_msg, $log_channel);
                    }

                    $return_arr['errors']++;
                    continue;
                }

                if ($log_channel) {
                    PHS_Logger::notice('NEW account created #'.$new_db_account_arr['id'].'.', $log_channel);
                }

                $return_arr['inserts']++;
            } else {
                if ($account_deleted) {
                    if (empty($action_fields['fields'])) {
                        $action_fields['fields'] = [];
                    }

                    $action_fields['fields']['status'] = $accounts_model::STATUS_ACTIVE;
                }

                if (!($new_db_account_arr = $accounts_model->edit($db_account_arr, $action_fields))) {
                    if ($log_channel) {
                        if ($accounts_model->has_error()) {
                            $error_msg = $accounts_model->get_simple_error_message();
                        } else {
                            $error_msg = 'Unknown error.';
                        }

                        PHS_Logger::error('Error updating account #'.$db_account_arr['id']
                                          .', position '.$knti.', email '.$account_arr['email'].': '.$error_msg, $log_channel);
                    }

                    $return_arr['errors']++;
                    continue;
                }

                if ($log_channel) {
                    PHS_Logger::notice('UPDATED account #'.$new_db_account_arr['id'].'.', $log_channel);
                }

                $return_arr['edits']++;
            }

            $return_arr['processed_accounts']++;
        }

        if ($log_channel) {
            PHS_Logger::notice('[END] Import finished!', $log_channel);
        }

        return $return_arr;
    }

    /**
     * @param int[] $account_ids
     * @param null|array $export_params
     *
     * @return bool
     */
    public function export_account_ids(array $account_ids = [], ?array $export_params = null) : bool
    {
        if (empty($export_params) || !is_array($export_params)) {
            $export_params = [];
        }

        if (empty($export_params['export_file_dir'])) {
            $export_params['export_file_dir'] = '';
        }

        if (empty($export_params['export_to'])
         || !self::valid_export_to($export_params['export_to'])) {
            $export_params['export_to'] = self::EXPORT_TO_BROWSER;
        }

        if (empty($export_params['export_file_name'])) {
            $export_params['export_file_name'] = 'accounts_export_'.date('YmdHi').'.json';
        }

        if (!($accounts_json = $this->export_account_ids_to_json($account_ids))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Nothing to export.'));

            return false;
        }

        switch ($export_params['export_to']) {
            case self::EXPORT_TO_FILE:
                if (empty($export_params['export_file_dir'])
                 || !($export_file_dir = rtrim($export_params['export_file_dir'], '/\\'))
                 || !@is_dir($export_file_dir)
                 || !@is_writable($export_file_dir)) {
                    $this->set_error(self::ERR_PARAMETERS,
                        $this->_pt('No directory provided to save export data to or no rights to write in that directory.'));

                    return false;
                }

                $full_file_path = $export_file_dir.'/'.$export_params['export_file_name'];
                if (!($fd = @fopen($full_file_path, 'wb'))) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t create export file.'));

                    return false;
                }

                @fwrite($fd, $accounts_json);
                @fflush($fd);
                @fclose($fd);
                break;

            case self::EXPORT_TO_BROWSER:
                if (@headers_sent()) {
                    $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Headers already sent. Cannot send export file to browser.'));

                    return false;
                }

                @header('Content-Transfer-Encoding: binary');
                @header('Content-Disposition: attachment; filename="'.$export_params['export_file_name'].'"');
                @header('Expires: 0');
                @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                @header('Pragma: public');
                @header('Content-Type: application/json;charset=UTF-8');

                echo $accounts_json;
                exit;
                break;

            case self::EXPORT_TO_OUTPUT:
                echo $accounts_json;
                exit;
                break;
        }

        return true;
    }

    /**
     * @param int[] $account_ids
     *
     * @return string
     */
    public function export_account_ids_to_json(array $account_ids = []) : string
    {
        if (!($accounts_arr = $this->export_account_ids_to_array($account_ids))
         || !($accounts_json = @json_encode($accounts_arr))) {
            return '';
        }

        return $accounts_json;
    }

    /**
     * @param int[] $account_ids
     *
     * @return array
     */
    public function export_account_ids_to_array(array $account_ids = []) : array
    {
        if (!($qid = $this->_get_accounts_qid($account_ids))) {
            return [];
        }

        $db_connection = $this->_accounts_model->get_db_connection(['table_name' => 'users']);
        $return_arr = $this->default_export_accounts_wrapper();
        while (($db_account_arr = @db_fetch_assoc($qid, $db_connection))) {
            if (!($account_arr = $this->populate_export_data_from_account_array($db_account_arr))) {
                continue;
            }

            if (!($roles_arr = PHS_Roles::get_user_roles_slugs($db_account_arr))) {
                $roles_arr = [];
            }

            $account_arr['roles'] = $roles_arr;

            $return_arr['accounts'][] = $account_arr;
        }

        return $return_arr;
    }

    public function default_export_accounts_wrapper() : array
    {
        return [
            'version'       => 1,
            'platform_name' => PHS_SITE_NAME.' ('.PHS_SITEBUILD_VERSION.')',
            'platform_url'  => PHS::url(['force_https' => true]),
            'accounts'      => [],
        ];
    }

    public function default_export_array_for_account_data() : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        // "hardcoded" data...
        if (!($user_details = $this->_accounts_details_model->get_empty_data())) {
            $user_details = ['title' => '', 'fname' => '', 'lname' => '',
                'phone'              => '', 'company' => '', 'limit_emails' => 0,
            ];
        }

        if (isset($user_details['id'])) {
            unset($user_details['id']);
        }
        if (isset($user_details['uid'])) {
            unset($user_details['uid']);
        }

        return [
            'nick'           => '',
            'email'          => '',
            'email_verified' => 0,
            'language'       => '',
            'status'         => 0,
            'status_date'    => null,
            'level'          => 0,
            'lastlog'        => null,
            'lastip'         => '',
            'user_details'   => $user_details,
            'roles'          => [],
        ];
    }

    /**
     * @param array $account_arr
     *
     * @return null|array
     */
    public function populate_export_data_from_account_array(array $account_arr) : ?array
    {
        if (empty($account_arr)) {
            return null;
        }

        $export_structure = $this->default_export_array_for_account_data();
        $source_keys = ['nick', 'email', 'email_verified', 'language', 'status', 'status_date',
            'level', 'lastlog', 'lastip', ];
        foreach ($source_keys as $key) {
            if (array_key_exists($key, $account_arr)
             && array_key_exists($key, $export_structure)) {
                $export_structure[$key] = $account_arr[$key];
            }
        }
        foreach (['email_verified', 'status', 'level', ] as $key) {
            if (isset($export_structure[$key])) {
                $export_structure[$key] = (int)$export_structure[$key];
            }
        }

        if (!($empty_fields = $this->_accounts_details_model->get_empty_data())) {
            // "hardcoded" fields
            $details_fields = ['title', 'fname', 'lname', 'phone', 'company', 'limit_emails', ];
        } else {
            if (isset($empty_fields['id'])) {
                unset($empty_fields['id']);
            }
            if (isset($empty_fields['uid'])) {
                unset($empty_fields['uid']);
            }

            $details_fields = array_keys($empty_fields);
        }

        foreach ($details_fields as $field_name) {
            $join_field_name = 'users_details_'.$field_name;
            // check if we have get_list() result
            if (array_key_exists($join_field_name, $account_arr)
             && array_key_exists($field_name, $export_structure['user_details'])) {
                $export_structure['user_details'][$field_name] = $account_arr[$join_field_name];
            }

            // check if we have account details result
            elseif (!empty($account_arr['{users_details}']) && is_array($account_arr['{users_details}'])
             && array_key_exists($field_name, $account_arr['{users_details}'])
             && array_key_exists($field_name, $export_structure['user_details'])) {
                $export_structure['user_details'][$field_name] = $account_arr['{users_details}'][$field_name];
            }
        }

        if (isset($export_structure['user_details']['limit_emails'])) {
            $export_structure['user_details']['limit_emails'] = (int)$export_structure['user_details']['limit_emails'];
        }

        return $export_structure;
    }

    public function get_accounts_import_dir($slash_ended = true)
    {
        $dir = PHS_UPLOADS_DIR;

        if (substr($dir, -1) !== '/') {
            $dir .= '/';
        }

        return $dir.self::ACCOUNTS_IMPORT_DIR.(!empty($slash_ended) ? '/' : '');
    }

    public function get_accounts_import_www($slash_ended = true)
    {
        $dir = PHS_UPLOADS_WWW;

        if (substr($dir, -1) !== '/') {
            $dir .= '/';
        }

        return $dir.self::ACCOUNTS_IMPORT_DIR.(!empty($slash_ended) ? '/' : '');
    }

    /**
     * @param int|array $account_data
     *
     * @return bool
     */
    public function send_account_confirmation_email($account_data) : bool
    {
        if (empty($account_data)
         || !$this->_load_dependencies()
         || !($account_arr = $this->_accounts_model->data_to_array($account_data))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));
            }

            return false;
        }

        if ($this->is_password_decryption_enabled()) {
            $clean_pass = $this->_accounts_model->clean_password($account_arr);
        } else {
            $clean_pass = $this->_accounts_model::OBFUSCATED_PASSWORD;
        }

        $hook_args = [];
        $hook_args['template'] = $this->email_template_resource_from_file('confirmation');
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Account Confirmation');
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'clean_pass'      => $clean_pass,
            'contact_us_link' => PHS::url(['a' => 'contact_us']),
            'login_link'      => PHS::url(['p' => 'accounts', 'a' => 'login'], ['nick' => $account_arr['nick']]),
        ];

        if (($hook_results = PHS_Hooks::trigger_email($hook_args)) === null) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error sending confirmation email.'));

            return false;
        }

        if (empty($hook_results) || !is_array($hook_results)
            || empty($hook_results['send_result'])) {
            if (self::st_has_error()) {
                $this->copy_static_error(self::ERR_FUNCTIONALITY);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error sending confirmation email to %s.', $account_arr['email']));
            }

            return false;
        }

        return true;
    }

    /**
     * @param int|array $account_data
     *
     * @return bool
     */
    public function send_account_password_setup($account_data) : bool
    {
        if (empty($account_data)
         || !$this->_load_dependencies()
         || !($account_arr = $this->_accounts_model->data_to_array($account_data))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));
            }

            return false;
        }

        if (!$this->_accounts_model->must_setup_password($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('No need to setup a password for provided account.'));

            return false;
        }

        $hook_args = [];
        $hook_args['template'] = $this->email_template_resource_from_file('password_setup');
        $hook_args['to'] = $account_arr['email'];
        $hook_args['to_name'] = $account_arr['nick'];
        $hook_args['subject'] = $this->_pt('Account Password Setup');
        $hook_args['email_vars'] = [
            'nick'            => $account_arr['nick'],
            'contact_us_link' => PHS::url(['a' => 'contact_us']),
            'setup_link'      => $this->get_confirmation_link($account_arr, self::CONF_REASON_PASS_SETUP),
        ];

        if (($hook_results = PHS_Hooks::trigger_email($hook_args)) === null) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error sending confirmation email.'));

            return false;
        }

        if (empty($hook_results) || !is_array($hook_results)
            || empty($hook_results['send_result'])) {
            if (self::st_has_error()) {
                $this->copy_static_error(self::ERR_FUNCTIONALITY);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error sending confirmation email to %s.', $account_arr['email']));
            }

            return false;
        }

        return true;
    }

    protected function _extract_account_fields_for_db($account_arr, $db_account_arr, $import_params, $params)
    {
        if (empty($account_arr) || !is_array($account_arr)) {
            return false;
        }

        $json_account_arr = $params['json_account_arr'];
        $roles_by_slug = $params['roles_by_slug'];

        $action_fields = [];
        if (empty($db_account_arr)) {
            // we have to create a new account...
            $account_fields = ['nick' => true, 'email' => true, 'email_verified' => true, 'language' => true,
                'level'               => true, 'status' => true, ];
        } else {
            $account_fields = ['email_verified' => true, 'language' => true, 'level' => true, ];

            if (empty($import_params['override_level'])) {
                unset($account_fields['level']);
            }
        }

        $account_fields_keys = array_keys($account_fields);
        foreach ($account_fields_keys as $field) {
            if (!isset($account_arr[$field])) {
                continue;
            }

            $action_fields['fields'][$field] = $account_arr[$field];
        }

        if (!empty($account_arr['user_details']) && is_array($account_arr['user_details'])
         && (empty($db_account_arr) || !empty($import_params['update_details']))) {
            if (isset($account_arr['user_details']['id'])) {
                unset($account_arr['user_details']['id']);
            }
            if (isset($account_arr['user_details']['uid'])) {
                unset($account_arr['user_details']['uid']);
            }

            $action_fields['{users_details}'] = $account_arr['user_details'];
        }

        if (!empty($account_arr['roles']) && is_array($account_arr['roles'])
         && (empty($db_account_arr)
             || !empty($import_params['update_roles']) || !empty($import_params['reset_roles']))) {
            // Validate roles with platform roles...
            if (!empty($db_account_arr)
             && empty($import_params['reset_roles'])) {
                $existing_roles = PHS_Roles::get_user_roles_slugs($db_account_arr);
            } else {
                $existing_roles = [];
            }

            foreach ($account_arr['roles'] as $role_slug) {
                if (empty($roles_by_slug[$role_slug])) {
                    continue;
                }

                if (!in_array($role_slug, $existing_roles, true)) {
                    $existing_roles[] = $role_slug;
                }
            }

            $action_fields['{account_roles}'] = $existing_roles;
        }

        $action_fields['{append_default_roles}'] = false;

        $hook_args = PHS_Hooks::default_import_accounts_hook_args();
        $hook_args['action_fields'] = $action_fields;
        $hook_args['import_params'] = $import_params;
        $hook_args['import_data'] = $json_account_arr;
        $hook_args['account_data'] = $db_account_arr;

        if (($import_fields_arr = PHS::trigger_hooks(PHS_Hooks::H_USERS_IMPORT_DB_FIELDS_VALIDATE, $hook_args))
         && !empty($import_fields_arr['action_fields'])) {
            $action_fields = $import_fields_arr['action_fields'];
        }

        return $action_fields;
    }

    protected function custom_activate($plugin_arr)
    {
        $this->reset_error();

        if (!$this->_create_required_directories()) {
            return false;
        }

        // Make sure we don't have static errors...
        self::st_reset_error();

        return true;
    }

    protected function custom_update($old_version, $new_version)
    {
        $this->reset_error();

        if (!$this->_create_required_directories()) {
            return false;
        }

        // Make sure we don't have static errors...
        self::st_reset_error();

        return true;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if ((empty($this->_accounts_model)
             && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
         || (empty($this->_accounts_details_model)
             && !($this->_accounts_details_model = PHS_Model_Accounts_details::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * @param false|array $params
     *
     * @return array|false
     */
    private function _get_current_session_data($params = false)
    {
        static $online_db_details = false;

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['force'])) {
            $params['force'] = false;
        }

        if (!empty($online_db_details)
         && empty($params['force'])) {
            return $online_db_details;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (empty($params['accounts_model'])) {
            $accounts_model = PHS::load_model('accounts', $this->instance_plugin_name());
        } else {
            $accounts_model = $params['accounts_model'];
        }

        if (empty($accounts_model)
         || !($skey_value = PHS_Session::_g(self::session_key()))
         || !($online_db_details = $accounts_model->get_details_fields(['wid' => $skey_value, ], ['table_name' => 'online', ]))) {
            return false;
        }

        return $online_db_details;
    }

    private function _validate_import_fields_using_hook($account_data, $import_params)
    {
        $hook_args = PHS_Hooks::default_account_structure_hook_args();
        $hook_args['account_data'] = $account_data;

        if (!($hook_result = PHS_Hooks::trigger_account_structure($hook_args))
            || empty($hook_result['account_structure'])
            || !is_array($hook_result['account_structure'])) {
            return false;
        }

        return $hook_result['account_structure'];
    }

    /**
     * @param array $account_ids
     *
     * @return null|mixed
     */
    private function _get_accounts_qid(array $account_ids = [])
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $accounts_model = $this->_accounts_model;

        if (empty($account_ids) || !is_array($account_ids)) {
            $account_ids = [];
            $new_accounts_ids = [];
        } else {
            $new_accounts_ids = self::extract_integers_from_array($account_ids);
        }

        if (!($uflow_arr = $accounts_model->fetch_default_flow_params(['table_name' => 'users']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error querying accounts for export.'));

            return null;
        }

        $list_arr = $uflow_arr;
        $list_arr['get_query_id'] = true;
        if (!empty($new_accounts_ids)) {
            $list_arr['fields']['id'] = ['check' => 'IN', 'value' => '('.implode(',', $new_accounts_ids).')'];
        }
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => $accounts_model::STATUS_DELETED];
        $list_arr['flags'] = ['include_account_details'];

        if (!($qid = $accounts_model->get_list($list_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error querying accounts for export.'));

            return null;
        }

        return $qid;
    }

    /**
     * @return bool
     */
    private function _create_required_directories()
    {
        $this->reset_error();

        if (!($accounts_import_dir = $this->get_accounts_import_dir(false))) {
            $this->set_error(self::ERR_RESOURCES,
                $this->_pt('Error obtaining products import export directory path.'));

            return false;
        }

        if (!@file_exists($accounts_import_dir)
         && !@mkdir($accounts_import_dir, 0775)
         && !@is_dir($accounts_import_dir)) {
            $this->set_error(self::ERR_RESOURCES,
                $this->_pt('Error creating temporary accounts import directory: [%s].',
                    $accounts_import_dir));

            return false;
        }

        return true;
    }

    /**
     * @param null|string $key
     *
     * @return string
     */
    public static function session_key($key = null)
    {
        if ($key === null) {
            return self::$_session_key;
        }

        if (!is_string($key)) {
            return false;
        }

        self::$_session_key = $key;

        return self::$_session_key;
    }

    public static function valid_export_to($export_to) : bool
    {
        return !empty($export_to)
                && in_array($export_to, [self::EXPORT_TO_FILE, self::EXPORT_TO_OUTPUT, self::EXPORT_TO_BROWSER], true);
    }
}
