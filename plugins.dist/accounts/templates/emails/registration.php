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
Welcome to the <?php echo $email_vars['site_name']?> platform!<br/>
You're almost ready, just confirm your registration by clicking here: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Confirm Registration</a>
<br/>
or copy and paste the link below in your browser:<br/>
<hr/>
<?php echo $email_vars['activation_link']?><br/>
<hr/>
<?php
    if( !empty( $email_vars['pass_generated'] ) )
    {
        ?>
        <br/>
        As your password was generated, you will receive an email with your password after activation.<br/><?php
    }
?>
<br/>
Need help? <a href="<?php echo $email_vars['contact_us_link']?>">Please contact us!</a><br/>
<br/>
We're looking forward to working with you!<br/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']?> team
