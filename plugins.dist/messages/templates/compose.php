<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Roles;
    use \phs\libraries\PHS_Hooks;

    if( !($dest_types = $this->view_var( 'dest_types' )) )
        $dest_types = array();
    if( !($user_levels = $this->view_var( 'user_levels' )) )
        $user_levels = array();
    if( !($roles_arr = $this->view_var( 'roles_arr' )) )
        $roles_arr = array();
    if( !($roles_units_arr = $this->view_var( 'roles_units_arr' )) )
        $roles_units_arr = array();

    /** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
    if( !($messages_model = $this->view_var( 'messages_model' )) )
        $messages_model = false;
    /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
    if( !($accounts_model = $this->view_var( 'accounts_model' )) )
        $accounts_model = false;
    /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
    if( !($roles_model = $this->view_var( 'roles_model' )) )
        $roles_model = false;
    /** @var \phs\plugins\messages\PHS_Plugin_Messages $messages_plugin */
    if( !($messages_plugin = $this->view_var( 'messages_plugin' )) )
        $messages_plugin = false;

    if( !($reply_message = $this->view_var( 'reply_message' )) )
        $reply_message = false;
    if( !($reply_to = $this->view_var( 'reply_to' )) )
        $reply_to = 0;
    if( !($reply_to_muid = $this->view_var( 'reply_to_muid' )) )
        $reply_to_muid = 0;

    if( !($followup_message = $this->view_var( 'followup_message' )) )
        $followup_message = false;
    if( !($follow_up = $this->view_var( 'follow_up' )) )
        $follow_up = 0;
    if( !($follow_up_muid = $this->view_var( 'follow_up_muid' )) )
        $follow_up_muid = 0;

    $url_args_arr = array();
    if( !empty( $reply_to ) )
        $url_args_arr['reply_to'] = $reply_to;
    if( !empty( $reply_to_muid ) )
        $url_args_arr['reply_to_muid'] = $reply_to_muid;
    if( !empty( $follow_up ) )
        $url_args_arr['follow_up'] = $follow_up;
    if( !empty( $follow_up_muid ) )
        $url_args_arr['follow_up_muid'] = $follow_up_muid;

    $current_user = PHS::current_user();

    if( $this->is_admin_controller() )
        $compose_path = array( 'p' => 'messages', 'c' => 'admin', 'a' => 'compose' );
    else
        $compose_path = array( 'p' => 'messages', 'a' => 'compose' );

    $msg_type = false;
    $msg_type_id = false;
    if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_SET_TYPE_IN_COMPOSE ) )
    {
        if( !($msg_type = $this->view_var( 'msg_type' )) )
            $msg_type = false;
        if( !($msg_type_id = $this->view_var( 'msg_type_id' )) )
            $msg_type_id = false;
    }

?>
<form id="compose_message_form" name="compose_message_form" action="<?php echo PHS::url( $compose_path, $url_args_arr )?>" method="post">
    <input type="hidden" name="foobar" value="1" />
    <?php
    if( $msg_type !== false )
    {
        ?>
        <input type="hidden" name="msg_type" value="<?php echo form_str( $msg_type )?>" />
        <input type="hidden" name="msg_type_id" value="<?php echo form_str( $msg_type_id )?>" />
        <?php
    }
    ?>

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Compose message' )?></h3>
        </section>
        <div class="clearfix"></div>

        <?php
        if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_ALL_DESTINATIONS ) )
        {
        ?>
        <fieldset class="form-group">
            <label for="dest_type"><?php echo $this->_pt( 'Destination' )?></label>
            <div class="lineform_line">
                <select name="dest_type" id="dest_type" class="chosen-select-nosearch" style="width: 100%;" required="required">
                    <option value=""><?php echo $this->_pt( ' - Choose - ' )?></option>
                    <?php
                        $selected_dest_type = $this->view_var( 'dest_type' );
                        foreach( $dest_types as $key => $text )
                        {
                            ?><option value="<?php echo $key?>" <?php echo ($selected_dest_type==$key?'selected="selected"':'')?>><?php echo $text?></option><?php
                        }
                    ?>
                </select>
            </div>
        </fieldset>

        <fieldset class="form-group" id="dest_type_users_ids_container" data-s2p-source="dest_type"
                  data-s2p-show-dest_type="<?php echo $messages_model::DEST_TYPE_USERS_IDS?>" data-s2p-hide-dest_type="*">
            <label for="dest_type_users_ids"><?php echo $dest_types[$messages_model::DEST_TYPE_USERS_IDS]?></label>
            <div class="lineform_line">
                <input type="text" id="dest_type_users_ids" name="dest_type_users_ids" class="form-control" value="<?php echo form_str( $this->view_var( 'dest_type_users_ids' ) )?>" required="required"
                       placeholder="<?php echo $this->_pt( 'Comma separated user ids' )?>" style="width: 100%;" />
            </div>
        </fieldset>

        <fieldset class="form-group" id="dest_type_users_container" data-s2p-source="dest_type"
                  data-s2p-show-dest_type="<?php echo $messages_model::DEST_TYPE_USERS?>" data-s2p-hide-dest_type="*">
            <label for="dest_type_users"><?php echo $dest_types[$messages_model::DEST_TYPE_USERS]?></label>
            <div class="lineform_line">
                <input type="text" id="dest_type_users" name="dest_type_users" class="form-control" value="<?php echo form_str( $this->view_var( 'dest_type_users' ) )?>" required="required"
                       placeholder="<?php echo $this->_pt( 'Comma separated user nicknames or emails' )?>" style="width: 100%;" />
            </div>
        </fieldset>

        <fieldset class="form-group" id="dest_type_handlers_container" data-s2p-source="dest_type"
                  data-s2p-show-dest_type="<?php echo $messages_model::DEST_TYPE_HANDLERS?>" data-s2p-hide-dest_type="*">
            <label for="dest_type_handlers"><?php echo $dest_types[$messages_model::DEST_TYPE_HANDLERS]?></label>
            <div class="lineform_line">
                <input type="text" id="dest_type_handlers" name="dest_type_handlers" class="form-control" value="<?php echo form_str( $this->view_var( 'dest_type_handlers' ) )?>" required="required"
                       placeholder="<?php echo $this->_pt( 'Comma separated messaging handlers' )?>" style="width: 100%;" />
            </div>
        </fieldset>

        <fieldset class="form-group" id="dest_type_level_container" data-s2p-source="dest_type"
                  data-s2p-show-dest_type="<?php echo $messages_model::DEST_TYPE_LEVEL?>" data-s2p-hide-dest_type="*">
            <label for="dest_type_level"><?php echo $dest_types[$messages_model::DEST_TYPE_LEVEL]?></label>
            <div class="lineform_line">
                <select name="dest_type_level" id="dest_type_level" class="chosen-select-nosearch" style="width: 100%;" required="required">
                    <option value="0"><?php echo $this->_pt( ' - Choose - ' )?></option>
                    <?php
                        $selected_dest_type_level = $this->view_var( 'dest_type_level' );
                        foreach( $user_levels as $key => $text )
                        {
                            ?><option value="<?php echo $key?>" <?php echo ($selected_dest_type_level==$key?'selected="selected"':'')?>><?php echo $text?></option><?php
                        }
                    ?>
                </select>
            </div>
        </fieldset>

        <fieldset class="form-group" id="dest_type_role_container" data-s2p-source="dest_type"
                  data-s2p-show-dest_type="<?php echo $messages_model::DEST_TYPE_ROLE?>" data-s2p-hide-dest_type="*">
            <label for="dest_type_role"><?php echo $dest_types[$messages_model::DEST_TYPE_ROLE]?></label>
            <div class="lineform_line">
                <select name="dest_type_role" id="dest_type_role" class="chosen-select-nosearch" style="width: 100%;" required="required">
                    <option value=""><?php echo $this->_pt( ' - Choose - ' )?></option>
                    <?php
                        $selected_dest_type_role = $this->view_var( 'dest_type_role' );
                        foreach( $roles_arr as $role_id => $role_arr )
                        {
                            ?><option value="<?php echo $role_id?>" <?php echo ($selected_dest_type_role==$role_id?'selected="selected"':'')?>><?php echo $role_arr['name']?></option><?php
                        }
                    ?>
                </select>
            </div>
        </fieldset>

        <fieldset class="form-group" id="dest_type_role_unit_container" data-s2p-source="dest_type"
                  data-s2p-show-dest_type="<?php echo $messages_model::DEST_TYPE_ROLE_UNIT?>" data-s2p-hide-dest_type="*">
            <label for="dest_type_role_unit"><?php echo $dest_types[$messages_model::DEST_TYPE_ROLE_UNIT]?></label>
            <div class="lineform_line">
                <select name="dest_type_role_unit" id="dest_type_role_unit" class="chosen-select-nosearch" style="width: 100%;" required="required">
                    <option value=""><?php echo $this->_pt( ' - Choose - ' )?></option>
                    <?php
                        $selected_dest_type_role_unit = $this->view_var( 'dest_type_role_unit' );
                        foreach( $roles_units_arr as $roleu_id => $roleu_arr )
                        {
                            ?><option value="<?php echo $roleu_id?>" <?php echo ($selected_dest_type_role_unit==$roleu_id?'selected="selected"':'')?>><?php echo $roleu_arr['name']?></option><?php
                        }
                    ?>
                </select>
            </div>
        </fieldset>
        <?php
        } else
        {
            ?>
            <fieldset class="form-group">
                <label for="dest_type_handlers"><?php echo $this->_pt( 'Destination' )?></label>
                <div class="lineform_line">
                    <?php
                    if( !empty( $reply_message ) )
                    {
                        echo $this->view_var( 'dest_type_handlers' );
                    } elseif( !empty( $followup_message ) )
                    {
                        echo $messages_model->get_destination_as_string( $followup_message['message'] );
                    } else
                    {
                        ?>
                        <input type="text" id="dest_type_handlers" name="dest_type_handlers" class="form-control" value="<?php echo form_str( $this->view_var( 'dest_type_handlers' ) )?>" required="required"
                               placeholder="<?php echo $this->_pt( 'Comma separated messaging handlers' )?>" style="width: 100%;" />
                        <?php
                    }
                    ?>
                </div>
            </fieldset>
            <?php
        }
        ?>

        <fieldset class="form-group">
            <label for="subject"><?php echo $this->_pt( 'Subject' )?></label>
            <div class="lineform_line">
                <?php
                if( !empty( $reply_message ) )
                {
                    echo $this->view_var( 'subject' );
                } else
                {
                    ?>
                    <input type="text" id="subject" name="subject" class="form-control" value="<?php echo form_str( $this->view_var( 'subject' ) )?>" required="required"
                           placeholder="<?php echo $this->_pt( 'Message subject...' )?>" style="width: 100%;" />
                    <?php
                }
                ?>
            </div>
        </fieldset>

        <?php
        if( PHS_Roles::user_has_role_units( $current_user, $messages_plugin::ROLEU_NO_REPLY_OPTION ) )
        {
        ?>
        <fieldset class="form-group">
            <label for="cannot_reply"><?php echo $this->_pt( 'Destination cannot reply' )?></label>
            <div class="lineform_line">
                <div style="float:left;margin:5px;"><input type="checkbox" id="cannot_reply" name="cannot_reply" value="1" rel="skin_checkbox" <?php echo ($this->view_var( 'cannot_reply' )?'checked="checked"':'')?> /></div>
                <label for="cannot_reply" style="width:90%;max-width:100%;"><?php echo $this->_pt( 'Destination will not be able to reply to this message.' )?></label>
            </div>
        </fieldset>
        <?php
        }

        $hook_args = PHS_Hooks::default_buffer_hook_args();
        $hook_args['view_obj'] = $this;
        $hook_args['buffer_data'] = $this->get_all_view_vars();

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_MSG_RENDER_WRITE_FORM, $hook_args ))
        and is_array( $hook_args )
        and !empty( $hook_args['buffer'] ) )
            echo $hook_args['buffer'];
        ?>

        <fieldset class="form-group">
            <label for="body"><?php echo $this->_pt( 'Body' )?></label>
            <div class="lineform_line">
                <textarea id="body" name="body" class="form-control" required="required"
                       placeholder="<?php echo $this->_pt( 'Message body...' )?>" style="width: 560px;height:400px;"><?php echo textarea_str( $this->view_var( 'body' ) )?></textarea>
            </div>
        </fieldset>

        <fieldset>
            <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection" value="<?php echo $this->_pte( 'Send Message' )?>" />
        </fieldset>

    </div>
</form>
