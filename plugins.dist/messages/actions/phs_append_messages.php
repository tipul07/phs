<?php
namespace phs\plugins\messages\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\messages\PHS_Plugin_Messages;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\messages\models\PHS_Model_Messages;

class PHS_Action_Append_messages extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!($messages_plugin = PHS_Plugin_Messages::get_instance())
            || !($messages_model = PHS_Model_Messages::get_instance())
            || !($mu_flow_params = $messages_model->fetch_default_flow_params(['table_name' => 'messages_users']))
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !($roles_model = PHS_Model_Roles::get_instance())
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!can($messages_plugin::ROLEU_READ_MESSAGE)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $muid = PHS_Params::_p('muid', PHS_Params::T_INT);
        $max_messages = PHS_Params::_p('max_messages', PHS_Params::T_INT);
        $offset = PHS_Params::_p('offset', PHS_Params::T_INT);
        $location = PHS_Params::_p('location', PHS_Params::T_NOHTML) ?: '';

        if (!$location
            || !in_array($location, ['before', 'after'], true)) {
            $location = 'after';
        }

        if (empty($max_messages)) {
            $max_messages = 5;
        } elseif ($max_messages === -1) {
            $max_messages = 99999999;
        }

        if (!$muid
            || !($user_message = $messages_model->get_details($muid, $mu_flow_params))
            || empty($user_message['message_id'])) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load message details.'));

            return self::default_action_result();
        }

        if (!($message_arr = $messages_model->full_data_to_array($user_message['message_id'], $current_user))) {
            if (!can($messages_plugin::ROLEU_VIEW_ALL_MESSAGES)
                || !($message_arr = $messages_model->full_data_to_array($user_message['message_id'], $current_user, ['ignore_user_message' => true]))) {
                PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load message details.'));

                return action_redirect(['p' => 'messages', 'a' => 'inbox'], ['unknown_message' => 1]);
            }

            if (empty($message_arr['message_user'])) {
                $message_arr['message_user'] = $user_message;
            }
        }

        if (empty($message_arr['message']['thread_id'])
            || !($thread_messages_arr = $messages_model->get_thread_messages_flow($message_arr['message']['thread_id'], $current_user['id']))) {
            $thread_messages_arr = [];
        }

        $ids_to_render = [];
        $messages_before = 0;
        $messages_after = 0;
        if (!empty($thread_messages_arr) && is_array($thread_messages_arr)) {
            $we_are_before = true;
            foreach ($thread_messages_arr as $um_id => $um_arr) {
                if ((int)($message_arr['message']['id'] ?? 0) === (int)($um_arr['message_id'] ?? 0)) {
                    $we_are_before = false;
                    continue;
                }

                if ($we_are_before) {
                    if ($location === 'before') {
                        $ids_to_render[] = $um_arr['message_id'];
                    }
                } elseif ($location === 'after') {
                    $ids_to_render[] = $um_arr['message_id'];
                }
            }
        }

        if (empty($ids_to_render) || !is_array($ids_to_render)) {
            $buffer = $this->_pt('No more messages...');
        } else {
            $user_levels = $accounts_model->get_levels_as_key_val() ?: [];
            $roles_arr = $roles_model->get_all_roles() ?: [];
            $roles_units_arr = $roles_model->get_all_role_units() ?: [];

            $buffer = '';
            foreach ($ids_to_render as $render_message_id) {
                if (!($message_arr = $messages_model->full_data_to_array($render_message_id, $current_user, ['ignore_user_message' => true]))) {
                    continue;
                }

                $data = [
                    'muid'        => $muid,
                    'message_arr' => $message_arr,

                    'thread_messages_arr' => $thread_messages_arr,

                    'user_levels'     => $user_levels,
                    'roles_arr'       => $roles_arr,
                    'roles_units_arr' => $roles_units_arr,

                    'messages_model'  => $messages_model,
                    'accounts_model'  => $accounts_model,
                    'roles_model'     => $roles_model,
                    'messages_plugin' => $messages_plugin,
                ];

                if (!($action_result = $this->quick_render_template('view_single_message', $data))
                    || empty($action_result['buffer'])) {
                    PHS_Notifications::add_error_notice($this->_pt('Error rendering messages. Please retry.'));

                    return self::default_action_result();
                }

                $buffer .= $action_result['buffer'];
            }
        }

        return $this->send_ajax_response($buffer);
    }
}
