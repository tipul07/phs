<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    $this->set_context( 'page_title', 'PHS Setup Error' );
?>
<h1><?php echo (($error_title = $this->get_context( 'error_title' ))?$error_title:'Error...')?></h1>

<p><?php echo $this->get_context( 'error_message' )?></p>
