<?php
/** @var phs\system\core\views\PHS_View_email $this */
?>
Hi <?php echo $this->email_var('nick'); ?>,<br/>
<br/>
We received a request to reset your account password.<br/>
In order to change your password please click here: <a target="_blank" href="<?php echo $this->email_var('forgot_link', '#'); ?>">Reset password</a>
<br/>
or copy and paste the link below in your browser:<br/>
<br/>
<?php echo $this->email_var('forgot_link', '-'); ?><br/>
<br/>
Did you remember the password? <a href="<?php echo $this->email_var('login_link', '#'); ?>">Login into your account</a><br/>
Need help? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name', 'Our'); ?> team
