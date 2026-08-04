<?php
/** @var phs\system\core\views\PHS_View_email $this */
?>
Hi <?php echo $this->email_var('author_nick'); ?>,<br/>
<br/>
On <?php echo $this->email_var('message_date'); ?> you sent a new internal message with subject "<?php echo $this->email_var('message_subject'); ?>".<br/>
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
In order to view this message, please click on this link: <a href="<?php echo $this->email_var('message_link', '#'); ?>"><?php echo $this->email_var('message_link', '#'); ?></a><br/>
<br/>
Need help? <a href="<?php echo $this->email_var('contact_us_link', '#'); ?>">Please contact us!</a><br/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name', 'Our'); ?> team
