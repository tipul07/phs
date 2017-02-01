<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->context_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();

?>
Ciao <?php echo $email_vars['nick']?>,<br/>
<br/>
Recentemente la password del tuo account sulla piattaforma di <?php echo $email_vars['site_name']?> è stata cambiata.<br/>
<br/>
Qui trovi i dettagli del tuo login:<br/>
Login: <?php echo $email_vars['nick']?><br/>
Password: <?php echo $email_vars['obfuscated_pass']?><br/>
<small>Per ragioni di sicurezza abbiamo nascosto la tua password.</small><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Contattaci!</a><br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']?> team
