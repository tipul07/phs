<?php
namespace phs\plugins\accounts\models;

use phs\PHS_Crypt;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Logger;
use phs\plugins\accounts\PHS_Plugin_Accounts;

class PHS_Model_Accounts_tfa extends PHS_Model
{
    private static array $SECRET_CHARS_ARR = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
        'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
        'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
        'Y', 'Z', '2', '3', '4', '5', '6', '7',
    ];

    private const PADDING_CHAR = '=';

    private const CODE_LENGTH = 6;
    private const TFA_PERIOD = 30;
    private const TFA_ALGO = 'SHA1';

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
        return ['users_tfa'];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'users_tfa';
    }

    public function get_code_length(): int
    {
        return self::CODE_LENGTH;
    }

    public function generate_secret(int $length = 16): ?string
    {
        $this->reset_error();

        // 80 to 640 bits
        if ($length < 16 || $length > 128) {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Two factor authentication secret length is not valid.' ) );
            return null;
        }

        try {
            $random_arr = random_bytes( $length );
        } catch( \Exception $e ) {
            $random_arr = [];
            for( $i = 0; $i < $length; $i++ ) {
                $random_arr[] = mt_rand(0, 255);
            }
        }

        $chars_len = count( self::$SECRET_CHARS_ARR );
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$SECRET_CHARS_ARR[ord($random_arr[$i]) % $chars_len];
        }

        return $secret;
    }

    public function generate_recovery_codes( int $codes_no = 8, int $secret_length = 16 ): array
    {
        $result_arr = [];
        for( $i = 0; $i < $codes_no; $i++ ) {
            $result_arr[] = $this->generate_secret($secret_length);
        }

        return $result_arr;
    }

    public function get_tfa_data_for_account( $account_data ): ?array
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS_Model_Accounts::get_instance()) ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt( 'Error loading required resources.' ) );
            return null;
        }

        if( !($account_arr = $accounts_model->data_to_array( $account_data ))
            || $accounts_model->is_deleted( $account_arr ) ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt( 'Account not found in database.' ) );
            return null;
        }

        if( !($tfa_arr = $this->get_details_fields( [ 'account_id' => $account_arr['id'] ])) ) {
            $tfa_arr = null;
        }

        return [
            'account_data' => $account_arr,
            'tfa_data' => $tfa_arr,
        ];
    }

    /**
     * @param int|array $account_data
     * @param  array  $tfa_data
     *
     * @return null|array
     */
    public function update_tfa_for_account( $account_data, array $tfa_data ): ?array
    {
        if( !($tfa_check = $this->get_tfa_data_for_account( $account_data )) ) {
            return null;
        }

        $existing_tfa = $tfa_check['tfa_data'] ?? null;
        $account_arr = $tfa_check['account_data'] ?? null;

        if( empty( $account_arr['id'] ) ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt( 'Account not found in database.' ) );
            return null;
        }

        $flow_arr = $this->fetch_default_flow_params(['table_name' => 'users_tfa']);
        $fields_arr = [];

        if( !empty( $tfa_data['secret'] ) ) {
            if( !is_string( $tfa_data['secret'] ) ) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt( 'Invalid secret for two factor authentication.' ) );
                return null;
            }

            $fields_arr['secret'] = $tfa_data['secret'];
        }

        if( !empty( $tfa_data['recovery'] ) ) {
            if( !is_array( $tfa_data['recovery'] ) ) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt( 'Invalid recovery data for two factor authentication.' ) );
                return null;
            }

            $fields_arr['recovery'] = $tfa_data['recovery'];
        }

        if( empty( $existing_tfa ) ) {
            $fields_arr['uid'] = $account_arr['id'];

            $flow_arr['fields'] = $fields_arr;

            if( !($result = $this->insert( $flow_arr )) ) {
                if( self::st_debugging_mode() ) {
                    PHS_Logger::debug('Error saving 2FA data for account #'.$account_arr['id'].': '.$this->get_error_message(),
                        PHS_Logger::TYPE_DEBUG );
                }
            }
        } else {
            $flow_arr['fields'] = $fields_arr;

            if( !($result = $this->edit( $existing_tfa, $flow_arr )) ) {
                if( self::st_debugging_mode() ) {
                    PHS_Logger::debug('Error chaning 2FA data for account #'.$account_arr['id'].': '.$this->get_error_message(),
                        PHS_Logger::TYPE_DEBUG );
                }
            }
        }

        if( empty( $result ) ) {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error saving two factor authentication data in database.' ) );
            return null;
        }

        return $result;
    }

    public function get_tfa_otp_url( $account_data ): ?string
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = PHS_Plugin_Accounts::get_instance()) ) {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Error loading required resources.' ) );
            return null;
        }

        if( !($tfa_check = $this->get_tfa_data_for_account( $account_data )) ) {
            return null;
        }

        $existing_tfa = $tfa_check['tfa_data'] ?? null;
        $account_arr = $tfa_check['account_data'] ?? null;

        if( empty( $account_arr['id'] )
         || empty( $existing_tfa['id'] ) ) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt( 'Account doesn\'t have two factor authentication enabled.' ) );
            return null;
        }

        if( !($secret_str = $this->get_secret( $existing_tfa )) ) {
            if( !$this->has_error() ) {
                $this->set_error(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error obtaining two factor authentication details.'));
            }
            return null;
        }

        if( !($settings_arr = $accounts_plugin->get_plugin_settings()) ) {
            $settings_arr = [];
        }

        $issuer = $settings_arr['2fa_issuer_name'] ?? PHS_SITE_NAME;

        return 'otpauth://totp/'.
               rawurlencode($issuer).
               ':'.
               rawurlencode($account_arr['nick']).
               '?secret='.$secret_str.
               '&issuer='.rawurlencode($issuer).
               '&algorithm='.rawurlencode(self::TFA_ALGO).
               '&digits='.self::CODE_LENGTH.
               '&period='.self::TFA_PERIOD;
    }

    /**
     * @param int|array $tfa_data
     *
     * @return null|string
     */
    public function get_secret( $tfa_data ): ?string
    {
        $this->reset_error();

        if( !($tfa_arr = $this->data_to_array( $tfa_data )) ) {
            $this->set_error( self::ERR_PARAMETERS,
                $this->_pt( 'Two factor authentication data not found in database.' ) );
            return null;
        }

        if( empty( $tfa_arr['secret'] )
            || !($result = PHS_Crypt::quick_decode( $tfa_arr['secret'] )) ) {
            $this->set_error( self::ERR_FUNCTIONALITY,
                $this->_pt( 'Error obtaining two factor authentication secret.' ) );
            return null;
        }

        return $result;
    }

    /**
     * @param int|array $tfa_data
     *
     * @return null|array
     */
    public function get_recovery_codes( $tfa_data ): ?array
    {
        $this->reset_error();

        if( !($tfa_arr = $this->data_to_array( $tfa_data )) ) {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Two factor authentication data not found in database.' ) );
            return null;
        }

        if( empty( $tfa_arr['recovery'] )
            || !($result = $this->decode_recovery_field( $tfa_arr['recovery'] )) ) {
            return [];
        }

        return $result;
    }

    public function decode_recovery_field( string $recovery ): ?array
    {
        $this->reset_error();

        if( empty( $recovery )
            || !($decrypted_recovery = PHS_Crypt::quick_decode( $recovery ))
            || !($recovery_codes = @json_decode( $decrypted_recovery, true ))
            || !is_array( $recovery_codes ) ) {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error decoding recovery data.' ) );
            return null;
        }

        return $recovery_codes;
    }

    /**
     * @param array $recovery_arr
     *
     * @return null|string
     */
    private function _encode_recovery_codes( array $recovery_arr ): ?string
    {
        $this->reset_error();

        if( !($recovery_str = @json_encode( $recovery_arr ))
            || !($encrypted_recovery = PHS_Crypt::quick_encode( $recovery_str )) ) {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error encoding recovery data.' ) );
            return null;
        }

        return $encrypted_recovery;
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
                        'type'     => self::FTYPE_INT,
                        'index'    => true,
                    ],
                    'secret' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                    ],
                    'recovery' => [
                        'type'     => self::FTYPE_TEXT,
                    ],
                    'last_update' => [
                        'type'     => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type'     => self::FTYPE_DATETIME,
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
            || !is_array($params['fields']['recovery']) ) {
            $params['fields']['recovery'] = $this->generate_recovery_codes();
        }

        if( !($params['fields']['recovery'] = $this->_encode_recovery_codes( $params['fields']['recovery'] )) ) {
            if( !$this->has_error() ) {
                $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error encoding recovery data.' ) );
            }

            return false;
        }

        if( !($params['fields']['secret'] = PHS_Crypt::quick_encode( $params['fields']['secret'] )) ) {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error encoding secret key.' ) );
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

        if( !empty( $params['fields']['secret'] )
            && (!is_string( $params['fields']['secret'] )
                || !($params['fields']['secret'] = PHS_Crypt::quick_encode( $params['fields']['secret'] ))) ) {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error encoding secret key.' ) );
            return false;
        }

        if (!empty($params['fields']['recovery'])
            && (!is_array($params['fields']['recovery'])
                || !($params['fields']['recovery'] = $this->_encode_recovery_codes( $params['fields']['recovery'] )))
        ) {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error encoding recovery data.' ) );
            return false;
        }

        $params['fields']['last_update'] = date(self::DATETIME_DB);

        return $params;
    }
}
