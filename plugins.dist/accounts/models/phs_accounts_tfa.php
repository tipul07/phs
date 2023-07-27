<?php
namespace phs\plugins\accounts\models;

use phs\PHS;
use phs\PHS_Crypt;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\phs_libs\PHS_Plugin_Phs_libs;

class PHS_Model_Accounts_tfa extends PHS_Model
{
    public const RECOVERY_DOWNLOAD_PARAM = '_t';

    private const PADDING_CHAR = '=';

    private const CODE_LENGTH = 6;

    private const TFA_PERIOD = 30;

    private const TFA_ALGO = 'SHA1';

    /** @var null|\phs\plugins\accounts\PHS_Plugin_Accounts */
    private ?PHS_Plugin_Accounts $_accounts_plugin = null;

    /** @var null|\phs\plugins\accounts\models\PHS_Model_Accounts */
    private ?PHS_Model_Accounts $_accounts_model = null;

    private static array $SECRET_CHARS_ARR = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
        'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
        'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
        'Y', 'Z', '2', '3', '4', '5', '6', '7',
    ];

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
        return ['users_tfa'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'users_tfa';
    }

    public function get_code_length() : int
    {
        return self::CODE_LENGTH;
    }

    /**
     * @param int|array $tfa_data
     *
     * @return bool
     */
    public function is_setup_completed($tfa_data) : bool
    {
        return ($tfa_arr = $this->data_to_array($tfa_data))
               && !empty($tfa_arr['setup']);
    }

    /**
     * @param int|array $tfa_data
     *
     * @return bool
     */
    public function is_recovery_code_downloaded($tfa_data) : bool
    {
        return ($tfa_arr = $this->data_to_array($tfa_data))
               && !empty($tfa_arr['recovery_downloaded']);
    }

    /**
     * @return bool
     */
    public function is_session_tfa_valid() : bool
    {
        return ($online_arr = PHS::current_user_session())
               && !empty($online_arr['tfa_expiration'])
               && parse_db_date($online_arr['tfa_expiration']) > time();
    }

    public function validate_tfa_for_session() : ?bool
    {
        $this->reset_error();

        if (!$this->_load_dependencies()) {
            return null;
        }

        if ($this->is_session_tfa_valid()) {
            return true;
        }

        if (!($online_arr = PHS::current_user_session())) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Session not created yet.'));

            return null;
        }

        // 1 year
        $tfa_session_seconds = 525600;
        if (($settings_arr = $this->_accounts_plugin->get_plugin_settings())
            && !empty($settings_arr['2fa_session_length'])) {
            $tfa_session_seconds = $settings_arr['2fa_session_length'] * 3600;
        }

        $session_fields = [
            'tfa_expiration' => date(self::DATETIME_DB, time() + $tfa_session_seconds),
        ];

        if (!$this->_accounts_model->update_current_session($online_arr, $session_fields)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error updating session.'));

            return null;
        }

        PHS::current_user_session(true);

        return true;
    }

    public function cancel_tfa_setup($tfa_data) : ?bool
    {
        $this->reset_error();

        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid TFA data provided.'));

            return null;
        }

        // make sure that any sessions of TFA user are invalidated
        if (($flow_arr = $this->_accounts_model->fetch_default_flow_params(['table_name' => 'online']))
         && ($table_name = $this->_accounts_model->get_flow_table_name($flow_arr))) {
            db_query('UPDATE `'.$table_name.'` SET tfa_expiration = NULL WHERE uid = \''.$tfa_arr['uid'].'\'', $flow_arr['db_connection']);
        }

        if (!$this->hard_delete($tfa_arr)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error deleting two factor authentication data.'));

            return null;
        }

        return true;
    }

    /**
     * @param int|array $tfa_data
     *
     * @return null|array
     */
    public function finish_tfa_setup($tfa_data) : ?array
    {
        $this->reset_error();

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid TFA data provided.'));

            return null;
        }

        $edit_arr = $this->fetch_default_flow_params();
        $edit_arr['fields'] = [];
        $edit_arr['fields']['setup'] = date(self::DATETIME_DB);

        if (!($new_tfa_arr = $this->edit($tfa_arr, $edit_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error finishing two factor authenticatrion setup.'));

            return null;
        }

        return $new_tfa_arr;
    }

    /**
     * @param int|array $tfa_data
     *
     * @return null|array
     */
    public function recovery_codes_downloaded_for_tfa($tfa_data) : ?array
    {
        $this->reset_error();

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid TFA data provided.'));

            return null;
        }

        $edit_arr = $this->fetch_default_flow_params();
        $edit_arr['fields'] = [];
        $edit_arr['fields']['recovery_downloaded'] = date(self::DATETIME_DB);

        if (!($new_tfa_arr = $this->edit($tfa_arr, $edit_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error updating two factor authenticatrion setup.'));

            return null;
        }

        return $new_tfa_arr;
    }

    /**
     * @param int|array $tfa_data
     * @param string $code
     * @param null|array $params
     *
     * @return null|bool
     */
    public function verify_code_for_tfa_data($tfa_data, string $code, ?array $params = null) : ?bool
    {
        $this->reset_error();

        if (empty($params)) {
            $params = [];
        }

        $params['discrepancy'] = (int)($params['discrepancy'] ?? 1);
        $params['time_slice'] ??= null;

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid TFA data provided.'));

            return null;
        }

        if (!($secret = $this->get_secret($tfa_arr))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error obtaining secret for provided TFA data.'));

            return null;
        }

        return self::verify_code_with_secret($secret, $code, $params['discrepancy'], $params['time_slice']);
    }

    /**
     * @param int|array $tfa_data
     * @param string $code
     *
     * @return null|bool
     */
    public function verify_recovery_code_for_tfa_data($tfa_data, string $code) : ?bool
    {
        $this->reset_error();

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid TFA data provided.'));

            return null;
        }

        if (!($codes_arr = $this->get_recovery_codes($tfa_arr))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error obtaining recovery codes for provided TFA data.'));

            return null;
        }

        return in_array($code, $codes_arr, true);
    }

    /**
     * @param int $length
     *
     * @return null|string
     */
    public function generate_secret(int $length = 16) : ?string
    {
        $this->reset_error();

        // 80 to 640 bits
        if ($length < 16 || $length > 128) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Two factor authentication secret length is not valid.'));

            return null;
        }

        try {
            $random_arr = random_bytes($length);
        } catch (\Exception $e) {
            $random_arr = [];
            for ($i = 0; $i < $length; $i++) {
                $random_arr[] = mt_rand(0, 255);
            }
        }

        $chars_len = count(self::$SECRET_CHARS_ARR);
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$SECRET_CHARS_ARR[ord($random_arr[$i]) % $chars_len];
        }

        return $secret;
    }

    /**
     * @param int $codes_no
     * @param int $secret_length
     *
     * @return array
     */
    public function generate_recovery_codes(int $codes_no = 8, int $secret_length = 16) : array
    {
        $result_arr = [];
        for ($i = 0; $i < $codes_no; $i++) {
            $result_arr[] = $this->generate_secret($secret_length);
        }

        return $result_arr;
    }

    /**
     * @param bool $force
     *
     * @return null|array
     */
    public function get_tfa_for_current_account(bool $force = false) : ?array
    {
        static $tfa_arr = null;

        if (empty($force)
            && $tfa_arr !== null) {
            return $tfa_arr;
        }

        // If user is not logged in yet, do not cache the result
        if (!($account_arr = PHS::current_user())
            || null === ($tfa_data = $this->get_tfa_data_for_account($account_arr))) {
            return [];
        }

        $tfa_arr = $tfa_data['tfa_data'] ?? [];

        return $tfa_arr;
    }

    /**
     * @param int|array $account_data
     *
     * @return null|array
     */
    public function get_tfa_data_for_account($account_data) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($account_arr = $this->_accounts_model->data_to_array($account_data))
            || $this->_accounts_model->is_deleted($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        if (!($tfa_arr = $this->get_details_fields(['uid' => $account_arr['id']]))) {
            $tfa_arr = null;
        }

        return [
            'account_data' => $account_arr,
            'tfa_data'     => $tfa_arr,
        ];
    }

    /**
     * @param int|array $account_data
     *
     * @return null|array
     */
    public function install_tfa_for_account($account_data) : ?array
    {
        if (!($tfa_check = $this->get_tfa_data_for_account($account_data))) {
            return null;
        }

        if (!empty($tfa_check['tfa_data'])) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Two factor authentication installation not required for provided account.'));

            return null;
        }

        $account_arr = $tfa_check['account_data'] ?? null;

        if (empty($account_arr['id'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        $flow_arr = $this->fetch_default_flow_params(['table_name' => 'users_tfa']);
        $fields_arr = [];
        $fields_arr['uid'] = $account_arr['id'];

        $flow_arr['fields'] = $fields_arr;

        if (!($result = $this->insert($flow_arr))) {
            if (self::st_debugging_mode()) {
                PHS_Logger::debug('Error installing 2FA data for account #'.$account_arr['id'].': '.$this->get_error_message(),
                    PHS_Logger::TYPE_DEBUG);
            }

            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_pt('Error installing two factor authentication data for provided account.'));

            return null;
        }

        return $result;
    }

    /**
     * @param int|array $account_data
     * @param array $tfa_fields
     *
     * @return null|array
     */
    public function update_tfa_for_account($account_data, array $tfa_fields) : ?array
    {
        if (!($tfa_check = $this->get_tfa_data_for_account($account_data))) {
            return null;
        }

        if (empty($tfa_check['tfa_data'])
            || empty($tfa_check['account_data'])) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Account doesn\'t have two factor authentication installed.'));

            return null;
        }

        $existing_tfa = $tfa_check['tfa_data'];
        $account_arr = $tfa_check['account_data'];

        $flow_arr = $this->fetch_default_flow_params(['table_name' => 'users_tfa']);
        $fields_arr = [];

        if (!empty($tfa_fields['secret'])) {
            if (!is_string($tfa_fields['secret'])) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid secret for two factor authentication.'));

                return null;
            }

            $fields_arr['secret'] = $tfa_fields['secret'];
        }

        if (!empty($tfa_fields['recovery'])) {
            if (!is_array($tfa_fields['recovery'])) {
                $this->set_error(self::ERR_PARAMETERS,
                    $this->_pt('Invalid recovery data for two factor authentication.'));

                return null;
            }

            $fields_arr['recovery'] = $tfa_fields['recovery'];
        }

        if (empty($fields_arr)) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Nothing to update for two factor authentication for provided account.'));

            return null;
        }

        $flow_arr['fields'] = $fields_arr;

        if (!($result = $this->edit($existing_tfa, $flow_arr))) {
            if (self::st_debugging_mode()) {
                PHS_Logger::debug('Error updating 2FA data for account #'.$account_arr['id'].': '.$this->get_error_message(),
                    PHS_Logger::TYPE_DEBUG);
            }

            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_pt('Error saving two factor authentication data for provided account.'));

            return null;
        }

        return $result;
    }

    /**
     * @param int|array $account_data
     * @param null|array $params
     *
     * @return null|array
     */
    public function get_qr_code_url_for_tfa_setup($account_data, ?array $params = null) : ?array
    {
        $this->reset_error();

        if (!$this->_load_dependencies()) {
            return null;
        }

        $params ??= [];

        $params['link_expiration_seconds'] ??= 60;

        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
        if (!($libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!($account_arr = $this->_accounts_model->data_to_array($account_data))
            || $this->_accounts_model->is_deleted($account_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Account not found in database.'));

            return null;
        }

        if (!($tfa_check = $this->get_tfa_data_for_account($account_arr))
            || empty($tfa_check['tfa_data'])) {
            if ($this->has_error()
                || !($tfa_arr = $this->install_tfa_for_account($account_arr))) {
                return null;
            }
        } else {
            $tfa_arr = $tfa_check['tfa_data'];
        }

        if (!($tfa_url = $this->_get_tfa_otp_url($account_arr))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS,
                    $this->_pt('Error obtaining two factor authentication setup URL.'));
            }

            return null;
        }

        $link_params = [];
        $link_params['link_expiration_seconds'] = $params['link_expiration_seconds'];
        $link_params['for_account_id'] = $account_arr['id'];
        // Image expiration, doesn't affect QR code functionality
        $link_params['expiration_hours'] = 24;

        if (!($setup_url = $libs_plugin->generate_qr_code_img_url($tfa_url, $link_params))) {
            if ($libs_plugin->has_error()) {
                $this->copy_error($libs_plugin, self::ERR_FUNCTIONALITY);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error generating two factor authentication setup link.'));
            }

            return null;
        }

        PHS_Logger::notice('[SETUP] Generated TFA setup URL for account #'.$account_arr['id'],
            $this->_accounts_plugin::LOG_TFA);

        return [
            'url'      => $setup_url,
            'tfa_data' => $tfa_arr ?? null,
        ];
    }

    /**
     * @param int|array $tfa_data
     *
     * @return null|string
     */
    public function get_secret($tfa_data) : ?string
    {
        $this->reset_error();

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Two factor authentication data not found in database.'));

            return null;
        }

        if (empty($tfa_arr['secret'])
            || !($result = PHS_Crypt::quick_decode($tfa_arr['secret']))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_pt('Error obtaining two factor authentication secret.'));

            return null;
        }

        return $result;
    }

    /**
     * @param int|array $tfa_data
     *
     * @return null|array
     */
    public function get_recovery_codes($tfa_data) : ?array
    {
        $this->reset_error();

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Two factor authentication data not found in database.'));

            return null;
        }

        if (empty($tfa_arr['recovery'])
            || !($result = $this->decode_recovery_field($tfa_arr['recovery']))) {
            return [];
        }

        return $result;
    }

    public function download_recovery_codes_file($tfa_data) : ?bool
    {
        $this->reset_error();

        if (@headers_sent()) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Headers already sent. Cannot send file to browser.'));

            return null;
        }

        if (!($tfa_arr = $this->data_to_array($tfa_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Two factor authentication data not found in database.'));

            return null;
        }

        if (!($content = $this->get_recovery_codes_download_file_content($tfa_arr))) {
            return null;
        }

        if (!$this->recovery_codes_downloaded_for_tfa($tfa_arr)) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $this->_pt('Error updating two factor authentication data. Please try again.'));

            return null;
        }

        @header('Content-Transfer-Encoding: binary');
        @header('Content-Disposition: attachment; filename="recovery_'.$tfa_arr['id'].'.txt"');
        @header('Expires: 0');
        @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        @header('Pragma: public');
        @header('Content-Type: text/plain; charset=UTF-8');
        @header('Content-Length: '.strlen($content));
        @header('Content-Encoding: UTF-8');

        echo $content;
        exit;
    }

    public function get_recovery_codes_download_file_content($tfa_data) : ?string
    {
        $this->reset_error();

        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($tfa_arr = $this->data_to_array($tfa_data))
            || !($account_arr = $this->_accounts_model->get_details($tfa_arr['uid']))
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Two factor authentication data not found in database.'));

            return null;
        }

        if (!$this->is_setup_completed($tfa_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Two factor authentication setup not finialized.'));

            return null;
        }

        if (!($recovery_codes = $this->get_recovery_codes($tfa_arr))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error obtaining recovery codes.'));

            return null;
        }

        $site_name = PHS_SITE_NAME;
        if (($settings_arr = $this->_accounts_plugin->get_plugin_settings())
            && !empty($settings_arr['2fa_issuer_name'])) {
            $site_name = $settings_arr['2fa_issuer_name'];
        }

        $content = 'Recovery codes for account '.$account_arr['nick'].', platform '.$site_name.':'."\n"
                   ."\n";

        foreach ($recovery_codes as $recovery_code) {
            $content .= ' - '.$recovery_code."\n";
        }

        return $content."\n";
    }

    /**
     * @param string $recovery
     *
     * @return null|array
     */
    public function decode_recovery_field(string $recovery) : ?array
    {
        $this->reset_error();

        if (empty($recovery)
            || !($decrypted_recovery = PHS_Crypt::quick_decode($recovery))
            || !($recovery_codes = @json_decode($decrypted_recovery, true))
            || !is_array($recovery_codes)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error decoding recovery data.'));

            return null;
        }

        return $recovery_codes;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false)
    {
        // $params should be flow parameters...
        if (empty($params) || !is_array($params)
         || empty($params['table_name'])) {
            return false;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'users_tfa':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'uid' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'secret' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                    ],
                    'recovery' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'setup' => [
                        'type'    => self::FTYPE_DATETIME,
                        'comment' => 'When TFA setup finished',
                    ],
                    'recovery_downloaded' => [
                        'type'    => self::FTYPE_DATETIME,
                        'comment' => 'Recovery codes downloaded',
                    ],
                    'last_update' => [
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

    protected function get_insert_prepare_params_users_tfa($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['uid'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide an user account id.'));

            return false;
        }

        if ((empty($params['fields']['secret'])
             || !is_string($params['fields']['secret']))
            && !($params['fields']['secret'] = $this->generate_secret())) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide a secret for 2FA.'));

            return false;
        }

        if (empty($params['fields']['recovery'])
            || !is_array($params['fields']['recovery'])) {
            $params['fields']['recovery'] = $this->generate_recovery_codes();
        }

        if (!($params['fields']['recovery'] = $this->_encode_recovery_codes($params['fields']['recovery']))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error encoding recovery data.'));
            }

            return false;
        }

        if (!($params['fields']['secret'] = PHS_Crypt::quick_encode($params['fields']['secret']))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encoding secret key.'));

            return false;
        }

        $params['fields']['last_update'] = date(self::DATETIME_DB);

        if (empty($params['fields']['cdate']) || empty_db_date($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $params['fields']['last_update'];
        } else {
            $params['fields']['cdate'] = date(self::DATETIME_DB, parse_db_date($params['fields']['cdate']));
        }

        return $params;
    }

    protected function get_edit_prepare_params_users_tfa($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['uid']) && empty($params['fields']['uid'])) {
            $this->set_error(self::ERR_INSERT, $this->_pt('Please provide an user account id.'));

            return false;
        }

        if (!empty($params['fields']['secret'])
            && (!is_string($params['fields']['secret'])
                || !($params['fields']['secret'] = PHS_Crypt::quick_encode($params['fields']['secret'])))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encoding secret key.'));

            return false;
        }

        if (!empty($params['fields']['recovery'])
            && (!is_array($params['fields']['recovery'])
                || !($params['fields']['recovery'] = $this->_encode_recovery_codes($params['fields']['recovery'])))
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error encoding recovery data.'));

            return false;
        }

        $params['fields']['last_update'] = date(self::DATETIME_DB);

        return $params;
    }

    /**
     * @return bool
     */
    private function _load_dependencies() : bool
    {
        if ((empty($this->_accounts_plugin)
             && !($this->_accounts_plugin = PHS_Plugin_Accounts::get_instance()))
            || (empty($this->_accounts_model)
                && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * @param int|array $account_data
     *
     * @return null|string
     */
    private function _get_tfa_otp_url($account_data) : ?string
    {
        $this->reset_error();

        if (!$this->_load_dependencies()) {
            return null;
        }

        if (!($tfa_check = $this->get_tfa_data_for_account($account_data))) {
            return null;
        }

        $existing_tfa = $tfa_check['tfa_data'] ?? null;
        $account_arr = $tfa_check['account_data'] ?? null;

        if (empty($account_arr['id'])
         || empty($existing_tfa['id'])) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Account doesn\'t have two factor authentication enabled.'));

            return null;
        }

        if (!($secret_str = $this->get_secret($existing_tfa))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error obtaining two factor authentication details.'));
            }

            return null;
        }

        if (!($settings_arr = $this->_accounts_plugin->get_plugin_settings())) {
            $settings_arr = [];
        }

        $issuer = $settings_arr['2fa_issuer_name'] ?? PHS_SITE_NAME;

        return 'otpauth://totp/'
               .rawurlencode($issuer)
               .':'
               .rawurlencode($account_arr['nick'])
               .'?secret='.$secret_str
               .'&issuer='.rawurlencode($issuer)
               .'&algorithm='.rawurlencode(self::TFA_ALGO)
               .'&digits='.self::CODE_LENGTH
               .'&period='.self::TFA_PERIOD;
    }

    /**
     * @param array $recovery_arr
     *
     * @return null|string
     */
    private function _encode_recovery_codes(array $recovery_arr) : ?string
    {
        $this->reset_error();

        if (!($recovery_str = @json_encode($recovery_arr))
            || !($encrypted_recovery = PHS_Crypt::quick_encode($recovery_str))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error encoding recovery data.'));

            return null;
        }

        return $encrypted_recovery;
    }

    /**
     * @param string $secret
     * @param null|float $timeSlice
     *
     * @return string
     */
    public static function get_code_from_secret(string $secret, ?float $timeSlice = null) : string
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretkey = self::_debase32($secret);

        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hm = hash_hmac(self::TFA_ALGO, $time, $secretkey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);

        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value &= 0x7FFFFFFF;

        $modulo = 10 ** self::CODE_LENGTH;

        return str_pad($value % $modulo, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $secret
     * @param string $code
     * @param int $discrepancy
     * @param null|float $time_slice
     *
     * @return bool
     */
    public static function verify_code_with_secret(string $secret, string $code, int $discrepancy = 1, ?float $time_slice = null) : bool
    {
        if ($time_slice === null) {
            $time_slice = floor(time() / 30);
        }

        if (strlen($code) !== self::CODE_LENGTH) {
            return false;
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::get_code_from_secret($secret, $time_slice + $i);
            if (self::_safe_equal_strings($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $secret
     *
     * @return string
     */
    private static function _debase32(string $secret) : string
    {
        if (empty($secret)) {
            return '';
        }

        $base32chars = self::$SECRET_CHARS_ARR;
        $base32chars[] = self::PADDING_CHAR;

        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, self::PADDING_CHAR);
        if (!in_array($paddingCharCount, [6, 4, 3, 1, 0], true)
            || ($paddingCharCount > 0
                && substr($secret, -($paddingCharCount)) !== str_repeat(self::PADDING_CHAR, $paddingCharCount))) {
            return false;
        }

        $secret_arr = str_split(str_replace('=', '', $secret));
        $result = '';
        for ($i = 0, $iMax = count($secret_arr); $i < $iMax; $i += 8) {
            $x = '';
            if (!in_array($secret_arr[$i], $base32chars, true)) {
                return false;
            }
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret_arr[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0, $zMax = count($eightBits); $z < $zMax; $z++) {
                $result .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) === 48) ? $y : '';
            }
        }

        return $result;
    }

    /**
     * @param string $string_one
     * @param string $string_two
     *
     * @return bool
     */
    private static function _safe_equal_strings(string $string_one, string $string_two) : bool
    {
        if (@function_exists('hash_equals')) {
            return hash_equals($string_one, $string_two);
        }

        if (($userLen = strlen($string_two)) !== strlen($string_one)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($string_one[$i]) ^ ord($string_two[$i]));
        }

        return $result === 0;
    }
}
