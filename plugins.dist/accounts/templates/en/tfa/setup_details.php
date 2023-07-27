<?php
/** @var \phs\system\core\views\PHS_View $this */
if (!($qr_code_url = $this->view_var('qr_code_url'))) {
    return $this->_pt('Error loading required resources.');
}
?>
<div class="form-group row">
    <div class="col-sm-12">
        <p>In order to strengthen your account security, it is recommended that you set up Two-Factor Authentication.</p>
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
        <p>Add a new account on Google Authenticator application by scanning the QR code below to set up your account.</p>
        <p class="text-center"><img src="<?php echo $qr_code_url; ?>" alt="QR code" /></p>

        <h4>Step 3</h4>
        <p>Once account was added to Google Authenticator, enter the code displayed for the account in the input below.</p>
    </div>
</div>
