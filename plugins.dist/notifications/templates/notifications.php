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
    if( !($ajax_placeholders_prefix = $this->view_var( 'ajax_placeholders_prefix' ))
     or !is_string( $ajax_placeholders_prefix ) )
        $ajax_placeholders_prefix = 'phs_ajax';

if( !empty( $display_channels ) and is_array( $display_channels ) )
{
    $channel_to_class = array( 'success' => 'success', 'errors' => 'error', 'warnings' => 'warning' );
    foreach( $display_channels as $channel )
    {
        if( empty( $notifications_arr[$channel] )
         or !is_array( $notifications_arr[$channel] ) )
            continue;

        if( empty( $channel_to_class[$channel] ) )
            $channel_class = 'warning';
        else
            $channel_class = $channel_to_class[$channel];

        ?>
        <div class="<?php echo $channel_class?>-box"><div class="dismissible">
        <?php
        foreach( $notifications_arr[$channel] as $message )
        {
            echo '<p>'.$message.'</p>';
        }
        ?>
        </div></div>
        <div class="clearfix"></div>
        <?php
    }
}

if( $output_ajax_placeholders )
{
?>
<div id="<?php echo $ajax_placeholders_prefix?>_success_box" style="display:none;" class="success-box"><div class="dismissible"></div></div><div class="clearfix"></div>
<div id="<?php echo $ajax_placeholders_prefix?>_warning_box" style="display:none;" class="warning-box"><div class="dismissible"></div></div><div class="clearfix"></div>
<div id="<?php echo $ajax_placeholders_prefix?>_error_box" style="display:none;" class="error-box"><div class="dismissible"></div></div><div class="clearfix"></div>
<?php
}
