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
Recently your password for your account at <?php echo $email_vars['site_name']; ?> platform was changed.<br/>
<br/>
Here are your login details:<br/>
Login: <?php echo $email_vars['nick']; ?><br/>
Password: <?php echo $email_vars['obfuscated_pass']; ?><br/>
<small>For security reasons we obfuscated your password.</small><br/>
<br/>
Need help? <a href="<?php echo $email_vars['contact_us_link']; ?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']; ?> team
