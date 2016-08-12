<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Roles;
    use \phs\PHS_ajax;

    /** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
    /** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
    if( !($messages_model = $this->context_var( 'messages_model' ))
     or !($messages_plugin = $this->context_var( 'messages_plugin' )) )
        return $this->_pt( 'Couldn\'t load dependencies.' );

    if( !($summary_container_id = $this->context_var( 'summary_container_id' )) )
        $summary_container_id = '';
    if( !($messages_new = $this->context_var( 'messages_new' )) )
        $messages_new = 0;
    if( !($messages_count = $this->context_var( 'messages_count' )) )
        $messages_count = 0;
    if( !($messages_list = $this->context_var( 'messages_list' )) )
        $messages_list = array();

    $current_user = PHS::current_user();

    $can_reply_messages = false;
    $compose_url = false;
    $compose_route_arr = array( 'p' => 'messages', 'a' => 'compose' );
    if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_WRITE_MESSAGE ) )
        $compose_url = PHS::url( $compose_route_arr );
    if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_REPLY_MESSAGE ) )
        $can_reply_messages = true;

?><script type="text/javascript">
function phs_close_messages_summary_popup()
{
    $("#<?php echo (!empty( $summary_container_id )?$summary_container_id:'messages-summary-popup')?>").hide();
}
</script><div id="messages-summary-popup">
<div id="messages-summary-popup-title">
    <a href="<?php echo PHS::url( array( 'p' => 'messages', 'a' => 'inbox' ) );?>">Inbox</a> (<?php echo $messages_new?>/<?php echo $messages_count?>)
    <?php
    if( !empty( $compose_url ) )
    {
        ?> |
        <a href="<?php echo $compose_url?>">Compose</a>
        <?php
    }
    ?>

    <div style="float:right; margin: 0 15px 0 0; cursor: pointer;" onclick="phs_close_messages_summary_popup()"><i class="fa fa-times-circle"></i></div>
</div>
<div id="messages-summary-popup-content">
<div id="phs_ajax_new_messages" style="display:none;"></div>
<?php
    if( !empty( $messages_list ) and is_array( $messages_list ) )
    {
        foreach( $messages_list as $message_id => $full_message_arr )
        {
            $message_link = PHS::url( array( 'p' => 'messages', 'a' => 'view_message' ), array( 'muid' => $full_message_arr['message_user']['id'] ) );

            ?><div class="pop_msg <?php echo ($messages_model->is_new( $full_message_arr )?'pop_msg_new':'')?>">
            <div class="pop_msg_title"><a href="<?php echo $message_link?>"><?php echo $full_message_arr['message']['subject']?></a></div>
            <div class="pop_msg_date">
                <?php echo date( 'Y-M-d H:i', parse_db_date( $full_message_arr['message_user']['cdate'] ) )?>
                <div class="pop_msg_actions">
                <?php
                if( $can_reply_messages
                and $messages_model->can_reply( $full_message_arr, array( 'account_data' => $current_user ) ) )
                {
                    ?> <a href="<?php echo PHS::url( $compose_route_arr, array( 'reply_to_muid' => $full_message_arr['message_user']['id'] ) )?>"><i class="fa fa-reply action-icons" title="<?php echo $this->_pt( 'Reply' )?>"></i></a> <?php
                }
                ?>
                <a href="<?php echo PHS_ajax::url( array( 'p' => 'messages', 'a' => 'inbox' ), array( 'pag_act' => 'do_delete', 'pag_act_params' => $full_message_arr['message_user']['id'] ) )?>"><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete' )?>"></i></a>
                </div>
            </div>
            </div>
            <?php
        }
    }
?>

</div>
</div>
<div class="clearfix"></div>
<?php
