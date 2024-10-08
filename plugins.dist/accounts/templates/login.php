<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Utils;
use phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd;

$trd_party_login_buffer = '';
/** @var PHS_Plugin_Accounts_3rd $trd_party_plugin */
if (($trd_party_plugin = PHS_Plugin_Accounts_3rd::get_instance())
    && $trd_party_plugin->plugin_active()
    && ($hook_args = PHS::trigger_hooks($trd_party_plugin::H_ACCOUNTS_3RD_LOGIN_BUFFER, PHS_Hooks::default_buffer_hook_args()))
    && !empty($hook_args['buffer'])) {
    $trd_party_login_buffer = $hook_args['buffer'];
}

$remember_me_session_minutes = $this->view_var('remember_me_session_minutes') ?: 0;
$no_nickname_only_email = $this->view_var('no_nickname_only_email') ?: false;
$back_page = $this->view_var('back_page') ?: '';
?>
<div class="login_container">
    <form id="login_form" name="login_form" method="post"
          action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'login']); ?>">
        <input type="hidden" name="foobar" value="1" />
        <?php
        if (!empty($back_page)) {
            ?><input type="hidden" name="back_page" value="<?php echo form_str(safe_url($back_page)); ?>" /><?php
        }
?>

        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt('Login'); ?></h3>
            </section>

            <fieldset class="form-group">
                <label for="nick"><?php echo empty($no_nickname_only_email) ? $this->_pt('Username') : $this->_pt('Email'); ?></label>
                <div class="lineform_line">
                <input type="text" id="nick" name="nick" class="form-control" required="required" value="<?php echo form_str($this->view_var('nick')); ?>" />
                </div>
            </fieldset>

            <fieldset class="form-group">
                <label for="pass"><?php echo $this->_pt('Password'); ?></label>
                <div class="lineform_line">
                <input type="password" id="pass" name="pass" class="form-control" required="required" value="<?php echo form_str($this->view_var('pass')); ?>" />
                </div>
            </fieldset>

            <fieldset class="fixskin">
                <label for="do_remember"><input type="checkbox" value="1" name="do_remember" id="do_remember" rel="skin_checkbox" <?php echo $this->view_var('do_remember'); ?> />
                    <strong><?php echo $this->_pt('Remember Me').(!empty($remember_me_session_minutes) ? $this->_pt(' (for %s)', PHS_Utils::parse_period($remember_me_session_minutes * 60, ['only_big_part' => true])) : ''); ?></strong></label>
                <div class="clearfix"></div>
                <small><?php echo $this->_pt('Normal sessions will expire in %s.', PHS_Utils::parse_period($this->view_var('normal_session_minutes') * 60, ['only_big_part' => true])); ?></small>
            </fieldset>

            <fieldset class="login_button">
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-success btn-medium submit-protection ignore_hidden_required"
                       value="<?php echo $this->_pte('Login'); ?>" />
            </fieldset>

            <?php
    if (!empty($trd_party_login_buffer)) {
        echo $trd_party_login_buffer;
    }
?>

        </div>

        <div class="login_form_actions">
            <a class="forgot_pass" href="<?php echo PHS::url(['p' => 'accounts', 'a' => 'forgot']); ?>"><?php echo $this->_pt('Forgot password'); ?></a>
            <?php
if (can(PHS_Roles::ROLEU_REGISTER)) {
    ?>
                <span class="separator">|</span>
                <a class="register_acc" href="<?php echo PHS::url(['p' => 'accounts', 'a' => 'register']); ?>"><?php echo $this->_pt('Register an account'); ?></a>
                <?php
}
?>
        </div>
    </form>
</div>
