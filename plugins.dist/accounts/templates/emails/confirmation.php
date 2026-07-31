<?php
/** @var phs\system\core\views\PHS_View_email $this */
$this->_pt();
?>
Hi <?php echo $this->email_var('nick'); ?>,<br/>
<br/>
Welcome to the <?php echo $this->email_var('site_name'); ?> Platform!<br/>
Your account password was auto-generated. In order for you to access your account we provide your account password below.<br/>
For security reasons please change your password as soon as possible.<br/>
<br/>
<hr/>
Here are your login details:<br/>
Login: <?php echo $this->email_var('nick'); ?><br/>
Password: <?php echo $this->email_var('clean_pass'); ?><br/>
<hr/><br/>
<br/>
Go to login page: <a href="<?php echo $this->email_var('login_link', '#'); ?>">Login page</a><br/>
Need help? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Please contact us!</a><br/>
<br/>
We're looking forward to working with you!<br/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name'); ?> team
