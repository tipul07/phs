<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;

/** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $libs_plugin */
/** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
/** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tfa $tfa_model */
if (!($libs_plugin = $this->view_var('libs_plugin'))
    || !($accounts_plugin = $this->view_var('accounts_plugin'))
    || !($tfa_model = $this->view_var('tfa_model'))) {
    return $this->_pt('Error loading required resources.');
}

if (!($tfa_arr = $this->view_var('tfa_data'))) {
    $tfa_arr = null;
}
?>
<div class="form_container">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Two Factor Authentication Settings'); ?></h3>
    </section>

    <?php
    if (empty($tfa_arr)
        || !$tfa_model->is_setup_completed($tfa_arr)) {
        ?>
        <div class="form-group row">
            <div class="col-sm-12">
                <p class="text-center"><strong><?php echo $this->_pt('You didn\'t set up yet Two Factor Authentication for your account.'); ?></strong></p>
                <p><?php echo $this->_pt('In order to strengthen your account security, it is recommended that you set up Two-Factor Authentication.'); ?></p>
                <p><a href="https://en.wikipedia.org/wiki/Help:Two-factor_authentication"
                      target="_blank"><?php echo $this->_pt('What Two-Factor Authentication (TFA) means?'); ?></a></p>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-sm-12">
                <a href="<?php echo PHS::url(['p' => 'accounts', 'ad' => 'tfa', 'a' => 'setup']); ?>"
                   class="btn btn-primary"><?php echo $this->_pte('Setup Two Factor Authentication'); ?></a>
            </div>
        </div>
        <?php
    }

if (!empty($tfa_arr)
    && $tfa_model->is_setup_completed($tfa_arr)
    && ($recovery_codes = $tfa_model->get_recovery_codes($tfa_arr))) {
    ?>
        <form id="tfa_settings_download" name="tfa_settings_download" method="post"
              action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'settings', 'ad' => 'tfa']); ?>">
            <input type="hidden" name="foobar" value="1" />
            <div id="download_button_section">
                <div class="form-group row">
                    <div class="col-sm-12">
                        <p><?php echo $this->_pt('In case you didn\'t download your recovery codes yet, you still can, by providing a two factor authentication verification code first.'); ?></p>
                    </div>
                </div>
                <div class="form-group row">
                    <div class="col-sm-12">
                        <input type="button" id="do_download_codes" name="do_download_codes"
                               onclick="start_download()" class="btn btn-primary"
                               value="<?php echo $this->_pte('Download Recovery Codes'); ?>" />
                    </div>
                </div>
            </div>
            <div id="downloading_section" style="display:none;">
                <div class="form-group row">
                    <div class="col-sm-12">
                        <p><?php echo $this->_pt('In order to download your recovery codes, please provide a two factor authentication verification code first.'); ?></p>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="tfa_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Verification Code'); ?></label>
                    <div class="col-sm-5">
                        <input type="text" id="tfa_code" name="tfa_code" autocomplete="tfa_code" class="form-control"
                               required="required" value="" />
                    </div>
                    <div class="col-sm-5">
                        <input type="submit" id="do_download_codes" name="do_download_codes"
                               onclick="cancel_download()"
                               class="btn btn-primary ignore_hidden_required"
                               value="<?php echo $this->_pte('Check Code'); ?>" />
                    </div>
                </div>
                <div class="form-group row">
                    <div class="col-sm-12">
                        <input type="button" id="do_cancel_download_codes" name="do_cancel_download_codes"
                               onclick="cancel_download()" class="btn btn-primary"
                               value="<?php echo $this->_pte('Cancel Download'); ?>" />
                    </div>
                </div>
            </div>
        </form>
        <script>
            function start_download()
            {
                $("#download_button_section").hide();
                $("#downloading_section").show();
            }
            function cancel_download()
            {
                $("#download_button_section").show();
                $("#downloading_section").hide();
            }
        </script>

        <form id="tfa_settings_disable" name="tfa_settings_disable" method="post"
              onsubmit="return confirm_cancel_tfa()"
              action="<?php echo PHS::url(['p' => 'accounts', 'a' => 'settings', 'ad' => 'tfa']); ?>">
            <input type="hidden" name="foobar" value="1" />

            <div id="cancel_tfa_button_section">
                <div class="form-group row">
                    <div class="col-sm-12">
                        <p><?php echo $this->_pt('Altough we don\'t recommend it, you can disable your Two Factor Authentication set up.'); ?></p>
                    </div>
                </div>
                <div class="form-group row">
                    <div class="col-sm-12">
                        <input type="button" id="do_start_cancel_tfa" name="do_start_cancel_tfa"
                               onclick="start_cancel_tfa()" class="btn btn-danger"
                               value="<?php echo $this->_pte('Disable Two Factor Authentication'); ?>" />
                    </div>
                </div>
            </div>

            <div id="cancel_tfa_section" style="display:none;">
                <div class="form-group row">
                    <div class="col-sm-12">
                        <p><?php echo $this->_pt('Altough we don\'t recommend it, you can disable your Two Factor Authentication set up after providing a verification code.'); ?></p>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="tfa_code" class="col-sm-2 col-form-label"><?php echo $this->_pt('Verification Code'); ?></label>
                    <div class="col-sm-5">
                        <input type="text" id="tfa_code_disable" name="tfa_code" autocomplete="tfa_code_disable" class="form-control"
                               required="required" value="" />
                    </div>
                    <div class="col-sm-5">
                        <input type="submit" id="do_cancel_tfa" name="do_cancel_tfa"
                               class="btn btn-danger"
                               value="<?php echo $this->_pte('Disable TFA Setup'); ?>" />
                    </div>
                </div>
                <div class="form-group row">
                    <div class="col-sm-12">
                        <input type="button" id="do_cancel_cancel_tfa" name="do_cancel_cancel_tfa"
                               onclick="stop_cancel_tfa()" class="btn btn-primary"
                               value="<?php echo $this->_pte('Cancel'); ?>" />
                    </div>
                </div>
            </div>
        </form>
        <script>
            function start_cancel_tfa()
            {
                $("#cancel_tfa_button_section").hide();
                $("#cancel_tfa_section").show();
            }
            function stop_cancel_tfa()
            {
                $("#cancel_tfa_button_section").show();
                $("#cancel_tfa_section").hide();
            }
            function confirm_cancel_tfa()
            {
                PHS_JSEN.js_messages_hide_all();

                if( $("#tfa_code_disable").val().length === 0 ) {
                    PHS_JSEN.js_messages( [ "<?php echo $this->_pt('Please provide a verification code first.'); ?>" ], "error" );
                    return false;
                }

                return confirm( "<?php echo $this::_e($this->_pt('Are you sure you want to disable TFA setup for your account?'), '"'); ?>" );
            }
        </script>
        <?php
}
?>
</div>


