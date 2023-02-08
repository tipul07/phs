<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\libraries\PHS_Hooks;

?>
<div>
    <form id="forgot_form" name="forgot_form" action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'forgot']); ?>" method="post">
        <input type="hidden" name="foobar" value="1" />

        <div class="form_container">

            <section class="heading-bordered">
                <h3><?php echo $this->_pt('Forgot password'); ?></h3>
            </section>

            <fieldset class="form-group">
                <label for="email"><?php echo $this->_pt('Email'); ?></label>
                <div class="lineform_line">
                <input type="text" id="email" name="email" class="form-control" required="required" value="<?php echo form_str($this->view_var('email')); ?>" /><br/>
                <small><?php echo $this->_pt('Provide email address of your account'); ?></small>
                </div>
            </fieldset>

            <?php
            $hook_params = [];
$hook_params['extra_img_style'] = 'padding:3px;border:1px solid black;margin-bottom:3px;';

if (($captcha_buf = PHS_Hooks::trigger_captcha_display($hook_params))) {
    ?>
                <fieldset class="form-group">
                    <label for="vcode"><?php echo $this->_pt('Validation code'); ?></label>
                    <div class="lineform_line">
                    <?php echo $captcha_buf; ?><br/>
                    <input type="text" id="vcode" name="vcode" class="form-control" required="required" value="<?php echo form_str($this->view_var('vcode')); ?>" style="max-width: 160px;" />
                    </div>
                </fieldset>
                <?php
}
?>

            <fieldset>
                <div><?php echo $this->_pt('Once you submit this form you will receive an email with instructions.'); ?></div>
            </fieldset>

            <fieldset>
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required" value="<?php echo $this->_pte('Submit'); ?>" />
            </fieldset>

        </div>
    </form>
</div>
