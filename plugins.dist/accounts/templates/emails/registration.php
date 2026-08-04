<?php
/** @var phs\system\core\views\PHS_View_email $this */
?>
Hi <?php echo $this->email_var('nick'); ?>,<br/>
<br/>
Welcome to the <?php echo $this->email_var('site_name'); ?> platform!<br/>
You're almost ready, just confirm your registration by clicking here: <a target="_blank" href="<?php echo $this->email_var('activation_link', '#'); ?>">Confirm Registration</a>
<br/>
or copy and paste the link below in your browser:<br/>
<hr/>
<?php echo $this->email_var('activation_link', '-'); ?><br/>
<hr/>
<?php
    if ($this->email_var('pass_generated')) {
        ?>
        <br/>
        As your password was generated, you will receive an email with your password after activation.<br/><?php
    }
?>
<br/>
Need help? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Please contact us!</a><br/>
<br/>
We're looking forward to working with you!<br/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name', 'Our'); ?> team
