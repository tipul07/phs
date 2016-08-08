<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;
    use \phs\libraries\PHS_Roles;

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

    $compose_url = false;
    $compose_route_arr = array( 'p' => 'messages', 'a' => 'compose' );
    if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_WRITE_MESSAGE ) )
        $compose_url = PHS::url( $compose_route_arr );

?><script type="text/javascript">
function phs_close_messages_summary_popup()
{
    $("#<?php echo (!empty( $summary_container_id )?$summary_container_id:'messages-summary-popup')?>").hide();
}
</script><div id="messages-summary-popup">
<div id="messages-summary-popup-title">
    <a href="javascript:void(0)">Inbox (<?php echo $messages_new?>/<?php echo $messages_count?>)</a>
    |
    <a href="javascript:void(0)">Compose</a>
    <div style="float:right; margin: 0 15px 0 0; cursor: pointer;" onclick="phs_close_messages_summary_popup()"><i class="fa fa-times-circle"></i></div>
</div>
<div id="messages-summary-popup-content">
<div id="phs_ajax_new_messages" style="display:none;"></div>
<?php
    if( !empty( $messages_list ) and is_array( $messages_list ) )
    {
        foreach( $messages_list as $message_id => $full_message_arr )
        {
            ?><div class="pop_msg <?php echo ($messages_model->is_new( $full_message_arr )?'pop_msg_new':'')?>">
            <div class="pop_msg_title"><?php echo $full_message_arr['title']?></div>
            <div class="pop_msg_date">
                2016-03-04 12:34
                <div class="pop_msg_actions">
                <a href="javascript:void(0)"><i class="fa fa-reply action-icons" title="<?php echo $this->_pt( 'Reply' )?>"></i></a>
                <a href="javascript:void(0)"><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete' )?>"></i></a>
                </div>
            </div>
            <?php
        }
    }
?>
<div class="pop_msg">
    <div class="pop_msg_title"><strong>Message title...  fsdsd fsd fsdf asdg sdfgdfsa gsdfgsdf g dfg sdfgsdfgsdfg sdfgs dfgsdfg</strong></div>
    <div class="pop_msg_date">
        2016-03-04 12:34
        <div class="pop_msg_actions">
        <a href="javascript:void(0)"><i class="fa fa-reply action-icons" title="<?php echo $this->_pt( 'Reply' )?>"></i></a>
        <a href="javascript:void(0)"><i class="fa fa-times-circle-o action-icons" title="<?php echo $this->_pt( 'Delete' )?>"></i></a>
        </div>
    </div>
</div>

</div>
</div>
<div class="clearfix"></div>
<?php
