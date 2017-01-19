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
Abbiamo ricevuto una richiesta di reimpostare la password dell'account.<br/>
Per cambiare la password, clicca qui: <a target="_blank" href="<?php echo $email_vars['forgot_link']?>">Conferma registrazione</a>
<br/>
o copia e incolla il link in basso nel browser:<br/>
<br/>
<?php echo $email_vars['forgot_link']?><br/>
<br/>
Vi siete ricordati la password? <a href="<?php echo $email_vars['login_link']?>">Accedi al tuo account</a><br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Vi preghiamo di contattarci!</a><br/>
<br/>
I migliori auguri,<br/>
<?php echo $email_vars['site_name']?> squadra
