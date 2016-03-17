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
Hi {EMAIL_INFO.user_nick},<br/>
<br/>
Welcome to the {EMAIL_INFO.site_name} Platform!<br/>
You're almost ready, just confirm your registration by clicking here: <a target="_blank" href="{EMAIL_INFO.activation_link}">Confirm Registration</a>
<br/>
or copy and paste this link below in your browser:<hr/>
{EMAIL_INFO.activation_link}
<hr/>
Here are your login details:<br/>
Login: {EMAIL_INFO.user_nick} <br/>
Password: {EMAIL_INFO.user_password}<br/>
<hr/>
Want to look at our quick how-to material? <a href="{EMAIL_LINKS.how_it_works}">How it works</a><br/>
Any problems? <a href="{EMAIL_LINKS.contact}">Get in touch</a><br/>

We're looking forward to working with you!<br/><br/>

Best wishes,<br/>
{EMAIL_INFO.site_team_name}
