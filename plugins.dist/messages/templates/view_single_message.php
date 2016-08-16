<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\PHS_ajax;
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

    $current_user = PHS::current_user();

    $can_reply_messages = false;
    if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_REPLY_MESSAGE ) )
        $can_reply_messages = true;

?>
<fieldset class="form-group message_line">
    <?php
    $destination_str = '';

    switch( $message_arr['message']['dest_type'] )
    {
        default:
            $destination_str = '['.$this->_pt( 'Unknown destination' ).']';
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
                $destination_str = $roles_arr[$message_arr['message']['dest_id']];
            else
                $destination_str = '['.$this->_pt( 'Unknown role' ).']';
        break;

        case $messages_model::DEST_TYPE_ROLE_UNIT:
            if( !empty( $roles_units_arr[$message_arr['message']['dest_id']] ) )
                $destination_str = $roles_units_arr[$message_arr['message']['dest_id']];
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

    ?><p><?php echo $this->_pt( 'On <strong>%s</strong>, <strong>%s</strong> wrote to <strong>%s</strong>:',
                                date( 'Y-m-d H:i', parse_db_date( $message_arr['message']['cdate'] ) ),
                                $this->context_var( 'author_handler' ),
                                $destination_str )?></p>
    <div class="message_body"><?php echo nl2br( str_replace( '  ', ' &nbsp;', $message_arr['message_body']['body'] ) );?></div>
    <div class="clearfix"></div>
    <div class="message_actions">
    <?php
    if( $can_reply_messages
    and $messages_model->can_reply( $message_arr, array( 'account_data' => $current_user ) ) )
    {
        ?> <a class="btn btn-primary btn-small" href="<?php echo PHS::url( array( 'p' => 'messages', 'a' => 'compose' ), array( 'reply_to_muid' => $message_arr['message_user']['id'] ) )?>"><i class="fa fa-reply"></i> Reply</a> <?php
    }
    ?>
    </div>
</fieldset>
