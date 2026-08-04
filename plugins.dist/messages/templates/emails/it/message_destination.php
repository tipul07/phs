<?php
/** @var phs\system\core\views\PHS_View_email $this */
?>
Ciao <?php echo $this->email_var('destination_nick'); ?>,<br/>
<br/>
Il <?php echo $this->email_var('message_date'); ?> hai ricevuto un nuovo messaggio interno da <?php echo $this->email_var('author_handle'); ?> con questo oggetto "<?php echo $this->email_var('message_subject'); ?>".<br/>
<br/>
<?php
if (($body = $this->email_var('message_body'))) {
    ?>
    <hr width="100%" size="1" />
    <?php echo nl2br(str_replace('  ', ' &nbsp;', $body)); ?>
    <hr width="100%" size="1" />
    <br/>
    <?php
}
?>
Per visualizzare questo messaggio, clicca su questo link: <a href="<?php echo $this->email_var('message_link', '#'); ?>"><?php echo $this->email_var('message_link', '#'); ?></a><br/>
<br/>
Hai bisogno di aiuto? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Contattaci!</a><br/>
<br/>
Cordiali saluti,<br/>
<?php echo $this->email_var('site_name', 'Our'); ?> team
