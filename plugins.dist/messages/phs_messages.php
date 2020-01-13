<?php

namespace phs\plugins\messages;

use phs\libraries\PHS_Roles;
use \phs\PHS;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Error;
use \phs\libraries\PHS_params;
use \phs\system\core\views\PHS_View;
use \phs\libraries\PHS_Model;

class PHS_Plugin_Messages extends PHS_Plugin
{
    const ERR_TEMPLATE = 40000, ERR_RENDER = 40001;

    const UD_COLUMN_MSG_HANDLER = 'msg_handler';

    const ROLE_MESSAGE_READER = 'phs_messages_reader', ROLE_MESSAGE_WRITER = 'phs_messages_writer',
          // Normal users with special priviledges
          ROLE_MESSAGE_ALL = 'phs_messages_user_all',
          // Role which sums all role units for platform admins
          ROLE_MESSAGE_ADMIN = 'phs_messages_admin';

    const ROLEU_READ_MESSAGE = 'phs_messages_read', ROLEU_REPLY_MESSAGE = 'phs_messages_reply', ROLEU_FOLLOWUP_MESSAGE = 'phs_messages_followup',
          ROLEU_WRITE_MESSAGE = 'phs_messages_write',
          ROLEU_HANDLER_CHANGE = 'phs_messages_handler_change', ROLEU_HANDLER_AUTOCOMPLETE = 'phs_messages_handler_autocomplete',
          ROLEU_ALL_DESTINATIONS = 'phs_messages_all_destinations', ROLEU_SEND_ANONYMOUS = 'phs_messages_send_anonymous',
          ROLEU_NO_REPLY_OPTION = 'phs_messages_no_reply_opt',
          ROLEU_SET_TYPE_IN_COMPOSE = 'phs_messages_type_in_compose',
          ROLEU_VIEW_ALL_MESSAGES = 'phs_messages_view_all_messages', ROLEU_CAN_REPLY_TO_ALL = 'phs_messages_can_reply_to_all';

    public static function get_msg_handler_field_definition()
    {
        return array(
            'type' => PHS_Model::FTYPE_VARCHAR,
            'length' => '255',
            'index' => true,
            'nullable' => true,
            'default' => null,
            'editable' => true,
        );
    }

    protected function custom_install()
    {
        $this->reset_error();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $accounts_details_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_details_model = PHS::load_model( 'accounts_details', 'accounts' ))
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_INSTALL, $this->_pt( 'Error instantiating accounts details model.' ) );
            return false;
        }

        $flow_params = array( 'table_name' => 'users_details' );
        if( !$accounts_details_model->check_column_exists( self::UD_COLUMN_MSG_HANDLER, $flow_params ) )
        {
            $field_arr = self::get_msg_handler_field_definition();

            $column_params = array( 'after_column' => 'id' );
            if( !($result = $accounts_details_model->alter_table_add_column( self::UD_COLUMN_MSG_HANDLER, $field_arr, $flow_params, $column_params )) )
            {
                $this->set_error( self::ERR_INSTALL, $this->_pt( 'Error altering user_details table.' ) );
                return false;
            }
        }

        if( ($ad_flow_params = $accounts_details_model->fetch_default_flow_params( array( 'table_name' => 'users_details' ) ))
        and ($ud_table_name = $accounts_details_model->get_flow_table_name( $ad_flow_params ))
        and ($users_flow_params = $accounts_model->fetch_default_flow_params( array( 'table_name' => 'users' ) ))
        and ($users_table_name = $accounts_model->get_flow_table_name( $users_flow_params )) )
        {
            $list_arr = $users_flow_params;
            $list_arr['fields']['status'] = array( 'check' => '!=', 'value' => $accounts_model::STATUS_DELETED );

            if( ($users_list = $accounts_model->get_list( $list_arr ))
            and is_array( $users_list ) )
            {
                foreach( $users_list as $user_id => $user_arr )
                {
                    if( !($user_details = $accounts_model->get_account_details( $user_arr ))
                     or empty( $user_details[self::UD_COLUMN_MSG_HANDLER] ) )
                    {
                        $details_arr = array();
                        $details_arr[self::UD_COLUMN_MSG_HANDLER] = $user_arr['nick'];

                        $accounts_model->update_user_details( $user_arr, $details_arr );
                    }

                    if( $accounts_model->acc_is_admin( $user_arr ) )
                        $roles_arr = array( self::ROLE_MESSAGE_ADMIN );
                    elseif( $accounts_model->acc_is_operator( $user_arr ) )
                        $roles_arr = array( self::ROLE_MESSAGE_ALL );
                    else
                        $roles_arr = array( self::ROLE_MESSAGE_WRITER );

                    PHS_Roles::link_roles_to_user( $user_arr, $roles_arr, array( 'append_roles' => true ) );
                }
            }
        }

        // Reset any errors
        $this->reset_error();
        self::st_reset_error();

        return true;
    }

    protected function custom_uninstall()
    {
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $accounts_details_model */
        if( !($accounts_details_model = PHS::load_model( 'accounts_details', 'accounts' )) )
        {
            $this->set_error( self::ERR_INSTALL, $this->_pt( 'Error instantiating accounts details model.' ) );
            return false;
        }

        $flow_params = array( 'table_name' => 'users_details' );

        if( !$accounts_details_model->alter_table_drop_column( self::UD_COLUMN_MSG_HANDLER, $flow_params ) )
        {
            $this->set_error( self::ERR_UNINSTALL, $this->_pt( 'Error altering user_details table.' ) );
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
            // default template
            'summary_template' => array(
                'display_name' => 'Summary template',
                'display_hint' => 'What template should be used when displaying messages summary',
                'type' => PHS_params::T_ASIS,
                'input_type' => self::INPUT_TYPE_TEMPLATE,
                'default' => $this->template_resource_from_file( 'summary' ),
            ),
            'summary_limit' => array(
                'display_name' => 'Messages sumary count',
                'display_hint' => 'How many messages should be presented in summary. 0 to disable summary',
                'type' => PHS_params::T_INT,
                'default' => 5,
            ),
            'send_emails' => array(
                'display_name' => 'Alert by emails',
                'display_hint' => 'Alert user by email when he/she receives an internal message',
                'type' => PHS_params::T_BOOL,
                'default' => true,
            ),
            'include_body' => array(
                'display_name' => 'Body message in email',
                'display_hint' => 'When sending email alert, also include body of the message in the email',
                'type' => PHS_params::T_BOOL,
                'default' => false,
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition()
    {
        $return_arr = array(
            self::ROLE_MESSAGE_READER => array(
                'name' => 'Messages reader',
                'description' => 'Allow user only to read and reply to received messages',
                'role_units' => array(
                    self::ROLEU_READ_MESSAGE => array(
                        'name' => 'Read received messages',
                        'description' => 'Allow user to read received messages',
                    ),
                    self::ROLEU_REPLY_MESSAGE => array(
                        'name' => 'Reply to a message',
                        'description' => 'Allow user to reply to received messages',
                    ),
                ),
            ),
        );

        $return_arr[self::ROLE_MESSAGE_WRITER] = $return_arr[self::ROLE_MESSAGE_READER];

        $return_arr[self::ROLE_MESSAGE_WRITER]['name'] = 'Messages writer';
        $return_arr[self::ROLE_MESSAGE_WRITER]['description'] = 'Role units which allow users to compose messages';

        $return_arr[self::ROLE_MESSAGE_WRITER]['role_units'][self::ROLEU_WRITE_MESSAGE] = array(
            'name' => 'Compose messages',
            'description' => 'Allow user to compose messages to other users',
        );
        $return_arr[self::ROLE_MESSAGE_WRITER]['role_units'][self::ROLEU_FOLLOWUP_MESSAGE] = array(
            'name' => 'Follow up messages',
            'description' => 'Allow user to send a message to destination even if destination didn\'t reply yet',
        );
        $return_arr[self::ROLE_MESSAGE_WRITER]['role_units'][self::ROLEU_HANDLER_CHANGE] = array(
            'name' => 'Change message handler',
            'description' => 'Allow user to change messages handler (nickname of messages system)',
        );

        $return_arr[self::ROLE_MESSAGE_ALL] = $return_arr[self::ROLE_MESSAGE_WRITER];

        $return_arr[self::ROLE_MESSAGE_ALL]['name'] = 'Messages writer plus';
        $return_arr[self::ROLE_MESSAGE_ALL]['description'] = 'All role units defined for messages writer plus autocomplete for handlers when composing messages';

        $return_arr[self::ROLE_MESSAGE_ALL]['role_units'][self::ROLEU_HANDLER_AUTOCOMPLETE] = array(
            'name' => 'Handler autocomplete',
            'description' => 'Allow user to have autocomplete feature when writing messages',
        );

        $return_arr[self::ROLE_MESSAGE_ADMIN] = $return_arr[self::ROLE_MESSAGE_ALL];

        $return_arr[self::ROLE_MESSAGE_ADMIN]['name'] = 'All messages functionalities';
        $return_arr[self::ROLE_MESSAGE_ADMIN]['description'] = 'Defines role units available for admin accounts';

        $return_arr[self::ROLE_MESSAGE_ADMIN]['role_units'][self::ROLEU_ALL_DESTINATIONS] = array(
            'name' => 'Write to all destinations',
            'description' => 'Allow user to compose to all destination types',
        );
        $return_arr[self::ROLE_MESSAGE_ADMIN]['role_units'][self::ROLEU_NO_REPLY_OPTION] = array(
            'name' => 'No reply option',
            'description' => 'Allow user to compose messages which cannot be replied',
        );
        $return_arr[self::ROLE_MESSAGE_ADMIN]['role_units'][self::ROLEU_SEND_ANONYMOUS] = array(
            'name' => 'Send as anonymous',
            'description' => 'Allow user to send messages with no option to see who wrote them (will appear as system messages)',
        );
        $return_arr[self::ROLE_MESSAGE_ADMIN]['role_units'][self::ROLEU_SET_TYPE_IN_COMPOSE] = array(
            'name' => 'Message type in compose',
            'description' => 'Allow user to change message type as parameter in compose form (for special messages)',
        );
        $return_arr[self::ROLE_MESSAGE_ADMIN]['role_units'][self::ROLEU_VIEW_ALL_MESSAGES] = array(
            'name' => 'View all messages',
            'description' => 'Allow user to view all messages (not only threads user is involved in)',
        );
        $return_arr[self::ROLE_MESSAGE_ADMIN]['role_units'][self::ROLEU_CAN_REPLY_TO_ALL] = array(
            'name' => 'Reply to all messages',
            'description' => 'Allow user to reply to all messages (not only threads user is involved in)',
        );

        return $return_arr;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function get_messages_summary_hook_args( $hook_args )
    {
        $this->reset_error();

        if( !($current_user = PHS::user_logged_in())
         or !PHS_Roles::user_has_role_units( $current_user, self::ROLEU_READ_MESSAGE ) )
            return PHS_Hooks::default_messages_summary_hook_args();

        if( !($settings_arr = $this->get_db_settings())
         or empty( $settings_arr['summary_template'] ) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Couldn\'t load summary template from plugin settings.' ) );
            return false;
        }

        // 0 means disable summary
        if( empty( $settings_arr['summary_limit'] ) or $settings_arr['summary_limit'] <= 0 )
            return self::validate_array_recursive( $hook_args, PHS_Hooks::default_messages_summary_hook_args() );

        if( !($summary_template = PHS_View::validate_template_resource( $settings_arr['summary_template'] )) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed validating messages summary template file.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        /** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
        if( !($messages_model = PHS::load_model( 'messages', 'messages' )) )
        {
            $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Failed loading messages model.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        $hook_args = self::validate_array_recursive( $hook_args, PHS_Hooks::default_messages_summary_hook_args() );

        if( !($hook_args['messages_new'] = $messages_model->get_new_messages_count( $current_user )) )
            $hook_args['messages_new'] = 0;
        if( !($hook_args['messages_count'] = $messages_model->get_total_messages_count( $current_user )) )
            $hook_args['messages_count'] = 0;

        $hook_args['list_limit'] = $settings_arr['summary_limit'];
        $hook_args['template'] = $summary_template;

        if( ($hook_args['messages_list'] = $messages_model->get_summary_listing( $hook_args, $current_user )) === false )
        {
            if( $messages_model->has_error() )
                $this->copy_error( $messages_model, self::ERR_TEMPLATE );
            else
                $this->set_error( self::ERR_TEMPLATE, $this->_pt( 'Error obtaining summary list of messages.' ) );

            $hook_args['hook_errors'] = self::validate_array( $this->get_error(), PHS_Error::default_error_array() );

            return $hook_args;
        }

        if( empty( $hook_args['messages_list'] ) )
            $hook_args['messages_list'] = array();

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['parent_plugin_obj'] = $this;
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = array(
            'summary_container_id' => $hook_args['summary_container_id'],
            'messages_new' => $hook_args['messages_new'],
            'messages_count' => $hook_args['messages_count'],
            'messages_list' => $hook_args['messages_list'],
            'messages_model' => $messages_model,
            'messages_plugin' => $this,
        );

        if( !($view_obj = PHS_View::init_view( $summary_template, $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        if( ($hook_args['summary_buffer'] = $view_obj->render()) === false )
        {
            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, $this->_pt( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        if( empty( $hook_args['summary_buffer'] ) )
            $hook_args['summary_buffer'] = '';

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_after_main_menu_logged_in( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = array();

        $hook_args['buffer'] = $this->quick_render_template_for_buffer( 'main_menu_member', $data );

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_after_main_menu_admin( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = array();

        $hook_args['buffer'] = $this->quick_render_template_for_buffer( 'main_menu_admin', $data );

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_model_table_fields( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Model::default_table_fields_hook_args() );

        if( empty( $hook_args['flow_params'] ) or !is_array( $hook_args['flow_params'] )
         or empty( $hook_args['flow_params']['table_name'] ) )
            return false;

        switch( $hook_args['flow_params']['table_name'] )
        {
            default:
                return false;

            case 'users_details':
                $hook_args['fields_arr'] = array(
                    self::UD_COLUMN_MSG_HANDLER => self::get_msg_handler_field_definition(),
                );
            break;
        }

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_account_action( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_account_action_hook_args() );

        if( empty( $hook_args['account_data'] ) or !is_array( $hook_args['account_data'] )
         or empty( $hook_args['account_data']['id'] )
         or $hook_args['action_alias'] !== 'after_delete' )
            return false;

        $account_arr = $hook_args['account_data'];

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_details $accounts_details_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( ($accounts_details_model = PHS::load_model( 'accounts_details', 'accounts' ))
        and ($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
        and $accounts_details_model->check_column_exists( self::UD_COLUMN_MSG_HANDLER, array( 'table_name' => 'users_details' ) )
        and ($user_details_arr = $accounts_model->get_account_details( $account_arr ))
        and !empty( $user_details_arr[self::UD_COLUMN_MSG_HANDLER] ) )
        {
            $details_arr = array();
            $details_arr[self::UD_COLUMN_MSG_HANDLER] = $account_arr['nick'];

            if( ($new_account_arr = $accounts_model->update_user_details( $account_arr, $details_arr )) )
                $account_arr = $new_account_arr;


            $hook_args['account_data'] = $account_arr;
        }

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_assign_registration_roles( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_user_registration_roles_hook_args() );

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or empty( $hook_args['account_data'] )
         or !($account_arr = $accounts_model->data_to_array( $hook_args['account_data'] )) )
            return $hook_args;

        if( empty( $hook_args['roles_arr'] ) )
            $hook_args['roles_arr'] = array();

        if( $accounts_model->acc_is_admin( $account_arr ) )
            $hook_args['roles_arr'][] = self::ROLE_MESSAGE_ADMIN;
        elseif( $accounts_model->acc_is_operator( $account_arr ) )
            $hook_args['roles_arr'][] = self::ROLE_MESSAGE_ALL;
        else
            $hook_args['roles_arr'][] = self::ROLE_MESSAGE_WRITER;

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_user_details_fields( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_user_account_fields_hook_args() );

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( empty( $hook_args['account_data'] )
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or !($accounts_details_model = PHS::load_model( 'accounts_details', 'accounts' ))
         or !($account_arr = $accounts_model->data_to_array( $hook_args['account_data'] )) )
            return $hook_args;

        $account_details_arr = false;
        if( empty( $hook_args['account_details_data'] ) )
            $hook_args['account_details_data'] = false;
        elseif( !($account_details_arr = $accounts_details_model->data_to_array( $hook_args['account_details_data'] )) )
            return $hook_args;

        if( empty( $hook_args['account_details_fields'] ) or !is_array( $hook_args['account_details_fields'] ) )
            $hook_args['account_details_fields'] = array();

        if( empty( $account_details_arr ) or !is_array( $account_details_arr )
         or empty( $account_details_arr[self::UD_COLUMN_MSG_HANDLER] ) )
            $hook_args['account_details_fields'][self::UD_COLUMN_MSG_HANDLER] = $account_arr['nick'];

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_user_registration( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_user_account_hook_args() );

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( empty( $hook_args['account_data'] )
         or !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or !($accounts_details_model = PHS::load_model( 'accounts_details', 'accounts' ))
         or !($account_arr = $accounts_model->data_to_array( $hook_args['account_data'] )) )
            return $hook_args;

        if( empty( $hook_args['account_details_data'] ) or !is_array( $hook_args['account_details_data'] )
         or empty( $hook_args['account_details_data'][self::UD_COLUMN_MSG_HANDLER] ) )
        {
            if( empty( $hook_args['account_details_data'] ) or !is_array( $hook_args['account_details_data'] ) )
                $hook_args['account_details_data'] = array();

            if( !($updated_account_arr = $accounts_model->update_user_details( $account_arr, $hook_args['account_details_data'] )) )
                return $hook_args;

            $hook_args['account_data'] = $updated_account_arr;
            if( !empty( $updated_account_arr['{users_details}'] ) )
                $hook_args['account_details_data'] = $updated_account_arr['{users_details}'];
        }

        return $hook_args;
    }
}
