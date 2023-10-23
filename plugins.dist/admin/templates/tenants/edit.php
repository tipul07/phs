<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

if (!($back_page = $this->view_var('back_page'))) {
    $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'tenants']);
}
echo $this->sub_view('ractive/bootstrap');
?>
<form id="edit_apikey_form" name="edit_apikey_form" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'edit', 'ad' => 'tenants'],
          ['tid' => $this->view_var('tid')]); ?>">
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
        <h3><?php echo $this->_pt('Edit Tenant'); ?></h3>
    </section>

    <div class="form-group row">
        <label for="name" class="col-sm-2 col-form-label"><?php echo $this->_pt('Name'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="name" name="name" class="form-control" autocomplete="name"
                   placeholder="<?php echo form_str($this->_pt('Friendly tenant name')); ?>"
                   value="<?php echo form_str($this->view_var('name')); ?>" />
            <div id="name_help" class="form-text"><?php echo $this::_t('A friendly name to identify the tenant. This will replace PHS_SITE_NAME constant.'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="domain" class="col-sm-2 col-form-label"><?php echo $this->_pt('Domain'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="domain" name="domain" class="form-control" autocomplete="name"
                   placeholder="www.exemple.com"
                   value="<?php echo form_str($this->view_var('domain')); ?>" />
            <div id="domain_help" class="form-text"><?php echo $this::_t('What domain is used for this tenant (e.g. www.example.com)'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="directory" class="col-sm-2 col-form-label"><?php echo $this->_pt('Directory'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="directory" name="directory" class="form-control" autocomplete="directory"
                   placeholder="appdir/"
                   value="<?php echo form_str($this->view_var('directory')); ?>" />
            <div id="directory_help" class="form-text"><?php echo $this::_t('What directory (if any) is used for this tenant (e.g. appdir/)'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="identifier" class="col-sm-2 col-form-label"><?php echo $this->_pt('Identifier'); ?></label>
        <div class="col-sm-10">
            <input type="text" id="identifier" name="identifier" class="form-control" autocomplete="identifier"
                   placeholder="<?php echo form_str($this->_pt('External tenant identifier')); ?>"
                   value="<?php echo form_str($this->view_var('identifier')); ?>" />
            <div id="identifier_help" class="form-text"><?php echo $this::_t('Used to identify the tenant from requests from outside.'); ?></div>
        </div>
    </div>

    <div class="form-group row">
        <label for="is_default" class="col-sm-2 col-form-label"><?php echo $this->_pt('Default tenant?'); ?></label>
        <div class="col-sm-10">
            <input class="form-check-input" type="checkbox" id="is_default" name="is_default"
                   rel="skin_checkbox"
                <?php echo $this->view_var('is_default') ? 'checked="checked"' : ''; ?>>
            <br/>
            <small><?php echo $this->_pt('If no tenant can be selected from the request, use this tenant as default'); ?></small>
        </div>
    </div>

    <div id="PHS_RActive_Tenants_themes_target"></div>

    <fieldset>
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pte('Save changes'); ?>" />
    </fieldset>

</div>
</form>
<script id="PHS_RActive_Tenants_themes_template" type="text/html">
    It works for {{foobar.name}} @{{foobar.age}}...
</script>
<script type="text/javascript">
let PHS_RActive_Tenants_themes_app = null;
$(document).ready(function() {
    PHS_RActive_Tenants_themes_app = PHS_RActive_Tenants_themes_app || new PHS_RActive({

        target: "PHS_RActive_Tenants_themes_target",
        template: "#PHS_RActive_Tenants_themes_template",

        data: function () {
            return {
                foobar: {
                    name: "Gigi",
                    age: 14
                }
            }
        },

        onrender: function () {
            //phs_refresh_input_skins();
        }

    });
})
</script>
