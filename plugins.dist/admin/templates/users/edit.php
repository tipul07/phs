<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Tenants;

/** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
/** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
if (!($roles_model = $this->view_var('roles_model'))
    || !($accounts_plugin = $this->view_var('accounts_plugin'))
    || !($plugins_model = $this->view_var('plugins_model'))
    || !($account_arr = $this->view_var('account_data'))) {
    return $this->_pt('Error loading required resources.');
}

/** @var null|\phs\system\core\models\PHS_Model_Tenants $tenants_model */
$tenants_model = $this->view_var('tenants_model') ?: null;

$is_multi_tenant = PHS::is_multi_tenant();

$accounts_plugin_settings = $this->view_var('accounts_plugin_settings') ?: [];

$back_page = $this->view_var('back_page') ?: '';

$user_levels = $this->view_var('user_levels') ?: [];

$account_roles = $this->view_var('account_roles') ?: [];
$roles_by_slug = $this->view_var('roles_by_slug') ?: [];

$db_account_tenants = $this->view_var('db_account_tenants') ?: [];
$all_tenants_arr = $this->view_var('all_tenants_arr') ?: [];

$current_user = PHS::user_logged_in();
?>
<form id="edit_user_form" name="edit_user_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'users'],
          ['uid' => $this->view_var('uid')]); ?>">
<input type="hidden" name="foobar" value="1" />
<?php
if (!empty($back_page)) {
    ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
}
?>

<div class="form_container">

    <?php
    if (!empty($back_page)) {
        ?><i class="fa fa-chevron-left"></i> <a href="<?php echo form_str(from_safe_url($back_page)); ?>"><?php echo $this->_pt('Back'); ?></a><?php
    }
?>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Edit User Account'); ?></h3>
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
        <label for="email" class="col-sm-2 col-form-label"><?php echo $this->_pt('Email'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="email" name="email" class="form-control" autocomplete="email"
                <?php echo $accounts_plugin->registration_email_mandatory() ? 'required="required"' : ''; ?>
                   value="<?php echo form_str($this->view_var('email')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="level" class="col-sm-2 col-form-label"><?php echo $this->_pt('Level'); ?></label>
        <div class="col-sm-10"><?php
        if ((int)$account_arr['level'] === (int)$current_user['level']) {
            echo !empty($user_levels[$account_arr['level']]) ? $user_levels[$account_arr['level']]['title'] : $this->_pt('N/A');
        } else {
            ?>
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
                <?php
        }
?>
        </div>
    </div>

    <div class="form-group row">
        <label for="account_current_roles" class="col-sm-2 col-form-label"><?php echo $this->_pt('Roles'); ?></label>
        <div class="col-sm-10">
            <div id="account_current_roles"></div>
            <a href="javascript:void(0)" onclick="open_roles_dialogue();this.blur();"
               class="btn btn-small btn-primary"><?php echo $this->_pt('Change roles'); ?></a>
        </div>
    </div>

    <?php
    if ($is_multi_tenant) {
        ?>
    <div class="form-group row">
        <label for="account_current_tenants" class="col-sm-2 col-form-label"><?php echo $this->_pt('Tenants'); ?></label>
        <div class="col-sm-10">
            <div id="account_current_tenants"></div>
            <a href="javascript:void(0)" onclick="open_tenants_dialogue();this.blur();"
               class="btn btn-small btn-primary"><?php echo $this->_pt('Change tenants'); ?></a>
        </div>
    </div>
    <?php } ?>

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

    <fieldset>
        <small><?php echo $this->_pt('Complete password fields ony if you want to change password'); ?></small>
    </fieldset>

    <div class="form-group row">
        <label for="pass" class="col-sm-2 col-form-label"><?php echo $this->_pt('Password'); ?></label>
        <div class="col-sm-10">
        <input type="password" id="pass" name="pass" class="form-control" autocomplete="pass"
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
                         title="<?php echo $this->_pte('Click for details'); ?>"
                         target="_blank"><?php echo $pass_regexp; ?></a><?php
    } else {
        echo $this->_pt('Password should pass regular expresion: %s.', $pass_regexp);
    }
}
?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="pass2" class="col-sm-2 col-form-label"><?php echo $this->_pt('Password'); ?> (<?php echo $this->_pt('confirm'); ?>)</label>
        <div class="col-sm-10">
            <input type="password" id="pass2" name="pass2" class="form-control"
                   autocomplete="pass2"
                   value="<?php echo form_str($this->view_var('pass2')); ?>" />
        </div>
    </div>

    <div class="form-group row">
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pt('Save Changes'); ?>" />
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
            <div class="mb-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="account_roles_slugs[]" id="account_roles_slugs_<?php echo $role_slug; ?>"
                           data-role-title="<?php echo form_str($role_arr['name']); ?>"
                           data-role-slug="<?php echo form_str($role_slug); ?>"
                           data-role-plugin="<?php echo form_str($role_arr['plugin']); ?>"
                           data-role-plugin-name="<?php echo form_str($plugin_name); ?>"
                           <?php echo in_array($role_slug, $account_roles, true) ? 'checked="checked"' : ''; ?>
                           value="<?php echo form_str($role_slug); ?>"
                           <?php echo !$did_autofocus ? 'autofocus="true"' : ''; ?> />
                    <label class="form-check-label" for="account_roles_slugs_<?php echo $role_slug; ?>">
                        <?php echo $role_arr['name']; ?>
                        <i class="fa fa-question-circle" title="<?php echo form_str($role_arr['description']); ?>"></i>
                    </label>
                </div>
            </div>
            <?php

        $did_autofocus = true;
    }
}
?>
    </div>
    <div class="float-right p-2">
        <input type="button" id="do_close_roles_dialogue" name="do_close_roles_dialogue" class="btn btn-primary btn-small"
               value="<?php echo $this->_pt('Close'); ?>" onclick="close_roles_dialogue()" />
    </div>
</div>

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
        //width: 800,
        height: 600,
        title: "<?php echo $this->_pte('Account Roles'); ?>",
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
    var container_obj = $("#account_current_roles");
    if( !container_obj )
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
        container_obj.html( selected_roles.join( ', ' ) + '.' );
    else
        container_obj.html( "<em><?php echo $this->_pte('No role assigned to this account.'); ?></em>" );
}

update_selected_roles();
</script>
<?php
if ($is_multi_tenant) {
    ?>

<div style="display: none;" id="account_tenants_container">

    <div>
    <?php
if (!empty($all_tenants_arr) && is_array($all_tenants_arr)) {
    $old_domain = null;
    $did_autofocus = false;
    foreach ($all_tenants_arr as $t_id => $t_arr) {
        if ($tenants_model && $tenants_model->is_deleted($t_arr)) {
            continue;
        }

        if ($old_domain !== $t_arr['domain']) {
            if ($old_domain !== null) {
                ?><div style="margin-bottom:10px;"></div><?php
            }
            ?>
                <section class="heading-bordered">
                    <h4><?php echo $t_arr['domain']; ?></h4>
                </section>
                <?php
            $old_domain = $t_arr['domain'];
        }
        ?>
        <div class="mb-1">
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       name="account_tenants[]" id="account_tenants_<?php echo $t_id; ?>"
                       data-tenant-id="<?php echo form_str($t_id); ?>"
                       data-tenant-name="<?php echo form_str($t_arr['name']); ?>"
                       data-tenant-domain="<?php echo form_str($t_arr['domain']); ?>"
                       <?php echo in_array($t_id, $db_account_tenants, true) ? 'checked="checked"' : ''; ?>
                       value="<?php echo form_str($t_id); ?>"
                       <?php echo !$did_autofocus ? 'autofocus="true"' : ''; ?> />
                <label class="form-check-label" for="account_tenants_<?php echo $t_id; ?>">
                    <?php echo PHS_Tenants::get_tenant_details_for_display($t_arr); ?>
                </label>
            </div>
        </div>
        <?php

        $did_autofocus = true;
    }
}
    ?>
    </div>
    <div class="float-right p-2">
        <input type="button" id="do_close_tenant_dialogue" name="do_close_tenant_dialogue" class="btn btn-primary btn-small"
               value="<?php echo $this->_pt('Close'); ?>" onclick="close_tenants_dialogue()" />
    </div>
</div>

<script type="text/javascript">
function close_tenants_dialogue()
{
    PHS_JSEN.closeAjaxDialog( 'user_tenants_' );
}
function open_tenants_dialogue()
{
    var container_obj = $("#account_tenants_container");
    if( !container_obj )
        return;

    container_obj.show();

    PHS_JSEN.createAjaxDialog( {
        suffix: 'user_tenants_',
        //width: 800,
        height: 600,
        title: "<?php echo $this->_pte('Account Tenants'); ?>",
        resizable: false,
        source_obj: container_obj,
        source_not_cloned: true,
        onbeforeclose: closing_tenants_dialogue
    });
}
function closing_tenants_dialogue()
{
    var container_obj = $("#account_tenants_container");
    if( !container_obj )
        return;

    container_obj.hide();

    update_selected_tenants();
}
function update_selected_tenants()
{
    var container_obj = $("#account_current_tenants");
    if( !container_obj )
        return;

    var old_domain = false;

    var selected_tenants = [];
    $("input:checked[name=account_tenants\\[\\]").each(function(){
        var tenant_name = $(this).data( 'tenantName' );
        if( tenant_name && tenant_name.length )
        {
            var tenant_domain = $(this).data( 'tenantDomain' );

            if( !tenant_domain || !tenant_domain.length )
                tenant_domain = "";

            if( old_domain !== tenant_domain )
            {
                var prefix = " - ";
                if( old_domain !== false )
                    prefix = "<br/> - ";

                tenant_name = prefix + "<strong>" + tenant_domain + "</strong>: " + tenant_name;

                old_domain = tenant_domain;
            }

            selected_tenants.push( tenant_name );
        }
    });

    if( selected_tenants.length )
        container_obj.html( selected_tenants.join( ', ' ) + '.' );
    else
        container_obj.html( "<strong><?php echo $this->_pte('ALL TENANTS'); ?></strong>" );
}

update_selected_tenants();
</script>
<?php } ?>
</form>
