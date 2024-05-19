<?php
/** @var phs\setup\libraries\PHS_Setup_view $this */
$this->set_context('page_title', $this->_pt('Step 3'));

if (!($timezones_arr = $this->get_context('timezones_arr'))) {
    $timezones_arr = [];
}

$now_gmdate = gmdate('Y-m-d H:i:s');
$now_date = date('Y-m-d H:i:s');
$offset = floor((parse_db_date($now_date) - parse_db_date($now_gmdate)) / 3600);
?>
<form id="phs_setup_step3" name="phs_setup_step3" method="post">
<input type="hidden" name="foobar" value="1" />
<fieldset class="form-group">
    <label for="phs_timezone_continent"><?php echo $this->_pt('Site Timezone'); ?></label>
    <div class="lineform_line">
        <?php
        if (!($selected_continent = $this->get_context('phs_timezone_continent'))) {
            $selected_continent = '';
        }
if (!($selected_city = $this->get_context('phs_timezone_city'))) {
    $selected_city = '';
}
?>
        <select id="phs_timezone_continent" name="phs_timezone_continent" onchange="document.phs_setup_step3.submit()">
        <option value=""> - <?php echo $this->_pt('Choose'); ?> - </option>
        <?php
foreach ($timezones_arr as $continent => $cities_arr) {
    ?><option value="<?php echo $continent; ?>" <?php echo $continent === $selected_continent ? 'selected="selected"' : ''; ?>><?php echo $continent; ?></option><?php
}
?>
        </select>
        <?php

if (!empty($selected_continent)) {
    if (empty($timezones_arr[$selected_continent]) || !is_array($timezones_arr[$selected_continent])) {
        echo $this->_pt('No city defined for this continent.');
    } else {
        ?>
                <select id="phs_timezone_city" name="phs_timezone_city" onchange="document.phs_setup_step3.submit()">
                <option value=""> - <?php echo $this->_pt('Choose'); ?> - </option>
                <?php
        foreach ($timezones_arr[$selected_continent] as $city) {
            ?><option value="<?php echo $city; ?>" <?php echo $city === $selected_city ? 'selected="selected"' : ''; ?>><?php echo $city; ?></option><?php
        }
        ?>
                </select>
                <?php
    }
}

echo ' ('.($offset >= 0 ? '+' : '').$offset.' GMT)';
?><br/>
        <strong><?php echo $this->_pt('GMT'); ?></strong>: <?php echo $now_gmdate; ?>,
        <strong><?php echo $this->_pt('Selected Timezone'); ?></strong>: <?php echo $now_date; ?><br/>
        <small><?php echo $this->_pt('This will affect PHP time and database time. Same timezone is set for both PHP and database so there are no time differences because of timezone.'); ?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_site_name"><?php echo $this->_pt('Site Name'); ?></label>
    <div class="lineform_line">
        <input type="text" id="phs_site_name" name="phs_site_name" class="form-control" value="<?php echo form_str($this->get_context('phs_site_name')); ?>" placeholder="My First Site" style="width: 350px;" /><br/>
        <small><?php echo $this->_pt('This name will be used when displaying site name.'); ?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_contact_email"><?php echo $this->_pt('Contact Emails'); ?></label>
    <div class="lineform_line">
        <input type="text" id="phs_contact_email" name="phs_contact_email" class="form-control" value="<?php echo form_str($this->get_context('phs_contact_email')); ?>" placeholder="email1@email1.com, email@gmail.com" style="width: 350px;" /><br/>
        <small><?php echo $this->_pt('You can provide a comma separated list of emails (eg. email1@email.com, email2@gmail.com)'); ?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_sitebuild_version"><?php echo $this->_pt('Site Build Version'); ?></label>
    <div class="lineform_line">
        <input type="text" id="phs_sitebuild_version" name="phs_sitebuild_version" class="form-control" value="<?php echo form_str($this->get_context('phs_sitebuild_version')); ?>" placeholder="1.0.0" style="width: 350px;" /><br/>
        <small><?php echo $this->_pt('Version current site is running (not related to framework, but rather the site itself)'); ?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_debug_mode"><?php echo $this->_pt('Site on Debug Mode'); ?></label>
    <div class="lineform_line">
        <input type="checkbox" id="phs_debug_mode" name="phs_debug_mode" class="form-control" value="1" <?php echo $this->get_context('phs_debug_mode') ? 'checked="checked"' : ''; ?> /><br/>
        <small><?php echo $this->_pt('If this is a development copy of the site you should set debug mode on (tick this checkbox). On production sites untick this checkbox.'); ?></small>
    </div>
</fieldset>

<fieldset class="form-group">
    <label for="phs_php_cli_path"><?php echo $this->_pt('PHP CLI Binary Path'); ?></label>
    <div class="lineform_line">
        <input type="text" id="phs_php_cli_path" name="phs_php_cli_path" class="form-control" value="<?php echo form_str($this->get_context('phs_php_cli_path')); ?>" placeholder="<?php echo form_str($this->_pt('Full path to PHP CLI')); ?>" style="width: 350px;" /><br/>
        <small><?php echo $this->_pt('Full system path to PHP CLI binary file. This will be used when launching background tasks or agent tasks.'); ?></small>
    </div>
</fieldset>

<fieldset>
    <div class="lineform_line">
        <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte('Continue'); ?>" />
    </div>
</fieldset>

</form>
