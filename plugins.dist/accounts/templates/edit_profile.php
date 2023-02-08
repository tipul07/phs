<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Utils;

if (!($no_nickname_only_email = $this->view_var('no_nickname_only_email'))) {
    $no_nickname_only_email = false;
}

if (!($limit_emails = $this->view_var('limit_emails'))) {
    $limit_emails = false;
} else {
    $limit_emails = true;
}
?>
<form id="edit_profile_form" name="edit_profile_form" method="post"
      action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'edit_profile']); ?>">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container responsive">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Edit Profile'); ?></h3>
        </section>

        <?php
        if (empty($no_nickname_only_email)) {
            ?>
            <fieldset class="form-group">
                <label for="nick"><?php echo $this->_pt('Username'); ?></label>
                <div class="lineform_line"><?php echo form_str($this->view_var('nick')); ?></div>
            </fieldset>
            <?php
        }
?>

        <fieldset class="form-group">
            <label for="email"><?php echo $this->_pt('Email'); ?></label>
            <div class="lineform_line">
            <?php
    if (empty($no_nickname_only_email)) {
        ?>
                <input type="text" id="email" name="email" class="form-control" required="required" value="<?php echo form_str($this->view_var('email')); ?>" />
                <?php
    } else {
        echo $this->view_var('email');
    }
?><br/>
            <?php
if (!$this->view_var('email_verified')) {
    echo $this->_pt('Email is %s',
        '<span style="color: red;">'.$this->_pt('NOT VERIFIED')
        .'</span>. <a href="'.$this->view_var('verify_email_link').'">'.$this->_pt('Send verification email').'</a>');
} else {
    echo $this->_pt('Email is %s', '<span style="color: green;">'.$this->_pt('VERIFIED').'</span>.');
}
?>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="title"><?php echo $this->_pt('Title'); ?></label>
            <div class="lineform_line">
            <input type="text" id="title" name="title" class="form-control" style="max-width: 60px;"
                   value="<?php echo form_str($this->view_var('title')); ?>" /><br/>
            <small><?php echo $this->_pt('eg. Mr., Ms., Mrs., etc'); ?></small>
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="fname"><?php echo $this->_pt('First Name'); ?></label>
            <div class="lineform_line">
            <input type="text" id="fname" name="fname" class="form-control"
                   value="<?php echo form_str($this->view_var('fname')); ?>" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="lname"><?php echo $this->_pt('Last Name'); ?></label>
            <div class="lineform_line">
            <input type="text" id="lname" name="lname" class="form-control"
                   value="<?php echo form_str($this->view_var('lname')); ?>" />
            </div>
        </fieldset>

        <fieldset class="form-group">
            <label for="phone"><?php echo $this->_pt('Phone Number'); ?></label>
            <div class="lineform_line">
            <input type="text" id="phone" name="phone" class="form-control"
                   value="<?php echo form_str($this->view_var('phone')); ?>" />
        </fieldset>

        <fieldset class="form-group">
            <label for="company"><?php echo $this->_pt('Company'); ?></label>
            <div class="lineform_line">
            <input type="text" id="company" name="company" class="form-control"
                   value="<?php echo form_str($this->view_var('company')); ?>" />
            </div>
        </fieldset>

        <fieldset class="fixskin">
            <label for="limit_emails">
                <input type="checkbox" value="1" name="limit_emails" id="limit_emails" rel="skin_checkbox" <?php echo $limit_emails ? 'checked="checked"' : ''; ?> />
                <strong><?php echo $this->_pt('Limit emails received from platform'); ?></strong>
            </label>
            <div class="clearfix"></div>
            <small><?php echo $this->_pt('Platform will minimize emails sent to you, however functionality emails will still be send to your email address (e.g. forgot password emails, change password emails, etc)'); ?></small>
        </fieldset>

        <fieldset>
            <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
                   value="<?php echo $this->_pte('Save Changes'); ?>" />
        </fieldset>

        <fieldset>
            <a href="<?php echo PHS::url(['p' => 'accounts', 'a' => 'change_password']); ?>"><?php echo $this->_pt('Change password'); ?></a>
        </fieldset>

    </div>
</form>
