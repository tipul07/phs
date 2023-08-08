<?php
namespace phs\plugins\accounts\actions\tfa;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\plugins\phs_libs\PHS_Plugin_Phs_libs;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_tfa;
use phs\system\core\events\actions\PHS_Event_Action_start;
use phs\system\core\events\actions\PHS_Event_Action_after;

class PHS_Action_Setup extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_TFA_SETUP, ];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::TFA_SETUP, $this))
            && !empty($event_result['action_result']) && is_array($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        PHS::page_settings('page_title', $this->_pt('Two Factor Authentication Setup'));
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
        if (!($tfa_model = PHS_Model_Accounts_tfa::get_instance())
            || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!($back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML))) {
            $back_page = '';
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $tfa_code = PHS_Params::_p('tfa_code', PHS_Params::T_ASIS);
        $remember_device = PHS_Params::_p('remember_device', PHS_Params::T_BOOL);

        $do_submit = PHS_Params::_p('do_submit');
        $do_download_codes = PHS_Params::_p('do_download_codes');

        if( empty( $foobar ) ) {
            $remember_device = true;
        }

        if( PHS_Params::_g('setup_success', PHS_Params::T_INT ) ) {
            PHS_Notifications::add_success_notice($this->_pt('Two factor authentication setup with success.'));
        }

        if (!($tfa_details = $tfa_model->get_qr_code_url_for_tfa_setup($current_user))
            || empty($tfa_details['url']['full_url'])
            || empty($tfa_details['tfa_data'])) {
            if (!empty($do_submit)) {
                unset($do_submit);
            }

            PHS_Notifications::add_warning_notice($this->_pt('Error obtaining two factor authentication setup link. Please refresh the page or contact support.'));
        }

        $tfa_arr = $tfa_details['tfa_data'] ?? null;
        $qr_code_url = $tfa_details['url']['full_url'] ?? null;

        $setup_completed = (!empty( $tfa_arr ) && $tfa_model->is_setup_completed($tfa_arr));

        if (!empty( $tfa_arr )
            && $tfa_model->is_recovery_code_downloaded($tfa_arr)) {
            return action_redirect();
        }

        if (!empty($do_download_codes)) {
            if (!$setup_completed) {
                PHS_Notifications::add_warning_notice($this->_pt('You have to finalize two factor authentication setup first.'));
            } elseif (!$tfa_model->download_recovery_codes_file($tfa_arr)) {
                $error_msg = $tfa_model->has_error()
                    ? $tfa_model->get_simple_error_message()
                    : $this->_pt('Couldn\'t generate recovery codes file.');

                PHS_Notifications::add_error_notice($this->_pt('Error downloading two factor authentication recovery codes file: %s', $error_msg));
            }
        }

        if (!empty($do_submit)) {
            $new_tfa_arr = null;
            if (empty($tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide a verification code.'));
            } elseif (!$tfa_model->verify_code_for_tfa_data($tfa_arr, $tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Two factor authentication verification failed. Please try again.'));
            } elseif (!($new_tfa_arr = $tfa_model->finish_tfa_setup($tfa_arr))) {
                PHS_Notifications::add_error_notice($this->_pt('Error finializing two factor authentication setup. Please try again.'));
            } elseif (!$tfa_model->validate_tfa_for_session()) {
                PHS_Notifications::add_error_notice($this->_pt('Error finializing two factor authentication setup. Please try again.'));
            } else {
                PHS_Notifications::add_success_notice($this->_pt('Two factor authentication setup with success.'));
            }

            if (!empty($new_tfa_arr)) {
                $tfa_arr = $new_tfa_arr;

                if( !PHS_Notifications::have_notifications_errors() ) {
                    if (!empty($remember_device) && !$tfa_model->mark_device_as_tfa_valid()) {
                        PHS_Notifications::add_warning_notice($this->_pt('Error marking device as two factor authentication valid.'));
                    }

                    if (($event_result = PHS_Event_Action_after::action(PHS_Event_Action_after::TFA_SETUP,
                            $this)) && !empty($event_result['action_result']) && is_array($event_result['action_result'])) {
                        $this->set_action_result($event_result['action_result']);

                        return $event_result['action_result'];
                    }

                    $args = [
                        'setup_success' => 1,
                    ];
                    if (!empty($back_page)) {
                        $args['back_page'] = $back_page;
                    }

                    return action_redirect(['p' => 'accounts', 'a' => 'setup', 'ad' => 'tfa'], $args);
                }
            }
        }

        $data = [
            'back_page' => $back_page,
            'nick'        => $current_user['nick'],
            'qr_code_url' => $qr_code_url,
            'tfa_data'    => $tfa_arr,
            'setup_completed'    => $setup_completed,
            'remember_device'    => $remember_device,
            'device_session_length'    => $accounts_plugin->tfa_remember_device_length(),

            'libs_plugin'     => $libs_plugin,
            'tfa_model'       => $tfa_model,
            'accounts_plugin' => $accounts_plugin,
        ];

        return $this->quick_render_template('tfa/setup', $data);
    }
}
