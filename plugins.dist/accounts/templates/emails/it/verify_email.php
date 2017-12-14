<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->view_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();

?>
Ciao <?php echo $email_vars['nick']?>,<br/>
<br/>
Abbiamo avuto una richiesta per la verifica di questo indirizzo email (<?php echo $email_vars['site_name']?>).<br/>
<br/>
Per la conferma dell'indirizzo email puoi cliccare qui: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Conferma indirizzo Email</a><br/>
<br/>
o copia e incolla il seguente link sul tuo browser:<br/>
<?php echo $email_vars['activation_link']?><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Contattaci!</a><br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']?> team
