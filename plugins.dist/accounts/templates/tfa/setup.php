<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

/** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
if (!($libs_plugin = $this->view_var('libs_plugin'))
 || !($qr_code_url = $this->view_var('qr_code_url'))) {
    return $this->_pt('Error loading required resources.');
}

?>
<form id="tfa_setup" name="tfa_setup" method="post"
      action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'setup', 'ad' => 'tfa']); ?>">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Two Factor Authentication Setup'); ?></h3>
        </section>

        <div class="form-group row">
            <div class="col-sm-12 text-center">
                <?php echo $this->_pt('You are setting up two factor authentication for account %s.',
                    '<strong>'.$this->view_var('nick').'</strong>'); ?>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-sm-12">
                <p>In order to strengthen your account security, it is recommended that you setup Two-Factor Authentication.</p>
                <p><a href="https://en.wikipedia.org/wiki/Help:Two-factor_authentication" target="_blank">What Two-Factor Authentication (TFA) means?</a></p>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-sm-12">
                <h4>Step 1</h4>
                <p>On your device, install Google Authenticator app:
                    <a href="https://support.google.com/accounts/answer/1066447?hl=en"
                       target="_blank">https://support.google.com/accounts/answer/1066447?hl=en</a>
                </p>

                <h4>Step 2</h4>
                <p>Add a new account on Google Authenticator application by scanning the QR code below to set-up your account.</p>
                <p class="text-center"><img src="<?php echo $qr_code_url; ?>" alt="<?php echo $this->_pt('QR code'); ?>" /></p>

                <h4>Step 3</h4>
                <p>Once account was added to Google Authenticator, enter the code displayed for the account in the input below.</p>
            </div>
        </div>

        <div class="form-group row">
            <label for="tfa_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Verification Code'); ?></label>
            <div class="col-sm-5">
                <input type="text" id="tfa_code" name="tfa_code" autocomplete="tfa_code" class="form-control"
                       required="required" value="" />
            </div>
            <div class="col-sm-5">
                <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
                       value="<?php echo $this->_pte('Check Code'); ?>" />
            </div>
        </div>

    </div>
</form>
