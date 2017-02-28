<?php
    /** @var \phs\setup\libraries\PHS_Setup_view $this */

    $this->set_context( 'page_title', 'PHS Setup Error' );
?>
<h1><?php echo (($error_title = $this->get_context( 'error_title' ))?$error_title:'Error...')?></h1>
<?php
if( ($error_message = $this->get_context( 'error_message' )) )
{
    ?><p><?php echo $error_message?></p><?php
} elseif( ($error_message_arr = $this->get_context( 'error_message_arr' ))
      and is_array( $error_message_arr ) )
{
    $knti = 1;
    ?><ol><?php
    foreach( $error_message_arr as $error_msg )
    {
        ?><li><?php echo $error_msg?></li><?php
    }
    ?></ol> <?php
}


