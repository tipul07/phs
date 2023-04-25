<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array($this->view_var('hook_args'), PHS_Hooks::default_init_email_hook_args());

$email_vars = $hook_args['email_vars'];
if (empty($email_vars) || !is_array($email_vars)) {
    $email_vars = [];
}

?>
Hi <?php echo $email_vars['nick']; ?>,<br/>
<br/>
We received a request to setup an account on our platform <?php echo $email_vars['site_name']; ?>.<br/>
In order to complete account registration you must setup a password. Please click here: <a target="_blank" href="<?php echo $email_vars['setup_link']; ?>">Setup a password</a>
<br/>
or copy and paste the link below in your browser:<br/>
<br/>
<?php echo $email_vars['setup_link']; ?><br/>
<br/>
Need help? <a href="<?php echo $email_vars['contact_us_link']; ?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']; ?> team
