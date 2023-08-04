<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

/** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
/** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
if (!($libs_plugin = $this->view_var('libs_plugin'))
    || !($accounts_plugin = $this->view_var('accounts_plugin'))
    || !($tfa_model = $this->view_var('tfa_model'))
    || !($qr_code_url = $this->view_var('qr_code_url'))) {
    return $this->_pt('Error loading required resources.');
}

if (!($tfa_arr = $this->view_var('tfa_data'))) {
    $tfa_arr = null;
}
?>
<form id="tfa_setup" name="tfa_setup" method="post"
      action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'setup', 'ad' => 'tfa']); ?>">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container">

        <section class="heading-bordered">
            <h3><?php echo $this->_pt('Two Factor Authentication Setup'); ?></h3>
        </section>

        <?php
        if (empty($tfa_arr)
            || !$tfa_model->is_setup_completed($tfa_arr)) {
            ?>
            <div class="form-group row">
                <div class="col-sm-12">
                    <p class="text-center"><?php echo $this->_pt('You are setting up two factor authentication for account %s.',
                        '<strong>'.$this->view_var('nick').'</strong>'); ?></p>
                    <?php
                    if ($accounts_plugin->tfa_policy_is_enforced()) {
                        ?>
                        <p class="text-center"><?php echo $this->_pt('Please note that on this platform two factor authentication is mandatory.'); ?></p>
                        <?php
                    }
            ?>
                </div>
            </div>

            <?php echo $this->sub_view('tfa/setup_details'); ?>

            <div class="form-group row">
                <label for="tfa_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Verification Code'); ?></label>
                <div class="col-sm-5">
                    <input type="text" id="tfa_code" name="tfa_code" autocomplete="tfa_code" class="form-control"
                           required="required" value="" />
                </div>
                <div class="col-sm-5">
                    <input type="submit" id="do_submit" name="do_submit"
                           class="btn btn-primary submit-protection ignore_hidden_required"
                           value="<?php echo $this->_pte('Check Code'); ?>" />
                </div>
            </div>
            <?php
        } elseif (!($recovery_codes = $tfa_model->get_recovery_codes($tfa_arr))) {
            ?>
            <div class="form-group row">
                <div class="col-sm-12">
                    <p><?php echo $this->_pt('Error obtaining two factor authentication recovery codes. Please refresh the page.'); ?></p>
                </div>
            </div>
            <?php
        } else {
            echo $this->sub_view('tfa/setup_recovery_codes');
            ?>
            <ul>
                <?php
                foreach ($recovery_codes as $recovery_code) {
                    ?><li><?php echo $recovery_code; ?></li><?php
                }
            ?>
            </ul>
            <div class="form-group row">
                <div class="col-sm-12">
                    <p><?php echo $this->_pt('Please download your recovery codes and store them somewhere safe.'); ?></p>
                </div>
            </div>
            <div class="form-group row" id="download_button_section">
                <input type="submit" id="do_download_codes" name="do_download_codes"
                       onclick="start_download()"
                       class="btn btn-primary ignore_hidden_required"
                       value="<?php echo $this->_pte('Download Recovery Codes'); ?>" />
            </div>
            <div class="form-group row" id="downloading_section" style="display:none;">
                <div class="col-sm-12">
                    <p><?php echo $this->_pt('Downloading file. Please wait...'); ?></p>
                    <p><?php echo $this->_pt('After download finishes, you can continue browsing the site.'); ?></p>
                    <p><a href="<?php echo PHS::url(); ?>" class="btn btn-primary"><?php echo $this->_pt('Continue browsing the site'); ?></a></p>
                </div>
            </div>
            <script>
            function start_download()
            {
                $("#download_button_section").hide();
                $("#downloading_section").show();
            }
            </script>
            <?php
        }
?>

    </div>
</form>

