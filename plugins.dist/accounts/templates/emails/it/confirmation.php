<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array($this->view_var('hook_args'), PHS_Hooks::default_init_email_hook_args());

$email_vars = $hook_args['email_vars'];
if (empty($email_vars) || !is_array($email_vars)) {
    $email_vars = [];
}

?>
Ciao <?php echo $email_vars['nick']; ?>,<br/>
<br/>
Benvenuto sulla piattaforma di <?php echo $email_vars['site_name']; ?>!<br/>
La password dell'account è stata generata automaticamente.<br/>
Al fine di poter accedere al tuo conto ti forniamo la password dell'account qui di seguito.<br/>
Per motivi di sicurezza ti preghiamo di cambiare la password il prima possibile.<br/>
<br/>
<hr/>
Ecco i tuoi dati d'accesso:<br/>
Login: <?php echo $email_vars['nick']; ?><br/>
Password: <?php echo $email_vars['clean_pass']; ?><br/>
<hr/><br/>
<br/>
Vai alla pagina login: <a href="<?php echo $email_vars['login_link']; ?>">Pagina di Login</a><br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']; ?>">Contattaci!</a><br/>
<br/>
Non vediamo l'ora di lavorare con te!<br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']; ?> team
