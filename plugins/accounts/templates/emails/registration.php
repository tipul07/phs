<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->context_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();
?>Registration email [<?php var_dump( $email_vars ); ?>]
Hi <?php echo $email_vars['nick']?>,<br/>
<br/>
Welcome to the <?php echo $email_vars['site_name']?> Platform!<br/>
You're almost ready, just confirm your registration by clicking here: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Confirm Registration</a>
<br/>
or copy and paste this link below in your browser:<hr/>
<?php echo $email_vars['activation_link']?>
<hr/>
Here are your login details:<br/>
Login: <?php echo $email_vars['nick']?><br/>
Password: {EMAIL_INFO.user_password}<br/>
<hr/>
Want to look at our quick how-to material? <a href="{EMAIL_LINKS.how_it_works}">How it works</a><br/>
Any problems? <a href="{EMAIL_LINKS.contact}">Get in touch</a><br/>

We're looking forward to working with you!<br/><br/>

Best wishes,<br/>
{EMAIL_INFO.site_team_name}
