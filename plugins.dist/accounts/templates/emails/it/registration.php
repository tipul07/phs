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
Benvenuti sulla piattaforma di <?php echo $email_vars['site_name']?>!<br/>
Sei quasi pronto, conferma la tua registrazione cliccando qui: <a target="_blank" href="<?php echo $email_vars['activation_link']?>">Conferma la tua registrazione</a>
<br/>
o copia e incolla il seguente link sul tuo browser:<br/>
<hr/>
<?php echo $email_vars['activation_link']?><br/>
<hr/>
<?php
    if( !empty( $email_vars['pass_generated'] ) )
    {
        ?>
        <br/>
        Come la password è stata generata, si riceverà una e-mail dopo l'attivazione con la tua password.<br/><?php
    }
?>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $email_vars['contact_us_link']?>">Contattaci!</a><br/>
<br/>
Non vediamo l'ora di lavorare con te!<br/>
<br/>
Cordiali saluti,<br/>
<?php echo $email_vars['site_name']?> team
