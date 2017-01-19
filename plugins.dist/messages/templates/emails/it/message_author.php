<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->context_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();

?>
Ciao <?php echo $email_vars['author_nick']?>,<br/>
<br/>
Il <?php echo $email_vars['message_date']?> hai inviato un nuovo messaggio interno con oggetto "<?php echo $email_vars['message_subject']?>".<br/>
<br/>
Per visualizzare questo messaggio, clicca su questo link: <a href="<?php echo $email_vars['message_link']?>"><?php echo $email_vars['message_link']?></a><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Vi preghiamo di contattarci!</a><br/>
<br/>
I migliori auguri,<br/>
<?php echo $email_vars['site_name']?> squadra
