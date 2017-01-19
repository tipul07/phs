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
Recentemente c'è stata una richiesta inserita a <?php echo $email_vars['site_name']?> per verificare questo indirizzo e-mail.<br/>
<br/>
Per confermare questo indirizzo e-mail potete cliccare qui: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Conferma l'indirizzo e-mail</a><br/>
<br/>
o copia e incolla il link in basso nel browser:<br/>
<?php echo $email_vars['activation_link']?><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Vi preghiamo di contattarci!</a><br/>
<br/>
I migliori auguri,<br/>
<?php echo $email_vars['site_name']?> squadra
