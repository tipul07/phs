<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Roles;

    /** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
    /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
    /** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
    if( !($messages_model = $this->context_var( 'messages_model' ))
     or !($accounts_model = $this->context_var( 'accounts_model' ))
     or !($roles_model = $this->context_var( 'roles_model' ))
     or !($messages_plugin = $this->context_var( 'messages_plugin' ))
     or !($message_arr = $this->context_var( 'message_arr' ))
     or !$messages_model::is_full_message_data( $message_arr ) )
        return $this->_pt( 'Couldn\'t initialize required parameters for current view.' );

    if( !($user_levels = $this->context_var( 'user_levels' )) )
        $user_levels = array();
    if( !($roles_arr = $this->context_var( 'roles_arr' )) )
        $roles_arr = array();
    if( !($roles_units_arr = $this->context_var( 'roles_units_arr' )) )
        $roles_units_arr = array();
    if( !($author_handle = $this->context_var( 'author_handle' )) )
        $author_handle = $this->_pt( 'N/A' );

    $current_user = PHS::current_user();

    $can_reply_messages = false;
    if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_REPLY_MESSAGE ) )
        $can_reply_messages = true;

    if( !empty( $message_arr['message_user'] )
    and !empty( $message_arr['message_user']['is_new'] )
    and !empty( $message_arr['message_user']['user_id'] )
    and $current_user['id'] == $message_arr['message_user']['user_id'] )
    {
        $messages_model->mark_as_read( $message_arr['message_user'] );
    }

?>
<fieldset class="form-group message_line <?php echo (!empty( $message_arr['message_user']['is_author'] )?'my_message':'');?>" >
    <?php
    $destination_str = '';

    switch( $message_arr['message']['dest_type'] )
    {
        default:
            $destination_str = '['.$this->_pt( 'Unknown destination' ).']';
        break;

        case $messages_model::DEST_TYPE_USERS_IDS:
            $destination_str = 'IDs: '.$message_arr['message']['dest_str'];
        break;

        case $messages_model::DEST_TYPE_USERS:
        case $messages_model::DEST_TYPE_HANDLERS:
            $destination_str = $message_arr['message']['dest_str'];
        break;

        case $messages_model::DEST_TYPE_LEVEL:
            if( !empty( $user_levels[$message_arr['message']['dest_id']] ) )
                $destination_str = $user_levels[$message_arr['message']['dest_id']];
            else
                $destination_str = '['.$this->_pt( 'Unknown user level' ).']';
        break;

        case $messages_model::DEST_TYPE_ROLE:
            if( !empty( $roles_arr[$message_arr['message']['dest_id']] ) )
                $destination_str = $roles_arr[$message_arr['message']['dest_id']]['name'];
            else
                $destination_str = '['.$this->_pt( 'Unknown role' ).']';
        break;

        case $messages_model::DEST_TYPE_ROLE_UNIT:
            if( !empty( $roles_units_arr[$message_arr['message']['dest_id']] ) )
                $destination_str = $roles_units_arr[$message_arr['message']['dest_id']]['name'];
            else
                $destination_str = '['.$this->_pt( 'Unknown role unit' ).']';
        break;
    }

    if( !empty( $message_arr['message_user']['user_id'] )
    and $message_arr['message_user']['user_id'] == $current_user['id']
    and empty( $message_arr['message_user']['is_author'] ) )
    {
        $destination_str = $this->_pt( 'You (%s)', $destination_str );
    }

    ?>
	<div class="message_header">
		<?php echo $this->_pt( 'FROM: %s', '<strong>'.$author_handle.'</strong>' ).'<br/>'.
                   $this->_pt( 'TO: %s', '<strong>'.$destination_str.'</strong>' );?>
		<div class="message_date">
			<?php echo date( 'Y-m-d H:i', parse_db_date( $message_arr['message']['cdate'] ) ) ?>
		</div>
	</div>
    <div class="message_body">
		<?php echo nl2br( str_replace( '  ', ' &nbsp;', $message_arr['message_body']['body'] ) );?>
	</div>
    <div class="message_actions">
		<?php
		$msg_actions_arr = array();

		// $msg_actions_arr['{ACTION_KEY}'] = array(
		//     'extra_classes' => 'any classes to be added to a tag',
		//     'action_link' => '',
		//     'action_icon' => '',
		//     'action_label' => '',
		// );

		if( $can_reply_messages
		and $messages_model->can_reply( $message_arr, array( 'account_data' => $current_user ) ) )
		{
			$msg_actions_arr['msg_reply'] = array(
				'extra_classes' => '',
				'action_link' => PHS::url( array( 'p' => 'messages', 'a' => 'compose' ), array( 'reply_to_muid' => $message_arr['message_user']['id'] ) ),
				'action_icon' => 'fa-reply',
				'action_label' => $this->_pt( 'Reply' ),
			);
		}

		$hook_args = PHS_Hooks::default_single_types_actions_hook_args();
		$hook_args['actions_arr'] = $msg_actions_arr;
		$hook_args['message_data'] = $message_arr;
		$hook_args['destination_str'] = $destination_str;
		$hook_args['author_handle'] = $author_handle;

		if( ($hook_actions_arr = PHS::trigger_hooks( PHS_Hooks::H_MSG_SINGLE_DISPLAY_TYPES_ACTIONS, $hook_args ))
		and is_array( $hook_actions_arr ) and !empty( $hook_actions_arr['actions_arr'] ) )
			$msg_actions_arr = $this::merge_array_assoc( $hook_actions_arr['actions_arr'], $msg_actions_arr );

		if( !empty( $msg_actions_arr ) and is_array( $msg_actions_arr ) )
		{
			foreach( $msg_actions_arr as $action_key => $action_arr )
			{
				?> <a class="btn btn-primary btn-small<?php echo (!empty( $action_arr['extra_classes'] )?' '.$action_arr['extra_classes']:'')?>" href="<?php echo (!empty( $action_arr['action_link'] )?$action_arr['action_link']:'')?>"><?php echo (!empty( $action_arr['action_icon'] )?'<i class="fa '.$action_arr['action_icon'].'"></i> ':'').(!empty( $action_arr['action_label'] )?' '.$action_arr['action_label']:'')?></a> <?php
			}
		}
		?>
    </div>
</fieldset>
