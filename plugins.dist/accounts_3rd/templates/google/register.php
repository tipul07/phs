<?php
/** @var phs\system\core\views\PHS_View $this */

/** @var phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $plugin_obj */
/** @var phs\plugins\accounts_3rd\libraries\Google $google_lib */
if (!($plugin_obj = $this->get_plugin_instance())
 || !($google_lib = $this->view_var('google_lib'))) {
    return $this->_pt('Error loading required resources.');
}

if (!($phs_gal_code = $this->view_var('phs_gal_code'))) {
    $phs_gal_code = '';
}

if (!($display_error_msg = $this->view_var('display_error_msg'))) {
    $display_error_msg = '';
}
if (!($display_message_msg = $this->view_var('display_message_msg'))) {
    $display_message_msg = '';
}

$retry_action = (bool)$this->view_var('retry_action');
$login_required = (bool)$this->view_var('login_required');
?>
<div class="google_register_container">

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Register with Google'); ?></h3>
        </section>

        <?php
        if (!empty($display_error_msg)) {
            ?><p style="padding:10px;"><?php echo $display_error_msg; ?></p><?php
        }
if (!empty($display_message_msg)) {
    ?><p style="padding:10px;"><?php echo $display_message_msg; ?></p><?php
}

if (!empty($retry_action)
 && ($google_client = $google_lib->get_web_instance_for_register())) {
    ?>
            <fieldset class="login_button">
                <a href="<?php echo $google_client->createAuthUrl(); ?>"
                   class="btn btn-success btn-medium phs_3rdparty_register_button phs_3rdparty_register_google">
                    <i class="fa fa-google"></i> <?php echo $this->_pt('Retry registering in with Google'); ?>
                </a>
            </fieldset>
            <?php
}

if (!empty($login_required)
 && ($google_client = $google_lib->get_web_instance_for_login())) {
    ?>
            <fieldset class="login_button">
                <a href="<?php echo $google_client->createAuthUrl(); ?>"
                   class="btn btn-success btn-medium phs_3rdparty_login_button phs_3rdparty_login_google">
                    <i class="fa fa-google"></i> <?php echo $this->_pt('Login with Google'); ?>
                </a>
            </fieldset>
            <?php
}
?>

    </div>

</div>
