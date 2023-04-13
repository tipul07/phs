<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

?>
<form id="add_tenant" name="add_tenant" method="post"
      action="<?php echo PHS::url(['p' => 'admin', 'a' => 'add', 'ad' => 'tenants']); ?>">
<input type="hidden" name="foobar" value="1" />

<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Add Tenant'); ?></h3>
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

    <fieldset>
        <input type="submit" id="do_submit" name="do_submit"
               class="btn btn-primary submit-protection ignore_hidden_required"
               value="<?php echo $this->_pte('Add Tenant'); ?>" />
    </fieldset>

</div>
</form>
