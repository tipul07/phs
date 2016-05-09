<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->context_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();

?>
Hi <?php echo $email_vars['nick']?>,<br/>
<br/>
Recently there was a request placed at <?php echo $email_vars['site_name']?> to verify this email address.<br/>
<br/>
In order to confirm this email address you can click here: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Confirm Email Address</a><br/>
<br/>
or copy and paste this link below in your browser:<br/>
<?php echo $email_vars['activation_link']?><br/>
<br/>
Any problems? <a href="<?php echo $email_vars['contact_us_link']?>">Get in touch</a><br/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']?> team
