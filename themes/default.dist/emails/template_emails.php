<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Language;
use \phs\libraries\PHS_Hooks;

$hook_args = $this::validate_array( $this->context_var( 'hook_args' ), PHS_Hooks::default_init_email_hook_args() );

$email_vars = $hook_args['email_vars'];
if( empty( $email_vars ) or !is_array( $email_vars ) )
    $email_vars = array();

if( !($email_content = $this->context_var( 'email_content' )) )
    $email_content = '';


?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
    <meta content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0" name="viewport">
    <meta content="telephone=no" name="format-detection">
    <title><?php echo $hook_args['subject']?></title>
    <style type="text/css">
    </style>
</head>
<body style="margin:0; padding: 10px;">
<h2><?php echo $hook_args['subject']?></h2>
<div style="clear: both;"></div>
<?php echo $email_content?>
</body>
</html>
