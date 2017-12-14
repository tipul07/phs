<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($notifications_arr = $this->view_var( 'notifications' )) )
        $notifications_arr = PHS_Hooks::default_notifications_hook_args();
    if( !($display_channels = $this->view_var( 'display_channels' )) )
        $display_channels = array();
    if( !($output_ajax_placeholders = $this->view_var( 'output_ajax_placeholders' )) )
        $output_ajax_placeholders = false;

if( (empty( $display_channels ) or in_array( 'success', $display_channels ))
and !empty( $notifications_arr['success'] ) and is_array( $notifications_arr['success'] ) )
{
    ?>
    <div class="success-box"><div class="dismissible">
    <?php
    foreach( $notifications_arr['success'] as $message )
    {
        echo '<p>'.$message.'</p>';
    }
    ?>
    </div></div>
    <div class="clearfix"></div>
    <?php
}
if( (empty( $display_channels ) or in_array( 'warnings', $display_channels ))
and !empty( $notifications_arr['warnings'] ) and is_array( $notifications_arr['warnings'] ) )
{
    ?>
    <div class="warning-box"><div class="dismissible">
    <?php
    foreach( $notifications_arr['warnings'] as $message )
    {
        echo '<p>'.$message.'</p>';
    }
    ?>
    </div></div>
    <div class="clearfix"></div>
    <?php
}
if( (empty( $display_channels ) or in_array( 'errors', $display_channels ))
and !empty( $notifications_arr['errors'] ) and is_array( $notifications_arr['errors'] ) )
{
    ?>
    <div class="error-box"><div class="dismissible">
    <?php
    foreach( $notifications_arr['errors'] as $message )
    {
        echo '<p>'.$message.'</p>';
    }
    ?>
    </div></div>
    <div class="clearfix"></div>
    <?php
}

if( $output_ajax_placeholders )
{
?>
<div id="phs_ajax_success_box" style="display:none;" class="success-box"><div class="dismissible"></div></div><div class="clearfix"></div>
<div id="phs_ajax_warning_box" style="display:none;" class="warning-box"><div class="dismissible"></div></div><div class="clearfix"></div>
<div id="phs_ajax_error_box" style="display:none;" class="error-box"><div class="dismissible"></div></div><div class="clearfix"></div>
<?php
}
