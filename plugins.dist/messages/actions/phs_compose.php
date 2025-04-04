<?php
namespace phs\plugins\messages\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\messages\PHS_Plugin_Messages;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\messages\models\PHS_Model_Messages;

class PHS_Action_Compose extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Compose Message'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Messages $messages_plugin */
        /** @var PHS_Model_Messages $messages_model */
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Model_Roles $roles_model */
        if (!($messages_plugin = PHS_Plugin_Messages::get_instance())
            || !($messages_model = PHS_Model_Messages::get_instance())
            || !($mu_flow_params = $messages_model->fetch_default_flow_params(['table_name' => 'messages_users']))
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !($roles_model = PHS_Model_Roles::get_instance())
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        $reply_to = PHS_Params::_g('reply_to', PHS_Params::T_INT);
        $reply_to_muid = PHS_Params::_g('reply_to_muid', PHS_Params::T_INT);
        $follow_up = PHS_Params::_g('follow_up', PHS_Params::T_INT);
        $follow_up_muid = PHS_Params::_g('follow_up_muid', PHS_Params::T_INT);
        $reply_to_all = PHS_Params::_g('reply_to_all', PHS_Params::T_INT);

        if (PHS_Params::_g('message_queued', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Message queued... Server will send it as soon as possible.'));
        }

        if (empty($reply_to)
            || !($reply_message = $messages_model->full_data_to_array($reply_to, $current_user))) {
            $reply_message = null;
        }

        if (!empty($reply_to_muid) && empty($reply_message)) {
            if (!($reply_user_message = $messages_model->get_details($reply_to_muid, $mu_flow_params))
             || empty($reply_user_message['message_id'])) {
                $reply_message = null;
            } elseif (!($reply_message = $messages_model->full_data_to_array($reply_user_message['message_id'], $current_user))) {
                if (!can($messages_plugin::ROLEU_CAN_REPLY_TO_ALL)
                 || !($reply_message = $messages_model->full_data_to_array($reply_user_message['message_id'], $current_user, ['ignore_user_message' => true]))) {
                    $reply_message = null;
                }
            }

            if (!empty($reply_message)
            && !empty($reply_user_message)
            && empty($reply_message['message_user'])) {
                $reply_message['message_user'] = $reply_user_message;
            }
        }

        if (!empty($reply_message)
        && !can($messages_plugin::ROLEU_REPLY_MESSAGE)
        && !$messages_model->can_reply($reply_message, ['account_data' => $current_user])) {
            PHS_Notifications::add_error_notice($this->_pt('Unknown message or you don\'t have rights to reply to this message.'));

            $reply_message = null;
        }

        if (empty($follow_up)
         || !($followup_message = $messages_model->full_data_to_array($follow_up, $current_user))) {
            $followup_message = null;
        }

        if (!empty($follow_up_muid) && empty($followup_message)) {
            if (!($followup_user_message = $messages_model->get_details($follow_up_muid, $mu_flow_params))
             || empty($followup_user_message['message_id'])) {
                $followup_message = null;
            } elseif (!($followup_message = $messages_model->full_data_to_array($followup_user_message['message_id'], $current_user))) {
                if (!can($messages_plugin::ROLEU_CAN_REPLY_TO_ALL)
                 || !($followup_message = $messages_model->full_data_to_array($followup_user_message['message_id'], $current_user, ['ignore_user_message' => true]))) {
                    $followup_message = null;
                }
            }

            if (!empty($followup_message)
            && !empty($followup_user_message)
            && empty($followup_message['message_user'])) {
                $followup_message['message_user'] = $followup_user_message;
            }
        }

        if (!empty($followup_message)
        && !can($messages_plugin::ROLEU_FOLLOWUP_MESSAGE)
        && !$messages_model->can_followup($followup_message, ['account_data' => $current_user])) {
            PHS_Notifications::add_error_notice($this->_pt('Unknown message or you don\'t have rights to follow up this message.'));

            $followup_message = null;
        }

        if (empty($reply_message)
        && empty($followup_message)
        && !can($messages_plugin::ROLEU_WRITE_MESSAGE)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $dest_types = $messages_model->get_dest_types_as_key_val() ?: [];
        $user_levels = $accounts_model->get_levels_as_key_val() ?: [];
        $roles_arr = $roles_model->get_all_roles() ?: [];
        $roles_units_arr = $roles_model->get_all_role_units() ?: [];

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $subject = PHS_Params::_pg('subject', PHS_Params::T_NOHTML);
        $dest_type = PHS_Params::_pg('dest_type', PHS_Params::T_INT);
        $dest_type_users_ids = PHS_Params::_pg('dest_type_users_ids', PHS_Params::T_NOHTML);
        $dest_type_users = PHS_Params::_pg('dest_type_users', PHS_Params::T_NOHTML);
        $dest_type_handlers = PHS_Params::_pg('dest_type_handlers', PHS_Params::T_NOHTML);
        $dest_type_level = PHS_Params::_pg('dest_type_level', PHS_Params::T_INT);
        $dest_type_role = PHS_Params::_pg('dest_type_role', PHS_Params::T_INT);
        $dest_type_role_unit = PHS_Params::_pg('dest_type_role_unit', PHS_Params::T_INT);
        $body = PHS_Params::_pg('body', PHS_Params::T_NOHTML);
        $cannot_reply = PHS_Params::_pg('cannot_reply', PHS_Params::T_INT);
        $do_submit = PHS_Params::_p('do_submit');

        $msg_type = false;
        $msg_type_id = false;
        if (can($messages_plugin::ROLEU_SET_TYPE_IN_COMPOSE)) {
            $msg_type = PHS_Params::_gp('msg_type', PHS_Params::T_NOHTML);
            $msg_type_id = PHS_Params::_gp('msg_type_id', PHS_Params::T_INT);

            if (!$messages_model->valid_type($msg_type)) {
                $msg_type = false;
                $msg_type_id = false;
            }
        }

        if (!can($messages_plugin::ROLEU_NO_REPLY_OPTION)) {
            $cannot_reply = 0;
        }

        if (!($can_write_to_all = can($messages_plugin::ROLEU_ALL_DESTINATIONS))) {
            $can_write_to_all = false;
        }

        if (!$can_write_to_all) {
            $dest_type = $messages_model::DEST_TYPE_HANDLERS;
        }

        if (!empty($reply_message)) {
            if (empty($reply_message['message']['from_uid'])
             || !($reply_author_handler = $messages_model->get_account_message_handler($reply_message['message']['from_uid']))) {
                PHS_Notifications::add_warning_notice($this->_pt('You cannot reply to this message. Author not reachable.'));
            } else {
                if (empty($foobar)
                 || !$can_write_to_all) {
                    $dest_type = $messages_model::DEST_TYPE_HANDLERS;
                    $dest_type_handlers = $reply_author_handler;

                    $appending_handlers = '';
                    if (!empty($reply_to_all)
                    && $reply_message['message']['dest_type'] === $messages_model::DEST_TYPE_HANDLERS
                    && ($handlers_parts = self::extract_strings_from_comma_separated($reply_message['message']['dest_str'], ['trim_parts' => true]))
                    && ($current_user_handler = $messages_model->get_account_message_handler($current_user))) {
                        $current_user_handler_lower = strtolower(trim($current_user_handler));
                        foreach ($handlers_parts as $user_handle) {
                            if ($current_user_handler_lower === strtolower($user_handle)) {
                                continue;
                            }

                            $appending_handlers .= ($appending_handlers !== '' ? ', ' : '').$user_handle;
                        }
                    }

                    if ($appending_handlers !== '') {
                        $dest_type_handlers .= ', '.$appending_handlers;
                    }
                }

                $subject = $reply_message['message']['subject'];

                $re_str = $this->_pt('Re: ');
                if (stripos($subject, strtolower($re_str)) !== 0) {
                    $subject = $re_str.$subject;
                }
            }
        }

        if (!empty($followup_message)) {
            if (empty($followup_message['message']['from_uid'])) {
                PHS_Notifications::add_warning_notice($this->_pt('You cannot follow up this message.'));
            } else {
                if (empty($foobar)
                 || !$can_write_to_all) {
                    $dest_type = $followup_message['message']['dest_type'];

                    switch ($dest_type) {
                        case $messages_model::DEST_TYPE_USERS_IDS:
                            $dest_type_users_ids = $followup_message['message']['dest_str'];
                            break;
                        case $messages_model::DEST_TYPE_USERS:
                            $dest_type_users = $followup_message['message']['dest_str'];
                            break;
                        case $messages_model::DEST_TYPE_HANDLERS:
                            $dest_type_handlers = $followup_message['message']['dest_str'];
                            break;
                        case $messages_model::DEST_TYPE_LEVEL:
                            $dest_type_level = $followup_message['message']['dest_id'];
                            break;
                        case $messages_model::DEST_TYPE_ROLE:
                            $dest_type_role = $followup_message['message']['dest_id'];
                            break;
                        case $messages_model::DEST_TYPE_ROLE_UNIT:
                            $dest_type_role_unit = $followup_message['message']['dest_id'];
                            break;
                    }
                }

                $subject = $followup_message['message']['subject'];

                $re_str = $this->_pt('Re: ');
                if (stripos($subject, strtolower($re_str)) !== 0) {
                    $subject = $re_str.$subject;
                }
            }
        }

        if (empty($foobar)) {
            if (empty($dest_type)) {
                $dest_type = $messages_model::DEST_TYPE_HANDLERS;
            }
        }

        if (!empty($do_submit)) {
            $message_params = [];
            $message_params['reply_message'] = $reply_message;
            $message_params['followup_message'] = $followup_message;
            $message_params['account_data'] = $current_user;
            $message_params['subject'] = $subject;
            $message_params['body'] = $body;
            $message_params['dest_type'] = $dest_type;
            $message_params['dest_type_users_ids'] = $dest_type_users_ids;
            $message_params['dest_type_users'] = $dest_type_users;
            $message_params['dest_type_handlers'] = $dest_type_handlers;
            $message_params['dest_type_level'] = $dest_type_level;
            $message_params['dest_type_role'] = $dest_type_role;
            $message_params['dest_type_role_unit'] = $dest_type_role_unit;
            $message_params['can_reply'] = ($cannot_reply ? 0 : 1);
            if ($msg_type !== false
            && can($messages_plugin::ROLEU_SET_TYPE_IN_COMPOSE)) {
                $message_params['type'] = $msg_type;
                $message_params['type_id'] = $msg_type_id;
            }

            if (($new_message = $messages_model->write_message($message_params))) {
                PHS_Notifications::add_success_notice($this->_pt('Message details saved in database.'));

                if ($this->is_admin_controller()) {
                    $redirect_path = ['p' => 'messages', 'c' => 'admin', 'a' => 'compose'];
                } else {
                    $redirect_path = ['p' => 'messages', 'a' => 'compose'];
                }

                return action_redirect($redirect_path, ['mid' => $new_message['message']['id'], 'message_queued' => 1]);
            }

            if ($messages_model->has_error()) {
                PHS_Notifications::add_error_notice($messages_model->get_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error sending message. Please try again.'));
            }
        }

        $data = [
            'reply_message' => $reply_message,
            'reply_to'      => $reply_to,
            'reply_to_muid' => $reply_to_muid,

            'followup_message' => $followup_message,
            'follow_up'        => $follow_up,
            'follow_up_muid'   => $follow_up_muid,

            'msg_type'    => $msg_type,
            'msg_type_id' => $msg_type_id,

            'subject'             => $subject,
            'dest_type'           => $dest_type,
            'dest_type_users'     => $dest_type_users,
            'dest_type_handlers'  => $dest_type_handlers,
            'dest_type_level'     => $dest_type_level,
            'dest_type_role'      => $dest_type_role,
            'dest_type_role_unit' => $dest_type_role_unit,
            'cannot_reply'        => $cannot_reply,
            'body'                => $body,

            'dest_types'      => $dest_types,
            'user_levels'     => $user_levels,
            'roles_arr'       => $roles_arr,
            'roles_units_arr' => $roles_units_arr,

            'messages_model'  => $messages_model,
            'accounts_model'  => $accounts_model,
            'roles_model'     => $roles_model,
            'messages_plugin' => $messages_plugin,
        ];

        return $this->quick_render_template('compose', $data);
    }
}
