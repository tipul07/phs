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
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
        if (!($tfa_model = PHS_Model_Accounts_tfa::get_instance())
            || !($libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $tfa_code = PHS_Params::_p('tfa_code', PHS_Params::T_ASIS);

        $do_submit = PHS_Params::_p('do_submit');

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

        if (!empty($do_submit)) {
            if (empty($tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide a verification code.'));
            } elseif (!$tfa_model->verify_code_for_tfa_data($tfa_arr, $tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Two factor authentication verification failed. Please try again.'));
            } elseif (!($new_tfa_arr = $tfa_model->finish_tfa_setup($tfa_arr))) {
                PHS_Notifications::add_error_notice($this->_pt('Error finializing two factor authentication setup. Please try again.'));
            } else {
                PHS_Notifications::add_success_notice($this->_pt('Two factor authentication setup with success.'));

                $tfa_arr = $new_tfa_arr;
            }
        }

        $data = [
            'nick'        => $current_user['nick'],
            'qr_code_url' => $qr_code_url,
            'tfa_data'    => $tfa_arr,

            'libs_plugin' => $libs_plugin,
            'tfa_model'   => $tfa_model,
        ];

        return $this->quick_render_template('tfa/setup', $data);
    }
}
