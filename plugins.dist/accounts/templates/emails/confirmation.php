<?php
/** @var phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array($this->view_var('hook_args'), PHS_Hooks::default_init_email_hook_args());

$email_vars = $hook_args['email_vars'];
if (empty($email_vars) || !is_array($email_vars)) {
    $email_vars = [];
}

?>
Hi <?php echo $email_vars['nick']; ?>,<br/>
<br/>
Welcome to the <?php echo $email_vars['site_name']; ?> Platform!<br/>
Your account password was auto-generated. In order for you to access your account we provide your account password below.<br/>
For security reasons please change your password as soon as possible.<br/>
<br/>
<hr/>
Here are your login details:<br/>
Login: <?php echo $email_vars['nick']; ?><br/>
Password: <?php echo $email_vars['clean_pass']; ?><br/>
<hr/><br/>
<br/>
Go to login page: <a href="<?php echo $email_vars['login_link']; ?>">Login page</a><br/>
Need help? <a href="<?php echo $email_vars['contact_us_link']; ?>">Please contact us!</a><br/>
<br/>
We're looking forward to working with you!<br/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']; ?> team
