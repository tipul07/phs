<?php
namespace phs\system\core\scopes;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\system\core\views\PHS_View;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\events\layout\PHS_Event_Template;
use phs\plugins\accounts\models\PHS_Model_Accounts_tfa;

class PHS_Scope_Web extends PHS_Scope
{
    public function get_scope_type() : int
    {
        return self::SCOPE_WEB;
    }

    public function process_action_result($action_result, $static_error_arr = false)
    {
        /** @var \phs\libraries\PHS_Action $action_obj */
        if (!($action_obj = PHS::running_action())) {
            $action_obj = false;
        }
        /** @var \phs\libraries\PHS_Controller $controller_obj */
        if (!($controller_obj = PHS::running_controller())) {
            $controller_obj = false;
        }

        $action_result = PHS_Action::validate_action_result($action_result);

        // TFA preceeds password expiration...
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if ($this->_should_redirect_to_tfa_flow()
            && (empty($action_obj)
                || !$action_obj->action_role_is([$action_obj::ACT_ROLE_TFA_SETUP, $action_obj::ACT_ROLE_TFA_VERIFY, ]))
        ) {
            $args = [];
            if (!empty($action_result['redirect_to_url'])) {
                $args['back_page'] = $action_result['redirect_to_url'];
            } else {
                $args['back_page'] = PHS::current_url();
            }

            if ($this->_should_setup_tfa_for_account()) {
                $action_result['redirect_to_url'] = PHS::url(['p' => 'accounts', 'a' => 'setup', 'ad' => 'tfa'], $args);
            } else {
                $action_result['redirect_to_url'] = PHS::url(['p' => 'accounts', 'a' => 'verify', 'ad' => 'tfa'], $args);
            }
        } elseif (($expiration_arr = $this->_password_expired_for_current_account())) {
            if (!empty($action_obj)
                && $action_obj->action_role_is([$action_obj::ACT_ROLE_CHANGE_PASSWORD, $action_obj::ACT_ROLE_LOGIN,
                    $action_obj::ACT_ROLE_LOGOUT, $action_obj::ACT_ROLE_PASSWORD_EXPIRED, ])
            ) {
                $in_special_page = true;
            } else {
                $in_special_page = false;
            }

            if (!$in_special_page) {
                PHS_Notifications::add_warning_notice(
                    $this->_pt('Your password expired %s ago. For security reasons, please <a href="%s">change your password</a>.',
                        PHS_Utils::parse_period($expiration_arr['expired_for_seconds']),
                        PHS::url(['p' => 'accounts', 'a' => 'change_password'], ['password_expired' => 1]))
                );
            }

            if (empty($expiration_arr['show_only_warning'])
             && !empty($action_obj)
             && !$in_special_page) {
                $args = [];
                $args['password_expired'] = 1;

                $action_result['redirect_to_url'] = PHS::url(['p' => 'accounts', 'a' => 'change_password'], $args);
            }
        }

        if (!empty($action_result['request_login'])) {
            $args = [];
            if (!empty($action_result['redirect_to_url'])) {
                $args['back_page'] = $action_result['redirect_to_url'];
            } else {
                $args['back_page'] = PHS::current_url();
            }

            $action_result['redirect_to_url'] = PHS::url(['p' => 'accounts', 'a' => 'login'], $args);
        }

        if (!empty($action_result['redirect_to_url'])
         && !@headers_sent()) {
            @header('Location: '.$action_result['redirect_to_url']);
            exit;
        }

        $this->_update_tfa_device_cookie_if_required();

        if (($event_result = PHS_Event_Template::template(PHS_Event_Template::GENERIC, $action_result['page_template'], $action_result['action_data']))) {
            if (!empty($event_result['page_template'])) {
                $action_result['page_template'] = $event_result['page_template'];
            }
            if (!empty($event_result['page_template_args'])) {
                $action_result['action_data'] = $event_result['page_template_args'];
            }
        }

        if (empty($action_obj)
         && empty($action_result['page_template'])) {
            echo 'No running action to render page template.';
            exit;
        }

        // send custom headers as we will echo page content here...
        if (!@headers_sent()) {
            $result_headers = [];
            if (!empty($action_result['custom_headers']) && is_array($action_result['custom_headers'])) {
                foreach ($action_result['custom_headers'] as $key => $val) {
                    if (empty($key)) {
                        continue;
                    }

                    if (null !== $val) {
                        $result_headers[$key] = $val;
                    } else {
                        $result_headers[$key] = '';
                    }
                }
            }

            $result_headers['X-Powered-By'] = 'PHS-'.PHS_VERSION;

            $result_headers = self::unify_array_insensitive($result_headers, ['trim_keys' => true]);

            foreach ($result_headers as $key => $val) {
                if ($val === '') {
                    @header($key);
                } else {
                    @header($key.': '.$val);
                }
            }
        }

        if (self::arr_has_error($static_error_arr)) {
            echo self::arr_get_simple_error_message($static_error_arr);
        } elseif (empty($action_obj)
                  || empty($action_result['page_template'])) {
            echo $action_result['buffer'];
        } else {
            $view_params = [];
            $view_params['action_obj'] = $action_obj;
            $view_params['controller_obj'] = $controller_obj;
            $view_params['parent_plugin_obj'] = $action_obj->get_plugin_instance();
            $view_params['plugin'] = $action_obj->instance_plugin_name();
            $view_params['template_data'] = (!empty($action_result['action_data']) ? $action_result['action_data'] : false);
            $view_params['as_singleton'] = false;

            if (!($view_obj = PHS_View::init_view($action_result['page_template'], $view_params))) {
                if (self::st_has_error()) {
                    echo self::st_get_error_message();
                } else {
                    echo self::_t('Error instantiating view object.');
                }

                exit;
            }

            $action_result['page_settings']['page_title'] ??= '';
            $action_result['page_settings']['page_title'] .= ($action_result['page_settings']['page_title'] !== '' ? ' - ' : '').PHS_SITE_NAME;

            if (($result_buffer = $view_obj->render()) === false) {
                if ($view_obj->has_error()) {
                    $error_msg = $view_obj->get_error_message();
                } else {
                    if (!is_string($action_result['page_template'])) {
                        ob_start();
                        var_dump($action_result['page_template']);
                        $template_str = ob_get_clean();
                    } else {
                        $template_str = $action_result['page_template'];
                    }

                    $error_msg = 'Error rendering action result in template ['.$template_str.']';
                }

                PHS_Logger::error($error_msg, PHS_Logger::TYPE_DEBUG);

                echo self::_t('Error rendering page template.');
                exit;
            }

            echo $result_buffer;
        }

        return true;
    }

    private function _password_expired_for_current_account() : ?array
    {
        if (!($expiration_arr = PHS::current_user_password_expiration())
            || empty($expiration_arr['is_expired'])) {
            return null;
        }

        return $expiration_arr;
    }

    private function _update_tfa_device_cookie_if_required() : void
    {
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
            || $accounts_plugin->tfa_policy_is_off()
            || !$accounts_plugin->tfa_remember_device_length()
            || !($tfa_model = PHS_Model_Accounts_tfa::get_instance())
            || !PHS::user_logged_in()
            || !$tfa_model->is_device_tfa_valid()
        ) {
            return;
        }

        $tfa_model->mark_device_as_tfa_valid();
    }

    private function _should_redirect_to_tfa_flow() : bool
    {
        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        return ($accounts_plugin = PHS_Plugin_Accounts::get_instance())
               && !$accounts_plugin->tfa_policy_is_off()
               && ($tfa_model = PHS_Model_Accounts_tfa::get_instance())
               && ($current_user = PHS::user_logged_in())
               && !$tfa_model->is_device_tfa_valid()
               && !$tfa_model->is_session_tfa_valid()
               && (
                   (($tfa_arr = $tfa_model->get_tfa_data_for_account($current_user))
                    && !empty($tfa_arr['tfa_data']) && $tfa_model->is_setup_completed($tfa_arr['tfa_data']))
                   || ($accounts_plugin->tfa_policy_is_enforced()
                    && ($settings_arr = $accounts_plugin->get_plugin_settings())
                    && (empty($settings_arr['2fa_policy_account_level'])
                        || in_array((int)$current_user['level'], $settings_arr['2fa_policy_account_level'], true))
                   )
               );
    }

    private function _should_setup_tfa_for_account() : bool
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
        return ($tfa_model = PHS_Model_Accounts_tfa::get_instance())
               && PHS::current_user()
               && (!($tfa_arr = $tfa_model->get_tfa_for_current_account())
                   || !$tfa_model->is_setup_completed($tfa_arr));
    }
}
