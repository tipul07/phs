<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;

/** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
/** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
/** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
/** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
if (!($messages_model = $this->view_var('messages_model'))
 || !($accounts_model = $this->view_var('accounts_model'))
 || !($roles_model = $this->view_var('roles_model'))
 || !($messages_plugin = $this->view_var('messages_plugin'))
 || !($message_arr = $this->view_var('message_arr'))
 || !$messages_model::is_full_message_data($message_arr)) {
    return $this->_pt('Couldn\'t initialize required parameters for current view.');
}

if (!($user_levels = $this->view_var('user_levels'))) {
    $user_levels = [];
}
if (!($roles_arr = $this->view_var('roles_arr'))) {
    $roles_arr = [];
}
if (!($roles_units_arr = $this->view_var('roles_units_arr'))) {
    $roles_units_arr = [];
}
if (!($author_handle = $this->view_var('author_handle'))) {
    $author_handle = $this->_pt('N/A');
}

$current_user = PHS::current_user();

$can_reply_messages = false;
$can_followup_messages = false;
if (can($messages_plugin::ROLEU_REPLY_MESSAGE)) {
    $can_reply_messages = true;
}
if (can($messages_plugin::ROLEU_FOLLOWUP_MESSAGE)) {
    $can_followup_messages = true;
}

if (!empty($message_arr['message_user'])
&& !empty($message_arr['message_user']['is_new'])
&& !empty($message_arr['message_user']['user_id'])
&& (int)$current_user['id'] === (int)$message_arr['message_user']['user_id']) {
    $messages_model->mark_as_read($message_arr['message_user']);
}

?>
<fieldset class="form-group message_line <?php echo (!empty($message_arr['message_user']) && !empty($message_arr['message_user']['is_author'])) ? 'my_message' : ''; ?>" >
<?php

    if (!($destination_str = $messages_model->get_destination_as_string($message_arr['message']))) {
        $destination_str = '['.$this->_pt('Unknown destination').']';
    }

    if (!empty($message_arr['message_user'])
    && !empty($message_arr['message_user']['user_id'])
    && $message_arr['message_user']['user_id'] == $current_user['id']
    && empty($message_arr['message_user']['is_author'])) {
        $destination_str = $this->_pt('You (%s)', $destination_str);
    }

?>
	<div class="message_header">
		<?php echo $this->_pt('FROM: %s', '<strong>'.$author_handle.'</strong>').'<br/>'
               .$this->_pt('TO: %s', '<strong>'.$destination_str.'</strong>'); ?>
		<div class="message_date">
			<?php echo date('Y-m-d H:i', parse_db_date($message_arr['message']['cdate'])); ?>
		</div>
	</div>
    <div class="message_body">
		<?php echo nl2br(str_replace('  ', ' &nbsp;', $message_arr['message_body']['body'])); ?>
	</div>
    <div class="message_actions">
		<?php
    $msg_actions_arr = [];

// $msg_actions_arr['{ACTION_KEY}'] = array(
//     'extra_classes' => 'any classes to be added to a tag',
//     'action_link' => '',
//     'action_icon' => '',
//     'action_label' => '',
// );

if ($can_reply_messages
&& $messages_model->can_reply($message_arr, ['account_data' => $current_user])) {
    $msg_actions_arr['msg_reply'] = [
        'extra_classes' => '',
        'action_link'   => PHS::url(['p' => 'messages', 'a' => 'compose'], ['reply_to_muid' => $message_arr['message_user']['id'], 'reply_to_all' => 0]),
        'action_icon'   => 'fa-reply',
        'action_label'  => $this->_pt('Reply'),
    ];
    $msg_actions_arr['msg_reply_to_all'] = [
        'extra_classes' => '',
        'action_link'   => PHS::url(['p' => 'messages', 'a' => 'compose'], ['reply_to_muid' => $message_arr['message_user']['id'], 'reply_to_all' => 1]),
        'action_icon'   => 'fa-reply-all',
        'action_label'  => $this->_pt('Reply to all'),
    ];
}

if ($can_followup_messages
&& $messages_model->can_followup($message_arr, ['account_data' => $current_user])) {
    $msg_actions_arr['msg_followup'] = [
        'extra_classes' => '',
        'action_link'   => PHS::url(['p' => 'messages', 'a' => 'compose'], ['follow_up_muid' => $message_arr['message_user']['id']]),
        'action_icon'   => 'fa-flag',
        'action_label'  => $this->_pt('Follow Up'),
    ];
}

$hook_args = PHS_Hooks::default_single_types_actions_hook_args();
$hook_args['actions_arr'] = $msg_actions_arr;
$hook_args['message_data'] = $message_arr;
$hook_args['destination_str'] = $destination_str;
$hook_args['author_handle'] = $author_handle;

if (($hook_actions_arr = PHS::trigger_hooks(PHS_Hooks::H_MSG_SINGLE_DISPLAY_TYPES_ACTIONS, $hook_args))
&& is_array($hook_actions_arr) && !empty($hook_actions_arr['actions_arr'])) {
    $msg_actions_arr = $this::merge_array_assoc($hook_actions_arr['actions_arr'], $msg_actions_arr);
}

if (!empty($msg_actions_arr) && is_array($msg_actions_arr)) {
    foreach ($msg_actions_arr as $action_key => $action_arr) {
        ?> <a class="btn btn-primary btn-small<?php echo !empty($action_arr['extra_classes']) ? ' '.$action_arr['extra_classes'] : ''; ?>" href="<?php echo !empty($action_arr['action_link']) ? $action_arr['action_link'] : ''; ?>"><?php echo(!empty($action_arr['action_icon']) ? '<i class="fa '.$action_arr['action_icon'].'"></i> ' : '').(!empty($action_arr['action_label']) ? ' '.$action_arr['action_label'] : ''); ?></a> <?php
    }
}
?>
    </div>
</fieldset>
