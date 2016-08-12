<?php

namespace phs\plugins\messages\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Compose extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Compose Message' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ) );

            return $action_result;
        }

        /** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
        if( !($messages_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load messages plugin.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
        if( !($messages_model = PHS::load_model( 'messages', 'messages' ))
         or !($mu_flow_params = $messages_model->fetch_default_flow_params( array( 'table_name' => 'messages_users' ) )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load messages model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
        if( !($roles_model = PHS::load_model( 'roles' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load roles model.' ) );
            return self::default_action_result();
        }

        $reply_to = PHS_params::_g( 'reply_to', PHS_params::T_INT );
        $reply_to_muid = PHS_params::_g( 'reply_to_muid', PHS_params::T_INT );

        if( PHS_params::_g( 'message_queued', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Message queued... Server will send it as soon as possible.' ) );

        if( empty( $reply_to )
         or !($reply_message = $messages_model->full_data_to_array( $reply_to )) )
            $reply_message = false;

        if( !empty( $reply_to_muid ) and empty( $reply_message ) )
        {
            if( !($reply_user_message = $messages_model->data_to_array( $reply_to_muid, $mu_flow_params ))
             or empty( $reply_user_message['message_id'] )
             or !($reply_message = $messages_model->full_data_to_array( $reply_user_message['message_id'] )) )
                $reply_message = false;
        }

        if( !empty( $reply_message )
        and !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_REPLY_MESSAGE )
        and !$messages_model->can_reply( $reply_message, array( 'account_data' => $current_user ) ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Unknown message or you don\'t have rights to reply to this messages.' ) );

            $reply_message = false;
        }

        if( empty( $reply_message )
        and !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_WRITE_MESSAGE ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to compose messages.' ) );
            return self::default_action_result();
        }

        if( !($dest_types = $messages_model->get_dest_types_as_key_val()) )
            $dest_types = array();
        if( !($user_levels = $accounts_model->get_levels_as_key_val()) )
            $user_levels = array();
        if( !($roles_arr = $roles_model->get_all_roles()) )
            $roles_arr = array();
        if( !($roles_units_arr = $roles_model->get_all_role_units()) )
            $roles_units_arr = array();

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $subject = PHS_params::_p( 'subject', PHS_params::T_NOHTML );
        $dest_type = PHS_params::_p( 'dest_type', PHS_params::T_INT );
        $dest_type_users = PHS_params::_p( 'dest_type_users', PHS_params::T_NOHTML );
        $dest_type_handlers = PHS_params::_p( 'dest_type_handlers', PHS_params::T_NOHTML );
        $dest_type_level = PHS_params::_p( 'dest_type_level', PHS_params::T_INT );
        $dest_type_role = PHS_params::_p( 'dest_type_role', PHS_params::T_INT );
        $dest_type_role_unit = PHS_params::_p( 'dest_type_role_unit', PHS_params::T_INT );
        $body = PHS_params::_p( 'body', PHS_params::T_NOHTML );
        $cannot_reply = PHS_params::_p( 'cannot_reply', PHS_params::T_INT );
        $do_submit = PHS_params::_p( 'do_submit' );

        if( !$accounts_model->acc_is_admin( $current_user ) )
            $cannot_reply = 0;

        if( !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_ALL_DESTINATIONS ) )
            $dest_type = $messages_model::DEST_TYPE_HANDLERS;

        if( !empty( $reply_message ) )
        {
            if( empty( $reply_message['message']['from_uid'] )
             or !($reply_author_handler = $messages_model->get_account_message_handler( $reply_message['message']['from_uid'] )) )
                PHS_Notifications::add_warning_notice( $this->_pt( 'You cannot reply to this message. Author not reachable.' ) );

            else
            {
                $dest_type = $messages_model::DEST_TYPE_HANDLERS;
                $dest_type_handlers = $reply_author_handler;
                $subject = $reply_message['message']['subject'];

                $re_str = $this->_pt( 'Re: ' );
                if( strtolower( substr( $subject, 0, 4 ) ) != strtolower( $re_str ) )
                    $subject = $re_str.$subject;
            }
        }

        if( empty( $foobar ) )
        {
            if( empty( $dest_type ) )
                $dest_type = $messages_model::DEST_TYPE_HANDLERS;
        }

        if( !empty( $do_submit ) )
        {
            $message_params = array();
            $message_params['reply_message'] = $reply_message;
            $message_params['account_data'] = $current_user;
            $message_params['subject'] = $subject;
            $message_params['body'] = $body;
            $message_params['dest_type'] = $dest_type;
            $message_params['dest_type_users'] = $dest_type_users;
            $message_params['dest_type_handlers'] = $dest_type_handlers;
            $message_params['dest_type_level'] = $dest_type_level;
            $message_params['dest_type_role'] = $dest_type_role;
            $message_params['dest_type_role_unit'] = $dest_type_role_unit;
            $message_params['can_reply'] = ($cannot_reply?0:1);

            if( ($new_message = $messages_model->write_message( $message_params )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Message details saved in database.' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'messages', 'a' => 'compose' ), array( 'mid' => $new_message['message']['id'], 'message_queued' => 1 ) );

                return $action_result;
            } else
            {
                if( $messages_model->has_error() )
                    PHS_Notifications::add_error_notice( $messages_model->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error sending message. Please try again.' ) );
            }
        }

        $data = array(
            'reply_message' => $reply_message,
            'reply_to' => $reply_to,
            'reply_to_muid' => $reply_to_muid,

            'subject' => $subject,
            'dest_type' => $dest_type,
            'dest_type_users' => $dest_type_users,
            'dest_type_handlers' => $dest_type_handlers,
            'dest_type_level' => $dest_type_level,
            'dest_type_role' => $dest_type_role,
            'dest_type_role_unit' => $dest_type_role_unit,
            'cannot_reply' => $cannot_reply,
            'body' => $body,

            'dest_types' => $dest_types,
            'user_levels' => $user_levels,
            'roles_arr' => $roles_arr,
            'roles_units_arr' => $roles_units_arr,

            'messages_model' => $messages_model,
            'accounts_model' => $accounts_model,
            'roles_model' => $roles_model,
            'messages_plugin' => $messages_plugin,
        );


        $action_result = $this->quick_render_template( 'compose', $data );

        /** @var \phs\plugins\s2p_libraries\PHS_Plugin_S2p_libraries $libraries_plugin */
        if( ($libraries_plugin = PHS::load_plugin( 's2p_libraries' ))
        and ($libraries_view_obj = $libraries_plugin->quick_init_view_instance( 'page_in_header' )) )
        {
            if( empty( $action_result['page_settings']['page_in_header'] ) )
                $action_result['page_settings']['page_in_header'] = '';

            if( ($page_in_header_buffer = $libraries_view_obj->render()) )
                $action_result['page_settings']['page_in_header'] .= $page_in_header_buffer;
        }

        return $action_result;
    }
}
