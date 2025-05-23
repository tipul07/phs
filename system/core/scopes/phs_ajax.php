<?php
namespace phs\system\core\scopes;

use phs\PHS;
use phs\PHS_Ajax;
use phs\PHS_Scope;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\phs_security\PHS_Plugin_Phs_security;
use phs\plugins\phs_security\libraries\Phs_security_headers;

class PHS_Scope_Ajax extends PHS_Scope
{
    public function get_scope_type() : int
    {
        return self::SCOPE_AJAX;
    }

    /**
     * @inheritdoc
     */
    public function process_action_result($action_result, $static_error_arr = false)
    {
        $action_result = PHS_Action::validate_action_result($action_result);

        $full_buffer = PHS_Params::_gp(PHS_Ajax::PARAM_FB_KEY, PHS_Params::T_INT);

        if (!isset($action_result['buffer'])) {
            $action_result['buffer'] = '';
        }
        if (!isset($action_result['ajax_result'])) {
            $action_result['ajax_result'] = false;
        }

        if (!empty($action_result['request_login'])) {
            $args = [];
            if (!empty($action_result['redirect_to_url'])) {
                $args['back_page'] = $action_result['redirect_to_url'];
            } else { // we cannot redirect user to same page as we are in an AJAX request...
                $args['back_page'] = PHS::url();
            }

            $action_result['redirect_to_url'] = PHS::url(['p' => 'accounts', 'a' => 'login'], $args);
        }

        // send custom headers as we will echo page content here...
        if (!@headers_sent()) {
            $result_headers = [];
            if (!empty($action_result['custom_headers']) && is_array($action_result['custom_headers'])) {
                foreach ($action_result['custom_headers'] as $key => $val) {
                    if (empty($key)) {
                        continue;
                    }

                    if ($val !== null) {
                        $result_headers[$key] = $val;
                    } else {
                        $result_headers[$key] = '';
                    }
                }
            }

            $result_headers['X-Powered-By'] = 'PHS-'.PHS_VERSION;

            if (($security_plugin = PHS_Plugin_Phs_security::get_instance())
               && $security_plugin->security_headers_are_enabled()
               && ($headers_lib = Phs_security_headers::get_instance())
               && ($headers_arr = $headers_lib->get_security_headers_for_response())) {
                $result_headers = self::merge_array_assoc($result_headers, $headers_arr);
            }

            $result_headers = self::unify_array_insensitive($result_headers, ['trim_keys' => true]);

            foreach ($result_headers as $key => $val) {
                if ($val === '') {
                    @header($key);
                } else {
                    @header($key.': '.$val);
                }
            }
        }

        if ($action_result['buffer'] !== '') {
            @header('Content-Type: text/html');
            echo $action_result['buffer'];
        } else {
            if (!empty($action_result['ajax_only_result'])) {
                $ajax_data = $action_result['ajax_result'];
            } else {
                $ajax_data = [];
                $ajax_data['status'] = [
                    'success_messages' => PHS_Notifications::notifications_success(),
                    'warning_messages' => PHS_Notifications::notifications_warnings(),
                    'error_messages'   => PHS_Notifications::notifications_errors(),
                ];

                if (!empty($full_buffer)
                    && PHS_Notifications::have_any_notifications()) {
                    $hook_args = PHS_Hooks::default_notifications_hook_args();
                    $hook_args['output_ajax_placeholders'] = false;

                    if (($hook_args = PHS::trigger_hooks(PHS_Hooks::H_NOTIFICATIONS_DISPLAY, $hook_args))
                     && is_array($hook_args)
                     && !empty($hook_args['notifications_buffer'])) {
                        $action_result['ajax_result'] = $hook_args['notifications_buffer'].$action_result['ajax_result'];
                    }
                }

                $ajax_data['response'] = $action_result['ajax_result'];
                $ajax_data['redirect_to_url'] = (!empty($action_result['redirect_to_url']) ? $action_result['redirect_to_url'] : '');
                $ajax_data['request_login'] = (!empty($action_result['request_login']));
            }

            if ($full_buffer) {
                @header('Content-Type: text/html');
                if (is_string($ajax_data)) {
                    echo $ajax_data;
                } elseif (!empty($action_result['ajax_result'])
                          && is_string($action_result['ajax_result'])) {
                    echo $action_result['ajax_result'];
                }
            } else {
                @header('Content-Type: application/json');
                echo @json_encode($ajax_data);
            }
        }

        return true;
    }
}
