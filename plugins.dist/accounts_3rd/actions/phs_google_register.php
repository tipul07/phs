<?php
namespace phs\plugins\accounts_3rd\actions;

use phs\PHS;
use phs\PHS_Crypt;
use phs\PHS_Scope;
use phs\PHS_Session;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\system\core\events\actions\PHS_Event_Action_after;

class PHS_Action_Google_register extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles() : array
    {
        return [self::ACT_ROLE_REGISTER];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Register with Google'));

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $accounts_trd_plugin */
        /** @var \phs\plugins\accounts_3rd\models\PHS_Model_Accounts_services $services_model */
        if (!($accounts_plugin = PHS::load_plugin('accounts'))
         || !($accounts_trd_plugin = PHS::load_plugin('accounts_3rd'))
         || !($google_lib = $accounts_trd_plugin->get_google_instance())
         || !($accounts_model = PHS::load_model('accounts', 'accounts'))
         || !($services_model = PHS::load_model('accounts_services', 'accounts_3rd'))) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        $account_info = false;
        $account_arr = false;
        $login_required = false;
        $retry_action = false;

        $display_error_msg = '';
        $display_message_msg = '';

        if (!($google_code = PHS_Params::_gp('code', PHS_Params::T_NOHTML))) {
            $retry_action = true;
            $display_error_msg = $this->_pt('Invalid Google token. Please try again.');
        } elseif (!($account_info = $google_lib->get_web_account_details_by_code($google_code, 'register'))
               || !is_array($account_info)) {
            $retry_action = true;
            $account_info = false;
            $display_error_msg = $this->_pt('Error obtaining Google account details. Please try again.');
        }

        if (!empty($account_info)) {
            if (empty($account_info['email'])) {
                $account_info = false;
                $retry_action = true;
                $display_error_msg = $this->_pt('Error obtaining Google account email address. Please make sure you give us rights to read Google account email address.');
            } elseif (($account_arr = $accounts_model->get_details_fields(['email' => $account_info['email']]))) {
                $account_arr = false;

                $error_msg = $this->_pt('This email address is already registered on this platform.');

                $login_required = true;
                $retry_action = true;
                $display_message_msg = $error_msg;
            }
        }

        if (empty($account_arr)
         && !empty($account_info)) {
            $fields_arr = [];
            $fields_arr['nick'] = $account_info['email'];
            $fields_arr['email'] = $account_info['email'];
            $fields_arr['pass'] = '';
            $fields_arr['level'] = $accounts_model::LVL_MEMBER;
            $fields_arr['status'] = $accounts_model::STATUS_ACTIVE;
            $fields_arr['lastip'] = request_ip();

            if (!empty($account_info['locale'])
             && self::valid_language($account_info['locale'])) {
                $fields_arr['language'] = $account_info['locale'];
            }

            $insert_arr = $accounts_model->fetch_default_flow_params(['table_name' => 'users']);
            $insert_arr['fields'] = $fields_arr;
            if (!empty($account_info['given_name']) || !empty($account_info['family_name'])) {
                $insert_arr['{users_details}'] = [];
                if (!empty($account_info['given_name'])) {
                    $insert_arr['{users_details}']['fname'] = trim($account_info['given_name']);
                }
                if (!empty($account_info['family_name'])) {
                    $insert_arr['{users_details}']['lname'] = trim($account_info['family_name']);
                }
            }

            if (!($account_arr = $accounts_model->insert($insert_arr))) {
                $account_arr = false;
                $retry_action = true;
                $error_msg = '';
                if ($accounts_model->has_error()) {
                    $error_msg = $accounts_model->get_simple_error_message().' ';
                }

                $display_error_msg = $this->_pt('Error registering account.')
                                     .' '
                                     .$error_msg.$this->_pt('Please try again.');
            } else {
                PHS_Logger::notice('[GOOGLE] Registered user #'.$account_arr['id'].' with details ['.print_r($account_info, true).'].', $accounts_trd_plugin::LOG_CHANNEL);

                if (!($db_linkage_arr = $services_model->link_user_with_service($account_arr['id'], $services_model::SERVICE_GOOGLE, @json_encode($account_info)))) {
                    PHS_Logger::error('Error linking Google service with user #'.$account_arr['id'].'.', $accounts_trd_plugin::LOG_ERR_CHANNEL);
                }

                // Login user after registration...
                if (!($plugin_settings = $accounts_plugin->get_plugin_settings())) {
                    $plugin_settings = [];
                }

                if (empty($plugin_settings['session_expire_minutes_normal'])) {
                    $plugin_settings['session_expire_minutes_normal'] = 0;
                } // till browser closes

                $login_params = [];
                $login_params['expire_mins'] = $plugin_settings['session_expire_minutes_normal'];

                if ($accounts_plugin->do_login($account_arr, $login_params)) {
                    if (($account_language = $accounts_model->get_account_language($account_arr))) {
                        if (!($current_language = self::get_current_language())
                         || $current_language !== $account_language) {
                            self::set_current_language($account_language);
                            PHS_Session::_s(self::LANG_SESSION_KEY, $account_language);
                        }
                    }

                    if (($event_result = PHS_Event_Action_after::action(PHS_Event_Action_after::LOGIN, $this))
                        && !empty($event_result['action_result']) && is_array($event_result['action_result'])) {
                        $this->set_action_result($event_result['action_result']);

                        return $event_result['action_result'];
                    }

                    return action_redirect();
                }

                $retry_action = true;

                $display_error_msg = $this->_pt('Error logging in.')
                                     .' '
                                     .$this->_pt('Please try again.');
            }
        }

        $data_arr = [
            'display_error_msg'   => $display_error_msg,
            'display_message_msg' => $display_message_msg,
            'account_arr'         => $account_arr,
            'account_info'        => $account_info,
            'retry_action'        => $retry_action,
            'login_required'      => $login_required,
            'phs_gal_code'        => ($account_info ? $this->encode_google_account_data($account_info) : ''),
            'google_lib'          => $google_lib,
        ];

        return $this->quick_render_template('google/register', $data_arr);
    }

    public function encode_google_account_data($google_result)
    {
        return PHS_Crypt::quick_encode(@json_encode($google_result));
    }

    public function decode_google_account_data($google_result)
    {
        if (!($clean_str = PHS_Crypt::quick_decode($google_result))
         || !($result_arr = @json_decode($clean_str, true))) {
            return false;
        }

        return $result_arr;
    }
}
