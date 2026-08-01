<?php
/** @var phs\system\core\views\PHS_View_email $this */
$current_user = $this->email_var('current_user', null);
?>
Hello,<br/>
<br/>
Recently someone completed Contact Us form on <?php echo $this->email_var('site_name', 'our'); ?> Platform!<br/>
<hr/>
<?php
if (!empty($current_user['id'])) {
    ?>Account: <?php echo ($current_user['nick'] ?? '-').' (#'.$current_user['id'].')'; ?><br/><?php
}
?>
User-Agent: <?php echo $this->email_var('user_agent'); ?><br/>
Host: <?php echo $this->email_var('request_ip'); ?><br/>
Email: <?php echo $this->email_var('email'); ?><br/>
Subject: <?php echo $this->email_var('subject'); ?><br/>
<hr/>
<?php echo $this->email_var('body'); ?><br/>
<hr/>
<br/>
Best wishes,<br/>
<?php echo $this->email_var('site_name'); ?> team
