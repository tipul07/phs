<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;
    use \phs\libraries\PHS_Hooks;

    if( !($notifications_arr = $this->context_var( 'notifications' )) )
        $notifications_arr = PHS_Hooks::default_notifications_hook_args();
    if( !($display_channels = $this->context_var( 'display_channels' )) )
        $display_channels = array();

if( (empty( $display_channels ) or in_array( 'success', $display_channels ))
and !empty( $notifications_arr['success'] ) and is_array( $notifications_arr['success'] ) )
{
    ?>
    <div class="success-box dismissible">
        <?php
        foreach( $notifications_arr['success'] as $message )
        {
            echo '<p>'.$message.'</p>';
        }
        ?>
    </div>
    <div class="clearfix"></div>
    <?php
}
if( (empty( $display_channels ) or in_array( 'warnings', $display_channels ))
and !empty( $notifications_arr['warnings'] ) and is_array( $notifications_arr['warnings'] ) )
{
    ?>
    <div class="warning-box dismissible">
        <?php
        foreach( $notifications_arr['warnings'] as $message )
        {
            echo '<p>'.$message.'</p>';
        }
        ?>
    </div>
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
            echo '<p>'.$message.'</p>';
        }
        ?>
    </div></div>
    <div class="clearfix"></div>
    <?php
}
