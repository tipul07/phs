<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
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
     or !$messages_model::is_full_message_data( $message_arr )
     or !($thread_arr = $this->context_var( 'thread_arr' ))
     or !$messages_model::is_full_message_data( $thread_arr ) )
        return $this->_pt( 'Couldn\'t initialize required parameters for current view.' );

    if( !($dest_types = $this->context_var( 'dest_types' )) )
        $dest_types = array();
    if( !($user_levels = $this->context_var( 'user_levels' )) )
        $user_levels = array();
    if( !($roles_arr = $this->context_var( 'roles_arr' )) )
        $roles_arr = array();
    if( !($roles_units_arr = $this->context_var( 'roles_units_arr' )) )
        $roles_units_arr = array();

    $current_user = PHS::current_user();

?>
<form id="view_message_form" name="view_message_form" action="<?php echo PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ) )?>" method="post">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container" style="min-width: 800px;max-width:850px;">

        <section class="heading-bordered">
            <h3><?php echo $thread_arr['message']['subject'];?></h3>
        </section>
        <div class="clearfix"></div>

        <fieldset class="form-group">
            <p>On <strong><?php echo date( 'Y-m-d H:i', parse_db_date( $message_arr['message']['cdate'] ) )?></strong>,
                <strong><?php echo $this->context_var( 'author_handler' )?></strong> wrote to
            <strong><?php
            switch( $message_arr['message']['dest_type'] )
            {
                case $messages_model::DEST_TYPE_USERS:
                case $messages_model::DEST_TYPE_HANDLERS:
                    echo $message_arr['message']['dest_str'];
                break;

                case $messages_model::DEST_TYPE_LEVEL:
                    if( !empty( $user_levels[$message_arr['message']['dest_id']] ) )
                        echo $user_levels[$message_arr['message']['dest_id']];
                    else
                        echo '['.$this->_pt( 'Unknown user level' ).']';
                break;

                case $messages_model::DEST_TYPE_ROLE:
                    if( !empty( $roles_arr[$message_arr['message']['dest_id']] ) )
                        echo $roles_arr[$message_arr['message']['dest_id']];
                    else
                        echo '['.$this->_pt( 'Unknown role' ).']';
                break;

                case $messages_model::DEST_TYPE_ROLE_UNIT:
                    if( !empty( $roles_units_arr[$message_arr['message']['dest_id']] ) )
                        echo $roles_units_arr[$message_arr['message']['dest_id']];
                    else
                        echo '['.$this->_pt( 'Unknown role unit' ).']';
                break;
            }
            ?></strong>:</p>
            <?php echo nl2br( str_replace( '  ', ' &nbsp;', $message_arr['message_body']['body'] ) );?>
        </fieldset>

    </div>
</form>
