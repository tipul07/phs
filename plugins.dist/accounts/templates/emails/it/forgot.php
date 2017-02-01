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
Abbiamo ricevuto una richiesta di ripristinare la password del tuo account.<br/>
Per cambiare la tua password, per piacere, clicca qui: <a target="_blank" href="<?php echo $email_vars['forgot_link']?>">Cambiare la password</a>
<br/>
o copia e incolla il seguente link sul tuo browser:<br/>
<br/>
<?php echo $email_vars['forgot_link']?><br/>
<br/>
Ti ricordi la tua password? <a href="<?php echo $email_vars['login_link']?>">Fai il login dal tuo account</a><br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Contattaci!</a><br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']?> team
