<?php
namespace phs\system\core\scopes;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\PHS_Api_remote;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Notifications;

class PHS_Scope_Remote extends PHS_Scope
{
    public function get_scope_type() : int
    {
        return self::SCOPE_REMOTE;
    }

    public function process_action_result($action_result, $static_error_arr = false)
    {
        // We have already an error from flow before initiating scope class
        if (!empty($static_error_arr)
            && self::arr_has_error($static_error_arr)) {
            PHS_Api_remote::http_header_response(
                PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR,
                self::arr_get_simple_error_message($static_error_arr));
            exit;
        }

        if (!($api_obj = PHS_Api_remote::api_factory())) {
            $api_obj = null;
        }

        $action_result = PHS_Action::validate_action_result($action_result);

        // send custom headers as we will echo page content here...
        $api_flow_arr = [];
        if (!empty($api_obj)) {
            if (!($api_headers = $api_obj->response_headers(true))
                || !is_array($api_headers)) {
                $api_headers = [];
            }

            $api_flow_arr = $api_obj->api_flow_value();

            if (!empty($action_result['request_login'])
                && !$api_obj->api_user_account_id()) {
                PHS_Api_remote::http_header_response(PHS_Api_base::H_CODE_UNAUTHORIZED);
                exit;
            }
        } else {
            $api_headers = [];
        }

        if (!empty($action_result['custom_headers']) && is_array($action_result['custom_headers'])) {
            foreach ($action_result['custom_headers'] as $key => $val) {
                if (empty($key)) {
                    continue;
                }

                $api_headers[$key] = $val;
            }
        }

        $api_headers['X-Powered-By'] = 'PHS-'.PHS_VERSION;

        $api_headers = self::unify_array_insensitive($api_headers, ['trim_keys' => true]);

        if (empty($api_obj)) {
            $lowercase_api_headers = self::array_lowercase_keys($api_headers);
        } else {
            $api_obj->set_response_headers($api_headers, false);

            if (!($lowercase_api_headers = $api_obj->response_headers(false))) {
                $lowercase_api_headers = [];
            }
        }

        if (!@headers_sent()) {
            if (!empty($api_headers)) {
                foreach ($api_headers as $key => $val) {
                    $header_str = $key;
                    if (null !== $val) {
                        $header_str .= ': '.$val;
                    }

                    @header($header_str);
                }
            }

            // If we don't have a Content-Type header set, just set is as application/json (default API response)
            if (empty($lowercase_api_headers['content-type'])) {
                $api_headers['Content-Type'] = 'application/json';

                @header('Content-Type: application/json');

                if (empty($api_obj)) {
                    $lowercase_api_headers = self::array_lowercase_keys($api_headers);
                } else {
                    $api_obj->set_response_headers($api_headers, false);

                    if (!($lowercase_api_headers = $api_obj->response_headers(false))) {
                        $lowercase_api_headers = [];
                    }
                }
            }
        }

        if (!isset($action_result['api_buffer']) || $action_result['api_buffer'] === '') {
            $json_array = [];
            // Check for specific API reponse
            if (is_array($action_result['api_json_result_array'])) {
                $json_array = $action_result['api_json_result_array'];
            }
            // CHeck if we have an AJAX response to convert it in API response
            elseif (is_array($action_result['ajax_result'])) {
                $json_array = $action_result['ajax_result'];
            }

            $errors_arr = [];
            if (PHS_Notifications::have_notifications_errors()) {
                $errors_arr = PHS_Notifications::notifications_errors();
            }

            if (($new_json_response = $api_obj->create_response_envelope($json_array, $errors_arr))) {
                $new_json_response['timezone'] = (int)date('Z');
                $json_array = $new_json_response;
            }

            // we assume Content-Type header was set by action
            $action_result['api_buffer'] = @json_encode($json_array);
        }

        if ($action_result['api_buffer'] !== '') {
            echo $action_result['api_buffer'];
        }

        return $action_result;
    }
}
