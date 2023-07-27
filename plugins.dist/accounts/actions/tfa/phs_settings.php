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

class PHS_Action_Settings extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_TFA_SETTINGS, ];
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
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::TFA_SETTINGS, $this))
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

        PHS::page_settings('page_title', $this->_pt('Two Factor Authentication Settings'));
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
        if (!($tfa_model = PHS_Model_Accounts_tfa::get_instance())
            || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $tfa_code = PHS_Params::_p('tfa_code', PHS_Params::T_ASIS);

        $do_download_codes = PHS_Params::_p('do_download_codes');
        $do_cancel_tfa = PHS_Params::_p('do_cancel_tfa');

        if (!($tfa_details = $tfa_model->get_tfa_data_for_account($current_user))) {
            $tfa_details = null;
        }

        $tfa_arr = $tfa_details['tfa_data'] ?? null;

        if (PHS_Params::_g('tfa_cancelled', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Two Factor Authentication disabled for your account.'));
        }

        if (!empty($do_download_codes)) {
            if (empty($tfa_arr)) {
                PHS_Notifications::add_warning_notice($this->_pt('You should first setup two factor authentication for your account.'));
            } elseif (empty($tfa_code)) {
                PHS_Notifications::add_warning_notice($this->_pt('Please provide a two factor authentication code.'));
            } elseif (!$tfa_model->is_setup_completed($tfa_arr)) {
                PHS_Notifications::add_warning_notice($this->_pt('You have to finalize two factor authentication setup first.'));
            } elseif (!$tfa_model->verify_code_for_tfa_data($tfa_arr, $tfa_code)) {
                PHS_Notifications::add_warning_notice($this->_pt('Invalid verification code. Please try again.'));
            } elseif (!$tfa_model->download_recovery_codes_file($tfa_arr)) {
                $error_msg = $tfa_model->has_error()
                    ? $tfa_model->get_simple_error_message()
                    : $this->_pt('Couldn\'t generate recovery codes file.');

                PHS_Notifications::add_error_notice($this->_pt('Error downloading two factor authentication recovery codes file: %s', $error_msg));
            }
        }

        if (!empty($do_cancel_tfa)) {
            if (empty($tfa_arr)) {
                PHS_Notifications::add_warning_notice($this->_pt('No two factor authentication set up for your account yet.'));
            } elseif (empty($tfa_code)) {
                PHS_Notifications::add_warning_notice($this->_pt('Please provide a two factor authentication code.'));
            } elseif (!$tfa_model->verify_code_for_tfa_data($tfa_arr, $tfa_code)) {
                PHS_Notifications::add_warning_notice($this->_pt('Invalid verification code. Please try again.'));
            } elseif (!$tfa_model->cancel_tfa_setup($tfa_arr)) {
                PHS_Notifications::add_error_notice($this->_pt('Error cancelling two factor authentication for your account. Please try again'));
            } else {
                return action_redirect(['p' => 'accounts', 'ad' => 'tfa', 'a' => 'settings'], ['tfa_cancelled' => 1]);
            }
        }

        $data = [
            'nick'     => $current_user['nick'],
            'tfa_data' => $tfa_arr,

            'libs_plugin'     => $libs_plugin,
            'tfa_model'       => $tfa_model,
            'accounts_plugin' => $accounts_plugin,
        ];

        return $this->quick_render_template('tfa/settings', $data);
    }
}
