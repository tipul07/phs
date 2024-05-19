<?php
/** @var phs\system\core\views\PHS_View $this */

use phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array($this->view_var('hook_args'), PHS_Hooks::default_init_email_hook_args());

$email_vars = $hook_args['email_vars'];
if (empty($email_vars) || !is_array($email_vars)) {
    $email_vars = [];
}

?>
Hello,<br/>
<br/>
Recently someone completed Contact Us form on <?php echo $email_vars['site_name']; ?> Platform!<br/>
<hr/>
<?php
if (!empty($email_vars['current_user'])) {
    ?>Account: <?php echo $email_vars['current_user']['nick'].' (#'.$email_vars['current_user']['id'].')'; ?><br/><?php
}
?>
User-Agent: <?php echo $email_vars['user_agent']; ?><br/>
Host: <?php echo $email_vars['request_ip']; ?><br/>
Email: <?php echo $email_vars['email']; ?><br/>
Subject: <?php echo $email_vars['subject']; ?><br/>
<hr/>
<?php echo $email_vars['body']; ?><br/>
<hr/>
<br/>
Best wishes,<br/>
<?php echo $email_vars['site_name']; ?> team
