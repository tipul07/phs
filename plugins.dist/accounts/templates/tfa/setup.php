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
                <?php echo $this->_pt('You are setting up two factor authentication for account %s.', $this->view_var('nick')); ?>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-sm-12">
                <img src="<?php echo $qr_code_url; ?>"
                     alt="<?php echo $this->_pt('QR code'); ?>" />
            </div>
        </div>

        <div class="form-group row">
            <label for="tfa_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Verification Code'); ?></label>
            <div class="col-sm-10">
                <input type="password" id="tfa_code" name="tfa_code" class="form-control" required="required"
                       value="" />
            </div>
        </div>

        <div class="form-group row">
            <input type="submit" id="do_submit" name="do_submit" class="btn btn-primary submit-protection ignore_hidden_required"
                   value="<?php echo $this->_pte('Check Code'); ?>" />
        </div>

    </div>
</form>
