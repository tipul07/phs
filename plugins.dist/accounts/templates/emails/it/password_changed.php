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
Recentemente la tua password per il tuo account a <?php echo $email_vars['site_name']?> piattaforma era cambiata.<br/>
<br/>
Ecco i tuoi dati d'accesso:<br/>
Login: <?php echo $email_vars['nick']?><br/>
Password: <?php echo $email_vars['obfuscated_pass']?><br/>
<small>Per motivi di sicurezza abbiamo offuscato la tua password.</small><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Vi preghiamo di contattarci!</a><br/>
<br/>
I migliori auguri,<br/>
<?php echo $email_vars['site_name']?> squadra
