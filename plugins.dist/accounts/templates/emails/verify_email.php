<?php
/** @var phs\system\core\views\PHS_View_email $this */
?>
Hi <?php echo $this->email_var('nick'); ?>,<br/>
<br/>
Recently there was a request placed at <?php echo $this->email_var('site_name', 'our platform'); ?> to verify this email address.<br/>
<br/>
In order to confirm this email address you can click here: <a target="_blank" href="<?php echo $this->email_var('activation_link', '#'); ?>">Confirm Email Address</a><br/>
<br/>
or copy and paste the link below in your browser:<br/>
<?php echo $this->email_var('activation_link', '-'); ?><br/>
<br/>
Need help? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name', 'Our'); ?> team
