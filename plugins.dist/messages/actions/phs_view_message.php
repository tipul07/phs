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

class PHS_Action_View_message extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('View Message'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!($messages_plugin = PHS_Plugin_Messages::get_instance())
            || !($messages_model = PHS_Model_Messages::get_instance())
            || !($m_flow_params = $messages_model->fetch_default_flow_params(['table_name' => 'messages']))
            || !($mu_flow_params = $messages_model->fetch_default_flow_params(['table_name' => 'messages_users']))
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !($roles_model = PHS_Model_Roles::get_instance())
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load messages plugin.'));

            return self::default_action_result();
        }

        if (!can($messages_plugin::ROLEU_READ_MESSAGE)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $mid = PHS_Params::_g('mid', PHS_Params::T_INT);
        $muid = PHS_Params::_g('muid', PHS_Params::T_INT);

        $user_message = null;
        if ($mid && !$muid
        && ($top_message_arr = $messages_model->get_details($mid, $m_flow_params))) {
            $params_arr = [];
            $params_arr['order_by'] = ' (user_id = \''.$current_user['id'].'\') DESC, cdate DESC';

            if (!($user_message = $messages_model->get_details_fields(['message_id' => $mid], $params_arr))
                || empty($user_message['message_id'])) {
                $user_message = null;
            }
        }

        if (!$user_message
            && (!$muid
                || !($user_message = $messages_model->data_to_array($muid, $mu_flow_params))
                || empty($user_message['message_id']))
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load message details.'));

            return action_redirect(['p' => 'messages', 'a' => 'inbox'], ['unknown_message' => 1]);
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

        if ((int)($message_arr['message']['thread_id'] ?? 0) === (int)($message_arr['message']['id'] ?? 0)) {
            $thread_arr = $message_arr;
        } elseif (!($thread_arr = $messages_model->full_data_to_array($message_arr['message']['thread_id']))) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load thread of message.'));

            return action_redirect(['p' => 'messages', 'a' => 'inbox'], ['unknown_thread' => 1]);
        }

        if (empty($message_arr['message']['thread_id'])
         || !($thread_messages_arr = $messages_model->get_thread_messages_flow($message_arr['message']['thread_id'], $current_user['id']))) {
            $thread_messages_arr = [];
        }

        $dest_types = $messages_model->get_dest_types_as_key_val() ?: [];
        $user_levels = $accounts_model->get_levels_as_key_val() ?: [];
        $roles_arr = $roles_model->get_all_roles() ?: [];
        $roles_units_arr = $roles_model->get_all_role_units() ?: [];

        $data = [
            'muid'        => $muid,
            'thread_arr'  => $thread_arr,
            'message_arr' => $message_arr,

            'thread_messages_arr' => $thread_messages_arr,

            'dest_types'      => $dest_types,
            'user_levels'     => $user_levels,
            'roles_arr'       => $roles_arr,
            'roles_units_arr' => $roles_units_arr,

            'messages_model'  => $messages_model,
            'accounts_model'  => $accounts_model,
            'roles_model'     => $roles_model,
            'messages_plugin' => $messages_plugin,
        ];

        return $this->quick_render_template('view_message', $data);
    }
}
