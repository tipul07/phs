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
Benvenuti al <?php echo $email_vars['site_name']?> Piattaforma!<br/>
Sei quasi pronto, basta confermare la registrazione cliccando qui: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Conferma registrazione</a>
<br/>
o copia e incolla il link in basso nel browser:<hr/>
<?php echo $email_vars['activation_link']?>
<hr/>
Ecco i tuoi dati d'accesso:<br/>
Login: <?php echo $email_vars['nick']?><br/>
Password: <?php echo $email_vars['obfuscated_pass']?><br/>
<small>Per motivi di sicurezza abbiamo offuscato la tua password.</small>
<hr/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Vi preghiamo di contattarci!</a><br/>
<br/>
Non vediamo l'ora di lavorare con te!<br/>
<br/>
I migliori auguri,<br/>
<?php echo $email_vars['site_name']?> squadra
