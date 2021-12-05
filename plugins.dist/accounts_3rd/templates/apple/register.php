<?php
/** @var \phs\system\core\views\PHS_View $this */

use \phs\PHS;
use \phs\libraries\PHS_Roles;

    /** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $plugin_obj */
    /** @var \phs\plugins\accounts_3rd\libraries\Apple $apple_lib */
    if( !($plugin_obj = $this->parent_plugin())
     || !($apple_lib = $this->view_var( 'apple_lib' )) )
        return $this->_pt( 'Error loading required resources.' );

    if( !($phs_gal_code = $this->view_var( 'phs_gal_code' )) )
        $phs_gal_code = '';

    if( !($display_error_msg = $this->view_var( 'display_error_msg' )) )
        $display_error_msg = '';
    if( !($display_message_msg = $this->view_var( 'display_message_msg' )) )
        $display_message_msg = '';

    $retry_action = (bool)$this->view_var( 'retry_action' );
    $login_required = (bool)$this->view_var( 'login_required' );
?>
<div class="apple_register_container">
    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt( 'Register with Apple' )?></h3>
        </section>

        <?php
        if( !empty( $display_error_msg ) )
        {
            ?><p style="padding:10px;"><?php echo $display_error_msg?></p><?php
        }
        if( !empty( $display_message_msg ) )
        {
            ?><p style="padding:10px;"><?php echo $display_message_msg?></p><?php
        }

        if( !empty( $retry_action )
         && $apple_lib->prepare_instance_for_register() )
        {
            ?>
            <fieldset class="login_button">
                <a href="<?php echo $apple_lib->get_url( $apple_lib::ACTION_REGISTER )?>"
                   class="btn btn-success btn-medium phs_3rdparty_register_button phs_3rdparty_register_apple">
                    <i class="fa fa-apple"></i> <?php echo $this->_pt( 'Retry registering in with Apple' )?>
                </a>
            </fieldset>
            <?php
        }

        if( !empty( $login_required )
         && $apple_lib->prepare_instance_for_login() )
        {
            ?>
            <fieldset class="login_button">
                <a href="<?php echo $apple_lib->get_url( $apple_lib::ACTION_LOGIN )?>"
                   class="btn btn-success btn-medium phs_3rdparty_login_button phs_3rdparty_login_apple">
                    <i class="fa fa-apple"></i> <?php echo $this->_pt( 'Login with Apple' )?>
                </a>
            </fieldset>
            <?php
        }
        ?>

    </div>
</div>
