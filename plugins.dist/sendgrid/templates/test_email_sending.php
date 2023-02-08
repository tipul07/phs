<?php
/** @var \phs\system\core\views\PHS_View $this */
if (!($test_email_sending_error = $this->view_var('test_email_sending_error'))) {
    $test_email_sending_error = '';
}
if (!($test_email_sending_success = $this->view_var('test_email_sending_success'))) {
    $test_email_sending_success = false;
}

if (!empty($test_email_sending_success)) {
    ?>
        <div class="success-box"><?php echo $this->_pt('Successfully sent test email.'); ?></div>
        <?php
} elseif (!empty($test_email_sending_error)) {
    ?>
        <div class="error-box"><?php echo $test_email_sending_error; ?></div>
        <?php
}
?>
    <div style="margin-bottom:10px;">
        <label for="test_email_sending_email" style="width:150px !important;">
            <?php echo $this->_pt('To email'); ?>
            <i class="fa fa-question-circle" title="<?php echo $this->_pt('To what email should we send test email'); ?>"></i>
        </label>
        <input type="text" class="form-control" style="width:250px;"
               name="test_email_sending_email"
               id="test_email_sending_email"
               value="<?php echo form_str($this->view_var('test_email_sending_email')); ?>" /><br/>
        <small><?php echo $this->_pt('Use send test email after saving plugin settings.'); ?></small>
    </div>

    <div style="margin-bottom:10px;">
        <input type="submit" id="do_test_email_sending_submit" name="do_test_email_sending_submit"
               class="btn btn-success submit-protection ignore_hidden_required" value="<?php echo $this->_pte('Send test email'); ?>" />
    </div>
    <?php
