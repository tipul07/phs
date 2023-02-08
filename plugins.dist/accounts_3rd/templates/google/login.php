<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Roles;

/** @var \phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd $plugin_obj */
/** @var \phs\plugins\accounts_3rd\libraries\Google $google_lib */
if (!($plugin_obj = $this->parent_plugin())
 || !($google_lib = $this->view_var('google_lib'))) {
    return $this->_pt('Error loading required resources.');
}

if (!($account_arr = $this->view_var('account_arr'))) {
    $account_arr = false;
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
$register_required = (bool)$this->view_var('register_required');
?>
<div class="google_login_container">

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Login with Google'); ?></h3>
        </section>

        <?php
        if (!empty($display_error_msg)) {
            ?><p style="padding:10px;"><?php echo $display_error_msg; ?></p><?php
        }
        if (!empty($display_message_msg)) {
            ?><p style="padding:10px;"><?php echo $display_message_msg; ?></p><?php
        }

        if (!empty($retry_action)
         && ($google_client = $google_lib->get_web_instance_for_login())) {
            ?>
            <fieldset class="login_button">
                <a href="<?php echo $google_client->createAuthUrl(); ?>"
                   class="btn btn-success btn-medium phs_3rdparty_login_button phs_3rdparty_login_google">
                    <i class="fa fa-google"></i> <?php echo $this->_pt('Retry logging in with Google'); ?>
                </a>
            </fieldset>
            <?php
        }

        if (!empty($register_required)
         && !empty($phs_gal_code)) {
            ?>
            <form id="google_login_form" name="google_login_form" method="post"
                  action="<?php echo PHS::url(['p' => 'accounts_3rd', 'a' => 'google_login']); ?>">
                <input type="hidden" name="phs_gal_code" value="<?php echo form_str($phs_gal_code); ?>" />

                <fieldset>
                    <button type="submit" id="do_register" name="do_register"
                           class="btn btn-success btn-medium submit-protection" value="1">
                        <i class="fa fa-google"></i> <?php echo $this->_pt('Register a new account'); ?>
                    </button>
                </fieldset>
            </form>
            <?php
        }
?>

    </div>

</div>
