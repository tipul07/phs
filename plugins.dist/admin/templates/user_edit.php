<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Utils;

    /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
    /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
    if( !($roles_model = $this->view_var( 'roles_model' ))
     or !($plugins_model = $this->view_var( 'plugins_model' )) )
        return $this->_pt( 'Couldn\'t load roles model.' );

    if( !($accounts_plugin_settings = $this->view_var( 'accounts_plugin_settings' )) )
        $accounts_plugin_settings = array();

    if( !($user_levels = $this->view_var( 'user_levels' )) )
        $user_levels = array();

    if( !($account_roles = $this->view_var( 'account_roles' )) )
        $account_roles = array();
    if( !($roles_by_slug = $this->view_var( 'roles_by_slug' )) )
        $roles_by_slug = array();

    $current_user = PHS::user_logged_in();
?>
<form id="edit_user_form" name="edit_user_form" action="<?php echo PHS::url( array( 'p' => 'admin', 'a' => 'user_edit' ), array( 'uid' => $this->view_var( 'uid' ) ) )?>" method="post">
<div style="min-width:100%;max-width:1000px;margin: 0 auto;">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container responsive" style="width: 650px;">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Edit User Account' )?></h3>
        </section>

        <fieldset class="form-group">
            <label for="nick"><?php echo $this->_pt( 'Username' )?></label>
            <div class="lineform_line">
            <input type="text" id="nick" name="nick" class="form-control" required="required" value="<?php echo form_str( $this->view_var( 'nick' ) )?>" style="width: 260px;" autocomplete="off" /><br/>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="email"><?php echo $this->_pt( 'Email' )?></label>
            <div class="lineform_line">
            <input type="text" id="email" name="email" class="form-control" <?php echo (!empty( $accounts_plugin_settings['email_mandatory'] )?'required="required"':'')?> value="<?php echo form_str( $this->view_var( 'email' ) )?>" style="width: 260px;" autocomplete="off" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="level"><?php echo $this->_pt( 'Level' )?></label>
            <div class="lineform_line">
            <select name="level" id="level" class="chosen-select-nosearch" style="width:260px;">
                <option value="0"><?php echo $this->_pt( ' - Choose - ' )?></option>
                <?php
                foreach( $user_levels as $key => $level_details )
                {
                    if( $key > $current_user['level'] )
                        break;

                    ?><option value="<?php echo $key?>" <?php echo ($this->view_var( 'level' )==$key?'selected="selected"':'')?>><?php echo $level_details['title']?></option><?php
                }
                ?>
            </select>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="level"><?php echo $this->_pt( 'Roles' )?></label>
            <div class="lineform_line">
            <div id="account_current_roles"></div>
            <a href="javascript:void(0)" onclick="open_roles_dialogue()"><?php echo $this->_pt( 'Change roles' )?></a>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="title"><?php echo $this->_pt( 'Title' )?></label>
            <div class="lineform_line">
            <input type="text" id="title" name="title" class="form-control" value="<?php echo form_str( $this->view_var( 'title' ) )?>" style="width: 60px;" autocomplete="off" /><br/>
            <small><?php echo $this->_pt( 'eg. Mr., Ms., Mrs., etc' )?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="fname"><?php echo $this->_pt( 'First Name' )?></label>
            <div class="lineform_line">
            <input type="text" id="fname" name="fname" class="form-control" value="<?php echo form_str( $this->view_var( 'fname' ) )?>" style="width: 260px;" autocomplete="off" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="lname"><?php echo $this->_pt( 'Last Name' )?></label>
            <div class="lineform_line">
            <input type="text" id="lname" name="lname" class="form-control" value="<?php echo form_str( $this->view_var( 'lname' ) )?>" style="width: 260px;" autocomplete="off" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="phone"><?php echo $this->_pt( 'Phone Number' )?></label>
            <div class="lineform_line">
            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo form_str( $this->view_var( 'phone' ) )?>" style="width: 260px;" autocomplete="off" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="company"><?php echo $this->_pt( 'Company' )?></label>
            <div class="lineform_line">
            <input type="text" id="company" name="company" class="form-control" value="<?php echo form_str( $this->view_var( 'company' ) )?>" style="width: 260px;" autocomplete="off" />
            </div>
        </fieldset>

        <fieldset>
            <small><?php echo $this->_pt( 'Complete password fields ony if you want to change password' )?></small>
        </fieldset>

        <fieldset class="form-group">
            <label for="pass"><?php echo $this->_pt( 'Password' )?></label>
            <div class="lineform_line">
            <input type="password" id="pass" name="pass" class="form-control" value="<?php echo form_str( $this->view_var( 'pass' ) )?>" style="width: 260px;" autocomplete="off" /><br/>
            <small><?php

                echo $this->_pt( 'Password should be at least %s characters.', $this->view_var( 'min_password_length' ) );

                $pass_regexp = $this->view_var( 'password_regexp' );
                if( !empty( $pass_regexp ) )
                {
                    echo '<br/>'.$this->_pt( 'Password should pass regular expresion: ' );

                    if( ($regexp_parts = explode( '/', $pass_regexp ))
                        and !empty( $regexp_parts[1] ) )
                    {
                        if( empty($regexp_parts[2]) )
                            $regexp_parts[2] = '';

                        ?><a href="https://regex101.com/?regex=<?php echo rawurlencode( $regexp_parts[1] )?>&options=<?php echo $regexp_parts[2]?>" title="Click for details" target="_blank"><?php echo $pass_regexp?></a><?php
                    } else
                        echo $this->_pt( 'Password should pass regular expresion: %s.', $pass_regexp );
                }

            ?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="pass2"><?php echo $this->_pt( 'Password' )?></label>
            <div class="lineform_line">
            <input type="password" id="pass2" name="pass2" class="form-control" value="<?php echo form_str( $this->view_var( 'pass2' ) )?>" style="width: 260px;" autocomplete="off" />
            (<small><?php echo $this->_pt( 'confirm' )?></small>)
            </div>
        </fieldset>

        <fieldset>
            <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte( 'Save Changes' )?>" />
        </fieldset>

    </div>
</div>
<div class="clearfix"></div>

<div style="display: none;" id="account_roles_container">

    <div>
    <?php
    if( !empty( $roles_by_slug ) and is_array( $roles_by_slug ) )
    {
        $old_plugin = false;
        $plugin_name = $this->_pt( 'Manually added' );
        $did_autofocus = false;
        foreach( $roles_by_slug as $role_slug => $role_arr )
        {
            if( $roles_model->is_deleted( $role_arr ) )
                continue;

            if( $old_plugin !== $role_arr['plugin'] )
            {
                if( !($plugin_name = $plugins_model->get_plugin_name_by_slug( $role_arr['plugin'] )) )
                    $plugin_name = $this->_pt( 'Manually added' );

                if( $old_plugin !== false )
                {
                    ?><div style="margin-bottom:10px;"></div><?php
                }
                ?>
                <section class="heading-bordered">
                    <h4><?php echo $plugin_name?></h4>
                </section>
                <?php
                $old_plugin = $role_arr['plugin'];
            }
            ?>
            <div class="clearfix">
            <div style="float:left;"><input type="checkbox" id="account_roles_slugs_<?php echo $role_slug ?>"
                                            name="account_roles_slugs[]" value="<?php echo form_str( $role_slug )?>" rel="skin_checkbox"
                                            data-role-title="<?php echo form_str( $role_arr['name'] )?>"
                                            data-role-slug="<?php echo form_str( $role_slug )?>"
                                            data-role-plugin="<?php echo form_str( $role_arr['plugin'] )?>"
                                            data-role-plugin-name="<?php echo form_str( $plugin_name )?>"
                                            <?php echo (in_array( $role_slug, $account_roles ) ? 'checked="checked"' : '')?>
                                            <?php echo (!$did_autofocus?'autofocus="true"':'')?> /></div>
            <label style="margin-left:5px;width: auto !important;float:left;" for="account_roles_slugs_<?php echo $role_slug ?>">
                <?php echo $role_arr['name']?>
                <i class="fa fa-question-circle" title="<?php echo form_str( $role_arr['description'] )?>"></i>
            </label>
            </div>
            <?php

            $did_autofocus = true;
        }
    }
    ?>
    </div>
    <div class="clearfix"></div>
    <div>
    <div style="float:right;"><input type="button" id="do_close_roles_dialogue" name="do_reject_doc_cancel" class="btn btn-primary btn-small" value="<?php echo $this->_pt( 'Close' )?>" onclick="close_roles_dialogue()" /></div>
    </div>
</div>
</form>

<script type="text/javascript">
function close_roles_dialogue()
{
    PHS_JSEN.closeAjaxDialog( 'user_roles_' );
}
function open_roles_dialogue()
{
    var container_obj = $("#account_roles_container");
    if( !container_obj )
        return;

    container_obj.show();

    PHS_JSEN.createAjaxDialog( {
        suffix: 'user_roles_',
        width: 800,
        height: 600,
        title: "<?php echo $this->_pte( 'Account Roles' )?>",
        resizable: false,
        source_obj: container_obj,
        source_not_cloned: true,
        onbeforeclose: closing_roles_dialogue
    });
}
function closing_roles_dialogue()
{
    var container_obj = $("#account_roles_container");
    if( !container_obj )
        return;

    container_obj.hide();

    update_selected_roles();
}
function update_selected_roles()
{
    var roles_container_obj = $("#account_current_roles");
    if( !roles_container_obj )
        return;

    var old_slug = false;

    var selected_roles = [];
    $("input:checked[name=account_roles_slugs\\[\\]").each(function(){
        var role_title = $(this).data( 'roleTitle' );
        if( role_title && role_title.length )
        {
            var plugin_slug = $(this).data( 'rolePlugin' );
            var plugin_name = $(this).data( 'rolePluginName' );

            if( !plugin_slug || !plugin_slug.length )
                plugin_slug = "";

            if( plugin_name && plugin_name.length
             && old_slug !== plugin_slug )
            {
                var prefix = " - ";
                if( old_slug !== false )
                    prefix = "<br/> - ";

                role_title = prefix + "<strong>" + plugin_name + "</strong>: " + role_title;

                old_slug = plugin_slug;
            }

            selected_roles.push( role_title );
        }
    });

    if( selected_roles.length )
        roles_container_obj.html( selected_roles.join( ', ' ) + '.' );
    else
        roles_container_obj.html( "<em><?php echo $this->_pte( 'No role assigned to this account.' )?></em>" );
}

update_selected_roles();
</script>
