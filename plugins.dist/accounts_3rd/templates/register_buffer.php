<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Roles;

    /** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $plugin_obj */
    if( !($plugin_obj = $this->parent_plugin()) )
        return $this->_pt( 'Couldn\'t get parent plugin object.' );

    if( !($settings_arr = $plugin_obj->get_plugin_settings())
     || !is_array( $settings_arr )
     || empty( $settings_arr['enable_3rd_party'] ) )
        return '';

ob_start();
if( !empty( $settings_arr['enable_google'] )
 && ($google_lib = $plugin_obj->get_google_instance())
 && ($google_client = $google_lib->get_web_instance_for_register()) )
{
    ?>
    <a href="<?php echo $google_client->createAuthUrl()?>"
       class="btn btn-success btn-medium phs_3rdparty_register_button phs_3rdparty_register_google">
        <i class="fa fa-google"></i> <?php echo $this->_pt( 'Register with Google' )?>
    </a>
    <?php
}

if( !empty( $settings_arr['enable_apple'] ) )
{
    ?>
    <a href="#"
       class="btn btn-success btn-medium phs_3rdparty_register_button phs_3rdparty_register_apple">
        <i class="fa fa-apple"></i> <?php echo $this->_pt( 'Register with Apple' )?>
    </a>
    <?php
}

if( !empty( $settings_arr['enable_facebook'] ) )
{
?>
<a href="#"
   class="btn btn-success btn-medium phs_3rdparty_register_button phs_3rdparty_register_facebook">
    <i class="fa fa-facebook"></i> <?php echo $this->_pt( 'Register with Facebook' )?>
</a>
<?php
}

if( ($buf = @ob_get_clean()) )
{
    ?><div class="phs_3rdparty_register_container"><?php echo $buf?></div><?php
}
?>
<style>
.phs_3rdparty_register_container { margin: 20px auto 0; padding: 10px 0; border-top: 1px solid #ced4da; }
.phs_3rdparty_register_button { margin-top: 10px; }
</style>

