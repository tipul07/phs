<?php
/** @var \phs\setup\libraries\PHS_Setup_view $this */

use \phs\PHS;
use \phs\libraries\PHS_Hooks;
?>
<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport"           content="user-scalable=no, width=device-width, initial-scale=1.0" />
    <meta name="copyright"          content="Copyright <?php echo date( 'Y' )?> PHS. All Right Reserved." />
    <meta name="author"             content="PHS Framework" />
    <meta name="revisit-after"      content="1 days" />

    <link href="<?php echo $this->get_resource_url( 'css/style.css' )?>" rel="stylesheet" type="text/css" />

    <title><?php echo (($page_title = $this->get_context( 'page_title' ))?$page_title.' - ':'')?>PHS Setup</title>
</head>
<style>
</style>
<body>
<div id="container">
    <small>PHS v<?php echo phs_version()?></small>
    <div id="content">
        <?php
        if( ($notifications_arr = $this->get_context( 'notifications' ))
         && is_array( $notifications_arr ) )
        {
            foreach( array( 'error', 'success', 'notice' ) as $notification_type )
            {
                if( !empty( $notifications_arr[$notification_type] ) && is_array( $notifications_arr[$notification_type] ) )
                {
                    ?><div class="notification_container notification_<?php echo $notification_type?>"><ul><?php
                    foreach( $notifications_arr[$notification_type] as $error_msg )
                    {
                        ?><li><?php echo $error_msg?></li><?php
                    }
                    ?></ul></div><?php
                }
            }
        }
        ?>
        <div id="main_content">

            <?php echo (($page_content = $this->get_context( 'page_content' ))?$page_content:'')?>

        </div>
    </div>
</div>
<small>PHS v<?php echo phs_version()?></small>
</body>
</html>
