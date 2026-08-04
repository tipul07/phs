<?php
/** @var phs\system\core\views\PHS_View_email $this */
?>
Hi <?php echo $this->email_var('nick'); ?>,<br/>
<br/>
Recently your password for your account at <?php echo $this->email_var('site_name', 'our'); ?> platform was changed.<br/>
<br/>
Here are your login details:<br/>
Login: <?php echo $this->email_var('nick'); ?><br/>
Password: <?php echo $this->email_var('obfuscated_pass'); ?><br/>
<small>For security reasons we obfuscated your password.</small><br/>
<br/>
Need help? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name', 'Our'); ?> team
