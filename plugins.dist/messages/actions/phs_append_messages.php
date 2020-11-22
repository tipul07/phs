<?php

namespace phs\plugins\messages\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_Append_messages extends PHS_Action
{

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AJAX );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

       /** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
        if( !($messages_plugin = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load messages plugin.' ) );
            return self::default_action_result();
        }

        if( !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_READ_MESSAGE ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to read messages.' ) );
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

        $muid = PHS_Params::_p( 'muid', PHS_Params::T_INT );
        $max_messages = PHS_Params::_p( 'max_messages', PHS_Params::T_INT );
        $offset = PHS_Params::_p( 'offset', PHS_Params::T_INT );
        $location = PHS_Params::_p( 'location', PHS_Params::T_NOHTML );

        if( empty( $location )
         or !in_array( $location, array( 'before', 'after',  ) ) )
            $location = 'after';

        if( empty( $max_messages ) )
            $max_messages = 5;
        elseif( $max_messages == -1 )
            $max_messages = 99999999;

        if( empty( $muid )
         or !($user_message = $messages_model->get_details( $muid, $mu_flow_params ))
         or empty( $user_message['message_id'] ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load message details.' ) );

            $action_result = self::default_action_result();

            //$action_result['redirect_to_url'] = PHS::url( array( 'p' => 'messages', 'a' => 'inbox' ), array( 'unknown_message' => 1 ) );

            return $action_result;
        }

        if( !($message_arr = $messages_model->full_data_to_array( $user_message['message_id'], $current_user )) )
        {
            if( !PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_VIEW_ALL_MESSAGES )
             or !($message_arr = $messages_model->full_data_to_array( $user_message['message_id'], $current_user, array( 'ignore_user_message' => true ) )) )
            {
                PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load message details.' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'messages', 'a' => 'inbox' ), array( 'unknown_message' => 1 ) );

                return $action_result;
            }

            if( empty( $message_arr['message_user'] ) )
                $message_arr['message_user'] = $user_message;
        }

        if( empty( $message_arr['message']['thread_id'] )
         or !($thread_messages_arr = $messages_model->get_thread_messages_flow( $message_arr['message']['thread_id'], $current_user['id'] )) )
            $thread_messages_arr = array();

        $ids_to_render = array();
        $messages_before = 0;
        $messages_after = 0;
        if( !empty( $thread_messages_arr ) and is_array( $thread_messages_arr ) )
        {
            $we_are_before = true;
            foreach( $thread_messages_arr as $um_id => $um_arr )
            {
                if( $message_arr['message']['id'] == $um_arr['message_id'] )
                {
                    $we_are_before = false;
                    continue;
                }

                if( $we_are_before )
                {
                    if( $location == 'before' )
                        $ids_to_render[] = $um_arr['message_id'];
                } else
                {
                    if( $location == 'after' )
                        $ids_to_render[] = $um_arr['message_id'];
                }
            }
        }

        if( empty( $ids_to_render ) or !is_array( $ids_to_render ) )
            $buffer = $this->_pt( 'No more messages...' );

        else
        {
            if( !($user_levels = $accounts_model->get_levels_as_key_val()) )
                $user_levels = array();
            if( !($roles_arr = $roles_model->get_all_roles()) )
                $roles_arr = array();
            if( !($roles_units_arr = $roles_model->get_all_role_units()) )
                $roles_units_arr = array();

            $buffer = '';
            foreach( $ids_to_render as $render_message_id )
            {
                if( !($message_arr = $messages_model->full_data_to_array( $render_message_id, $current_user, array( 'ignore_user_message' => true ) )) )
                    continue;

                if( !($author_handle = $messages_model->get_relative_account_message_handler( $message_arr['message']['from_uid'], $current_user )) )
                    $author_handle = '['.$this->_pt( 'Unknown author' ).']';

                $data = array(
                    'muid' => $muid,
                    'message_arr' => $message_arr,
                    'author_handle' => $author_handle,

                    'thread_messages_arr' => $thread_messages_arr,

                    'user_levels' => $user_levels,
                    'roles_arr' => $roles_arr,
                    'roles_units_arr' => $roles_units_arr,

                    'messages_model' => $messages_model,
                    'accounts_model' => $accounts_model,
                    'roles_model' => $roles_model,
                    'messages_plugin' => $messages_plugin,
                );

                if( !($action_result = $this->quick_render_template( 'view_single_message', $data ))
                 or empty( $action_result['buffer'] ) )
                {
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error rendering messages. Please retry.' ) );
                    return self::default_action_result();
                }

                $buffer .= $action_result['buffer'];
            }
        }

        $action_result = self::default_action_result();

        $action_result['ajax_result'] = $buffer;

        return $action_result;
    }
}
