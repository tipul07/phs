<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array($this->view_var('hook_args'), PHS_Hooks::default_init_email_hook_args());

$email_vars = $hook_args['email_vars'];
if (empty($email_vars) || !is_array($email_vars)) {
    $email_vars = [];
}

?>
Ciao <?php echo $email_vars['destination_nick']; ?>,<br/>
<br/>
Il <?php echo $email_vars['message_date']; ?> hai ricevuto un nuovo messaggio interno da <?php echo $email_vars['author_handle']; ?> con questo oggetto "<?php echo $email_vars['message_subject']; ?>".<br/>
<br/>
<?php
if (!empty($email_vars['message_body'])) {
    ?>
    <hr width="100%" size="1" />
    <?php echo nl2br(str_replace('  ', ' &nbsp;', $email_vars['message_body'])); ?>
    <hr width="100%" size="1" />
    <br/>
    <?php
}
?>
Per visualizzare questo messaggio, clicca su questo link: <a href="<?php echo $email_vars['message_link']; ?>"><?php echo $email_vars['message_link']; ?></a><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']; ?>">Contattaci!</a><br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']; ?> team
