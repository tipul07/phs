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
La password dell'account è stato generato automaticamente. Al fine di poter accedere al tuo conto forniamo la password dell'account di seguito.<br/>
Per motivi di sicurezza si prega di cambiare la password al più presto possibile.<br/>
<br/>
<hr/>
Ecco i tuoi dati d'accesso:<br/>
Login: <?php echo $email_vars['nick']?><br/>
Password: <?php echo $email_vars['clean_pass']?><br/>
<hr/><br/>
<br/>
Vai alla pagina login: <a href="<?php echo $email_vars['login_link']?>">Pagina di Login</a><br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Contattaci!</a><br/>
<br/>
Non vediamo l'ora di lavorare con te!<br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']?> team
