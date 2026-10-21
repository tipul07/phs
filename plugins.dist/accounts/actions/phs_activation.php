<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Activation extends PHS_Action
{
    #[PHS_Dependency]
    private ?PHS_Plugin_Accounts $_accounts_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Model_Accounts $_accounts_model = null;

    /**
     * @inheritdoc
     */
    public function action_roles(): array
    {
        return [self::ACT_ROLE_ACTIVATION];
    }

    /**
     * @inheritdoc
     */
    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Account Activation'));

        $confirmation_param = PHS_Params::_gp($this->_accounts_plugin::PARAM_CONFIRMATION, PHS_Params::T_NOHTML) ?: '';

        if (!($confirmation_parts = $this->_accounts_plugin->decode_confirmation_param($confirmation_param))) {
            PHS_Notifications::add_error_notice(
                $this->_accounts_plugin->get_simple_error_message(
                    $this->_pt('Couldn\'t interpret confirmation parameter. Please try again.')),
            );
        }

        // Reset error for do_confirmation_reason() method call...
        $this->_accounts_plugin->reset_error();

        $will_send_email_confirmation = false;
        if (!empty($confirmation_parts['reason'])
            && !empty($confirmation_parts['account_data'])
            && $confirmation_parts['reason'] === $this->_accounts_plugin::CONF_REASON_ACTIVATION
            && $this->_accounts_model->needs_activation($confirmation_parts['account_data'])
            && $this->_accounts_model->needs_confirmation_email($confirmation_parts['account_data'])) {
            $will_send_email_confirmation = true;
        }

        if (!empty($confirmation_parts['account_data'])
            && !empty($confirmation_parts['reason'])
            && !PHS_Notifications::have_notifications_errors()
            && ($confirmation_result = $this->_accounts_plugin->do_confirmation_reason($confirmation_parts['account_data'], $confirmation_parts['reason']))) {
            PHS_Notifications::add_success_notice($this->_pt('Action Confirmed...'));

            $url_params = [];
            $url_params['reason'] = $confirmation_parts['reason'];
            if (!empty($will_send_email_confirmation)) {
                $url_params['confirmation_email'] = 1;
            }

            if (!empty($confirmation_result['redirect_url'])) {
                $redirect_to_url = $confirmation_result['redirect_url'];
            } elseif ($confirmation_parts['reason'] === $this->_accounts_plugin::CONF_REASON_ACTIVATION
                      || !PHS::user_logged_in()) {
                $redirect_to_url = PHS::url(['p' => 'accounts', 'a' => 'login'], $url_params);
            } else {
                $redirect_to_url = PHS::url(['p' => 'accounts', 'a' => 'edit_profile'], $url_params);
            }

            return action_redirect($redirect_to_url);
        }

        if ($this->_accounts_plugin->has_error()) {
            PHS_Notifications::add_error_notice($this->_accounts_plugin->get_simple_error_message());
        }

        return $this->quick_render_template('activation', [
            'nick'                         => $confirmation_parts['account_data']['nick'] ?? '',
            'will_send_email_confirmation' => $will_send_email_confirmation,
        ]);
    }
}
