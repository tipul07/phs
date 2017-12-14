<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->view_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();

?>
Hi <?php echo $email_vars['nick']?>,<br/>
<br/>
We received a request to reset your account password.<br/>
In order to change you password please click here: <a target="_blank" href="<?php echo $email_vars['forgot_link']?>">Confirm Registration</a>
<br/>
or copy and paste the link below in your browser:<br/>
<br/>
<?php echo $email_vars['forgot_link']?><br/>
<br/>
Did you remember the password? <a href="<?php echo $email_vars['login_link']?>">Login into your account</a><br/>
Need help? <a href="<?php echo $email_vars['contact_us_link']?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']?> team
