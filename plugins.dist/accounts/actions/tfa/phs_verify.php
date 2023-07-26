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

class PHS_Action_Verify extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_TFA_VERIFY, ];
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
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::TFA_VERIFY, $this))
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

        PHS::page_settings('page_title', $this->_pt('Two Factor Authentication Verification'));
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
        if (!($tfa_model = PHS_Model_Accounts_tfa::get_instance())
            || !($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || !($libs_plugin = PHS_Plugin_Phs_libs::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if( !($back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML)) ) {
            $back_page = '';
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $tfa_code = PHS_Params::_p('tfa_code', PHS_Params::T_ASIS);

        $do_submit = PHS_Params::_p('do_submit');
        $do_check_recovery = PHS_Params::_p('do_check_recovery');

        if (!($tfa_details = $tfa_model->get_tfa_data_for_account($current_user))) {
            $tfa_details = null;
        }

        $tfa_arr = $tfa_details['tfa_data'] ?? null;

        if( empty( $tfa_arr )
         || !$tfa_model->is_setup_completed($tfa_arr) ) {
            $args = [];
            if( !empty( $back_page ) ) {
                $args['back_page'] = $back_page;
            }

            return action_redirect(['p' => 'accounts', 'a' => 'setup', 'ad' => 'tfa'], $args);
        }

        $code_is_valid = false;
        if (!empty($do_submit)) {
            if (empty($tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide a verification code.'));
            } elseif (!$tfa_model->verify_code_for_tfa_data($tfa_arr, $tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Two factor authentication verification failed. Please try again.'));
            } else {
                $code_is_valid = true;
            }
        }

        if (!empty($do_check_recovery)) {
            if (empty($tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Please provide a verification code.'));
            } elseif (!$tfa_model->verify_recovery_code_for_tfa_data($tfa_arr, $tfa_code)) {
                PHS_Notifications::add_error_notice($this->_pt('Two factor authentication verification failed. Please try again.'));
            } else {
                $code_is_valid = true;
            }
        }

        if ($code_is_valid) {
            if (!$tfa_model->validate_tfa_for_session()) {
                PHS_Notifications::add_error_notice($this->_pt('Error updating session details. Please try again.'));
            } else {
                PHS_Notifications::add_success_notice($this->_pt('Two factor authentication verification passed.'));

                return action_redirect(!empty($back_page) ? from_safe_url($back_page) : PHS::url());
            }
        }

        $data = [
            'back_page'        => $back_page,
            'nick'        => $current_user['nick'],
            'tfa_data'    => $tfa_arr,

            'libs_plugin' => $libs_plugin,
            'tfa_model'   => $tfa_model,
            'accounts_plugin'   => $accounts_plugin,
        ];

        return $this->quick_render_template('tfa/verify', $data);
    }
}
