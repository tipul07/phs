<?php

namespace phs\plugins\accounts\models;

use phs\PHS;
use phs\PHS_Crypt;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Record_data;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\traits\PHS_Trait_Has_relations;
use phs\traits\PHS_Model_Trait_statuses;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\events\accounts\PHS_Event_Accounts_generate_password;
use phs\system\core\events\accounts\PHS_Event_Accounts_password_encryption;

class PHS_Model_Accounts extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const ERR_LOGIN = 10001, ERR_EMAIL = 10002, ERR_ACCOUNT_ACTION = 10003, ERR_CHANGE_PASS = 10004, ERR_PASS_CHECK = 10005;

    public const PASSWORDS_ALGO = 'sha256';

    public const OBFUSCATED_PASSWORD = '************';

    public const ROLES_USER_KEY = '{roles_slugs}', ROLE_UNITS_USER_KEY = '{role_units_slugs}';

    public const HOOK_LEVELS = 'phs_accounts_levels', HOOK_STATUSES = 'phs_accounts_statuses';

    // "Hardcoded" minimum password length (if 'min_password_length' is not found in settings)
    public const DEFAULT_MIN_PASSWORD_LENGTH = 8;

    public const STATUS_INACTIVE = 1, STATUS_ACTIVE = 2, STATUS_SUSPENDED = 3, STATUS_DELETED = 4;

    public const LVL_GUEST = 0, LVL_MEMBER = 1,
        LVL_OPERATOR = 10, LVL_ADMIN = 11, LVL_SUPERADMIN = 12, LVL_DEVELOPER = 13;

    protected static array $STATUSES_ARR = [
        self::STATUS_INACTIVE  => ['title' => 'Inactive'],
        self::STATUS_ACTIVE    => ['title' => 'Active'],
        self::STATUS_SUSPENDED => ['title' => 'Suspended'],
        self::STATUS_DELETED   => ['title' => 'Deleted'],
    ];

    protected static array $LEVELS_ARR = [
        self::LVL_MEMBER     => ['title' => 'Member'],
        self::LVL_OPERATOR   => ['title' => 'Operator'],
        self::LVL_ADMIN      => ['title' => 'Admin'],
        self::LVL_SUPERADMIN => ['title' => 'Super admin'],
        self::LVL_DEVELOPER  => ['title' => 'Developer'],
    ];

    public function get_model_version() : string
    {
        return '1.3.8';
    }

    public function get_table_names() : array
    {
        // 'users_pass_salts' is first, so we are sure table is created before changing users table...
        return ['users_pass_salts', 'users', 'online', 'users_pass_history', ];
    }

    public function get_main_table_name() : string
    {
        return 'users';
    }

    /**
     * @inheritdoc
     */
    public function allow_record_data_keys(null | bool | array $flow_arr = []) : array
    {
        if (!($flow_arr = $this->fetch_default_flow_params($flow_arr))
            || $flow_arr['table_name'] !== 'users') {
            return [];
        }

        return [self::ROLES_USER_KEY, self::ROLE_UNITS_USER_KEY, '{users_details}', '{pass_salt}', '{old_pass_salt}'];
    }

    public function acc_is_developer(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && self::is_developer((int)($user_arr['level'] ?? 0));
    }

    public function acc_is_sadmin(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && self::is_sadmin((int)($user_arr['level'] ?? 0));
    }

    public function acc_is_admin(bool | null | int | array | PHS_Record_data $user_data, bool $strict = false) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && self::is_admin((int)($user_arr['level'] ?? 0), $strict);
    }

    public function acc_is_operator(bool | null | int | array | PHS_Record_data $user_data, bool $strict = false) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && self::is_operator((int)($user_arr['level'] ?? 0), $strict);
    }

    public function acc_is_member(bool | null | int | array | PHS_Record_data $user_data, bool $strict = false) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && self::is_member((int)($user_arr['level'] ?? 0), $strict);
    }

    public function is_active(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && (int)($user_arr['status'] ?? 0) === self::STATUS_ACTIVE;
    }

    public function is_inactive(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && (int)($user_arr['status'] ?? 0) === self::STATUS_INACTIVE;
    }

    public function is_deleted(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && (int)($user_arr['status'] ?? 0) === self::STATUS_DELETED;
    }

    public function is_just_registered(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
                && ($user_arr = $this->data_to_array($user_data))
                && empty($user_arr['lastlog']);
    }

    public function is_locked(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && !empty($user_arr['locked_date'])
               && parse_db_date($user_arr['locked_date']) > time();
    }

    public function must_setup_password(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
                && ($user_arr = $this->data_to_array($user_data))
                && empty($user_arr['pass']);
    }

    public function can_obtain_password(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
                && ($user_arr = $this->data_to_array($user_data))
                && !empty($user_arr['pass_clear']);
    }

    public function is_password_generated(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
                && ($user_arr = $this->data_to_array($user_data))
                && !empty($user_arr['pass_generated']);
    }

    public function has_logged_in(bool | null | int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
                && ($user_arr = $this->data_to_array($user_data))
                && !empty($user_arr['lastlog']);
    }

    /**
     * Password check already failed at login step, so we only manage what to do when password failed in login flow
     * (api or front-end)
     * @param int|array|PHS_Record_data $account_data
     *
     * @return null|array
     */
    public function manage_failed_password(int | array | PHS_Record_data $account_data) : ?array
    {
        $this->reset_error();

        if (!($account_arr = $this->data_to_array($account_data))
            || $this->is_deleted($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        // Check account lockout policy.
        if (($new_account = $this->_check_lockout_policy($account_arr))) {
            $account_arr = $new_account;
        }

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            PHS_Logger::notice('PASSWORD Account #'.$account_arr['id'].': '.$account_arr['nick'].' ('.$this->get_account_level_as_title($account_arr).') wrong password.',
                $accounts_plugin::LOG_SECURITY);
        }

        return $account_arr;
    }

    public function populate_account_data_for_account_contract(null | bool | int | array | PHS_Record_data $account_data) : ?array
    {
        $this->reset_error();

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))
            || $this->is_deleted($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        if ($account_arr instanceof PHS_Record_data) {
            $account_arr = $account_arr->cast_to_array();
        }

        // As we announce account action, we should have updated values...
        $structure_hook_args = PHS_Hooks::default_account_structure_hook_args();
        $structure_hook_args['account_data'] = $account_arr;

        /** @var PHS_Plugin_Accounts $plugin_obj */
        if (($plugin_obj = PHS_Plugin_Accounts::get_instance())
            && ($account_structure = $plugin_obj->get_account_structure($structure_hook_args))
            && !empty($account_structure['account_structure'])) {
            $account_arr = $account_structure['account_structure'];
        }

        if (!empty($account_arr[self::ROLES_USER_KEY])) {
            $account_arr['roles'] = $account_arr[self::ROLES_USER_KEY];
            unset($account_arr[self::ROLES_USER_KEY]);
        }
        if (!empty($account_arr[self::ROLE_UNITS_USER_KEY])) {
            $account_arr['roles_units'] = $account_arr[self::ROLE_UNITS_USER_KEY];
            unset($account_arr[self::ROLE_UNITS_USER_KEY]);
        }

        return $account_arr;
    }

    public function needs_after_registration_email(int | array | PHS_Record_data $user_data, array $params = []) : bool
    {
        if (empty($user_data)) {
            return false;
        }

        $params['send_confirmation_email'] = !empty($params['send_confirmation_email']);

        if ((empty($params['accounts_plugin_settings'])
             || !is_array($params['accounts_plugin_settings']))
            && !($params['accounts_plugin_settings'] = $this->get_plugin_settings())
        ) {
            $params['accounts_plugin_settings'] = [];
        }

        if (!($user_arr = $this->data_to_array($user_data))) {
            return false;
        }

        return $this->needs_activation($user_arr, $params) || $this->needs_confirmation_email($user_arr);
    }

    public function needs_activation(int | array | PHS_Record_data $user_data, array $params = []) : bool
    {
        if (empty($user_data)) {
            return false;
        }

        if ((empty($params['accounts_plugin_settings'])
             || !is_array($params['accounts_plugin_settings']))
            && !($params['accounts_plugin_settings'] = $this->get_plugin_settings())
        ) {
            $params['accounts_plugin_settings'] = [];
        }

        return !(empty($params['accounts_plugin_settings']['account_requires_activation'])
            || !($user_arr = $this->data_to_array($user_data))
            || !$this->is_just_registered($user_arr)
            || $this->must_setup_password($user_arr)
            || $this->is_active($user_arr)
            || $this->is_deleted($user_arr));
    }

    public function needs_confirmation_email(int | array | PHS_Record_data $user_data) : bool
    {
        // If password was provided by user, or he did already login, no need to send him password confirmation
        return !empty($user_data)
                && ($user_arr = $this->data_to_array($user_data))
                && ($this->must_setup_password($user_arr)
                    || ($this->is_password_generated($user_arr)
                        && $this->can_obtain_password($user_arr)
                        && !$this->has_logged_in($user_arr))
                );
    }

    public function needs_email_verification(int | array | PHS_Record_data $user_data) : bool
    {
        return !empty($user_data)
               && ($user_arr = $this->data_to_array($user_data))
               && empty($user_arr['email_verified'])
               && !$this->is_deleted($user_arr);
    }

    public function can_manage_account(int | array | PHS_Record_data $user_data, int | array | PHS_Record_data $user_to_manage) : bool
    {
        /** @var PHS_Plugin_Admin $admin_plugin */
        return !empty($user_data)
               && ($admin_plugin = PHS_Plugin_Admin::get_instance())
               && ($user_arr = $this->data_to_array($user_data))
               && $admin_plugin->can_admin_manage_accounts($user_arr)
               && ($user_to_manage_arr = $this->data_to_array($user_to_manage))
               && ($user_arr['level'] > $user_to_manage_arr['level']
                   || (int)$user_arr['id'] === (int)$user_to_manage_arr['id']
               );
    }

    public function get_account_details(null | bool | int | array | PHS_Record_data $account_data, array $params = []) : ?array
    {
        if (empty($account_data)) {
            return null;
        }

        $params['populate_with_empty_data'] = !empty($params['populate_with_empty_data']);

        /** @var PHS_Model_Accounts_details $accounts_details_model */
        if (!($accounts_details_model = PHS_Model_Accounts_details::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!($account_arr = $this->data_to_array($account_data))
         || empty($account_arr['details_id'])
         || !($accounts_details_arr = $accounts_details_model->get_details($account_arr['details_id']))) {
            return empty($params['populate_with_empty_data']) ? null : $accounts_details_model->get_empty_data();
        }

        return $accounts_details_arr;
    }

    final public function get_levels(null | bool | string $lang = false) : array
    {
        static $levels_arr = [];

        if (empty($lang)
            && !empty($levels_arr)) {
            return $levels_arr;
        }

        $new_levels_arr = self::$LEVELS_ARR;
        $hook_args = PHS_Hooks::default_common_hook_args();
        $hook_args['levels_arr'] = self::$LEVELS_ARR;

        if (($extra_levels_arr = PHS::trigger_hooks(PHS_Hooks::H_USER_LEVELS, $hook_args))
            && !empty($extra_levels_arr['levels_arr'])) {
            $new_levels_arr = self::merge_array_assoc($extra_levels_arr['levels_arr'], $new_levels_arr);
        }

        $return_arr = [];
        // Translate and validate levels...
        if (!empty($new_levels_arr)) {
            foreach ($new_levels_arr as $level_id => $level_arr) {
                if (!($level_id = (int)$level_id)) {
                    continue;
                }

                $level_arr['title'] = empty($level_arr['title'])
                    ? $this->_pt('Level %s', $lang, $level_id)
                    : $this->_pt($level_arr['title'], $lang);

                $return_arr[$level_id] = ['title' => $level_arr['title']];
            }
        }

        if (empty($lang)) {
            $levels_arr = $return_arr;
        }

        return $return_arr;
    }

    final public function get_levels_as_key_val(null | bool | string $lang = false) : array
    {
        static $user_levels_key_val_arr = null;

        if (empty($lang)
            && $user_levels_key_val_arr !== null) {
            return $user_levels_key_val_arr;
        }

        $return_arr = [];
        if (($user_levels = $this->get_levels($lang))) {
            foreach ($user_levels as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $return_arr[$key] = $val['title'];
            }
        }

        if (empty($lang)) {
            $user_levels_key_val_arr = $return_arr;
        }

        return $return_arr;
    }

    public function valid_level(int $level, null | bool | string $lang = false) : ?array
    {
        $all_levels = $this->get_levels($lang);
        if (empty($level)
            || empty($all_levels[$level])) {
            return null;
        }

        return $all_levels[$level];
    }

    public function get_account_level_as_title(int | array | PHS_Record_data $account_data, null | bool | string $lang = false) : string
    {
        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))
            || empty($account_arr['level'])
            || !($level_arr = $this->valid_level($account_arr['level'], $lang))) {
            return $this->_pt('N/A');
        }

        return $level_arr['title'] ?? $this->_pt('N/A');
    }

    public function raw_check_pass(?string $acc_pass, ?string $acc_salt, ?string $pass) : bool
    {
        return !empty($acc_pass)
               && !empty($acc_salt)
               && !empty($pass)
               && ($encoded_pass = self::encode_pass($pass, $acc_salt))
               && @hash_equals($acc_pass, $encoded_pass);
    }

    public function check_pass(int | array | PHS_Record_data $account_data, $pass) : ?array
    {
        if (!($account_arr = $this->data_to_array($account_data))) {
            return null;
        }

        $pass_salt = '';
        if (!empty($account_arr['pass_salt'])) {
            $pass_salt = $account_arr['pass_salt'];
        }

        if (empty($pass_salt)
         && (!($account_salt_arr = $this->get_details_fields(['uid' => $account_arr['id']], ['table_name' => 'users_pass_salts']))
             || !isset($account_salt_arr['pass_salt'])
             || !$this->raw_check_pass($account_arr['pass'], $account_salt_arr['pass_salt'], $pass)
         )) {
            return null;
        }

        return $account_arr;
    }

    public function obfuscate_password(int | array | PHS_Record_data $account_data) : string
    {
        $this->reset_error();

        if (empty($account_data)
         || !($clean_pass = $this->clean_password($account_data))) {
            return self::OBFUSCATED_PASSWORD;
        }

        // Don't put exact number of chars from password unless password is bigger than 16 chars
        return substr($clean_pass, 0, 1).str_repeat('*', max(strlen($clean_pass) - 2, 14)).substr($clean_pass, -1);
    }

    public function clean_password(int | array | PHS_Record_data $account_data) : ?string
    {
        $this->reset_error();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        if (empty($account_arr['pass_clear'])) {
            return '';
        }

        if (!($clean_pass = PHS_Crypt::quick_decode($account_arr['pass_clear']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Couldn\'t obtain account password.'));

            return null;
        }

        return $clean_pass;
    }

    public function is_password_expired(int | array | PHS_Record_data $account_data) : array
    {
        $return_arr = PHS_Hooks::default_password_expiration_data();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))
         || $this->is_deleted($account_arr)
         || !($settings_arr = $this->get_plugin_settings())
         || empty($settings_arr['expire_passwords_days'])
         || ($expire_days = (int)$settings_arr['expire_passwords_days']) <= 0) {
            return $return_arr;
        }

        $now_time = time();

        // block_after_expiration in hours
        $settings_arr['block_after_expiration'] = (int)($settings_arr['block_after_expiration'] ?? 0);

        $block_after_seconds = -1;
        if ($settings_arr['block_after_expiration'] !== -1) {
            $block_after_seconds = $settings_arr['block_after_expiration'] * 3600;
        }

        $expire_seconds = $expire_days * 86400;

        if (empty($account_arr['last_pass_change'])
         || empty_db_date($account_arr['last_pass_change'])) {
            // in case password was never changed, consider password is expired and force user to change password
            $last_pass_change_time = $now_time - $expire_seconds - $block_after_seconds - 3600;
        } else {
            $last_pass_change_time = parse_db_date($account_arr['last_pass_change']);
        }

        $expiration_time = $last_pass_change_time + $expire_seconds;

        $expired_for_seconds = 0;
        if ($expiration_time < $now_time) {
            $expired_for_seconds = $now_time - $expiration_time;
        }

        $return_arr['is_expired'] = ($expired_for_seconds > 0);
        $return_arr['show_only_warning'] = ($block_after_seconds === -1 || $expired_for_seconds < $block_after_seconds);
        $return_arr['pass_expires_seconds'] = $expiration_time;
        $return_arr['last_pass_change_seconds'] = $last_pass_change_time;
        $return_arr['expiration_days'] = $expire_days;
        $return_arr['expired_for_seconds'] = $expired_for_seconds;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    public function is_password_in_history(int | array | PHS_Record_data $account_data, string $pass, array $params = []) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'users_pass_history']))
         || !($uph_table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Cannot obtain flow params.'));

            return null;
        }

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a valid account to save password history.'));

            return null;
        }

        if ((empty($params['{accounts_settings}'])
                && !($params['{accounts_settings}'] = $this->get_plugin_settings())
        ) || !is_array($params['{accounts_settings}'])) {
            $params['{accounts_settings}'] = [];
        }

        $accounts_settings = $params['{accounts_settings}'];

        if (empty($accounts_settings['passwords_history_count'])
         || !($history_count = (int)$accounts_settings['passwords_history_count'])
         || !($qid = db_query('SELECT * '
                              .' FROM `'.$uph_table_name.'`'
                              .' WHERE uid = \''.$account_arr['id'].'\' '
                              .' ORDER BY cdate DESC LIMIT 0, '.$history_count, $flow_params['db_connection']))
         || !db_num_rows($qid, $flow_params['db_connection'])) {
            return null;
        }

        $return_arr = [];
        $return_arr['history_count'] = $history_count;
        $return_arr['oldest_password_date_timestamp'] = false;
        $return_arr['matched_history_data'] = false;

        while (($history_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            if (empty($return_arr['oldest_password_date_timestamp'])) {
                $return_arr['oldest_password_date_timestamp'] = parse_db_date($history_arr['cdate']);
            }

            if (!empty($history_arr['pass'])
             && !empty($history_arr['pass_salt'])
             && $this->raw_check_pass($history_arr['pass'], $history_arr['pass_salt'], $pass)) {
                $return_arr['matched_history_data'] = $history_arr;

                return $return_arr;
            }
        }

        return null;
    }

    public function get_account_language(int | array | PHS_Record_data $account_data) : ?string
    {
        $this->reset_error();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data, ['table_name' => 'users']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        if (empty($account_arr['language'])
         || !($clean_lang = self::valid_language($account_arr['language']))) {
            return null;
        }

        return $clean_lang;
    }

    public function set_account_language(int | array | PHS_Record_data $account_data, ?string $lang) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($lang)
         || !($clean_lang = self::valid_language($lang))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a valid language.'));

            return null;
        }

        if (empty($account_data)
         || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'users']))
         || !($users_table = $this->get_flow_table_name($flow_arr))
         || !($account_arr = $this->data_to_array($account_data, $flow_arr))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        if (!empty($account_arr['language'])
            && $account_arr['language'] === $clean_lang) {
            return $account_arr;
        }

        if (!db_query('UPDATE `'.$users_table.'` SET language = \''.$clean_lang.'\' WHERE id = \''.$account_arr['id'].'\'', $flow_arr['db_connection'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error updating account language.'));

            return null;
        }

        $account_arr['language'] = $clean_lang;

        return $account_arr;
    }

    public function clear_idler_sessions() : bool
    {
        return ($flow_params = $this->fetch_default_flow_params(['table_name' => 'online']))
               && db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` '
                        .' WHERE expire_date < \''.date(self::DATETIME_DB).'\'', $flow_params['db_connection']);
    }

    public function update_current_session(int | array | PHS_Record_data $online_data, array $params = []) : null | array | PHS_Record_data
    {
        if (empty($online_data)
            || !($online_arr = $this->data_to_array($online_data, ['table_name' => 'online']))) {
            return null;
        }

        $params['location'] = empty($params['location'])
            ? PHS::relative_url(PHS::current_url())
            : trim($params['location']);

        if (isset($params['auid'])) {
            $params['auid'] = (int)$params['auid'];
        }
        if (isset($params['uid'])) {
            $params['uid'] = (int)$params['uid'];
        }
        if (isset($params['wid'])) {
            $params['wid'] = trim($params['wid']);
        }

        if (!($host = request_ip())) {
            $host = '127.0.0.1';
        }

        $now_time = time();
        $cdate = date(self::DATETIME_DB, $now_time);

        $edit_arr = [];
        if (!empty($params['uid'])) {
            $edit_arr['uid'] = $params['uid'];
        }
        if (!empty($params['auid'])) {
            $edit_arr['auid'] = $params['auid'];
        }
        if (!empty($params['wid'])) {
            $edit_arr['wid'] = $params['wid'];
        }
        if (array_key_exists('tfa_expiration', $params)) {
            $edit_arr['tfa_expiration'] = !empty($params['tfa_expiration'])
                ? date(self::DATETIME_DB, parse_db_date($params['tfa_expiration']))
                : null;
        }
        $edit_arr['host'] = $host;
        $edit_arr['idle'] = $cdate;
        $edit_arr['expire_date'] = date(self::DATETIME_DB, $now_time + $online_arr['expire_mins'] * 60);
        $edit_arr['location'] = $params['location'];

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'online']);
        $edit_params['fields'] = $edit_arr;

        if (!($online_arr = $this->edit($online_arr, $edit_params))) {
            $this->set_error_if_not_set(self::ERR_INSERT, $this->_pt('Error saving session details to database.'));

            return null;
        }

        return $online_arr;
    }

    public function session_logout_subaccount(int | array | PHS_Record_data $online_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($online_data)
         || !($online_flow = $this->fetch_default_flow_params(['table_name' => 'online']))
         || !($online_arr = $this->data_to_array($online_data, $online_flow))
         || empty($online_arr['auid'])) {
            return null;
        }

        $edit_arr = $online_flow;
        $edit_arr['fields'] = [];
        $edit_arr['fields']['uid'] = $online_arr['auid'];
        $edit_arr['fields']['auid'] = 0;

        if ( !($new_record = $this->edit($online_arr, $edit_arr)) ) {
            return null;
        }

        return $new_record;
    }

    public function session_logout(int | array | PHS_Record_data $online_data) : bool
    {
        if (empty($online_data)
         || !($online_flow = $this->fetch_default_flow_params(['table_name' => 'online']))
         || !($online_arr = $this->data_to_array($online_data, $online_flow))
         || empty($online_arr['id'])) {
            return false;
        }

        return $this->hard_delete($online_arr, $online_flow);
    }

    /**
     * @return string
     */
    public function create_session_id() : string
    {
        return md5(uniqid(mt_rand(), true));
    }

    public function login(int | array | PHS_Record_data $account_data, array $params = []) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))
            || empty($account_arr['id'])) {
            $this->set_error(self::ERR_LOGIN, $this->_pt('Unknown account.'));

            return null;
        }

        if (empty($params['force_session_id']) || !is_string($params['force_session_id'])) {
            $params['force_session_id'] = '';
        } else {
            $params['force_session_id'] = trim($params['force_session_id']);
        }

        $params['expire_mins'] = (int)($params['expire_mins'] ?? 0);
        $params['location'] = trim($params['location'] ?? PHS::relative_url(PHS::current_url()));

        $auid = 0;
        if (($current_user = PHS::user_logged_in())
            && ($current_session = PHS::current_user_session())
            && !empty($current_session['id'])) {
            if (!can(PHS_Roles::ROLEU_LOGIN_SUBACCOUNT)) {
                $this->set_error(self::ERR_LOGIN, $this->_pt('Already logged in.'));

                return null;
            }

            $new_session_params = [];
            $new_session_params['uid'] = $account_arr['id'];
            $new_session_params['auid'] = $current_user['id'];
            $new_session_params['location'] = $params['location'];
            if (!empty($params['force_session_id'])) {
                $new_session_params['wid'] = $params['force_session_id'];
            }

            if (!($onuser_arr = $this->update_current_session($current_session, $new_session_params))) {
                $this->set_error_if_not_set(self::ERR_INSERT, $this->_pt('Error saving session details to database.'));

                return null;
            }

            return $onuser_arr;
        }

        if (!($host = request_ip())) {
            $host = '127.0.0.1';
        }

        $now_time = time();
        $cdate = date(self::DATETIME_DB, $now_time);

        $insert_arr = [];
        if (!empty($params['force_session_id'])) {
            $insert_arr['wid'] = $params['force_session_id'];
        } else {
            $insert_arr['wid'] = $this->create_session_id();
        }
        $insert_arr['uid'] = $account_arr['id'];
        $insert_arr['auid'] = $auid;
        $insert_arr['host'] = $host;
        $insert_arr['idle'] = $cdate;
        $insert_arr['connected'] = $cdate;
        $insert_arr['expire_date'] = (empty($params['expire_mins'])
            ? null : date(self::DATETIME_DB, $now_time + (60 * $params['expire_mins'])));
        $insert_arr['expire_mins'] = $params['expire_mins'];
        $insert_arr['location'] = $params['location'];

        if (!($onuser_arr = $this->insert(['table_name' => 'online', 'fields' => $insert_arr]))) {
            $this->set_error_if_not_set(self::ERR_INSERT, $this->_pt('Error saving session details to database.'));

            return null;
        }

        $edit_arr = [];
        $edit_arr['lastlog'] = $cdate;
        $edit_arr['lastip'] = $host;
        $edit_arr['failed_logins'] = 0;
        $edit_arr['locked_date'] = null;

        $this->edit($account_arr, ['fields' => $edit_arr]);

        return $onuser_arr;
    }

    public function email_verified(int | array | PHS_Record_data $account_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        if (!empty($account_arr['email_verified'])) {
            return $account_arr;
        }

        if ( !($new_record = $this->edit($account_arr, ['fields' => ['email_verified' => 1]])) ) {
            return null;
        }

        return $new_record;
    }

    public function reset_account_locking(int | array | PHS_Record_data $account_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        if (!($account_arr = $this->edit($account_arr, ['fields' => ['failed_logins' => 0, 'locked_date' => null]]))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error resetting account locking.'));

            return null;
        }

        return $account_arr;
    }

    public function activate_account_after_registration(int | array | PHS_Record_data $account_data) : null | array | PHS_Record_data
    {
        $this->reset_error();

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))
            || !$this->needs_activation($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        if (!($result = $this->edit($account_arr, ['fields' => ['status' => self::STATUS_ACTIVE], '{activate_after_registration}' => true]))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error inactivating account.'));

            return null;
        }

        if ($accounts_plugin->should_log_account_creation()) {
            PHS_Logger::notice('ACTIVATION Account #'.$account_arr['id'].': '.$account_arr['nick'].' ('.$this->get_account_level_as_title($account_arr).') was activated after registration.',
                $accounts_plugin::LOG_SECURITY);
        }

        return $result;
    }

    public function activate_account(int | array | PHS_Record_data $account_data, array $params = []) : null | array | PHS_Record_data
    {
        $this->reset_error();

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        $params['prevent_sending_emails'] = !empty($params['prevent_sending_emails']);

        if ($this->is_active($account_arr)) {
            return $account_arr;
        }

        $edit_params = [];
        if ($params['prevent_sending_emails']) {
            $edit_params['{activate_after_registration}'] = false;
        } elseif ($this->needs_confirmation_email($account_arr)
             && $this->is_just_registered($account_arr)) {
            $edit_params['{activate_after_registration}'] = true;
        }

        $edit_params['fields'] = ['status' => self::STATUS_ACTIVE];

        if (!($result = $this->edit($account_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error activating account.'));

            return null;
        }

        if ($accounts_plugin->should_log_account_creation()) {
            PHS_Logger::notice('ACTIVATION Account #'.$account_arr['id'].': '.$account_arr['nick'].' ('.$this->get_account_level_as_title($account_arr).') was activated.',
                $accounts_plugin::LOG_SECURITY);
        }

        return $result;
    }

    public function inactivate_account(int | array | PHS_Record_data $account_data, array $params = []) : null | array | PHS_Record_data
    {
        $this->reset_error();

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        $params['prevent_sending_emails'] = !empty($params['prevent_sending_emails']);

        if ($this->is_inactive($account_arr)) {
            return $account_arr;
        }

        $edit_params = [];
        if ($params['prevent_sending_emails']) {
            $edit_params['{activate_after_registration}'] = false;
        }

        $edit_params['fields'] = ['status' => self::STATUS_INACTIVE];

        if (!($result = $this->edit($account_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error inactivating account.'));

            return null;
        }

        if ($accounts_plugin->should_log_account_creation()) {
            PHS_Logger::notice('INACTIVATION Account #'.$account_arr['id'].': '.$account_arr['nick'].' ('.$this->get_account_level_as_title($account_arr).') was inactivated.',
                $accounts_plugin::LOG_SECURITY);
        }

        return $result;
    }

    public function delete_account(int | array | PHS_Record_data $account_data, array $params = []) : null | array | PHS_Record_data
    {
        $this->reset_error();

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Unknown account.'));

            return null;
        }

        if ($this->is_deleted($account_arr)) {
            return $account_arr;
        }

        $params['unlink_roles'] = !empty($params['unlink_roles']);

        //
        // We don't put before delete action to background as this should be a sync action
        //
        $hook_args = PHS_Hooks::default_account_action_hook_args();
        $hook_args['account_data'] = $account_arr;
        $hook_args['action_alias'] = 'before_delete';
        $hook_args['action_params'] = $params;
        $hook_args['route'] = PHS::get_route_details();

        if (($result_arr = PHS_Hooks::trigger_account_action($hook_args))
         && !empty($result_arr['account_data'])) {
            $account_arr = $result_arr['account_data'];
        }

        $edit_arr = [];
        $edit_arr['nick'] = $account_arr['nick'].'-DELETED-'.time();
        $edit_arr['email'] = $account_arr['email'].'-DELETED-'.time();
        $edit_arr['status'] = self::STATUS_DELETED;

        if (!($new_account_arr = $this->edit($account_arr, ['fields' => $edit_arr]))) {
            return null;
        }

        // Send account as not deleted to roles un-linking method
        if (!empty($params['unlink_roles'])) {
            PHS_Roles::unlink_all_roles_from_user($account_arr);
        }

        if ($accounts_plugin->should_log_account_creation()) {
            PHS_Logger::notice('DELETE Account #'.$account_arr['id'].': '.$account_arr['nick'].' ('.$this->get_account_level_as_title($account_arr).') was deleted.',
                $accounts_plugin::LOG_SECURITY);
        }

        $account_arr = $new_account_arr;

        //
        // After delete should be in background (all actions which require more time should be hooked here)
        //
        $hook_args = PHS_Hooks::default_account_action_hook_args();
        $hook_args['account_data'] = $account_arr;
        $hook_args['action_alias'] = 'after_delete';
        $hook_args['action_params'] = $params;
        $hook_args['route'] = PHS::get_route_details();

        $this->trigger_account_action_in_background($hook_args);

        return $account_arr;
    }

    public function trigger_account_action_in_background(array $hook_args) : bool | array
    {
        $this->reset_error();

        // If no plugin is registered to this hook, there is no use in launching a background job for it
        if (!PHS::hook_has_callbacks(PHS_Hooks::H_USER_ACCOUNT_ACTION)) {
            return $hook_args;
        }

        if (!($hook_args = self::validate_array($hook_args, PHS_Hooks::default_account_action_hook_args()))
         || empty($hook_args['account_data'])
         || !($account_arr = $this->data_to_array($hook_args['account_data'], ['table_name' => 'users']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return false;
        }

        $hook_args['account_data'] = $account_arr['id'];

        if (!PHS_Bg_jobs::run(['p' => 'accounts', 'a' => 'account_action_bg', 'c' => 'index_bg'], $hook_args)) {
            $this->copy_or_set_static_error(self::ERR_ACCOUNT_ACTION, $this->_pt('Error launching account action in background.'));

            PHS_Logger::error('Error launching account action ['.(!empty($hook_args['action_alias']) ? $hook_args['action_alias'] : 'N/A').'] in background. ('.$this->get_simple_error_message().')', PHS_Logger::TYPE_ERROR);

            return false;
        }

        $hook_args['account_data'] = $account_arr;

        return $hook_args;
    }

    public function send_confirmation_email(int | array | PHS_Record_data $account_data) : bool
    {
        $this->reset_error();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_EMAIL, $this->_pt('Unknown account.'));

            return false;
        }

        if (!$this->needs_confirmation_email($account_arr)) {
            $this->set_error(self::ERR_EMAIL, $this->_pt('This account doesn\'t need a confirmation email anymore. Logged in before or already active.'));

            return false;
        }

        if (!PHS_Bg_jobs::run(['p' => 'accounts', 'a' => 'registration_confirmation_bg', 'c' => 'index_bg'], ['uid' => $account_arr['id']])) {
            $this->copy_or_set_static_error(self::ERR_EMAIL, $this->_pt('Error sending confirmation email. Please try again.'));

            return false;
        }

        return true;
    }

    public function send_after_registration_email(int | array | PHS_Record_data $account_data, array $params = []) : ?array
    {
        $this->reset_error();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_EMAIL, $this->_pt('Unknown account.'));

            return null;
        }

        $params['send_confirmation_email'] = !empty($params['send_confirmation_email']);

        if ((empty($params['accounts_plugin_settings'])
             || !is_array($params['accounts_plugin_settings']))
            && !($params['accounts_plugin_settings'] = $this->get_plugin_settings())
        ) {
            $params['accounts_plugin_settings'] = [];
        }

        $return_arr = [];
        $return_arr['has_error'] = false;
        $return_arr['activation_email_required'] = false;
        $return_arr['activation_email_failed'] = false;
        $return_arr['confirmation_email_required'] = false;
        $return_arr['confirmation_email_failed'] = false;

        if (!$this->needs_after_registration_email($account_arr, $params)) {
            return $return_arr;
        }

        if ($this->needs_activation($account_arr, ['accounts_plugin_settings' => $params['accounts_plugin_settings']])) {
            $return_arr['activation_email_required'] = true;

            // send activation email...
            if (!PHS_Bg_jobs::run(['p' => 'accounts', 'a' => 'registration_email_bg', 'c' => 'index_bg'], ['uid' => $account_arr['id']])) {
                $return_arr['has_error'] = true;
                $return_arr['activation_email_failed'] = true;

                $this->copy_or_set_static_error(self::ERR_EMAIL, $this->_pt('Error sending activation email. Please try again.'));
            }

            return $return_arr;
        }

        if (!empty($params['send_confirmation_email'])) {
            $return_arr['confirmation_email_required'] = true;

            // send confirmation email...
            if ($this->needs_confirmation_email($account_arr)
                && !$this->send_confirmation_email($account_arr)) {
                $return_arr['has_error'] = true;
                $return_arr['confirmation_email_failed'] = true;
            }

            $this->reset_error();
        }

        return $return_arr;
    }

    public function update_user_details(int | array | PHS_Record_data $account_data, array $user_details_arr) : null | array | PHS_Record_data
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'users']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Invalid flow parameters while updating user details.'));

            return null;
        }

        /** @var PHS_Model_Accounts_details $accounts_details_model */
        if (!($accounts_details_model = PHS_Model_Accounts_details::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining account details model instance.'));

            return null;
        }

        if (!($account_arr = $this->data_to_array($account_data, $flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid account to update details.'));

            return null;
        }

        if (empty($account_arr['details_id'])
         || !($users_details = $accounts_details_model->get_details($account_arr['details_id']))) {
            $users_details = false;
        }

        $hook_args = PHS_Hooks::default_user_account_fields_hook_args();
        $hook_args['account_data'] = $account_arr;
        $hook_args['account_details_data'] = $users_details;
        $hook_args['account_details_fields'] = $user_details_arr;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_USERS_DETAILS_FIELDS, $hook_args))
         && is_array($hook_args) && !empty($hook_args['account_details_fields'])) {
            $user_details_arr = $hook_args['account_details_fields'];
        }

        if (empty($user_details_arr) || !is_array($user_details_arr)) {
            $account_arr['{users_details}'] = null;

            return $account_arr;
        }

        if (empty($users_details)) {
            // no details yet saved...
            $user_details_arr['uid'] = $account_arr['id'];

            $details_params = $accounts_details_model->fetch_default_flow_params(['table_name' => 'users_details']);
            $details_params['fields'] = $user_details_arr;

            if (!($users_details = $accounts_details_model->insert($details_params))) {
                if ($accounts_details_model->has_error()) {
                    $this->copy_error($accounts_details_model);
                } else {
                    $this->set_error(self::ERR_INSERT, $this->_pt('Error saving account details in database. Please try again.'));
                }

                return null;
            }
        } else {
            $details_params = $accounts_details_model->fetch_default_flow_params(['table_name' => 'users_details']);
            $details_params['fields'] = $user_details_arr;

            if (!($users_details = $accounts_details_model->edit($users_details, $details_params))) {
                $this->copy_or_set_error($accounts_details_model,
                    self::ERR_INSERT, $this->_pt('Error saving account details in database. Please try again.'));

                return null;
            }
        }

        if (empty($account_arr['details_id'])
            && !db_query('UPDATE `'.$this->get_flow_table_name($flow_params).'` SET details_id = \''.$users_details['id'].'\' '
                         .'WHERE id = \''.$account_arr['id'].'\'', $this->get_db_connection($flow_params))) {
            self::st_reset_error();

            $accounts_details_model->hard_delete($users_details);

            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Couldn\'t link account details with the account. Please try again.'));

            return null;
        }

        $account_arr['details_id'] = $users_details['id'];
        $account_arr['{users_details}'] = $users_details;

        $hook_args = PHS_Hooks::default_user_account_hook_args();
        $hook_args['account_data'] = $account_arr;
        $hook_args['account_details_data'] = $users_details;

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_USERS_DETAILS_UPDATED, $hook_args))
         && is_array($hook_args) && !empty($hook_args['account_data'])) {
            $account_arr = $hook_args['account_data'];
        }

        return $account_arr;
    }
    //
    // endregion Version Updates
    //

    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'users':
                $return_arr = [
                    self::T_DETAILS_KEY => [
                        'comment' => 'Account information (minimal details required for login)',
                    ],
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'nick' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'index'    => true,
                    ],
                    'pass' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 100,
                        'nullable' => true,
                    ],
                    'pass_clear' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                    ],
                    'email' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'index'    => true,
                        'nullable' => true,
                    ],
                    'email_verified' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 0,
                    ],
                    'language' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 5,
                        'nullable' => true,
                        'comment'  => 'Last selected language',
                    ],
                    'pass_generated' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'default' => 0,
                    ],
                    'added_by' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'details_id' => [
                        'type'    => self::FTYPE_INT,
                        'comment' => 'users_details.id',
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => false,
                    ],
                    'level' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                    ],
                    'is_multitenant' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'index'   => true,
                        'default' => 1,
                        'comment' => '1 - all tenants, 0 - check users_tenants',
                    ],
                    'failed_logins' => [
                        'type'    => self::FTYPE_TINYINT,
                        'default' => 0,
                    ],
                    'locked_date' => [
                        'type'    => self::FTYPE_DATETIME,
                        'default' => null,
                    ],
                    'deleted' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'last_pass_change' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => false,
                    ],
                    'lastlog' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => false,
                    ],
                    'lastip' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'index'    => false,
                        'length'   => 50,
                        'nullable' => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;

            case 'users_pass_history':
                $return_arr = [
                    self::T_DETAILS_KEY => [
                        'comment' => 'Users passwords history',
                    ],
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'changed_by_uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true, ],
                    'pass' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 100,
                        'nullable' => true,
                    ],
                    'pass_salt' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 50,
                        'nullable' => true,
                    ],
                    'pass_clear' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;

            case 'users_pass_salts':
                $return_arr = [
                    self::T_DETAILS_KEY => [
                        'comment' => 'Users passwords salt',
                    ],
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'pass_salt' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 50,
                        'nullable' => true,
                    ],
                ];
                break;

            case 'online':
                $return_arr = [
                    self::T_DETAILS_KEY => [
                        'comment' => 'Users session details',
                    ],
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'wid' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'index'    => true,
                        'nullable' => true,
                    ],
                    'uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'auid' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'host' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                    ],
                    'idle' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'connected' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'tfa_expiration' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'expire_date' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'expire_mins' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'location' => [
                        'type'     => self::FTYPE_TEXT,
                        'nullable' => true,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function _relations_definition() : void
    {
        $this->relation_one_to_one( 'details',
            PHS_Model_Accounts_details::class, 'details_id', ['table_name' => 'users_details'],
        );

        $this->relation_many_to_many('roles_slugs',
            PHS_Model_Roles::class, 'id',
            PHS_Model_Roles::class, 'role_id', 'user_id',
            ['table_name' => 'roles'],
            ['table_name' => 'roles_users'],
            filter_fn: function(PHS_Record_data $role_data) {
                return $role_data['slug'] ?? '';
            },
        );

        $this->relation_many_to_many('roles_units_slugs',
            PHS_Model_Roles::class, 'id',
            PHS_Model_Roles::class, 'role_id', 'user_id',
            ['table_name' => 'roles'],
            ['table_name' => 'roles_users'],
            filter_fn: function(PHS_Record_data $result) {
                $return_arr = [];
                foreach ($result->role_units_slugs(0, 100)?->yield() ?? [] as $role_unit_slug) {
                    $return_arr[] = $role_unit_slug;
                }

                return $return_arr;
            },
            options: ['merge_relation_results' => true],
        );
    }

    //
    // Custom updates
    //
    protected function custom_after_update($old_version, $new_version)
    {
        return !(@version_compare($old_version, '1.0.3', '<=')
         && @version_compare($new_version, '1.0.4', '>=')
         && !$this->_update_to_104_or_higher());
    }

    protected function custom_after_missing_tables_update($old_version, $new_version, $params_arr = false)
    {
        return !(@version_compare($old_version, '1.0.4', '<=')
         && @version_compare($new_version, '1.1.0', '>=')
         && !$this->_update_to_110_or_higher());
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
    protected function get_insert_prepare_params_users($params)
    {
        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (empty($params) || !is_array($params)
         || !($accounts_plugin = $this->get_plugin_instance())) {
            $this->set_error_if_not_set(self::ERR_INSERT, $this->_pt('Error loading required resources.'));

            return false;
        }

        $accounts_settings = $this->get_plugin_settings();

        if (empty($params['fields']['email'])
            && !$accounts_plugin->registration_email_mandatory()) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide an email.'));

            return false;
        }

        if (!empty($params['fields']['email'])
            && !PHS_Params::check_type($params['fields']['email'], PHS_Params::T_EMAIL)) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a valid email.'));

            return false;
        }

        if (!empty($params['fields']['email'])
         && (
             (empty($params['fields']['nick']) && !empty($accounts_settings['replace_nick_with_email']))
             || !empty($accounts_settings['no_nickname_only_email'])
         )) {
            $params['fields']['nick'] = $params['fields']['email'];
        }

        if (empty($params['fields']['nick'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide an username.'));

            return false;
        }

        if (empty($params['fields']['level'])) {
            $params['fields']['level'] = self::LVL_MEMBER;
        }
        if (empty($params['fields']['status'])) {
            if (empty($accounts_settings['account_requires_activation'])) {
                $params['fields']['status'] = self::STATUS_ACTIVE;
            } else {
                $params['fields']['status'] = self::STATUS_INACTIVE;
            }
        }

        if (!$this->valid_level($params['fields']['level'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a valid account level.'));

            return false;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a valid status.'));

            return false;
        }

        $should_setup_password_at_first_login = $accounts_plugin->should_setup_password_at_first_login();

        if (empty($params['fields']['pass'])
            && !$should_setup_password_at_first_login
            && $accounts_plugin->registration_password_mandatory()) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a password.'));

            return false;
        }

        if ($should_setup_password_at_first_login) {
            $params['fields']['pass'] = null;
            $params['fields']['pass_generated'] = 0;
        } elseif (!empty($params['fields']['pass'])
            && !$this->validate_password_rules($params['fields']['pass'], $accounts_settings)) {
            $this->change_error_code(self::ERR_INSERT);

            return false;
        }

        if ($this->get_details_fields(['nick' => $params['fields']['nick']])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Username already exists in database. Please pick another one.'));

            return false;
        }

        if (!empty($params['fields']['email'])
            && !empty($accounts_settings['email_unique'])
            && $this->get_details_fields(['email' => $params['fields']['email']])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Email address exists in database. Please pick another one.'));

            return false;
        }

        $now_date = date(self::DATETIME_DB);

        if ($should_setup_password_at_first_login) {
            $encoded_pass = null;
            $encoded_clear = null;
        } else {
            if (empty($params['fields']['pass'])) {
                $pass_length = !empty($accounts_settings['min_password_length'])
                    ? $accounts_settings['min_password_length'] + 3
                    : self::DEFAULT_MIN_PASSWORD_LENGTH;

                $params['fields']['pass'] = self::generate_password($pass_length);
                $params['fields']['pass_generated'] = 1;
            } else {
                $params['fields']['pass_generated'] = empty($params['fields']['pass_generated']) ? 0 : 1;
            }

            if (empty($params['{pass_salt}'])) {
                $params['{pass_salt}'] = self::generate_password((!empty($accounts_settings['pass_salt_length']) ? $accounts_settings['pass_salt_length'] + 3 : self::DEFAULT_MIN_PASSWORD_LENGTH));
            }

            $encoded_clear = null;
            if ($accounts_plugin->is_password_decryption_enabled()
                && false === ($encoded_clear = PHS_Crypt::quick_encode($params['fields']['pass']))) {
                $this->set_error(self::ERR_INSERT, $this->_pt('Error encrypting account password. Please retry.'));

                return false;
            }

            if (!($encoded_pass = self::encode_pass($params['fields']['pass'], $params['{pass_salt}']))) {
                $this->set_error(self::ERR_INSERT, $this->_pt('Error obtaining account password. Please retry.'));

                return false;
            }

            $params['fields']['last_pass_change'] = $now_date;
        }

        $params['fields']['pass_clear'] = $encoded_clear;
        $params['fields']['pass'] = $encoded_pass;

        $params['fields']['status_date'] = $now_date;

        $params['fields']['cdate'] = empty($params['fields']['cdate'])
            ? $now_date
            : date(self::DATETIME_DB, parse_db_date($params['fields']['cdate']));

        $params['{accounts_settings}'] = $accounts_settings;

        if (empty($params['{users_details}']) || !is_array($params['{users_details}'])) {
            $params['{users_details}'] = false;
        }
        if (empty($params['{account_roles}']) || !is_array($params['{account_roles}'])) {
            $params['{account_roles}'] = false;
        }

        if (!isset($params['{account_tenants}']) || !is_array($params['{account_tenants}'])
            || !PHS::is_multi_tenant()) {
            $params['{account_tenants}'] = null;
        }

        $params['{append_default_roles}'] = !isset($params['{append_default_roles}']) || !empty($params['{append_default_roles}']);
        $params['{send_confirmation_email}'] = !empty($params['{send_confirmation_email}']);

        return $params;
    }

    protected function insert_after_users(array $insert_arr, array $params) : ?array
    {
        if (empty($params['{accounts_settings}']) || !is_array($params['{accounts_settings}'])) {
            $params['{accounts_settings}'] = [];
        }

        $insert_arr['{users_details}'] = false;
        $insert_arr['{pass_salt}'] = false;

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        /** @var PHS_Model_Accounts_details $accounts_details_model */
        if (!($accounts_details_model = PHS_Model_Accounts_details::get_instance())
            || !($accounts_plugin = $this->get_plugin_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!empty($params['{pass_salt}'])) {
            $salt_insert_arr = $this->fetch_default_flow_params(['table_name' => 'users_pass_salts']) ?: [];
            $salt_insert_arr['fields']['uid'] = $insert_arr['id'];
            $salt_insert_arr['fields']['pass_salt'] = $params['{pass_salt}'];

            if (!($salt_arr = $this->insert($salt_insert_arr))) {
                $this->set_error(self::ERR_INSERT, $this->_pt('Error saving account password. Please try again.'));

                return null;
            }

            $insert_arr['{pass_salt}'] = $salt_arr;
        }

        if (!empty($params['{users_details}']) && is_array($params['{users_details}'])
            && !($insert_arr = $this->update_user_details($insert_arr, $params['{users_details}']))) {
            $this->set_error_if_not_set(self::ERR_INSERT, $this->_pt('Error saving account details in database. Please try again.'));

            return null;
        }

        $roles_arr = [];
        if (!empty($params['{append_default_roles}'])) {
            if ($this->acc_is_admin($insert_arr)) {
                $roles_arr = [PHS_Roles::ROLE_MEMBER, PHS_Roles::ROLE_OPERATOR, PHS_Roles::ROLE_ADMIN];
            } elseif ($this->acc_is_operator($insert_arr)) {
                $roles_arr = [PHS_Roles::ROLE_MEMBER, PHS_Roles::ROLE_OPERATOR];
            } else {
                $roles_arr = [PHS_Roles::ROLE_MEMBER];
            }

            $hook_args = PHS_Hooks::default_user_registration_roles_hook_args();
            $hook_args['roles_arr'] = $roles_arr;
            $hook_args['account_data'] = $insert_arr;

            if (($extra_roles_arr = PHS::trigger_hooks(PHS_Hooks::H_USER_REGISTRATION_ROLES, $hook_args))
             && is_array($extra_roles_arr) && !empty($extra_roles_arr['roles_arr'])) {
                $roles_arr = self::array_merge_unique_values($extra_roles_arr['roles_arr'], $roles_arr);
            }
        }

        if (!empty($params['{account_roles}']) && is_array($params['{account_roles}'])) {
            if (!empty($roles_arr)) {
                $roles_arr = self::array_merge_unique_values($params['{account_roles}'], $roles_arr);
            } else {
                $roles_arr = $params['{account_roles}'];
            }
        }

        if (!empty($roles_arr)) {
            PHS_Roles::link_roles_to_user($insert_arr, $roles_arr);
        }

        /** @var PHS_Model_Accounts_tenants $accounts_tenants_model */
        if (!empty($params['{account_tenants}'])
            && ($accounts_tenants_model = PHS_Model_Accounts_tenants::get_instance())) {
            $accounts_tenants_model->link_tenants_to_account($insert_arr, $params['{account_tenants}']);
        }

        $registration_email_params = [];
        $registration_email_params['accounts_plugin_settings'] = $params['{accounts_settings}'];
        $registration_email_params['send_confirmation_email'] = $params['{send_confirmation_email}'];

        if (!($email_result = $this->send_after_registration_email($insert_arr, $registration_email_params))
         || !is_array($email_result)
         || !empty($email_result['has_error'])) {
            if (empty($email_result) || !is_array($email_result)) {
                $email_result = [];
            }

            // If only confirmation email fails don't delete the account...
            if (!empty($insert_arr['{users_details}'])
             && (empty($email_result) || !empty($email_result['activation_email_failed']))) {
                $this->set_error_if_not_set(self::ERR_EMAIL, $this->_pt('Error sending registration email. Please try again.'));

                $accounts_details_model->hard_delete($insert_arr['{users_details}']);

                return null;
            }
        }

        if ($accounts_plugin->should_log_account_creation()) {
            PHS_Logger::notice('CREATION Account #'.$insert_arr['id'].': '.$insert_arr['nick'].' ('.$this->get_account_level_as_title($insert_arr).') was created.',
                $accounts_plugin::LOG_SECURITY);
        }

        if (!empty($roles_arr)
            && $accounts_plugin->should_log_roles_changes()) {
            PHS_Logger::notice('ROLES Account #'.$insert_arr['id'].': '.$insert_arr['nick'].' ('.$this->get_account_level_as_title($insert_arr).') was assigned roles: '
                               .implode(', ', $roles_arr).'.',
                $accounts_plugin::LOG_SECURITY);
        }

        $hook_args = PHS_Hooks::default_user_account_hook_args();
        $hook_args['account_data'] = $insert_arr;
        $hook_args['account_details_data'] = $insert_arr['{users_details}'];

        if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_USERS_REGISTRATION, $hook_args))
         && is_array($hook_args) && !empty($hook_args['account_data'])) {
            $insert_arr = $hook_args['account_data'];
        }

        return $insert_arr;
    }

    protected function get_edit_prepare_params_users($existing_data, $params)
    {
        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if (empty($params) || !is_array($params)
         || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            $this->set_error_if_not_set(self::ERR_EDIT, $this->_pt('Error loading required resources.'));

            return false;
        }

        if (!empty($params['fields']['status'])) {
            $params['fields']['status'] = (int)$params['fields']['status'];
        }

        $accounts_settings = $this->get_plugin_settings() ?: [];

        $params['{password_was_changed}'] = false;
        if (!empty($params['fields']['pass'])) {
            if ($this->check_pass($existing_data, $params['fields']['pass'])) {
                $this->set_error(self::ERR_EDIT, $this->_pt('You used this password in the past. Please provide another one.'));

                return false;
            }

            if (!$this->validate_password_rules($params['fields']['pass'], $accounts_settings)) {
                $this->change_error_code(self::ERR_EDIT);

                return false;
            }

            if (($history_details = $this->is_password_in_history($existing_data, $params['fields']['pass']))) {
                if (!empty($history_details['history_count'])
                    && !empty($history_details['oldest_password_date_timestamp'])) {
                    $this->set_error(self::ERR_EDIT, $this->_pt('You used this password in last %s, one of last %s passwords. Please provide another one.',
                        PHS_Utils::parse_period(abs(time() - $history_details['oldest_password_date_timestamp']),
                            ['only_big_part' => true]),
                        $history_details['history_count']));
                } else {
                    $this->set_error(self::ERR_EDIT, $this->_pt('You used this password in the past. Please provide another one.'));
                }

                return false;
            }

            if (!($old_pass_salt_arr = $this->_get_account_salt_data($existing_data))) {
                // reset any error
                $this->reset_error();
                $old_pass_salt_arr = false;
            }

            $encoded_clear = null;
            if ($accounts_plugin->is_password_decryption_enabled()
                && false === ($encoded_clear = PHS_Crypt::quick_encode($params['fields']['pass']))) {
                $this->set_error(self::ERR_INSERT, $this->_pt('Error encrypting account password. Please retry.'));

                return false;
            }

            if (!($pass_salt = self::generate_password((!empty($accounts_settings['pass_salt_length']) ? $accounts_settings['pass_salt_length'] + 3 : 8)))
                || !($encoded_pass = self::encode_pass($params['fields']['pass'], $pass_salt))) {
                $this->set_error(self::ERR_INSERT, $this->_pt('Error obtaining account password. Please retry.'));

                return false;
            }

            $params['{old_pass_salt}'] = $old_pass_salt_arr;
            $params['{pass_salt}'] = $pass_salt;
            $params['fields']['pass_clear'] = $encoded_clear;
            $params['fields']['pass'] = $encoded_pass;
            $params['fields']['last_pass_change'] = date(self::DATETIME_DB);

            $params['{password_was_changed}'] = true;
        }

        if (empty($params['{password_was_changed}'])) {
            // make sure passwords fields are not set if password will not be changed
            if (isset($params['{pass_salt}'])) {
                unset($params['{pass_salt}']);
            }
            if (isset($params['{old_pass_salt}'])) {
                unset($params['{old_pass_salt}']);
            }
            // pass_clear can also be null
            if (array_key_exists('pass_clear', $params['fields'])) {
                unset($params['fields']['pass_clear']);
            }
            if (isset($params['fields']['pass'])) {
                unset($params['fields']['pass']);
            }
        }

        if (isset($params['fields']['email'])
         && (string)$params['fields']['email'] !== (string)$existing_data['email']) {
            // If we delete the account, just skip checks...
            if (empty($params['fields']['status'])
             || $params['fields']['status'] !== self::STATUS_DELETED) {
                if (empty($params['fields']['email'])
                 || !PHS_Params::check_type($params['fields']['email'], PHS_Params::T_EMAIL)) {
                    $this->set_error(self::ERR_EDIT, $this->_pt('Invalid email address.'));

                    return false;
                }

                if (!empty($accounts_settings['email_unique'])) {
                    $check_arr = [];
                    $check_arr['email'] = $params['fields']['email'];
                    $check_arr['id'] = ['check' => '!=', 'value' => $existing_data['id']];

                    if ($this->get_details_fields($check_arr)) {
                        $this->set_error(self::ERR_EDIT, $this->_pt('Email address exists in database. Please pick another one.'));

                        return false;
                    }
                }
            }

            if ((empty($params['fields']['nick']) && !empty($accounts_settings['replace_nick_with_email']))
             || !empty($accounts_settings['no_nickname_only_email'])) {
                $params['fields']['nick'] = $params['fields']['email'];
            }

            $params['fields']['email_verified'] = 0;
        }

        if (isset($params['fields']['nick'])
         && (string)$params['fields']['nick'] !== (string)$existing_data['nick']) {
            // If we delete the account, just skip checks...
            if (empty($params['fields']['status'])
             || $params['fields']['status'] !== self::STATUS_DELETED) {
                $check_arr = [];
                $check_arr['nick'] = $params['fields']['nick'];
                $check_arr['id'] = ['check' => '!=', 'value' => $existing_data['id']];

                if ($this->get_details_fields($check_arr)) {
                    $this->set_error(self::ERR_EDIT, $this->_pt('Nickname already exists in database. Please pick another one.'));

                    return false;
                }
            }
        }

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a valid status.'));

                return false;
            }

            $cdate = date(self::DATETIME_DB);
            $params['fields']['status_date'] = $cdate;

            if ($params['fields']['status'] === self::STATUS_DELETED) {
                $params['fields']['deleted'] = $cdate;
            }
        }

        if (isset($params['fields']['level'])
            && !$this->valid_level($params['fields']['level'])) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a valid account level.'));

            return false;
        }

        $params['{accounts_settings}'] = $accounts_settings;

        if (empty($params['{users_details}']) || !is_array($params['{users_details}'])) {
            $params['{users_details}'] = false;
        }

        if (!isset($params['{account_tenants}']) || !is_array($params['{account_tenants}'])
            || !PHS::is_multi_tenant()) {
            $params['{account_tenants}'] = null;
        }

        $params['{activate_after_registration}'] = !empty($params['{activate_after_registration}']);

        return $params;
    }

    protected function edit_after_users($existing_data, $edit_arr, $params)
    {
        /** @var PHS_Plugin_Accounts $plugin_obj */
        if (!($plugin_obj = PHS_Plugin_Accounts::get_instance())) {
            $plugin_obj = null;
        }

        if (!empty($params['{users_details}']) && is_array($params['{users_details}'])
            && !($existing_data = $this->update_user_details($existing_data, $params['{users_details}']))) {
            $this->set_error_if_not_set(self::ERR_EDIT, $this->_pt('Error saving account details in database. Please try again.'));

            return false;
        }

        if (!empty($params['{account_roles}']) && is_array($params['{account_roles}'])) {
            /** @var PHS_Model_Roles $roles_model */
            if (!($roles_model = PHS_Model_Roles::get_instance())) {
                $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

                return false;
            }

            if ($roles_model->account_roles_changed($existing_data, $params['{account_roles}'])) {
                if (!$roles_model->link_roles_to_user($existing_data, $params['{account_roles}'], ['append_roles' => false])) {
                    $this->copy_or_set_error($roles_model,
                        self::ERR_EDIT, $this->_pt('Error saving account roles in database. Please try again.'));

                    return false;
                }

                if ($plugin_obj->should_log_roles_changes()) {
                    PHS_Logger::notice('ROLES Account #'.$existing_data['id'].': '.$existing_data['nick'].' ('.$this->get_account_level_as_title($existing_data).') was assigned roles: '
                                       .implode(', ', $params['{account_roles}']).'.',
                        $plugin_obj::LOG_SECURITY);
                }
            }
        }

        if (isset($params['{account_tenants}']) && is_array($params['{account_tenants}'])
            && PHS::is_multi_tenant()) {
            /** @var PHS_Model_Accounts_tenants $account_tenants_model */
            if (!($account_tenants_model = PHS_Model_Accounts_tenants::get_instance())
                || !$account_tenants_model->link_tenants_to_account($existing_data, $params['{account_tenants}'], ['append_tenants' => false])) {
                $this->copy_or_set_error($account_tenants_model,
                    self::ERR_EDIT, $this->_pt('Error saving account tenants in database. Please try again.'));

                return false;
            }
        }

        if (!empty($params['{password_was_changed}'])) {
            if (!empty($params['{pass_salt}'])
             && ($salt_flow_params = $this->fetch_default_flow_params(['table_name' => 'users_pass_salts']))) {
                if (!empty($params['{old_pass_salt}'])) {
                    $old_salt_arr = $params['{old_pass_salt}'];
                } else {
                    $check_arr = [];
                    $check_arr['uid'] = $existing_data['id'];

                    if (!($old_salt_arr = $this->get_details_fields($check_arr, $salt_flow_params))) {
                        $old_salt_arr = false;
                    }
                }

                $fields_arr = [];
                $fields_arr['pass_salt'] = $params['{pass_salt}'];

                $salt_data_arr = $salt_flow_params;
                $salt_data_arr['fields'] = $fields_arr;

                if (!empty($old_salt_arr)) {
                    if (!($salt_arr = $this->edit($old_salt_arr, $salt_data_arr))) {
                        $this->set_error(self::ERR_EDIT, $this->_pt('Error saving account password. Please try again.'));

                        return false;
                    }
                } else {
                    $salt_data_arr['fields']['uid'] = $existing_data['id'];

                    if (!($salt_arr = $this->insert($salt_data_arr))) {
                        $this->set_error(self::ERR_EDIT, $this->_pt('Error inserting account password. Please try again.'));

                        return false;
                    }
                }

                $existing_data['{pass_salt}'] = $salt_arr;
                $existing_data['{old_pass_salt}'] = $old_salt_arr;
            }

            if (!$this->must_setup_password($existing_data)) {
                $history_params = [];
                $history_params['{accounts_settings}'] = $params['{accounts_settings}'];

                // save old password to history
                if (!$this->_add_account_password_to_history($existing_data, $history_params)) {
                    PHS_Logger::error('Couldn\'t save user #'.$existing_data['id'].' password to history: '
                                      .$this->get_error_message(), PHS_Logger::TYPE_ERROR);

                    $this->reset_error();
                }

                if (!empty($params['{accounts_settings}']) && is_array($params['{accounts_settings}'])
                    && !empty($params['{accounts_settings}']['announce_pass_change'])) {
                    // send password changed email...
                    PHS_Bg_jobs::run(['p' => 'accounts', 'a' => 'pass_changed_email_bg', 'c' => 'index_bg'], ['uid' => $existing_data['id']]);
                }
            }

            if ($plugin_obj->should_log_password_changes()) {
                PHS_Logger::notice('PASSWORD Account #'.$existing_data['id'].': '.$existing_data['nick'].' ('.$this->get_account_level_as_title($existing_data).') password changed.',
                    $plugin_obj::LOG_SECURITY);
            }
        }

        if (!empty($params['{activate_after_registration}'])
         && $this->needs_confirmation_email($existing_data)) {
            $this->send_confirmation_email($existing_data);
        }

        // As we announce account action, we should have updated values...
        $structure_hook_args = PHS_Hooks::default_account_structure_hook_args();
        $structure_hook_args['account_data'] = $existing_data['id'];

        if ($plugin_obj !== null
            && ($account_structure = $plugin_obj->get_account_structure($structure_hook_args))
            && !empty($account_structure['account_structure'])) {
            $existing_data = $account_structure['account_structure'];
        }

        $hook_args = PHS_Hooks::default_account_action_hook_args();
        $hook_args['account_data'] = $existing_data;
        $hook_args['action_alias'] = 'edit';
        $hook_args['action_params'] = $params;
        $hook_args['route'] = PHS::get_route_details();

        $this->trigger_account_action_in_background($hook_args);

        return $existing_data;
    }

    /**
     * @inheritdoc
     */
    protected function get_count_list_common_params($params = false)
    {
        $model_table = $this->get_flow_table_name($params);

        if (!empty($params['flags']) && is_array($params['flags'])) {
            if (empty($params['db_fields'])) {
                $params['db_fields'] = '';
            }

            foreach ($params['flags'] as $flag) {
                switch ($flag) {
                    case 'include_account_details':

                        $old_error_arr = PHS::st_stack_error();
                        /** @var PHS_Model_Accounts_details $account_details_model */
                        if ($params['table_name'] !== 'users'
                         || !($account_details_model = PHS::load_model('accounts_details', 'accounts'))
                         || !($user_details_table = $account_details_model->get_flow_table_name())) {
                            PHS::st_restore_errors($old_error_arr);
                            continue 2;
                        }

                        // user_details is a dynamic table, so we want to obtain all fields from other plugins also
                        if (!($empty_fields = $account_details_model->get_empty_data())) {
                            // "hardcoded" fields in case we have an error obtaining all fields
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
                            $params['db_fields'] .= ', `'.$user_details_table.'`.`'.$field_name.'` AS users_details_'.$field_name;
                        }

                        $params['join_sql'] .= ' LEFT JOIN `'.$user_details_table.'` ON `'.$user_details_table.'`.id = `'.$model_table.'`.details_id ';
                        break;
                }
            }
        }

        if (empty($params['one_of_role_unit']) || !is_array($params['one_of_role_unit'])) {
            $params['one_of_role_unit'] = false;
        }
        if (empty($params['one_of_role']) || !is_array($params['one_of_role'])) {
            $params['one_of_role'] = false;
        }

        if (empty($params['all_role_units']) || !is_array($params['all_role_units'])) {
            $params['all_role_units'] = false;
        }
        if (empty($params['all_roles']) || !is_array($params['all_roles'])) {
            $params['all_roles'] = false;
        }

        if (!empty($params['one_of_role_unit'])
         || !empty($params['all_role_units'])
         || !empty($params['one_of_role'])
         || !empty($params['all_roles'])) {
            $old_error_arr = PHS::st_stack_error();
            /** @var PHS_Model_Roles $roles_model */
            if (!($roles_model = PHS::load_model('roles'))
             || !($roles_users_flow = $roles_model->fetch_default_flow_params(['table_name' => 'roles_users']))
             || !($roles_users_table = $roles_model->get_flow_table_name($roles_users_flow))
            ) {
                PHS::st_restore_errors($old_error_arr);
            } else {
                $roles_users_joined = false;
                if (!empty($params['one_of_role_unit']) && is_array($params['one_of_role_unit'])) {
                    if (($one_of_role = $roles_model->get_roles_ids_for_roles_units_list($params['one_of_role_unit']))) {
                        if (empty($params['one_of_role']) || !is_array($params['one_of_role'])) {
                            $params['one_of_role'] = $one_of_role;
                        } else {
                            $params['one_of_role'] = array_merge($params['one_of_role'], $one_of_role);
                        }
                    }
                }

                // if( !empty( $params['all_role_units'] ) && is_array( $params['all_role_units'] ) )
                // {
                //     if( ($all_roles_groups = $roles_model->get_roles_ids_for_roles_units_list_grouped( $params['all_role_units'] ))
                //     && is_array( $all_roles_groups ) )
                //     {
                //         $extra_sql = '';
                //         foreach( $all_roles_groups as $role_unit_id => $roles_arr )
                //         {
                //             if( empty( $roles_arr ) || !is_array( $roles_arr ) )
                //                 continue;
                //
                //             $extra_sql .= ($extra_sql!=''?' AND ':'').' `'.$roles_users_table.'`.role_id IN ('.@implode( ',', $roles_arr ).')';
                //         }
                //
                //         if( $extra_sql != '' )
                //         {
                //             if( empty( $roles_users_joined ) )
                //                 $params['join_sql'] .= ' LEFT JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$model_table.'`.id ';
                //
                //             $roles_users_joined = true;
                //
                //             $params['fields'][] = array(
                //                 'raw' => '('.$extra_sql.')',
                //             );
                //         }
                //
                //         // if( empty( $params['all_roles'] ) || !is_array( $params['all_roles'] ) )
                //         //     $params['all_roles'] = $all_roles;
                //         //
                //         // else
                //         //     $params['all_roles'] = array_merge( $params['all_roles'], $all_roles );
                //     }
                // }

                if (!empty($params['one_of_role'])
                 && ($one_of_role_ids = $roles_model->roles_list_to_ids($params['one_of_role']))) {
                    if (empty($roles_users_joined)) {
                        $params['join_sql'] .= ' LEFT JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$model_table.'`.id ';
                    }

                    $roles_users_joined = true;

                    $params['fields'][] = [
                        'raw' => 'EXISTS (SELECT 1 FROM `'.$roles_users_table.'` '
                                    .' WHERE `'.$roles_users_table.'`.user_id = `'.$model_table.'`.id AND `'.$roles_users_table.'`.role_id IN ('.@implode(',', $one_of_role_ids).'))',
                    ];
                }

                // if( !empty( $params['all_roles'] )
                //  && ($all_roles_ids = $roles_model->roles_list_to_ids( $params['all_roles'] ))
                //  && is_array( $all_roles_ids ))
                // {
                //     if( empty( $roles_users_joined ) )
                //         $params['join_sql'] .= ' LEFT JOIN `'.$roles_users_table.'` ON `'.$roles_users_table.'`.user_id = `'.$model_table.'`.id ';
                //
                //     $roles_users_joined = true;
                //
                //     $params['fields'][] = array(
                //         'raw' => '(`'.$roles_users_table.'`.user_id = `'.$model_table.'`.id AND `'.$roles_users_table.'`.role_id IN ('.@implode( ',', $all_roles_ids ).'))',
                //     );
                // }
            }
        }

        return $params;
    }

    private function _check_lockout_policy(int | array | PHS_Record_data $account_arr) : null | array | PHS_Record_data
    {
        /** @var PHS_Plugin_Accounts $accounts_plugin */
        if ($this->is_locked($account_arr)
            || !($flow_arr = $this->fetch_default_flow_params(['table_name' => 'users']))
            || !($users_table = $this->get_flow_table_name($flow_arr))
            || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($settings_arr = $accounts_plugin->get_plugin_settings())
            || !$accounts_plugin->lockout_is_enabled()) {
            return null;
        }

        $lockout_failed_count = $settings_arr['lockout_failed_count'] ?? 5;
        $account_failed_count = $account_arr['failed_logins'] ?? 0;

        $extra_sql = 'failed_logins = failed_logins + 1';
        if (!$this->is_locked($account_arr)
            && !empty($account_arr['locked_date'])) {
            // Reset account locking even if we have a password failure as locking time passed
            $account_failed_count = 0;
            $extra_sql = 'failed_logins = 1, locked_date = NULL';
        } elseif ($account_failed_count + 1 >= $lockout_failed_count) {
            $lockout_period_minutes = $settings_arr['lockout_period_minutes'] ?? 15;

            $locked_date = date(self::DATETIME_DB, (time() + $lockout_period_minutes * 60));

            $extra_sql = 'failed_logins = failed_logins + 1, locked_date = \''.$locked_date.'\'';
            $account_arr['locked_date'] = $locked_date;
        }

        // Low level query, so we don't trigger other actions...
        db_query('UPDATE `'.$users_table.'` SET '.$extra_sql.' WHERE id = \''.$account_arr['id'].'\'',
            $flow_arr['db_connection']);

        $account_arr['failed_logins'] = $account_failed_count + 1;

        return $account_arr;
    }

    private function validate_password_rules(string $pass, ?array $accounts_settings = null) : bool
    {
        $this->reset_error();

        if ($accounts_settings === null) {
            $accounts_settings = $this->get_plugin_settings();
        }

        if (!empty($accounts_settings['min_password_length'])
         && strlen($pass) < $accounts_settings['min_password_length']) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Password should be at least %s characters.',
                $accounts_settings['min_password_length']));

            return false;
        }

        if (!empty($accounts_settings['password_regexp'])
         && !@preg_match($accounts_settings['password_regexp'], $pass)) {
            if (!empty($accounts_settings['password_regexp_explanation'])) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt($accounts_settings['password_regexp_explanation']));
            } elseif (($regexp_parts = explode('/', $accounts_settings['password_regexp']))
                 && !empty($regexp_parts[1])) {
                if (empty($regexp_parts[2])) {
                    $regexp_parts[2] = '';
                }

                $this->set_error(self::ERR_PARAMETERS,
                    $this->_pt('Password doesn\'t match regular expression %s.',
                        '<a href="https://regex101.com/?regex='.$regexp_parts[1].'&options='.$regexp_parts[2].'" title="'.$this->_pt('Click for details').'" target="_blank">'.$accounts_settings['password_regexp'].'</a>'));
            } else {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Password doesn\'t match regular expression %s.', $accounts_settings['password_regexp']));
            }

            return false;
        }

        return true;
    }

    private function _get_account_salt_data(int | array | PHS_Record_data $account_data) : ?array
    {
        $this->reset_error();

        if (empty($account_data)
         || !($account_arr = $this->data_to_array($account_data))
         || !($account_salt_arr = $this->get_details_fields(['uid' => $account_arr['id']], ['table_name' => 'users_pass_salts']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid account.'));

            return null;
        }

        return $account_salt_arr;
    }

    private function _add_account_password_to_history(int | array | PHS_Record_data $account_data, array $params = []) : bool | array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'users_pass_history']))
         || !($uph_table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Cannot obtain flow params.'));

            return false;
        }

        if (empty($account_data)
            || !($account_arr = $this->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a valid account to save password history.'));

            return false;
        }

        $old_pass_salt = '';
        // If salt was changed we will have salt record in ths key
        if (!empty($account_arr['{old_pass_salt}']) && is_array($account_arr['{old_pass_salt}'])
         && !empty($account_arr['{old_pass_salt}']['pass_salt'])) {
            $old_pass_salt = $account_arr['{old_pass_salt}']['pass_salt'];
        } else {
            // if nothing was provided, we assume old salt is still in database...
            if (!($account_salt_arr = $this->get_details_fields(['uid' => $account_arr['id']], ['table_name' => 'users_pass_salts']))) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a valid account to save password history.'));

                return false;
            }

            $old_pass_salt = $account_salt_arr['pass_salt'];
        }

        if ((empty($params['{accounts_settings}'])
             && !($params['{accounts_settings}'] = $this->get_plugin_settings()))
            || !is_array($params['{accounts_settings}'])) {
            $params['{accounts_settings}'] = [];
        }

        $accounts_settings = $params['{accounts_settings}'];

        if (empty($accounts_settings['passwords_history_count'])
            || !($history_count = (int)$accounts_settings['passwords_history_count'])) {
            // delete extra records
            db_query('DELETE FROM `'.$uph_table_name.'`'
                      .' WHERE uid = \''.$account_arr['id'].'\'', $flow_params['db_connection']);

            return true;
        }

        if (($qid = db_query('SELECT COUNT(*) AS total_history_records '
                              .' FROM `'.$uph_table_name.'`'
                              .' WHERE uid = \''.$account_arr['id'].'\'', $flow_params['db_connection']))
         && ($record_arr = @db_fetch_assoc($qid, $flow_params['db_connection']))
         && ($records_to_delete = $record_arr['total_history_records'] - $history_count + 1) > 0) {
            // delete extra records
            db_query('DELETE FROM `'.$uph_table_name.'`'
                      .' WHERE uid = \''.$account_arr['id'].'\' ORDER BY cdate ASC LIMIT '.$records_to_delete, $flow_params['db_connection']);
        }

        $changed_by_uid = 0;
        if (($changed_account_arr = PHS::user_logged_in())) {
            $changed_by_uid = $changed_account_arr['id'];
        }

        $insert_fields_arr = [];
        $insert_fields_arr['uid'] = $account_arr['id'];
        $insert_fields_arr['changed_by_uid'] = $changed_by_uid;
        $insert_fields_arr['pass_salt'] = $old_pass_salt;
        $insert_fields_arr['pass'] = $account_arr['pass'];
        $insert_fields_arr['pass_clear'] = $account_arr['pass_clear'];
        $insert_fields_arr['cdate'] = date(self::DATETIME_DB);

        $insert_arr = $flow_params;
        $insert_arr['fields'] = $insert_fields_arr;

        if (!($history_arr = $this->insert($insert_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error saving password history data.'));

            return false;
        }

        return $history_arr;
    }

    //
    // region Version Updates
    //
    private function _update_to_104_or_higher() : bool
    {
        $this->reset_error();

        // Make sure we don't throw errors here...
        $st_throwing_errors = PHS::st_throw_errors();
        $throwing_errors = $this->throw_errors();
        $this->throw_errors(false);
        PHS::st_throw_errors(false);

        // Changed passwords encoding function from md5 to sha256
        if (@function_exists('hash_algos')
         && !in_array(self::PASSWORDS_ALGO, (array)@hash_algos(), true)) {
            $this->set_error(self::ERR_SERVER, $this->_pt('%s hash algorithm not available on this server.', self::PASSWORDS_ALGO));
            $this->throw_errors($throwing_errors);
            PHS::st_throw_errors($st_throwing_errors);

            return false;
        }

        // we work with low level queries, so we don't trigger functionalities from model...
        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'users']))
         || !($user_table_name = $this->get_flow_table_name($flow_params))
         || !($qid = db_query('SELECT * FROM `'.$user_table_name.'`', $flow_params['db_connection']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error querying users from database.'));
            $this->throw_errors($throwing_errors);
            PHS::st_throw_errors($st_throwing_errors);

            return false;
        }

        if (!($users_count = db_num_rows($qid, $flow_params['db_connection']))) {
            return true;
        }

        PHS_Logger::notice('Converting passwords from md5 to sha256 for '.$users_count.' accounts...', PHS_Logger::TYPE_MAINTENANCE);

        while (($users_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            if (empty($users_arr['pass_clear'])
                || !($pass_clear = PHS_Crypt::quick_decode($users_arr['pass_clear']))) {
                PHS_Logger::error('Couldn\'t convert password for user #'.$users_arr['id'].'. Please change password manually or using forgot password.', PHS_Logger::TYPE_MAINTENANCE);
                continue;
            }

            // Already converted...
            if (empty($users_arr['pass_salt'])
                || $this->check_pass($users_arr, $pass_clear)) {
                continue;
            }

            if (!($sql = db_quick_edit($user_table_name, ['pass' => self::encode_pass($pass_clear, $users_arr['pass_salt'])], $flow_params['db_connection']))
                || !db_query($sql.' WHERE id = \''.$users_arr['id'].'\'', $flow_params['db_connection'])) {
                PHS_Logger::error('Couldn\'t save converted password for user #'.$users_arr['id'].'. Please change password manually or using forgot password.', PHS_Logger::TYPE_MAINTENANCE);
                continue;
            }
        }

        PHS_Logger::notice('FINISHED Converting passwords.', PHS_Logger::TYPE_MAINTENANCE);

        $this->throw_errors($throwing_errors);
        PHS::st_throw_errors($st_throwing_errors);

        return true;
    }

    private function _update_to_110_or_higher() : bool
    {
        $this->reset_error();

        // Make sure we don't throw errors here...
        $st_throwing_errors = PHS::st_throw_errors();
        $throwing_errors = $this->throw_errors();
        $this->throw_errors(false);
        PHS::st_throw_errors(false);

        // we work with low level queries, so we don't trigger functionalities from model...
        if (!($salt_flow_params = $this->fetch_default_flow_params(['table_name' => 'users_pass_salts']))
         || !($salt_table_name = $this->get_flow_table_name($salt_flow_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining password salts flow.'));
            $this->throw_errors($throwing_errors);
            PHS::st_throw_errors($st_throwing_errors);

            return false;
        }

        // we work with low level queries, so we don't trigger functionalities from model...
        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'users']))
         || !($user_table_name = $this->get_flow_table_name($flow_params))
         || !($qid = db_query('SELECT * FROM `'.$user_table_name.'`', $flow_params['db_connection']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error querying users from database.'));
            $this->throw_errors($throwing_errors);
            PHS::st_throw_errors($st_throwing_errors);

            return false;
        }

        if (!($users_count = db_num_rows($qid, $flow_params['db_connection']))) {
            return true;
        }

        PHS_Logger::notice('Converting passwords salts for '.$users_count.' accounts...', PHS_Logger::TYPE_MAINTENANCE);

        while (($users_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            // Already converted...
            if (empty($users_arr['pass_salt'])) {
                continue;
            }

            $check_arr = [];
            $check_arr['uid'] = $users_arr['id'];

            $fields_arr = [];
            $fields_arr['pass_salt'] = $users_arr['pass_salt'];

            if (($existing_arr = $this->get_details_fields($check_arr, $salt_flow_params))) {
                if (!($sql = db_quick_edit($salt_table_name, $fields_arr, $salt_flow_params['db_connection']))
                 || !db_query($sql.' WHERE id = \''.$existing_arr['id'].'\'', $salt_flow_params['db_connection'])) {
                    PHS_Logger::error('Couldn\'t save converted password salt for user #'.$users_arr['id'].'. Please change password manually or using forgot password.', PHS_Logger::TYPE_MAINTENANCE);
                    continue;
                }
            } else {
                $fields_arr['uid'] = $users_arr['id'];

                if (!($sql = db_quick_insert($salt_table_name, $fields_arr, $salt_flow_params['db_connection']))
                 || !($item_id = db_query_insert($sql, $salt_flow_params['db_connection']))) {
                    PHS_Logger::error('Couldn\'t insert converted password salt for user #'.$users_arr['id'].'. Please change password manually or using forgot password.', PHS_Logger::TYPE_MAINTENANCE);
                    continue;
                }
            }
        }

        PHS_Logger::notice('FINISHED Converting password salts.', PHS_Logger::TYPE_MAINTENANCE);

        $this->throw_errors($throwing_errors);
        PHS::st_throw_errors($st_throwing_errors);

        return true;
    }

    private function _not_used_only_for_translation() : void
    {
        $this->_pt('Inactive');
        $this->_pt('Active');
        $this->_pt('Suspended');
        $this->_pt('Deleted');

        $this->_pt('Member');
        $this->_pt('Operator');
        $this->_pt('Admin');
        $this->_pt('Super admin');
        $this->_pt('Developer');
    }
    //
    // END Custom updates
    //

    //
    //  Level checks
    //
    public static function is_developer(int $lvl) : bool
    {
        return $lvl === self::LVL_DEVELOPER;
    }

    public static function is_sadmin(int $lvl) : bool
    {
        return $lvl === self::LVL_SUPERADMIN || $lvl === self::LVL_DEVELOPER;
    }

    public static function is_admin(int $lvl, bool $strict = false) : bool
    {
        return $lvl === self::LVL_ADMIN || (!$strict && ($lvl === self::LVL_SUPERADMIN || $lvl === self::LVL_DEVELOPER));
    }

    public static function is_operator(int $lvl, bool $strict = false) : bool
    {
        return $lvl === self::LVL_OPERATOR || (!$strict && self::is_admin($lvl));
    }

    public static function is_member(int $lvl, bool $strict = false) : bool
    {
        return $lvl === self::LVL_MEMBER || (!$strict && self::is_admin($lvl));
    }

    public static function generate_password(int $len = 10, array $params = []) : string
    {
        /** @var PHS_Event_Accounts_generate_password $event_obj */
        if ( ($event_obj = PHS_Event_Accounts_generate_password::trigger(['length' => $len]))
            && ($generated_password = $event_obj->get_output('generated_password'))) {
            return (string)$generated_password;
        }

        if (empty($params['percents']) || !is_array($params['percents'])) {
            $params['percents'] = ['spacial_chars' => 10, 'digits_chars' => 20, 'normal_chars' => 70, ];
        }

        if (!isset($params['percents']['spacial_chars'])) {
            $params['percents']['spacial_chars'] = 10;
        }
        if (!isset($params['percents']['digits_chars'])) {
            $params['percents']['digits_chars'] = 20;
        }
        if (!isset($params['percents']['normal_chars'])) {
            $params['percents']['normal_chars'] = 70;
        }

        $spacial_chars_perc = (int)$params['percents']['spacial_chars'];
        $digits_chars_perc = (int)$params['percents']['digits_chars'];
        $normal_chars_perc = (int)$params['percents']['normal_chars'];

        if ($spacial_chars_perc + $digits_chars_perc + $normal_chars_perc > 100) {
            $spacial_chars_perc = 10;
            $digits_chars_perc = 20;
            $normal_chars_perc = 70;
        }

        $special_chars_dict = '!@#%^&*()_-+}{:;?/.,;';
        $digits_dict = '123456789';
        $letters_dict = 'abcdbefghklmnqprstuvwxyz';
        $special_chars_dict_len = strlen($special_chars_dict);
        $digits_dict_len = strlen($digits_dict);
        $letters_dict_len = strlen($letters_dict);

        $uppercase_chars = 0;
        $special_chars = 0;
        $digit_chars = 0;

        $ret = '';
        for ($ret_len = 0; $ret_len < $len; $ret_len++) {
            $uppercase_char = false;
            // 10% spacial char, 20% digit, 70% letter
            $dict_index = mt_rand(0, 100);
            if ($dict_index <= $spacial_chars_perc) {
                $current_dict = $special_chars_dict;
                $dict_len = $special_chars_dict_len;
                $special_chars++;
            } elseif ($dict_index <= $spacial_chars_perc + $digits_chars_perc) {
                $current_dict = $digits_dict;
                $dict_len = $digits_dict_len;
                $digit_chars++;
            } else {
                $current_dict = $letters_dict;
                $dict_len = $letters_dict_len;
                if (mt_rand(0, 100) > 50) {
                    $uppercase_char = true;
                    $uppercase_chars++;
                }
            }

            $ch = substr($current_dict, mt_rand(0, $dict_len - 1), 1);
            if ($uppercase_char) {
                $ch = strtoupper($ch);
            }

            $ret .= $ch;
        }

        // Add a special char if none was added already
        if (!$special_chars) {
            $ch = substr($special_chars_dict, mt_rand(0, $special_chars_dict_len - 1), 1);
            // 50% in front or in back of the result
            if (mt_rand(0, 100) > 50) {
                $ret .= $ch;
            } else {
                $ret = $ch.$ret;
            }
        }

        // Add a digit char if none was added already
        while ($digit_chars < 2) {
            $ch = substr($digits_dict, mt_rand(0, $digits_dict_len - 1), 1);
            // 50% in front or in back of the result
            if (mt_rand(0, 100) > 50) {
                $ret .= $ch;
            } else {
                $ret = $ch.$ret;
            }

            $digit_chars++;
        }

        return $ret;
    }

    public static function encode_pass(string $pass, string $salt) : string
    {
        /** @var PHS_Event_Accounts_password_encryption $event_obj */
        if ( ($event_obj = PHS_Event_Accounts_password_encryption::trigger(['pass' => $pass, 'salt' => $salt]))
            && ($encyped_password = $event_obj->get_output('encrypted_password'))) {
            return (string)$encyped_password;
        }

        return @hash(self::PASSWORDS_ALGO, $salt.'_'.$pass, false);
    }
}
