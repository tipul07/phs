<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Api;

/** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
/** @var \phs\plugins\admin\actions\PHS_Action_Users_autocomplete $users_autocomplete_action */
if (!($apikeys_model = $this->view_var('apikeys_model'))
 || !($users_autocomplete_action = $this->view_var('users_autocomplete_action'))) {
    return $this->_pt('Couldn\'t initialize view.');
}

if (!($api_methods_arr = $this->view_var('api_methods_arr'))) {
    $api_methods_arr = [];
}
if (!($allowed_methods = $this->view_var('allowed_methods'))) {
    $allowed_methods = [];
}
if (!($denied_methods = $this->view_var('denied_methods'))) {
    $denied_methods = [];
}

/** @var null|\phs\system\core\models\PHS_Model_Tenants $tenants_model */
if (!($tenants_model = $this->view_var('tenants_model'))) {
    $tenants_model = null;
}

if (!($all_tenants_arr = $this->view_var('all_tenants_arr'))) {
    $all_tenants_arr = [];
}

$is_multi_tenant = PHS::is_multi_tenant();

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'apikeys']);
}
?>
<form id="edit_apikey_form" name="edit_apikey_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'apikeys'],
          ['aid' => $this->view_var('aid')]); ?>">
    <input type="hidden" name="foobar" value="1" />
    <?php
        if (!empty($back_page)) {
            ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
        }
?>

    <div class="form_container">

        <?php
if (!empty($back_page)) {
    ?><i class="fa fa-chevron-left"></i>
            <a href="<?php echo form_str(from_safe_url($back_page)); ?>"><?php echo $this->_pt('Back'); ?></a><?php
}
?>

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Edit API key'); ?></h3>
        </section>

        <div class="form-group row">
            <label for="autocomplete_uid" class="col-sm-2 col-form-label"><?php echo $this->_pt('User account'); ?></label>
            <div class="col-sm-10">
                <?php echo $users_autocomplete_action->autocomplete_inputs($this->get_all_view_vars()); ?>
            </div>
        </div>

        <?php
        if ($is_multi_tenant) {
            ?>
            <div class="form-group row">
                <label for="tenant_id" class="col-sm-2 col-form-label"><?php echo $this->_pt('Tenants'); ?></label>
                <div class="col-sm-10">
                    <select name="tenant_id" id="tenant_id" class="chosen-select" style="width:100%;">
                        <option value="0"> - <?php echo $this->_pt('All tenants'); ?> - </option>
                        <?php
                        foreach ($all_tenants_arr as $t_id => $t_arr) {
                            ?>
                            <option value="<?php echo $t_id; ?>"
                                <?php echo (int)$t_id === (int)$this->view_var('tenant_id') ? 'selected="selected"' : ''; ?>
                            ><?php echo $t_arr['name'].' ('.$t_arr['domain'].(!empty($t_arr['directory']) ? '/'.$t_arr['directory'] : '').')'; ?></option>
                            <?php
                        }
            ?>
                    </select>
                </div>
            </div>
        <?php } ?>

        <div class="form-group row">
            <label for="title" class="col-sm-2 col-form-label"><?php echo $this->_pt('Title'); ?></label>
            <div class="col-sm-10">
                <input type="text" id="title" name="title" class="form-control"
                       value="<?php echo form_str($this->view_var('title')); ?>" autocomplete="title" />
                <small class="text-muted"><?php echo $this->_pt('Short description for this API key'); ?></small>
            </div>
        </div>

        <div class="form-group row">
            <label for="api_key" class="col-sm-2 col-form-label"><?php echo $this->_pt('API Key'); ?></label>
            <div class="col-sm-10">
                <input type="text" id="api_key" name="api_key" class="form-control"
                       value="<?php echo form_str($this->view_var('api_key')); ?>" autocomplete="api_key" />
            </div>
        </div>

        <div class="form-group row">
            <label for="api_secret" class="col-sm-2 col-form-label"><?php echo $this->_pt('API Secret'); ?></label>
            <div class="col-sm-10">
                <input type="text" id="api_secret" name="api_secret" class="form-control"
                       value="<?php echo form_str($this->view_var('api_secret')); ?>" autocomplete="api_secret" />
            </div>
        </div>

        <div class="form-group row">
            <label for="allowed_methods" class="col-sm-2 col-form-label"><?php echo $this->_pt('Allowed HTTP methods'); ?></label>
            <div class="col-sm-10">
                <?php
                foreach ($api_methods_arr as $api_method) {
                    ?>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" id="allowed_methods_<?php echo $api_method; ?>"
                               name="allowed_methods[]" value="<?php echo form_str($api_method); ?>"
                            <?php echo in_array($api_method, $allowed_methods, true) ? 'checked="checked"' : ''; ?> />
                        <label class="form-check-label" for="allowed_methods_<?php echo $api_method; ?>"><?php echo $api_method; ?></label>
                    </div>
                    <?php
                }
?>
                <small class="text-muted"><?php echo $this->_pt('Don\'t tick any method to allow all.'); ?></small>
            </div>
        </div>

        <div class="form-group row">
            <label for="denied_methods" class="col-sm-2 col-form-label"><?php echo $this->_pt('Denied HTTP methods'); ?></label>
            <div class="col-sm-10">
                <?php
foreach ($api_methods_arr as $api_method) {
    ?>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" id="denied_methods_<?php echo $api_method; ?>"
                               name="denied_methods[]" value="<?php echo form_str($api_method); ?>"
                            <?php echo in_array($api_method, $denied_methods, true) ? 'checked="checked"' : ''; ?> />
                        <label class="form-check-label" for="denied_methods_<?php echo $api_method; ?>"><?php echo $api_method; ?></label>
                    </div>
                    <?php
}
?>
                <small class="text-muted"><?php echo $this->_pt('Tick only methods which you want to deny access to.'); ?></small>
            </div>
        </div>

        <div class="form-group row">
            <label for="allow_sw" class="col-sm-2 col-form-label"><?php echo $this->_pt('Allow Simulating Web'); ?></label>
            <div class="col-sm-10">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input position-static" id="allow_sw" name="allow_sw" value="1"
                        <?php echo $this->view_var('allow_sw') ? 'checked="checked"' : ''; ?> />
                </div>
                <small class="text-muted"><?php echo $this->_pt('If ticked, API key will be allowed to access actions which are normally available in web scope (by sending %s=1 in GET).', PHS_Api::PARAM_WEB_SIMULATION); ?></small>
            </div>
        </div>

        <div class="form-group row">
            <input type="submit" id="do_submit" name="do_submit"
                   class="btn btn-primary submit-protection ignore_hidden_required"
                   value="<?php echo $this->_pte('Save changes'); ?>" />
        </div>

    </div>
</form>
<?php

echo $users_autocomplete_action->js_all_functionality($this->get_all_view_vars());
