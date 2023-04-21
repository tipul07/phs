<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Utils;

/** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
/** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
if (!($roles_model = $this->view_var('roles_model'))
    || !($accounts_plugin = $this->view_var('accounts_plugin'))
    || !($plugins_model = $this->view_var('plugins_model'))) {
    return $this->_pt('Couldn\'t load roles model.');
}

if (!($accounts_plugin_settings = $this->view_var('accounts_plugin_settings'))) {
    $accounts_plugin_settings = [];
}

if (!($user_levels = $this->view_var('user_levels'))) {
    $user_levels = [];
}

if (!($roles_by_slug = $this->view_var('roles_by_slug'))) {
    $roles_by_slug = [];
}

$current_user = PHS::user_logged_in();
?>
<form id="add_user_form" name="add_user_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'add', 'ad' => 'users']); ?>">
<input type="hidden" name="foobar" value="1" />

<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Add User Account'); ?></h3>
    </section>

    <div class="form-group row">
        <label for="nick" class="col-sm-2 col-form-label"><?php echo $this->_pt('Username'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="nick" name="nick" class="form-control"
                   required="required" autocomplete="nick"
                   value="<?php echo form_str($this->view_var('nick')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="pass" class="col-sm-2 col-form-label"><?php echo $this->_pt('Password'); ?></label>
        <div class="col-sm-10">
            <input type="password" id="pass" name="pass" class="form-control" autocomplete="pass"
                   <?php echo $accounts_plugin->registration_password_mandatory() ? 'required="required"' : ''; ?>
                   value="<?php echo form_str($this->view_var('pass')); ?>" />
            <div id="password_help" class="form-text"><?php

                echo $this->_pt('Password should be at least %s characters.', $this->view_var('min_password_length'));

$pass_regexp = $this->view_var('password_regexp');
if (!empty($pass_regexp)) {
    echo ' '.$this->_pt('Password should pass regular expresion: ');

    if (($regexp_parts = explode('/', $pass_regexp))
        && !empty($regexp_parts[1])) {
        if (empty($regexp_parts[2])) {
            $regexp_parts[2] = '';
        }

        ?><a href="https://regex101.com/?regex=<?php echo rawurlencode($regexp_parts[1]); ?>&options=<?php echo $regexp_parts[2]; ?>"
                             title="Click for details" target="_blank"><?php echo $pass_regexp; ?></a><?php
    } else {
        echo $this->_pt('Password should pass regular expresion: %s.', $pass_regexp);
    }
}

echo ' '.$this->_pt('If password field is left empty, system will generate a password and will send it by email to the provided email.');

?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="email" class="col-sm-2 col-form-label"><?php echo $this->_pt('Email'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="email" name="email" class="form-control" autocomplete="email"
                <?php echo !empty($accounts_plugin_settings['email_mandatory']) ? 'required="required"' : ''; ?>
                   value="<?php echo form_str($this->view_var('email')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="level" class="col-sm-2 col-form-label"><?php echo $this->_pt('Level'); ?></label>
        <div class="col-sm-10">
            <select name="level" id="level" class="chosen-select-nosearch" style="min-width:260px;">
                <option value="0"><?php echo $this->_pt(' - Choose - '); ?></option>
                <?php
    $current_level = (int)$this->view_var('level');
foreach ($user_levels as $key => $level_details) {
    if ($key >= $current_user['level']) {
        break;
    }

    ?><option value="<?php echo $key; ?>" <?php echo $current_level === $key ? 'selected="selected"' : ''; ?>><?php echo $level_details['title']; ?></option><?php
}
?>
            </select>
        </div>
    </div>

    <div class="form-group row">
        <label for="level" class="col-sm-2 col-form-label"><?php echo $this->_pt('Roles'); ?></label>
        <div class="col-sm-10">
            <div id="account_current_roles"></div>
            <a href="javascript:void(0)" onclick="open_roles_dialogue();this.blur();"
               class="btn btn-small btn-primary"><?php echo $this->_pt('Change roles'); ?></a>
            <div id="roles_help" class="form-text"><?php echo $this->_pt('If no roles are provided, roles will be set depending on selected level.'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="title" class="col-sm-2 col-form-label"><?php echo $this->_pt('Title'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="title" name="title" class="form-control"
                   style="width: 60px;" autocomplete="title"
                   value="<?php echo form_str($this->view_var('title')); ?>" />
            <div id="roles_help" class="form-text"><?php echo $this::_t('eg. Mr., Ms., Mrs., etc'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="fname" class="col-sm-2 col-form-label"><?php echo $this->_pt('First Name'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="fname" name="fname" class="form-control" autocomplete="fname"
                   value="<?php echo form_str($this->view_var('fname')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="lname" class="col-sm-2 col-form-label"><?php echo $this->_pt('Last Name'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="lname" name="lname" class="form-control" autocomplete="lname"
                   value="<?php echo form_str($this->view_var('lname')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="phone" class="col-sm-2 col-form-label"><?php echo $this->_pt('Phone Number'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="phone" name="phone" class="form-control" autocomplete="phone"
                   value="<?php echo form_str($this->view_var('phone')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="company" class="col-sm-2 col-form-label"><?php echo $this->_pt('Company'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="company" name="company" class="form-control" autocomplete="company"
                   value="<?php echo form_str($this->view_var('company')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pt('Create Account'); ?>" />
    </div>

</div>

<div style="display: none;" id="account_roles_container">

    <div>
    <?php
if (!empty($roles_by_slug) && is_array($roles_by_slug)) {
    $old_plugin = false;
    $plugin_name = $this->_pt('Manually added');
    $did_autofocus = false;
    foreach ($roles_by_slug as $role_slug => $role_arr) {
        if ($roles_model->is_deleted($role_arr)) {
            continue;
        }

        if ($old_plugin !== $role_arr['plugin']) {
            if (!($plugin_name = $plugins_model->get_plugin_name_by_slug($role_arr['plugin']))) {
                $plugin_name = $this->_pt('Manually added');
            }

            if ($old_plugin !== false) {
                ?><div style="margin-bottom:10px;"></div><?php
            }
            ?>
                <section class="heading-bordered">
                    <h4><?php echo $plugin_name; ?></h4>
                </section>
                <?php
            $old_plugin = $role_arr['plugin'];
        }
        ?>
            <div class="clearfix">
            <div style="float:left;"><input type="checkbox" id="account_roles_slugs_<?php echo $role_slug; ?>"
                                            name="account_roles_slugs[]" value="<?php echo form_str($role_slug); ?>" rel="skin_checkbox"
                                            data-role-title="<?php echo form_str($role_arr['name']); ?>"
                                            data-role-slug="<?php echo form_str($role_slug); ?>"
                                            data-role-plugin="<?php echo form_str($role_arr['plugin']); ?>"
                                            data-role-plugin-name="<?php echo form_str($plugin_name); ?>"
                                            <?php echo !$did_autofocus ? 'autofocus="true"' : ''; ?> /></div>
            <label style="margin-left:5px;width: auto !important;float:left;" for="account_roles_slugs_<?php echo $role_slug; ?>">
                <?php echo $role_arr['name']; ?>
                <i class="fa fa-question-circle" title="<?php echo form_str($role_arr['description']); ?>"></i>
            </label>
            </div>
            <?php

        $did_autofocus = true;
    }
}
?>
    </div>
    <div class="float-right p-2">
        <input type="button" id="do_close_roles_dialogue" name="do_reject_doc_cancel" class="btn btn-primary btn-small"
               value="<?php echo $this->_pt('Close'); ?>" onclick="close_roles_dialogue()" />
    </div>
</div>
</form>

<script type="text/javascript">
function close_roles_dialogue() {
    PHS_JSEN.closeAjaxDialog( 'user_roles_' );
}
function open_roles_dialogue() {
    var container_obj = $("#account_roles_container");
    if( !container_obj )
        return;

    container_obj.show();

    PHS_JSEN.createAjaxDialog( {
        suffix: 'user_roles_',
        width: 800,
        height: 600,
        title: "<?php echo $this->_pte('Account Roles'); ?>",
        resizable: true,
        source_obj: container_obj,
        source_not_cloned: true,
        onbeforeclose: closing_roles_dialogue
    });
}
function closing_roles_dialogue() {
    var container_obj = $("#account_roles_container");
    if( !container_obj )
        return;

    container_obj.hide();

    update_selected_roles();
}
function update_selected_roles() {
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
             && old_slug !== plugin_slug ) {
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
        roles_container_obj.html( "<em><?php echo $this->_pte('No role assigned to this account.'); ?></em>" );
}

update_selected_roles();
</script>
