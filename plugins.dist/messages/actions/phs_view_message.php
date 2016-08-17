<?php

namespace phs\plugins\messages\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_View_message extends PHS_Action
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
        PHS::page_settings( 'page_title', $this->_pt( 'View Message' ) );

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

        $muid = PHS_params::_g( 'muid', PHS_params::T_INT );

        if( empty( $muid )
         or !($user_message = $messages_model->data_to_array( $muid, $mu_flow_params ))
         or empty( $user_message['message_id'] )
         or !($message_arr = $messages_model->full_data_to_array( $user_message['message_id'], $current_user )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load message details.' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'messages', 'a' => 'inbox' ), array( 'unknown_message' => 1 ) );

            return $action_result;
        }

        if( $message_arr['message']['thread_id'] == $message_arr['message']['id'] )
            $thread_arr = $message_arr;

        elseif( !($thread_arr = $messages_model->full_data_to_array( $message_arr['message']['thread_id'] )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load thread of message.' ) );

            $action_result = self::default_action_result();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'messages', 'a' => 'inbox' ), array( 'unknown_thread' => 1 ) );

            return $action_result;
        }

        if( empty( $message_arr['message']['thread_id'] )
         or !($thread_messages_arr = $messages_model->get_thread_messages_flow( $message_arr['message']['thread_id'], $current_user['id'] )) )
            $thread_messages_arr = array();

        if( !($dest_types = $messages_model->get_dest_types_as_key_val()) )
            $dest_types = array();
        if( !($user_levels = $accounts_model->get_levels_as_key_val()) )
            $user_levels = array();
        if( !($roles_arr = $roles_model->get_all_roles()) )
            $roles_arr = array();
        if( !($roles_units_arr = $roles_model->get_all_role_units()) )
            $roles_units_arr = array();

        if( !($author_handler = $messages_model->get_relative_account_message_handler( $message_arr['message']['from_uid'], $current_user )) )
            $author_handler = '['.$this->_pt( 'Unknown author' ).']';

        $data = array(
            'thread_arr' => $thread_arr,
            'message_arr' => $message_arr,
            'author_handler' => $author_handler,

            'thread_messages_arr' => $thread_messages_arr,

            'dest_types' => $dest_types,
            'user_levels' => $user_levels,
            'roles_arr' => $roles_arr,
            'roles_units_arr' => $roles_units_arr,

            'messages_model' => $messages_model,
            'accounts_model' => $accounts_model,
            'roles_model' => $roles_model,
            'messages_plugin' => $messages_plugin,
        );

        return $this->quick_render_template( 'view_message', $data );
    }
}
